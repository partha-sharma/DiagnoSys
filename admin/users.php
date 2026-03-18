<?php
require_once '../config/init.php';

// 1. MANUAL GATEKEEPER (Stops the bouncing)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // If it's a patient trying to be sneaky, send them back to dashboard
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'patient') {
        header("Location: ../dashboard.php");
        exit();
    }
    // Otherwise send to login
    header("Location: ../login.php");
    exit();
}

// Fetch all users from database
$usersQuery = "SELECT user_id, full_name, email, phone, address, created_at FROM users ORDER BY created_at DESC";
$usersResult = mysqli_query($conn, $usersQuery);
$users = [];
if ($usersResult) {
    while ($row = mysqli_fetch_assoc($usersResult)) {
        $users[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patients List - DiagnoLab</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .admin-layout { display: flex; height: 100vh; }
        .sidebar { width: 250px; background: #1e293b; color: white; padding: 20px; overflow-y: auto; }
        .sidebar a { display: block; color: #cbd5e1; padding: 10px 0; text-decoration: none; }
        .sidebar a:hover { color: white; }
        .sidebar a.active { color: white; font-weight: bold; }
        .sidebar .sidebar-logout { display: flex; align-items: center; gap: 8px; color: #fca5a5; padding: 10px 16px; border-radius: 8px; border: 1px solid rgba(252, 165, 165, 0.25); transition: all 0.2s ease; margin-top: 8px; font-size: 14px; }
        .sidebar .sidebar-logout:hover { background: rgba(239, 68, 68, 0.15); color: #fecaca; border-color: rgba(252, 165, 165, 0.4); }
        .content { flex: 1; padding: 40px; background: #f1f5f9; overflow-y: auto; }
        .table-container { background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e293b; color: white; padding: 12px; text-align: left; font-weight: bold; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; }
        tr:hover { background: #f8fafc; }
        .patient-id { color: #2563eb; font-weight: bold; }
        .created-at { color: #64748b; font-size: 0.9em; }
    </style>
</head>
<body>

<div class="admin-layout">
    <!-- Left Sidebar -->
    <div class="sidebar">
        <h2 style="margin-bottom: 30px;">Admin Panel</h2>
        <a href="index.php">Dashboard</a>
        <a href="manage_tests.php">Manage Tests</a>
        <a href="appointments.php">Appointments</a>
        <a href="technicians.php">Technicians</a>
        <a href="packages.php">Packages</a>
        <a href="users.php" class="active">Patients List</a>
        <hr style="border-color: #334155; margin: 20px 0;">
        <a href="../logout.php" class="sidebar-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <!-- Right Content -->
    <div class="content">
        <h1>Patients List</h1>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>

        <div style="margin-top: 30px;">
            <div class="table-container">
                <h3 style="margin-top: 0; color: #1e293b;">All Registered Patients (<?php echo count($users); ?>)</h3>
                
                <?php if (count($users) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Patient ID</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Registered Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td class="patient-id">#<?php echo htmlspecialchars($user['user_id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($user['address'] ?? 'N/A'); ?></td>
                                    <td class="created-at"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; color: #64748b; padding: 40px;">No patients registered yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>
