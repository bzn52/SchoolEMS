<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Redirect if already logged in
if (Auth::check()) {
    header('Location: ' . Auth::getDashboardUrl());
    exit;
}

/* ----------------------
   Registration Handler
   ---------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    
    // CSRF validation
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['register_error'] = 'Security validation failed. Please try again.';
        $_SESSION['active_form'] = 'register';
        header('Location: index.php');
        exit;
    }
    
    // Rate limiting
    if (!RateLimit::check('register', 5, 3600)) {
        $_SESSION['register_error'] = 'Too many registration attempts. Please try again later.';
        $_SESSION['active_form'] = 'register';
        header('Location: index.php');
        exit;
    }
    
    // Collect and sanitize input
    $name = Input::text($_POST['name'] ?? '', 100);
    $email = Input::email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = Role::normalize($_POST['role'] ?? '');
    
    $errors = [];
    
    // Validation
    if (strlen($name) < 2) {
        $errors[] = 'Name must be at least 2 characters long.';
    }
    
    if (!$email) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long.';
    }
    
    // Check password strength
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter.';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter.';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number.';
    }
    
    if (!$role || !in_array($role, Role::all(), true)) {
        $errors[] = 'Please select a valid role.';
    }
    
    // Check email uniqueness
    if (empty($errors) && $email) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $errors[] = 'This email is already registered.';
        }
        $stmt->close();
    }
    
    // Handle errors
    if (!empty($errors)) {
        $_SESSION['register_error'] = implode(' ', $errors);
        $_SESSION['active_form'] = 'register';
        $_SESSION['register_name'] = $name;
        $_SESSION['register_email'] = $email;
        $_SESSION['register_role'] = $role;
        header('Location: index.php');
        exit;
    }
    
    // Create user
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param('ssss', $name, $email, $hash, $role);
    
    if ($stmt->execute()) {
        $userId = $stmt->insert_id;
        RateLimit::reset('register');
        
        // Try to send welcome email (don't fail registration if email fails)
        try {
            if (file_exists(__DIR__ . '/email_config.php')) {
                require_once __DIR__ . '/email_config.php';
                @Mailer::sendWelcome($email, $name, $role);
            }
        } catch (Exception $e) {
            error_log("Failed to send welcome email: " . $e->getMessage());
        }
        
        // Create welcome notification
        try {
            if (file_exists(__DIR__ . '/notifications.php')) {
                require_once __DIR__ . '/notifications.php';
                Notification::create(
                    $userId,
                    'Welcome to Event Management System! 🎉',
                    'Your account has been created successfully. You can now start exploring events.',
                    'success'
                );
            }
        } catch (Exception $e) {
            error_log("Failed to create notification: " . $e->getMessage());
        }
        
        $_SESSION['register_success'] = 'Registration successful! Please login.';
        $_SESSION['active_form'] = 'login';
        $_SESSION['login_email'] = $email;
        $stmt->close();
        header('Location: index.php');
        exit;
    } else {
        error_log("Registration failed: " . $stmt->error);
        $_SESSION['register_error'] = 'Registration failed. Please try again.';
        $_SESSION['active_form'] = 'register';
        $stmt->close();
        header('Location: index.php');
        exit;
    }
}

/* ----------------------
   Login Handler
   ---------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    
    // CSRF validation
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $_SESSION['login_error'] = 'Security validation failed. Please try again.';
        $_SESSION['active_form'] = 'login';
        header('Location: index.php');
        exit;
    }
    
    // Rate limiting
    if (!RateLimit::check('login', 5, 900)) {
        $_SESSION['login_error'] = 'Too many login attempts. Please try again in 15 minutes.';
        $_SESSION['active_form'] = 'login';
        header('Location: index.php');
        exit;
    }
    
    $email = Input::email($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!$email || strlen($password) === 0) {
        $_SESSION['login_error'] = 'Email and password are required.';
        $_SESSION['active_form'] = 'login';
        $_SESSION['login_email'] = $email;
        header('Location: index.php');
        exit;
    }
    
    // Fetch user
    $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Verify credentials
    if (!$user || !Auth::verifyPassword($user, $password)) {
        $_SESSION['login_error'] = 'Invalid email or password.';
        $_SESSION['active_form'] = 'login';
        $_SESSION['login_email'] = $email;
        header('Location: index.php');
        exit;
    }
    
    // Check if password needs rehashing
    if (Auth::needsRehash($user['password'])) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $newHash, $user['id']);
        $stmt->execute();
        $stmt->close();
    }
    
    // Login successful
    Auth::login($user);
    
    // Redirect to intended URL or dashboard
    $redirect = $_SESSION['intended_url'] ?? Auth::getDashboardUrl();
    unset($_SESSION['intended_url']);
    
    header('Location: ' . $redirect);
    exit;
}

// Invalid request
header('Location: index.php');
exit;