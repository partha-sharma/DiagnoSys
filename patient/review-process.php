<?php
require_once __DIR__ . '/../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: reviews.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$appointment_id = (int)($_POST['appointment_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if (strlen($comment) > 1000) {
    $_SESSION['error'] = 'Comment is too long. Maximum 1000 characters allowed.';
    header('Location: reviews.php');
    exit();
}

if ($appointment_id <= 0 || $rating < 1 || $rating > 5) {
    $_SESSION['error'] = 'Invalid review input.';
    header('Location: reviews.php');
    exit();
}

$check = $conn->prepare("SELECT appointment_id FROM appointments WHERE appointment_id = ? AND user_id = ? AND status = 'Completed' LIMIT 1");
$check->bind_param('ii', $appointment_id, $user_id);
$check->execute();
$exists = $check->get_result()->num_rows > 0;
$check->close();

if (!$exists) {
    $_SESSION['error'] = 'Review allowed only for your completed appointments.';
    header('Location: reviews.php');
    exit();
}

$find = $conn->prepare("SELECT review_id FROM reviews WHERE appointment_id = ? AND user_id = ? LIMIT 1");
$find->bind_param('ii', $appointment_id, $user_id);
$find->execute();
$review = $find->get_result()->fetch_assoc();
$find->close();

if ($review) {
    $update = $conn->prepare("UPDATE reviews SET rating = ?, comment = ? WHERE review_id = ?");
    $update->bind_param('isi', $rating, $comment, $review['review_id']);
    $update->execute();
    $update->close();
    $_SESSION['success'] = 'Review updated successfully.';
} else {
    $insert = $conn->prepare("INSERT INTO reviews (appointment_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
    $insert->bind_param('iiis', $appointment_id, $user_id, $rating, $comment);
    $insert->execute();
    $insert->close();
    $_SESSION['success'] = 'Review submitted successfully.';
}

$conn->close();
header('Location: reviews.php');
exit();


