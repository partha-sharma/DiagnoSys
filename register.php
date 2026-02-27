<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - DiagnoLab</title>
    <link rel="stylesheet" href="/DiagnoSys/assets/css/style.css">
</head>
<body class="auth-body">

    <div class="auth-box register-box">
        <div class="logo-header">
            <div class="logo-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" role="img" aria-label="DiagnoLab logo">
                    <path d="M6 3h12" />
                    <path d="M10 3v7l-5 9a2 2 0 0 0 1.76 3h10.48A2 2 0 0 0 19 19l-5-9V3" />
                    <path d="M8 15h8" />
                </svg>
            </div>
            <h2>DiagnoLab</h2>
        </div>
        
        <h3 class="form-title">Create Account</h3>
        <p class="form-subtitle">Register as a patient to book tests</p>

        <?php
        session_start();
        if (isset($_SESSION['errors'])) {
            echo '<div class="alert alert-error">';
            foreach ($_SESSION['errors'] as $error) {
                echo '<p>• ' . htmlspecialchars($error) . '</p>';
            }
            echo '</div>';
            unset($_SESSION['errors']);
        }
        if (isset($_SESSION['success'])) {
            echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
            unset($_SESSION['success']);
        }
        ?>

        <form action="register-process.php" method="POST">
            <label>Full Name</label>
            <input type="text" name="fullname" placeholder="Enter your full name" value="<?php echo isset($_SESSION['old_input']['fullname']) ? htmlspecialchars($_SESSION['old_input']['fullname']) : ''; ?>" required>

            <label>Email Address</label>
            <input type="email" name="email" placeholder="Enter your email" value="<?php echo isset($_SESSION['old_input']['email']) ? htmlspecialchars($_SESSION['old_input']['email']) : ''; ?>" required>

            <label>Phone Number</label>
            <input type="tel" name="phone" placeholder="Enter your phone number" value="<?php echo isset($_SESSION['old_input']['phone']) ? htmlspecialchars($_SESSION['old_input']['phone']) : ''; ?>" required>

            <!-- ADDED ADDRESS FIELD PER ERD -->
            <label>Address</label>
            <textarea name="address" placeholder="Enter your full address" rows="2" required><?php echo isset($_SESSION['old_input']['address']) ? htmlspecialchars($_SESSION['old_input']['address']) : ''; ?></textarea>

            <label>Password</label>
            <input type="password" name="password" placeholder="Create a password" required>

            <label>Confirm Password</label>
            <input type="password" name="confirm_password" placeholder="Confirm your password" required>

            <div class="terms-checkbox">
                <label>
                    <input type="checkbox" required>
                    I agree to the <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a>
                </label>
            </div>

            <button type="submit" class="btn-primary full">Create Account</button>
        </form>

        <div class="footer-text">
            Already have an account? <a href="login.php">Login here</a>
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
</body>
</html>