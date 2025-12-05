<?php

if (!defined('APP_INIT')) {
    die('Direct access not permitted');
}

// Email Configuration
// Set to false to disable emails in local development
define('MAIL_ENABLED', false); // Change to true only if you configure SMTP below

define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.gmail.com');
define('MAIL_PORT', getenv('MAIL_PORT') ?: 587);
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: 'your-email@gmail.com');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: 'your-app-password');
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls'); 
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@schoolems.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'Event Management System');

/**
 * Simple Mailer Class - Works without mail server for local development
 */
class Mailer {
    private static $fromEmail = MAIL_FROM_ADDRESS;
    private static $fromName = MAIL_FROM_NAME;
    
    /**
     * Send an email (logs to file if MAIL_ENABLED is false)
     */
    public static function send(string $to, string $subject, string $htmlBody, string $textBody = ''): bool {
        // If emails disabled, just log and return success
        if (!MAIL_ENABLED) {
            return self::logEmail($to, $subject, $htmlBody);
        }
        
        $headers = self::buildHeaders();
        
        // If text body not provided, strip HTML from html body
        if (empty($textBody)) {
            $textBody = strip_tags($htmlBody);
        }
        
        // Build multipart message
        $boundary = md5(time());
        
        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $textBody . "\r\n\r\n";
        
        $message .= "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $htmlBody . "\r\n\r\n";
        
        $message .= "--{$boundary}--";
        
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        
        try {
            $result = @mail($to, $subject, $message, $headers);
            
            if (!$result) {
                error_log("Failed to send email to: {$to}, Subject: {$subject}");
                return self::logEmail($to, $subject, $htmlBody);
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return self::logEmail($to, $subject, $htmlBody);
        }
    }
    
    /**
     * Log email to file instead of sending (for local development)
     */
    private static function logEmail(string $to, string $subject, string $body): bool {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/emails.log';
        $timestamp = date('Y-m-d H:i:s');
        
        $logEntry = "\n" . str_repeat('=', 80) . "\n";
        $logEntry .= "EMAIL LOG - {$timestamp}\n";
        $logEntry .= str_repeat('=', 80) . "\n";
        $logEntry .= "TO: {$to}\n";
        $logEntry .= "SUBJECT: {$subject}\n";
        $logEntry .= "BODY:\n{$body}\n";
        $logEntry .= str_repeat('=', 80) . "\n";
        
        $result = @file_put_contents($logFile, $logEntry, FILE_APPEND);
        
        // Also log to PHP error log for visibility
        error_log("Email logged (not sent - MAIL_ENABLED=false): To={$to}, Subject={$subject}");
        
        return $result !== false;
    }
    
    /**
     * Build email headers
     */
    private static function buildHeaders(): string {
        $headers = "From: " . self::$fromName . " <" . self::$fromEmail . ">\r\n";
        $headers .= "Reply-To: " . self::$fromEmail . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        
        return $headers;
    }
    
    /**
     * Send password reset email
     */
    public static function sendPasswordReset(string $to, string $name, string $token, string $resetUrl): bool {
        $subject = "Password Reset Request";
        
        $htmlBody = self::getPasswordResetTemplate($name, $resetUrl);
        $textBody = "Hi {$name},\n\n"
                  . "You requested to reset your password. Click the link below to reset it:\n\n"
                  . "{$resetUrl}\n\n"
                  . "This link will expire in 1 hour.\n\n"
                  . "If you didn't request this, please ignore this email.\n\n"
                  . "Thanks,\n"
                  . self::$fromName;
        
        return self::send($to, $subject, $htmlBody, $textBody);
    }
    
    /**
     * Send welcome email
     */
    public static function sendWelcome(string $to, string $name, string $role): bool {
        $subject = "Welcome to Event Management System";
        
        $htmlBody = self::getWelcomeTemplate($name, $role);
        $textBody = "Hi {$name},\n\n"
                  . "Welcome to Event Management System!\n\n"
                  . "Your account has been created successfully as a {$role}.\n\n"
                  . "You can now log in and start using the platform.\n\n"
                  . "Thanks,\n"
                  . self::$fromName;
        
        return self::send($to, $subject, $htmlBody, $textBody);
    }
    
    /**
     * Send notification email
     */
    public static function sendNotification(string $to, string $subject, string $message): bool {
        $htmlBody = self::getNotificationTemplate($subject, $message);
        $textBody = strip_tags($message);
        
        return self::send($to, $subject, $htmlBody, $textBody);
    }
    
    /**
     * Password reset email template
     */
    private static function getPasswordResetTemplate(string $name, string $resetUrl): string {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; }
        .button { display: inline-block; padding: 12px 30px; background: #06b6d4; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 14px; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Password Reset Request</h1>
        </div>
        <div class="content">
            <p>Hi {$name},</p>
            <p>You recently requested to reset your password. Click the button below to reset it:</p>
            <p style="text-align: center;">
                <a href="{$resetUrl}" class="button">Reset Password</a>
            </p>
            <p><strong>This link will expire in 1 hour.</strong></p>
            <p>If you didn't request a password reset, you can safely ignore this email.</p>
        </div>
        <div class="footer">
            <p>&copy; 2025 Event Management System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Welcome email template
     */
    private static function getWelcomeTemplate(string $name, string $role): string {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; }
        .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 14px; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome! 🎉</h1>
        </div>
        <div class="content">
            <p>Hi {$name},</p>
            <p>Welcome to Event Management System! Your account has been successfully created.</p>
            <p><strong>Account Type:</strong> {$role}</p>
            <p>You can now log in and start exploring all the features available to you.</p>
        </div>
        <div class="footer">
            <p>&copy; 2025 Event Management System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Generic notification template
     */
    private static function getNotificationTemplate(string $title, string $message): string {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; }
        .footer { text-align: center; margin-top: 30px; color: #6b7280; font-size: 14px; padding: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$title}</h1>
        </div>
        <div class="content">
            {$message}
        </div>
        <div class="footer">
            <p>&copy; 2025 Event Management System. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}