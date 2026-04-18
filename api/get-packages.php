<?php
require_once __DIR__ . '/../config/init.php';

header('Content-Type: application/json');

$result = $conn->query("SELECT package_id, name, description, final_price FROM packages WHERE status = 'Active' ORDER BY name ASC");
$packages = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $packages[] = $row;
    }
}

$conn->close();
echo json_encode(['success' => true, 'packages' => $packages]);
