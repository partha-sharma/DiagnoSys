<?php
require_once 'config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$appointment_id = (int)($_GET['appointment_id'] ?? 0);

if ($appointment_id <= 0) {
    $_SESSION['error'] = 'Invalid invoice request.';
    header('Location: dashboard.php');
    exit();
}

$stmt = $conn->prepare("SELECT a.appointment_id, a.appointment_date, u.full_name, u.email,
                              GROUP_CONCAT(t.test_name SEPARATOR ', ') AS test_names,
                  p.payment_id, p.amount, p.payment_method, p.transaction_id, p.payment_date, p.status
                       FROM appointments a
                       JOIN users u ON a.user_id = u.user_id
                       LEFT JOIN appointment_tests apt ON a.appointment_id = apt.appointment_id
                       LEFT JOIN tests t ON apt.test_id = t.test_id
                       JOIN payments p ON a.appointment_id = p.appointment_id
              WHERE a.appointment_id = ? AND a.user_id = ? AND p.status = 'Completed'
                       GROUP BY a.appointment_id, p.payment_id
                       ORDER BY p.payment_id DESC
                       LIMIT 1");
$stmt->bind_param('ii', $appointment_id, $user_id);
$stmt->execute();
$invoice = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if (!$invoice) {
    $_SESSION['error'] = 'No invoice found for this appointment.';
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo (int)$invoice['appointment_id']; ?> - DiagnoLab</title>
    <link rel="stylesheet" href="/DiagnoSys/assets/css/style.css">
</head>
<body>
<div class="navbar">
    <a href="dashboard.php" class="logo">DiagnoLab</a>
    <div class="nav-buttons">
        <button onclick="window.print()" class="btn-outline">Print</button>
        <a href="dashboard.php" class="btn-primary">Dashboard</a>
    </div>
</div>

<div class="dashboard-container">
    <div class="appointments-section">
        <h2>Invoice</h2>
        <p><strong>Invoice ID:</strong> INV-<?php echo (int)$invoice['appointment_id']; ?>-<?php echo (int)$invoice['payment_id']; ?></p>
        <p><strong>Patient:</strong> <?php echo htmlspecialchars($invoice['full_name']); ?> (<?php echo htmlspecialchars($invoice['email']); ?>)</p>
        <p><strong>Appointment Date:</strong> <?php echo date('M d, Y @ h:i A', strtotime($invoice['appointment_date'])); ?></p>
        <p><strong>Payment Date:</strong> <?php echo !empty($invoice['payment_date']) ? htmlspecialchars($invoice['payment_date']) : 'N/A'; ?></p>
        <hr style="margin:12px 0; border-color:#e2e8f0;">
        <p><strong>Tests:</strong> <?php echo htmlspecialchars($invoice['test_names'] ?? 'N/A'); ?></p>
        <p><strong>Payment Method:</strong> <?php echo htmlspecialchars($invoice['payment_method'] ?? 'N/A'); ?></p>
        <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($invoice['transaction_id'] ?? 'N/A'); ?></p>
        <h3 style="margin-top:12px;">Total Paid: ৳<?php echo number_format((float)$invoice['amount'], 2); ?></h3>
        <p><strong>Status:</strong> <?php echo htmlspecialchars($invoice['status']); ?></p>
    </div>
</div>
</body>
</html>
