-- Migration: Enhance users table with email verification, password reset, and profile photo
-- Date: 2026-03-18
-- Purpose: Add columns for email verification, password reset tokens, and profile photo storage

ALTER TABLE `users` 
ADD COLUMN `email_verified` BOOLEAN DEFAULT FALSE AFTER `email`,
ADD COLUMN `email_token` VARCHAR(255) DEFAULT NULL AFTER `email_verified`,
ADD COLUMN `email_token_expiry` DATETIME DEFAULT NULL AFTER `email_token`,
ADD COLUMN `reset_token` VARCHAR(255) DEFAULT NULL AFTER `email_token_expiry`,
ADD COLUMN `reset_expiry` DATETIME DEFAULT NULL AFTER `reset_token`,
ADD COLUMN `profile_photo` VARCHAR(255) DEFAULT NULL AFTER `reset_expiry`;

-- Create indexes for token lookups
ALTER TABLE `users` 
ADD INDEX `idx_email_token` (`email_token`),
ADD INDEX `idx_reset_token` (`reset_token`);

-- Add constraint to ensure email is unique only for verified emails (this is handled in application logic)
-- Note: Keeping existing unique constraint on email
