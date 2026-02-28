<?php
require_once 'config/init.php';

// Gatekeeper: Only patients can process bookings
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    die("Access Denied.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $test_ids = $_POST['test_ids'] ?? []; // Get array of selected tests

    // Validation
    if (empty($appointment_date) || empty($appointment_time) || empty($test_ids)) {
        $_SESSION['error'] = "Please select a date, time, and at least one test.";
        header("Location: book-appointment.php");
        exit();
    }

    // Combine date and time into a single datetime string
    $appointment_datetime = $appointment_date . ' ' . $appointment_time . ':00';

    // --- TRANSACTION PART ---
    $conn->begin_transaction();

    try {
        // 1. Create the main appointment record
        $stmt = $conn->prepare("INSERT INTO appointments (user_id, appointment_date) VALUES (?, ?)");
        $stmt->bind_param("is", $user_id, $appointment_datetime);
        $stmt->execute();

        // 2. Get the ID of the appointment we just created
        $appointment_id = $conn->insert_id;

        // 3. Link the selected tests to this appointment
        $stmt_link = $conn->prepare("INSERT INTO appointment_tests (appointment_id, test_id) VALUES (?, ?)");
        foreach ($test_ids as $test_id) {
            $test_id = (int) $test_id;
            $stmt_link->bind_param("ii", $appointment_id, $test_id);
            $stmt_link->execute();
        }

        $stmt->close();
        $stmt_link->close();

        $conn->commit();
        $_SESSION['success'] = "Appointment booked successfully with your selected tests.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "There was a problem booking your appointment. Please try again.";
    }

    header("Location: dashboard.php");
    exit();
}
?>