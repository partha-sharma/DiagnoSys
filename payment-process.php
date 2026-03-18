<?php
require_once 'config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$appointment_id = (int)($_POST['appointment_id'] ?? 0);
$payment_method = trim($_POST['payment_method'] ?? '');
$transaction_id = trim($_POST['transaction_id'] ?? '');

if ($appointment_id <= 0 || $payment_method === '') {
    $_SESSION['error'] = 'Invalid payment request.';
    header('Location: payment.php?appointment_id=' . max(0, $appointment_id));
    exit();
}

if (!in_array($payment_method, ['Cash', 'bKash', 'Nagad', 'Card'], true)) {
    $_SESSION['error'] = 'Unsupported payment method.';
    header('Location: payment.php?appointment_id=' . $appointment_id);
    exit();
}

if ($payment_method !== 'Cash' && $transaction_id === '') {
    $_SESSION['error'] = 'Transaction ID is required for this payment method.';
    header('Location: payment.php?appointment_id=' . $appointment_id);
    exit();
}

$check = $conn->prepare("SELECT appointment_id, status, total_amount FROM appointments WHERE appointment_id = ? AND user_id = ? LIMIT 1");
$check->bind_param('ii', $appointment_id, $user_id);
$check->execute();
$appointment = $check->get_result()->fetch_assoc();
$check->close();

if (!$appointment) {
    $_SESSION['error'] = 'Appointment not found.';
    header('Location: dashboard.php');
    exit();
}

if ($appointment['status'] === 'Cancelled') {
    $_SESSION['error'] = 'Cancelled appointments cannot be paid.';
    header('Location: payments.php');
    exit();
}

$amount = (float)$appointment['total_amount'];
if ($amount <= 0) {
    $sumStmt = $conn->prepare("SELECT COALESCE(SUM(t.price),0) AS total FROM appointment_tests apt JOIN tests t ON apt.test_id = t.test_id WHERE apt.appointment_id = ?");
    $sumStmt->bind_param('i', $appointment_id);
    $sumStmt->execute();
    $amount = (float)$sumStmt->get_result()->fetch_assoc()['total'];
    $sumStmt->close();
}

if ($amount <= 0) {
    $_SESSION['error'] = 'Unable to process payment amount for this appointment.';
    header('Location: payment.php?appointment_id=' . $appointment_id);
    exit();
}

$existingPayment = $conn->prepare("SELECT payment_id FROM payments WHERE appointment_id = ? AND user_id = ? AND status = 'Completed' LIMIT 1");
$existingPayment->bind_param('ii', $appointment_id, $user_id);
$existingPayment->execute();
$completedPayment = $existingPayment->get_result()->fetch_assoc();
$existingPayment->close();

if ($completedPayment) {
    $_SESSION['success'] = 'This appointment is already paid.';
    header('Location: invoice.php?appointment_id=' . $appointment_id);
    exit();
}

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("INSERT INTO payments (appointment_id, user_id, amount, status, payment_method, transaction_id, payment_date) VALUES (?, ?, ?, 'Completed', ?, ?, NOW())");
    $stmt->bind_param('iidss', $appointment_id, $user_id, $amount, $payment_method, $transaction_id);
    $stmt->execute();
    $stmt->close();

    $update = $conn->prepare("UPDATE appointments SET status = CASE WHEN status = 'Pending' THEN 'Confirmed' ELSE status END WHERE appointment_id = ?");
    $update->bind_param('i', $appointment_id);
    $update->execute();
    $update->close();

    $history = $conn->prepare("INSERT INTO appointment_history (appointment_id, status) VALUES (?, ?)");
    $historyStatus = 'Payment Completed via ' . $payment_method;
    $history->bind_param('is', $appointment_id, $historyStatus);
    $history->execute();
    $history->close();

    $conn->commit();

    $_SESSION['success'] = 'Payment successful.';
    header('Location: invoice.php?appointment_id=' . $appointment_id);
    exit();
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = 'Payment failed. Please try again.';
    header('Location: payment.php?appointment_id=' . $appointment_id);
    exit();
}
