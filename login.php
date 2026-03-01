<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DiagnoLab</title>
    <link rel="stylesheet" href="/DiagnoSys/assets/css/style.css">
</head>
<body class="auth-body">

    <div class="auth-box">
        <h2>Welcome Back</h2>
        <p>Login to access your account</p>

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

        <form action="login-process.php" method="POST">
            <label>Login As</label>
            <select name="role" required>
                <option value="patient" <?php echo (isset($_SESSION['old_input']['role']) && $_SESSION['old_input']['role'] === 'patient') ? 'selected' : ''; ?>>Patient</option>
                <option value="admin" <?php echo (isset($_SESSION['old_input']['role']) && $_SESSION['old_input']['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
            </select>

            <label>Email Address</label>
            <input type="email" name="email" placeholder="Enter your email" value="<?php echo isset($_SESSION['old_input']['email']) ? htmlspecialchars($_SESSION['old_input']['email']) : ''; ?>" required>

            <label>Password</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="password" placeholder="Enter your password" required>
                <button type="button" class="toggle-password" onclick="togglePassword('password', this)" aria-label="Toggle password visibility">
                    <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                </button>
            </div>

            <div class="remember">
                <label><input type="checkbox"> Remember me</label>
                <a href="forgot-password.php">Forgot password?</a>
            </div>

            <button type="submit" class="btn-primary full">Login</button>
        </form>

        <div class="footer-text">
            Don't have an account? <a href="register.php">Register here</a>
        </div>

        <div class="back">
            <a href="index.php">← Back to Home</a>
        </div>
    </div>

<?php
if (isset($_SESSION['old_input'])) {
    unset($_SESSION['old_input']);
}
?>

<script>
function togglePassword(fieldId, btn) {
    const input = document.getElementById(fieldId);
    const eyeOpen = btn.querySelector('.eye-open');
    const eyeClosed = btn.querySelector('.eye-closed');
    if (input.type === 'password') {
        input.type = 'text';
        eyeOpen.style.display = 'none';
        eyeClosed.style.display = 'block';
    } else {
        input.type = 'password';
        eyeOpen.style.display = 'block';
        eyeClosed.style.display = 'none';
    }
}
</script>
</body>
</html>