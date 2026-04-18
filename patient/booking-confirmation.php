<?php
require_once __DIR__ . '/../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../auth/login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$appointment_id = (int)($_GET['appointment_id'] ?? 0);

if ($appointment_id <= 0) {
    $_SESSION['error'] = 'Invalid booking confirmation request.';
    header('Location: dashboard.php');
    exit();
}

$appointmentStmt = $conn->prepare("SELECT a.appointment_id, a.appointment_date, a.status, a.total_amount,
                                         p.payment_method, p.payment_date,
                                         u.full_name, u.email
                                  FROM appointments a
                                  LEFT JOIN payments p ON a.appointment_id = p.appointment_id AND p.status = 'Completed'
                                  INNER JOIN users u ON a.user_id = u.user_id
                                  WHERE a.appointment_id = ? AND a.user_id = ?
                                  ORDER BY p.payment_id DESC
                                  LIMIT 1");
$appointmentStmt->bind_param('ii', $appointment_id, $user_id);
$appointmentStmt->execute();
$appointment = $appointmentStmt->get_result()->fetch_assoc();
$appointmentStmt->close();

if (!$appointment) {
    $_SESSION['error'] = 'Appointment not found.';
    header('Location: dashboard.php');
    exit();
}

$planStmt = $conn->prepare("SELECT sequence_no, test_name_snapshot, room_number_snapshot, slot_label_snapshot, estimated_at, status
                           FROM appointment_test_plan
                           WHERE appointment_id = ?
                           ORDER BY sequence_no ASC");
$planStmt->bind_param('i', $appointment_id);
$planStmt->execute();
$planResult = $planStmt->get_result();
$planRows = [];
while ($row = $planResult->fetch_assoc()) {
    $planRows[] = $row;
}
$planStmt->close();

$notifStmt = $conn->prepare("SELECT channel, recipient, status, created_at
                            FROM notification_logs
                            WHERE appointment_id = ?
                            ORDER BY notification_id DESC");
$notifStmt->bind_param('i', $appointment_id);
$notifStmt->execute();
$notifResult = $notifStmt->get_result();
$notifications = [];
while ($row = $notifResult->fetch_assoc()) {
    $notifications[] = $row;
}
$notifStmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation - DiagnoLab</title>
    <link rel="stylesheet" href="/DiagnoSys/assets/css/style.css">
</head>
<body>
<div class="navbar">
    <a href="dashboard.php" class="logo">DiagnoLab</a>
    <div class="nav-buttons">
        <a href="dashboard.php" class="btn-outline">Dashboard</a>
        <a href="payments.php" class="btn-outline">Payments</a>
        <a href="../auth/logout.php" class="btn-primary">Logout</a>
    </div>
</div>

<div class="dashboard-container">
    <div class="dashboard-header">
        <div>
            <h1>Booking Confirmed</h1>
            <p>Your payment is complete and your test execution order is now locked.</p>
        </div>
        <div>
            <a class="btn-outline" href="invoice.php?appointment_id=<?php echo (int)$appointment_id; ?>">View Invoice</a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="appointments-section" style="margin-bottom:16px;">
        <h2>Confirmation Details</h2>
        <div class="confirmation-meta-grid">
            <div><strong>Booking ID:</strong> #<?php echo (int)$appointment['appointment_id']; ?></div>
            <div><strong>Patient Name:</strong> <?php echo htmlspecialchars($appointment['full_name']); ?></div>
            <div><strong>Scheduled Date:</strong> <?php echo date('M d, Y h:i A', strtotime($appointment['appointment_date'])); ?></div>
            <div><strong>Payment Method:</strong> <?php echo htmlspecialchars($appointment['payment_method'] ?? 'N/A'); ?></div>
            <div><strong>Total Paid:</strong> ৳<?php echo number_format((float)$appointment['total_amount'], 2); ?></div>
            <div><strong>Status:</strong> <?php echo htmlspecialchars($appointment['status']); ?></div>
        </div>
        <p class="confirmation-instruction">Please arrive 15 minutes early and follow the sequence below. Room and time assignments are generated from active admin mappings at payment time and saved as your snapshot plan.</p>
    </div>

    <div class="appointments-section">
        <h2>Ordered Test Execution Plan</h2>
        <?php if (!empty($planRows)): ?>
            <div class="rooms-grid-wrap">
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Order</th>
                            <th>Test Name</th>
                            <th>Room Number</th>
                            <th>Time Slot</th>
                            <th>Estimated Time</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($planRows as $plan): ?>
                            <tr>
                                <td>#<?php echo (int)$plan['sequence_no']; ?></td>
                                <td><?php echo htmlspecialchars($plan['test_name_snapshot']); ?></td>
                                <td><?php echo htmlspecialchars($plan['room_number_snapshot'] ?? 'To Be Assigned'); ?></td>
                                <td><?php echo htmlspecialchars($plan['slot_label_snapshot'] ?? 'To Be Assigned'); ?></td>
                                <td><?php echo !empty($plan['estimated_at']) ? date('M d, Y h:i A', strtotime($plan['estimated_at'])) : 'To Be Assigned'; ?></td>
                                <td><span class="status-badge status-confirmed"><?php echo htmlspecialchars($plan['status']); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-error">No execution plan is available yet. Please contact support.</div>
        <?php endif; ?>
    </div>

    <div class="appointments-section" style="margin-top:16px;">
        <h2>Patient Notification Log</h2>
        <p style="margin-bottom:10px; color:#64748b;">Email and SMS notifications are currently simulated and logged here.</p>
        <?php if (!empty($notifications)): ?>
            <div class="rooms-grid-wrap">
                <table class="appointments-table">
                    <thead>
                        <tr>
                            <th>Channel</th>
                            <th>Recipient</th>
                            <th>Status</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($notifications as $notif): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($notif['channel']); ?></td>
                                <td><?php echo htmlspecialchars($notif['recipient'] ?? 'N/A'); ?></td>
                                <td><span class="status-badge status-pending"><?php echo htmlspecialchars($notif['status']); ?></span></td>
                                <td><?php echo htmlspecialchars($notif['created_at']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p style="color:#64748b;">No notifications logged yet.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>


