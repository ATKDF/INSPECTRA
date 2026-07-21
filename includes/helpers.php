<?php
// =============================================
// INSPECTRA — Helper Functions (v2)
// =============================================

require_once __DIR__ . '/../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
function clean($value): string {
    if (is_array($value)) {
        return '';
    }
    return htmlspecialchars(trim(strip_tags((string) $value)), ENT_QUOTES, 'UTF-8');
}
function redirect(string $url): void { header('Location: ' . $url); exit; }
function json_response(array $data, int $code = 200): void {
    http_response_code($code); header('Content-Type: application/json');
    echo json_encode($data); exit;
}
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}
function format_currency(float $amount, string $symbol = '₵'): string { return $symbol . number_format($amount, 2); }
function format_date(string $date, string $format = 'M d, Y'): string { return date($format, strtotime($date)); }
function format_datetime(string $datetime, string $format = 'M d, Y H:i'): string { return date($format, strtotime($datetime)); }
function time_ago(string $datetime): string {
    $diff = (new DateTime())->diff(new DateTime($datetime));
    if ($diff->days > 30) return format_date($datetime);
    if ($diff->days > 0)  return $diff->days . 'd ago';
    if ($diff->h > 0)     return $diff->h . 'h ago';
    if ($diff->i > 0)     return $diff->i . 'm ago';
    return 'Just now';
}
function sanitize(string $str): string { return trim(strip_tags($str)); }
function upload_file(array $file, string $dir, array $allowedTypes = ['image/jpeg','image/png','image/webp','image/gif']): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > MAX_FILE_SIZE) return false;
    if (!in_array($file['type'], $allowedTypes)) return false;
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_', true) . '.' . strtolower($ext);
    $destDir = UPLOAD_PATH . $dir . '/';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $destDir . $filename)) return false;
    return UPLOAD_URL . $dir . '/' . $filename;
}

