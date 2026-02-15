<?php
require 'config/init.php';



// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validate full name
    if (empty($fullname)) {
        $errors[] = "Full name is required";
    }
    
    // Validate email
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $errors[] = "Email already registered";
    }
    $stmt->close();
    
    // Validate phone
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }
    
    // Validate password
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    // Validate password confirmation
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        // Hash the password for security
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Prepare insert statement
        $stmt = $conn->prepare("INSERT INTO users (full_name, email, phone, password, role) VALUES (?, ?, ?, ?, 'patient')");
        $stmt->bind_param("ssss", $fullname, $email, $phone, $hashed_password);
        
        if ($stmt->execute()) {
            // Get the newly created user ID
            $user_id = $stmt->insert_id;
            
            // Automatically log in the user
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $fullname;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = 'patient';
            $_SESSION['success'] = "Account created successfully! Welcome to DiagnoLab.";
            
            $stmt->close();
            $conn->close();
            header("Location: dashboard.php");
            exit();
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
        $stmt->close();
    }
    
    // If there are errors, store them in session and redirect back
    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        $_SESSION['old_input'] = $_POST;
        $conn->close();
        header("Location: register.php");
        exit();
    }
} else {
    // If not POST request, redirect to register page
    header("Location: register.php");
    exit();
}
?>
