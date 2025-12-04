<?php
// notifications_page.php - View all notifications
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/notifications.php';

Auth::requireLogin();

$userId = Auth::id();
$action = $_GET['action'] ?? '';
$notifId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle actions
if ($action === 'mark_read' && $notifId) {
    Notification::markAsRead($notifId, $userId);
    header('Location: notifications_page.php');
    exit;
}

if ($action === 'mark_all_read') {
    Notification::markAllAsRead($userId);
    header('Location: notifications_page.php');
    exit;
}

if ($action === 'delete' && $notifId) {
    Notification::delete($notifId, $userId);
    header('Location: notifications_page.php');
    exit;
}

if ($action === 'delete_all_read') {
    Notification::deleteAllRead($userId);
    header('Location: notifications_page.php');
    exit;
}

// Get notifications
$notifications = Notification::getForUser($userId, 50);
$unreadCount = Notification::getUnreadCount($userId);

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    return date('M j, Y', $time);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Notifications - Event Management</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .notification-item {
      display: flex;
      gap: 1rem;
      padding: 1.25rem;
      border-bottom: 1px solid var(--gray-200);
      transition: var(--transition);
      position: relative;
    }
    
    .notification-item:last-child {
      border-bottom: none;
    }
    
    .notification-item:hover {
      background: var(--gray-50);
    }
    
    .notification-item.unread {
      background: #eff6ff;
    }
    
    .notification-item.unread::before {
      content: '';
      position: absolute;
      left: 0;
      top: 0;
      bottom: 0;
      width: 4px;
      background: var(--primary);
    }
    
    .notification-icon {
      flex-shrink: 0;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.25rem;
    }
    
    .notification-icon.info {
      background: #dbeafe;
    }
    
    .notification-icon.success {
      background: #d1fae5;
    }
    
    .notification-icon.warning {
      background: #fef3c7;
    }
    
    .notification-icon.error {
      background: #fee2e2;
    }
    
    .notification-content {
      flex: 1;
    }
    
    .notification-title {
      font-weight: 600;
      color: var(--gray-900);
      margin-bottom: 0.25rem;
    }
    
    .notification-message {
      color: var(--gray-600);
      font-size: 0.875rem;
      line-height: 1.5;
    }
    
    .notification-time {
      font-size: 0.75rem;
      color: var(--gray-500);
      margin-top: 0.5rem;
    }
    
    .notification-actions {
      display: flex;
      gap: 0.5rem;
      align-items: flex-start;
    }
    
    .notification-actions a {
      font-size: 0.875rem;
      padding: 0.25rem 0.5rem;
      color: var(--gray-600);
      text-decoration: none;
      border-radius: 4px;
      transition: var(--transition);
    }
    
    .notification-actions a:hover {
      background: var(--gray-200);
      color: var(--gray-900);
    }
    
    .header-actions {
      display: flex;
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
  </style>
</head>
<body>
  <div class="page-wrapper">
    <header>
      <div class="header-content">
        <div class="header-left">
          <h1>🔔 Notifications</h1>
        </div>
        <div class="header-right">
          <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr(Auth::name(), 0, 1)) ?></div>
            <div>
              <div><?= e(Auth::name()) ?></div>
              <span class="user-role-badge"><?= e(Auth::role()) ?></span>
            </div>
          </div>
          <nav class="nav-links">
            <a href="<?= Auth::getDashboardUrl() ?>">Dashboard</a>
            <a href="settings.php">Settings</a>
            <a href="logout.php">Logout</a>
          </nav>
        </div>
      </div>
    </header>

    <main>
      <div class="container">
        <div class="card">
          <div class="card-header">
            <div>
              <h2 class="card-title">
                All Notifications
                <?php if ($unreadCount > 0): ?>
                  <span class="badge badge-pending"><?= $unreadCount ?> unread</span>
                <?php endif; ?>
              </h2>
            </div>
            
            <div class="header-actions">
              <?php if ($unreadCount > 0): ?>
                <a href="?action=mark_all_read" class="btn btn-sm btn-outline">Mark All Read</a>
              <?php endif; ?>
              <a href="?action=delete_all_read" class="btn btn-sm btn-secondary" onclick="return confirm('Delete all read notifications?')">Clear Read</a>
            </div>
          </div>

          <?php if (empty($notifications)): ?>
            <div class="empty-state">
              <div class="empty-state-icon">📭</div>
              <h3 class="empty-state-title">No notifications yet</h3>
              <p class="empty-state-text">You're all caught up!</p>
            </div>
          <?php else: ?>
            <?php foreach ($notifications as $notif): ?>
              <div class="notification-item <?= $notif['is_read'] ? '' : 'unread' ?>">
                <div class="notification-icon <?= e($notif['type']) ?>">
                  <?php
                    $icons = [
                      'info' => 'ℹ️',
                      'success' => '✅',
                      'warning' => '⚠️',
                      'error' => '❌'
                    ];
                    echo $icons[$notif['type']] ?? 'ℹ️';
                  ?>
                </div>
                
                <div class="notification-content">
                  <div class="notification-title"><?= e($notif['title']) ?></div>
                  <div class="notification-message"><?= $notif['message'] ?></div>
                  <div class="notification-time"><?= timeAgo($notif['created_at']) ?></div>
                </div>
                
                <div class="notification-actions">
                  <?php if (!$notif['is_read']): ?>
                    <a href="?action=mark_read&id=<?= $notif['id'] ?>">Mark Read</a>
                  <?php endif; ?>
                  <a href="?action=delete&id=<?= $notif['id'] ?>" onclick="return confirm('Delete this notification?')">Delete</a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>
</body>
</html>