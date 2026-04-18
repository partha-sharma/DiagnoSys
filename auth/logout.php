<?php
// logout.php
require '../config/init.php';

// 1. Unset all session values
session_unset();

// 2. Destroy the session completely
session_destroy();

// 3. Redirect to login with a nice message
// We can't use $_SESSION anymore for message because we destroyed it!
// So we just go to login.php
header("Location: login.php?msg=logged_out");
exit();
?>