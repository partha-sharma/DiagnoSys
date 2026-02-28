<?php
require_once 'config/init.php';

// 1. GATEKEEPER
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// 2. REAL DATABASE QUERY (Member 3 Work)
$stmt = $conn->prepare("
    SELECT appointment_id, appointment_date, status 
    FROM appointments 
    WHERE user_id = ? 
    ORDER BY appointment_date DESC
");
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

    <div class="navbar">
        <div class="logo">DiagnoLab</div>
        <div class="nav-buttons">
            <span style="margin-right: 20px;">
                Welcome, <?php echo htmlspecialchars($user_name); ?>
            </span>
            <a href="logout.php" class="btn-outline">Logout</a>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <div>
                <h1>Patient Dashboard</h1>
                <p>Manage your appointments and view test reports</p>
            </div>
            <a href="book-appointment.php" class="btn-primary">
                Book New Appointment
            </a>
        </div>

        <?php
        if (isset($_SESSION['success'])) {
            echo '<div class="alert alert-success">' 
                . htmlspecialchars($_SESSION['success']) . 
                '</div>';
            unset($_SESSION['success']);
        }

        if (isset($_SESSION['error'])) {
            echo '<div class="alert alert-error">' 
                . htmlspecialchars($_SESSION['error']) . 
                '</div>';
            unset($_SESSION['error']);
        }
        ?>

        <div class="appointments-section">
            <h2>Your Appointments</h2>

            <?php if ($appointments->num_rows > 0): ?>
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Appt. ID</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $appointments->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row['appointment_id']; ?></td>
                                <td>
                                    <?php 
                                    echo date(
                                        'M d, Y @ h:i A', 
                                        strtotime($row['appointment_date'])
                                    ); 
                                    ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <p>You have no appointments scheduled.</p>
                    <a href="book-appointment.php" class="btn-primary">
                        Book Your First Appointment
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>

<?php $conn->close(); ?>