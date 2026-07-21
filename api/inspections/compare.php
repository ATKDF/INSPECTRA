<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/bootstrap.php';
Auth::requireLogin();

$bookingId = (int)($_GET['booking_id'] ?? 0);
if (!$bookingId) {
    json_response(['error' => 'Missing booking_id'], 400);
}

// Get booking and verify access
$booking = DB::fetchOne("
    SELECT b.*, u.first_name, u.last_name, u.email, u.phone, v.make, v.model, v.registration_number, v.year,
           v.color, v.category AS type, v.transmission, v.fuel_type
    FROM bookings b
    JOIN users u ON b.customer_id=u.id
    JOIN vehicles v ON b.vehicle_id=v.id
    WHERE b.id=?
", [$bookingId]);

if (!$booking) {
    json_response(['error' => 'Booking not found'], 404);
}

// Verify access: admin or booking owner
if (Auth::role() !== 'admin' && $booking['customer_id'] !== Auth::id()) {
    json_response(['error' => 'Unauthorized'], 403);
}

// Get pickup and dropoff inspections
$pickupInsp = DB::fetchOne("
    SELECT * FROM inspections 
    WHERE booking_id=? AND type='pickup'
", [$bookingId]);

$dropoffInsp = DB::fetchOne("
    SELECT * FROM inspections 
    WHERE booking_id=? AND type='return'
", [$bookingId]);

if (!$pickupInsp || !$dropoffInsp) {
    json_response(['error' => 'Both pickup and dropoff inspections required'], 400);
}

// Get photos
$pickupPhotos = DB::fetchAll("
    SELECT * FROM inspection_photos 
    WHERE inspection_id=?
", [$pickupInsp['id']]);

$dropoffPhotos = DB::fetchAll("
    SELECT * FROM inspection_photos 
    WHERE inspection_id=?
", [$dropoffInsp['id']]);

// Get damages
$pickupDamages = DB::fetchAll("
    SELECT dr.*, u.first_name, u.last_name FROM damage_reports dr
    LEFT JOIN users u ON dr.recorded_by=u.id
    WHERE dr.inspection_id=?
    ORDER BY dr.zone, dr.created_at
", [$pickupInsp['id']]);

$dropoffDamages = DB::fetchAll("
    SELECT dr.*, u.first_name, u.last_name FROM damage_reports dr
    LEFT JOIN users u ON dr.recorded_by=u.id
    WHERE dr.inspection_id=?
    ORDER BY dr.zone, dr.created_at
", [$dropoffInsp['id']]);

// Analysis: Compare damages
$pickupZones = array_column($pickupDamages, 'zone');
$dropoffZones = array_column($dropoffDamages, 'zone');

$newDamages = [];
$resolvedDamages = [];
$persistentDamages = [];

// Find new and resolved damages
foreach ($dropoffDamages as $d) {
    $found = false;
    foreach ($pickupDamages as $p) {
        if ($p['zone'] === $d['zone'] && $p['damage_type'] === $d['damage_type']) {
            $found = true;
            $persistentDamages[] = $d;
            break;
        }
    }
    if (!$found) {
        $newDamages[] = $d;
    }
}

// Find resolved damages
foreach ($pickupDamages as $p) {
    $found = false;
    foreach ($dropoffDamages as $d) {
        if ($d['zone'] === $p['zone']) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        $resolvedDamages[] = $p;
    }
}

// Calculate total deductions for new damages
$totalDeduction = array_sum(array_map(fn($d) => (float)($d['repair_cost'] ?? 0), $newDamages));

// Mileage change
$mileageIncrease = ($dropoffInsp['mileage_at_inspection'] ?? 0) - ($pickupInsp['mileage_at_inspection'] ?? 0);

// Fuel comparison
$fuelLevelPickup = $pickupInsp['fuel_level'] ?? 'unknown';
$fuelLevelDropoff = $dropoffInsp['fuel_level'] ?? 'unknown';

// Duration of rental
$pickupTime = strtotime($booking['pickup_date']);
$returnTime = strtotime($booking['return_date']);
$rentalDays = ceil(($returnTime - $pickupTime) / 86400);

json_response([
    'success' => true,
    'booking' => [
        'id' => $booking['id'],
        'booking_ref' => $booking['booking_ref'],
        'customer' => [
            'name' => $booking['first_name'] . ' ' . $booking['last_name'],
            'email' => $booking['email'],
            'phone' => $booking['phone'],
        ],
        'vehicle' => [
            'make' => $booking['make'],
            'model' => $booking['model'],
            'registration' => $booking['registration_number'],
            'year' => $booking['year'],
            'color' => $booking['color'],
            'vin' => null,
            'type' => $booking['type'],
            'transmission' => $booking['transmission'],
            'fuel_type' => $booking['fuel_type'],
        ],
        'dates' => [
            'pickup' => $booking['pickup_date'],
            'return' => $booking['return_date'],
            'rental_days' => $rentalDays,
        ],
        'total_amount' => (float)$booking['total_amount'],
        'status' => $booking['status'],
    ],
    'inspections' => [
        'pickup' => [
            'id' => $pickupInsp['id'],
            'status' => $pickupInsp['status'],
            'photos' => count($pickupPhotos),
            'damages' => count($pickupDamages),
            'condition' => $pickupInsp['overall_condition'],
            'fuel' => $pickupInsp['fuel_level'],
            'mileage' => $pickupInsp['mileage_at_inspection'],
            'notes' => $pickupInsp['agent_notes'],
            'completed_at' => $pickupInsp['completed_at'],
        ],
        'dropoff' => [
            'id' => $dropoffInsp['id'],
            'status' => $dropoffInsp['status'],
            'photos' => count($dropoffPhotos),
            'damages' => count($dropoffDamages),
            'condition' => $dropoffInsp['overall_condition'],
            'fuel' => $dropoffInsp['fuel_level'],
            'mileage' => $dropoffInsp['mileage_at_inspection'],
            'notes' => $dropoffInsp['agent_notes'],
            'completed_at' => $dropoffInsp['completed_at'],
        ],
    ],
    'comparison' => [
        'new_damages' => [
            'count' => count($newDamages),
            'details' => $newDamages,
            'total_deduction' => $totalDeduction,
        ],
        'resolved_damages' => [
            'count' => count($resolvedDamages),
            'details' => $resolvedDamages,
        ],
        'persistent_damages' => [
            'count' => count($persistentDamages),
            'details' => $persistentDamages,
        ],
        'mileage_increase' => $mileageIncrease,
        'fuel_used' => $fuelLevelPickup !== $fuelLevelDropoff ? 'Yes' : 'No',
        'fuel_pickup' => $fuelLevelPickup,
        'fuel_dropoff' => $fuelLevelDropoff,
    ],
    'assessment' => [
        'vehicle_state' => $dropoffInsp['overall_condition'],
        'condition_change' => compare_conditions(
            $pickupInsp['overall_condition'] ?? 'unknown',
            $dropoffInsp['overall_condition'] ?? 'unknown'
        ),
        'damage_severity' => calculate_damage_severity($newDamages),
        'total_deduction' => $totalDeduction,
        'recommendation' => generate_recommendation($newDamages, $totalDeduction),
    ],
]);

// Helper functions
function compare_conditions($pickup, $dropoff) {
    $order = ['poor' => 0, 'fair' => 1, 'good' => 2, 'excellent' => 3];
    $p = $order[$pickup] ?? 1;
    $d = $order[$dropoff] ?? 1;
    
    if ($d < $p) return 'Deteriorated';
    if ($d > $p) return 'Improved';
    return 'No change';
}

function calculate_damage_severity($damages) {
    if (empty($damages)) return 'None';
    
    $totalCost = array_sum(array_map(fn($d) => (float)($d['repair_cost'] ?? 0), $damages));
    $avgCost = $totalCost / count($damages);
    
    if ($avgCost < 100) return 'Minor';
    if ($avgCost < 500) return 'Moderate';
    return 'Severe';
}

function generate_recommendation($damages, $totalDeduction) {
    if (empty($damages)) {
        return 'Vehicle returned in good condition. No deductions recommended.';
    }
    
    if (count($damages) === 1 && $totalDeduction < 200) {
        return 'Small damage reported. Minor deduction recommended.';
    }
    
    if (count($damages) <= 3 && $totalDeduction < 500) {
        return 'Multiple minor damages. Moderate deduction recommended.';
    }
    
    return 'Multiple significant damages detected. Substantial deduction recommended. Recommend insurance claim review.';
}
?>
