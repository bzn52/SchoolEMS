<?php
// dashboard_admin.php - Enhanced admin dashboard
if (session_status() === PHP_SESSION_NONE) session_start();

define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();
Auth::requireRole('admin');

function e($s) { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

// Fetch all events
$sql = "SELECT e.id, e.title, e.description, e.image, e.status, e.created_at, u.name as creator_name 
        FROM events e 
        LEFT JOIN users u ON e.created_by = u.id 
        ORDER BY e.created_at DESC";
$result = $conn->query($sql);

// Get statistics
$stats = [
    'total' => $conn->query("SELECT COUNT(*) as count FROM events")->fetch_assoc()['count'],
    'pending' => $conn->query("SELECT COUNT(*) as count FROM events WHERE status='pending'")->fetch_assoc()['count'],
    'approved' => $conn->query("SELECT COUNT(*) as count FROM events WHERE status='approved'")->fetch_assoc()['count'],
    'rejected' => $conn->query("SELECT COUNT(*) as count FROM events WHERE status='rejected'")->fetch_assoc()['count'],
    'users' => $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'],
];

$role = Auth::role();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Dashboard - Events Management</title>
  <link rel="stylesheet" href="styles.css">
  <style>
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    .stat-card {
      background: var(--white);
      padding: 1.5rem;
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      border-left: 4px solid var(--primary);
      transition: var(--transition);
    }
    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-lg);
    }
    .stat-card.pending { border-left-color: var(--warning); }
    .stat-card.approved { border-left-color: var(--success); }
    .stat-card.rejected { border-left-color: var(--error); }
    .stat-card.users { border-left-color: #3b82f6; }
    .stat-number {
      font-size: 2.5rem;
      font-weight: 700;
      color: var(--gray-900);
      line-height: 1;
      margin-bottom: 0.5rem;
    }
    .stat-label {
      color: var(--gray-600);
      font-size: 0.875rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-weight: 600;
    }
    .table-container {
      overflow-x: auto;
    }
    .action-btn {
      padding: 0.375rem 0.75rem;
      font-size: 0.813rem;
      border-radius: 4px;
      text-decoration: none;
      display: inline-block;
      transition: var(--transition);
      border: none;
      cursor: pointer;
    }
    .action-btn:hover {
      transform: translateY(-1px);
    }
  </style>
</head>
<body>
  <div class="page-wrapper">
    <header>
      <div class="header-content">
        <div class="header-left">
          <h1>🛡️ Admin Dashboard</h1>
        </div>
        <div class="header-right">
          <div class="user-info">
            <div class="user-avatar"><?= strtoupper(substr(Auth::name(), 0, 1)) ?></div>
            <div>
              <div><?= e(Auth::name()) ?></div>
              <span class="user-role-badge badge-admin"><?= e($role) ?></span>
            </div>
          </div>
          <nav class="nav-links">
            <a href="events/create.php">➕ Create Event</a>
            <a href="dashboard_student.php">👁️ Student View</a>
            <?php if (file_exists(__DIR__ . '/notification_badge.php')): ?>
              <?php require_once __DIR__ . '/notification_badge.php'; ?>
            <?php endif; ?>
            <a href="settings.php">⚙️ Settings</a>
            <a href="logout.php" style="color: var(--error);">🚪 Logout</a>
          </nav>
        </div>
      </div>
    </header>

    <main>
      <div class="container container-lg">
        <div class="page-header">
          <h2 class="page-title">Event Management Dashboard</h2>
          <p class="page-subtitle">Oversee all events and user activities</p>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
          <div class="stat-card">
            <div class="stat-number"><?= $stats['total'] ?></div>
            <div class="stat-label">📊 Total Events</div>
          </div>
          <div class="stat-card pending">
            <div class="stat-number"><?= $stats['pending'] ?></div>
            <div class="stat-label">⏳ Pending Review</div>
          </div>
          <div class="stat-card approved">
            <div class="stat-number"><?= $stats['approved'] ?></div>
            <div class="stat-label">✅ Approved</div>
          </div>
          <div class="stat-card rejected">
            <div class="stat-number"><?= $stats['rejected'] ?></div>
            <div class="stat-label">❌ Rejected</div>
          </div>
          <div class="stat-card users">
            <div class="stat-number"><?= $stats['users'] ?></div>
            <div class="stat-label">👥 Total Users</div>
          </div>
        </div>

        <!-- Events Table -->
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">All Events</h3>
            <div style="display: flex; gap: 0.5rem;">
              <a href="events/create.php" class="btn btn-sm">➕ New Event</a>
            </div>
          </div>
          
          <?php if (!$result || $result->num_rows === 0): ?>
            <div class="empty-state">
              <div class="empty-state-icon">📭</div>
              <h3 class="empty-state-title">No events found</h3>
              <p class="empty-state-text">Events will appear here once created</p>
              <a href="events/create.php" class="btn btn-sm" style="margin-top: 1rem; width: auto; display: inline-block;">Create First Event</a>
            </div>
          <?php else: ?>
            <div class="table-container">
              <table>
                <thead>
                  <tr>
                    <th>Title</th>
                    <th>Creator</th>
                    <th>Created</th>
                    <th>Status</th>
                    <th style="text-align: center;">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($row = $result->fetch_assoc()): ?>
                  <tr>
                    <td>
                      <strong><?= e($row['title']) ?></strong>
                      <?php if ($row['image']): ?>
                        <br><small class="text-muted">📷 Has image</small>
                      <?php endif; ?>
                    </td>
                    <td><?= e($row['creator_name'] ?? 'Unknown') ?></td>
                    <td><?= date('M j, Y', strtotime($row['created_at'])) ?></td>
                    <td>
                      <span class="badge badge-<?= e($row['status']) ?>">
                        <?= e(ucfirst($row['status'])) ?>
                      </span>
                    </td>
                    <td style="text-align: center;">
                      <div style="display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap;">
                        <a href="events/view.php?id=<?= (int)$row['id'] ?>" class="action-btn" style="background: #e0e7ff; color: #4338ca;">👁️ View</a>
                        <a href="events/edit.php?id=<?= (int)$row['id'] ?>" class="action-btn" style="background: #dbeafe; color: #1e40af;">✏️ Edit</a>
                        <?php if ($row['status'] !== 'approved'): ?>
                          <a href="events/approve.php?id=<?= (int)$row['id'] ?>&action=approve" class="action-btn" style="background: #d1fae5; color: #065f46;">✓ Approve</a>
                        <?php endif; ?>
                        <?php if ($row['status'] !== 'rejected'): ?>
                          <a href="events/approve.php?id=<?= (int)$row['id'] ?>&action=reject" class="action-btn" style="background: #fef3c7; color: #92400e;">✗ Reject</a>
                        <?php endif; ?>
                        <a href="events/delete.php?id=<?= (int)$row['id'] ?>" class="action-btn" style="background: #fee2e2; color: #991b1b;" onclick="return confirm('Delete this event?')">🗑️ Delete</a>
                      </div>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>
</body>
</html>