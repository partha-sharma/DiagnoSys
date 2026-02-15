<?php
// admin/index.php

// This single require points UP one directory to get to the config folder
require_once '../config/init.php';

// Protect this page: ONLY ADMINS ALLOWED. This handles everything.
protect_admin_page();

// We don't need any other security checks. The function did it all.
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - DiagnoLab</title>
    <!-- We go up one level (..) to find assets -->
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Temporary simple admin styles */
        .admin-layout { display: flex; height: 100vh; }
        .sidebar { width: 250px; background: #1e293b; color: white; padding: 20px; }
        .sidebar a { display: block; color: #cbd5e1; padding: 10px 0; text-decoration: none; }
        .sidebar a:hover { color: white; }
        .content { flex: 1; padding: 40px; background: #f1f5f9; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; margin-right: 20px; display: inline-block; width: 200px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<div class="admin-layout">
    <!-- Left Sidebar -->
    <div class="sidebar">
        <h2 style="margin-bottom: 30px;">👨‍⚕️ Admin Panel</h2>
        <a href="index.php">Dashboard</a>
        <a href="appointments.php">Appointments</a>
        <a href="users.php">Patients List</a>
        <hr style="border-color: #334155; margin: 20px 0;">
        <a href="../logout.php" style="color: #fca5a5;">Logout</a>
    </div>

    <!-- Right Content -->
    <div class="content">
        <h1>Overview</h1>
        <p>Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>

        <div style="margin-top: 30px;">
            <div class="stat-card">
                <h3>Appointments</h3>
                <p style="font-size: 24px; font-weight: bold; color: #2563eb;">5 Pending</p>
            </div>
            <div class="stat-card">
                <h3>Total Patients</h3>
                <p style="font-size: 24px; font-weight: bold; color: #059669;">12</p>
            </div>
        </div>
    </div>
</div>

</body>
</html>