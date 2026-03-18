<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $base_price = (float)($_POST['base_price'] ?? 0);
        $discount_percent = (float)($_POST['discount_percent'] ?? 0);
        $final_price = max(0, $base_price - ($base_price * $discount_percent / 100));

        if ($name !== '' && $base_price > 0) {
            $stmt = $conn->prepare("INSERT INTO packages (name, description, base_price, discount_percent, final_price, status) VALUES (?, ?, ?, ?, ?, 'Active')");
            $stmt->bind_param('ssddd', $name, $description, $base_price, $discount_percent, $final_price);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Package added successfully.';
            } else {
                $_SESSION['error'] = 'Failed to add package. Ensure package name is unique.';
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = 'Package name and base price are required.';
        }
    }

    if ($action === 'toggle_status') {
        $package_id = (int)($_POST['package_id'] ?? 0);
        $next_status = trim($_POST['next_status'] ?? 'Active');
        $next_status = $next_status === 'Inactive' ? 'Inactive' : 'Active';

        $stmt = $conn->prepare("UPDATE packages SET status = ? WHERE package_id = ?");
        $stmt->bind_param('si', $next_status, $package_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success'] = 'Package status updated.';
    }

    header('Location: packages.php');
    exit();
}

$packages = $conn->query("SELECT package_id, name, description, base_price, discount_percent, final_price, status FROM packages ORDER BY package_id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packages - DiagnoLab</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-layout">
    <div class="sidebar">
        <h2 style="margin-bottom: 30px;">Admin Panel</h2>
        <a href="index.php">Dashboard</a>
        <a href="manage_tests.php">Manage Tests</a>
        <a href="appointments.php">Appointments</a>
        <a href="technicians.php">Technicians</a>
        <a href="packages.php" class="active">Packages</a>
        <a href="users.php">Patients List</a>
        <hr style="border-color: #334155; margin: 20px 0;">
        <a href="../logout.php" class="sidebar-logout">Logout</a>
    </div>

    <div class="content">
        <h1>Package System</h1>
        <p>Create and manage test bundles for patients.</p>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="appointments-section" style="margin-bottom:20px;">
            <h2>Add Package</h2>
            <form method="POST" style="display:grid; grid-template-columns: repeat(4,minmax(120px,1fr)); gap:10px;">
                <input type="hidden" name="action" value="add">
                <input type="text" name="name" class="form-input" placeholder="Package name" required>
                <input type="text" name="description" class="form-input" placeholder="Short description">
                <input type="number" step="0.01" name="base_price" class="form-input" placeholder="Base price" required>
                <input type="number" step="0.01" name="discount_percent" class="form-input" placeholder="Discount %" value="0">
                <div style="grid-column:1/-1; text-align:right;">
                    <button class="btn-primary" type="submit">Add Package</button>
                </div>
            </form>
        </div>

        <table class="appointments-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Base Price</th>
                    <th>Discount %</th>
                    <th>Final Price</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($packages && $packages->num_rows > 0): ?>
                    <?php while ($row = $packages->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo (int)$row['package_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['description'] ?: 'N/A'); ?></td>
                            <td>৳<?php echo number_format((float)$row['base_price'], 2); ?></td>
                            <td><?php echo number_format((float)$row['discount_percent'], 2); ?>%</td>
                            <td>৳<?php echo number_format((float)$row['final_price'], 2); ?></td>
                            <td>
                                <span class="status-badge <?php echo $row['status'] === 'Active' ? 'status-completed' : 'status-cancelled'; ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="package_id" value="<?php echo (int)$row['package_id']; ?>">
                                    <input type="hidden" name="next_status" value="<?php echo $row['status'] === 'Active' ? 'Inactive' : 'Active'; ?>">
                                    <button type="submit" class="btn-outline"><?php echo $row['status'] === 'Active' ? 'Set Inactive' : 'Set Active'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center;">No packages yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>
