<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'patient') {
        header('Location: ../dashboard.php');
        exit();
    }
    header('Location: ../login.php');
    exit();
}

function normalize_test_type_from_name(string $name): string
{
    $n = strtolower($name);
    if (str_contains($n, 'x-ray') || str_contains($n, 'xray') || str_contains($n, 'radiology')) {
        return 'Radiology';
    }
    if (str_contains($n, 'ecg') || str_contains($n, 'echo') || str_contains($n, 'troponin') || str_contains($n, 'heart') || str_contains($n, 'blood pressure')) {
        return 'Cardiology';
    }
    if (str_contains($n, 'urine')) {
        return 'Urine';
    }
    if (str_contains($n, 'thyroid')) {
        return 'Endocrinology';
    }
    if (str_contains($n, 'liver') || str_contains($n, 'kidney') || str_contains($n, 'lipid') || str_contains($n, 'blood') || str_contains($n, 'cbc') || str_contains($n, 'hba1c') || str_contains($n, 'crp')) {
        return 'Blood Test';
    }
    return 'General';
}

function redirect_manage_rooms(): void
{
    header('Location: manage_rooms.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_room') {
        $roomNumber = trim($_POST['room_number'] ?? '');
        $department = trim($_POST['department'] ?? '');

        if ($roomNumber === '') {
            $_SESSION['error'] = 'Room number is required.';
            redirect_manage_rooms();
        }

        $stmt = $conn->prepare('INSERT INTO rooms (room_number, department, status) VALUES (?, ?, "Active")');
        $stmt->bind_param('ss', $roomNumber, $department);
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Room added successfully.';
        } else {
            $_SESSION['error'] = 'Could not add room. Ensure room number is unique.';
        }
        $stmt->close();
        redirect_manage_rooms();
    }

    if ($action === 'add_slot') {
        $slotLabel = trim($_POST['slot_label'] ?? '');
        $startTime = trim($_POST['start_time'] ?? '');
        $endTime = trim($_POST['end_time'] ?? '');

        if ($slotLabel === '' || $startTime === '' || $endTime === '') {
            $_SESSION['error'] = 'Slot label, start time, and end time are required.';
            redirect_manage_rooms();
        }

        if (strtotime($startTime) >= strtotime($endTime)) {
            $_SESSION['error'] = 'Start time must be before end time.';
            redirect_manage_rooms();
        }

        $stmt = $conn->prepare('INSERT INTO room_time_slots (slot_label, start_time, end_time, status) VALUES (?, ?, ?, "Active")');
        $stmt->bind_param('sss', $slotLabel, $startTime, $endTime);
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Time slot added successfully.';
        } else {
            $_SESSION['error'] = 'Could not add time slot. Ensure slot label is unique.';
        }
        $stmt->close();
        redirect_manage_rooms();
    }

    if ($action === 'save_assignment') {
        $roomId = (int)($_POST['room_id'] ?? 0);
        $slotId = (int)($_POST['slot_id'] ?? 0);
        $mapScope = trim($_POST['map_scope'] ?? 'type');
        $mappedTestType = trim($_POST['mapped_test_type'] ?? '');
        $mappedTestId = (int)($_POST['mapped_test_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'Active');
        $capacity = (int)($_POST['capacity'] ?? 0);
        $colorCode = trim($_POST['color_code'] ?? '#dbeafe');

        if ($roomId <= 0 || $slotId <= 0) {
            $_SESSION['error'] = 'Invalid room/slot assignment.';
            redirect_manage_rooms();
        }

        if (!in_array($mapScope, ['type', 'test'], true)) {
            $mapScope = 'type';
        }

        if ($mapScope === 'type' && $mappedTestType === '') {
            $_SESSION['error'] = 'Test type is required for type-based mapping.';
            redirect_manage_rooms();
        }

        if ($mapScope === 'test' && $mappedTestId <= 0) {
            $_SESSION['error'] = 'Specific test is required for test-based mapping.';
            redirect_manage_rooms();
        }

        if (!in_array($status, ['Active', 'Inactive'], true)) {
            $status = 'Active';
        }

        if ($capacity < 1) {
            $capacity = 1;
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colorCode)) {
            $colorCode = '#dbeafe';
        }

        $mappedTypeValue = $mapScope === 'type' ? $mappedTestType : null;
        $mappedTestValue = $mapScope === 'test' ? $mappedTestId : null;

        $sql = 'INSERT INTO room_assignments (room_id, slot_id, map_scope, mapped_test_type, mapped_test_id, status, capacity, color_code)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    map_scope = VALUES(map_scope),
                    mapped_test_type = VALUES(mapped_test_type),
                    mapped_test_id = VALUES(mapped_test_id),
                    status = VALUES(status),
                    capacity = VALUES(capacity),
                    color_code = VALUES(color_code)';

        $stmt = $conn->prepare($sql);
        $stmt->bind_param('iissisis', $roomId, $slotId, $mapScope, $mappedTypeValue, $mappedTestValue, $status, $capacity, $colorCode);
        if ($stmt->execute()) {
            $_SESSION['success'] = 'Room assignment saved.';
        } else {
            $_SESSION['error'] = 'Could not save room assignment.';
        }
        $stmt->close();
        redirect_manage_rooms();
    }

    if ($action === 'bulk_assign_room') {
        $roomId = (int)($_POST['room_id'] ?? 0);
        $mapScope = trim($_POST['map_scope'] ?? 'type');
        $mappedTestType = trim($_POST['mapped_test_type'] ?? '');
        $mappedTestId = (int)($_POST['mapped_test_id'] ?? 0);
        $status = trim($_POST['status'] ?? 'Active');
        $capacity = (int)($_POST['capacity'] ?? 0);
        $colorCode = trim($_POST['color_code'] ?? '#dbeafe');

        if ($roomId <= 0) {
            $_SESSION['error'] = 'Please select a room for bulk scheduling.';
            redirect_manage_rooms();
        }

        if (!in_array($mapScope, ['type', 'test'], true)) {
            $mapScope = 'type';
        }

        if ($mapScope === 'type' && $mappedTestType === '') {
            $_SESSION['error'] = 'Test type is required for type-based bulk assignment.';
            redirect_manage_rooms();
        }

        if ($mapScope === 'test' && $mappedTestId <= 0) {
            $_SESSION['error'] = 'Specific test is required for test-based bulk assignment.';
            redirect_manage_rooms();
        }

        if (!in_array($status, ['Active', 'Inactive'], true)) {
            $status = 'Active';
        }

        if ($capacity < 1) {
            $capacity = 1;
        }

        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $colorCode)) {
            $colorCode = '#dbeafe';
        }

        $mappedTypeValue = $mapScope === 'type' ? $mappedTestType : null;
        $mappedTestValue = $mapScope === 'test' ? $mappedTestId : null;

        $slotsResult = $conn->query('SELECT slot_id FROM room_time_slots WHERE status = "Active" ORDER BY start_time ASC');
        $saveStmt = $conn->prepare('INSERT INTO room_assignments (room_id, slot_id, map_scope, mapped_test_type, mapped_test_id, status, capacity, color_code)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE
                                        map_scope = VALUES(map_scope),
                                        mapped_test_type = VALUES(mapped_test_type),
                                        mapped_test_id = VALUES(mapped_test_id),
                                        status = VALUES(status),
                                        capacity = VALUES(capacity),
                                        color_code = VALUES(color_code)');

        $count = 0;
        while ($slotRow = $slotsResult->fetch_assoc()) {
            $slotId = (int)$slotRow['slot_id'];
            $saveStmt->bind_param('iissisis', $roomId, $slotId, $mapScope, $mappedTypeValue, $mappedTestValue, $status, $capacity, $colorCode);
            $saveStmt->execute();
            $count++;
        }

        $saveStmt->close();
        $_SESSION['success'] = "Bulk schedule applied for {$count} time slots.";
        redirect_manage_rooms();
    }
}

