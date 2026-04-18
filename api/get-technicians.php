<?php
require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json');

$result = $conn->query("SELECT technician_id, name, specialization FROM technicians WHERE status = 'Active' ORDER BY name ASC");
$technicians = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $technicians[] = $row;
    }
}

$conn->close();
echo json_encode(['success' => true, 'technicians' => $technicians]);
