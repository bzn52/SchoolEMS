<?php
// notifications_ajax.php - AJAX handler for notification actions
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();

if (!defined('APP_INIT')) {
    define('APP_INIT', true);
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/notifications.php';

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = Auth::id();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$notifId = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['id']) ? (int)$_POST['id'] : 0);

$response = ['success' => false];

try {
    switch ($action) {
        case 'get_count':
            $count = Notification::getUnreadCount($userId);
            $response = [
                'success' => true,
                'count' => $count
            ];
            break;
            
        case 'get_recent':
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 5;
            $notifications = Notification::getForUser($userId, $limit);
            $response = [
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => Notification::getUnreadCount($userId)
            ];
            break;
            
        case 'mark_read':
            if ($notifId > 0) {
                $result = Notification::markAsRead($notifId, $userId);
                $response = [
                    'success' => $result,
                    'unread_count' => Notification::getUnreadCount($userId)
                ];
            }
            break;
            
        case 'mark_all_read':
            $result = Notification::markAllAsRead($userId);
            $response = [
                'success' => $result,
                'unread_count' => 0
            ];
            break;
            
        case 'delete':
            if ($notifId > 0) {
                $result = Notification::delete($notifId, $userId);
                $response = [
                    'success' => $result,
                    'unread_count' => Notification::getUnreadCount($userId)
                ];
            }
            break;
            
        case 'delete_all_read':
            $result = Notification::deleteAllRead($userId);
            $response = [
                'success' => $result,
                'unread_count' => Notification::getUnreadCount($userId)
            ];
            break;
            
        default:
            http_response_code(400);
            $response = ['success' => false, 'error' => 'Invalid action'];
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log('Notification AJAX error: ' . $e->getMessage());
    $response = ['success' => false, 'error' => 'Server error'];
}

echo json_encode($response);