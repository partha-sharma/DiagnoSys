-- =============================================================================
-- DiagnoSys Database Migration - Complete Schema Update
-- Date: March 18, 2026
-- Description: All migrations combined for easy execution
-- =============================================================================

-- =============================================================================
-- Migration 1: Enhance users table with email verification, password reset, and profile photo
-- =============================================================================

ALTER TABLE `users` 
ADD COLUMN `email_verified` BOOLEAN DEFAULT FALSE AFTER `email`,
ADD COLUMN `email_token` VARCHAR(255) DEFAULT NULL AFTER `email_verified`,
ADD COLUMN `email_token_expiry` DATETIME DEFAULT NULL AFTER `email_token`,
ADD COLUMN `reset_token` VARCHAR(255) DEFAULT NULL AFTER `email_token_expiry`,
ADD COLUMN `reset_expiry` DATETIME DEFAULT NULL AFTER `reset_token`,
ADD COLUMN `profile_photo` VARCHAR(255) DEFAULT NULL AFTER `reset_expiry`;

ALTER TABLE `users` 
ADD INDEX `idx_email_token` (`email_token`),
ADD INDEX `idx_reset_token` (`reset_token`);

-- =============================================================================
-- Migration 2: Enhance appointments table with new features
-- =============================================================================

ALTER TABLE `appointments` 
ADD COLUMN `cancellation_reason` TEXT DEFAULT NULL AFTER `status`,
ADD COLUMN `sample_status` ENUM('Pending','Collected','Processing','Report_Ready','Completed') DEFAULT 'Pending' AFTER `cancellation_reason`,
ADD COLUMN `assigned_technician_id` INT(11) DEFAULT NULL AFTER `sample_status`,
ADD COLUMN `is_home_collection` BOOLEAN DEFAULT FALSE AFTER `assigned_technician_id`,
ADD COLUMN `collection_address` TEXT DEFAULT NULL AFTER `is_home_collection`,
ADD COLUMN `collection_time` DATETIME DEFAULT NULL AFTER `collection_address`,
ADD COLUMN `collection_charge` DECIMAL(10,2) DEFAULT 0.00 AFTER `collection_time`;

ALTER TABLE `appointments` 
ADD INDEX `idx_sample_status` (`sample_status`),
ADD INDEX `idx_assigned_technician` (`assigned_technician_id`),
ADD INDEX `idx_appointment_date` (`appointment_date`);

-- =============================================================================
-- Migration 3: Create appointment notes, reviews, and payments tables
-- =============================================================================

