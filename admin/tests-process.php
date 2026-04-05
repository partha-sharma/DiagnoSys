<?php
require_once '../config/init.php';

// Gatekeeper: Only admins can do this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied.");
}

function redirect_manage_tests($query = '') {
    header('Location: manage_tests.php' . $query);
    exit();
}

function redirect_manage_rooms($query = '') {
    header('Location: manage_rooms.php' . $query);
    exit();
}

// Handle ADD action (from the form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $test_name = trim($_POST['test_name']);
    $description = trim($_POST['description']);
    $price = (float)($_POST['price'] ?? 0);

    if ($test_name === '' || $price <= 0) {
        $_SESSION['error'] = 'Test name and valid price are required.';
        redirect_manage_tests();
    }

    $stmt = $conn->prepare("INSERT INTO tests (test_name, description, price) VALUES (?, ?, ?)");
    $stmt->bind_param("ssd", $test_name, $description, $price);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Test added successfully.';
    } else {
        $_SESSION['error'] = 'Could not add test. Please try again.';
    }
    $stmt->close();
    redirect_manage_tests();
}

// Handle UPDATE action (from edit modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $test_id = (int)($_POST['test_id'] ?? 0);
    $test_name = trim($_POST['test_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $status = trim($_POST['status'] ?? 'Active');

    if ($test_id <= 0 || $test_name === '' || $price <= 0) {
        $_SESSION['error'] = 'Please provide a valid test to update.';
        redirect_manage_tests();
    }

    if (!in_array($status, ['Active', 'Inactive'], true)) {
        $status = 'Active';
    }

    $stmt = $conn->prepare('UPDATE tests SET test_name = ?, description = ?, price = ?, status = ? WHERE test_id = ?');
    $stmt->bind_param('ssdsi', $test_name, $description, $price, $status, $test_id);

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Test updated successfully.';
    } else {
        $_SESSION['error'] = 'Could not update test. Please try again.';
    }
    $stmt->close();
    redirect_manage_tests();
}

// Handle ADD SLOT action (from manage tests slot modal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_slot') {
    $slot_date = trim($_POST['slot_date'] ?? '');
    $slot_time = trim($_POST['slot_time'] ?? '');
    $max_capacity = (int)($_POST['max_capacity'] ?? 0);
    $status = trim($_POST['status'] ?? 'Available');
    $allowedStatus = ['Available', 'Unavailable', 'Closed'];

    $dateObj = DateTime::createFromFormat('Y-m-d', $slot_date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $slot_date) {
        $_SESSION['error'] = 'Please provide a valid slot date.';
        redirect_manage_rooms();
    }

    $timeObj = DateTime::createFromFormat('H:i', $slot_time);
    if (!$timeObj || $timeObj->format('H:i') !== $slot_time) {
        $_SESSION['error'] = 'Please provide a valid slot time.';
        redirect_manage_rooms('?slot_date=' . urlencode($slot_date));
    }

    if ($max_capacity < 1) {
        $_SESSION['error'] = 'Max capacity must be at least 1.';
        redirect_manage_rooms('?slot_date=' . urlencode($slot_date));
    }

    if (!in_array($status, $allowedStatus, true)) {
        $status = 'Available';
    }

    $hour = (int)$timeObj->format('H');
    $time_period = $hour < 12 ? 'Morning' : ($hour < 17 ? 'Afternoon' : 'Evening');
    $slot_time_db = $timeObj->format('H:i:s');

    $check = $conn->prepare('SELECT slot_id FROM appointment_slots WHERE slot_date = ? AND slot_time = ? LIMIT 1');
    $check->bind_param('ss', $slot_date, $slot_time_db);
    $check->execute();
    $existing = $check->get_result();

    if ($existing->num_rows > 0) {
        $check->close();
        $_SESSION['error'] = 'A slot already exists for this date and time.';
        redirect_manage_rooms('?slot_date=' . urlencode($slot_date));
    }
    $check->close();

    $stmt = $conn->prepare('INSERT INTO appointment_slots (slot_date, slot_time, time_period, max_capacity, status) VALUES (?, ?, ?, ?, ?)');
    $stmt->bind_param('sssis', $slot_date, $slot_time_db, $time_period, $max_capacity, $status);

    if ($stmt->execute()) {
        $_SESSION['success'] = 'Time slot created successfully.';
    } else {
        $_SESSION['error'] = 'Could not create slot. Please try again.';
    }
    $stmt->close();
    redirect_manage_rooms('?slot_date=' . urlencode($slot_date));
}

