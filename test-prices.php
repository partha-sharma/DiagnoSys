<?php
require_once 'config/db.php';
$tests = $conn->query("SELECT test_name, description, price FROM tests WHERE status = 'Active' ORDER BY test_name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Prices - DiagnoLab</title>
    <link rel="stylesheet" href="/DiagnoSys/assets/css/style.css">
</head>
<body>

    <!-- Navbar -->
    <nav class="navbar">
        <a href="index.php" class="logo">
            <svg class="hero-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.75h4.5m-4.5 0v5.25a4.5 4.5 0 0 1-1.318 3.182L5.25 15.364a2.25 2.25 0 0 0 1.591 3.886h10.318a2.25 2.25 0 0 0 1.591-3.886l-3.182-3.182A4.5 4.5 0 0 1 14.25 9V3.75m-4.5 0h4.5" />
            </svg>
            DiagnoLab
        </a>
        <div class="nav-buttons">
            <a href="login.php" class="btn-outline">Login</a>
            <a href="register.php" class="btn-primary">Register</a>
        </div>
    </nav>

    <!-- Test Prices -->
    <section class="test-prices" id="test-prices">
        <div class="test-prices-nav">
            <a href="index.php" class="btn-back-dashboard">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
                Homepage
            </a>
        </div>
        <h2>Our Test Prices</h2>
        <p>Transparent pricing for all diagnostic tests</p>
        <div class="price-grid">
            <?php if ($tests && $tests->num_rows > 0): ?>
                <?php while ($test = $tests->fetch_assoc()): ?>
                <div class="price-card">
                    <div class="price-card-icon">🧪</div>
                    <h3><?php echo htmlspecialchars($test['test_name']); ?></h3>
                    <p class="price-desc"><?php echo htmlspecialchars($test['description']); ?></p>
                    <div class="price-tag">৳<?php echo number_format($test['price'], 0); ?></div>
                    <a href="register.php" class="btn-primary">Book Now</a>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="no-tests">No tests available at the moment.</p>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <p>&copy; 2026 DiagnoLab. All rights reserved.</p>
    </footer>

</body>
</html>
<?php $conn->close(); ?>
