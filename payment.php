<?php
require_once 'config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$appointment_id = (int)($_GET['appointment_id'] ?? 0);

function infer_preview_test_type(string $testName): string
{
    $name = strtolower($testName);

    if (str_contains($name, 'x-ray') || str_contains($name, 'xray') || str_contains($name, 'radiology')) {
        return 'Radiology';
    }
    if (str_contains($name, 'ecg') || str_contains($name, 'echo') || str_contains($name, 'troponin') || str_contains($name, 'heart')) {
        return 'Cardiology';
    }
    if (str_contains($name, 'urine')) {
        return 'Urine';
    }
    if (str_contains($name, 'thyroid')) {
        return 'Endocrinology';
    }
    if (str_contains($name, 'blood') || str_contains($name, 'cbc') || str_contains($name, 'hba1c') || str_contains($name, 'liver') || str_contains($name, 'kidney')) {
        return 'Blood Test';
    }

    return 'General';
}

function pick_preview_assignment(mysqli $conn, int $testId, string $testType): ?array
{
    $sql = "SELECT ra.room_id, ra.slot_id, ra.capacity, ra.booked_count,
                   r.room_number, rs.slot_label, rs.start_time
            FROM room_assignments ra
            INNER JOIN rooms r ON ra.room_id = r.room_id
            INNER JOIN room_time_slots rs ON ra.slot_id = rs.slot_id
            WHERE ra.status = 'Active'
              AND r.status = 'Active'
              AND rs.status = 'Active'
              AND ra.capacity > ra.booked_count
              AND (
                  ra.mapped_test_id = ?
                  OR (ra.map_scope = 'type' AND ra.mapped_test_type = ?)
                  OR (ra.map_scope = 'type' AND ra.mapped_test_type = 'General')
              )
            ORDER BY
                (ra.mapped_test_id = ?) DESC,
                (ra.mapped_test_type = ?) DESC,
                (ra.capacity - ra.booked_count) DESC,
                rs.start_time ASC
            LIMIT 50";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('isis', $testId, $testType, $testId, $testType);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();

    if (!empty($rows)) {
        return $rows[array_rand($rows)];
    }

    $fallback = $conn->query("SELECT ra.room_id, ra.slot_id, ra.capacity, ra.booked_count,
                                     r.room_number, rs.slot_label, rs.start_time
                              FROM room_assignments ra
                              INNER JOIN rooms r ON ra.room_id = r.room_id
                              INNER JOIN room_time_slots rs ON ra.slot_id = rs.slot_id
                              WHERE ra.status = 'Active'
                                AND r.status = 'Active'
                                AND rs.status = 'Active'
                                AND ra.capacity > ra.booked_count
                              ORDER BY (ra.capacity - ra.booked_count) DESC, rs.start_time ASC
                              LIMIT 50");

    if (!$fallback) {
        return null;
    }

    $fallbackRows = [];
    while ($row = $fallback->fetch_assoc()) {
        $fallbackRows[] = $row;
    }

    if (empty($fallbackRows)) {
        return null;
    }

    return $fallbackRows[array_rand($fallbackRows)];
}

if ($appointment_id <= 0) {
    $_SESSION['error'] = 'Invalid appointment selected for payment.';
    header('Location: dashboard.php');
    exit();
}

$sql = "SELECT a.appointment_id, a.appointment_date, a.status, a.total_amount,
               GROUP_CONCAT(t.test_name SEPARATOR ', ') AS test_names
        FROM appointments a
        LEFT JOIN appointment_tests apt ON a.appointment_id = apt.appointment_id
        LEFT JOIN tests t ON apt.test_id = t.test_id
        WHERE a.appointment_id = ? AND a.user_id = ?
        GROUP BY a.appointment_id
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $appointment_id, $user_id);
$stmt->execute();
$appointment = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appointment) {
    $_SESSION['error'] = 'Appointment not found.';
    header('Location: dashboard.php');
    exit();
}

$payStmt = $conn->prepare("SELECT payment_id, status, payment_method, transaction_id, amount, payment_date FROM payments WHERE appointment_id = ? AND user_id = ? ORDER BY payment_id DESC LIMIT 1");
$payStmt->bind_param('ii', $appointment_id, $user_id);
$payStmt->execute();
$payment = $payStmt->get_result()->fetch_assoc();
$payStmt->close();

$amount = (float)($appointment['total_amount'] ?? 0);
if ($amount <= 0) {
    $sumStmt = $conn->prepare("SELECT COALESCE(SUM(t.price),0) AS total FROM appointment_tests apt JOIN tests t ON apt.test_id = t.test_id WHERE apt.appointment_id = ?");
    $sumStmt->bind_param('i', $appointment_id);
    $sumStmt->execute();
    $amount = (float)$sumStmt->get_result()->fetch_assoc()['total'];
    $sumStmt->close();
}

