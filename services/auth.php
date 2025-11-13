<?php
include __DIR__ . '/../config.php';
include __DIR__ . '/../helpers/AppManager.php';

// Initialize session and persistence managers with error handling
try {
    $sm = AppManager::getSM();
    $pm = AppManager::getPM();
} catch (Exception $e) {
    // Log the error
    error_log('Auth error: ' . $e->getMessage());

    // Try to set error in session if possible
    if (isset($sm)) {
        $sm->setAttribute("error", 'Database connection failed. Please contact the administrator.');
    }

    // Redirect back to login
    header("Location: " . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '../views/auth/login.php'));
    exit;
}

// Validate POST data exists
if (!isset($_POST['email']) || !isset($_POST['password'])) {
    $sm->setAttribute("error", 'Please fill all required fields!');
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

$email = trim($_POST['email']);
$password = $_POST['password'];
$remember = isset($_POST['remember']) ? true : false;

if (empty($email) || empty($password)) {
    $sm->setAttribute("error", 'Please fill all required fields!');
    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
}

try {
    // Use two separate parameters for the same value since it appears twice in the query
    $param = array(':email' => $email, ':username' => $email);
    $user = $pm->run("SELECT * FROM users WHERE email = :email OR username = :username", $param, true);

    // Debug: Log what we got from database
    error_log('Auth Debug - User lookup result: ' . print_r($user, true));

    if ($user !== null && $user !== -1 && is_array($user) && !empty($user)) {
        $correct = password_verify($password, $user['password']);

        // Debug: Log password verification
        error_log('Auth Debug - Password verify result: ' . ($correct ? 'true' : 'false'));

        if ($correct) {
            // Check if user is active (only if is_active field exists)
            if (isset($user['is_active']) && $user['is_active'] == 0) {
                $sm->setAttribute("error", 'Your account is not active. Please contact the administrator.');
                header("Location: " . $_SERVER['HTTP_REFERER']);
                exit;
            }

            // Set session variables
            $sm->setAttribute("userId", $user['id']);
            $sm->setAttribute("username", $user['username']);
            $sm->setAttribute("permission", $user['permission']);

            // Handle Remember Me functionality
            if ($remember) {
                // Set cookie for 30 days
                /**
                 * Expiration timestamp for a long-lived cookie (30 days from now).
                 *
                 * Computation details:
                 * - Adds a 30-day duration, expressed in seconds, to the current UNIX timestamp (time()).
                 * - Breakdown: 30 days * 24 hours/day * 60 minutes/hour * 60 seconds/minute
                 *   = 30 * 24 * 60 * 60 = 2,592,000 seconds.
                 * - Result is an integer UNIX timestamp representing "now + 30 days".
                 */
                $cookie_time = time() + (30 * 24 * 60 * 60); // 30 days
                setcookie('remember_email', $email, $cookie_time, '/', '', false, true);
            } else {
                // Clear remember me cookies if unchecked
                if (isset($_COOKIE['remember_email'])) {
                    setcookie('remember_email', '', time() - 3600, '/', '', false, true);
                }
            }

            header('location: ../app.php');
            exit;
        } else {
            $sm->setAttribute("error", 'Invalid username or password!');
        }
    } else {
        error_log('Auth Debug - User not found or query error for email: ' . $email);
        $sm->setAttribute("error", 'Invalid username or password!');
    }
} catch (Exception $e) {
    var_dump($e);
    die;
    // Log the error
    error_log('Auth database error: ' . $e->getMessage());
    $sm->setAttribute("error", 'An error occurred during authentication. Please try again later.');
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
