<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireLogin();

if (Auth::role() !== 'admin') { redirect(APP_URL.'/customer/inspections.php?error='.urlencode('Unauthorized')); }

$damageId = (int)$_POST['damage_id'];
$inspId   = (int)$_POST['inspection_id'];

DB::query("DELETE FROM damage_reports WHERE id=? AND inspection_id=?", [$damageId, $inspId]);

redirect(APP_URL.'/admin/inspection-flow.php?id='.$inspId.'&success='.urlencode('Damage removed.'));
