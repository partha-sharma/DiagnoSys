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

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email is required';
}

if (empty($password)) {
    $errors[] = 'Password is required';
}

if (!in_array($role, ['patient', 'admin'], true)) {
    $errors[] = 'Invalid role selected';
}

if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old_input'] = ['email' => $email, 'role' => $role];
    header('Location: login.php');
    exit();
}

$stmt = $conn->prepare('SELECT id, full_name, email, password, role FROM users WHERE email = ? AND role = ? LIMIT 1');
$stmt->bind_param('ss', $email, $role);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['errors'] = ['Invalid email or role'];
    $_SESSION['old_input'] = ['email' => $email, 'role' => $role];
    $stmt->close();
    header('Location: login.php');
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

$storedPassword = $user['password'];
$isPasswordValid = false;

if (password_verify($password, $storedPassword)) {
    $isPasswordValid = true;
} elseif (hash_equals($storedPassword, $password)) {
    $isPasswordValid = true;
    // Upgrade plain-text password to hashed for security
    $newHash = password_hash($password, PASSWORD_DEFAULT);
    $updateStmt = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
    $updateStmt->bind_param('si', $newHash, $user['id']);
    $updateStmt->execute();
    $updateStmt->close();
}

if (!$isPasswordValid) {
    $_SESSION['errors'] = ['Invalid password'];
    $_SESSION['old_input'] = ['email' => $email, 'role' => $role];
    header('Location: login.php');
    exit();
}

$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['full_name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role'] = $user['role'];

header('Location: dashboard.php');
exit();