$testsResult = $conn->query('SELECT test_id, test_name, COALESCE(test_type, "") AS test_type, price FROM tests ORDER BY test_name ASC');
$tests = [];
$testTypeSet = [];
while ($row = $testsResult->fetch_assoc()) {
    $row['test_type'] = trim($row['test_type']) !== '' ? $row['test_type'] : normalize_test_type_from_name($row['test_name']);
    $tests[] = $row;
    $testTypeSet[$row['test_type']] = true;
}
$testTypes = array_keys($testTypeSet);
sort($testTypes);

$filterRoom = (int)($_GET['room_id'] ?? 0);
$filterSlot = (int)($_GET['slot_id'] ?? 0);
$filterType = trim($_GET['filter_type'] ?? '');

$roomsSql = 'SELECT room_id, room_number, department, status FROM rooms ORDER BY room_number ASC';
$roomsResult = $conn->query($roomsSql);
$allRooms = [];
while ($r = $roomsResult->fetch_assoc()) {
    $allRooms[] = $r;
}

$rooms = $allRooms;
if ($filterRoom > 0) {
    $rooms = array_values(array_filter($allRooms, static function (array $room) use ($filterRoom): bool {
        return (int)$room['room_id'] === $filterRoom;
    }));
}

$slotsResult = $conn->query('SELECT slot_id, slot_label, start_time, end_time, status FROM room_time_slots ORDER BY start_time ASC');
$allSlots = [];
while ($s = $slotsResult->fetch_assoc()) {
    $allSlots[] = $s;
}

