-- Migration: Create packages, package_tests, and appointment_slots tables
-- Date: 2026-03-18
-- Purpose: Add tables for test packages/bundles and appointment slot management

-- ============================================
-- Table: packages
-- Purpose: Store test packages/bundles
-- ============================================
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

-- ============================================
-- Table: package_tests
-- Purpose: Associate tests with packages (many-to-many)
-- ============================================
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

-- ============================================
-- Table: appointment_slots
-- Purpose: Manage appointment slot availability
-- ============================================
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

-- ============================================
-- Insert Sample Test Packages (Optional - can be added via admin UI)
-- ============================================
INSERT INTO `packages` (`name`, `description`, `base_price`, `discount_percent`, `final_price`, `status`) VALUES
('Health Checkup Basic', 'Complete Blood Count + Basic Health Parameters', 500.00, 10.00, 450.00, 'Active'),
('Full Body Checkup', 'Comprehensive health checkup package', 1500.00, 15.00, 1275.00, 'Active'),
('Cardiac Checkup', 'Heart and cardiovascular system checkup', 800.00, 5.00, 760.00, 'Active'),
('Diabetes Screening', 'Blood sugar and diabetes related tests', 400.00, 8.00, 368.00, 'Active'),
('Kidney Function Panel', 'Complete kidney function tests', 600.00, 10.00, 540.00, 'Active');

-- ============================================
-- Insert Sample Appointment Slots for Next 7 Days
-- ============================================
-- Note: Adjust dates based on current date and working hours (9 AM - 6 PM)
-- This creates 30-minute slots for next 7 days
INSERT INTO `appointment_slots` (`slot_date`, `slot_time`, `time_period`, `max_capacity`, `booked_count`, `status`) VALUES
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
