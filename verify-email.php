<?php
require_once 'config/init.php';

$token = trim($_GET['token'] ?? '');
$message = '';
$type = 'error';

if ($token === '') {
    $message = 'Missing verification token.';
} else {
    $stmt = $conn->prepare("SELECT user_id, email_verified, email_token_expiry FROM users WHERE email_token = ? LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $message = 'Invalid verification token.';
    } else {
        $user = $result->fetch_assoc();

        if ((int)$user['email_verified'] === 1) {
            $message = 'Email is already verified. Please login.';
            $type = 'success';
        } elseif (!empty($user['email_token_expiry']) && strtotime($user['email_token_expiry']) < time()) {
            $message = 'Verification token has expired. Please request a new one from login page.';
        } else {
            $update = $conn->prepare("UPDATE users SET email_verified = 1, email_token = NULL, email_token_expiry = NULL WHERE user_id = ?");
            $update->bind_param('i', $user['user_id']);
            $update->execute();
            $update->close();

            $message = 'Email verified successfully. You can now login.';
            $type = 'success';
        }
    }

    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - DiagnoLab</title>
    <link rel="stylesheet" href="/DiagnoSys/assets/css/style.css">
</head>
<body class="auth-body">
    <div class="auth-box">
        <h2>Email Verification</h2>
        <p>Account confirmation status</p>

        <div class="alert <?php echo $type === 'success' ? 'alert-success' : 'alert-error'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>

        <a href="login.php" class="btn-primary full" style="display: inline-block; text-align: center;">Go to Login</a>
        <div class="back">
            <a href="index.php">← Back to Home</a>
        </div>
    </div>
</body>
</html>
