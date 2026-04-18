<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$categoryOptions = test_category_options();
$sampleOptions = sample_requirement_options();

$filterCategory = trim((string)($_GET['filter_category'] ?? ''));
if ($filterCategory === '' || !in_array($filterCategory, $categoryOptions, true)) {
    $filterCategory = '';
}
$filterSample = trim((string)($_GET['filter_sample'] ?? ''));
if ($filterSample === '' || !in_array($filterSample, $sampleOptions, true)) {
    $filterSample = '';
}
$search = trim($_GET['search'] ?? '');

$where = [];
$types = '';
$params = [];

if ($filterCategory !== '') {
    $where[] = 'test_category = ?';
    $types .= 's';
    $params[] = $filterCategory;
}
if ($filterSample !== '') {
    $where[] = 'sample_requirement = ?';
    $types .= 's';
    $params[] = $filterSample;
}
if ($search !== '') {
    $where[] = '(test_name LIKE ? OR COALESCE(description, "") LIKE ?)';
    $types .= 'ss';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$sql = 'SELECT test_id, test_name, COALESCE(description, "") AS description, price, COALESCE(status, "Active") AS status, COALESCE(test_category, "General") AS test_category, COALESCE(sample_requirement, "None") AS sample_requirement FROM tests';
if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY FIELD(test_category, "Laboratory", "Cardiology", "Imaging", "General"), test_name ASC';

$stmt = $conn->prepare($sql);
if ($stmt && $types !== '') {
    $stmt->bind_param($types, ...$params);
}

$tests = [];
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['test_category'] = normalize_test_category((string)$row['test_category']);
        $row['sample_requirement'] = normalize_sample_requirement((string)$row['sample_requirement']);
        $tests[] = $row;
    }
    $stmt->close();
}

$groupedTests = [];
foreach ($tests as $test) {
    $groupedTests[$test['test_category']][] = $test;
}

$testCounts = [
    'total' => count($tests),
    'Laboratory' => count($groupedTests['Laboratory'] ?? []),
    'Cardiology' => count($groupedTests['Cardiology'] ?? []),
    'Imaging' => count($groupedTests['Imaging'] ?? []),
];

