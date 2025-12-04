<?php
// notifications.php - Notification management system

if (!defined('APP_INIT')) {
    die('Direct access not permitted');
}

/**
 * Notification Management Class
 */
class Notification {
    
    private static function getConnection(): mysqli {
        return Database::getConnection();
    }
    
    /**
     * Create a new notification
     */
    public static function create(
        int $userId, 
        string $title, 
        string $message, 
        string $type = 'info',
        ?string $relatedType = null,
        ?int $relatedId = null,
        bool $sendEmail = false
    ): bool {
        $conn = self::getConnection();
        
        $stmt = $conn->prepare(
            "INSERT INTO notifications (user_id, title, message, type, related_type, related_id) 
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('issssi', $userId, $title, $message, $type, $relatedType, $relatedId);
        $result = $stmt->execute();
        $stmt->close();
        
        // Optionally send email notification
        if ($result && $sendEmail) {
            self::sendEmailNotification($userId, $title, $message);
        }
        
        return $result;
    }
    
    /**
     * Create notifications for multiple users
     */
    public static function createForUsers(
        array $userIds,
        string $title,
        string $message,
        string $type = 'info',
        ?string $relatedType = null,
        ?int $relatedId = null
    ): int {
        $count = 0;
        foreach ($userIds as $userId) {
            if (self::create($userId, $title, $message, $type, $relatedType, $relatedId)) {
                $count++;
            }
        }
        return $count;
    }
    
    /**
     * Get unread count for user
     */
    public static function getUnreadCount(int $userId): int {
        $conn = self::getConnection();
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        return (int)($row['count'] ?? 0);
    }
    
    /**
     * Get notifications for user
     */
    public static function getForUser(int $userId, int $limit = 20, bool $unreadOnly = false): array {
        $conn = self::getConnection();
        
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $stmt->close();
        
        return $notifications;
    }
    
    /**
     * Mark notification as read
     */
    public static function markAsRead(int $notificationId, int $userId): bool {
        $conn = self::getConnection();
        $stmt = $conn->prepare(
            "UPDATE notifications SET is_read = 1, read_at = NOW() 
             WHERE id = ? AND user_id = ?"
        );
        $stmt->bind_param('ii', $notificationId, $userId);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Mark all notifications as read for user
     */
    public static function markAllAsRead(int $userId): bool {
        $conn = self::getConnection();
        $stmt = $conn->prepare(
            "UPDATE notifications SET is_read = 1, read_at = NOW() 
             WHERE user_id = ? AND is_read = 0"
        );
        $stmt->bind_param('i', $userId);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Delete notification
     */
    public static function delete(int $notificationId, int $userId): bool {
        $conn = self::getConnection();
        $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $notificationId, $userId);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Delete all read notifications for user
     */
    public static function deleteAllRead(int $userId): bool {
        $conn = self::getConnection();
        $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND is_read = 1");
        $stmt->bind_param('i', $userId);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Send email notification to user
     */
    private static function sendEmailNotification(int $userId, string $title, string $message): bool {
        $conn = self::getConnection();
        $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        if ($user) {
            require_once __DIR__ . '/email_config.php';
            return Mailer::sendNotification($user['email'], $title, $message);
        }
        
        return false;
    }
    
    /**
     * Notify event creator about status change
     */
    public static function notifyEventStatusChange(int $eventId, string $oldStatus, string $newStatus): void {
        $conn = self::getConnection();
        $stmt = $conn->prepare("SELECT created_by, title FROM events WHERE id = ?");
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $event = $result->fetch_assoc();
        $stmt->close();
        
        if ($event) {
            $statusMessages = [
                'approved' => '✅ Your event has been approved!',
                'rejected' => '❌ Your event has been rejected.',
                'pending' => '⏳ Your event is pending review.'
            ];
            
            $title = $statusMessages[$newStatus] ?? 'Event Status Updated';
            $message = "Your event '<strong>{$event['title']}</strong>' status has changed from <em>{$oldStatus}</em> to <em>{$newStatus}</em>.";
            
            $type = $newStatus === 'approved' ? 'success' : ($newStatus === 'rejected' ? 'error' : 'info');
            
            self::create(
                (int)$event['created_by'],
                $title,
                $message,
                $type,
                'event',
                $eventId,
                true // Send email
            );
        }
    }
    
    /**
     * Notify all students about new approved event
     */
    public static function notifyNewEvent(int $eventId): void {
        $conn = self::getConnection();
        
        // Get event details
        $stmt = $conn->prepare("SELECT title FROM events WHERE id = ?");
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $result = $stmt->get_result();
        $event = $result->fetch_assoc();
        $stmt->close();
        
        if ($event) {
            // Get all students
            $result = $conn->query("SELECT id FROM users WHERE role = 'student'");
            $studentIds = [];
            while ($row = $result->fetch_assoc()) {
                $studentIds[] = (int)$row['id'];
            }
            
            if (!empty($studentIds)) {
                $title = '🎉 New Event Available!';
                $message = "A new event '<strong>{$event['title']}</strong>' has been posted. Check it out!";
                
                self::createForUsers($studentIds, $title, $message, 'info', 'event', $eventId);
            }
        }
    }
    
    /**
     * Clean up old read notifications (older than 30 days)
     */
    public static function cleanup(): int {
        $conn = self::getConnection();
        $stmt = $conn->prepare(
            "DELETE FROM notifications 
             WHERE is_read = 1 AND read_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        return $affected;
    }
}