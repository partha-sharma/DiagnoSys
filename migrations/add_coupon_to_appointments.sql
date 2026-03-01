-- Migration: Add coupon and discount tracking to appointments table
-- Date: 2026-03-01

ALTER TABLE `appointments` 
ADD COLUMN `coupon_code` VARCHAR(20) DEFAULT NULL AFTER `status`,
ADD COLUMN `discount_amount` DECIMAL(10,2) DEFAULT 0.00 AFTER `coupon_code`,
ADD COLUMN `total_amount` DECIMAL(10,2) DEFAULT 0.00 AFTER `discount_amount`;

-- Create coupons table for better coupon management
CREATE TABLE IF NOT EXISTS `coupons` (
  `coupon_id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `discount_type` enum('percentage','fixed') NOT NULL,
  `discount_value` decimal(10,2) NOT NULL,
  `min_amount` decimal(10,2) DEFAULT 0.00,
  `max_discount` decimal(10,2) DEFAULT NULL,
  `usage_limit` int(11) DEFAULT NULL,
  `used_count` int(11) DEFAULT 0,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`coupon_id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert sample coupons
INSERT INTO `coupons` (`code`, `description`, `discount_type`, `discount_value`, `min_amount`, `status`) VALUES
('SAVE10', '10% discount on all tests', 'percentage', 10.00, 0.00, 'Active'),
('SAVE50', 'Flat ৳50 off', 'fixed', 50.00, 100.00, 'Active'),
('HEALTH20', '20% discount for health checkup', 'percentage', 20.00, 200.00, 'Active'),
('FIRST100', 'First time customer - ৳100 off', 'fixed', 100.00, 150.00, 'Active');
