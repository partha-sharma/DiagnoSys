<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - DiagnoLab</title>
    <link rel="stylesheet" href="/DiagnoSys/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="auth-body">

    <div class="auth-box register-box">
        <div class="logo-header">
            <div class="logo-icon"><i class="fa-solid fa-flask" style="font-size: 28px;"></i></div>
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

        if (isset($_SESSION['verify_link'])) {
            echo '<div class="alert alert-success">'
                . 'Verification link (local testing): '
                . '<a href="' . htmlspecialchars($_SESSION['verify_link']) . '">Verify Now</a>'
                . '</div>';
            unset($_SESSION['verify_link']);
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
            <input type="text" name="address" placeholder="Enter your full address" value="<?php echo isset($_SESSION['old_input']['address']) ? htmlspecialchars($_SESSION['old_input']['address']) : ''; ?>" required>

            <label>Password</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="password" placeholder="Create a password" required>
                <button type="button" class="toggle-password" onclick="togglePassword('password', this)" aria-label="Toggle password visibility">
                    <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                </button>
            </div>

            <label>Confirm Password</label>
            <div class="password-wrapper">
                <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm your password" required>
                <button type="button" class="toggle-password" onclick="togglePassword('confirm_password', this)" aria-label="Toggle password visibility">
                    <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                </button>
            </div>

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