-- Migration: Create technicians, sample tracking, and doctor referrals tables
-- Date: 2026-03-18
-- Purpose: Add tables for lab staff management, sample workflow tracking, and doctor referrals

-- ============================================
-- Table: technicians
-- Purpose: Store lab technician/staff information
-- ============================================
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

-- ============================================
-- Table: sample_tracking
-- Purpose: Track sample collection workflow status
-- ============================================
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

-- ============================================
-- Table: doctor_referrals
-- Purpose: Store doctor referral information for appointments
-- ============================================
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

-- ============================================
-- Add Foreign Key Constraint for technician in appointments
-- ============================================
ALTER TABLE `appointments` 
ADD CONSTRAINT `appointments_ibfk_technician` 
  FOREIGN KEY (`assigned_technician_id`) REFERENCES `technicians` (`technician_id`) ON DELETE SET NULL;
