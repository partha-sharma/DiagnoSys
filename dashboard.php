<?php
require_once 'config/init.php';

// 1. GATEKEEPER
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// --- TEMPORARY FIX FOR MEMBER 1 ---
// We are NOT checking the database for appointments yet because 
// Member 3 hasn't created the table. We pretend there are 0 appointments.
$appointments = false; 
// ----------------------------------
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

    <div class="navbar">
        <div class="logo">DiagnoLab</div>
        <div class="nav-buttons">
            <span style="margin-right: 20px;">Welcome, <?php echo htmlspecialchars($user_name); ?></span>
            <a href="logout.php" class="btn-outline">Logout</a>
        </div>
    </div>

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

        <div class="appointments-section">
            <h2>Your Appointments</h2>

            <?php if ($appointments): ?>
                <!-- This part is hidden until Member 3 builds the table -->
                <table>...</table>
            <?php else: ?>
                <div class="empty-state">
                    <p>No appointments found (Database table not created yet).</p>
                    <a href="#" class="btn-primary">Book Your First Appointment</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
<?php $conn->close(); ?>