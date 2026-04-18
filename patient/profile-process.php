<?php
require_once __DIR__ . '/../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: ../auth/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: profile.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = trim($_POST['full_name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$address = trim($_POST['address'] ?? '');
$remove_photo = isset($_POST['remove_photo']) && $_POST['remove_photo'] === '1';

if ($full_name === '' || $phone === '' || $address === '') {
    $_SESSION['error'] = 'All profile fields are required.';
    header('Location: profile.php');
    exit();
}

$stmt = $conn->prepare("SELECT profile_photo FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$currentUser = $result->fetch_assoc();
$stmt->close();

$currentPhoto = $currentUser['profile_photo'] ?? null;
$newPhotoPath = $currentPhoto;

if ($remove_photo) {
    if (!empty($currentPhoto)) {
        $absPath = __DIR__ . '/' . ltrim($currentPhoto, '/');
        if (is_file($absPath)) {
            unlink($absPath);
        }
    }
    $newPhotoPath = null;
}

if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['error'] = 'Failed to upload image.';
        header('Location: profile.php');
        exit();
    }

    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $maxSize = 2 * 1024 * 1024;

    $mime = mime_content_type($_FILES['profile_photo']['tmp_name']);
    if (!isset($allowedTypes[$mime])) {
        $_SESSION['error'] = 'Invalid image format. Use JPG, PNG, or WEBP.';
        header('Location: profile.php');
        exit();
    }

    if ($_FILES['profile_photo']['size'] > $maxSize) {
        $_SESSION['error'] = 'Image is too large. Maximum size is 2MB.';
        header('Location: profile.php');
        exit();
    }

    $uploadDirRel = 'uploads/profile';
    $uploadDirAbs = __DIR__ . '/' . $uploadDirRel;
    if (!is_dir($uploadDirAbs)) {
        mkdir($uploadDirAbs, 0777, true);
    }

    $extension = $allowedTypes[$mime];
    $fileName = 'user_' . $user_id . '_' . time() . '.' . $extension;
    $targetRel = $uploadDirRel . '/' . $fileName;
    $targetAbs = __DIR__ . '/' . $targetRel;

    if (!move_uploaded_file($_FILES['profile_photo']['tmp_name'], $targetAbs)) {
        $_SESSION['error'] = 'Could not save uploaded image.';
        header('Location: profile.php');
        exit();
    }

    if (!empty($currentPhoto)) {
        $oldAbsPath = __DIR__ . '/' . ltrim($currentPhoto, '/');
        if (is_file($oldAbsPath)) {
            unlink($oldAbsPath);
        }
    }

    $newPhotoPath = $targetRel;
}

$update = $conn->prepare("UPDATE users SET full_name = ?, phone = ?, address = ?, profile_photo = ? WHERE user_id = ?");
$update->bind_param('ssssi', $full_name, $phone, $address, $newPhotoPath, $user_id);
$update->execute();
$update->close();

$_SESSION['user_name'] = $full_name;
$_SESSION['success'] = 'Profile updated successfully.';

$conn->close();
header('Location: profile.php');
exit();


