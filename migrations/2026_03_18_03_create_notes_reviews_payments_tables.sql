-- Migration: Create appointment notes, reviews, and payments tables
-- Date: 2026-03-18
-- Purpose: Add tables for admin notes on appointments, patient reviews, and payment tracking

-- ============================================
-- Table: appointment_notes
-- Purpose: Store admin notes on appointments
-- ============================================
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

-- ============================================
-- Table: reviews
-- Purpose: Store patient reviews and ratings for appointments
-- ============================================
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

-- ============================================
-- Table: payments
-- Purpose: Store payment transaction records
-- ============================================
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
