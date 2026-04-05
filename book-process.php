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
    $test_ids = array_values(array_unique(array_map('intval', $_POST['test_ids'] ?? [])));
    $package_id = (int)($_POST['package_id'] ?? 0);
    $coupon_code = $_POST['coupon_code'] ?? '';
    $discount_amount = floatval($_POST['discount_amount'] ?? 0);
    $is_home_collection = isset($_POST['is_home_collection']) ? 1 : 0;
    $collection_address = trim($_POST['collection_address'] ?? '');
    $collection_time = trim($_POST['collection_time'] ?? '');
    $collection_charge = floatval($_POST['collection_charge'] ?? 0);
    $assigned_technician_id = (int)($_POST['assigned_technician_id'] ?? 0);

    $doctor_name = trim($_POST['doctor_name'] ?? '');
    $doctor_hospital = trim($_POST['doctor_hospital'] ?? '');
    $doctor_specialty = trim($_POST['doctor_specialty'] ?? '');
    $doctor_contact = trim($_POST['doctor_contact'] ?? '');
    $referral_notes = trim($_POST['referral_notes'] ?? '');

    $packageSnapshot = null;
    $packageNameSnapshot = null;
    $packagePriceSnapshot = 0.00;
    $packageTestIds = [];
    $packageTestRows = [];

    if ($package_id > 0) {
        $packageStmt = $conn->prepare('SELECT package_id, name, description, base_price, discount_percent, final_price, status FROM packages WHERE package_id = ? AND status = "Active" LIMIT 1');
        $packageStmt->bind_param('i', $package_id);
        $packageStmt->execute();
        $packageResult = $packageStmt->get_result();

        if ($packageResult->num_rows === 0) {
            $_SESSION['error'] = 'Selected package is not available.';
            header('Location: book-appointment.php');
            exit();
        }

        $package = $packageResult->fetch_assoc();
        $packageNameSnapshot = $package['name'];
        $packagePriceSnapshot = (float)$package['final_price'];

        $packageTestsStmt = $conn->prepare('SELECT pt.test_id, pt.package_test_price, t.test_name, t.price AS master_price FROM package_tests pt INNER JOIN tests t ON t.test_id = pt.test_id WHERE pt.package_id = ? ORDER BY t.test_name ASC');
        $packageTestsStmt->bind_param('i', $package_id);
        $packageTestsStmt->execute();
        $packageTestsResult = $packageTestsStmt->get_result();

        while ($row = $packageTestsResult->fetch_assoc()) {
            $testId = (int)$row['test_id'];
            $packageTestIds[] = $testId;
            $packageTestRows[] = [
                'test_id' => $testId,
                'test_name' => $row['test_name'],
                'package_test_price' => (float)$row['package_test_price'],
                'master_price' => (float)$row['master_price'],
            ];
        }

        $packageTestsStmt->close();
        $packageStmt->close();

        $packageSnapshot = json_encode($packageTestRows);
        $test_ids = array_values(array_diff($test_ids, $packageTestIds));
    }

    // Validation
    if (empty($appointment_date) || empty($appointment_time) || (empty($package_id) && empty($test_ids))) {
        $_SESSION['error'] = "Please select a date, time, and at least one test or a package.";
        header("Location: book-appointment.php");
        exit();
    }

    if ($is_home_collection === 1 && ($collection_address === '' || $collection_time === '')) {
        $_SESSION['error'] = 'Home collection requires address and collection time.';
        header('Location: book-appointment.php');
        exit();
    }

    // Calculate total amount
    $subtotal = 0.0;

    if ($package_id > 0) {
        $subtotal += $packagePriceSnapshot;
    }

    if (!empty($test_ids)) {
        $test_ids_str = implode(',', array_map('intval', $test_ids));
        $tests_query = $conn->query("SELECT SUM(price) as total FROM tests WHERE test_id IN ($test_ids_str)");
        $total_row = $tests_query ? $tests_query->fetch_assoc() : null;
        $subtotal += (float)($total_row['total'] ?? 0);
    }

    $total_amount = max(0, $subtotal - $discount_amount + ($is_home_collection ? $collection_charge : 0));

    // Combine date and time into a single datetime string
    $appointment_datetime = $appointment_date . ' ' . $appointment_time . ':00';

    // --- TRANSACTION PART ---
    $conn->begin_transaction();

    try {
        // Check if coupon columns exist in appointments table
        $check_columns = $conn->query("SHOW COLUMNS FROM appointments LIKE 'coupon_code'");
        $has_coupon_columns = ($check_columns && $check_columns->num_rows > 0);
        $check_package_columns = $conn->query("SHOW COLUMNS FROM appointments LIKE 'package_id'");
        $has_package_columns = ($check_package_columns && $check_package_columns->num_rows > 0);

        // 1. Create the main appointment record
        if ($has_coupon_columns && $has_package_columns) {
            $stmt = $conn->prepare("INSERT INTO appointments (user_id, appointment_date, package_id, package_name_snapshot, package_tests_snapshot, package_price_snapshot, coupon_code, discount_amount, total_amount, is_home_collection, collection_address, collection_time, collection_charge, assigned_technician_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
            $techValue = $assigned_technician_id > 0 ? $assigned_technician_id : null;
            $collectionDatetime = $collection_time !== '' ? date('Y-m-d H:i:s', strtotime($collection_time)) : null;
            $collectionAddressValue = $is_home_collection ? $collection_address : null;
            $collectionChargeValue = $is_home_collection ? $collection_charge : 0;
            $packageIdValue = $package_id > 0 ? $package_id : null;
            $stmt->bind_param("isissdsddissdi", $user_id, $appointment_datetime, $packageIdValue, $packageNameSnapshot, $packageSnapshot, $packagePriceSnapshot, $coupon_code, $discount_amount, $total_amount, $is_home_collection, $collectionAddressValue, $collectionDatetime, $collectionChargeValue, $techValue);
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

        foreach ($packageTestIds as $testId) {
            $stmt_link->bind_param("ii", $appointment_id, $testId);
            $stmt_link->execute();
        }

        foreach ($test_ids as $test_id) {
            $test_id = (int) $test_id;
            $stmt_link->bind_param("ii", $appointment_id, $test_id);
            $stmt_link->execute();
        }

        if ($doctor_name !== '') {
            $doctorStmt = $conn->prepare("INSERT INTO doctor_referrals (appointment_id, doctor_name, hospital, specialty, contact_number, referral_notes) VALUES (?, ?, ?, ?, ?, ?)");
            $doctorStmt->bind_param('isssss', $appointment_id, $doctor_name, $doctor_hospital, $doctor_specialty, $doctor_contact, $referral_notes);
            $doctorStmt->execute();
            $doctorStmt->close();
        }

        $sampleStmt = $conn->prepare("INSERT INTO sample_tracking (appointment_id, status, notes) VALUES (?, 'Pending', ?)");
        $initialNote = $is_home_collection ? 'Home collection requested' : 'Lab collection';
        $sampleStmt->bind_param('is', $appointment_id, $initialNote);
        $sampleStmt->execute();
        $sampleStmt->close();

        $stmt->close();
        $stmt_link->close();

        $conn->commit();
        $_SESSION['success'] = "Appointment booked successfully. Please complete payment to confirm your test schedule.";
        header("Location: payment.php?appointment_id=" . $appointment_id);
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "There was a problem booking your appointment. Please try again.";
    }

    header("Location: book-appointment.php");
    exit();
}
?>