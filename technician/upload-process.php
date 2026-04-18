<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'technician') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'patient') {
        header('Location: ../patient/dashboard.php');
        exit();
    }

    if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
        header('Location: ../admin/index.php');
        exit();
    }

    header('Location: ../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

$technicianId = (int)$_SESSION['user_id'];
$technicianSpecialization = normalize_technician_specialization((string)($_SESSION['technician_specialization'] ?? 'Laboratory'));
$appointmentId = (int)($_POST['appointment_id'] ?? 0);

if ($appointmentId <= 0) {
    $_SESSION['error'] = 'Invalid appointment selected.';
    header('Location: dashboard.php');
    exit();
}

$ownershipStmt = $conn->prepare("SELECT a.appointment_id
    FROM appointments a
    INNER JOIN appointment_tests apt ON a.appointment_id = apt.appointment_id
    INNER JOIN tests t ON apt.test_id = t.test_id
    WHERE a.appointment_id = ?
      AND COALESCE(t.test_category, '') = ?
    LIMIT 1");
$ownershipStmt->bind_param('is', $appointmentId, $technicianSpecialization);
$ownershipStmt->execute();
$ownershipResult = $ownershipStmt->get_result();
$allowedAppointment = $ownershipResult && $ownershipResult->num_rows > 0;
$ownershipStmt->close();

if (!$allowedAppointment) {
    $_SESSION['error'] = 'You can only upload results for appointments that match your specialization.';
    header('Location: dashboard.php');
    exit();
}

$uploadDir = '../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!isset($_FILES['report_file']) || $_FILES['report_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = 'File upload failed. Please try again.';
    header('Location: dashboard.php');
    exit();
}

if ($_FILES['report_file']['size'] > 5 * 1024 * 1024) {
    $_SESSION['error'] = 'File is too large. Maximum size is 5 MB.';
    header('Location: dashboard.php');
    exit();
}

$allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
$fileType = mime_content_type($_FILES['report_file']['tmp_name']);
if (!in_array($fileType, $allowedTypes, true)) {
    $_SESSION['error'] = 'Invalid file type. Only PDF, JPG, and PNG are allowed.';
    header('Location: dashboard.php');
    exit();
}

$safeFileName = time() . '_' . bin2hex(random_bytes(4)) . '_' . preg_replace('/[^A-Za-z0-9._-]/', '_', basename($_FILES['report_file']['name']));
$targetFile = $uploadDir . $safeFileName;

if (move_uploaded_file($_FILES['report_file']['tmp_name'], $targetFile)) {
    $stmt = $conn->prepare("INSERT INTO test_results (appointment_id, admin_id, technician_id, file_path) VALUES (?, NULL, ?, ?)");
    $stmt->bind_param('iis', $appointmentId, $technicianId, $targetFile);
    $stmt->execute();
    $stmt->close();

    $appointmentUpdate = $conn->prepare("UPDATE appointments SET status = 'Completed', sample_status = 'Completed' WHERE appointment_id = ?");
    $appointmentUpdate->bind_param('i', $appointmentId);
    $appointmentUpdate->execute();
    $appointmentUpdate->close();

    $trackingCheck = $conn->prepare("SELECT tracking_id FROM sample_tracking WHERE appointment_id = ? LIMIT 1");
    $trackingCheck->bind_param('i', $appointmentId);
    $trackingCheck->execute();
    $trackingResult = $trackingCheck->get_result();
    $trackingExists = $trackingResult && $trackingResult->num_rows > 0;
    $trackingCheck->close();

    if ($trackingExists) {
        $trackingUpdate = $conn->prepare("UPDATE sample_tracking SET status = 'Completed', completed_at = NOW(), report_ready_at = NOW() WHERE appointment_id = ?");
        $trackingUpdate->bind_param('i', $appointmentId);
        $trackingUpdate->execute();
        $trackingUpdate->close();
    } else {
        $trackingInsert = $conn->prepare("INSERT INTO sample_tracking (appointment_id, status, report_ready_at, completed_at, notes) VALUES (?, 'Completed', NOW(), NOW(), ?)");
        $note = 'Report uploaded by technician';
        $trackingInsert->bind_param('is', $appointmentId, $note);
        $trackingInsert->execute();
        $trackingInsert->close();
    }

    $_SESSION['success'] = 'Report uploaded successfully.';
} else {
    $_SESSION['error'] = 'Failed to save the file. Please try again.';
}

header('Location: dashboard.php');
exit();


