<?php
require_once 'config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$focus_appointment_id = (int)($_GET['appointment_id'] ?? 0);

$sql = "SELECT a.appointment_id, a.appointment_date, a.status,
               GROUP_CONCAT(t.test_name SEPARATOR ', ') AS test_names,
               r.review_id, r.rating, r.comment, r.created_at
        FROM appointments a
        LEFT JOIN appointment_tests apt ON a.appointment_id = apt.appointment_id
        LEFT JOIN tests t ON apt.test_id = t.test_id
        LEFT JOIN reviews r ON a.appointment_id = r.appointment_id AND r.user_id = a.user_id
        WHERE a.user_id = ? AND a.status = 'Completed'
        GROUP BY a.appointment_id
        ORDER BY (a.appointment_id = ?) DESC, a.appointment_date DESC";

$stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $user_id, $focus_appointment_id);
$stmt->execute();
$rows = $stmt->get_result();
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews - DiagnoLab</title>
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
            <h1>Review & Rating</h1>
            <p>Rate completed appointments and share your feedback.</p>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="appointments-section">
        <?php if ($rows && $rows->num_rows > 0): ?>
            <table class="appointments-table">
                <thead>
                    <tr>
                        <th>Appointment</th>
                        <th>Date</th>
                        <th>Tests</th>
                        <th>Your Review</th>
                    </tr>
                </thead>
                <tbody>
                <?php while ($row = $rows->fetch_assoc()): ?>
                    <tr>
                        <td>#<?php echo (int)$row['appointment_id']; ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['appointment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['test_names'] ?? 'N/A'); ?></td>
                        <td>
                            <form action="review-process.php" method="POST">
                                <input type="hidden" name="appointment_id" value="<?php echo (int)$row['appointment_id']; ?>">
                                <select name="rating" class="form-input" required>
                                    <option value="">Rating</option>
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <option value="<?php echo $i; ?>" <?php echo ((int)($row['rating'] ?? 0) === $i) ? 'selected' : ''; ?>><?php echo $i; ?> Star</option>
                                    <?php endfor; ?>
                                </select>
                                <textarea name="comment" class="form-input" placeholder="Write feedback..." style="min-height:70px;"><?php echo htmlspecialchars($row['comment'] ?? ''); ?></textarea>
                                <button type="submit" class="btn-primary" style="padding:6px 12px; margin-top:8px;">
                                    <?php echo !empty($row['review_id']) ? 'Update Review' : 'Submit Review'; ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p>No completed appointments available for review yet.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
