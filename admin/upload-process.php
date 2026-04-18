<?php
require_once '../config/init.php';

// Admin Gatekeeper
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'patient') {
        header("Location: ../patient/dashboard.php");
        exit();
    }
    header("Location: ../auth/login.php");
    exit();
}

$admin_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appointment_id = intval($_POST['appointment_id']);

    if ($appointment_id <= 0) {
        $_SESSION['error'] = "Invalid appointment ID.";
        header("Location: appointments.php");
        exit();
    }

    // --- Create 'uploads' folder if it doesn't exist ---
    $upload_dir = '../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Validate the uploaded file
    if (!isset($_FILES['report_file']) || $_FILES['report_file']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = "File upload failed. Please try again.";
        header("Location: upload_form.php?id=" . $appointment_id);
        exit();
    }

    // Check file size (max 5 MB)
    if ($_FILES['report_file']['size'] > 5 * 1024 * 1024) {
        $_SESSION['error'] = "File is too large. Maximum size is 5 MB.";
        header("Location: upload_form.php?id=" . $appointment_id);
        exit();
    }

    // Check file type
    $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
    $file_type = mime_content_type($_FILES['report_file']['tmp_name']);
    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['error'] = "Invalid file type. Only PDF, JPG, and PNG are allowed.";
        header("Location: upload_form.php?id=" . $appointment_id);
        exit();
    }

    $file_name = time() . '_' . basename($_FILES["report_file"]["name"]);
    $target_file = $upload_dir . $file_name;

    // Move the uploaded file
    if (move_uploaded_file($_FILES["report_file"]["tmp_name"], $target_file)) {
        // Save the file path to the database
        $stmt = $conn->prepare("INSERT INTO test_results (appointment_id, admin_id, file_path) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $appointment_id, $admin_id, $target_file);
        $stmt->execute();
        $stmt->close();

        // Also update the appointment status to 'Completed'
        $stmt_update = $conn->prepare("UPDATE appointments SET status = 'Completed' WHERE appointment_id = ?");
        $stmt_update->bind_param("i", $appointment_id);
        $stmt_update->execute();
        $stmt_update->close();

        $_SESSION['success'] = "Report uploaded successfully and appointment marked as Completed.";
    } else {
        $_SESSION['error'] = "Failed to save the file. Please try again.";
    }

    header("Location: appointments.php");
    exit();
}

// If accessed via GET, redirect back
header("Location: appointments.php");
exit();
?>


