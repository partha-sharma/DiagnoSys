<?php
// includes/functions.php



function validateInput($dbConn, $input) {
    if($input) {
        return mysqli_real_escape_string($dbConn, htmlspecialchars(trim($input)));
    }
    return "";
}


// It's a helper to make redirects cleaner and allows for messages
function redirect($url, $message = null, $type = 'success') {
    if ($message) {
        $_SESSION['message'] = [
            'text' => $message,
            'type' => $type // 'success' or 'error'
        ];
    }
    header("Location: $url");
    exit();
}


// --- Add these two new authorization functions ---

/**
 * Ensures that only logged-in patients can access a page.
 * If not, redirects them to the login page or admin dashboard.
 */
function protect_patient_page() {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        redirect('/DiagnoSys/login.php', 'You must be logged in to view this page.', 'error');
    }

    // Check if the logged-in user is a patient
    if ($_SESSION['user_role'] !== 'patient') {
        // If they are an admin, send them to their dashboard.
        // If some other role existed, you could handle it here.
        redirect('/DiagnoSys/admin/index.php', 'Access denied. You are not a patient.', 'error');
    }
}


/**
 * Ensures that only logged-in admins can access a page.
 * If not, redirects them to the login page or patient dashboard.
 */
function protect_admin_page() {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        redirect('/DiagnoSys/login.php', 'You must be logged in to view this page.', 'error');
    }

    // Check if the logged-in user is an admin
    if ($_SESSION['user_role'] !== 'admin') {
        // If they are a patient, send them to their dashboard.
        redirect('/DiagnoSys/dashboard.php', 'Access denied. You do not have admin privileges.', 'error');
    }
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