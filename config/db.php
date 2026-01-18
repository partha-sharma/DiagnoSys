<?php
$conn = new mysqli('localhost', 'root', '', 'diagnosys_db');
if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}
// No error means it works!
?>