$slots = $allSlots;
if ($filterSlot > 0) {
    $slots = array_values(array_filter($allSlots, static function (array $slot) use ($filterSlot): bool {
        return (int)$slot['slot_id'] === $filterSlot;
    }));
}

$assignmentSql = "SELECT ra.assignment_id, ra.room_id, ra.slot_id, ra.map_scope, ra.mapped_test_type, ra.mapped_test_id,
                         ra.status, ra.capacity, ra.booked_count, ra.color_code,
                         t.test_name
                  FROM room_assignments ra
                  LEFT JOIN tests t ON ra.mapped_test_id = t.test_id";
$assignmentResult = $conn->query($assignmentSql);
$assignmentMap = [];
while ($a = $assignmentResult->fetch_assoc()) {
    if ($filterType !== '') {
        $name = $a['map_scope'] === 'test' ? ($a['test_name'] ?? '') : ($a['mapped_test_type'] ?? '');
        if (stripos($name, $filterType) === false) {
            continue;
        }
    }

    $key = $a['room_id'] . '_' . $a['slot_id'];
    $assignmentMap[$key] = $a;
}

$planSql = "SELECT p.room_id, p.slot_id, p.appointment_id, p.test_name_snapshot,
                   COALESCE(t.test_type, '') AS test_type,
                   u.user_id, u.full_name,
                   a.appointment_date,
                   dr.doctor_name
            FROM appointment_test_plan p
            INNER JOIN appointments a ON p.appointment_id = a.appointment_id
            INNER JOIN users u ON a.user_id = u.user_id
            LEFT JOIN tests t ON p.test_id = t.test_id
            LEFT JOIN doctor_referrals dr ON p.appointment_id = dr.appointment_id
            WHERE p.room_id IS NOT NULL
              AND p.slot_id IS NOT NULL
              AND p.status = 'Planned'
            ORDER BY a.appointment_date DESC, p.sequence_no ASC";
$planResult = $conn->query($planSql);
$patientsByCell = [];
if ($planResult) {
    while ($row = $planResult->fetch_assoc()) {
        $key = ((int)$row['room_id']) . '_' . ((int)$row['slot_id']);
        $testType = trim((string)$row['test_type']);
        if ($testType === '') {
            $testType = normalize_test_type_from_name((string)$row['test_name_snapshot']);
        }

        $patientsByCell[$key][] = [
            'patient_id' => (int)$row['user_id'],
            'patient_name' => $row['full_name'],
            'test_type' => $testType,
            'test_name' => $row['test_name_snapshot'],
            'reference' => 'APT-' . (int)$row['appointment_id'],
            'doctor_ref' => $row['doctor_name'] ?: 'N/A',
            'appointment_date' => $row['appointment_date'],
        ];
    }
}

$slotDateFilter = trim($_GET['slot_date'] ?? date('Y-m-d'));
$slotDateObj = DateTime::createFromFormat('Y-m-d', $slotDateFilter);
if (!$slotDateObj || $slotDateObj->format('Y-m-d') !== $slotDateFilter) {
    $slotDateFilter = date('Y-m-d');
}

