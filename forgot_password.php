<?php
if (session_status() === PHP_SESSION_NONE) session_start();

define('APP_INIT', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/email_config.php';

// Redirect if already logged in
if (Auth::check()) {
    header('Location: ' . Auth::getDashboardUrl());
    exit;
}

$message = $_SESSION['reset_message'] ?? '';
$messageType = $_SESSION['reset_message_type'] ?? 'info';
$email = $_SESSION['reset_email'] ?? '';

unset($_SESSION['reset_message'], $_SESSION['reset_message_type'], $_SESSION['reset_email']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF validation
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security validation failed. Please try again.';
        $messageType = 'error';
    } else {
        // Rate limiting
        if (!RateLimit::check('password_reset', 3, 1800)) {
            $message = 'Too many password reset attempts. Please try again in 30 minutes.';
            $messageType = 'error';
        } else {
            $email = Input::email($_POST['email'] ?? '');
            
            if (!$email) {
                $message = 'Please enter a valid email address.';
                $messageType = 'error';
            } else {
                // Check if user exists
                $stmt = $conn->prepare("SELECT id, name, email FROM users WHERE email = ?");
                $stmt->bind_param('s', $email);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                $stmt->close();
                
                if ($user) {
                    // Generate reset token
                    $token = bin2hex(random_bytes(32));
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Store token in database
                    $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                    $stmt->bind_param('sss', $email, $token, $expiresAt);
                    
                    if ($stmt->execute()) {
                        // Send email
                        $resetUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/reset_password.php?token=' . $token;
                        
                        if (Mailer::sendPasswordReset($email, $user['name'], $token, $resetUrl)) {
                            $message = 'Password reset instructions have been sent to your email.';
                            $messageType = 'success';
                            $email = '';
                        } else {
                            $message = 'Failed to send email. Please try again later.';
                            $messageType = 'error';
                        }
                    } else {
                        $message = 'An error occurred. Please try again.';
                        $messageType = 'error';
                    }
                    $stmt->close();
                } else {
                    // Don't reveal if email exists (security best practice)
                    $message = 'If an account exists with that email, you will receive password reset instructions.';
                    $messageType = 'info';
                    $email = '';
                }
            }
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
  <title>Forgot Password - Event Management</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div class="page-wrapper">
    <main>
      <div class="container container-sm">
        <div class="form-box active">
          <h2>Reset Password</h2>
          
          <p class="text-center text-muted mb-2">
            Enter your email address and we'll send you instructions to reset your password.
          </p>
          
          <?php if ($message): ?>
            <div class="message message-<?= e($messageType) ?>">
              <?= e($message) ?>
            </div>
          <?php endif; ?>
          
          <form method="post" autocomplete="on">
            <?= CSRF::field() ?>
            
            <div class="form-group">
              <input 
                type="email" 
                name="email" 
                placeholder="Email Address" 
                value="<?= e($email) ?>"
                autocomplete="email"
                required
                autofocus
              >
            </div>
            
            <button type="submit" class="btn">Send Reset Link</button>
          </form>
          
          <p class="text-center mt-3">
            <a href="index.php" class="toggle-link">← Back to Login</a>
          </p>
        </div>
      </div>
    </main>
  </div>
</body>
</html>