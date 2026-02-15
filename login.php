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

        <form action="login-process.php" method="POST">
            <label>Login As</label>
            <select name="role" required>
                <option value="patient">Patient</option>
                <option value="admin">Admin</option>
            </select>

            <label>Email Address</label>
            <input type="email" name="email" placeholder="Enter your email" required>

            <label>Password</label>
            <input type="password" name="password" placeholder="Enter your password" required>

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

</body>
</html>