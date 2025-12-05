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

// Handle messages
$errors = [
    'login' => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? '',
    'success' => $_SESSION['register_success'] ?? ''
];
$activeForm = $_SESSION['active_form'] ?? 'login';
$timeoutMsg = isset($_GET['timeout']) ? 'Your session has expired. Please login again.' : '';

// Preserve form data
$loginEmail = $_SESSION['login_email'] ?? '';
$registerName = $_SESSION['register_name'] ?? '';
$registerEmail = $_SESSION['register_email'] ?? '';
$registerRole = $_SESSION['register_role'] ?? '';

// Clear session messages
unset(
    $_SESSION['login_error'],
    $_SESSION['register_error'], 
    $_SESSION['register_success'],
    $_SESSION['active_form'],
    $_SESSION['login_email'],
    $_SESSION['register_name'],
    $_SESSION['register_email'],
    $_SESSION['register_role']
);

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Event Management System</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    .form-container { 
      display: none !important;
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    .form-container.active { 
      display: block !important;
      opacity: 1;
      animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .password-requirements {
      font-size: 0.875rem;
      color: var(--gray-600);
      background: var(--gray-50);
      border-radius: var(--radius);
      padding: 1rem;
      margin-top: 0.5rem;
    }
    .password-requirements ul {
      margin: 0.5rem 0 0 1.5rem;
      padding: 0;
    }
    .password-requirements li {
      margin: 0.25rem 0;
    }
    .password-toggle {
      position: relative;
    }
    .password-toggle input {
      padding-right: 3rem;
    }
    .password-toggle button {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      background: none !important;
      border: none;
      cursor: pointer;
      padding: 5px;
      color: var(--gray-500);
      width: auto !important;
      margin: 0;
      font-size: 1.25rem;
      box-shadow: none !important;
    }
    .password-toggle button:hover {
      color: var(--gray-700);
      background: none !important;
      transform: translateY(-50%) scale(1.1);
    }
    .password-toggle button::before {
      display: none !important;
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- LOGIN FORM -->
    <div class="form-box form-container <?= $activeForm === 'login' ? 'active' : '' ?>" id="login-container">
      <form action="login_register.php" method="post" autocomplete="on">
        <h2>Login</h2>
        
        <?php if ($timeoutMsg): ?>
          <div class="info-message"><?= e($timeoutMsg) ?></div>
        <?php endif; ?>
        
        <?php if ($errors['login']): ?>
          <div class="error-message"><?= e($errors['login']) ?></div>
        <?php endif; ?>
        
        <?php if ($errors['success']): ?>
          <div class="success-message"><?= e($errors['success']) ?></div>
        <?php endif; ?>
        
        <?= CSRF::field() ?>
        
        <input 
          type="email" 
          name="email" 
          placeholder="Email" 
          value="<?= e($loginEmail) ?>"
          autocomplete="email"
          required
        >
        
        <div class="password-toggle">
          <input 
            type="password" 
            name="password" 
            id="login-password"
            placeholder="Password" 
            autocomplete="current-password"
            required
          >
          <button type="button" class="toggle-password" data-target="login-password" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
        </div>
        
        <button type="submit" name="login">Login</button>
        
        <p style="text-align: center; margin-top: 1rem;">
          <a href="forgot_password.php" style="color: var(--primary); text-decoration: none;">Forgot Password?</a>
        </p>
        
        <p style="text-align: center;">
          Don't have an account? 
          <a href="#" class="show-register" style="color: var(--primary); text-decoration: underline; font-weight: 600;">Register here</a>
        </p>
      </form>
    </div>

    <!-- REGISTER FORM -->
    <div class="form-box form-container <?= $activeForm === 'register' ? 'active' : '' ?>" id="register-container">
      <form action="login_register.php" method="post" autocomplete="on">
        <h2>Register</h2>
        
        <?php if ($errors['register']): ?>
          <div class="error-message"><?= e($errors['register']) ?></div>
        <?php endif; ?>
        
        <?= CSRF::field() ?>
        
        <input 
          type="text" 
          name="name" 
          placeholder="Full Name" 
          value="<?= e($registerName) ?>"
          autocomplete="name"
          required
        >
        
        <input 
          type="email" 
          name="email" 
          placeholder="Email" 
          value="<?= e($registerEmail) ?>"
          autocomplete="email"
          required
        >
        
        <div class="password-toggle">
          <input 
            type="password" 
            name="password" 
            id="register-password"
            placeholder="Password" 
            autocomplete="new-password"
            minlength="8"
            required
          >
          <button type="button" class="toggle-password" data-target="register-password" tabindex="-1"><i class="fa-solid fa-eye"></i></button>
        </div>
        
        <div class="password-requirements">
          <strong>Password must contain:</strong>
          <ul>
            <li>At least 8 characters</li>
            <li>One uppercase letter (A-Z)</li>
            <li>One lowercase letter (a-z)</li>
            <li>One number (0-9)</li>
          </ul>
        </div>
        
        <select name="role" required>
          <option value="">-- Select Role --</option>
          <option value="student" <?= $registerRole === 'student' ? 'selected' : '' ?>>Student</option>
          <option value="teacher" <?= $registerRole === 'teacher' ? 'selected' : '' ?>>Teacher</option>
        </select>
        
        <button type="submit" name="register">Register</button>
        
        <p style="text-align: center;">
          Already have an account? 
          <a href="#" class="show-login" style="color: var(--primary); text-decoration: underline; font-weight: 600;">Login here</a>
        </p>
      </form>
    </div>
  </div>

  <script src="script.js"></script>
  <script>
    // Page-specific initialization
    document.addEventListener('DOMContentLoaded', function() {
      console.log('=== Page Loaded ===');
      
      const loginContainer = document.getElementById('login-container');
      const registerContainer = document.getElementById('register-container');
      const showRegisterLinks = document.querySelectorAll('.show-register');
      const showLoginLinks = document.querySelectorAll('.show-login');
      const togglePasswordButtons = document.querySelectorAll('.toggle-password');
      
      
      // Show register form
      showRegisterLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
          e.preventDefault();
          if (loginContainer && registerContainer) {
            loginContainer.classList.remove('active');
            registerContainer.classList.add('active');
          }
        });
      });
      
      // Show login form
      showLoginLinks.forEach(function(link) {
        link.addEventListener('click', function(e) {
          e.preventDefault();
          if (loginContainer && registerContainer) {
            registerContainer.classList.remove('active');
            loginContainer.classList.add('active');
          }
        });
      });
      
      // Toggle password visibility
      togglePasswordButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
          e.preventDefault();
          const targetId = button.getAttribute('data-target');
          const input = document.getElementById(targetId);
          if (input) {
            if (input.type === 'password') {
              input.type = 'text';
              console.log('Password visible for:', targetId);
            } else {
              input.type = 'password';
              console.log('Password hidden for:', targetId);
            }
          }
        });
      });
      
      // Auto-hide success messages
      setTimeout(function() {
        const msg = document.querySelector('.success-message');
        if (msg) {
          console.log('Auto-hiding success message');
          msg.style.transition = 'opacity 0.5s';
          msg.style.opacity = '0';
          setTimeout(function() { msg.remove(); }, 500);
        }
      }, 5000);
      
      console.log('=== Page Ready ===');
    });
  </script>
</body>
</html>