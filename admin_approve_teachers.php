<?php
if (session_status() === PHP_SESSION_NONE) session_start();

define('APP_INIT', true);
require_once 'config.php';
require_once 'auth.php';

Auth::requireLogin();
Auth::requireRole('admin');

function e($s) { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

$adminId = Auth::id();
$message = '';
$messageType = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
        $message = 'Security validation failed.';
        $messageType = 'error';
    } else {
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $action = $_POST['action'] ?? '';
        
        if ($teacherId && in_array($action, ['approve', 'reject'])) {
            // Get teacher details
            $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ? AND role = 'teacher'");
            $stmt->bind_param('i', $teacherId);
            $stmt->execute();
            $teacher = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($teacher) {
                if ($action === 'approve') {
                    // Approve teacher
                    $stmt = $conn->prepare("UPDATE users 
                                          SET approved = 1, approved_by = ?, approved_at = NOW() 
                                          WHERE id = ?");
                    $stmt->bind_param('ii', $adminId, $teacherId);
                    
                    if ($stmt->execute()) {
                        $message = "Teacher account '{$teacher['name']}' has been approved successfully!";
                        $messageType = 'success';
                        
                        // Send notification to teacher
                        if (file_exists(__DIR__ . '/notifications.php')) {
                            require_once __DIR__ . '/notifications.php';
                            Notification::create(
                                $teacherId,
                                'Account Approved!',
                                'Your teacher account has been approved by the administrator. You can now login and start creating events.',
                                'success'
                            );
                        }
                        
                        // Send approval email
                        if (file_exists(__DIR__ . '/email_config.php')) {
                            require_once __DIR__ . '/email_config.php';
                            @Mailer::sendWelcome($teacher['email'], $teacher['name'], 'teacher');
                        }
                    }
                    $stmt->close();
                    
                } else {
                    // Reject teacher (delete account)
                    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->bind_param('i', $teacherId);
                    
                    if ($stmt->execute()) {
                        $message = "Teacher account '{$teacher['name']}' has been rejected and removed.";
                        $messageType = 'success';
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Fetch pending teachers
$pendingTeachers = $conn->query("SELECT id, name, email, created_at 
                                  FROM users 
                                  WHERE role = 'teacher' AND approved = 0 
                                  ORDER BY created_at DESC");

// Fetch approved teachers
$approvedTeachers = $conn->query("SELECT u.id, u.name, u.email, u.approved_at, 
                                   a.name as approved_by_name
                                   FROM users u
                                   LEFT JOIN users a ON u.approved_by = a.id
                                   WHERE u.role = 'teacher' AND u.approved = 1 
                                   ORDER BY u.approved_at DESC 
                                   LIMIT 10");

$role = Auth::role();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Approve Teachers - Admin Panel</title>
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
  <style>
    .approval-card {
      background: var(--bg-primary);
      border: 2px solid var(--border-light);
      border-radius: var(--radius-lg);
      padding: 1.5rem;
      margin-bottom: 1rem;
      transition: var(--transition);
    }
    .approval-card:hover {
      border-color: var(--primary);
      box-shadow: var(--shadow-md);
    }
    .approval-actions {
      display: flex;
      gap: 1rem;
      margin-top: 1rem;
    }
    .pending-badge {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      background: var(--warning);
      color: white;
      border-radius: var(--radius-sm);
      font-size: 0.75rem;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <div class="page-wrapper">
    <header>
      <div class="header-content">
        <div class="header-left">
          <h1><i class="fas fa-chalkboard-teacher"></i> Approve Teacher Accounts</h1>
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
            <a href="dashboard_admin.php">← Back to Dashboard</a>
            <a href="logout.php" style="color: var(--error);"><i class="fas fa-sign-out-alt"></i> Logout</a>
          </nav>
        </div>
      </div>
    </header>

    <main>
      <div class="container container-lg">
        
        <?php if ($message): ?>
          <div class="message message-<?= e($messageType) ?>">
            <?= e($message) ?>
          </div>
        <?php endif; ?>

        <!-- Pending Teachers -->
        <div class="card">
          <div class="card-header">
            <h2 class="card-title">
              Pending Teacher Approvals
              <?php if ($pendingTeachers && $pendingTeachers->num_rows > 0): ?>
                <span class="pending-badge"><?= $pendingTeachers->num_rows ?> Pending</span>
              <?php endif; ?>
            </h2>
          </div>

          <?php if (!$pendingTeachers || $pendingTeachers->num_rows === 0): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><i class="fas fa-check-circle"></i></div>
              <h3 class="empty-state-title">No Pending Approvals</h3>
              <p class="empty-state-text">All teacher registrations have been reviewed!</p>
            </div>
          <?php else: ?>
            <?php while ($teacher = $pendingTeachers->fetch_assoc()): ?>
              <div class="approval-card">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                  <div>
                    <h3 style="margin: 0 0 0.5rem 0; font-size: 1.25rem; color: var(--primary);">
                      <?= e($teacher['name']) ?>
                    </h3>
                    <p style="color: var(--text-secondary); margin: 0.25rem 0;">
                      📧 <?= e($teacher['email']) ?>
                    </p>
                    <p style="color: var(--text-muted); font-size: 0.875rem; margin: 0.25rem 0;">
                      🕐 Registered: <?= date('F j, Y \a\t g:i A', strtotime($teacher['created_at'])) ?>
                    </p>
                  </div>
                  <span class="badge badge-pending">Pending Review</span>
                </div>

                <form method="post" class="approval-actions">
                  <?= CSRF::field() ?>
                  <input type="hidden" name="teacher_id" value="<?= (int)$teacher['id'] ?>">
                  
                  <button type="submit" name="action" value="approve" class="btn btn-sm btn-success" 
                          onclick="return confirm('Approve this teacher account?')">
                    <i class="fas fa-check-circle"></i> Approve
                  </button>
                  
                  <button type="submit" name="action" value="reject" class="btn btn-sm btn-danger" 
                          onclick="return confirm('Reject and delete this teacher account? This cannot be undone.')">
                    <i class="fas fa-times-circle"></i> Reject
                  </button>
                </form>
              </div>
            <?php endwhile; ?>
          <?php endif; ?>
        </div>

        <!-- Recently Approved Teachers -->
        <div class="card">
          <div class="card-header">
            <h2 class="card-title">Recently Approved Teachers</h2>
          </div>

          <?php if (!$approvedTeachers || $approvedTeachers->num_rows === 0): ?>
            <div class="empty-state">
              <div class="empty-state-icon"><i class="fas fa-chalkboard-teacher"></i></div>
              <h3 class="empty-state-title">No Approved Teachers Yet</h3>
              <p class="empty-state-text">Approved teachers will appear here</p>
            </div>
          <?php else: ?>
            <div class="table-container">
              <table>
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Approved By</th>
                    <th>Approved At</th>
                  </tr>
                </thead>
                <tbody>
                  <?php while ($teacher = $approvedTeachers->fetch_assoc()): ?>
                    <tr>
                      <td><strong><?= e($teacher['name']) ?></strong></td>
                      <td><?= e($teacher['email']) ?></td>
                      <td><?= e($teacher['approved_by_name'] ?? 'N/A') ?></td>
                      <td><?= $teacher['approved_at'] ? date('M j, Y g:i A', strtotime($teacher['approved_at'])) : 'N/A' ?></td>
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