-- ========================================================
-- Database Schema for Student Self-Registration & OTP Verification
-- LASUSTECH Secure Online Voting Portal
-- ========================================================

-- 1. Ensure `users` table has proper constraints for email and student_id
ALTER TABLE `users` MODIFY COLUMN `student_id` VARCHAR(30) NOT NULL;
ALTER TABLE `users` MODIFY COLUMN `level` INT UNSIGNED NOT NULL DEFAULT 100;

-- (The unique indexes unique_student_id and unique_user_email are already present on `users`)

-- 2. Temporary OTP verification table for pending student sign-ups
CREATE TABLE IF NOT EXISTS `registration_verifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(30) NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `department` VARCHAR(150) NOT NULL,
    `level` INT UNSIGNED NOT NULL DEFAULT 100,
    `email` VARCHAR(190) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `otp_code` VARCHAR(10) NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `is_verified` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    INDEX `idx_email` (`email`),
    INDEX `idx_student_id` (`student_id`),
    INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Optional pre-approved student roster table
-- When 'enforce_student_roster' => true in config/mail.php,
-- only matric numbers listed here will be allowed to sign up.
CREATE TABLE IF NOT EXISTS `eligible_students` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(30) NOT NULL UNIQUE,
    `full_name` VARCHAR(150) NOT NULL,
    `department` VARCHAR(150) NOT NULL,
    `level` INT UNSIGNED DEFAULT 100,
    `email` VARCHAR(190) DEFAULT NULL,
    `is_registered` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

