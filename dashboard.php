<?php
require 'config/init.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// Get user's appointments
$stmt = $conn->prepare("SELECT * FROM appointments WHERE user_id = ? ORDER BY appointment_date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$appointments = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - DiagnoLab</title>
    <link rel="stylesheet" href="/DiagnoSys/assets/css/style.css">
</head>
<body>

    <!-- Navbar -->
    <div class="navbar">
        <div class="logo">
            <svg class="hero-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.75h4.5m-4.5 0v5.25a4.5 4.5 0 0 1-1.318 3.182L5.25 15.364a2.25 2.25 0 0 0 1.591 3.886h10.318a2.25 2.25 0 0 0 1.591-3.886l-3.182-3.182A4.5 4.5 0 0 1 14.25 9V3.75m-4.5 0h4.5" />
            </svg>
            DiagnoLab
        </div>
        <div class="nav-buttons">
            <span style="margin-right: 20px; color: #64748b;">Welcome, <?php echo htmlspecialchars($user_name); ?></span>
            <a href="dashboard.php" class="btn-primary">Dashboard</a>
            <a href="logout.php" class="btn-outline">Logout</a>
        </div>
    </div>

    <!-- Dashboard Content -->
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Patient Dashboard</h1>
            <p>Manage your appointments and view test reports</p>
        </div>

        <?php
        if (isset($_SESSION['success'])) {
            echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
            unset($_SESSION['success']);
        }
        ?>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="action-card">
                <div class="action-icon" aria-hidden="true">
                    <svg class="hero-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2Z" />
                    </svg>
                </div>
                <h3>Book Appointment</h3>
                <p>Schedule a new diagnostic test</p>
                <a href="book-appointment.php" class="btn-primary">Book Now</a>
            </div>
            <div class="action-card">
                <div class="action-icon" aria-hidden="true">
                    <svg class="hero-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18M9 17V9m4 8V5m4 12v-6" />
                    </svg>
                </div>
                <h3>View Reports</h3>
                <p>Access your test reports</p>
                <a href="reports.php" class="btn-primary">View Reports</a>
            </div>
            <div class="action-card">
                <div class="action-icon" aria-hidden="true">
                    <svg class="hero-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 7.5a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" />
                    </svg>
                </div>
                <h3>My Profile</h3>
                <p>Update your information</p>
                <a href="profile.php" class="btn-primary">Edit Profile</a>
            </div>
        </div>

        <!-- Recent Appointments -->
        <div class="appointments-section">
            <h2>Your Appointments</h2>
            
            <?php if ($appointments->num_rows > 0): ?>
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Test Name</th>
                            <th>Appointment Date</th>
                            <th>Status</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($appointment = $appointments->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $appointment['id']; ?></td>
                                <td><?php echo htmlspecialchars($appointment['test_name']); ?></td>
                                <td><?php echo date('M d, Y h:i A', strtotime($appointment['appointment_date'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($appointment['status']); ?>">
                                        <?php echo $appointment['status']; ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($appointment['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>
                        <svg class="hero-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true" style="vertical-align: middle; margin-right: 8px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m-9 4.5h12a2.25 2.25 0 0 0 2.25-2.25V5.25A2.25 2.25 0 0 0 18 3H6a2.25 2.25 0 0 0-2.25 2.25v12A2.25 2.25 0 0 0 6 19.5Z" />
                        </svg>
                        No appointments yet
                    </p>
                    <a href="book-appointment.php" class="btn-primary">Book Your First Appointment</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php require 'partials/footer.php'; ?>

</body>
</html>
<?php $conn->close(); ?>
