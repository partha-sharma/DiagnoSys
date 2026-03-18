<?php
require_once 'config/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: forgot-password.php');
    exit();
}

$email = trim($_POST['email'] ?? '');
$errors = [];

if ($email === '') {
    $errors[] = 'Email is required.';
}

if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    header('Location: forgot-password.php');
    exit();
}

$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));

    $update = $conn->prepare("UPDATE users SET reset_token = ?, reset_expiry = ? WHERE user_id = ?");
    $update->bind_param('ssi', $token, $expiry, $user['user_id']);
    $update->execute();
    $update->close();

    $_SESSION['reset_link'] = 'http://localhost/DiagnoSys/reset-password.php?token=' . urlencode($token);
}

$stmt->close();
$conn->close();

$_SESSION['success'] = 'If the email exists, a reset link has been generated.';
header('Location: forgot-password.php');
exit();
