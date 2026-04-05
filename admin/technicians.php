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
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');

        if ($name !== '' && $email !== '' && $phone !== '') {
            $stmt = $conn->prepare("INSERT INTO technicians (name, email, phone, specialization) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('ssss', $name, $email, $phone, $specialization);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Technician added successfully.';
            } else {
                $_SESSION['error'] = 'Could not add technician. Email might already exist.';
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = 'Name, email, and phone are required.';
        }
    }

    if ($action === 'toggle_status') {
        $technician_id = (int)($_POST['technician_id'] ?? 0);
        $next_status = trim($_POST['next_status'] ?? 'Active');
        $next_status = $next_status === 'Inactive' ? 'Inactive' : 'Active';

        $stmt = $conn->prepare("UPDATE technicians SET status = ? WHERE technician_id = ?");
        $stmt->bind_param('si', $next_status, $technician_id);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success'] = 'Technician status updated.';
    }

    header('Location: technicians.php');
    exit();
}

$tech_result = $conn->query("SELECT technician_id, name, email, phone, specialization, status, created_at FROM technicians ORDER BY technician_id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technicians - DiagnoLab</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="admin-layout">
    <div class="sidebar">
        <h2 style="margin-bottom: 30px;">Admin Panel</h2>
        <a href="index.php">Dashboard</a>
        <a href="manage_tests.php">Manage Tests</a>
        <a href="appointments.php">Appointments</a>
        <a href="technicians.php" class="active">Technicians</a>
        <a href="packages.php">Packages</a>
        <a href="manage_rooms.php">Manage Rooms</a>
        <a href="finance.php">Finance</a>
        <a href="users.php">Patients List</a>
        <hr style="border-color: #334155; margin: 20px 0;">
        <a href="../logout.php" class="sidebar-logout">Logout</a>
    </div>

    <div class="content">
        <h1>Technician Management</h1>
        <p>Add lab staff and manage availability.</p>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="appointments-section" style="margin-bottom:20px;">
            <h2>Add Technician</h2>
            <form method="POST" style="display:grid; grid-template-columns: repeat(4,minmax(120px,1fr)); gap:10px;">
                <input type="hidden" name="action" value="add">
                <input type="text" name="name" class="form-input" placeholder="Name" required>
                <input type="email" name="email" class="form-input" placeholder="Email" required>
                <input type="text" name="phone" class="form-input" placeholder="Phone" required>
                <input type="text" name="specialization" class="form-input" placeholder="Specialization">
                <div style="grid-column:1/-1; text-align:right;">
                    <button class="btn-primary" type="submit">Add Technician</button>
                </div>
            </form>
        </div>

        <table class="appointments-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Specialization</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($tech_result && $tech_result->num_rows > 0): ?>
                    <?php while ($row = $tech_result->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo (int)$row['technician_id']; ?></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                            <td><?php echo htmlspecialchars($row['phone']); ?></td>
                            <td><?php echo htmlspecialchars($row['specialization'] ?: 'N/A'); ?></td>
                            <td>
                                <span class="status-badge <?php echo $row['status'] === 'Active' ? 'status-completed' : 'status-cancelled'; ?>">
                                    <?php echo htmlspecialchars($row['status']); ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="technician_id" value="<?php echo (int)$row['technician_id']; ?>">
                                    <input type="hidden" name="next_status" value="<?php echo $row['status'] === 'Active' ? 'Inactive' : 'Active'; ?>">
                                    <button type="submit" class="btn-outline"><?php echo $row['status'] === 'Active' ? 'Set Inactive' : 'Set Active'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align:center;">No technicians yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>
