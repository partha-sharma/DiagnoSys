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
?>