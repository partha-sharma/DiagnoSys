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
            <div class="logo-icon">🧪</div>
            <h2>DiagnoLab</h2>
        </div>
        
        <h3 class="form-title">Create Account</h3>
        <p class="form-subtitle">Register as a patient to book tests</p>

        <form action="register-process.php" method="POST">
            <label>Full Name</label>
            <input type="text" name="fullname" placeholder="Enter your full name" required>

            <label>Email Address</label>
            <input type="email" name="email" placeholder="Enter your email" required>

            <label>Phone Number</label>
            <input type="tel" name="phone" placeholder="Enter your phone number" required>

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

</body>
</html>