<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireLogin();

$data = json_decode(file_get_contents('php://input'), true);
$notificationId = (int)($data['notification_id'] ?? $data['id'] ?? 0);
$toggle = (bool)($data['toggle'] ?? false);

if (!$notificationId) {
    json_response(['success' => false, 'message' => 'Invalid notification ID'], 400);
}

// Get current read status
$notif = DB::fetchOne('SELECT is_read FROM notifications WHERE id=? AND user_id=?', [$notificationId, Auth::id()]);
if (!$notif) {
    json_response(['success' => false, 'message' => 'Notification not found'], 404);
}

if ($toggle) {
    // Toggle read status
    $newStatus = !$notif['is_read'];
    DB::update('notifications', 
        [
            'is_read' => $newStatus ? 1 : 0,
            'read_at' => $newStatus ? date('Y-m-d H:i:s') : null
        ], 
        'id=? AND user_id=?', 
        [$notificationId, Auth::id()]
    );
    json_response(['success' => true, 'is_read' => $newStatus]);
} else {
    // Mark as read
    DB::update('notifications', 
        ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')], 
        'id=? AND user_id=?', 
        [$notificationId, Auth::id()]
    );
    json_response(['success' => true, 'is_read' => true]);
}
