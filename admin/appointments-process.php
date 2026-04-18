<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: appointments.php');
    exit();
}

$action = $_POST['action'] ?? '';
$appointment_id = (int)($_POST['appointment_id'] ?? 0);

if ($appointment_id <= 0 && $action !== '') {
    $_SESSION['error'] = 'Invalid appointment reference.';
    header('Location: appointments.php');
    exit();
}

switch ($action) {
    case 'reschedule':
        $new_date = trim($_POST['new_date'] ?? '');
        $new_time = trim($_POST['new_time'] ?? '');
        if ($new_date === '' || $new_time === '') {
            $_SESSION['error'] = 'Date and time are required for reschedule.';
            break;
        }

        $new_datetime = $new_date . ' ' . $new_time . ':00';
        $stmt = $conn->prepare("UPDATE appointments SET appointment_date = ?, status = 'Confirmed' WHERE appointment_id = ?");
        $stmt->bind_param('si', $new_datetime, $appointment_id);
        $stmt->execute();
        $stmt->close();

        $history = $conn->prepare("INSERT INTO appointment_history (appointment_id, status) VALUES (?, 'Rescheduled')");
        $history->bind_param('i', $appointment_id);
        $history->execute();
        $history->close();

        $_SESSION['success'] = 'Appointment rescheduled successfully.';
        break;

    case 'cancel':
        $reason = trim($_POST['cancellation_reason'] ?? '');
        if ($reason === '') {
            $_SESSION['error'] = 'Cancellation reason is required.';
            break;
        }

        $stmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled', cancellation_reason = ? WHERE appointment_id = ?");
        $stmt->bind_param('si', $reason, $appointment_id);
        $stmt->execute();
        $stmt->close();

        $history = $conn->prepare("INSERT INTO appointment_history (appointment_id, status) VALUES (?, 'Cancelled')");
        $history->bind_param('i', $appointment_id);
        $history->execute();
        $history->close();

        $_SESSION['success'] = 'Appointment cancelled successfully.';
        break;

    case 'add_note':
        $note = trim($_POST['note_text'] ?? '');
        if ($note === '') {
            $_SESSION['error'] = 'Note text is required.';
            break;
        }

        $admin_id = (int)$_SESSION['user_id'];
        $stmt = $conn->prepare("INSERT INTO appointment_notes (appointment_id, admin_id, note_text) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $appointment_id, $admin_id, $note);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success'] = 'Admin note saved successfully.';
        break;

    case 'update_sample_status':
        $sample_status = trim($_POST['sample_status'] ?? 'Pending');
        $allowed = ['Pending', 'Collected', 'Processing', 'Report Ready', 'Completed'];
        if (!in_array($sample_status, $allowed, true)) {
            $_SESSION['error'] = 'Invalid sample status selected.';
            break;
        }

        $stmt = $conn->prepare("UPDATE appointments SET sample_status = ? WHERE appointment_id = ?");
        $stmt->bind_param('si', $sample_status, $appointment_id);
        $stmt->execute();
        $stmt->close();

        $check = $conn->prepare("SELECT tracking_id FROM sample_tracking WHERE appointment_id = ? LIMIT 1");
        $check->bind_param('i', $appointment_id);
        $check->execute();
        $result = $check->get_result();
        $exists = $result->num_rows > 0;
        $check->close();

        if ($exists) {
            $updateTrack = $conn->prepare("UPDATE sample_tracking SET status = ? WHERE appointment_id = ?");
            $updateTrack->bind_param('si', $sample_status, $appointment_id);
            $updateTrack->execute();
            $updateTrack->close();
        } else {
            $insertTrack = $conn->prepare("INSERT INTO sample_tracking (appointment_id, status) VALUES (?, ?)");
            $insertTrack->bind_param('is', $appointment_id, $sample_status);
            $insertTrack->execute();
            $insertTrack->close();
        }

        $_SESSION['success'] = 'Sample status updated successfully.';
        break;

    default:
        $_SESSION['error'] = 'Unknown action requested.';
        break;
}

$conn->close();
header('Location: appointments.php');
exit();

