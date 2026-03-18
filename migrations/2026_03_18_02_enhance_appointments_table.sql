-- Migration: Enhance appointments table with new features
-- Date: 2026-03-18
-- Purpose: Add columns for cancellation, home collection, technician assignment, and sample tracking

ALTER TABLE `appointments` 
ADD COLUMN `cancellation_reason` TEXT DEFAULT NULL AFTER `status`,
ADD COLUMN `sample_status` ENUM('Pending','Collected','Processing','Report_Ready','Completed') DEFAULT 'Pending' AFTER `cancellation_reason`,
ADD COLUMN `assigned_technician_id` INT(11) DEFAULT NULL AFTER `sample_status`,
ADD COLUMN `is_home_collection` BOOLEAN DEFAULT FALSE AFTER `assigned_technician_id`,
ADD COLUMN `collection_address` TEXT DEFAULT NULL AFTER `is_home_collection`,
ADD COLUMN `collection_time` DATETIME DEFAULT NULL AFTER `collection_address`,
ADD COLUMN `collection_charge` DECIMAL(10,2) DEFAULT 0.00 AFTER `collection_time`;

-- Create indexes for performance
ALTER TABLE `appointments` 
ADD INDEX `idx_sample_status` (`sample_status`),
ADD INDEX `idx_assigned_technician` (`assigned_technician_id`),
ADD INDEX `idx_appointment_date` (`appointment_date`);

-- Note: Foreign key constraint for assigned_technician_id will be added in migration 04_create_technicians_table.sql
