<?php
require_once __DIR__ . '/events_common.php';

Auth::requireLogin();
Auth::requireRole('admin');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = $_GET['action'] ?? '';

if (!$id || !in_array($action, ['approve', 'reject'], true)) {
    http_response_code(400);
    die("Invalid request");
}

// Get old status for notification
$stmt = $conn->prepare("SELECT status FROM events WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$event = $result->fetch_assoc();
$stmt->close();

if (!$event) {
    http_response_code(404);
    die("Event not found");
}

$oldStatus = $event['status'];
$newStatus = $action === 'approve' ? 'approved' : 'rejected';

// Update status
$statusCol = EVENTS_STATUS_COL;
$stmt = $conn->prepare("UPDATE events SET {$statusCol} = ?, approved_at = NOW(), approved_by = ? WHERE id = ?");
$approvedBy = Auth::id();
$stmt->bind_param('sii', $newStatus, $approvedBy, $id);

if ($stmt->execute()) {
    $stmt->close();
    
    // Send notification about status change
    require_once __DIR__ . '/../notifications.php';
    Notification::notifyEventStatusChange($id, $oldStatus, $newStatus);
    
    // If approved, notify all students
    if ($newStatus === 'approved') {
        Notification::notifyNewEvent($id);
    }
    
    // Redirect back
    if (isset($_GET['return']) && $_GET['return'] === 'view') {
        header('Location: view.php?id=' . $id);
    } else {
        header('Location: ../dashboard_admin.php');
    }
    exit;
} else {
    http_response_code(500);
    die("Failed to update status: " . $conn->error);
}