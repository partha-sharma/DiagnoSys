<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'patient') {
        header('Location: ../patient/dashboard.php');
        exit();
    }
    header('Location: ../auth/login.php');
    exit();
}

$statusFilter = trim($_GET['status'] ?? '');
$methodFilter = trim($_GET['method'] ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$validStatuses = ['Pending', 'Processing', 'Completed', 'Failed', 'Refunded'];
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}

$summarySql = "SELECT
    COUNT(*) AS total_payments,
    SUM(CASE WHEN p.status = 'Completed' THEN p.amount ELSE 0 END) AS total_collected,
    SUM(CASE WHEN p.status = 'Pending' THEN 1 ELSE 0 END) AS pending_count,
    SUM(CASE WHEN p.status = 'Failed' THEN 1 ELSE 0 END) AS failed_count
FROM payments p";
$summaryResult = $conn->query($summarySql);
$summary = $summaryResult ? $summaryResult->fetch_assoc() : ['total_payments' => 0, 'total_collected' => 0, 'pending_count' => 0, 'failed_count' => 0];

$filters = [];
$params = [];
$types = '';

if ($statusFilter !== '') {
    $filters[] = 'p.status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

if ($methodFilter !== '') {
    $filters[] = 'p.payment_method = ?';
    $params[] = $methodFilter;
    $types .= 's';
}

if ($dateFrom !== '') {
    $filters[] = 'DATE(COALESCE(p.payment_date, p.created_at)) >= ?';
    $params[] = $dateFrom;
    $types .= 's';
}

if ($dateTo !== '') {
    $filters[] = 'DATE(COALESCE(p.payment_date, p.created_at)) <= ?';
    $params[] = $dateTo;
    $types .= 's';
}

$whereSql = '';
if (!empty($filters)) {
    $whereSql = ' WHERE ' . implode(' AND ', $filters);
}

$paymentsSql = "SELECT
    p.payment_id,
    p.appointment_id,
    p.amount,
    p.status,
    p.payment_method,
    p.transaction_id,
    p.payment_date,
    p.created_at,
    u.full_name,
    u.email,
    a.appointment_date
FROM payments p
INNER JOIN users u ON p.user_id = u.user_id
LEFT JOIN appointments a ON p.appointment_id = a.appointment_id" . $whereSql . "
ORDER BY COALESCE(p.payment_date, p.created_at) DESC
LIMIT 200";

$payments = [];
$paymentsStmt = $conn->prepare($paymentsSql);
if ($paymentsStmt) {
    if ($types !== '') {
        $paymentsStmt->bind_param($types, ...$params);
    }
    $paymentsStmt->execute();
    $paymentsResult = $paymentsStmt->get_result();
    while ($row = $paymentsResult->fetch_assoc()) {
        $payments[] = $row;
    }
    $paymentsStmt->close();
}

$methods = [];
$methodsResult = $conn->query("SELECT DISTINCT payment_method FROM payments WHERE payment_method IS NOT NULL AND payment_method <> '' ORDER BY payment_method ASC");
if ($methodsResult) {
    while ($row = $methodsResult->fetch_assoc()) {
        $methods[] = $row['payment_method'];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance - DiagnoLab Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .finance-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 16px;
            margin-bottom: 18px;
        }

        .finance-stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.05);
        }

        .finance-stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #0f172a;
            flex-shrink: 0;
        }

        .finance-icon-blue {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .finance-icon-green {
            background: #dcfce7;
            color: #059669;
        }

        .finance-icon-yellow {
            background: #fef3c7;
            color: #b45309;
        }

        .finance-icon-red {
            background: #fee2e2;
            color: #dc2626;
        }

        .finance-stat-content h3 {
            margin: 0;
            color: #475569;
            font-size: 14px;
            font-weight: 600;
        }

        .finance-number {
            margin-top: 5px;
            color: #0f172a;
            font-size: 28px;
            font-weight: 800;
            line-height: 1.1;
        }

        .finance-subtext {
            margin: 4px 0 0;
            font-size: 13px;
            color: #64748b;
        }

        .finance-filter-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        @media (max-width: 768px) {
            .finance-stat-card {
                padding: 14px;
            }

            .finance-number {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
<div class="admin-layout">
    <div class="sidebar">
        <h2 style="margin-bottom: 30px;">Admin Panel</h2>
        <a href="index.php">Dashboard</a>
        <a href="manage_tests.php">Manage Tests</a>
        <a href="appointments.php">Appointments</a>
        <a href="technicians.php">Technicians</a>
        <a href="packages.php">Packages</a>
        <a href="manage_rooms.php">Manage Rooms</a>
        <a href="finance.php" class="active">Finance</a>
        <a href="users.php">Patients List</a>
        <hr style="border-color: #334155; margin: 20px 0;">
        <a href="../auth/logout.php" class="sidebar-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="content">
        <div class="manage-tests-header">
            <div class="header-text" style="text-align:left;">
                <h1>Finance</h1>
                <p>Track all patient payments and transaction status.</p>
            </div>
        </div>

        <div class="finance-summary-grid">
            <div class="finance-stat-card">
                <div class="finance-stat-icon finance-icon-blue"><i class="fa-solid fa-receipt"></i></div>
                <div class="finance-stat-content">
                    <h3>Total Payments</h3>
                    <div class="finance-number"><?php echo (int)($summary['total_payments'] ?? 0); ?></div>
                    <p class="finance-subtext">All payment records</p>
                </div>
            </div>
            <div class="finance-stat-card">
                <div class="finance-stat-icon finance-icon-green"><i class="fa-solid fa-money-bill-wave"></i></div>
                <div class="finance-stat-content">
                    <h3>Total Collected</h3>
                    <div class="finance-number">৳<?php echo number_format((float)($summary['total_collected'] ?? 0), 2); ?></div>
                    <p class="finance-subtext">Completed payments only</p>
                </div>
            </div>
            <div class="finance-stat-card">
                <div class="finance-stat-icon finance-icon-yellow"><i class="fa-solid fa-clock"></i></div>
                <div class="finance-stat-content">
                    <h3>Pending</h3>
                    <div class="finance-number"><?php echo (int)($summary['pending_count'] ?? 0); ?></div>
                    <p class="finance-subtext">Awaiting completion</p>
                </div>
            </div>
            <div class="finance-stat-card">
                <div class="finance-stat-icon finance-icon-red"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="finance-stat-content">
                    <h3>Failed</h3>
                    <div class="finance-number"><?php echo (int)($summary['failed_count'] ?? 0); ?></div>
                    <p class="finance-subtext">Needs follow-up</p>
                </div>
            </div>
        </div>

        <div class="appointments-section" style="margin-bottom:16px;">
            <h2 style="margin-bottom:12px;">Filters</h2>
            <form method="GET" class="rooms-filter-grid">
                <select name="status" class="form-input">
                    <option value="">All Statuses</option>
                    <?php foreach ($validStatuses as $status): ?>
                        <option value="<?php echo htmlspecialchars($status); ?>" <?php echo $statusFilter === $status ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="method" class="form-input">
                    <option value="">All Methods</option>
                    <?php foreach ($methods as $method): ?>
                        <option value="<?php echo htmlspecialchars($method); ?>" <?php echo $methodFilter === $method ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($method); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="date_from" class="form-input" value="<?php echo htmlspecialchars($dateFrom); ?>">
                <input type="date" name="date_to" class="form-input" value="<?php echo htmlspecialchars($dateTo); ?>">
                <div class="finance-filter-actions">
                    <button type="submit" class="btn-primary">Apply</button>
                    <a href="finance.php" class="btn-outline" style="text-decoration:none;">Reset</a>
                </div>
            </form>
        </div>

        <div class="appointments-section">
            <h2 style="margin-bottom:12px;">Patient Payments</h2>
            <?php if (!empty($payments)): ?>
                <div class="rooms-grid-wrap">
                    <table class="appointments-table">
                        <thead>
                            <tr>
                                <th>Payment ID</th>
                                <th>Patient</th>
                                <th>Appointment</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Transaction</th>
                                <th>Status</th>
                                <th>Paid At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <?php
                                    $statusClass = 'status-pending';
                                    if ($payment['status'] === 'Completed') {
                                        $statusClass = 'status-completed';
                                    } elseif ($payment['status'] === 'Failed') {
                                        $statusClass = 'status-cancelled';
                                    } elseif ($payment['status'] === 'Refunded') {
                                        $statusClass = 'status-unavailable';
                                    }
                                    $paidAt = $payment['payment_date'] ?: $payment['created_at'];
                                ?>
                                <tr>
                                    <td>#<?php echo (int)$payment['payment_id']; ?></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($payment['full_name']); ?></strong>
                                        <small style="display:block; color:#64748b;"><?php echo htmlspecialchars($payment['email']); ?></small>
                                    </td>
                                    <td>
                                        #<?php echo (int)$payment['appointment_id']; ?>
                                        <small style="display:block; color:#64748b;">
                                            <?php echo !empty($payment['appointment_date']) ? date('M d, Y h:i A', strtotime($payment['appointment_date'])) : 'N/A'; ?>
                                        </small>
                                    </td>
                                    <td>৳<?php echo number_format((float)$payment['amount'], 2); ?></td>
                                    <td><?php echo htmlspecialchars($payment['payment_method'] ?: 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($payment['transaction_id'] ?: 'N/A'); ?></td>
                                    <td><span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($payment['status']); ?></span></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($paidAt)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p>No payments found for the selected filter.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>


