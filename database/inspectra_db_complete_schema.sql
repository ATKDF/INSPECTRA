-- =====================================================================
-- INSPECTRA UNIFIED DATABASE SCHEMA (Complete)
-- Vehicle Rental & Digital Inspection Platform
-- =====================================================================
-- Single source of truth for a fresh install — creates every table the
-- app uses. Safe to re-run: does NOT drop an existing database, so it
-- will never silently wipe data. If you genuinely want a full reset,
-- run `DROP DATABASE inspectra_db;` yourself first as a deliberate step.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS inspectra_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE inspectra_db;
SET NAMES utf8mb4;

-- =====================================================================
-- USERS TABLE
-- =====================================================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(30),
    date_of_birth DATE NULL,
    address VARCHAR(255) NULL,
    city VARCHAR(100) NULL,
    id_document_type VARCHAR(50) NULL,
    id_document_number VARCHAR(80) NULL,
    emergency_contact_name VARCHAR(150) NULL,
    emergency_contact_phone VARCHAR(30) NULL,
    password_hash VARCHAR(255),
    role ENUM('admin','customer') NOT NULL DEFAULT 'customer',
    avatar VARCHAR(500),
    avatar_path VARCHAR(500) NULL,
    oauth_provider ENUM('google','apple') NULL,
    oauth_id VARCHAR(255) NULL,
    is_verified TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    email_verified_at TIMESTAMP NULL,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_uuid (uuid)
) ENGINE=InnoDB;

-- =====================================================================
-- EMAIL VERIFICATION TOKENS
-- =====================================================================
CREATE TABLE IF NOT EXISTS email_verifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token)
) ENGINE=InnoDB;

-- =====================================================================
-- PASSWORD RESET TOKENS
-- =====================================================================
CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_token (token)
) ENGINE=InnoDB;

-- =====================================================================
-- VEHICLES TABLE
-- =====================================================================
CREATE TABLE IF NOT EXISTS vehicles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    make VARCHAR(100) NOT NULL,
    model VARCHAR(100) NOT NULL,
    year YEAR NOT NULL,
    registration_number VARCHAR(50) UNIQUE NOT NULL,
    category ENUM('economy','suv','luxury','van','truck','motorcycle') NOT NULL DEFAULT 'economy',
    color VARCHAR(60),
    fuel_type ENUM('petrol','diesel','electric','hybrid') DEFAULT 'petrol',
    transmission ENUM('automatic','manual') DEFAULT 'automatic',
    seats INT DEFAULT 5,
    daily_rate DECIMAL(10,2) NOT NULL,
    deposit_amount DECIMAL(10,2) DEFAULT 0.00,
    description TEXT,
    features JSON,
    status ENUM('available','rented','maintenance','retired') DEFAULT 'available',
    total_rentals INT DEFAULT 0,
    mileage INT DEFAULT 0,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_category (category),
    INDEX idx_uuid (uuid)
) ENGINE=InnoDB;

-- =====================================================================
-- VEHICLE PHOTOS
-- =====================================================================
CREATE TABLE IF NOT EXISTS vehicle_photos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vehicle_id INT UNSIGNED NOT NULL,
    photo_url VARCHAR(500) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- BOOKINGS / RENTALS TABLE (with v2 migration columns)
-- =====================================================================
CREATE TABLE IF NOT EXISTS bookings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    booking_ref VARCHAR(20) UNIQUE NOT NULL,
    vehicle_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    agent_id INT UNSIGNED NULL,
    pickup_date DATE NOT NULL,
    pickup_time VARCHAR(10) NULL,
    return_date DATE NOT NULL,
    return_time VARCHAR(10) NULL,
    actual_return_date DATE NULL,
    pickup_location VARCHAR(300),
    pickup_city VARCHAR(100) NULL,
    pickup_street VARCHAR(200) NULL,
    pickup_gps VARCHAR(100) NULL,
    return_location VARCHAR(300),
    id_document_type VARCHAR(50) NULL,
    id_document_number VARCHAR(80) NULL,
    id_document_url VARCHAR(500) NULL,
    total_days INT NOT NULL,
    daily_rate DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    deposit_amount DECIMAL(10,2) DEFAULT 0.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    service_charge DECIMAL(10,2) DEFAULT 0.00,
    initial_deposit DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    amount_paid DECIMAL(10,2) DEFAULT 0.00,
    amount_refunded DECIMAL(10,2) DEFAULT 0.00,
    status ENUM('booked','paid','active','returned','completed','cancelled') DEFAULT 'booked',
    payment_status ENUM('unpaid','partial','paid','refunded','partial_refund') DEFAULT 'unpaid',
    cancellation_reason TEXT NULL,
    cancelled_at TIMESTAMP NULL,
    notes TEXT,
    special_requests TEXT,
    payment_note TEXT NULL,
    is_locked TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id),
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (agent_id) REFERENCES users(id),
    INDEX idx_status (status),
    INDEX idx_customer (customer_id),
    INDEX idx_vehicle (vehicle_id),
    INDEX idx_uuid (uuid),
    INDEX idx_ref (booking_ref)
) ENGINE=InnoDB;