$activeFilterParts = [];
if ($filterCategory !== '') {
    $activeFilterParts[] = 'Type: ' . $filterCategory;
}
if ($filterSample !== '') {
    $activeFilterParts[] = 'Sample: ' . sample_requirement_display_label($filterSample);
}
if ($search !== '') {
    $activeFilterParts[] = 'Search: ' . $search;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Tests - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="admin-layout">
    <div class="sidebar">
        <h2 style="margin-bottom: 30px;">Admin Panel</h2>
        <a href="index.php">Dashboard</a>
        <a href="manage_tests.php" class="active">Manage Tests</a>
        <a href="appointments.php">Appointments</a>
        <a href="technicians.php">Technicians</a>
        <a href="packages.php">Packages</a>
        <a href="manage_rooms.php">Manage Rooms</a>
        <a href="finance.php">Finance</a>
        <a href="users.php">Patients List</a>
        <hr style="border-color: #334155; margin: 20px 0;">
        <a href="../auth/logout.php" class="sidebar-logout"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>

    <div class="content">
        <div class="manage-tests-header">
            <div class="header-text">
                <h1>Manage Tests</h1>
                <p>Each diagnostic test is classified by Type and Sample Requirement.</p>
            </div>
            <div class="header-actions">
                <button class="btn-primary" onclick="openAddModal()">+ Add New Test</button>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <div style="display:grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap:16px; margin-bottom:18px;">
            <div class="appointments-section" style="margin:0; padding:16px;">
                <div style="color:#64748b; font-size:13px;">Total Tests</div>
                <div style="font-size:30px; font-weight:700; color:#0f172a; margin-top:6px;"><?php echo (int)$testCounts['total']; ?></div>
            </div>
            <div class="appointments-section" style="margin:0; padding:16px;">
                <div style="color:#64748b; font-size:13px;">Laboratory</div>
                <div style="font-size:30px; font-weight:700; color:#0369a1; margin-top:6px;"><?php echo (int)$testCounts['Laboratory']; ?></div>
            </div>
            <div class="appointments-section" style="margin:0; padding:16px;">
                <div style="color:#64748b; font-size:13px;">Cardiology</div>
                <div style="font-size:30px; font-weight:700; color:#be185d; margin-top:6px;"><?php echo (int)$testCounts['Cardiology']; ?></div>
            </div>
            <div class="appointments-section" style="margin:0; padding:16px;">
                <div style="color:#64748b; font-size:13px;">Imaging</div>
                <div style="font-size:30px; font-weight:700; color:#7c3aed; margin-top:6px;"><?php echo (int)$testCounts['Imaging']; ?></div>
            </div>
        </div>

        <?php if (!empty($activeFilterParts)): ?>
            <div class="appointments-section" style="margin-bottom:14px; padding:12px 16px; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap;">
                <div style="color:#334155;">
                    <strong>Active filters:</strong> <?php echo htmlspecialchars(implode(' | ', $activeFilterParts)); ?>
                </div>
                <a href="manage_tests.php" class="btn-outline" style="text-decoration:none;">Clear filters</a>
            </div>
        <?php endif; ?>

        <form method="GET" class="appointments-section" style="padding:12px; margin-bottom:14px;">
            <div style="display:grid; grid-template-columns: repeat(4, minmax(140px, 1fr)); gap:10px; align-items:end;">
                <div>
                    <label style="font-size:12px; color:#475569; font-weight:600;">Search</label>
                    <input type="text" class="form-input" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name or description">
                </div>
                <div>
                    <label style="font-size:12px; color:#475569; font-weight:600;">Type</label>
                    <select class="form-input" name="filter_category">
                        <option value="">All Types</option>
                        <?php foreach ($categoryOptions as $option): ?>
                            <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $filterCategory === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="font-size:12px; color:#475569; font-weight:600;">Sample Requirement</label>
                    <select class="form-input" name="filter_sample">
                        <option value="">All Sample Requirements</option>
                        <?php foreach ($sampleOptions as $option): ?>
                            <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $filterSample === $option ? 'selected' : ''; ?>><?php echo htmlspecialchars(sample_requirement_display_label($option)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex; gap:8px;">
                    <button type="submit" class="btn-primary">Apply</button>
                    <a href="manage_tests.php" class="btn-outline" style="text-decoration:none;">Reset</a>
                </div>
            </div>
        </form>

        <?php if (empty($tests)): ?>
            <div class="tests-empty">
                <p>No tests found for the current filter.</p>
                <p style="color:#64748b; max-width:640px; margin:8px auto 0;">Try clearing the sample requirement filter. The seeded catalog includes Laboratory, Cardiology, and Imaging tests, but not every sample type exists for every category.</p>
                <button class="btn-primary" onclick="openAddModal()">+ Add New Test</button>
            </div>
        <?php else: ?>
            <?php foreach ($categoryOptions as $category): ?>
                <?php if (empty($groupedTests[$category])) continue; ?>
                <div style="margin-bottom:8px; margin-top:14px;">
                    <h2 style="font-size:20px; color:#0f172a; margin:0;"><?php echo htmlspecialchars($category); ?></h2>
                </div>
                <div class="tests-grid" style="margin-bottom:20px;">
                    <?php foreach ($groupedTests[$category] as $test): ?>
                        <div class="test-card">
                            <div class="test-card-top">
                                <div class="test-card-icon"><i class="fa-solid fa-flask" style="font-size: 28px;"></i></div>
                                <div class="test-card-actions">
                                    <button type="button"
                                            class="edit-btn"
                                            title="Edit"
                                            onclick="openEditModal(this)"
                                            data-test-id="<?php echo (int)$test['test_id']; ?>"
                                            data-test-name="<?php echo htmlspecialchars($test['test_name'], ENT_QUOTES); ?>"
                                            data-test-description="<?php echo htmlspecialchars($test['description'], ENT_QUOTES); ?>"
                                            data-test-price="<?php echo htmlspecialchars((string)$test['price'], ENT_QUOTES); ?>"
                                            data-test-status="<?php echo htmlspecialchars($test['status'], ENT_QUOTES); ?>"
                                            data-test-category="<?php echo htmlspecialchars($test['test_category'], ENT_QUOTES); ?>"
                                            data-test-sample="<?php echo htmlspecialchars($test['sample_requirement'], ENT_QUOTES); ?>">
                                        <i class="fa-solid fa-pen-to-square" style="font-size: 16px;"></i>
                                    </button>
                                    <a href="tests-process.php?action=delete&id=<?php echo (int)$test['test_id']; ?>"
                                       class="delete-btn"
                                       title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this test?');">
                                        <i class="fa-solid fa-trash-can" style="font-size: 16px;"></i>
                                    </a>
                                </div>
                            </div>

                            <h3><?php echo htmlspecialchars($test['test_name']); ?></h3>
                            <p class="test-desc"><?php echo htmlspecialchars($test['description']); ?></p>

                            <div class="test-card-footer">
                                <div class="test-card-meta">
                                    <span class="test-type-badge"><?php echo htmlspecialchars($test['test_category']); ?></span>
                                    <span class="test-type-badge"><?php echo htmlspecialchars(sample_requirement_display_label($test['sample_requirement'])); ?></span>
                                </div>
                                <span class="test-price">৳<?php echo number_format((float)$test['price'], 0); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

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

            <label>Type</label>
            <select name="test_category" required>
                <?php foreach ($categoryOptions as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $option === 'Laboratory' ? 'selected' : ''; ?>><?php echo htmlspecialchars($option); ?></option>
                <?php endforeach; ?>
            </select>

            <label>Sample Requirement</label>
            <select name="sample_requirement" required>
                <?php foreach ($sampleOptions as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>" <?php echo $option === 'Blood' ? 'selected' : ''; ?>><?php echo htmlspecialchars(sample_requirement_display_label($option)); ?></option>
                <?php endforeach; ?>
            </select>

            <label>Price (৳)</label>
            <input type="number" step="0.01" name="price" placeholder="e.g., 500.00" required>

            <div class="modal-footer">
                <button type="button" class="btn-outline" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" class="btn-primary">Add Test</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('editModal')">×</button>
        <h2>Edit Test</h2>
        <form action="tests-process.php" method="POST">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="test_id" id="edit_test_id">

            <label>Test Name</label>
            <input type="text" name="test_name" id="edit_test_name" required>

            <label>Description</label>
            <textarea name="description" id="edit_test_description" rows="3" placeholder="Describe the test"></textarea>

            <label>Type</label>
            <select name="test_category" id="edit_test_category" required>
                <?php foreach ($categoryOptions as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                <?php endforeach; ?>
            </select>

            <label>Sample Requirement</label>
            <select name="sample_requirement" id="edit_test_sample" required>
                <?php foreach ($sampleOptions as $option): ?>
                    <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars(sample_requirement_display_label($option)); ?></option>
                <?php endforeach; ?>
            </select>

            <label>Price (৳)</label>
            <input type="number" step="0.01" name="price" id="edit_test_price" required>

            <label>Status</label>
            <select name="status" id="edit_test_status" required>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>

            <div class="modal-footer">
                <button type="button" class="btn-outline" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addModal').classList.add('active');
}

function openEditModal(button) {
    document.getElementById('edit_test_id').value = button.dataset.testId || '';
    document.getElementById('edit_test_name').value = button.dataset.testName || '';
    document.getElementById('edit_test_description').value = button.dataset.testDescription || '';
    document.getElementById('edit_test_price').value = button.dataset.testPrice || '';
    document.getElementById('edit_test_status').value = button.dataset.testStatus || 'Active';
    document.getElementById('edit_test_category').value = button.dataset.testCategory || 'General';
    document.getElementById('edit_test_sample').value = button.dataset.testSample || 'None';
    document.getElementById('editModal').classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            overlay.classList.remove('active');
        }
    });
});
</script>

<?php $conn->close(); ?>
</body>
</html>

