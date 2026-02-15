<?php
// includes/functions.php

function validateInput($dbConn, $input) {
    if($input) {
        return mysqli_real_escape_string($dbConn, htmlspecialchars(trim($input)));
    }
    return "";
}

function redirect($url, $message = null) {
    if ($message) {
        $_SESSION['message'] = $message;
    }
    header("Location: " . $url);
    exit(0);
}

// FIX: Checking for 'user_id' instead of 'auth_id' to match friend's code
function isLoggedIn() {
    if(isset($_SESSION['user_id'])) {
        return true;
    } else {
        return false;
    }
}

function requireLogin() {
    if(!isLoggedIn()){
        // Save error in the format friend uses
        $_SESSION['errors'] = ["You must login to continue."];
        redirect('login.php');
    }
}

function alertMessage() {
    // Check for Friend's SUCCESS messages
    if(isset($_SESSION['success'])) {
        echo '<div style="background:#d4edda; color:#155724; padding:10px; border-radius:5px; margin-bottom:15px;">'.$_SESSION['success'].'</div>';
        unset($_SESSION['success']);
    }
    
    // Check for Friend's ERROR messages
    if(isset($_SESSION['errors'])) {
        echo '<div style="background:#f8d7da; color:#721c24; padding:10px; border-radius:5px; margin-bottom:15px;">';
        foreach($_SESSION['errors'] as $error) {
            echo '<p>'.$error.'</p>';
        }
        echo '</div>';
        unset($_SESSION['errors']);
    }

    // Check for Leader's MESSAGE logic
    if(isset($_SESSION['message'])) {
        echo '<div style="background:#cce5ff; color:#004085; padding:10px; border-radius:5px; margin-bottom:15px;">'.$_SESSION['message'].'</div>';
        unset($_SESSION['message']);
    }
}
?>