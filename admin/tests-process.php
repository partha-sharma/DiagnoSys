<?php
require_once '../config/init.php';

// Gatekeeper: Only admins can do this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access Denied.");
}

// Handle ADD action (from the form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $test_name = trim($_POST['test_name']);
    $description = trim($_POST['description']);
    $price = $_POST['price'];

    $stmt = $conn->prepare("INSERT INTO tests (test_name, description, price) VALUES (?, ?, ?)");
    $stmt->bind_param("ssd", $test_name, $description, $price);
    $stmt->execute();
    $stmt->close();
}

// Handle DELETE action (from the link)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $test_id = intval($_GET['id']);

    $stmt = $conn->prepare("DELETE FROM tests WHERE test_id = ?");
    $stmt->bind_param("i", $test_id);
    $stmt->execute();
    $stmt->close();
}

// Redirect back to the management page after action
header("Location: manage_tests.php");
exit();
?>