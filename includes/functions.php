<?php
// includes/functions.php

/**
 * 1. SANITIZE INPUTS
 * Usage: $name = validateInput($_POST['name']);
 * Purpose: Prevents Hackers (XSS/SQL Injection basic)
 */
function validateInput($dbConn, $input) {
    if($input) {
        $input = trim($input); // Remove extra spaces
        $input = stripslashes($input); // Remove backslashes
        $input = htmlspecialchars($input); // Convert < > to codes
        return mysqli_real_escape_string($dbConn, $input);
    }
    return "";
}

/**
 * 2. REDIRECT HELPER
 * Usage: redirect('login.php', 'Login to continue');
 * Purpose: Makes moving between pages easier
 */
function redirect($url, $message = null) {
    if ($message) {
        $_SESSION['message'] = $message;
    }
    header("Location: " . $url);
    exit(0);
}

/**
 * 3. CHECK LOGIN STATUS
 * Usage: if(!isLoggedIn()) { redirect('login.php'); }
 */
function isLoggedIn() {
    if(isset($_SESSION['auth_id'])) {
        return true;
    } else {
        return false;
    }
}

/**
 * 4. ALERT MESSAGE DISPLAYER
 * Usage: <?= alertMessage(); ?> in HTML
 */
function alertMessage() {
    if(isset($_SESSION['message'])) {
        echo '<div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">'.$_SESSION['message'].'</span>
              </div>';
        unset($_SESSION['message']); // Clear message after showing
    }
}
?> 