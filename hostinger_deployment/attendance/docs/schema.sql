-- Dedicated Database Schema for Attendance & Payroll Subsystem
-- Database: uss_attendance
-- Strictly independent from uss_incidents. NO shared tables, NO foreign keys to incident tables.

SET NAMES utf8mb4;
SET time_zone = '+04:00';

-- 1. Module-Specific Settings (isolated from incident settings)
CREATE TABLE IF NOT EXISTS `att_settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_val` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `att_settings` (`setting_key`, `setting_val`) VALUES
('company_name', 'ULTIMATE SECURITY SERVICES'),
('company_tagline', 'SECURITY OPERATIONS & MANPOWER MANAGEMENT'),
('currency', 'AED'),
('default_day_hours', '12'),
('payroll_cycle_start', '27'),
('payroll_cycle_end', '26');

-- 2. Module Sites Master List (independent from incident sites)
CREATE TABLE IF NOT EXISTS `att_sites` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `site_name` VARCHAR(180) NOT NULL,
  `site_code` VARCHAR(50) NOT NULL UNIQUE,
  `client_name` VARCHAR(180) NULL,
  `req_day` INT NOT NULL DEFAULT 1,
  `req_night` INT NOT NULL DEFAULT 1,
  `contract_start` DATE NULL,
  `contract_end` DATE NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_att_sites_active` (`is_active`),
  INDEX `idx_att_sites_name` (`site_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Module Staff Master List (independent from incident guards/supervisors)
CREATE TABLE IF NOT EXISTS `att_staff` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `emp_no` VARCHAR(60) NOT NULL UNIQUE,
  `emp_name` VARCHAR(180) NOT NULL,
  `designation` VARCHAR(100) NOT NULL DEFAULT 'Security Officer',
  `mobile_phone` VARCHAR(50) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_att_staff_emp_no` (`emp_no`),
  INDEX `idx_att_staff_name` (`emp_name`),
  INDEX `idx_att_staff_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Monthly Site Attendance Sheets Header
CREATE TABLE IF NOT EXISTS `att_sheets` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `site_id` INT UNSIGNED NOT NULL,
  `year` SMALLINT UNSIGNED NOT NULL,
  `month` TINYINT UNSIGNED NOT NULL,
  `status` ENUM('Open', 'Locked') NOT NULL DEFAULT 'Open',
  `locked_by` VARCHAR(100) NULL,
  `lock_reason` VARCHAR(255) NULL,
  `locked_at` DATETIME NULL,
  `version` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_sheet_site_period` (`site_id`, `year`, `month`),
  INDEX `idx_att_sheets_period` (`year`, `month`),
  CONSTRAINT `fk_att_sheets_site` FOREIGN KEY (`site_id`) REFERENCES `att_sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Attendance Sheet Roster Rows (One row per staff on that site-month sheet)
CREATE TABLE IF NOT EXISTS `att_rows` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sheet_id` INT UNSIGNED NOT NULL,
  `emp_no` VARCHAR(60) NOT NULL,
  `emp_name_snapshot` VARCHAR(180) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_row_sheet_emp` (`sheet_id`, `emp_no`),
  INDEX `idx_att_rows_sheet` (`sheet_id`, `sort_order`),
  CONSTRAINT `fk_att_rows_sheet` FOREIGN KEY (`sheet_id`) REFERENCES `att_sheets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Attendance Sheet Cells (Day 1 to 28/29/30/31 codes: D, N, OFF, S, B)
CREATE TABLE IF NOT EXISTS `att_cells` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `row_id` INT UNSIGNED NOT NULL,
  `day_number` TINYINT UNSIGNED NOT NULL,
  `code` VARCHAR(5) NOT NULL DEFAULT '',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_cell_row_day` (`row_id`, `day_number`),
  INDEX `idx_att_cells_code` (`code`),
  CONSTRAINT `fk_att_cells_row` FOREIGN KEY (`row_id`) REFERENCES `att_rows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Site Date-Range Assignments with Conflict Tracking
CREATE TABLE IF NOT EXISTS `att_site_assignments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `emp_no` VARCHAR(60) NOT NULL,
  `site_id` INT UNSIGNED NOT NULL,
  `date_from` DATE NOT NULL,
  `date_to` DATE NOT NULL,
  `default_code` ENUM('D', 'N', 'B') NOT NULL DEFAULT 'D',
  `is_conflict_override` TINYINT(1) NOT NULL DEFAULT 0,
  `override_reason` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_att_assign_emp` (`emp_no`, `date_from`, `date_to`),
  INDEX `idx_att_assign_site` (`site_id`),
  CONSTRAINT `fk_att_assign_site` FOREIGN KEY (`site_id`) REFERENCES `att_sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Dynamic Custom Payroll Columns
CREATE TABLE IF NOT EXISTS `att_payroll_columns` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `col_name` VARCHAR(100) NOT NULL,
  `col_type` ENUM('text', 'number') NOT NULL DEFAULT 'number',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Saved Custom Column Values per Payroll Period & Employee
CREATE TABLE IF NOT EXISTS `att_payroll_values` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `period_key` VARCHAR(50) NOT NULL,
  `emp_no` VARCHAR(60) NOT NULL,
  `column_id` INT UNSIGNED NOT NULL,
  `val_text` VARCHAR(255) NULL,
  `val_number` DECIMAL(12, 2) NULL DEFAULT 0.00,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_payroll_period_emp_col` (`period_key`, `emp_no`, `column_id`),
  INDEX `idx_payroll_period` (`period_key`),
  CONSTRAINT `fk_att_pval_col` FOREIGN KEY (`column_id`) REFERENCES `att_payroll_columns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Saved Payroll Remarks & Conflict Notes per Period & Employee
CREATE TABLE IF NOT EXISTS `att_payroll_remarks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `period_key` VARCHAR(50) NOT NULL,
  `emp_no` VARCHAR(60) NOT NULL,
  `remarks` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_payroll_period_emp_rem` (`period_key`, `emp_no`),
  INDEX `idx_payroll_rem_period` (`period_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Append-Only Tamper-Evident Audit Trail with Cryptographic Hash Chaining
CREATE TABLE IF NOT EXISTS `att_audit` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `prev_hash` VARCHAR(64) NOT NULL DEFAULT 'GENESIS_ATTENDANCE_HASH',
  `record_hash` VARCHAR(64) NOT NULL,
  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(80) NOT NULL,
  `entity_id` VARCHAR(80) NULL,
  `details` TEXT NULL,
  `actor_name` VARCHAR(150) NOT NULL DEFAULT 'Super Admin',
  `ip_address` VARCHAR(60) NOT NULL DEFAULT '127.0.0.1',
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_att_audit_action` (`action`),
  INDEX `idx_att_audit_entity` (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Additional Deployments Table
CREATE TABLE IF NOT EXISTS `att_additional_deployments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `sheet_id` INT UNSIGNED NOT NULL,
  `day_number` TINYINT UNSIGNED NOT NULL,
  `add_day` INT NOT NULL DEFAULT 0,
  `add_night` INT NOT NULL DEFAULT 0,
  `notes` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_add_sheet_day` (`sheet_id`, `day_number`),
  INDEX `idx_add_deploy_sheet` (`sheet_id`),
  CONSTRAINT `fk_add_deploy_sheet` FOREIGN KEY (`sheet_id`) REFERENCES `att_sheets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
