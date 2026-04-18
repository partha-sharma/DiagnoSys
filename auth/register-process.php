<?php
require '../config/init.php'; // Ensure this file connects to DB using $conn

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']); // New Field
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    if (empty($fullname)) $errors[] = "Full name is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($address)) $errors[] = "Address is required"; // Validate Address
    
    // Check if email exists in USERS table
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $errors[] = "Email already registered";
    }
    $stmt->close();
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $email_token = bin2hex(random_bytes(32));
        $email_token_expiry = date('Y-m-d H:i:s', strtotime('+1 day'));
        
        // Create user as unverified and save a verification token.
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, address, password, email_verified, email_token, email_token_expiry) VALUES (?, ?, ?, ?, ?, 0, ?, ?)");
        $stmt->bind_param("sssssss", $fullname, $email, $phone, $address, $hashed_password, $email_token, $email_token_expiry);
        
        if ($stmt->execute()) {
            $verify_link = "http://localhost/DiagnoSys/auth/verify-email.php?token=" . urlencode($email_token);
            $_SESSION['success'] = "Account created! Please verify your email before login.";
            $_SESSION['verify_link'] = $verify_link;
            $stmt->close();
            $conn->close();
            header("Location: register.php");
            exit();
        } else {
            $errors[] = "Registration failed: " . $conn->error;
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['old_input'] = $_POST;
        header("Location: register.php");
        exit();
    }
} else {
    header("Location: register.php");
    exit();
}
?>