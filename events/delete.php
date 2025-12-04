<?php
// events/delete.php - Delete event
require_once __DIR__ . '/events_common.php';

Auth::requireLogin();
$role = Auth::role();

if (!in_array($role, ['teacher', 'admin'])) {
    http_response_code(403);
    die("You don't have permission to delete events.");
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    http_response_code(400);
    die("Missing event ID");
}

// Fetch event details
$stmt = $conn->prepare("SELECT image, created_by FROM events WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    die("Event not found");
}

// Permission check: teachers can only delete their own events
$userId = Auth::id();
if ($role === 'teacher' && (int)($row['created_by'] ?? 0) !== $userId) {
    http_response_code(403);
    die("You can only delete your own events.");
}

// Delete from database
$stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    // Delete image file if exists
    if (!empty($row['image'])) {
        $imagePath = EVENTS_UPLOADS_DIR . '/' . $row['image'];
        if (file_exists($imagePath)) {
            @unlink($imagePath);
        }
    }
    
    $stmt->close();
    
    // Redirect based on role
    if ($role === 'admin') {
        header('Location: ../dashboard_admin.php');
    } else {
        header('Location: ../dashboard_teacher.php');
    }
    exit;
} else {
    http_response_code(500);
    die("Failed to delete event: " . $conn->error);
}