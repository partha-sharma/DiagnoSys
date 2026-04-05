<?php
require_once 'config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$date = trim($_GET['date'] ?? '');
if ($date === '') {
    echo json_encode(['success' => false, 'message' => 'Date is required']);
    exit();
}

$stmt = $conn->prepare("SELECT slot_time, max_capacity, booked_count, status FROM appointment_slots WHERE slot_date = ? ORDER BY slot_time ASC");
$stmt->bind_param('s', $date);
$stmt->execute();
$result = $stmt->get_result();

$slots = [];
while ($row = $result->fetch_assoc()) {
    $available = ((int)$row['max_capacity'] - (int)$row['booked_count']);
    $slots[] = [
        'time' => substr($row['slot_time'], 0, 5),
        'status' => $row['status'],
        'available' => max(0, $available)
    ];
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'slots' => $slots]);