// Handle BULK SLOT GENERATION action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate_bulk_slots') {
    $slot_date = trim($_POST['slot_date'] ?? '');
    $start_time = trim($_POST['start_time'] ?? '');
    $end_time = trim($_POST['end_time'] ?? '');
    $interval_minutes = (int)($_POST['interval_minutes'] ?? 30);
    $default_capacity = (int)($_POST['default_capacity'] ?? 5);
    $status = trim($_POST['status'] ?? 'Available');
    $allowedStatus = ['Available', 'Unavailable', 'Closed'];

    $dateObj = DateTime::createFromFormat('Y-m-d', $slot_date);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $slot_date) {
        $_SESSION['error'] = 'Please select a valid date for bulk slot creation.';
        redirect_manage_rooms();
    }

    $startObj = DateTime::createFromFormat('H:i', $start_time);
    $endObj = DateTime::createFromFormat('H:i', $end_time);
    if (!$startObj || $startObj->format('H:i') !== $start_time || !$endObj || $endObj->format('H:i') !== $end_time) {
        $_SESSION['error'] = 'Please provide valid start and end times.';
        redirect_manage_rooms('?slot_date=' . urlencode($slot_date));
    }

    if ($startObj >= $endObj) {
        $_SESSION['error'] = 'Start time must be earlier than end time.';
        redirect_manage_rooms('?slot_date=' . urlencode($slot_date));
    }

    if ($interval_minutes < 5 || $interval_minutes > 240) {
        $_SESSION['error'] = 'Interval must be between 5 and 240 minutes.';
        redirect_manage_rooms('?slot_date=' . urlencode($slot_date));
    }

    if ($default_capacity < 1) {
        $_SESSION['error'] = 'Default capacity must be at least 1.';
        redirect_manage_rooms('?slot_date=' . urlencode($slot_date));
    }

    if (!in_array($status, $allowedStatus, true)) {
        $status = 'Available';
    }

    $checkStmt = $conn->prepare('SELECT slot_id FROM appointment_slots WHERE slot_date = ? AND slot_time = ? LIMIT 1');
    $insertStmt = $conn->prepare('INSERT INTO appointment_slots (slot_date, slot_time, time_period, max_capacity, status) VALUES (?, ?, ?, ?, ?)');

    $created = 0;
    $skipped = 0;
    $cursor = clone $startObj;
    $guard = 0;

    while ($cursor < $endObj) {
        $guard++;
        if ($guard > 500) {
            break;
        }

        $slotTime = $cursor->format('H:i:s');

        $checkStmt->bind_param('ss', $slot_date, $slotTime);
        $checkStmt->execute();
        $exists = $checkStmt->get_result();

        if ($exists->num_rows > 0) {
            $skipped++;
            $cursor->modify('+' . $interval_minutes . ' minutes');
            continue;
        }

        $hour = (int)$cursor->format('H');
        $time_period = $hour < 12 ? 'Morning' : ($hour < 17 ? 'Afternoon' : 'Evening');

        $insertStmt->bind_param('sssis', $slot_date, $slotTime, $time_period, $default_capacity, $status);
        if ($insertStmt->execute()) {
            $created++;
        }

        $cursor->modify('+' . $interval_minutes . ' minutes');
    }

    $checkStmt->close();
    $insertStmt->close();

    if ($created > 0) {
        $_SESSION['success'] = "Bulk slots created: {$created}. Skipped existing: {$skipped}.";
    } else {
        $_SESSION['error'] = 'No new slots were created. They may already exist for this range.';
    }

    redirect_manage_rooms('?slot_date=' . urlencode($slot_date));
}

// Handle DELETE action (from the link)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $test_id = intval($_GET['id']);

    if ($test_id <= 0) {
        $_SESSION['error'] = 'Invalid test selected.';
        redirect_manage_tests();
    }

    $stmt = $conn->prepare("DELETE FROM tests WHERE test_id = ?");
    $stmt->bind_param("i", $test_id);
    if ($stmt->execute()) {
        $_SESSION['success'] = 'Test deleted successfully.';
    } else {
        $_SESSION['error'] = 'Could not delete test.';
    }
    $stmt->close();
    redirect_manage_tests();
}

// Handle DELETE SLOT action
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_slot') {
    $slot_id = (int)($_GET['id'] ?? 0);
    $slot_date = trim($_GET['slot_date'] ?? '');
    $query = $slot_date !== '' ? '?slot_date=' . urlencode($slot_date) : '';

    if ($slot_id <= 0) {
        $_SESSION['error'] = 'Invalid slot selected.';
        redirect_manage_rooms($query);
    }

    $checkStmt = $conn->prepare('SELECT booked_count FROM appointment_slots WHERE slot_id = ? LIMIT 1');
    $checkStmt->bind_param('i', $slot_id);
    $checkStmt->execute();
    $slotResult = $checkStmt->get_result();

    if ($slotResult->num_rows === 0) {
        $checkStmt->close();
        $_SESSION['error'] = 'Slot not found.';
        redirect_manage_rooms($query);
    }

    $slotRow = $slotResult->fetch_assoc();
    $checkStmt->close();

    if ((int)$slotRow['booked_count'] > 0) {
        $_SESSION['error'] = 'Cannot delete a slot that already has bookings.';
        redirect_manage_rooms($query);
    }

    $deleteStmt = $conn->prepare('DELETE FROM appointment_slots WHERE slot_id = ?');
    $deleteStmt->bind_param('i', $slot_id);

    if ($deleteStmt->execute()) {
        $_SESSION['success'] = 'Time slot deleted successfully.';
    } else {
        $_SESSION['error'] = 'Could not delete slot.';
    }
    $deleteStmt->close();
    redirect_manage_rooms($query);
}

// Redirect back to the management page after action
redirect_manage_tests();
?>