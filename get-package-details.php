<?php
require_once 'config/init.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$packageId = (int)($_GET['package_id'] ?? 0);
if ($packageId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Package ID is required']);
    exit();
}

$packageStmt = $conn->prepare('SELECT package_id, name, description, base_price, discount_percent, final_price, status FROM packages WHERE package_id = ? AND status = "Active" LIMIT 1');
$packageStmt->bind_param('i', $packageId);
$packageStmt->execute();
$packageResult = $packageStmt->get_result();

if ($packageResult->num_rows === 0) {
    $packageStmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Package not found']);
    exit();
}

$package = $packageResult->fetch_assoc();
$packageStmt->close();

$testStmt = $conn->prepare('SELECT pt.test_id, pt.package_test_price, t.test_name, t.price AS master_price FROM package_tests pt INNER JOIN tests t ON t.test_id = pt.test_id WHERE pt.package_id = ? ORDER BY t.test_name ASC');
$testStmt->bind_param('i', $packageId);
$testStmt->execute();
$testResult = $testStmt->get_result();

$tests = [];
while ($row = $testResult->fetch_assoc()) {
    $tests[] = [
        'test_id' => (int)$row['test_id'],
        'test_name' => $row['test_name'],
        'package_test_price' => (float)$row['package_test_price'],
        'master_price' => (float)$row['master_price'],
    ];
}

$testStmt->close();
$conn->close();

echo json_encode([
    'success' => true,
    'package' => [
        'package_id' => (int)$package['package_id'],
        'name' => $package['name'],
        'description' => $package['description'],
        'base_price' => (float)$package['base_price'],
        'discount_percent' => (float)$package['discount_percent'],
        'final_price' => (float)$package['final_price'],
        'tests' => $tests,
    ],
]);