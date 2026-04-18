<?php
require '../config/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$email = trim($_POST['email'] ?? '');
$identifier = trim($_POST['identifier'] ?? '');
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? 'patient';

$errors = [];

if (empty($identifier)) $errors[] = 'Email is required';
if (empty($password)) $errors[] = 'Password is required';

if (!empty($errors)) {
    $_SESSION['errors'] = $errors;
    $_SESSION['old_input'] = ['identifier' => $identifier, 'role' => $role];
    header('Location: login.php');
    exit();
}

// BRANCHING LOGIC: Check different tables based on Role
if ($role === 'admin') {
    // Check ADMINS table
    $sql = "SELECT admin_id, username, email, password FROM admins WHERE email = ? LIMIT 1";
} elseif ($role === 'technician') {
    $sql = "SELECT technician_id, name, email, password_hash, status FROM technicians WHERE email = ? OR CAST(technician_id AS CHAR) = ? LIMIT 1";
} else {
    // Check USERS table
    $sql = "SELECT user_id, full_name, email, password, email_verified FROM users WHERE email = ? LIMIT 1";
}

$stmt = $conn->prepare($sql);
if ($role === 'technician') {
    $stmt->bind_param('ss', $identifier, $identifier);
} else {
    $stmt->bind_param('s', $identifier);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['errors'] = ['Invalid email or role selection'];
    $_SESSION['old_input'] = ['identifier' => $identifier, 'role' => $role];
    header('Location: login.php');
    exit();
}

$user = $result->fetch_assoc();
$stmt->close();

// Password Verification
$passwordColumn = $role === 'technician' ? 'password_hash' : 'password';
if (!empty($user[$passwordColumn]) && password_verify($password, $user[$passwordColumn])) {
    if ($role === 'patient' && (int)$user['email_verified'] !== 1) {
        $_SESSION['errors'] = ['Please verify your email before logging in.'];
        $_SESSION['pending_verify_email'] = $identifier;
        $_SESSION['old_input'] = ['identifier' => $identifier, 'role' => $role];
        header('Location: login.php');
        exit();
    }

    if ($role === 'technician' && (($user['status'] ?? 'Inactive') !== 'Active')) {
        $_SESSION['errors'] = ['This technician account is inactive.'];
        $_SESSION['old_input'] = ['identifier' => $identifier, 'role' => $role];
        header('Location: login.php');
        exit();
    }

    // Login Success
    
    $_SESSION['role'] = $role; // 'admin', 'patient', or 'technician'
    $_SESSION['user_role'] = $role;

    if ($role === 'admin') {
        $_SESSION['user_id'] = $user['admin_id'];
        $_SESSION['user_name'] = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        header('Location: admin/index.php');
    } elseif ($role === 'technician') {
        $_SESSION['user_id'] = $user['technician_id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['technician_specialization'] = normalize_technician_specialization((string)($user['specialization'] ?? 'Laboratory'));
        header('Location: technician/dashboard.php');
    } else {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_email'] = $user['email'];
        header('Location: patient/dashboard.php');
    }
    exit();

} else {
    $_SESSION['errors'] = ['Invalid password'];
    $_SESSION['old_input'] = ['identifier' => $identifier, 'role' => $role];
    header('Location: login.php');
    exit();
}
?>