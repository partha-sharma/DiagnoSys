<?php
require 'config/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'patient';

$errors = [];

if (empty($email)) $errors[] = 'Email is required';
if (empty($password)) $errors[] = 'Password is required';

if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    header('Location: login.php');
    exit();
}

// BRANCHING LOGIC: Check different tables based on Role
if ($role === 'admin') {
    // Check ADMINS table
    $sql = "SELECT admin_id, username, email, password FROM admins WHERE email = ? LIMIT 1";
} else {
    // Check USERS table
    $sql = "SELECT user_id, full_name, email, password FROM users WHERE email = ? LIMIT 1";
}

$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['errors'] = ['Invalid email or role selection'];
    header('Location: login.php');
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Password Verification
if (password_verify($password, $user['password'])) {
    // Login Success
    
    $_SESSION['role'] = $role; // 'admin' or 'patient'

    if ($role === 'admin') {
        $_SESSION['user_id'] = $user['admin_id'];
        $_SESSION['user_name'] = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        header('Location: admin/index.php');
    } else {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        header('Location: dashboard.php');
    }
    exit();

} else {
    $_SESSION['errors'] = ['Invalid password'];
    header('Location: login.php');
    exit();
}
?>