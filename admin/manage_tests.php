<?php
require_once '../config/init.php';

// Gatekeeper: Only admins can see this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Fetch all tests from the database
$result = $conn->query("SELECT * FROM tests ORDER BY test_id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Tests - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="admin-layout">
    <!-- Left Sidebar -->
    <div class="sidebar">
        <h2 style="margin-bottom: 30px;">&#128105;&#8205;&#9877;&#65039; Admin Panel</h2>
        <a href="index.php">Dashboard</a>
        <a href="manage_tests.php" class="active">Manage Tests</a>
        <a href="appointments.php">Appointments</a>
        <a href="users.php">Patients List</a>
        <hr style="border-color: #334155; margin: 20px 0;">
        <a href="../logout.php" style="color: #fca5a5;">Logout</a>
    </div>

    <!-- Right Content -->
    <div class="content">

    <!-- Page Header -->
    <div class="manage-tests-header">
        <div class="header-text">
            <h1>Manage Tests</h1>
            <p>Add, edit, or remove diagnostic tests</p>
        </div>
        <button class="btn-primary" onclick="openAddModal()">+ Add New Test</button>
    </div>

    <!-- Tests Card Grid -->
    <div class="tests-grid">
        <?php
        $hasTests = false;
        while ($test = $result->fetch_assoc()):
            $hasTests = true;
        ?>
        <div class="test-card">
            <div class="test-card-top">
                <div class="test-card-icon">🧪</div>
                <div class="test-card-actions">
                    <a href="tests-process.php?action=delete&id=<?php echo $test['test_id']; ?>"
                       class="delete-btn"
                       title="Delete"
                       onclick="return confirm('Are you sure you want to delete this test?');">
                       🗑️
                    </a>
                </div>
            </div>

            <h3><?php echo htmlspecialchars($test['test_name']); ?></h3>
            <p class="test-desc"><?php echo htmlspecialchars($test['description']); ?></p>

            <div class="test-card-footer">
                <div class="test-card-meta">
                    <span class="test-type-badge">Blood Test</span>
                    <?php if (!empty($test['duration'])): ?>
                        <span class="test-duration">Duration: <?php echo htmlspecialchars($test['duration']); ?></span>
                    <?php endif; ?>
                </div>
                <span class="test-price">৳<?php echo number_format($test['price'], 0); ?></span>
            </div>
        </div>
        <?php endwhile; ?>

        <?php if (!$hasTests): ?>
        <div class="tests-empty">
            <p>No tests found. Add your first diagnostic test!</p>
            <button class="btn-primary" onclick="openAddModal()">+ Add New Test</button>
        </div>
        <?php endif; ?>
    </div>

    </div>
</div>

<!-- Add Test Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('addModal')">×</button>
        <h2>Add New Test</h2>
        <form action="tests-process.php" method="POST">
            <input type="hidden" name="action" value="add">

            <label>Test Name</label>
            <input type="text" name="test_name" placeholder="e.g., Complete Blood Count" required>

            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Describe the test"></textarea>

            <label>Price (৳)</label>
            <input type="number" step="0.01" name="price" placeholder="e.g., 500.00" required>

            <div class="modal-footer">
                <button type="button" class="btn-outline" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn-primary">Add Test</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addModal').classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Close modal when clicking outside
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            overlay.classList.remove('active');
        }
    });
});
</script>

</body>
</html>