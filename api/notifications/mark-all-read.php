<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireLogin();

$result = DB::update('notifications', 
    ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')], 
    'user_id=? AND is_read=0', 
    [Auth::id()]
);

json_response(['success' => true, 'updated' => $result]);
