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

    // Basic validation
    if (empty($appointment_date) || empty($appointment_time)) {
        $_SESSION['error'] = "Please select both a date and time.";
        header("Location: book-appointment.php");
        exit();
    }

    // Combine date and time into a single datetime string
    $appointment_datetime = $appointment_date . ' ' . $appointment_time . ':00';

    // Insert into the appointments table
    $stmt = $conn->prepare("INSERT INTO appointments (user_id, appointment_date) VALUES (?, ?)");
    $stmt->bind_param("is", $user_id, $appointment_datetime);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Your appointment has been booked successfully! It is now pending confirmation.";
    } else {
        $_SESSION['error'] = "There was a problem booking your appointment. Please try again.";
    }
    
    $stmt->close();
    header("Location: dashboard.php");
    exit();
}
?>