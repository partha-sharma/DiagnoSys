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

ensureUsersVerificationColumns($conn);
ensurePaymentsTable($conn);
?>