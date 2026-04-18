<?php
require_once '../config/init.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../auth/login.php');
    exit();
}

$testsResult = $conn->query("SELECT test_id, test_name, price, status FROM tests ORDER BY test_name ASC");
$allTests = [];
if ($testsResult) {
    while ($test = $testsResult->fetch_assoc()) {
        $allTests[] = $test;
    }
}

function redirect_packages(): void
{
    header('Location: packages.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_package') {
        $packageId = (int)($_POST['package_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $discountPercent = (float)($_POST['discount_percent'] ?? 0);
        $status = trim($_POST['status'] ?? 'Active');
        $selectedTestIds = array_values(array_unique(array_map('intval', $_POST['test_ids'] ?? [])));
        $selectedPrices = $_POST['test_prices'] ?? [];

        if ($name === '' || empty($selectedTestIds)) {
            $_SESSION['error'] = 'Package name and at least one test are required.';
            redirect_packages();
        }

        if (!in_array($status, ['Active', 'Inactive'], true)) {
            $status = 'Active';
        }

        $testMap = [];
        foreach ($allTests as $test) {
            $testMap[(int)$test['test_id']] = $test;
        }

        $packageTests = [];
        $basePrice = 0.0;
        foreach ($selectedTestIds as $testId) {
            if (!isset($testMap[$testId])) {
                continue;
            }

            $defaultPrice = (float)$testMap[$testId]['price'];
            $overridePrice = isset($selectedPrices[$testId]) ? (float)$selectedPrices[$testId] : $defaultPrice;
            $overridePrice = max(0, $overridePrice);
            $basePrice += $overridePrice;

            $packageTests[] = [
                'test_id' => $testId,
                'test_name' => $testMap[$testId]['test_name'],
                'base_price' => $defaultPrice,
                'package_price' => $overridePrice,
            ];
        }

        if (empty($packageTests)) {
            $_SESSION['error'] = 'Please select valid tests for this package.';
            redirect_packages();
        }

        $discountPercent = max(0, min(100, $discountPercent));
        $finalPrice = max(0, $basePrice - ($basePrice * $discountPercent / 100));

        $conn->begin_transaction();

        try {
            if ($packageId > 0) {
                $stmt = $conn->prepare('UPDATE packages SET name = ?, description = ?, base_price = ?, discount_percent = ?, final_price = ?, status = ? WHERE package_id = ?');
                $stmt->bind_param('ssddssi', $name, $description, $basePrice, $discountPercent, $finalPrice, $status, $packageId);
            } else {
                $stmt = $conn->prepare('INSERT INTO packages (name, description, base_price, discount_percent, final_price, status) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->bind_param('ssddds', $name, $description, $basePrice, $discountPercent, $finalPrice, $status);
            }

            if (!$stmt->execute()) {
                throw new Exception($stmt->error ?: 'Package save failed.');
            }

            if ($packageId <= 0) {
                $packageId = (int)$conn->insert_id;
            }
            $stmt->close();

            $deleteStmt = $conn->prepare('DELETE FROM package_tests WHERE package_id = ?');
            $deleteStmt->bind_param('i', $packageId);
            $deleteStmt->execute();
            $deleteStmt->close();

            $insertStmt = $conn->prepare('INSERT INTO package_tests (package_id, test_id, package_test_price) VALUES (?, ?, ?)');
            foreach ($packageTests as $item) {
                $testId = (int)$item['test_id'];
                $packagePrice = (float)$item['package_price'];
                $insertStmt->bind_param('iid', $packageId, $testId, $packagePrice);
                $insertStmt->execute();
            }
            $insertStmt->close();

            $conn->commit();
            $_SESSION['success'] = $packageId > 0 ? 'Package saved successfully.' : 'Package added successfully.';
        } catch (Throwable $e) {
            $conn->rollback();
            $_SESSION['error'] = 'Could not save package. Please try again.';
        }

        redirect_packages();
    }

    if ($action === 'toggle_status') {
        $packageId = (int)($_POST['package_id'] ?? 0);
        $nextStatus = trim($_POST['next_status'] ?? 'Active');
        $nextStatus = $nextStatus === 'Inactive' ? 'Inactive' : 'Active';

        $stmt = $conn->prepare('UPDATE packages SET status = ? WHERE package_id = ?');
        $stmt->bind_param('si', $nextStatus, $packageId);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success'] = 'Package status updated.';
        redirect_packages();
    }

    if ($action === 'delete') {
        $packageId = (int)($_POST['package_id'] ?? 0);
        if ($packageId <= 0) {
            $_SESSION['error'] = 'Invalid package selected.';
            redirect_packages();
        }

        $stmt = $conn->prepare('DELETE FROM packages WHERE package_id = ?');
        $stmt->bind_param('i', $packageId);
        $stmt->execute();
        $stmt->close();

        $_SESSION['success'] = 'Package deleted successfully.';
        redirect_packages();
    }
}

$packagesResult = $conn->query('SELECT package_id, name, description, base_price, discount_percent, final_price, status FROM packages ORDER BY package_id DESC');
$packageTestsResult = $conn->query('SELECT pt.package_id, pt.test_id, pt.package_test_price, t.test_name, t.price AS test_price FROM package_tests pt INNER JOIN tests t ON t.test_id = pt.test_id ORDER BY t.test_name ASC');

$packages = [];
if ($packagesResult) {
    while ($row = $packagesResult->fetch_assoc()) {
        $row['tests'] = [];
        $packages[$row['package_id']] = $row;
    }
}

if ($packageTestsResult) {
    while ($row = $packageTestsResult->fetch_assoc()) {
        $packageId = (int)$row['package_id'];
        if (!isset($packages[$packageId])) {
            continue;
        }
        $packages[$packageId]['tests'][] = $row;
    }
}

$packageCatalog = array_values($packages);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Packages - DiagnoLab</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="admin-layout">
    <div class="sidebar">
        <h2 style="margin-bottom: 30px;">Admin Panel</h2>
        <a href="index.php">Dashboard</a>
        <a href="manage_tests.php">Manage Tests</a>
        <a href="appointments.php">Appointments</a>
        <a href="technicians.php">Technicians</a>
        <a href="packages.php" class="active">Packages</a>
        <a href="manage_rooms.php">Manage Rooms</a>
        <a href="finance.php">Finance</a>
        <a href="users.php">Patients List</a>
        <hr style="border-color: #334155; margin: 20px 0;">
        <a href="../auth/logout.php" class="sidebar-logout">Logout</a>
    </div>

    <div class="content">
        <div class="manage-tests-header">
            <div class="header-text" style="text-align:left;">
                <h1>Package System</h1>
                <p>Create and manage predefined diagnostic bundles.</p>
            </div>
            <div class="header-actions">
                <button type="button" class="btn-primary" onclick="openPackageModal('add')">+ Add Package</button>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php if (empty($packageCatalog)): ?>
            <div class="empty-state" style="background:#fff; margin-top:20px; border-radius:12px;">
                <p>No packages yet. Add your first package to bundle tests for patients.</p>
                <button type="button" class="btn-primary" onclick="openPackageModal('add')">+ Add Package</button>
            </div>
        <?php else: ?>
            <div class="package-grid">
                <?php foreach ($packageCatalog as $row): ?>
                    <div class="package-card">
                        <div class="test-card-top">
                            <div class="test-card-icon"><i class="fa-solid fa-box-open" style="font-size:28px;"></i></div>
                            <div class="test-card-actions">
                                <button type="button"
                                        class="btn-outline package-action-btn"
                                        title="Edit"
                                        onclick="openPackageModal('edit', <?php echo (int)$row['package_id']; ?>)">
                                    Edit
                                </button>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="package_id" value="<?php echo (int)$row['package_id']; ?>">
                                    <input type="hidden" name="next_status" value="<?php echo $row['status'] === 'Active' ? 'Inactive' : 'Active'; ?>">
                                    <button type="submit" class="btn-outline package-action-btn" title="Toggle Status">
                                        <?php echo $row['status'] === 'Active' ? 'Set Inactive' : 'Set Active'; ?>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this package?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="package_id" value="<?php echo (int)$row['package_id']; ?>">
                                    <button type="submit" class="btn-danger package-action-btn" title="Delete">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>

                        <h3><?php echo htmlspecialchars($row['name']); ?></h3>
                        <p class="test-desc"><?php echo htmlspecialchars($row['description'] ?: 'No description provided.'); ?></p>

                        <div class="package-meta">
                            <span class="status-badge <?php echo $row['status'] === 'Active' ? 'status-completed' : 'status-cancelled'; ?>">
                                <?php echo htmlspecialchars($row['status']); ?>
                            </span>
                            <span class="test-duration"><?php echo count($row['tests']); ?> tests included</span>
                        </div>

                        <div class="package-test-tags">
                            <?php foreach ($row['tests'] as $test): ?>
                                <span class="test-type-badge">
                                    <?php echo htmlspecialchars($test['test_name']); ?> - ৳<?php echo number_format((float)$test['package_test_price'], 2); ?>
                                </span>
                            <?php endforeach; ?>
                        </div>

                        <div class="test-card-footer">
                            <div class="test-card-meta">
                                <span class="test-duration">Base: ৳<?php echo number_format((float)$row['base_price'], 2); ?></span>
                                <span class="test-duration">Discount: <?php echo number_format((float)$row['discount_percent'], 2); ?>%</span>
                            </div>
                            <span class="test-price">৳<?php echo number_format((float)$row['final_price'], 2); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="packageModal">
    <div class="modal-box modal-box-wide">
        <button class="modal-close" onclick="closeModal('packageModal')">×</button>
        <h2 id="packageModalTitle">Add Package</h2>
        <form method="POST" id="packageForm">
            <input type="hidden" name="action" value="save_package">
            <input type="hidden" name="package_id" id="package_id">

            <div class="package-form-grid">
                <div>
                    <label>Package Name</label>
                    <input type="text" name="name" id="package_name" class="form-input" required>
                </div>
                <div>
                    <label>Status</label>
                    <select name="status" id="package_status" class="form-input">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="package-full-width">
                    <label>Description</label>
                    <textarea name="description" id="package_description" class="form-input" rows="3" placeholder="Package description"></textarea>
                </div>
                <div>
                    <label>Discount Percent</label>
                    <input type="number" step="0.01" min="0" max="100" name="discount_percent" id="package_discount_percent" class="form-input" value="0">
                </div>
                <div>
                    <label>Base Price</label>
                    <input type="text" id="package_base_price" class="form-input" readonly>
                </div>
                <div>
                    <label>Final Price</label>
                    <input type="text" id="package_final_price" class="form-input" readonly>
                </div>
            </div>

            <div class="package-test-picker">
                <div class="package-test-picker-header">
                    <h3>Select Tests From Master List</h3>
                    <p>Check the tests to include in this package. You can override the package price for each selected test.</p>
                </div>

                <div class="package-test-list">
                    <?php foreach ($allTests as $test): ?>
                        <?php $testId = (int)$test['test_id']; ?>
                        <div class="package-test-row">
                            <label class="package-test-check">
                                <input type="checkbox" name="test_ids[]" value="<?php echo $testId; ?>" class="package-test-checkbox" data-test-id="<?php echo $testId; ?>">
                                <span>
                                    <?php echo htmlspecialchars($test['test_name']); ?>
                                    <small>Master price: ৳<?php echo number_format((float)$test['price'], 2); ?></small>
                                </span>
                            </label>
                            <div class="package-test-price-box">
                                <label for="test_price_<?php echo $testId; ?>">Package Price</label>
                                <input type="number" step="0.01" min="0" name="test_prices[<?php echo $testId; ?>]" id="test_price_<?php echo $testId; ?>" class="form-input package-test-price" value="<?php echo number_format((float)$test['price'], 2, '.', ''); ?>" disabled>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-outline" onclick="closeModal('packageModal')">Cancel</button>
                <button type="submit" class="btn-primary" id="packageSubmitBtn">Save Package</button>
            </div>
        </form>
    </div>
</div>

<script>
const packageCatalog = <?php echo json_encode($packageCatalog, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

function openPackageModal(mode, packageId = null) {
    const modal = document.getElementById('packageModal');
    const title = document.getElementById('packageModalTitle');
    const form = document.getElementById('packageForm');

    form.reset();
    document.getElementById('package_id').value = '';
    document.getElementById('package_status').value = 'Active';
    document.getElementById('package_discount_percent').value = '0';
    title.textContent = 'Add Package';
    document.getElementById('packageSubmitBtn').textContent = 'Save Package';

    document.querySelectorAll('.package-test-checkbox').forEach(function(checkbox) {
        checkbox.checked = false;
    });
    document.querySelectorAll('.package-test-price').forEach(function(input) {
        input.disabled = true;
        input.value = input.defaultValue;
    });

    if (mode === 'edit' && packageId !== null) {
        const packageData = packageCatalog.find(function(item) {
            return String(item.package_id) === String(packageId);
        });

        if (packageData) {
            title.textContent = 'Edit Package';
            document.getElementById('package_id').value = packageData.package_id;
            document.getElementById('package_name').value = packageData.name || '';
            document.getElementById('package_description').value = packageData.description || '';
            document.getElementById('package_discount_percent').value = packageData.discount_percent || 0;
            document.getElementById('package_status').value = packageData.status || 'Active';

            packageData.tests.forEach(function(test) {
                const checkbox = document.querySelector('.package-test-checkbox[value="' + test.test_id + '"]');
                const priceInput = document.getElementById('test_price_' + test.test_id);
                if (checkbox && priceInput) {
                    checkbox.checked = true;
                    priceInput.disabled = false;
                    priceInput.value = Number(test.package_test_price || test.test_price || 0).toFixed(2);
                }
            });
        }
    }

    syncPackageTotals();
    modal.classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

function syncPackageTotals() {
    let basePrice = 0;
    document.querySelectorAll('.package-test-row').forEach(function(row) {
        const checkbox = row.querySelector('.package-test-checkbox');
        const priceInput = row.querySelector('.package-test-price');
        if (checkbox && checkbox.checked && priceInput) {
            priceInput.disabled = false;
            const value = parseFloat(priceInput.value || '0');
            basePrice += isNaN(value) ? 0 : value;
        } else if (priceInput) {
            priceInput.disabled = true;
        }
    });

    const discount = parseFloat(document.getElementById('package_discount_percent').value || '0');
    const finalPrice = Math.max(0, basePrice - (basePrice * discount / 100));

    document.getElementById('package_base_price').value = '৳' + basePrice.toFixed(2);
    document.getElementById('package_final_price').value = '৳' + finalPrice.toFixed(2);
}

document.querySelectorAll('.package-test-checkbox').forEach(function(checkbox) {
    checkbox.addEventListener('change', function() {
        const row = this.closest('.package-test-row');
        const priceInput = row ? row.querySelector('.package-test-price') : null;
        if (priceInput) {
            priceInput.disabled = !this.checked;
            if (this.checked && !priceInput.value) {
                priceInput.value = '0.00';
            }
        }
        syncPackageTotals();
    });
});

document.querySelectorAll('.package-test-price, #package_discount_percent').forEach(function(input) {
    input.addEventListener('input', syncPackageTotals);
});

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
<?php $conn->close(); ?>

