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

// Fetch appointment count from database
$appointmentQuery = "SELECT COUNT(*) as count FROM appointments";
$appointmentResult = mysqli_query($conn, $appointmentQuery);
$appointmentData = mysqli_fetch_assoc($appointmentResult);
$appointmentCount = $appointmentData['count'];

// Fetch patient count from database
$patientQuery = "SELECT COUNT(*) as count FROM users";
$patientResult = mysqli_query($conn, $patientQuery);
$patientData = mysqli_fetch_assoc($patientResult);
$patientCount = $patientData['count'];

// Fetch total tests conducted
$testsQuery = "SELECT COUNT(*) as count FROM appointment_tests";
$testsResult = mysqli_query($conn, $testsQuery);
$testsData = mysqli_fetch_assoc($testsResult);
$totalTests = $testsData['count'];

// Fetch total revenue (sum of test prices for appointments)
$revenueQuery = "SELECT SUM(t.price) as total_revenue FROM appointment_tests at JOIN tests t ON at.test_id = t.test_id";
$revenueResult = mysqli_query($conn, $revenueQuery);
$revenueData = mysqli_fetch_assoc($revenueResult);
$totalRevenue = $revenueData['total_revenue'] ?? 0;

// Fetch pending reports (appointments without test results)
$pendingQuery = "SELECT COUNT(DISTINCT a.appointment_id) as count FROM appointments a LEFT JOIN test_results tr ON a.appointment_id = tr.appointment_id WHERE tr.result_id IS NULL";
$pendingResult = mysqli_query($conn, $pendingQuery);
$pendingData = mysqli_fetch_assoc($pendingResult);
$pendingReports = $pendingData['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - DiagnoLab</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .admin-layout { display: flex; height: 100vh; }
        .sidebar { width: 250px; background: #1e293b; color: white; padding: 20px; overflow-y: auto; }
        .sidebar a { display: block; color: #cbd5e1; padding: 10px 0; text-decoration: none; }
        .sidebar a:hover { color: white; }
        .content { flex: 1; padding: 40px; background: #f1f5f9; overflow-y: auto; }
        .dashboard-header { margin-bottom: 40px; }
        .cards-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 30px; }
        .stat-card { 
            background: white; 
            padding: 30px; 
            border-radius: 12px; 
            box-shadow: 0 2px 8px rgba(0,0,0,0.1); 
            display: flex; 
            align-items: center; 
            gap: 30px;
        }
        .stat-icon { 
            width: 80px; 
            height: 80px; 
            border-radius: 12px; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 40px;
        }
        .stat-content h3 { margin: 0; color: #64748b; font-size: 14px; font-weight: 500; }
        .stat-content .number { font-size: 32px; font-weight: bold; color: #1e293b; margin: 10px 0; }
        .stat-content .subtext { font-size: 13px; color: #10b981; margin: 0; }
        
        .icon-blue { background: #dbeafe; }
        .icon-green { background: #dcfce7; }
        .icon-yellow { background: #fef3c7; }
        .icon-red { background: #fee2e2; }
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
        <a href="users.php">Patients List</a>
        <hr style="border-color: #334155; margin: 20px 0;">
        <a href="../logout.php" style="color: #fca5a5;">Logout</a>

         
        
   
    </div>

    <!-- Right Content -->
    <div class="content">
        <div class="dashboard-header">
            <h1>Admin Dashboard</h1>
        </div>

        <div class="cards-grid">
            <!-- Total Patients Card -->
            <div class="stat-card">
                <div class="stat-icon icon-blue">
                    <i class="fas fa-users" style="font-size: 2.5rem; color: #2563eb;"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Patients</h3>
                    <div class="number"><?php echo $patientCount; ?></div>
                    <p class="subtext">↗ +12% from last month</p>
                </div>
            </div>

            <!-- Total Tests Conducted Card -->
            <div class="stat-card">
                <div class="stat-icon icon-green">
                    <i class="fas fa-flask" style="font-size: 2.5rem; color: #059669;"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Tests Conducted</h3>
                    <div class="number"><?php echo $totalTests; ?></div>
                    <p class="subtext">↗ +8% from last month</p>
                </div>
            </div>

            <!-- Total Revenue Card -->
            <div class="stat-card">
                <div class="stat-icon icon-yellow">
                    <i class="fas fa-wallet" style="font-size: 2.5rem; color: #b45309;"></i>
                </div>
                <div class="stat-content">
                    <h3>Total Revenue</h3>
                    <div class="number">৳<?php echo number_format($totalRevenue, 0); ?></div>
                    <p class="subtext">↗ +15% from last month</p>
                </div>
            </div>

            <!-- Pending Reports Card -->
            <div class="stat-card">
                <div class="stat-icon icon-red">
                    <i class="fas fa-file-alt" style="font-size: 2.5rem; color: #dc2626;"></i>
                </div>
                <div class="stat-content">
                    <h3>Pending Reports</h3>
                    <div class="number"><?php echo $pendingReports; ?></div>
                    <p class="subtext">↗ Awaiting results submission</p>
                </div>
            </div>

            <!-- Appointments Card -->
            <div class="stat-card">
                <div class="stat-icon icon-blue">
                    <i class="fas fa-calendar-alt" style="font-size: 2.5rem; color: #2563eb;"></i>
                </div>
                <div class="stat-content">
                    <h3>Appointments</h3>
                    <div class="number"><?php echo $appointmentCount; ?></div>
                    <p class="subtext">↗ Active appointments</p>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>