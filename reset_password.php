<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Redirect if already logged in
if (Auth::check()) {
    header('Location: ' . Auth::getDashboardUrl());
    exit;
}

$token = $_GET['token'] ?? '';
$errors = [];
$success = false;

// Validate token
$validToken = false;
$email = '';

if ($token) {
    $stmt = $conn->prepare("SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW() AND used_at IS NULL ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $validToken = true;
        $email = $row['email'];
    } else {
        $errors[] = 'This password reset link is invalid or has expired.';
    }
    $stmt->close();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    
    // CSRF validation
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security validation failed. Please try again.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate password
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }
        
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }
        
        if (empty($errors)) {
            // Update password
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->bind_param('ss', $hash, $email);
            
            if ($stmt->execute()) {
                // Mark token as used
                $stmt2 = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE token = ?");
                $stmt2->bind_param('s', $token);
                $stmt2->execute();
                $stmt2->close();
                
                $success = true;
                $_SESSION['login_success'] = 'Your password has been reset successfully. Please login with your new password.';
            } else {
                $errors[] = 'Failed to update password. Please try again.';
            }
            $stmt->close();
        }
    }
}

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Reset Password - Event Management</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="page-wrapper">
    <main>
      <div class="container container-sm">
        <div class="form-box active">
          <h2>Set New Password</h2>
          
          <?php if ($success): ?>
            <div class="message message-success">
              Password reset successfully! Redirecting to login...
            </div>
            <script>
              setTimeout(function() {
                window.location.href = 'index.php';
              }, 2000);
            </script>
          <?php elseif (!$validToken): ?>
            <div class="message message-error">
              <?= implode('<br>', array_map('e', $errors)) ?>
            </div>
            <p class="text-center mt-3">
              <a href="forgot_password.php" class="btn btn-outline">Request New Link</a>
            </p>
          <?php else: ?>
            
            <?php if ($errors): ?>
              <div class="message message-error">
                <?= implode('<br>', array_map('e', $errors)) ?>
              </div>
            <?php endif; ?>
            
            <form method="post" autocomplete="off">
              <?= CSRF::field() ?>
              
              <div class="form-group">
                <label class="form-label">New Password</label>
                <div class="password-toggle">
                  <input 
                    type="password" 
                    name="password" 
                    id="password"
                    placeholder="Enter new password" 
                    autocomplete="new-password"
                    minlength="8"
                    required
                    autofocus
                  >
                  <button type="button" onclick="togglePassword('password')" tabindex="-1">👁️</button>
                </div>
              </div>
              
              <div class="password-requirements">
                <strong>Password must contain:</strong>
                <ul>
                  <li>At least 8 characters</li>
                  <li>One uppercase letter</li>
                  <li>One lowercase letter</li>
                  <li>One number</li>
                </ul>
              </div>
              
              <div class="form-group">
                <label class="form-label">Confirm Password</label>
                <div class="password-toggle">
                  <input 
                    type="password" 
                    name="confirm_password" 
                    id="confirm_password"
                    placeholder="Confirm new password" 
                    autocomplete="new-password"
                    required
                  >
                  <button type="button" onclick="togglePassword('confirm_password')" tabindex="-1">👁️</button>
                </div>
              </div>
              
              <button type="submit" class="btn">Reset Password</button>
            </form>
            
          <?php endif; ?>
          
          <p class="text-center mt-3">
            <a href="index.php" class="toggle-link">← Back to Login</a>
          </p>
        </div>
      </div>
    </main>
  </div>
  
  <script>
    function togglePassword(inputId) {
      var input = document.getElementById(inputId);
      if (input.type === "password") {
        input.type = "text";
      } else {
        input.type = "password";
      }
    }
  </script>
</body>
</html>