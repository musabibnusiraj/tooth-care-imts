<?php
// Load configuration and helper classes
include __DIR__ . '/../config.php';
include __DIR__ . '/../helpers/AppManager.php';

// Initialize session manager (SM) and persistence manager (PM) with error handling
try {
    $sm = AppManager::getSM(); // Session manager (handles session attributes)
    $pm = AppManager::getPM(); // Persistence manager (DB access)
} catch (Exception $e) {
    // Log the error for server-side diagnostics
    error_log('Auth error: ' . $e->getMessage());

    // If session manager exists, set a user-facing error message
    if (isset($sm)) {
        $sm->setAttribute("error", 'Database connection failed. Please contact the administrator.');
    }

    // Redirect back to the referring page (or the login view if none)
    header("Location: " . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../views/auth/login.php'));
    exit;
}

// Ensure required POST fields are present
if (!isset($_POST['email']) || !isset($_POST['password'])) {
    $sm->setAttribute("error", 'Please fill all required fields!');
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

// Retrieve and normalize input
$email = trim($_POST['email']);
$password = $_POST['password'];

// Ensure inputs are not empty after trimming
if (empty($email) || empty($password)) {
    $sm->setAttribute("error", 'Please fill all required fields!');
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

try {
    // Prepare parameter for query (matches email OR username)
    $param = array(':email' => $email);

    // Fetch a single user record; third argument 'true' implies single-row fetch
    $user = $pm->run("SELECT * FROM users WHERE email = :email OR username = :email", $param, true);

    if ($user != null && is_array($user)) {
        // Verify provided password against stored hash
        $correct = password_verify($password, $user['password']);
        if ($correct) {
            // Check whether the account is active (is_active == 0 means inactive)
            if (isset($user['is_active']) && $user['is_active'] == 0) {
                $sm->setAttribute("error", 'Your account is not active. Please contact the administrator.');
                header("Location: " . $_SERVER['HTTP_REFERER']);
                exit;
            }

            // Authentication successful: set session attributes
            $sm->setAttribute("userId", $user['id']);
            $sm->setAttribute("username", $user['username']);
            $sm->setAttribute("permission", $user['permission']);

            // Redirect to main application page
            header('location: ../app.php');
            exit;
        } else {
            // Incorrect password
            $sm->setAttribute("error", 'Invalid username or password!');
        }
    } else {
        // No matching user found
        $sm->setAttribute("error", 'Invalid username or password!');
    }
} catch (Exception $e) {
    // Log unexpected DB/authentication errors and show a generic message to the user
    error_log('Auth database error: ' . $e->getMessage());
    $sm->setAttribute("error", 'An error occurred during authentication. Please try again later.');
}

// Redirect back to referring page with error message in session
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
