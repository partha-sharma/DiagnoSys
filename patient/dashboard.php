<?php
require_once __DIR__ . '/../config/init.php';

// 1. GATEKEEPER
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: ../auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// 2. THE ULTIMATE QUERY: Joins appointments, tests, and results
$sql = "SELECT 
            a.appointment_id, a.appointment_date, a.status,
            GROUP_CONCAT(t.test_name SEPARATOR ', ') AS test_names,
            tr.file_path,
            p.status AS payment_status,
            r.review_id
    FROM appointments a
        LEFT JOIN appointment_tests apt ON a.appointment_id = apt.appointment_id
        LEFT JOIN tests t ON apt.test_id = t.test_id
        LEFT JOIN test_results tr ON a.appointment_id = tr.appointment_id
        LEFT JOIN (
            SELECT appointment_id, MAX(payment_id) AS latest_payment_id
            FROM payments
            GROUP BY appointment_id
        ) lp ON a.appointment_id = lp.appointment_id
        LEFT JOIN payments p ON lp.latest_payment_id = p.payment_id
        LEFT JOIN reviews r ON a.appointment_id = r.appointment_id AND r.user_id = a.user_id
        WHERE a.user_id = ?
        GROUP BY a.appointment_id
        ORDER BY a.appointment_date DESC";

$stmt = $conn->prepare($sql);
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
            <a href="../auth/logout.php" class="btn-outline">Logout</a>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <div>
                <h1>Patient Dashboard</h1>
                <p>Manage your appointments and view test reports</p>
            </div>
            <div class="nav-buttons">
                <a href="profile.php" class="btn-outline">My Profile</a>
                <a href="book-appointment.php" class="btn-primary">Book New Appointment</a>
            </div>
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

        <div class="quick-actions">
            <div class="action-card">
                <div class="action-icon">👤</div>
                <h3>Update Profile</h3>
                <p>Manage your details, profile photo, and account recovery options.</p>
                <a href="profile.php" class="btn-primary">Open Profile & Recovery</a>
            </div>
            <div class="action-card">
                <div class="action-icon">💳</div>
                <h3>Payments & Invoices</h3>
                <p>Complete pending payments and view invoice records.</p>
                <a href="payments.php" class="btn-primary">Open Payments</a>
            </div>
            <div class="action-card">
                <div class="action-icon">⭐</div>
                <h3>Review & Rating</h3>
                <p>Rate completed appointments and submit feedback.</p>
                <a href="reviews.php" class="btn-primary">Write Review</a>
            </div>
        </div>

        <div class="appointments-section">
            <h2>Your Appointments</h2>

            <?php if ($appointments->num_rows > 0): ?>
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Appt. ID</th>
                            <th>Date & Time</th>
                            <th>Tests Booked</th>
                            <th>Status</th>
                            <th>Report</th>
                            <th>Payment</th>
                            <th>Review</th>
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
                                <td><?php echo htmlspecialchars($row['test_names'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($row['status']); ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($row['file_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($row['file_path']); ?>" class="btn-primary" style="padding: 6px 14px; font-size: 13px;" download>Download</a>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 13px;">Not Ready</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['payment_status'] === 'Completed'): ?>
                                        <a href="invoice.php?appointment_id=<?php echo (int)$row['appointment_id']; ?>" class="btn-outline" style="padding:6px 12px; font-size:13px;">Invoice</a>
                                    <?php elseif ($row['status'] === 'Cancelled'): ?>
                                        <span style="color:#94a3b8; font-size:13px;">N/A</span>
                                    <?php else: ?>
                                        <a href="payment.php?appointment_id=<?php echo (int)$row['appointment_id']; ?>" class="btn-primary" style="padding:6px 12px; font-size:13px;">Pay Now</a>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['status'] === 'Completed'): ?>
                                        <a href="reviews.php?appointment_id=<?php echo (int)$row['appointment_id']; ?>" class="btn-outline" style="padding:6px 12px; font-size:13px;">
                                            <?php echo !empty($row['review_id']) ? 'Update' : 'Add'; ?>
                                        </a>
                                    <?php else: ?>
                                        <span style="color:#94a3b8; font-size:13px;">After completion</span>
                                    <?php endif; ?>
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

