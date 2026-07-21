<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireAdmin();
$vid = (int)($_GET['vehicle_id'] ?? 0);
$photos = DB::fetchAll("SELECT id, photo_url as url, is_primary FROM vehicle_photos WHERE vehicle_id=? ORDER BY is_primary DESC, sort_order ASC", [$vid]);
json_response($photos);
