<?php
require_once 'config/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$email = trim($_POST['email'] ?? '');

if ($email === '') {
    $_SESSION['errors'] = ['Email is required to resend verification.'];
    header('Location: login.php');
    exit();
}

$stmt = $conn->prepare("SELECT user_id, email_verified FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['errors'] = ['No patient account found with this email.'];
    $_SESSION['old_input'] = ['email' => $email, 'role' => 'patient'];
    header('Location: login.php');
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

if ((int)$user['email_verified'] === 1) {
    $_SESSION['success'] = 'Your email is already verified. Please login.';
    header('Location: login.php');
    exit();
}

$token = bin2hex(random_bytes(32));
$expiry = date('Y-m-d H:i:s', strtotime('+1 day'));
$update = $conn->prepare("UPDATE users SET email_token = ?, email_token_expiry = ? WHERE user_id = ?");
$update->bind_param('ssi', $token, $expiry, $user['user_id']);
$update->execute();
$update->close();

$_SESSION['success'] = 'Verification link generated for local testing.';
$_SESSION['verify_link'] = 'http://localhost/DiagnoSys/verify-email.php?token=' . urlencode($token);

$conn->close();
header('Location: login.php');
exit();
