<?php
session_start();

$prefillEmail = trim($_GET['email'] ?? '');
$source = trim($_GET['from'] ?? '');

if (isset($_SESSION['old_forgot_email'])) {
    $prefillEmail = (string) $_SESSION['old_forgot_email'];
    unset($_SESSION['old_forgot_email']);
}

$isProfileSource = $source === 'profile';
$returnPath = $isProfileSource ? '../patient/profile.php' : '../index.php';
$returnLabel = $isProfileSource ? 'Back to Profile' : 'Back to Home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - DiagnoLab</title>
    <link rel="stylesheet" href="/DiagnoSys/assets/css/style.css">
</head>
<body class="auth-body">
    <div class="auth-box">
        <h2>Forgot Password</h2>
        <p>Enter your account email to reset password</p>

        <?php if (isset($_SESSION['errors'])): ?>
            <div class="alert alert-error">
                <?php foreach ($_SESSION['errors'] as $error): ?>
                    <p>• <?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
            <?php unset($_SESSION['errors']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); ?></div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['reset_link'])): ?>
            <div class="alert alert-success">
                Reset link (local testing):
                <a href="<?php echo htmlspecialchars($_SESSION['reset_link']); ?>">Open Reset Page</a>
            </div>
            <?php unset($_SESSION['reset_link']); ?>
        <?php endif; ?>

        <form action="forgot-password-process.php" method="POST">
            <input type="hidden" name="source" value="<?php echo htmlspecialchars($source); ?>">
            <label>Email Address</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($prefillEmail); ?>" required placeholder="Enter your registered email">

            <button type="submit" class="btn-primary full">Send Reset Link</button>
        </form>

        <div class="footer-text">
            Remembered your password? <a href="login.php">Login here</a>
        </div>

        <div class="back">
            <a href="<?php echo htmlspecialchars($returnPath); ?>">← <?php echo htmlspecialchars($returnLabel); ?></a>
        </div>
    </div>
</body>
</html>

