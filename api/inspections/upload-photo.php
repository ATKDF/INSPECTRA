<?php
// api/inspections/upload-photo.php
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireLogin();

if (Auth::role() !== 'admin') { json_response(['error'=>'Unauthorized'], 403); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['error'=>'Method not allowed'], 405); }

$inspId = (int)($_POST['inspection_id'] ?? 0);
$type   = $_POST['photo_type'] ?? '';
$validTypes = ['front','rear','left','right','interior','dashboard','other'];

if (!$inspId || !in_array($type, $validTypes)) { json_response(['error'=>'Invalid parameters'], 400); }

$inspection = DB::fetchOne("SELECT * FROM inspections WHERE id=?", [$inspId]);
if (!$inspection) { json_response(['error'=>'Inspection not found'], 404); }

if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    json_response(['error'=>'No photo uploaded'], 400);
}

$url = upload_file($_FILES['photo'], 'inspections');
if (!$url) { json_response(['error'=>'Upload failed. Check file type and size.'], 422); }

// Get geolocation from POST if sent
$lat = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
$lng = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

// Delete old photo of same type
DB::query("DELETE FROM inspection_photos WHERE inspection_id=? AND photo_type=?", [$inspId, $type]);

DB::insert('inspection_photos', [
    'inspection_id' => $inspId,
    'photo_type'    => $type,
    'photo_url'     => $url,
    'latitude'      => $lat,
    'longitude'     => $lng,
    'taken_at'      => date('Y-m-d H:i:s'),
    'device_info'   => json_encode(['user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '']),
]);

json_response(['success'=>true, 'url'=>$url]);