CREATE TABLE IF NOT EXISTS `appointment_notes` (
  `note_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `appointment_id` INT(11) NOT NULL,
  `admin_id` INT(11) NOT NULL,
  `note_text` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_appointment_id` (`appointment_id`),
  INDEX `idx_admin_id` (`admin_id`),
  INDEX `idx_created_at` (`created_at`),
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE,
  FOREIGN KEY (`admin_id`) REFERENCES `admins` (`admin_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `reviews` (
  `review_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `appointment_id` INT(11) NOT NULL UNIQUE,
  `user_id` INT(11) NOT NULL,
  `rating` INT(1) NOT NULL COMMENT '1-5 star rating',
  `comment` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_rating` (`rating`),
  INDEX `idx_created_at` (`created_at`),
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `payment_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `appointment_id` INT(11) NOT NULL,
  `user_id` INT(11) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `status` ENUM('Pending','Processing','Completed','Failed','Refunded') DEFAULT 'Pending',
  `payment_method` VARCHAR(50) DEFAULT NULL COMMENT 'Stripe, PayPal, SSLCommerz, etc',
  `transaction_id` VARCHAR(100) DEFAULT NULL,
  `payment_date` DATETIME DEFAULT NULL,
  `refund_amount` DECIMAL(10,2) DEFAULT 0.00,
  `refund_reason` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_appointment_id` (`appointment_id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_transaction_id` (`transaction_id`),
  INDEX `idx_payment_date` (`payment_date`),
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- Migration 4: Create technicians, sample tracking, and doctor referrals tables
-- =============================================================================

CREATE TABLE IF NOT EXISTS `technicians` (
  `technician_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) UNIQUE NOT NULL,
  `phone` VARCHAR(20) NOT NULL,
  `specialization` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('Active','Inactive') DEFAULT 'Active',
  `assigned_appointments` INT(11) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_email` (`email`),
  INDEX `idx_status` (`status`),
  INDEX `idx_specialization` (`specialization`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `sample_tracking` (
  `tracking_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `appointment_id` INT(11) NOT NULL UNIQUE,
  `status` ENUM('Pending','Collected','Processing','Report_Ready','Completed') DEFAULT 'Pending',
  `collected_by` INT(11) DEFAULT NULL COMMENT 'technician_id',
  `collected_at` DATETIME DEFAULT NULL,
  `processing_started_at` DATETIME DEFAULT NULL,
  `report_ready_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_appointment_id` (`appointment_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_collected_by` (`collected_by`),
  INDEX `idx_updated_at` (`updated_at`),
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE,
  FOREIGN KEY (`collected_by`) REFERENCES `technicians` (`technician_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `doctor_referrals` (
  `referral_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `appointment_id` INT(11) NOT NULL,
  `doctor_name` VARCHAR(100) NOT NULL,
  `hospital` VARCHAR(100) DEFAULT NULL,
  `specialty` VARCHAR(100) DEFAULT NULL,
  `contact_number` VARCHAR(20) DEFAULT NULL,
  `referral_notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_appointment_id` (`appointment_id`),
  INDEX `idx_doctor_name` (`doctor_name`),
  INDEX `idx_specialty` (`specialty`),
  FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `appointments` 
ADD CONSTRAINT `appointments_ibfk_technician` 
  FOREIGN KEY (`assigned_technician_id`) REFERENCES `technicians` (`technician_id`) ON DELETE SET NULL;

-- =============================================================================
-- Migration 5: Create packages, package_tests, and appointment_slots tables
-- =============================================================================

CREATE TABLE IF NOT EXISTS `packages` (
  `package_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL UNIQUE,
  `description` TEXT DEFAULT NULL,
  `base_price` DECIMAL(10,2) NOT NULL,
  `discount_percent` DECIMAL(5,2) DEFAULT 0.00,
  `final_price` DECIMAL(10,2) NOT NULL COMMENT 'calculated price after discount',
  `status` ENUM('Active','Inactive') DEFAULT 'Active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_name` (`name`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `package_tests` (
  `package_test_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `package_id` INT(11) NOT NULL,
  `test_id` INT(11) NOT NULL,
  UNIQUE KEY `unique_package_test` (`package_id`, `test_id`),
  INDEX `idx_package_id` (`package_id`),
  INDEX `idx_test_id` (`test_id`),
  FOREIGN KEY (`package_id`) REFERENCES `packages` (`package_id`) ON DELETE CASCADE,
  FOREIGN KEY (`test_id`) REFERENCES `tests` (`test_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `appointment_slots` (
  `slot_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `slot_date` DATE NOT NULL,
  `slot_time` TIME NOT NULL,
  `time_period` VARCHAR(20) DEFAULT NULL COMMENT 'e.g., 9:00-9:30, 9:30-10:00',
  `max_capacity` INT(11) DEFAULT 5 COMMENT 'max appointments per slot',
  `booked_count` INT(11) DEFAULT 0 COMMENT 'current bookings in this slot',
  `status` ENUM('Available','Booked','Unavailable','Closed') DEFAULT 'Available',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_slot` (`slot_date`, `slot_time`),
  INDEX `idx_slot_date` (`slot_date`),
  INDEX `idx_status` (`status`),
  INDEX `idx_slot_date_time` (`slot_date`, `slot_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================================
-- Insert Sample Data
-- =============================================================================

INSERT IGNORE INTO `packages` (`name`, `description`, `base_price`, `discount_percent`, `final_price`, `status`) VALUES
('Health Checkup Basic', 'Complete Blood Count + Basic Health Parameters', 500.00, 10.00, 450.00, 'Active'),
('Full Body Checkup', 'Comprehensive health checkup package', 1500.00, 15.00, 1275.00, 'Active'),
('Cardiac Checkup', 'Heart and cardiovascular system checkup', 800.00, 5.00, 760.00, 'Active'),
('Diabetes Screening', 'Blood sugar and diabetes related tests', 400.00, 8.00, 368.00, 'Active'),
('Kidney Function Panel', 'Complete kidney function tests', 600.00, 10.00, 540.00, 'Active');

INSERT IGNORE INTO `appointment_slots` (`slot_date`, `slot_time`, `time_period`, `max_capacity`, `booked_count`, `status`) VALUES
('2026-03-19', '09:00:00', '9:00-9:30', 5, 0, 'Available'),
('2026-03-19', '09:30:00', '9:30-10:00', 5, 0, 'Available'),
('2026-03-19', '10:00:00', '10:00-10:30', 5, 0, 'Available'),
('2026-03-19', '10:30:00', '10:30-11:00', 5, 0, 'Available'),
('2026-03-19', '11:00:00', '11:00-11:30', 5, 0, 'Available'),
('2026-03-19', '14:00:00', '2:00-2:30 PM', 5, 0, 'Available'),
('2026-03-19', '14:30:00', '2:30-3:00 PM', 5, 0, 'Available'),
('2026-03-19', '15:00:00', '3:00-3:30 PM', 5, 0, 'Available'),
('2026-03-20', '09:00:00', '9:00-9:30', 5, 0, 'Available'),
('2026-03-20', '09:30:00', '9:30-10:00', 5, 0, 'Available'),
('2026-03-20', '10:00:00', '10:00-10:30', 5, 0, 'Available'),
('2026-03-20', '14:00:00', '2:00-2:30 PM', 5, 0, 'Available'),
('2026-03-20', '14:30:00', '2:30-3:00 PM', 5, 0, 'Available');

-- =============================================================================
-- Migration Complete
-- =============================================================================
-- All tables created and columns enhanced successfully!
-- Next: PHASE 1 - Gisan's Frontend Implementation
-- =============================================================================
