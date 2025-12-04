<?php
// notification_badge.php - Include this in header to show notification count

if (!defined('APP_INIT')) {
    // If loaded standalone, initialize properly
    define('APP_INIT', true);
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/auth.php';
}

require_once __DIR__ . '/notifications.php';

$userId = Auth::id();
$unreadCount = $userId ? Notification::getUnreadCount($userId) : 0;
?>

<style>
.notification-badge-wrapper {
    position: relative;
    display: inline-block;
}

.notification-bell {
    font-size: 1.5rem;
    cursor: pointer;
    transition: var(--transition);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem;
    border-radius: 50%;
}

.notification-bell:hover {
    background: var(--gray-100);
    transform: scale(1.1);
}

.notification-count {
    position: absolute;
    top: 0;
    right: 0;
    background: var(--error);
    color: var(--white);
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.15rem 0.4rem;
    border-radius: 999px;
    min-width: 18px;
    text-align: center;
    line-height: 1;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    margin-top: 0.5rem;
    width: 360px;
    max-height: 400px;
    overflow-y: auto;
    background: var(--white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-xl);
    border: 1px solid var(--gray-200);
    display: none;
    z-index: 1000;
}

.notification-dropdown.show {
    display: block;
    animation: slideDown 0.2s ease;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.notification-dropdown-header {
    padding: 1rem;
    border-bottom: 1px solid var(--gray-200);
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: sticky;
    top: 0;
    background: var(--white);
    z-index: 1;
}

.notification-dropdown-header h3 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
}

.notification-dropdown-item {
    padding: 1rem;
    border-bottom: 1px solid var(--gray-200);
    cursor: pointer;
    transition: var(--transition);
}

.notification-dropdown-item:hover {
    background: var(--gray-50);
}

.notification-dropdown-item.unread {
    background: #eff6ff;
}

.notification-dropdown-footer {
    padding: 0.75rem;
    text-align: center;
    border-top: 1px solid var(--gray-200);
    position: sticky;
    bottom: 0;
    background: var(--white);
}

.notification-dropdown-footer a {
    color: var(--primary);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.875rem;
}

.notification-dropdown-footer a:hover {
    text-decoration: underline;
}

.notification-mini-title {
    font-weight: 600;
    font-size: 0.875rem;
    margin-bottom: 0.25rem;
    color: var(--gray-900);
}

.notification-mini-message {
    font-size: 0.75rem;
    color: var(--gray-600);
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}

.notification-mini-time {
    font-size: 0.7rem;
    color: var(--gray-500);
    margin-top: 0.25rem;
}
</style>

<div class="notification-badge-wrapper">
    <a href="#" class="notification-bell" id="notification-bell" onclick="toggleNotifications(event)">
        🔔
        <?php if ($unreadCount > 0): ?>
            <span class="notification-count"><?= min($unreadCount, 99) ?></span>
        <?php endif; ?>
    </a>
    
    <div class="notification-dropdown" id="notification-dropdown">
        <div class="notification-dropdown-header">
            <h3>Notifications</h3>
            <?php if ($unreadCount > 0): ?>
                <a href="#" onclick="markAllRead(event)" style="font-size: 0.75rem; color: var(--primary);">Mark all read</a>
            <?php endif; ?>
        </div>
        
        <div id="notification-list">
            <?php
            $recentNotifs = Notification::getForUser($userId, 5);
            if (empty($recentNotifs)):
            ?>
                <div style="padding: 2rem; text-align: center; color: var(--gray-500);">
                    <div style="font-size: 2rem; margin-bottom: 0.5rem;">📭</div>
                    <div>No notifications</div>
                </div>
            <?php else: ?>
                <?php foreach ($recentNotifs as $notif): ?>
                    <div class="notification-dropdown-item <?= $notif['is_read'] ? '' : 'unread' ?>">
                        <div class="notification-mini-title"><?= htmlspecialchars($notif['title']) ?></div>
                        <div class="notification-mini-message"><?= strip_tags($notif['message']) ?></div>
                        <div class="notification-mini-time"><?= date('M j, g:i A', strtotime($notif['created_at'])) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="notification-dropdown-footer">
            <a href="notifications_page.php">View All Notifications</a>
        </div>
    </div>
</div>

<script>
function toggleNotifications(e) {
    e.preventDefault();
    const dropdown = document.getElementById('notification-dropdown');
    dropdown.classList.toggle('show');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    const wrapper = document.querySelector('.notification-badge-wrapper');
    const dropdown = document.getElementById('notification-dropdown');
    
    if (wrapper && !wrapper.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});

// Handle mark all as read
function markAllRead(e) {
    e.preventDefault();
    fetch('notifications_ajax.php?action=mark_all_read')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        })
        .catch(error => console.error('Error:', error));
}
</script>