$appointmentSlotStmt = $conn->prepare('SELECT slot_id, slot_time, max_capacity, booked_count, status FROM appointment_slots WHERE slot_date = ? ORDER BY slot_time ASC');
$appointmentSlotStmt->bind_param('s', $slotDateFilter);
$appointmentSlotStmt->execute();
$appointmentSlotResult = $appointmentSlotStmt->get_result();
$appointmentSlots = [];
while ($row = $appointmentSlotResult->fetch_assoc()) {
    $appointmentSlots[] = $row;
}
$appointmentSlotStmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Rooms - DiagnoLab</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="admin-layout">
    <div class="sidebar">
        <h2 style="margin-bottom: 30px;">Admin Panel</h2>
        <a href="index.php">Dashboard</a>
        <a href="manage_tests.php">Manage Tests</a>
        <a href="appointments.php">Appointments</a>
        <a href="technicians.php">Technicians</a>
        <a href="packages.php">Packages</a>
        <a href="manage_rooms.php" class="active">Manage Rooms</a>
        <a href="finance.php">Finance</a>
        <a href="users.php">Patients List</a>
        <hr style="border-color: #334155; margin: 20px 0;">
        <a href="../logout.php" class="sidebar-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="content">
        <div class="manage-tests-header">
            <div class="header-text" style="text-align:left;">
                <h1>Manage Rooms</h1>
                <p>Assign rooms and time slots to test types or specific tests with capacity and availability controls.</p>
            </div>
        </div>

        <div class="appointments-section" style="margin-top: 14px;">
            <div class="slots-toolbar">
                <div>
                    <h2 style="margin-bottom: 6px;">Time Slot Management</h2>
                    <p style="color:#64748b; font-size:14px;">Create slots for patient booking and monitor daily capacity.</p>
                </div>
                <div class="slots-actions-block">
                    <form action="manage_rooms.php" method="GET" class="slots-filter-form">
                        <input type="hidden" name="room_id" value="<?php echo (int)$filterRoom; ?>">
                        <input type="hidden" name="slot_id" value="<?php echo (int)$filterSlot; ?>">
                        <input type="hidden" name="filter_type" value="<?php echo htmlspecialchars($filterType); ?>">
                        <label for="slot_date" style="font-size:13px; color:#334155; font-weight:600;">View Date</label>
                        <input type="date" id="slot_date" name="slot_date" value="<?php echo htmlspecialchars($slotDateFilter); ?>" class="slots-date-input">
                        <button type="submit" class="btn-outline">Load</button>
                    </form>

                    <form action="tests-process.php" method="POST" class="predefined-slot-form">
                        <input type="hidden" name="action" value="generate_bulk_slots">
                        <label style="font-size:13px; color:#334155; font-weight:600;">Bulk Time Slot Creation</label>
                        <div class="predefined-input-row" style="flex-wrap:wrap;">
                            <input type="date" name="slot_date" value="<?php echo htmlspecialchars($slotDateFilter); ?>" class="slots-date-input" required>
                            <input type="time" name="start_time" value="09:00" class="slots-date-input" required>
                            <input type="time" name="end_time" value="16:30" class="slots-date-input" required>
                            <input type="number" name="interval_minutes" min="5" max="240" value="30" class="slots-date-input" style="min-width:120px;" required>
                            <input type="number" name="default_capacity" min="1" max="100" value="5" class="slots-date-input" style="min-width:120px;" required>
                            <select name="status" class="slots-date-input" style="min-width:130px;">
                                <option value="Available">Available</option>
                                <option value="Unavailable">Unavailable</option>
                                <option value="Closed">Closed</option>
                            </select>
                            <button type="button" class="btn-outline" onclick="openAppointmentSlotModal()">+ Create Time Slot</button>
                            <button type="submit" class="btn-primary">Create Bulk Slots</button>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (!empty($appointmentSlots)): ?>
                <div class="slots-table-wrap">
                    <table class="appointments-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Capacity</th>
                                <th>Booked</th>
                                <th>Available</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointmentSlots as $slot): ?>
                                <?php $available = max(0, (int)$slot['max_capacity'] - (int)$slot['booked_count']); ?>
                                <tr>
                                    <td><?php echo date('h:i A', strtotime($slot['slot_time'])); ?></td>
                                    <td><?php echo (int)$slot['max_capacity']; ?></td>
                                    <td><?php echo (int)$slot['booked_count']; ?></td>
                                    <td><?php echo $available; ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower($slot['status']); ?>">
                                            <?php echo htmlspecialchars($slot['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="tests-process.php?action=delete_slot&id=<?php echo (int)$slot['slot_id']; ?>&slot_date=<?php echo urlencode($slotDateFilter); ?>" class="btn-danger" style="padding:6px 10px; font-size:12px;" onclick="return confirm('Delete this slot?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding: 26px 20px;">
                    <p style="font-size:16px; margin-bottom:10px;">No slots configured for this date.</p>
                    <button class="btn-primary" type="button" onclick="openAppointmentSlotModal()">+ Create First Slot</button>
                </div>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="appointments-section" style="margin-bottom:16px;">
            <h2 style="margin-bottom:6px;">Quick Setup</h2>
            <p style="color:#64748b; margin-bottom:12px;">Use these 2 actions for daily operation. Advanced options are available below.</p>

            <div class="rooms-quick-grid">
                <div class="rooms-quick-card">
                    <h3>Add Room</h3>
                    <form method="POST" class="rooms-simple-form">
                        <input type="hidden" name="action" value="add_room">
                        <input type="text" name="room_number" class="form-input" placeholder="Room 101" required>
                        <input type="text" name="department" class="form-input" placeholder="Department (optional)">
                        <button type="submit" class="btn-primary">Add Room</button>
                    </form>
                </div>

                <div class="rooms-quick-card">
                    <h3>Apply Whole Day By Type</h3>
                    <form method="POST" class="rooms-simple-form">
                        <input type="hidden" name="action" value="bulk_assign_room">
                        <input type="hidden" name="map_scope" value="type">
                        <input type="hidden" name="status" value="Active">
                        <input type="hidden" name="color_code" value="#dbeafe">

                        <select name="room_id" class="form-input" required>
                            <option value="">Select Room</option>
                            <?php foreach ($allRooms as $room): ?>
                                <option value="<?php echo (int)$room['room_id']; ?>"><?php echo htmlspecialchars($room['room_number']); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <select name="mapped_test_type" class="form-input" required>
                            <option value="">Select Test Type</option>
                            <?php foreach ($testTypes as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <input type="number" name="capacity" class="form-input" min="1" value="10" required>
                        <button type="submit" class="btn-primary">Apply Whole Day</button>
                    </form>
                </div>
            </div>
        </div>

        <details class="appointments-section rooms-advanced-panel" style="margin-bottom:16px;">
            <summary>Advanced Controls</summary>

            <div class="rooms-admin-grid-two" style="margin-top:12px;">
                <div>
                    <h3 style="margin-bottom:10px;">Filters</h3>
                    <form method="GET" class="rooms-filter-grid">
                        <select name="room_id" class="form-input">
                            <option value="">All Rooms</option>
                            <?php foreach ($allRooms as $room): ?>
                                <option value="<?php echo (int)$room['room_id']; ?>" <?php echo $filterRoom === (int)$room['room_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($room['room_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="slot_id" class="form-input">
                            <option value="">All Time Slots</option>
                            <?php foreach ($allSlots as $slot): ?>
                                <option value="<?php echo (int)$slot['slot_id']; ?>" <?php echo $filterSlot === (int)$slot['slot_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($slot['slot_label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="filter_type" class="form-input" placeholder="Filter by test type" value="<?php echo htmlspecialchars($filterType); ?>">
                        <div style="display:flex; gap:10px;">
                            <button type="submit" class="btn-primary">Apply</button>
                            <a href="manage_rooms.php" class="btn-outline" style="text-decoration:none;">Reset</a>
                        </div>
                    </form>
                </div>

                <div>
                    <h3 style="margin-bottom:10px;">Add Custom Time Slot</h3>
                    <form method="POST" class="rooms-simple-form">
                        <input type="hidden" name="action" value="add_slot">
                        <input type="text" name="slot_label" class="form-input" placeholder="09:00-10:00" required>
                        <input type="time" name="start_time" class="form-input" required>
                        <input type="time" name="end_time" class="form-input" required>
                        <button type="submit" class="btn-outline">Add Slot</button>
                    </form>
                </div>
            </div>

            <div style="margin-top:14px;">
                <h3 style="margin-bottom:10px;">Advanced Bulk Mapping</h3>
                <form method="POST" class="rooms-bulk-form">
                    <input type="hidden" name="action" value="bulk_assign_room">
                    <select name="room_id" class="form-input" required>
                        <option value="">Select Room</option>
                        <?php foreach ($allRooms as $room): ?>
                            <option value="<?php echo (int)$room['room_id']; ?>"><?php echo htmlspecialchars($room['room_number']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="map_scope" class="form-input" id="bulkMapScope">
                        <option value="type">By Test Type</option>
                        <option value="test">By Specific Test</option>
                    </select>
                    <select name="mapped_test_type" id="bulkTypeSelect" class="form-input">
                        <option value="">Select Test Type</option>
                        <?php foreach ($testTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="mapped_test_id" id="bulkTestSelect" class="form-input" style="display:none;">
                        <option value="">Select Specific Test</option>
                        <?php foreach ($tests as $test): ?>
                            <option value="<?php echo (int)$test['test_id']; ?>"><?php echo htmlspecialchars($test['test_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="capacity" class="form-input" min="1" value="10" required>
                    <select name="status" class="form-input">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                    <input type="color" name="color_code" class="form-input" value="#dbeafe" style="padding:6px;">
                    <button type="submit" class="btn-primary">Apply Advanced Mapping</button>
                </form>
            </div>
        </details>

        <div class="appointments-section" style="margin-top:16px;">
            <h2 style="margin-bottom:12px;">Room Schedule Grid</h2>
            <p style="color:#64748b; margin-bottom:10px;">Rows are rooms, columns are time slots. Click any cell to view allotted patients and update mapping.</p>

            <div class="rooms-grid-wrap">
                <table class="appointments-table rooms-grid-table">
                    <thead>
                        <tr>
                            <th style="min-width:140px;">Room</th>
                            <?php foreach ($slots as $slot): ?>
                                <th><?php echo htmlspecialchars($slot['slot_label']); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $room): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($room['room_number']); ?></strong>
                                    <small style="display:block; color:#64748b;"><?php echo htmlspecialchars($room['department'] ?? 'N/A'); ?></small>
                                </td>
                                <?php foreach ($slots as $slot): ?>
                                    <?php
                                        $cellKey = $room['room_id'] . '_' . $slot['slot_id'];
                                        $assignment = $assignmentMap[$cellKey] ?? null;
                                        $mapName = 'Unassigned';
                                        $statusClass = 'status-pending';
                                        $statusText = 'Available';
                                        $bookedCount = 0;
                                        $capacity = 0;
                                        $color = '#f8fafc';

                                        if ($assignment) {
                                            $bookedCount = (int)$assignment['booked_count'];
                                            $capacity = (int)$assignment['capacity'];
                                            $color = $assignment['color_code'] ?: '#dbeafe';
                                            $mapName = $assignment['map_scope'] === 'test'
                                                ? ($assignment['test_name'] ?: 'Specific Test')
                                                : ($assignment['mapped_test_type'] ?: 'Test Type');

                                            if ($assignment['status'] !== 'Active') {
                                                $statusText = 'Inactive';
                                                $statusClass = 'status-cancelled';
                                            } elseif ($capacity > 0 && $bookedCount >= $capacity) {
                                                $statusText = 'Full';
                                                $statusClass = 'status-unavailable';
                                            } elseif ($bookedCount > 0) {
                                                $statusText = 'Booked';
                                                $statusClass = 'status-confirmed';
                                            } else {
                                                $statusText = 'Available';
                                                $statusClass = 'status-completed';
                                            }
                                        }
                                    ?>
                                    <td>
                                        <button
                                            type="button"
                                            class="room-cell-btn"
                                            style="background: <?php echo htmlspecialchars($color); ?>;"
                                            onclick="openRoomCellDetails(this)"
                                            data-room-id="<?php echo (int)$room['room_id']; ?>"
                                            data-room-name="<?php echo htmlspecialchars($room['room_number'], ENT_QUOTES); ?>"
                                            data-slot-id="<?php echo (int)$slot['slot_id']; ?>"
                                            data-slot-label="<?php echo htmlspecialchars($slot['slot_label'], ENT_QUOTES); ?>"
                                            data-cell-key="<?php echo (int)$room['room_id'] . '_' . (int)$slot['slot_id']; ?>"
                                            data-map-scope="<?php echo htmlspecialchars($assignment['map_scope'] ?? 'type', ENT_QUOTES); ?>"
                                            data-mapped-type="<?php echo htmlspecialchars($assignment['mapped_test_type'] ?? '', ENT_QUOTES); ?>"
                                            data-mapped-test-id="<?php echo (int)($assignment['mapped_test_id'] ?? 0); ?>"
                                            data-capacity="<?php echo (int)($assignment['capacity'] ?? 10); ?>"
                                            data-status="<?php echo htmlspecialchars($assignment['status'] ?? 'Active', ENT_QUOTES); ?>"
                                            data-color="<?php echo htmlspecialchars($color, ENT_QUOTES); ?>"
                                        >
                                            <strong><?php echo htmlspecialchars($mapName); ?></strong>
                                            <small><?php echo (int)$bookedCount; ?>/<?php echo max(0, (int)$capacity); ?> booked</small>
                                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($statusText); ?></span>
                                        </button>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="assignModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('assignModal')">×</button>
        <h2>Assign Room Slot</h2>
        <form method="POST">
            <input type="hidden" name="action" value="save_assignment">
            <input type="hidden" name="room_id" id="assignRoomId">
            <input type="hidden" name="slot_id" id="assignSlotId">

            <label>Room</label>
            <input type="text" id="assignRoomName" class="form-input" readonly>

            <label>Time Slot</label>
            <input type="text" id="assignSlotLabel" class="form-input" readonly>

            <input type="hidden" name="map_scope" id="assignMapScope" value="type">

            <label class="rooms-advanced-toggle">
                <input type="checkbox" id="assignAdvancedToggle">
                Use specific test mapping
            </label>
            <small class="rooms-help-text">Leave unchecked for quick test-type mapping.</small>

            <div id="assignTypeBox">
                <label>Test Type</label>
                <select name="mapped_test_type" id="assignTypeSelect" class="form-input">
                    <option value="">Select Test Type</option>
                    <?php foreach ($testTypes as $type): ?>
                        <option value="<?php echo htmlspecialchars($type); ?>"><?php echo htmlspecialchars($type); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="assignTestBox" style="display:none;">
                <label>Specific Test</label>
                <select name="mapped_test_id" id="assignTestSelect" class="form-input">
                    <option value="">Select Test</option>
                    <?php foreach ($tests as $test): ?>
                        <option value="<?php echo (int)$test['test_id']; ?>"><?php echo htmlspecialchars($test['test_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <label>Capacity</label>
            <input type="number" name="capacity" id="assignCapacity" class="form-input" min="1" value="10" required>

            <label>Availability</label>
            <select name="status" id="assignStatus" class="form-input">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>

            <label>Color Code</label>
            <input type="color" name="color_code" id="assignColor" class="form-input" value="#dbeafe" style="padding:6px;">

            <div class="modal-footer">
                <button type="button" class="btn-outline" onclick="closeModal('assignModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Assignment</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="patientsModal">
    <div class="modal-box" style="max-width: 920px; width: min(920px, 96vw);">
        <button class="modal-close" onclick="closeModal('patientsModal')">×</button>
        <h2>Allotted Patients</h2>
        <p id="patientsModalMeta" style="margin: 0 0 12px 0; color:#64748b;">Room and slot details</p>

        <div style="max-height: 56vh; overflow:auto;">
            <table class="appointments-table">
                <thead>
                    <tr>
                        <th>Patient ID</th>
                        <th>Patient Name</th>
                        <th>Test Type</th>
                        <th>Test</th>
                        <th>Ref</th>
                        <th>Doctor Ref</th>
                        <th>Appointment Date</th>
                    </tr>
                </thead>
                <tbody id="patientsModalBody">
                </tbody>
            </table>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn-outline" onclick="openAssignFromPatientsModal()">Edit Assignment</button>
            <button type="button" class="btn-primary" onclick="closeModal('patientsModal')">Close</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="appointmentSlotModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('appointmentSlotModal')">×</button>
        <h2>Create Time Slot</h2>
        <form action="tests-process.php" method="POST">
            <input type="hidden" name="action" value="add_slot">

            <label>Slot Date</label>
            <input type="date" name="slot_date" value="<?php echo htmlspecialchars($slotDateFilter); ?>" min="<?php echo date('Y-m-d'); ?>" required>

            <label>Slot Time</label>
            <input type="time" name="slot_time" required>

            <label>Max Capacity</label>
            <input type="number" name="max_capacity" min="1" max="100" value="5" required>

            <label>Status</label>
            <select name="status" required>
                <option value="Available">Available</option>
                <option value="Unavailable">Unavailable</option>
                <option value="Closed">Closed</option>
            </select>

            <div class="modal-footer">
                <button type="button" class="btn-outline" onclick="closeModal('appointmentSlotModal')">Cancel</button>
                <button type="submit" class="btn-primary">Create Slot</button>
            </div>
        </form>
    </div>
</div>

<script>
const roomCellPatients = <?php echo json_encode($patientsByCell, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
let lastOpenedRoomCellButton = null;

function openAppointmentSlotModal() {
    document.getElementById('appointmentSlotModal').classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function updateAssignModeFromToggle() {
    const toggle = document.getElementById('assignAdvancedToggle');
    const mapScope = document.getElementById('assignMapScope');
    const typeBox = document.getElementById('assignTypeBox');
    const testBox = document.getElementById('assignTestBox');

    if (!toggle || !mapScope || !typeBox || !testBox) {
        return;
    }

    if (toggle.checked) {
        mapScope.value = 'test';
        typeBox.style.display = 'none';
        testBox.style.display = 'block';
    } else {
        mapScope.value = 'type';
        typeBox.style.display = 'block';
        testBox.style.display = 'none';
    }
}

function openAssignModal(button) {
    document.getElementById('assignRoomId').value = button.dataset.roomId || '';
    document.getElementById('assignSlotId').value = button.dataset.slotId || '';
    document.getElementById('assignRoomName').value = button.dataset.roomName || '';
    document.getElementById('assignSlotLabel').value = button.dataset.slotLabel || '';
    const mapScope = button.dataset.mapScope || 'type';
    document.getElementById('assignMapScope').value = mapScope;
    document.getElementById('assignTypeSelect').value = button.dataset.mappedType || '';
    document.getElementById('assignTestSelect').value = button.dataset.mappedTestId || '';
    document.getElementById('assignCapacity').value = button.dataset.capacity || '10';
    document.getElementById('assignStatus').value = button.dataset.status || 'Active';
    document.getElementById('assignColor').value = button.dataset.color || '#dbeafe';

    const assignAdvancedToggle = document.getElementById('assignAdvancedToggle');
    if (assignAdvancedToggle) {
        assignAdvancedToggle.checked = mapScope === 'test';
    }
    updateAssignModeFromToggle();
    document.getElementById('assignModal').classList.add('active');
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function openRoomCellDetails(button) {
    lastOpenedRoomCellButton = button;
    const roomName = button.dataset.roomName || '';
    const slotLabel = button.dataset.slotLabel || '';
    const cellKey = button.dataset.cellKey || '';
    const rows = roomCellPatients[cellKey] || [];

    const meta = document.getElementById('patientsModalMeta');
    const body = document.getElementById('patientsModalBody');
    if (!meta || !body) {
        return;
    }

    meta.textContent = `Room: ${roomName} | Slot: ${slotLabel}`;

    if (rows.length === 0) {
        body.innerHTML = '<tr><td colspan="7" style="text-align:center; color:#64748b;">No allotted patients for this room and slot.</td></tr>';
    } else {
        body.innerHTML = rows.map(function(row) {
            return `<tr>
                <td>#${escapeHtml(row.patient_id)}</td>
                <td>${escapeHtml(row.patient_name)}</td>
                <td>${escapeHtml(row.test_type)}</td>
                <td>${escapeHtml(row.test_name)}</td>
                <td>${escapeHtml(row.reference)}</td>
                <td>${escapeHtml(row.doctor_ref)}</td>
                <td>${escapeHtml(row.appointment_date)}</td>
            </tr>`;
        }).join('');
    }

    document.getElementById('patientsModal').classList.add('active');
}

function openAssignFromPatientsModal() {
    if (!lastOpenedRoomCellButton) {
        return;
    }
    closeModal('patientsModal');
    openAssignModal(lastOpenedRoomCellButton);
}

function updateBulkMode() {
    const bulkMapScope = document.getElementById('bulkMapScope');
    const bulkTypeSelect = document.getElementById('bulkTypeSelect');
    const bulkTestSelect = document.getElementById('bulkTestSelect');
    if (!bulkMapScope || !bulkTypeSelect || !bulkTestSelect) {
        return;
    }

    const isTest = bulkMapScope.value === 'test';
    bulkTypeSelect.style.display = isTest ? 'none' : 'block';
    bulkTestSelect.style.display = isTest ? 'block' : 'none';
}

const assignAdvancedToggle = document.getElementById('assignAdvancedToggle');
if (assignAdvancedToggle) {
    assignAdvancedToggle.addEventListener('change', updateAssignModeFromToggle);
}

const bulkMapScope = document.getElementById('bulkMapScope');
if (bulkMapScope) {
    bulkMapScope.addEventListener('change', updateBulkMode);
    updateBulkMode();
}

updateAssignModeFromToggle();

document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            overlay.classList.remove('active');
        }
    });
});
</script>
</body>
</html>
