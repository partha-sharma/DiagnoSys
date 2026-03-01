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
    $coupon_code = $_POST['coupon_code'] ?? '';
    $discount_amount = floatval($_POST['discount_amount'] ?? 0);

    // Validation
    if (empty($appointment_date) || empty($appointment_time) || empty($test_ids)) {
        $_SESSION['error'] = "Please select a date, time, and at least one test.";
        header("Location: book-appointment.php");
        exit();
    }

    // Calculate total amount
    $test_ids_str = implode(',', array_map('intval', $test_ids));
    $tests_query = $conn->query("SELECT SUM(price) as total FROM tests WHERE test_id IN ($test_ids_str)");
    $total_row = $tests_query->fetch_assoc();
    $subtotal = $total_row['total'] ?? 0;
    $total_amount = max(0, $subtotal - $discount_amount);

    // Combine date and time into a single datetime string
    $appointment_datetime = $appointment_date . ' ' . $appointment_time . ':00';

    // --- TRANSACTION PART ---
    $conn->begin_transaction();

    try {
        // Check if coupon columns exist in appointments table
        $check_columns = $conn->query("SHOW COLUMNS FROM appointments LIKE 'coupon_code'");
        $has_coupon_columns = ($check_columns && $check_columns->num_rows > 0);

        // 1. Create the main appointment record
        if ($has_coupon_columns) {
            $stmt = $conn->prepare("INSERT INTO appointments (user_id, appointment_date, coupon_code, discount_amount, total_amount) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issdd", $user_id, $appointment_datetime, $coupon_code, $discount_amount, $total_amount);
        } else {
            // Fallback to original structure if columns don't exist
            $stmt = $conn->prepare("INSERT INTO appointments (user_id, appointment_date) VALUES (?, ?)");
            $stmt->bind_param("is", $user_id, $appointment_datetime);
        }
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