<?php
require_once 'config/init.php';

$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$tokenValid = false;
$errorText = '';

if ($token !== '') {
    $stmt = $conn->prepare("SELECT user_id, reset_expiry FROM users WHERE reset_token = ? LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (!empty($row['reset_expiry']) && strtotime($row['reset_expiry']) >= time()) {
            $tokenValid = true;
        } else {
            $errorText = 'Reset token has expired. Please request a new one.';
        }
    } else {
        $errorText = 'Invalid reset token.';
    }

    $stmt->close();
} else {
    $errorText = 'Missing reset token.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $errors = [];

    if (!$tokenValid) {
        $errors[] = $errorText ?: 'Invalid reset token.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expiry = NULL WHERE reset_token = ?");
        $update->bind_param('ss', $hash, $token);
        $update->execute();

        if ($update->affected_rows > 0) {
            $_SESSION['success'] = 'Password updated successfully. Please login.';
            $update->close();
            $conn->close();
            header('Location: login.php');
            exit();
        }

        $update->close();
        $errors[] = 'Could not update password. Try again.';
    }

    $_SESSION['errors'] = $errors;
    header('Location: reset-password.php?token=' . urlencode($token));
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - DiagnoLab</title>
    <link rel="stylesheet" href="/DiagnoSys/assets/css/style.css">
</head>
<body class="auth-body">
    <div class="auth-box">
        <h2>Reset Password</h2>
        <p>Create a new password for your account</p>

        <?php
        if (isset($_SESSION['errors'])) {
            echo '<div class="alert alert-error">';
            foreach ($_SESSION['errors'] as $error) {
                echo '<p>• ' . htmlspecialchars($error) . '</p>';
            }
            echo '</div>';
            unset($_SESSION['errors']);
        }
        ?>

        <?php if (!$tokenValid): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($errorText); ?></div>
            <a href="forgot-password.php" class="btn-primary full" style="display:inline-block; text-align:center;">Request New Link</a>
        <?php else: ?>
            <form action="reset-password.php" method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                <label>New Password</label>
                <input type="password" name="password" required minlength="6" placeholder="Enter new password">

                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required minlength="6" placeholder="Confirm new password">

                <button type="submit" class="btn-primary full">Update Password</button>
            </form>
        <?php endif; ?>

        <div class="back">
            <a href="login.php">← Back to Login</a>
        </div>
    </div>
</body>
</html>
