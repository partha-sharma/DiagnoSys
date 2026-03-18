<?php
require_once 'config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT user_id, full_name, email, phone, address, profile_photo, email_verified, created_at FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['error'] = 'Profile not found.';
    header('Location: dashboard.php');
    exit();
}

$defaultAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name']) . '&background=0ea5e9&color=ffffff&size=200';
$profilePath = !empty($user['profile_photo']) ? '/DiagnoSys/' . ltrim($user['profile_photo'], '/') : $defaultAvatar;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - DiagnoLab</title>
    <link rel="stylesheet" href="/DiagnoSys/assets/css/style.css">
</head>
<body>
    <div class="navbar">
        <a href="dashboard.php" class="logo">DiagnoLab</a>
        <div class="nav-buttons">
            <a href="dashboard.php" class="btn-outline">Dashboard</a>
            <a href="logout.php" class="btn-primary">Logout</a>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <div>
                <h1>My Profile</h1>
                <p>Update your personal details and profile photo</p>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div class="profile-layout">
            <div class="profile-photo-card">
                <img src="<?php echo htmlspecialchars($profilePath); ?>" alt="Profile Photo" class="profile-photo-preview">
                <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                <p><?php echo htmlspecialchars($user['email']); ?></p>
                <span class="status-badge <?php echo (int)$user['email_verified'] === 1 ? 'status-completed' : 'status-pending'; ?>">
                    <?php echo (int)$user['email_verified'] === 1 ? 'Email Verified' : 'Email Not Verified'; ?>
                </span>
                <p class="profile-meta">Joined: <?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
            </div>

            <div class="appointments-section">
                <h2>Edit Profile</h2>
                <form action="profile-process.php" method="POST" enctype="multipart/form-data" class="profile-form">
                    <label>Full Name</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>

                    <label>Email (Read Only)</label>
                    <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>

                    <label>Phone</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>

                    <label>Address</label>
                    <textarea name="address" rows="3" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>

                    <label>Upload New Profile Photo (JPG, PNG, WEBP - max 2MB)</label>
                    <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp">

                    <?php if (!empty($user['profile_photo'])): ?>
                        <label class="inline-check">
                            <input type="checkbox" name="remove_photo" value="1"> Remove current photo
                        </label>
                    <?php endif; ?>

                    <button type="submit" class="btn-primary">Save Profile</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>