$previewTests = [];
$previewSql = "SELECT t.test_id, t.test_name, COALESCE(t.test_type, '') AS test_type
               FROM appointment_tests at
               INNER JOIN tests t ON at.test_id = t.test_id
               WHERE at.appointment_id = ?
               ORDER BY t.test_name ASC";
$previewStmt = $conn->prepare($previewSql);
$previewStmt->bind_param('i', $appointment_id);
$previewStmt->execute();
$previewResult = $previewStmt->get_result();
while ($row = $previewResult->fetch_assoc()) {
    $testType = trim($row['test_type']) !== '' ? $row['test_type'] : infer_preview_test_type($row['test_name']);
    $assignment = pick_preview_assignment($conn, (int)$row['test_id'], $testType);
    $previewTests[] = [
        'test_name' => $row['test_name'],
        'room_number' => $assignment['room_number'] ?? null,
        'slot_label' => $assignment['slot_label'] ?? null,
    ];
}
$previewStmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo Payment - DiagnoLab</title>
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
            <h1>Complete Payment</h1>
            <p>Demo Gateway for Appointment #<?php echo (int)$appointment['appointment_id']; ?></p>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="booking-layout">
        <div class="appointments-section">
            <h2>Appointment Summary</h2>
            <p><strong>Date:</strong> <?php echo date('M d, Y @ h:i A', strtotime($appointment['appointment_date'])); ?></p>
            <p><strong>Tests:</strong> <?php echo htmlspecialchars($appointment['test_names'] ?? 'N/A'); ?></p>
            <p><strong>Appointment Status:</strong> <?php echo htmlspecialchars($appointment['status']); ?></p>
            <div style="margin-top: 12px;">
                <h3 style="margin-bottom:8px;">Room Preview (Before Payment)</h3>
                <div style="overflow-x:auto;">
                    <table class="appointments-table">
                        <thead>
                            <tr>
                                <th>Test</th>
                                <th>Room</th>
                                <th>Time Slot</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($previewTests)): ?>
                                <?php foreach ($previewTests as $preview): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($preview['test_name']); ?></td>
                                        <td><?php echo htmlspecialchars($preview['room_number'] ?? 'To Be Assigned'); ?></td>
                                        <td><?php echo htmlspecialchars($preview['slot_label'] ?? 'To Be Assigned'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align:center; color:#64748b;">No test entries found for this appointment.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <small style="display:block; margin-top:6px; color:#64748b;">Final room/time will be locked after successful payment.</small>
            </div>
            <hr style="margin:14px 0; border-color:#e2e8f0;">
            <h3 style="margin-bottom:14px;">Total Payable: ৳<?php echo number_format($amount, 2); ?></h3>

            <?php if ($payment && $payment['status'] === 'Completed'): ?>
                <div class="alert alert-success">Payment already completed via <?php echo htmlspecialchars($payment['payment_method']); ?>.</div>
                <a class="btn-primary" href="booking-confirmation.php?appointment_id=<?php echo (int)$appointment_id; ?>">View Confirmation</a>
            <?php else: ?>
                <form method="POST" action="payment-process.php">
                    <input type="hidden" name="appointment_id" value="<?php echo (int)$appointment_id; ?>">

                    <label>Payment Method</label>
                    <select name="payment_method" class="form-input" required>
                        <option value="">Select method</option>
                        <option value="Cash on Arrival">Cash on Arrival</option>
                        <option value="bKash">bKash</option>
                        <option value="Nagad">Nagad</option>
                        <option value="Card">Card</option>
                    </select>

                    <label style="margin-top:10px; display:block;">Transaction ID (optional for Cash on Arrival)</label>
                    <input type="text" name="transaction_id" class="form-input" placeholder="e.g. TXN12345">
                    <small style="display:block; color:#64748b; margin-top:6px;">For bKash, Nagad, and Card, provide a transaction ID. On success you will be redirected to a room/time execution plan.</small>

                    <button type="submit" class="btn-primary" style="margin-top:12px;">Pay Now</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="cart-summary">
            <div class="cart-header">Payment History</div>
            <?php if ($payment): ?>
                <p><strong>Status:</strong> <?php echo htmlspecialchars($payment['status']); ?></p>
                <p><strong>Method:</strong> <?php echo htmlspecialchars($payment['payment_method'] ?? 'N/A'); ?></p>
                <p><strong>Transaction:</strong> <?php echo htmlspecialchars($payment['transaction_id'] ?? 'N/A'); ?></p>
                <p><strong>Date:</strong> <?php echo !empty($payment['payment_date']) ? htmlspecialchars($payment['payment_date']) : 'N/A'; ?></p>
            <?php else: ?>
                <p style="color:#64748b;">No payment record yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
