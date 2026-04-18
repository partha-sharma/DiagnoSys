<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

function makeTechnicianPassword(): string
{
    return 'Tech@' . strtoupper(bin2hex(random_bytes(4)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $specialization = normalize_technician_specialization($_POST['specialization'] ?? 'Laboratory');
        $password = trim($_POST['password'] ?? '');

        if ($name !== '' && $email !== '' && $phone !== '') {
            if ($password === '') {
                $password = makeTechnicianPassword();
            }

            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $status = 'Active';
            $stmt = $conn->prepare("INSERT INTO technicians (name, email, phone, specialization, password_hash, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssss', $name, $email, $phone, $specialization, $passwordHash, $status);

            if ($stmt->execute()) {
                $newTechnicianId = $stmt->insert_id;
                $_SESSION['success'] = 'Technician created successfully. ID #' . $newTechnicianId . ' | Password: ' . $password;
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

    if ($action === 'delete') {
        $technician_id = (int)($_POST['technician_id'] ?? 0);

        if ($technician_id > 0) {
            $stmt = $conn->prepare("DELETE FROM technicians WHERE technician_id = ?");
            $stmt->bind_param('i', $technician_id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['success'] = 'Technician deleted successfully.';
        } else {
            $_SESSION['error'] = 'Invalid technician selected.';
        }
    }

    header('Location: technicians.php');
    exit();
}

$tech_result = $conn->query("SELECT technician_id, name, email, phone, COALESCE(specialization, 'Laboratory') AS specialization, status, created_at FROM technicians ORDER BY technician_id DESC");
$tech_count_result = $conn->query("SELECT COUNT(*) AS total_count, SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) AS active_count FROM technicians");
$tech_counts = $tech_count_result ? $tech_count_result->fetch_assoc() : ['total_count' => 0, 'active_count' => 0];
$totalTechnicians = (int)($tech_counts['total_count'] ?? 0);
$activeTechnicians = (int)($tech_counts['active_count'] ?? 0);
$inactiveTechnicians = max(0, $totalTechnicians - $activeTechnicians);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Technicians - DiagnoLab</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
        <a href="../auth/logout.php" class="sidebar-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="content">
        <h1>Technician Management</h1>
        <p>Create technician credentials and manage access to result uploads.</p>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap:16px; margin-bottom:24px;">
            <div class="appointments-section" style="margin:0; padding:18px;">
                <div style="color:#64748b; font-size:13px;">Total Technicians</div>
                <div style="font-size:32px; font-weight:700; color:#0f172a; margin-top:8px;"><?php echo $totalTechnicians; ?></div>
            </div>
            <div class="appointments-section" style="margin:0; padding:18px;">
                <div style="color:#64748b; font-size:13px;">Active</div>
                <div style="font-size:32px; font-weight:700; color:#059669; margin-top:8px;"><?php echo $activeTechnicians; ?></div>
            </div>
            <div class="appointments-section" style="margin:0; padding:18px;">
                <div style="color:#64748b; font-size:13px;">Inactive</div>
                <div style="font-size:32px; font-weight:700; color:#b45309; margin-top:8px;"><?php echo $inactiveTechnicians; ?></div>
            </div>
        </div>

        <div class="appointments-section" style="margin-bottom:20px;">
            <h2>Add Technician</h2>
            <form method="POST" style="display:grid; grid-template-columns: repeat(2,minmax(120px,1fr)); gap:10px;">
                <input type="hidden" name="action" value="add">
                <input type="text" name="name" class="form-input" placeholder="Name" required>
                <input type="email" name="email" class="form-input" placeholder="Email" required>
                <input type="text" name="phone" class="form-input" placeholder="Phone" required>
                <select name="specialization" class="form-input" required>
                    <?php foreach (technician_specialization_options() as $option): ?>
                        <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="password" class="form-input" placeholder="Password (optional - leave blank to generate)">
                <div style="grid-column:1/-1; text-align:right;">
                    <button class="btn-primary" type="submit">Create Technician</button>
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
                    <th>Created</th>
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
                            <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                            <td>
                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <form method="POST">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="technician_id" value="<?php echo (int)$row['technician_id']; ?>">
                                        <input type="hidden" name="next_status" value="<?php echo $row['status'] === 'Active' ? 'Inactive' : 'Active'; ?>">
                                        <button type="submit" class="btn-outline"><?php echo $row['status'] === 'Active' ? 'Deactivate' : 'Activate'; ?></button>
                                    </form>
                                    <form method="POST" onsubmit="return confirm('Delete this technician? Existing appointments and uploaded results will keep their history.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="technician_id" value="<?php echo (int)$row['technician_id']; ?>">
                                        <button type="submit" class="btn-outline" style="border-color:#ef4444; color:#ef4444;">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" style="text-align:center;">No technicians yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
<?php $conn->close(); ?>