function send_notification_email(int $userId, string $type, string $title, string $message, array $data = []): bool {
    try {
        $user = DB::fetchOne('SELECT email, first_name, last_name FROM users WHERE id=?', [$userId]);
        if (!$user || !filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->Timeout    = 10; // Reduce timeout to 10 seconds
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($user['email'], $user['first_name'] . ' ' . $user['last_name']);
        $mail->Subject = '[' . ucfirst(str_replace('_', ' ', $type)) . '] ' . $title;
        
        // HTML Body
        $userName = $user['first_name'] . ' ' . $user['last_name'];
        $htmlBody = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px 8px 0 0; text-align: center; }
                .content { background: #f9fafb; padding: 30px; border: 1px solid #e5e7eb; border-radius: 0 0 8px 8px; }
                .content h2 { color: #1f2937; margin-top: 0; }
                .content p { color: #6b7280; line-height: 1.8; }
                .footer { font-size: 12px; color: #9ca3af; text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; }
                .badge { display: inline-block; background: #dbeafe; color: #1e40af; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
                .action-btn { display: inline-block; background: #667eea; color: white; padding: 10px 20px; text-decoration: none; border-radius: 6px; margin-top: 15px; }
                .action-btn:hover { background: #764ba2; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1 style='margin: 0;'>INSPECTRA</h1>
                    <p style='margin: 5px 0 0 0;'>Vehicle Rental & Inspection Platform</p>
                </div>
                <div class='content'>
                    <p>Hi {$userName},</p>
                    <h2>{$title}</h2>
                    <p style='white-space: pre-line;'>{$message}</p>";
        
        // Add action link based on notification type
        if (isset($data['booking_id'])) {
            $bookingUrl = APP_URL . '/customer/bookings.php?id=' . $data['booking_id'];
            $htmlBody .= "<a href='{$bookingUrl}' class='action-btn'>View Booking</a>";
        }
        if (isset($data['inspection_id'])) {
            $inspUrl = APP_URL . '/customer/inspections.php?id=' . $data['inspection_id'];
            $htmlBody .= "<a href='{$inspUrl}' class='action-btn'>View Inspection Report</a>";
        }
        if (isset($data['refund_id'])) {
            $refundUrl = APP_URL . '/customer/refunds.php?id=' . $data['refund_id'];
            $htmlBody .= "<a href='{$refundUrl}' class='action-btn'>View Refund Status</a>";
        }
        
        $htmlBody .= "
                    <div class='footer'>
                        <p><strong>Inspectra Rentals</strong><br>
                        This is an automated notification. Please do not reply to this email.<br>
                        <strong>Contact:</strong> " . get_setting('company_email', 'hello@inspectra.com') . "</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
        
        $mail->isHTML(true);
        $mail->Body = $htmlBody;
        
        // Plain text fallback
        $plainText = "Hi {$userName},\n\n{$title}\n\n{$message}\n\n";
        if (isset($data['booking_id'])) {
            $plainText .= "View your booking: " . APP_URL . '/customer/bookings.php?id=' . $data['booking_id'] . "\n";
        }
        $plainText .= "\nThis is an automated notification. Do not reply to this email.\nInspectra Rentals";
        $mail->AltBody = $plainText;
        
        return $mail->send();
    } catch (Exception $e) {
        // Log error silently to avoid exposing SMTP details
        error_log('Email notification failed for user ' . $userId . ': ' . $e->getMessage());
        return false;
    }
}

function create_notification(int $userId, string $type, string $title, string $message, array $data = []): void {
    // Store in-app notification immediately (fast)
    DB::insert('notifications', ['user_id'=>$userId,'type'=>$type,'title'=>$title,'message'=>$message,'data'=>json_encode($data),'created_at'=>date('Y-m-d H:i:s')]);
    
    // Queue email for asynchronous processing (does not block page load)
    queue_email($userId, $type, $title, $message, $data);
}

/**
 * Queue an email for background processing
 * This returns IMMEDIATELY after inserting to database
 * No SMTP connections or PHPMailer instantiation happens here
 */
function queue_email(int $userId, string $type, string $title, string $message, array $data = []): bool {
    try {
        // Insert into queue - this is fast (single DB insert)
        DB::insert('email_queue', [
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'data'    => json_encode($data),
            'status'  => 'pending',
            'attempt_count' => 0,
            'max_attempts' => 3,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Trigger background processing (completely non-blocking, returns immediately)
        trigger_background_processing();
        return true;
    } catch (Exception $e) {
        error_log('Failed to queue email: ' . $e->getMessage());
        return false;
    }
}

/**
 * Trigger background processing (completely non-blocking)
 * Uses PHP proc_open to spawn detached async process
 * Falls back to HTTP request with ultra-short timeout if proc_open unavailable
 */
function trigger_background_processing(): void {
    // Check if we should process (avoid too frequent calls)
    $lastRun = (int)get_setting('email_queue_last_run', '0');
    if (time() - $lastRun < 5) { // Process every 5 seconds max
        return;
    }
    
    // Get pending count
    $pendingCount = DB::fetchOne("SELECT COUNT(*) as cnt FROM email_queue WHERE status='pending'", [])['cnt'] ?? 0;
    if ($pendingCount === 0) {
        return;
    }
    
    // Update last run time
    set_setting('email_queue_last_run', (string)time());
    
    // Try proc_open method (preferred - completely detached)
    if (function_exists('proc_open') && PHP_OS_FAMILY !== 'Windows') {
        $processWorker = dirname(dirname(__FILE__)) . '/api/workers/process-email-queue.php';
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w']   // stderr
        ];
        
        $process = @proc_open(
            'php ' . escapeshellarg($processWorker),
            $descriptors,
            $pipes,
            dirname(dirname(__FILE__)),
            ['PATH' => $_ENV['PATH'] ?? getenv('PATH')]
        );
        
        if (is_resource($process)) {
            // Close pipes immediately
            @fclose($pipes[0]);
            @fclose($pipes[1]);
            @fclose($pipes[2]);
            
            // Detach process (don't wait for it)
            @proc_terminate($process, 0);
            return;
        }
    }
    
    // Fallback: async HTTP request with minimal timeout
    $workerUrl = APP_URL . '/api/workers/process-email-queue.php';
    
    // Use blocking approach but in background with minimal connection
    try {
        $ch = curl_init($workerUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,     // Capture (and discard) the response instead of echoing it into the current output
            CURLOPT_TIMEOUT_MS     => 50,       // 50ms timeout - fire and forget
            CURLOPT_CONNECTTIMEOUT_MS => 50,   // 50ms to connect
            CURLOPT_NOSIGNAL       => true,     // Allow low timeout
            CURLOPT_HEADER         => false,    // Don't retrieve headers
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        
        // Execute without waiting
        @curl_exec($ch);
        @curl_close($ch);
    } catch (Exception $e) {
        // Silently fail - this is background processing
        error_log('Email queue trigger failed: ' . $e->getMessage());
    }
}
function get_unread_notifications(int $userId): array {
    return DB::fetchAll("SELECT * FROM notifications WHERE user_id=? AND is_read=0 ORDER BY created_at DESC LIMIT 20", [$userId]);
}
function get_exchange_rate(bool $forceRefresh = false): float {
    static $rate = null;
    static $lastFetch = 0;
    
    if ($rate !== null && !$forceRefresh && time() - $lastFetch < 3600) { // Cache 1 hour
        return $rate;
    }
    
    // Try settings first
    $settingRate = (float) get_setting('exchange_rate', '15.00');
    if ($settingRate > 0) {
        $rate = $settingRate; // GHS per USD
        $lastFetch = time();
        return $rate;
    }
    
    // Fetch from free API
    $ch = curl_init('https://api.exchangerate.host/latest?base=USD&symbols=GHS');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_USERAGENT      => 'Inspectra/2.0'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
            $data = json_decode($response, true);
        if (isset($data['rates']['GHS'])) {
            $rate = $data['rates']['GHS']; // GHS per USD
            $lastFetch = time();
            // Cache in settings for fallback
            DB::query("INSERT INTO settings (`key`,`value`) VALUES ('exchange_rate', ?) ON DUPLICATE KEY UPDATE `value` = ?", [$data['rates']['GHS'], $data['rates']['GHS']]);
            return $rate;
        }
    }
    
    // Fallback
    $rate = 15.00;
    return $rate;
}


function convert_to_usd(float $ghsAmount): float {
    return round($ghsAmount / get_exchange_rate(), 2);
}

function convert_usd_to_ghs(float $usdAmount): float {
    return round($usdAmount * get_exchange_rate(), 2);
}


function format_amount(float $amount, ?string $currency = null): string {
    $currency = $currency ?? get_setting('currency', 'GHS');
    $symbol = get_setting('currency_symbol', '₵');
    
    return match(strtoupper($currency)) {
        'USD' => '$' . number_format($amount, 2),
        'GHS' => $symbol . number_format($amount, 2),
        default => number_format($amount, 2)
    };
}

function get_setting(string $key, string $default = ''): string {
    $row = DB::fetchOne("SELECT `value` FROM settings WHERE `key`=?", [$key]);
    return $row ? $row['value'] : $default;
}

function set_setting(string $key, string $value): bool {
    try {
        DB::query("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = ?", [$key, $value, $value]);
        return true;
    } catch (Exception $e) {
        error_log('Failed to set setting: ' . $e->getMessage());
        return false;
    }
}
function status_badge(string $status): string {
    $map = ['booked'=>'badge-booked','paid'=>'badge-paid','active'=>'badge-active','returned'=>'badge-returned','completed'=>'badge-completed','cancelled'=>'badge-returned','available'=>'badge-active','rented'=>'badge-booked','maintenance'=>'badge-paid','pending'=>'badge-paid','approved'=>'badge-active','rejected'=>'badge-returned','processed'=>'badge-completed','partial'=>'badge-paid','partial_refund'=>'badge-paid'];
    return '<span class="badge '.($map[$status]??'badge-returned').'">'.ucfirst(str_replace('_',' ',$status)).'</span>';
}
function paginate(int $total, int $perPage, int $current): array {
    $pages = (int)ceil($total/$perPage);
    return ['total'=>$total,'per_page'=>$perPage,'current'=>$current,'pages'=>$pages,'has_prev'=>$current>1,'has_next'=>$current<$pages,'offset'=>($current-1)*$perPage];
}
function vehicle_emoji(string $category): string {
    return match($category) { 'suv'=>'🚙','luxury'=>'🏎️','van'=>'🚐','truck'=>'🚚','motorcycle'=>'🏍️',default=>'🚗' };
}

// =============================================
// AUTOMATED COMPARISON ENGINE
// =============================================

function compare_inspections(array $pickupInsp, array $dropoffInsp, array $pickupDamages, array $dropoffDamages): array {
    $comparison = [
        'mileage' => [
            'pickup' => $pickupInsp['mileage_at_inspection'] ?? 0,
            'dropoff' => $dropoffInsp['mileage_at_inspection'] ?? 0,
            'difference' => ($dropoffInsp['mileage_at_inspection'] ?? 0) - ($pickupInsp['mileage_at_inspection'] ?? 0),
            'status' => 'ok'
        ],
        'fuel' => [
            'pickup' => $pickupInsp['fuel_level'] ?? 'unknown',
            'dropoff' => $dropoffInsp['fuel_level'] ?? 'unknown',
            'status' => 'ok'
        ],
        'condition' => [
            'pickup' => $pickupInsp['overall_condition'] ?? 'unknown',
            'dropoff' => $dropoffInsp['overall_condition'] ?? 'unknown',
            'status' => 'ok'
        ],
        'damages' => [
            'pickup_count' => count($pickupDamages),
            'dropoff_count' => count($dropoffDamages),
            'new' => [],
            'resolved' => [],
            'persistent' => []
        ],
        'issues' => [],
        'total_charge' => 0.00
    ];

    // Mileage analysis
    if ($comparison['mileage']['difference'] > 0) {
        $comparison['mileage']['status'] = 'info';
        if ($comparison['mileage']['difference'] > 500) {
            $comparison['mileage']['status'] = 'warning';
            $comparison['issues'][] = [
                'type' => 'excess_mileage',
                'severity' => 'high',
                'title' => 'Excess Mileage',
                'description' => "Vehicle driven {$comparison['mileage']['difference']} km over expected usage",
                'charge' => calculate_mileage_charge($comparison['mileage']['difference'])
            ];
        }
    }

    // Fuel analysis
    $fuelLevels = ['empty' => 0, 'quarter' => 25, 'half' => 50, 'three_quarter' => 75, 'full' => 100];
    $pickupFuel = $fuelLevels[$pickupInsp['fuel_level']] ?? 100;
    $dropoffFuel = $fuelLevels[$dropoffInsp['fuel_level']] ?? 100;

    if ($dropoffFuel < $pickupFuel) {
        $fuelShortage = $pickupFuel - $dropoffFuel;
        $comparison['fuel']['status'] = $fuelShortage > 25 ? 'danger' : 'warning';
        $comparison['issues'][] = [
            'type' => 'fuel_shortage',
            'severity' => $fuelShortage > 25 ? 'high' : 'medium',
            'title' => 'Fuel Shortage',
            'description' => "Fuel level dropped by {$fuelShortage}% from pickup",
            'charge' => calculate_fuel_charge($fuelShortage)
        ];
    }

    // Damage analysis
    $pickupZones = array_column($pickupDamages, 'zone');
    $dropoffZones = array_column($dropoffDamages, 'zone');

    // Find new damages
    foreach ($dropoffDamages as $damage) {
        $found = false;
        foreach ($pickupDamages as $pickupDamage) {
            if ($pickupDamage['zone'] === $damage['zone'] &&
                $pickupDamage['damage_type'] === $damage['damage_type']) {
                $found = true;
                $comparison['damages']['persistent'][] = $damage;
                break;
            }
        }
        if (!$found) {
            $comparison['damages']['new'][] = $damage;
            $comparison['issues'][] = [
                'type' => 'new_damage',
                'severity' => $damage['severity'] === 'severe' ? 'high' : 'medium',
                'title' => 'New Damage: ' . ucfirst($damage['zone']),
                'description' => ucfirst($damage['damage_type']) . ' detected at ' . $damage['zone'],
                'charge' => (float)($damage['repair_cost'] ?? 0)
            ];
        }
    }

    // Find resolved damages
    foreach ($pickupDamages as $damage) {
        $found = false;
        foreach ($dropoffDamages as $dropoffDamage) {
            if ($dropoffDamage['zone'] === $damage['zone']) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $comparison['damages']['resolved'][] = $damage;
        }
    }

    // Condition change analysis
    $conditionOrder = ['poor' => 0, 'fair' => 1, 'good' => 2, 'excellent' => 3];
    $pickupCond = $conditionOrder[$pickupInsp['overall_condition']] ?? 1;
    $dropoffCond = $conditionOrder[$dropoffInsp['overall_condition']] ?? 1;

    if ($dropoffCond < $pickupCond) {
        $comparison['condition']['status'] = 'warning';
        $comparison['issues'][] = [
            'type' => 'condition_deterioration',
            'severity' => 'medium',
            'title' => 'Condition Deterioration',
            'description' => "Vehicle condition changed from {$pickupInsp['overall_condition']} to {$dropoffInsp['overall_condition']}",
            'charge' => 0.00
        ];
    }

    // Calculate total charges
    $comparison['total_charge'] = array_sum(array_column($comparison['issues'], 'charge'));

    return $comparison;
}

function calculate_mileage_charge(int $excessKm): float {
    // Charge ₵0.50 per excess km
    return $excessKm * 0.50;
}

function calculate_fuel_charge(int $shortagePercent): float {
    // Estimate fuel cost based on shortage
    // Assume average vehicle holds 50L, petrol at ₵10/L
    $estimatedLiters = (50 * $shortagePercent) / 100;
    return $estimatedLiters * 10.00;
}

/** Renders a small 3-step progress tracker (Filed → Under Review → Resolved/Escalated/Closed) for a dispute's status. */
function render_dispute_progress(string $status): void {
    $step = ['open' => 1, 'under_review' => 2, 'escalated' => 2, 'resolved' => 3, 'closed' => 3][$status] ?? 1;
    $finalLabel = $status === 'escalated' ? 'Escalated' : (in_array($status, ['resolved', 'closed'], true) ? ucfirst($status) : 'Resolution');
    $finalColor = $status === 'escalated' ? 'var(--danger)' : 'var(--success)';
    $labels = ['Filed', 'Under Review', $finalLabel];
    ?>
    <div style="display:flex;align-items:flex-start;margin:10px 0 4px">
        <?php foreach ($labels as $i => $label): $num = $i + 1; $done = $num <= $step; ?>
        <div style="flex:1;text-align:center;position:relative">
            <?php if ($i > 0): ?>
            <div style="position:absolute;top:10px;right:50%;width:100%;height:2px;background:<?= $num <= $step ? 'var(--accent)' : 'var(--border)' ?>;z-index:0"></div>
            <?php endif; ?>
            <div style="position:relative;z-index:1;width:20px;height:20px;border-radius:50%;margin:0 auto 4px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;background:<?= $done ? ($num === 3 && $step === 3 ? $finalColor : 'var(--accent)') : '#fff' ?>;border:2px solid <?= $done ? ($num === 3 && $step === 3 ? $finalColor : 'var(--accent)') : 'var(--border)' ?>;color:<?= $done ? '#fff' : 'var(--text-tertiary)' ?>"><?= $done ? '✓' : $num ?></div>
            <div style="font-size:9.5px;color:<?= $done ? 'var(--text)' : 'var(--text-tertiary)' ?>;font-weight:<?= $done ? 600 : 400 ?>"><?= e($label) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * Renders a dispute's full conversation thread (dispute_messages) plus a reply
 * form, as HTML. Shared by customer/inspection-comparison.php and
 * admin/inspection-comparison.php so both sides of a dispute see one identical
 * timeline instead of two independently-maintained views.
 */
function render_dispute_thread(array $dispute, string $viewerRole): void {
    $messages = DB::fetchAll("
        SELECT dm.*, u.first_name, u.last_name, u.role
        FROM dispute_messages dm JOIN users u ON dm.sender_id = u.id
        WHERE dm.dispute_id = ?
        ORDER BY dm.created_at ASC
    ", [$dispute['dispute_id']]);

    $isOpen = in_array($dispute['status'], ['open', 'under_review', 'escalated'], true);
    ?>
    <div class="dispute-thread" style="margin-top:10px;padding-top:10px;border-top:1px dashed var(--border)">
        <?php if (!empty($messages)): ?>
        <div style="display:flex;flex-direction:column;gap:8px;max-height:320px;overflow-y:auto;margin-bottom:10px;padding-right:4px">
            <?php foreach ($messages as $m): $fromAdmin = $m['role'] === 'admin'; ?>
            <div style="padding:9px 12px;border-radius:8px;background:<?= $fromAdmin ? 'rgba(59,110,248,.06)' : 'var(--surface2, #f8f9fc)' ?>;border:1px solid <?= $fromAdmin ? 'rgba(59,110,248,.15)' : 'var(--border)' ?>">
                <div style="display:flex;justify-content:space-between;gap:8px;margin-bottom:4px">
                    <strong style="font-size:11.5px;color:<?= $fromAdmin ? 'var(--accent)' : 'var(--text)' ?>">
                        <?= $fromAdmin ? '🛡️ ' : '👤 ' ?><?= e($m['first_name'] . ' ' . $m['last_name']) ?><?= $fromAdmin ? ' (Admin)' : '' ?>
                    </strong>
                    <span style="font-size:10.5px;color:var(--text-tertiary)"><?= time_ago($m['created_at']) ?></span>
                </div>
                <div style="font-size:12.5px;color:var(--text-secondary);white-space:pre-line"><?= e($m['message']) ?></div>
                <?php if (!empty($m['attachments'])): $photos = json_decode($m['attachments'], true) ?: []; ?>
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px">
                    <?php foreach ($photos as $photo): ?>
                    <a href="<?= e($photo) ?>" target="_blank"><img src="<?= e($photo) ?>" alt="Evidence" style="width:56px;height:56px;object-fit:cover;border-radius:6px;border:1px solid var(--border)"></a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($isOpen): ?>
        <form method="POST" action="<?= APP_URL ?>/api/disputes/message.php" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:flex-start">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="dispute_id" value="<?= $dispute['dispute_id'] ?>">
            <textarea class="form-input" name="message" rows="2" placeholder="<?= $viewerRole === 'admin' ? 'Reply to the customer…' : 'Add more detail or respond…' ?>" required style="flex:1;font-size:12.5px"></textarea>
            <div style="display:flex;flex-direction:column;gap:6px">
                <label class="btn btn-ghost" style="font-size:11px;cursor:pointer;text-align:center" title="Attach a photo">
                    📷<input type="file" name="evidence[]" accept="image/*" multiple style="display:none">
                </label>
                <button type="submit" class="btn btn-primary" style="font-size:11px">Send</button>
            </div>
        </form>
        <?php else: ?>
        <div style="font-size:11.5px;color:var(--text-tertiary);text-align:center;padding:6px">This dispute is <?= e(str_replace('_', ' ', $dispute['status'])) ?> and no longer accepts replies.</div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Single source of truth for whether a booking's pickup/return comparison
 * may be viewed (FR24): both inspections must exist and both must be
 * status='completed', which (per api/inspections/complete.php) already
 * guarantees both the agent's and the customer's signatures were recorded.
 */
function get_comparison_status(int $bookingId, ?int $ownerCustomerId = null): array {
    $sql = "SELECT b.*, u.first_name, u.last_name, u.email, u.phone, v.make, v.model, v.registration_number, v.year,
                   v.color, v.category AS type, v.transmission, v.fuel_type
            FROM bookings b
            JOIN users u ON b.customer_id=u.id
            JOIN vehicles v ON b.vehicle_id=v.id
            WHERE b.id=?" . ($ownerCustomerId !== null ? " AND b.customer_id=?" : "");
    $params = $ownerCustomerId !== null ? [$bookingId, $ownerCustomerId] : [$bookingId];
    $booking = DB::fetchOne($sql, $params);

    if (!$booking) {
        return ['ready' => false, 'booking' => null, 'pickup' => null, 'return' => null];
    }

    $pickup = DB::fetchOne("SELECT * FROM inspections WHERE booking_id=? AND type='pickup'", [$bookingId]);
    $return = DB::fetchOne("SELECT * FROM inspections WHERE booking_id=? AND type='return'", [$bookingId]);
    $ready = $pickup && $return && $pickup['status'] === 'completed' && $return['status'] === 'completed';

    return ['ready' => $ready, 'booking' => $booking, 'pickup' => $pickup, 'return' => $return];
}

/** Human-readable readiness state for a single pickup or return inspection. */
function inspection_readiness_label(?array $insp): string {
    if (!$insp) return 'Not started yet';
    if ($insp['status'] === 'completed') return 'Completed';

    $roles = array_column(DB::fetchAll("SELECT role FROM signatures WHERE inspection_id=?", [$insp['id']]), 'role');
    if (!in_array('agent', $roles, true)) return 'In progress — agent has not signed yet';
    if (!in_array('customer', $roles, true)) return "Awaiting the customer's signature";
    return 'Signed — awaiting final completion by the admin';
}

/**
 * The recorded decision (if any) on a booking's post-return charge — the
 * persisted counterpart to compare_inspections()'s on-the-fly total_charge.
 * Returns null when nothing has been decided yet. When status='disputed',
 * the linked disputes row is included under the 'dispute' key.
 */
function get_booking_charge_status(int $bookingId): ?array {
    $charge = DB::fetchOne("
        SELECT bc.*, u.first_name AS decided_by_first_name, u.last_name AS decided_by_last_name
        FROM booking_charges bc
        LEFT JOIN users u ON bc.decided_by = u.id
        WHERE bc.booking_id = ?
    ", [$bookingId]);
    if (!$charge) return null;

    if ($charge['dispute_id']) {
        $charge['dispute'] = DB::fetchOne("SELECT * FROM disputes WHERE dispute_id=?", [$charge['dispute_id']]);
    }
    return $charge;
}

/**
 * The ONLY two ways a booking's post-trip charge is truly, irreversibly settled:
 * the customer explicitly accepted it, or a linked dispute was formally brought
 * to a close (resolved/closed) by an admin. Everything else — approved,
 * adjusted, waived, or an open/under_review/escalated dispute — stays revisable
 * on both sides until one of those two things happens. Admin actions
 * (approve/adjust/waive) are proposals, not finality, on their own.
 */
function is_charge_finalized(?array $bookingCharge): bool {
    if (!$bookingCharge) return false;
    if ($bookingCharge['status'] === 'accepted') return true;
    if (!empty($bookingCharge['dispute']) && in_array($bookingCharge['dispute']['status'], ['resolved', 'closed'], true)) return true;
    return false;
}

/** True while a dispute exists on this charge and is still actively open (not yet resolved/closed). */
function has_open_dispute(?array $bookingCharge): bool {
    return $bookingCharge && !empty($bookingCharge['dispute'])
        && in_array($bookingCharge['dispute']['status'], ['open', 'under_review', 'escalated'], true);
}

/** Renders a row of 5 stars (filled up to the rounded rating, muted after) as inline HTML. */
function render_stars(float $rating, int $size = 16): string {
    $rounded = (int) round($rating);
    $html = '<span style="white-space:nowrap;font-size:' . $size . 'px;line-height:1">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= $i <= $rounded
            ? '<span style="color:#f59e0b">★</span>'
            : '<span style="color:var(--border,#d1d5db)">★</span>';
    }
    $html .= '</span>';
    return $html;
}

/** Aggregate published-review stats for a vehicle: overall + sub-rating averages and count. */
function get_vehicle_rating_summary(int $vehicleId): array {
    $row = DB::fetchOne("
        SELECT COUNT(*) AS cnt, AVG(rating) AS avg_rating,
               AVG(cleanliness) AS avg_cleanliness, AVG(service) AS avg_service, AVG(value) AS avg_value
        FROM reviews WHERE vehicle_id=? AND is_published=1
    ", [$vehicleId]);
    return [
        'avg'             => $row && $row['avg_rating'] ? round((float)$row['avg_rating'], 1) : 0.0,
        'count'           => $row ? (int)$row['cnt'] : 0,
        'avg_cleanliness' => $row && $row['avg_cleanliness'] !== null ? round((float)$row['avg_cleanliness'], 1) : null,
        'avg_service'     => $row && $row['avg_service'] !== null ? round((float)$row['avg_service'], 1) : null,
        'avg_value'       => $row && $row['avg_value'] !== null ? round((float)$row['avg_value'], 1) : null,
    ];
}

/**
 * The 16-zone damage-diagram coordinate map, shared by the damage-zone
 * visual overlay on the comparison pages. Same taxonomy/coordinates as the
 * inline $zones array in admin/inspection-flow.php and customer/inspection-flow.php.
 */
function damage_zone_map(): array {
    return [
        'hood'             => ['x'=>240,'y'=>82,'w'=>95,'h'=>60,'label'=>'Hood'],
        'trunk'            => ['x'=>65, 'y'=>82,'w'=>95,'h'=>60,'label'=>'Trunk'],
        'front_bumper'     => ['x'=>28, 'y'=>95,'w'=>38,'h'=>45,'label'=>'Frt Bumper'],
        'rear_bumper'      => ['x'=>334,'y'=>95,'w'=>38,'h'=>45,'label'=>'Rr Bumper'],
        'roof'             => ['x'=>110,'y'=>43,'w'=>180,'h'=>36,'label'=>'Roof'],
        'left_front_door'  => ['x'=>153,'y'=>82,'w'=>65,'h'=>82,'label'=>'L.Fr Door'],
        'left_rear_door'   => ['x'=>200,'y'=>82,'w'=>65,'h'=>82,'label'=>'L.Rr Door'],
        'right_front_door' => ['x'=>40, 'y'=>82,'w'=>65,'h'=>82,'label'=>'R.Fr Door'],
        'right_rear_door'  => ['x'=>90, 'y'=>82,'w'=>60,'h'=>82,'label'=>'R.Rr Door'],
        'windshield'       => ['x'=>122,'y'=>45,'w'=>62,'h'=>33,'label'=>'Windshield'],
        'rear_window'      => ['x'=>192,'y'=>45,'w'=>62,'h'=>33,'label'=>'Rr Window'],
        'front_left_wheel' => ['cx'=>95, 'cy'=>192,'r'=>25,'label'=>'FL Wheel'],
        'front_right_wheel'=> ['cx'=>305,'cy'=>192,'r'=>25,'label'=>'FR Wheel'],
        'undercarriage'    => ['x'=>130,'y'=>185,'w'=>140,'h'=>28,'label'=>'Under'],
        'left_mirror'      => ['x'=>155,'y'=>72, 'w'=>20,'h'=>14,'label'=>'L.Mirror'],
        'right_mirror'     => ['x'=>225,'y'=>72, 'w'=>20,'h'=>14,'label'=>'R.Mirror'],
    ];
}

function generate_comparison_report(array $booking, array $pickupInsp, array $dropoffInsp, array $comparison): string {
    $report = "INSPECTION COMPARISON REPORT\n";
    $report .= "==========================\n\n";
    $report .= "Booking: {$booking['booking_ref']}\n";
    $report .= "Vehicle: {$booking['make']} {$booking['model']} ({$booking['registration_number']})\n";
    $report .= "Customer: {$booking['first_name']} {$booking['last_name']}\n";
    $report .= "Period: " . format_date($booking['pickup_date']) . " to " . format_date($booking['return_date']) . "\n\n";

    $report .= "MILEAGE ANALYSIS\n";
    $report .= "----------------\n";
    $report .= "Pickup: " . number_format($comparison['mileage']['pickup']) . " km\n";
    $report .= "Drop-off: " . number_format($comparison['mileage']['dropoff']) . " km\n";
    $report .= "Difference: " . number_format($comparison['mileage']['difference']) . " km\n\n";

    $report .= "FUEL ANALYSIS\n";
    $report .= "-------------\n";
    $report .= "Pickup: " . ucfirst($comparison['fuel']['pickup']) . "\n";
    $report .= "Drop-off: " . ucfirst($comparison['fuel']['dropoff']) . "\n\n";

    $report .= "DAMAGE ANALYSIS\n";
    $report .= "---------------\n";
    $report .= "Pickup Damages: {$comparison['damages']['pickup_count']}\n";
    $report .= "Drop-off Damages: {$comparison['damages']['dropoff_count']}\n";
    $report .= "New Damages: " . count($comparison['damages']['new']) . "\n";
    $report .= "Resolved Damages: " . count($comparison['damages']['resolved']) . "\n";
    $report .= "Persistent Damages: " . count($comparison['damages']['persistent']) . "\n\n";

    if (!empty($comparison['issues'])) {
        $report .= "ISSUES DETECTED\n";
        $report .= "---------------\n";
        foreach ($comparison['issues'] as $issue) {
            $report .= "• {$issue['title']}: {$issue['description']}\n";
            if ($issue['charge'] > 0) {
                $report .= "  Charge: ₵" . number_format($issue['charge'], 2) . "\n";
            }
        }
        $report .= "\nTOTAL CHARGES: ₵" . number_format($comparison['total_charge'], 2) . "\n";
    } else {
        $report .= "No issues detected. Vehicle returned in good condition.\n";
    }

    return $report;
}

function calculate_booking_amounts(float $dailyRate, int $days): array {
    $taxRate           = (float) get_setting('tax_rate', '0');
    $serviceChargeRate = (float) get_setting('service_charge_rate', '0');
    $depositPct        = (float) get_setting('deposit_percentage', '50');
    $subtotal          = round($dailyRate * $days, 2);
    $taxAmount         = round($subtotal * ($taxRate / 100), 2);
    $serviceCharge     = round($subtotal * ($serviceChargeRate / 100), 2);
    $totalAmount       = round($subtotal + $taxAmount + $serviceCharge, 2);
    $initialDeposit    = round($totalAmount * ($depositPct / 100), 2);
    return compact('subtotal','taxAmount','serviceCharge','totalAmount','initialDeposit','depositPct','taxRate','serviceChargeRate');
}

function check_vehicle_availability(int $vehicleId, string $pickupDate, string $returnDate, int $excludeBookingId = 0): bool {
    $row = DB::fetchOne("SELECT COUNT(*) c FROM bookings WHERE vehicle_id=? AND status NOT IN ('cancelled') AND id!=? AND pickup_date<=? AND return_date>=?", [$vehicleId, $excludeBookingId, $returnDate, $pickupDate]);
    return (int)$row['c'] === 0;
}

function log_inspection_activity(int $inspId, int $userId, string $action, string $detail = '', ?float $lat = null, ?float $lng = null): void {
    try {
        DB::insert('inspection_activity', ['inspection_id'=>$inspId,'user_id'=>$userId,'action'=>$action,'detail'=>$detail,'latitude'=>$lat,'longitude'=>$lng,'ip_address'=>$_SERVER['REMOTE_ADDR']??'','created_at'=>date('Y-m-d H:i:s')]);
    } catch (Exception $e) { }
}

function is_inspection_locked(int $inspId): bool {
    $insp = DB::fetchOne("SELECT status FROM inspections WHERE id=?", [$inspId]);
    return $insp && $insp['status'] === 'completed';
}

function hash_content(string $content): string { return hash('sha256', $content); }

function log_audit(?int $userId, string $action, string $entityType, ?int $entityId = null, array $oldData = [], array $newData = []): void {
    try {
        DB::insert('audit_logs', [
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_data' => json_encode($oldData),
            'new_data' => json_encode($newData),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log('Audit log failed: ' . $e->getMessage());
    }
}
?>


