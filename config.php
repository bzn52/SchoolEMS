<?php
// config.php - Enhanced database configuration
if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}

// Error handling (development vs production)
$isDevelopment = ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1');

if ($isDevelopment) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/logs/error.log');
}

// Database configuration (consider using .env file in production)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1:3306'); // Change 3306 to 3307 if MySQL uses different port
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'users_db');
define('DB_CHARSET', 'utf8mb4');

// Application paths
define('APP_ROOT', __DIR__);
define('UPLOADS_DIR', APP_ROOT . '/uploads');
define('LOGS_DIR', APP_ROOT . '/logs');

// Create necessary directories
foreach ([UPLOADS_DIR, LOGS_DIR] as $dir) {
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0755, true)) {
            die("Failed to create directory: $dir");
        }
    }
}

// Database connection with error handling
class Database {
    private static ?mysqli $connection = null;
    
    public static function getConnection(): mysqli {
        if (self::$connection === null) {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            
            try {
                self::$connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                self::$connection->set_charset(DB_CHARSET);
                
                // Set strict mode for better data integrity
                self::$connection->query("SET SESSION sql_mode='STRICT_ALL_TABLES'");
                
            } catch (mysqli_sql_exception $e) {
                error_log("Database connection failed: " . $e->getMessage());
                http_response_code(503);
                die("Database connection unavailable. Please try again later.");
            }
        }
        
        return self::$connection;
    }
    
    public static function close(): void {
        if (self::$connection !== null) {
            self::$connection->close();
            self::$connection = null;
        }
    }
}

// Initialize connection
$conn = Database::getConnection();

// Register shutdown function to close connection
register_shutdown_function(function() {
    Database::close();
});

// Timezone
date_default_timezone_set('UTC');

// Include security config AFTER defining APP_INIT but BEFORE session_start()
require_once __DIR__ . '/security_config.php';