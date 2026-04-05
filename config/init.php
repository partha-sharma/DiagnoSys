<?php
// config/init.php
session_start();

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

ensureUsersVerificationColumns($conn);
ensurePaymentsTable($conn);
ensurePackageBookingColumns($conn);
ensureRoomSchedulingTables($conn);
?>