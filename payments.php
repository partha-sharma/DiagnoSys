<?php
require_once 'config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];

$sql = "SELECT a.appointment_id, a.appointment_date, a.status,
               GROUP_CONCAT(t.test_name SEPARATOR ', ') AS test_names,
               a.total_amount,
               p.status AS payment_status
        FROM appointments a
        LEFT JOIN appointment_tests apt ON a.appointment_id = apt.appointment_id
        LEFT JOIN tests t ON apt.test_id = t.test_id
        LEFT JOIN (
            SELECT appointment_id, MAX(payment_id) AS latest_payment_id
            FROM payments
            GROUP BY appointment_id
        ) lp ON a.appointment_id = lp.appointment_id
        LEFT JOIN payments p ON lp.latest_payment_id = p.payment_id
        WHERE a.user_id = ?
        GROUP BY a.appointment_id
        ORDER BY a.appointment_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$rows = $stmt->get_result();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payments - DiagnoLab</title>
    <link rel="stylesheet" href="/DiagnoSys/assets/css/style.css">
</head>
<body>
<div class="navbar">
    <a href="dashboard.php" class="logo">DiagnoLab</a>
    <div class="nav-buttons">
        <a href="dashboard.php" class="btn-outline">Dashboard</a>
        <a href="logout.php" class="btn-primary">Logout</a>
    </div>
</div>

<div class="dashboard-container">
    <div class="dashboard-header">
        <div>
            <h1>Payments & Invoices</h1>
            <p>Pay for appointments and download invoice after success.</p>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="appointments-section">
        <?php if ($rows && $rows->num_rows > 0): ?>
            <table class="appointments-table">
                <thead>
                    <tr>
                        <th>Appt. ID</th>
                        <th>Date & Time</th>
                        <th>Tests</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $rows->fetch_assoc()): ?>
                        <?php
                            $paymentStatus = $row['payment_status'] ?? 'Pending';
                            $paymentClass = 'status-pending';
                            if ($paymentStatus === 'Completed') {
                                $paymentClass = 'status-completed';
                            } elseif ($paymentStatus === 'Failed') {
                                $paymentClass = 'status-cancelled';
                            }
                        ?>
                        <tr>
                            <td>#<?php echo (int)$row['appointment_id']; ?></td>
                            <td><?php echo date('M d, Y @ h:i A', strtotime($row['appointment_date'])); ?></td>
                            <td><?php echo htmlspecialchars($row['test_names'] ?? 'N/A'); ?></td>
                            <td>৳<?php echo number_format((float)($row['total_amount'] ?? 0), 2); ?></td>
                            <td>
                                <span class="status-badge <?php echo $paymentClass; ?>">
                                    <?php echo htmlspecialchars($paymentStatus); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($row['payment_status'] === 'Completed'): ?>
                                    <a href="invoice.php?appointment_id=<?php echo (int)$row['appointment_id']; ?>" class="btn-outline" style="padding:6px 12px; font-size:13px;">Invoice</a>
                                <?php else: ?>
                                    <a href="payment.php?appointment_id=<?php echo (int)$row['appointment_id']; ?>" class="btn-primary" style="padding:6px 12px; font-size:13px;">Pay</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p>No appointments available for payment.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
