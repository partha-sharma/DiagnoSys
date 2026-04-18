<?php
// config/init.php
session_start();
require_once __DIR__ . '/../includes/test_taxonomy.php';

$host = 'localhost';
$db_name = 'diagnosys_db';
$username = 'root'; 
$password = '';    

// Create connection using MySQLi (matches your coding style)
$conn = new mysqli($host, $username, $password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Keep older local databases compatible with current auth/email-verification flows.
function ensureUsersVerificationColumns(mysqli $conn): void
{
    $requiredColumns = [
        'email_verified' => "ALTER TABLE users ADD COLUMN email_verified TINYINT(1) DEFAULT 0",
        'email_token' => "ALTER TABLE users ADD COLUMN email_token VARCHAR(255) DEFAULT NULL",
        'email_token_expiry' => "ALTER TABLE users ADD COLUMN email_token_expiry DATETIME DEFAULT NULL",
    ];

    foreach ($requiredColumns as $columnName => $alterSql) {
        $check = $conn->prepare(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = ? LIMIT 1"
        );

        if (!$check) {
            die('Schema check failed: ' . $conn->error);
        }

        $check->bind_param('s', $columnName);
        $check->execute();
        $result = $check->get_result();
        $exists = $result && $result->num_rows > 0;
        $check->close();

        if (!$exists && !$conn->query($alterSql)) {
            die("Database schema update failed for '{$columnName}': " . $conn->error);
        }
    }
}

function ensurePaymentsTable(mysqli $conn): void
{
    $sql = "CREATE TABLE IF NOT EXISTS payments (
        payment_id INT(11) NOT NULL AUTO_INCREMENT,
        appointment_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('Pending','Processing','Completed','Failed','Refunded') DEFAULT 'Pending',
        payment_method VARCHAR(50) DEFAULT NULL,
        transaction_id VARCHAR(100) DEFAULT NULL,
        payment_date DATETIME DEFAULT NULL,
        refund_amount DECIMAL(10,2) DEFAULT 0.00,
        refund_reason TEXT DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (payment_id),
        KEY idx_payments_appointment_id (appointment_id),
        KEY idx_payments_user_id (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    if (!$conn->query($sql)) {
        die('Database schema update failed for payments table: ' . $conn->error);
    }
}

function ensureTechnicianAuthColumns(mysqli $conn): void
{
    $requiredColumns = [
        'password_hash' => "ALTER TABLE technicians ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL AFTER phone",
    ];

    foreach ($requiredColumns as $columnName => $alterSql) {
        $check = $conn->prepare(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'technicians' AND COLUMN_NAME = ? LIMIT 1"
        );

        if (!$check) {
            die('Schema check failed: ' . $conn->error);
        }

        $check->bind_param('s', $columnName);
        $check->execute();
        $result = $check->get_result();
        $exists = $result && $result->num_rows > 0;
        $check->close();

        if (!$exists && !$conn->query($alterSql)) {
            die("Database schema update failed for technicians.{$columnName}: " . $conn->error);
        }
    }
}

function ensureTestResultsColumns(mysqli $conn): void
{
    $requiredColumns = [
        'technician_id' => "ALTER TABLE test_results ADD COLUMN technician_id INT(11) DEFAULT NULL AFTER admin_id",
    ];

    foreach ($requiredColumns as $columnName => $alterSql) {
        $check = $conn->prepare(
            "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'test_results' AND COLUMN_NAME = ? LIMIT 1"
        );

        if (!$check) {
            die('Schema check failed: ' . $conn->error);
        }

        $check->bind_param('s', $columnName);
        $check->execute();
        $result = $check->get_result();
        $exists = $result && $result->num_rows > 0;
        $check->close();

        if (!$exists && !$conn->query($alterSql)) {
            die("Database schema update failed for test_results.{$columnName}: " . $conn->error);
        }
    }

    $fkCheck = $conn->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'test_results' AND CONSTRAINT_NAME = 'test_results_ibfk_technician' LIMIT 1");
    if ($fkCheck && $fkCheck->num_rows === 0) {
        $conn->query("ALTER TABLE test_results ADD CONSTRAINT test_results_ibfk_technician FOREIGN KEY (technician_id) REFERENCES technicians (technician_id) ON DELETE SET NULL");
    }
}

function ensurePackageBookingColumns(mysqli $conn): void
{
    $requiredColumns = [
        'package_id' => "ALTER TABLE appointments ADD COLUMN package_id INT(11) DEFAULT NULL AFTER user_id",
        'package_name_snapshot' => "ALTER TABLE appointments ADD COLUMN package_name_snapshot VARCHAR(100) DEFAULT NULL AFTER package_id",
        'package_tests_snapshot' => "ALTER TABLE appointments ADD COLUMN package_tests_snapshot LONGTEXT DEFAULT NULL AFTER package_name_snapshot",
        'package_price_snapshot' => "ALTER TABLE appointments ADD COLUMN package_price_snapshot DECIMAL(10,2) DEFAULT 0.00 AFTER package_tests_snapshot",
    ];

    foreach ($requiredColumns as $columnName => $alterSql) {
        $check = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = ? LIMIT 1");
        if (!$check) {
            die('Schema check failed: ' . $conn->error);
        }

        $check->bind_param('s', $columnName);
        $check->execute();
        $result = $check->get_result();
        $exists = $result && $result->num_rows > 0;
        $check->close();

        if (!$exists && !$conn->query($alterSql)) {
            die("Database schema update failed for '{$columnName}': " . $conn->error);
        }
    }

    $indexCheck = $conn->query("SHOW INDEX FROM appointments WHERE Key_name = 'idx_package_id'");
    if ($indexCheck && $indexCheck->num_rows === 0) {
        $conn->query("ALTER TABLE appointments ADD KEY idx_package_id (package_id)");
    }

    $fkCheck = $conn->query("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND CONSTRAINT_NAME = 'appointments_ibfk_package' LIMIT 1");
    if ($fkCheck && $fkCheck->num_rows === 0) {
        $conn->query("ALTER TABLE appointments ADD CONSTRAINT appointments_ibfk_package FOREIGN KEY (package_id) REFERENCES packages (package_id) ON DELETE SET NULL");
    }
}

function ensureRoomSchedulingTables(mysqli $conn): void
{
    $queries = [
        "CREATE TABLE IF NOT EXISTS rooms (
            room_id INT(11) NOT NULL AUTO_INCREMENT,
            room_number VARCHAR(30) NOT NULL,
            department VARCHAR(100) DEFAULT NULL,
            status ENUM('Active','Inactive') DEFAULT 'Active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (room_id),
            UNIQUE KEY uniq_room_number (room_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        "CREATE TABLE IF NOT EXISTS room_time_slots (
            slot_id INT(11) NOT NULL AUTO_INCREMENT,
            slot_label VARCHAR(50) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            status ENUM('Active','Inactive') DEFAULT 'Active',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (slot_id),
            UNIQUE KEY uniq_slot_label (slot_label)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        "CREATE TABLE IF NOT EXISTS room_assignments (
            assignment_id INT(11) NOT NULL AUTO_INCREMENT,
            room_id INT(11) NOT NULL,
            slot_id INT(11) NOT NULL,
            map_scope ENUM('type','test') DEFAULT 'type',
            mapped_test_type VARCHAR(100) DEFAULT NULL,
            mapped_test_id INT(11) DEFAULT NULL,
            status ENUM('Active','Inactive') DEFAULT 'Active',
            capacity INT(11) DEFAULT 10,
            booked_count INT(11) DEFAULT 0,
            color_code VARCHAR(20) DEFAULT '#dbeafe',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (assignment_id),
            UNIQUE KEY uniq_room_slot (room_id, slot_id),
            KEY idx_assignment_test_type (mapped_test_type),
            KEY idx_assignment_test_id (mapped_test_id),
            CONSTRAINT fk_room_assignment_room FOREIGN KEY (room_id) REFERENCES rooms (room_id) ON DELETE CASCADE,
            CONSTRAINT fk_room_assignment_slot FOREIGN KEY (slot_id) REFERENCES room_time_slots (slot_id) ON DELETE CASCADE,
            CONSTRAINT fk_room_assignment_test FOREIGN KEY (mapped_test_id) REFERENCES tests (test_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        "CREATE TABLE IF NOT EXISTS appointment_test_plan (
            plan_id INT(11) NOT NULL AUTO_INCREMENT,
            appointment_id INT(11) NOT NULL,
            test_id INT(11) NOT NULL,
            test_name_snapshot VARCHAR(150) NOT NULL,
            room_id INT(11) DEFAULT NULL,
            room_number_snapshot VARCHAR(30) DEFAULT NULL,
            slot_id INT(11) DEFAULT NULL,
            slot_label_snapshot VARCHAR(50) DEFAULT NULL,
            estimated_at DATETIME DEFAULT NULL,
            sequence_no INT(11) DEFAULT 0,
            status ENUM('Planned','Completed','Skipped') DEFAULT 'Planned',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (plan_id),
            KEY idx_plan_appointment_id (appointment_id),
            KEY idx_plan_test_id (test_id),
            CONSTRAINT fk_plan_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (appointment_id) ON DELETE CASCADE,
            CONSTRAINT fk_plan_test FOREIGN KEY (test_id) REFERENCES tests (test_id) ON DELETE CASCADE,
            CONSTRAINT fk_plan_room FOREIGN KEY (room_id) REFERENCES rooms (room_id) ON DELETE SET NULL,
            CONSTRAINT fk_plan_slot FOREIGN KEY (slot_id) REFERENCES room_time_slots (slot_id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",

        "CREATE TABLE IF NOT EXISTS notification_logs (
            notification_id INT(11) NOT NULL AUTO_INCREMENT,
            appointment_id INT(11) NOT NULL,
            user_id INT(11) NOT NULL,
            channel ENUM('SMS','Email') NOT NULL,
            recipient VARCHAR(150) DEFAULT NULL,
            message_text TEXT NOT NULL,
            status ENUM('Queued','Sent','Failed') DEFAULT 'Queued',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (notification_id),
            KEY idx_notification_appointment (appointment_id),
            KEY idx_notification_user (user_id),
            CONSTRAINT fk_notification_appointment FOREIGN KEY (appointment_id) REFERENCES appointments (appointment_id) ON DELETE CASCADE,
            CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
    ];

    foreach ($queries as $sql) {
        if (!$conn->query($sql)) {
            die('Database schema update failed for room scheduling tables: ' . $conn->error);
        }
    }

    $testTypeCheck = $conn->query("SHOW COLUMNS FROM tests LIKE 'test_type'");
    if ($testTypeCheck && $testTypeCheck->num_rows === 0) {
        $conn->query("ALTER TABLE tests ADD COLUMN test_type VARCHAR(100) DEFAULT NULL AFTER description");
    }

    $testCategoryCheck = $conn->query("SHOW COLUMNS FROM tests LIKE 'test_category'");
    if ($testCategoryCheck && $testCategoryCheck->num_rows === 0) {
        $conn->query("ALTER TABLE tests ADD COLUMN test_category VARCHAR(50) DEFAULT NULL AFTER description");
    }

    $sampleRequirementCheck = $conn->query("SHOW COLUMNS FROM tests LIKE 'sample_requirement'");
    if ($sampleRequirementCheck && $sampleRequirementCheck->num_rows === 0) {
        $conn->query("ALTER TABLE tests ADD COLUMN sample_requirement VARCHAR(30) DEFAULT NULL AFTER test_category");
    }

    $testsResult = $conn->query("SELECT test_id, test_name, COALESCE(description, '') AS description, COALESCE(test_type, '') AS test_type, COALESCE(test_category, '') AS test_category, COALESCE(sample_requirement, '') AS sample_requirement FROM tests");
    if ($testsResult) {
        $updateStmt = $conn->prepare("UPDATE tests SET test_category = ?, sample_requirement = ? WHERE test_id = ?");

        while ($test = $testsResult->fetch_assoc()) {
            $currentCategory = trim((string)$test['test_category']);
            $currentSample = trim((string)$test['sample_requirement']);

            $category = $currentCategory !== ''
                ? normalize_test_category($currentCategory)
                : infer_test_category_from_text((string)$test['test_name'], (string)$test['description'], (string)$test['test_type']);

            $sample = $currentSample !== ''
                ? normalize_sample_requirement($currentSample)
                : infer_sample_requirement_from_text((string)$test['test_name'], (string)$test['description'], $category);

            $testId = (int)$test['test_id'];
            $updateStmt->bind_param('ssi', $category, $sample, $testId);
            $updateStmt->execute();
        }

        $updateStmt->close();
    }

    $roomCountResult = $conn->query("SELECT COUNT(*) AS cnt FROM rooms");
    $roomCount = $roomCountResult ? (int)($roomCountResult->fetch_assoc()['cnt'] ?? 0) : 0;
    if ($roomCount === 0) {
        $conn->query("INSERT INTO rooms (room_number, department, status) VALUES
            ('Room 101', 'Sample Collection', 'Active'),
            ('Room 202', 'Cardiology', 'Active'),
            ('Room 303', 'Radiology', 'Active')");
    }

    $slotCountResult = $conn->query("SELECT COUNT(*) AS cnt FROM room_time_slots");
    $slotCount = $slotCountResult ? (int)($slotCountResult->fetch_assoc()['cnt'] ?? 0) : 0;
    if ($slotCount === 0) {
        $conn->query("INSERT INTO room_time_slots (slot_label, start_time, end_time, status) VALUES
            ('09:00-10:00', '09:00:00', '10:00:00', 'Active'),
            ('10:00-11:00', '10:00:00', '11:00:00', 'Active'),
            ('11:00-12:00', '11:00:00', '12:00:00', 'Active'),
            ('12:00-01:00', '12:00:00', '13:00:00', 'Active'),
            ('01:00-02:00', '13:00:00', '14:00:00', 'Active'),
            ('02:00-03:00', '14:00:00', '15:00:00', 'Active'),
            ('03:00-04:00', '15:00:00', '16:00:00', 'Active'),
            ('04:00-05:00', '16:00:00', '17:00:00', 'Active')");
    }
}

function ensureTestCatalogSeedData(mysqli $conn): void
{
    $seedTests = [
        ['Complete Blood Count (CBC)', 'A comprehensive blood panel that evaluates your overall health and detects a wide range of disorders.', 'Laboratory', 'Blood', 50.00, 'Active'],
        ['Lipid Panel', 'Measures the amount of cholesterol and other fats in your blood.', 'Laboratory', 'Blood', 75.50, 'Active'],
        ['Creatinine', 'Urine', 'Laboratory', 'Urine', 10.00, 'Active'],
        ['Creatinine 2.0', 'Blood urea level', 'Laboratory', 'Blood', 15.00, 'Active'],
        ['Testosterone', 'level of hormone in blood', 'Laboratory', 'Blood', 500.00, 'Active'],
        ['ECG', 'Electrocardiogram used to evaluate the electrical activity of the heart.', 'Cardiology', 'None', 300.00, 'Active'],
        ['Echocardiogram', 'Ultrasound of the heart to evaluate structure and function.', 'Cardiology', 'None', 1200.00, 'Active'],
        ['Chest X-Ray', 'Imaging test that captures the chest, lungs, heart, and bones.', 'Imaging', 'None', 800.00, 'Active'],
        ['Urine Routine', 'Routine urine analysis for kidney and metabolic screening.', 'Laboratory', 'Urine', 120.00, 'Active'],
        ['Random Blood Sugar', 'Measures current blood glucose level.', 'Laboratory', 'Blood', 80.00, 'Active'],
        ['HbA1c', 'Reflects average blood glucose levels over the past 2 to 3 months.', 'Laboratory', 'Blood', 250.00, 'Active'],
        ['Thyroid Profile', 'Panel used to assess thyroid hormone balance.', 'Laboratory', 'Blood', 650.00, 'Active'],
        ['Ultrasound Abdomen', 'Imaging examination of abdominal organs.', 'Imaging', 'None', 1500.00, 'Active'],
        ['C-Reactive Protein (CRP)', 'Marker used to detect inflammation in the blood.', 'Laboratory', 'Blood', 180.00, 'Active'],
        ['Erythrocyte Sedimentation Rate (ESR)', 'Measures inflammation level in blood.', 'Laboratory', 'Blood', 160.00, 'Active'],
        ['Dengue NS1 Antigen', 'Blood test used for early dengue detection.', 'Laboratory', 'Blood', 450.00, 'Active'],
        ['COVID-19 PCR', 'Molecular swab test used to detect viral infection.', 'Laboratory', 'Swab', 1200.00, 'Active'],
        ['Pap Smear', 'Cervical screening test for abnormal cells.', 'Laboratory', 'Swab', 700.00, 'Active'],
        ['2D Echo', 'Ultrasound imaging of the heart chambers and valves.', 'Cardiology', 'None', 1800.00, 'Active'],
        ['Treadmill Test (TMT)', 'Exercise stress test to assess heart performance.', 'Cardiology', 'None', 2200.00, 'Active'],
        ['MRI Brain', 'Detailed imaging scan of the brain and nervous system.', 'Imaging', 'None', 4500.00, 'Active'],
        ['CT Scan Abdomen', 'Cross-sectional imaging of abdominal organs.', 'Imaging', 'None', 5000.00, 'Active'],
        ['Stool Routine', 'Routine stool analysis for digestive and infection screening.', 'Laboratory', 'Stool', 200.00, 'Active'],
        ['Liver Function Test (LFT)', 'Panel used to assess liver enzymes and liver health.', 'Laboratory', 'Blood', 400.00, 'Active'],
        ['Uric Acid', 'Measures uric acid levels in the blood.', 'Laboratory', 'Blood', 180.00, 'Active'],
        ['Blood Culture', 'Identifies bacteria or fungus in the bloodstream.', 'Laboratory', 'Blood', 900.00, 'Active'],
        ['HBsAg', 'Screening test for hepatitis B infection.', 'Laboratory', 'Blood', 500.00, 'Active'],
        ['HCV Antibody', 'Screening test for hepatitis C infection.', 'Laboratory', 'Blood', 550.00, 'Active'],
        ['Holter Monitoring', '24-hour heart rhythm monitoring test.', 'Cardiology', 'None', 2500.00, 'Active'],
        ['Mammography', 'Breast imaging screening examination.', 'Imaging', 'None', 3500.00, 'Active'],
        ['X-Ray KUB', 'Imaging of kidneys, ureters, and bladder.', 'Imaging', 'None', 900.00, 'Active'],
    ];

    $checkStmt = $conn->prepare('SELECT test_id FROM tests WHERE test_name = ? LIMIT 1');
    $insertStmt = $conn->prepare('INSERT INTO tests (test_name, description, test_category, sample_requirement, price, status) VALUES (?, ?, ?, ?, ?, ?)');

    foreach ($seedTests as $seedTest) {
        [$name, $description, $category, $sampleRequirement, $price, $status] = $seedTest;

        $checkStmt->bind_param('s', $name);
        $checkStmt->execute();
        $exists = $checkStmt->get_result();
        if ($exists && $exists->num_rows > 0) {
            continue;
        }

        $insertStmt->bind_param('ssssis', $name, $description, $category, $sampleRequirement, $price, $status);
        $insertStmt->execute();
    }

    $checkStmt->close();
    $insertStmt->close();
}

ensureUsersVerificationColumns($conn);
ensurePaymentsTable($conn);
ensureTechnicianAuthColumns($conn);
ensureTestResultsColumns($conn);
ensurePackageBookingColumns($conn);
ensureRoomSchedulingTables($conn);
ensureTestCatalogSeedData($conn);
?>