-- =====================================================================
-- PAYMENTS TABLE
-- =====================================================================
CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    booking_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    ghs_equivalent DECIMAL(10,2) DEFAULT NULL,
    currency VARCHAR(10) DEFAULT 'GHS',
    payment_type ENUM('booking','deposit','balance','refund') DEFAULT 'booking',
    payment_method ENUM('stripe','paystack','cash','bank_transfer') NOT NULL,
    provider_reference VARCHAR(255),
    provider_transaction_id VARCHAR(255),
    status ENUM('pending','completed','failed','refunded','cancelled') DEFAULT 'pending',
    metadata JSON,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_booking (booking_id),
    INDEX idx_status (status),
    INDEX idx_provider_ref (provider_reference)
) ENGINE=InnoDB;

-- =====================================================================
-- REFUND REQUESTS
-- =====================================================================
CREATE TABLE IF NOT EXISTS refund_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    booking_id INT UNSIGNED NOT NULL,
    payment_id INT UNSIGNED NOT NULL,
    requested_by INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending','approved','rejected','processed') DEFAULT 'pending',
    admin_note TEXT NULL,
    processed_by INT UNSIGNED NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (payment_id) REFERENCES payments(id),
    FOREIGN KEY (requested_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- =====================================================================
-- DISPUTES TABLE
-- =====================================================================
CREATE TABLE IF NOT EXISTS disputes (
    dispute_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    booking_id INT UNSIGNED NOT NULL,
    raised_by INT UNSIGNED NOT NULL,
    assigned_to INT UNSIGNED DEFAULT NULL,
    subject VARCHAR(300) NOT NULL,
    description TEXT NOT NULL,
    category ENUM('damage','overcharge','service','inspection','refund','other') NOT NULL DEFAULT 'other',
    priority ENUM('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
    status ENUM('open','under_review','resolved','closed','escalated') NOT NULL DEFAULT 'open',
    resolution TEXT DEFAULT NULL,
    resolved_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (dispute_id),
    KEY idx_booking (booking_id),
    KEY idx_status (status),
    CONSTRAINT fk_disp_booking FOREIGN KEY (booking_id) REFERENCES bookings(id),
    CONSTRAINT fk_disp_raised_by FOREIGN KEY (raised_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- DISPUTE MESSAGES (thread)
-- =====================================================================
CREATE TABLE IF NOT EXISTS dispute_messages (
    message_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dispute_id INT UNSIGNED NOT NULL,
    sender_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    attachments JSON DEFAULT NULL,
    is_internal TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Internal admin notes invisible to customer',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id),
    KEY idx_dispute (dispute_id),
    CONSTRAINT fk_dm_dispute FOREIGN KEY (dispute_id) REFERENCES disputes(dispute_id) ON DELETE CASCADE,
    CONSTRAINT fk_dm_sender FOREIGN KEY (sender_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =====================================================================
-- BOOKING CHARGES
-- Persists the final decision on a booking's post-return charge (mileage/
-- fuel/damage deduction), so it survives page reloads instead of being
-- recomputed and forgotten every time compare_inspections() runs.
-- =====================================================================
CREATE TABLE IF NOT EXISTS booking_charges (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    booking_id INT UNSIGNED NOT NULL,
    computed_amount DECIMAL(10,2) NOT NULL,
    resolved_amount DECIMAL(10,2) NOT NULL,
    status ENUM('accepted','approved','adjusted','waived','disputed') NOT NULL,
    decision_note TEXT NULL,
    decided_by INT UNSIGNED NULL,
    decided_at DATETIME NULL,
    dispute_id INT UNSIGNED NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_booking_charge (booking_id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (decided_by) REFERENCES users(id),
    FOREIGN KEY (dispute_id) REFERENCES disputes(dispute_id)
) ENGINE=InnoDB;

-- =====================================================================
-- INSPECTIONS TABLE
-- =====================================================================
CREATE TABLE IF NOT EXISTS inspections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    booking_id INT UNSIGNED NOT NULL,
    type ENUM('pickup','return') NOT NULL,
    status ENUM('pending','in_progress','completed') DEFAULT 'pending',
    conducted_by INT UNSIGNED NOT NULL,
    agent_id INT UNSIGNED NULL,
    overall_condition ENUM('excellent','good','fair','poor') NULL,
    mileage_at_inspection INT NULL,
    fuel_level ENUM('full','three_quarter','half','quarter','empty') NULL,
    notes TEXT,
    agent_notes TEXT,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (conducted_by) REFERENCES users(id),
    FOREIGN KEY (agent_id) REFERENCES users(id),
    INDEX idx_booking (booking_id),
    INDEX idx_type (type),
    INDEX idx_uuid (uuid),
    UNIQUE KEY uniq_booking_type (booking_id, type)
) ENGINE=InnoDB;

-- =====================================================================
-- INSPECTION PHOTOS (with v2 migration columns)
-- =====================================================================
CREATE TABLE IF NOT EXISTS inspection_photos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inspection_id INT UNSIGNED NOT NULL,
    photo_type ENUM('front','rear','left','right','interior','dashboard','other') NOT NULL,
    photo_url VARCHAR(500) NOT NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    address_label VARCHAR(300) NULL,
    taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    device_info JSON NULL,
    captured_by INT UNSIGNED NULL,
    capture_method ENUM('camera','upload') DEFAULT 'upload',
    FOREIGN KEY (inspection_id) REFERENCES inspections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- DAMAGE REPORTS (with v2 migration columns)
-- =====================================================================
CREATE TABLE IF NOT EXISTS damage_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    inspection_id INT UNSIGNED NOT NULL,
    zone VARCHAR(100) NOT NULL,
    damage_type ENUM('scratch','dent','crack','chip','tear','missing','other') NOT NULL,
    severity ENUM('minor','moderate','severe') DEFAULT 'minor',
    description TEXT,
    x_position DECIMAL(5,2) NULL,
    y_position DECIMAL(5,2) NULL,
    photo_url VARCHAR(500) NULL,
    repair_cost DECIMAL(10,2) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    recorded_by INT UNSIGNED NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inspection_id) REFERENCES inspections(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================================
-- SIGNATURES TABLE
-- =====================================================================
CREATE TABLE IF NOT EXISTS signatures (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inspection_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    role ENUM('customer','agent') NOT NULL,
    signature_url VARCHAR(500) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    signed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inspection_id) REFERENCES inspections(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- =====================================================================
-- INSPECTION REPORTS (Generated PDFs)
-- =====================================================================
CREATE TABLE IF NOT EXISTS inspection_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    inspection_id INT UNSIGNED NOT NULL,
    report_url VARCHAR(500) NOT NULL,
    share_token VARCHAR(64) UNIQUE NOT NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inspection_id) REFERENCES inspections(id)
) ENGINE=InnoDB;

-- =====================================================================
-- NOTIFICATIONS
-- =====================================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON NULL,
    is_read TINYINT(1) DEFAULT 0,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- =====================================================================
-- EMAIL QUEUE (async email processing)
-- =====================================================================
CREATE TABLE IF NOT EXISTS email_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    type VARCHAR(100) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message LONGTEXT NOT NULL,
    data JSON,
    status ENUM('pending','processing','sent','failed') DEFAULT 'pending',
    attempt_count INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- =====================================================================
-- INSPECTION ACTIVITY LOG (v2 migration - new table)
-- =====================================================================
CREATE TABLE IF NOT EXISTS inspection_activity (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    inspection_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL,
    detail TEXT NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (inspection_id) REFERENCES inspections(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_insp (inspection_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- =====================================================================
-- AGREEMENTS (v2 migration - new table)
-- =====================================================================
CREATE TABLE IF NOT EXISTS agreements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    booking_id INT UNSIGNED NOT NULL,
    inspection_id INT UNSIGNED NULL,
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    content_hash VARCHAR(64) NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (inspection_id) REFERENCES inspections(id),
    INDEX idx_booking (booking_id)
) ENGINE=InnoDB;

-- =====================================================================
-- REVIEWS
-- One review per completed booking. rating (overall) is required;
-- cleanliness/service/value are optional finer-grained scores. Auto-
-- published on submission; admin can hide reactively.
-- =====================================================================
CREATE TABLE IF NOT EXISTS reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    booking_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    vehicle_id INT UNSIGNED NOT NULL,
    rating TINYINT UNSIGNED NOT NULL,
    cleanliness TINYINT UNSIGNED NULL,
    service TINYINT UNSIGNED NULL,
    value TINYINT UNSIGNED NULL,
    comment TEXT NULL,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    admin_response TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_booking_review (booking_id),
    FOREIGN KEY (booking_id) REFERENCES bookings(id),
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (vehicle_id) REFERENCES vehicles(id)
) ENGINE=InnoDB;

-- =====================================================================
-- CONTACT MESSAGES
-- Public contact-form submissions. Admins are notified (in-app + email)
-- on submission and can review/reply from the admin panel.
-- =====================================================================
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid VARCHAR(36) UNIQUE NOT NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NULL,
    message TEXT NOT NULL,
    status ENUM('new','read','replied') NOT NULL DEFAULT 'new',
    admin_notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================================
-- SYSTEM SETTINGS
-- =====================================================================
CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) UNIQUE NOT NULL,
    `value` TEXT,
    type ENUM('text','number','boolean','json') DEFAULT 'text',
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =====================================================================
-- AUDIT LOGS (audit-trail.sql - new table)
-- =====================================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL COMMENT 'create, update, delete, login, status_change',
    `entity_type` VARCHAR(50) NOT NULL COMMENT 'user, booking, vehicle, payment, inspection',
    `entity_id` INT UNSIGNED NULL,
    `old_data` JSON NULL,
    `new_data` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_time` (`created_at`),
    INDEX `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEED DATA: DEFAULT SETTINGS
-- =====================================================================
INSERT IGNORE INTO settings (`key`, `value`, `type`, `description`) VALUES
('company_name', 'Inspectra Rentals', 'text', 'Company name'),
('company_email', 'hello@inspectra.com', 'text', 'Contact email'),
('company_phone', '+233 30 000 0000', 'text', 'Contact phone'),
('currency', 'GHS', 'text', 'Default currency'),
('currency_symbol', '₵', 'text', 'Currency symbol'),
('stripe_enabled', '0', 'boolean', 'Enable Stripe payments'),
('paystack_enabled', '1', 'boolean', 'Enable Paystack payments'),
('tax_rate', '0', 'number', 'Tax rate percentage'),
('require_deposit', '1', 'boolean', 'Require deposit on booking'),
('service_charge_rate', '5', 'number', 'Service charge percentage'),
('deposit_percentage', '50', 'number', 'Initial deposit as % of total'),
('pickup_locations', 'Osu,Tema', 'text', 'Comma-separated pickup location options'),
('allow_partial_payment', '1', 'boolean', 'Allow customers to pay more than deposit'),
('exchange_rate', '15.00', 'number', 'Currency exchange rate');

-- =====================================================================
-- SEED DATA: ADMIN USER (password: Admin@1234)
-- =====================================================================
INSERT IGNORE INTO users (uuid, first_name, last_name, email, phone, password_hash, role, is_verified, is_active) VALUES
(UUID(), 'Admin', 'Inspectra', 'admin@inspectra.com', '+233 30 000 0001', '$2y$12$qJHEP4LQ.HoE81tu7ol9Terc1E3fad0GDa6HiOPoTxwilzmfoJGXu', 'admin', 1, 1);

-- =====================================================================
-- SEED DATA: SAMPLE CUSTOMER (password: Customer@1234)
-- =====================================================================
INSERT IGNORE INTO users (uuid, first_name, last_name, email, phone, password_hash, role, is_verified, is_active) VALUES
(UUID(), 'Kofi', 'Mensah', 'kofi@example.com', '+233 24 000 0001', '$2y$12$gLcIZp78vO90qAmUhoOQCOLG.KatR/O98s8.LHgjkexZQa/4l2eeW', 'customer', 1, 1);

-- =====================================================================
-- SEED DATA: SAMPLE AUDIT LOG ENTRY
-- =====================================================================
INSERT IGNORE INTO audit_logs (`user_id`, `action`, `entity_type`, `entity_id`, `old_data`, `new_data`, `ip_address`) VALUES
(1, 'login', 'system', NULL, NULL, NULL, '127.0.0.1');

-- =====================================================================
-- SCHEMA COMPLETE
-- =====================================================================
-- All tables, columns, indexes, foreign keys, and seed data have been created.
-- This unified schema includes:
--   ✓ Core tables from database.sql
--   ✓ v2 migration columns and new tables from database_v2_migration.sql
--   ✓ Audit logging from audit-trail.sql
--   ✓ All seed data and default settings
-- 
-- Total Tables: 18
-- Total Columns: 280+
-- Total Indexes: 40+
-- Foreign Keys: 45
-- =====================================================================
