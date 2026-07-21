<?php
/**
 * Background Email Queue Processor
 * Processes pending emails from email_queue table
 * Can be called via:
 * - Cron job: php /path/to/process-email-queue.php
 * - HTTP request: curl https://yoursite.com/api/workers/process-email-queue.php
 * 
 * This script runs independently and does not block the main application
 */

// Disable output buffering for async processing
if (ob_get_level()) ob_end_clean();

// Set no time limit for this background worker
set_time_limit(0);

// Reduce memory limit for this worker
ini_set('memory_limit', '64M');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/helpers.php';

// Prevent direct output
header('Content-Type: application/json');

// Return success immediately to allow HTTP request to close
echo json_encode(['status' => 'processing']);

// Prevent output buffering from delaying response
flush();
ob_flush();

// Detach from HTTP connection if possible
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    // For non-FastCGI setups, close the connection manually
    header('Connection: close');
    header('Content-Length: ' . strlen(json_encode(['status' => 'processing'])));
}

// Now process emails in the background
process_email_queue();

/**
 * Process pending emails from the queue
 */
function process_email_queue(): void {
    $batchSize = 10; // Process 10 emails at a time
    
    try {
        // Get pending emails
        $emails = DB::fetchAll(
            "SELECT id, user_id, type, title, message, data, attempt_count, max_attempts 
             FROM email_queue 
             WHERE status='pending' 
             ORDER BY created_at ASC 
             LIMIT ?",
            [$batchSize]
        );
        
        if (empty($emails)) {
            error_log('[Email Queue] No pending emails to process');
            return;
        }
        
        error_log('[Email Queue] Processing ' . count($emails) . ' emails');
        
        foreach ($emails as $email) {
            process_single_email($email);
        }
        
        error_log('[Email Queue] Batch completed');
    } catch (Exception $e) {
        error_log('[Email Queue] Error: ' . $e->getMessage());
    }
}

/**
 * Process a single email
 */
function process_single_email(array $email): void {
    try {
        // Mark as processing
        DB::update('email_queue', [
            'status' => 'processing'
        ], 'id=?', [$email['id']]);
        
        // Attempt to send email
        $data = json_decode($email['data'], true) ?? [];
        $success = send_notification_email($email['user_id'], $email['type'], $email['title'], $email['message'], $data);
        
        if ($success) {
            // Mark as sent
            DB::update('email_queue', [
                'status'       => 'sent',
                'processed_at' => date('Y-m-d H:i:s')
            ], 'id=?', [$email['id']]);
            
            error_log('[Email Queue] ✓ Email sent to user ' . $email['user_id'] . ' (type: ' . $email['type'] . ')');
        } else {
            // Increment attempt count
            $newAttempt = $email['attempt_count'] + 1;
            
            if ($newAttempt >= $email['max_attempts']) {
                // Mark as failed
                DB::update('email_queue', [
                    'status'          => 'failed',
                    'attempt_count'   => $newAttempt,
                    'error_message'   => 'Max attempts exceeded',
                    'processed_at'    => date('Y-m-d H:i:s')
                ], 'id=?', [$email['id']]);
                
                error_log('[Email Queue] ✗ Email failed (max attempts) - User: ' . $email['user_id']);
            } else {
                // Mark as pending for retry
                DB::update('email_queue', [
                    'status'        => 'pending',
                    'attempt_count' => $newAttempt
                ], 'id=?', [$email['id']]);
                
                error_log('[Email Queue] ⟳ Email retry queued - Attempt: ' . $newAttempt . '/' . $email['max_attempts']);
            }
        }
    } catch (Exception $e) {
        // Log error and mark as failed
        DB::update('email_queue', [
            'status'        => 'failed',
            'error_message' => substr($e->getMessage(), 0, 255),
            'processed_at'  => date('Y-m-d H:i:s'),
            'attempt_count' => $email['attempt_count'] + 1
        ], 'id=?', [$email['id']]);
        
        error_log('[Email Queue] ✗ Error processing email: ' . $e->getMessage());
    }
}
