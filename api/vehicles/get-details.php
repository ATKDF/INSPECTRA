<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing vehicle ID']);
    exit;
}

$vid = (int)$_GET['id'];
$vehicle = DB::fetchOne("
    SELECT v.*, 
           (SELECT photo_url FROM vehicle_photos WHERE vehicle_id = v.id AND is_primary = 1 LIMIT 1) as primary_photo,
           AVG(r.rating) as avg_rating,
           COUNT(r.id) as review_count
    FROM vehicles v 
    LEFT JOIN ratings r ON v.id = r.vehicle_id 
    WHERE v.id = ? AND v.status = 'available'
    GROUP BY v.id
", [$vid]);

if (!$vehicle) {
    http_response_code(404);
    echo json_encode(['error' => 'Vehicle not found']);
    exit;
}

echo json_encode($vehicle);
?>

