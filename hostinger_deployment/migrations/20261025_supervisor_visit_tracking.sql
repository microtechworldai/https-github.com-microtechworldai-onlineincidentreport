-- Run once on an existing production database after taking a database backup.
-- This migration is additive; it does not insert sample supervisors, sites or visits.
ALTER TABLE `system_settings`
  ADD COLUMN `supervisor_visit_config` LONGTEXT NULL;

ALTER TABLE `sites`
  MODIFY COLUMN `active_qr_token` VARCHAR(100) NULL,
  ADD COLUMN `active_qr_token_hash` CHAR(64) NULL,
  ADD COLUMN `active_qr_token_encrypted` TEXT NULL,
  ADD COLUMN `supervisor_qr_token_hash` CHAR(64) NULL,
  ADD COLUMN `supervisor_qr_token_encrypted` TEXT NULL,
  ADD COLUMN `geofence_radius_meters` INT UNSIGNED NOT NULL DEFAULT 250,
  ADD UNIQUE KEY `uniq_sites_active_qr_hash` (`active_qr_token_hash`),
  ADD UNIQUE KEY `uniq_sites_supervisor_qr_hash` (`supervisor_qr_token_hash`);

ALTER TABLE `camps`
  MODIFY COLUMN `qr_token` VARCHAR(100) NULL,
  ADD COLUMN `qr_token_hash` CHAR(64) NULL UNIQUE,
  ADD COLUMN `qr_token_encrypted` TEXT NULL;

ALTER TABLE `guards`
  ADD COLUMN `supervisor_session_revoked_at` DATETIME NULL;

ALTER TABLE `supervisor_duties`
  ADD COLUMN `client_camp_out_time` DATETIME NULL,
  ADD COLUMN `client_camp_in_time` DATETIME NULL;

ALTER TABLE `supervisor_visits`
  ADD COLUMN `corrected_time_in` DATETIME NULL,
  ADD COLUMN `corrected_time_out` DATETIME NULL,
  ADD COLUMN `manual_timeout_remarks` TEXT NULL,
  ADD COLUMN `correction_version` INT UNSIGNED NOT NULL DEFAULT 0;

UPDATE `supervisor_visits`
SET `manual_timeout_remarks` = `remarks`
WHERE `manual_timeout_requested` = 1 AND `manual_timeout_remarks` IS NULL;

CREATE TABLE IF NOT EXISTS `supervisor_scan_events` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `idempotency_key` CHAR(36) NOT NULL UNIQUE,
  `supervisor_id` INT UNSIGNED NOT NULL,
  `duty_id` INT UNSIGNED NULL,
  `visit_id` INT UNSIGNED NULL,
  `token_hash` CHAR(64) NOT NULL,
  `scan_action` ENUM('Camp Out', 'Camp In', 'Time In', 'Time Out', 'Rejected') NOT NULL,
  `server_time` DATETIME NOT NULL,
  `client_time` DATETIME NULL,
  `device_hash` CHAR(64) NULL,
  `is_offline_sync` TINYINT(1) NOT NULL DEFAULT 0,
  `location_encrypted` TEXT NULL,
  `result_json` LONGTEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_scan_sup_time` (`supervisor_id`, `server_time`),
  INDEX `idx_scan_site_duty` (`duty_id`, `server_time`),
  CONSTRAINT `fk_scan_sup` FOREIGN KEY (`supervisor_id`) REFERENCES `guards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_scan_duty` FOREIGN KEY (`duty_id`) REFERENCES `supervisor_duties` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_scan_visit` FOREIGN KEY (`visit_id`) REFERENCES `supervisor_visits` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `supervisor_visit_corrections` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `visit_id` INT UNSIGNED NOT NULL,
  `version_number` INT UNSIGNED NOT NULL,
  `action` ENUM('Approve Timeout', 'Reject Timeout', 'Edit', 'Force Close', 'Restore') NOT NULL,
  `reason` TEXT NOT NULL,
  `before_json` LONGTEXT NOT NULL,
  `after_json` LONGTEXT NOT NULL,
  `admin_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_visit_correction_version` (`visit_id`, `version_number`),
  INDEX `idx_correction_admin_time` (`admin_id`, `created_at`),
  CONSTRAINT `fk_correction_visit` FOREIGN KEY (`visit_id`) REFERENCES `supervisor_visits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_correction_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `supervisor_schedule_exceptions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `supervisor_id` INT UNSIGNED NOT NULL,
  `exception_date` DATE NOT NULL,
  `exception_type` ENUM('Leave', 'Swap', 'Override') NOT NULL,
  `replacement_supervisor_id` INT UNSIGNED NULL,
  `site_id` INT UNSIGNED NULL,
  `shift_type` ENUM('Day', 'Night') NULL,
  `reason` TEXT NOT NULL,
  `created_by_admin_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_schedule_exception_date` (`exception_date`, `supervisor_id`),
  CONSTRAINT `fk_schedule_exception_sup` FOREIGN KEY (`supervisor_id`) REFERENCES `guards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_schedule_exception_replacement` FOREIGN KEY (`replacement_supervisor_id`) REFERENCES `guards` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_schedule_exception_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_schedule_exception_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `supervisor_visit_form_fields` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `field_key` VARCHAR(80) NOT NULL UNIQUE,
  `label` VARCHAR(200) NOT NULL,
  `field_type` ENUM('text', 'textarea', 'number', 'select', 'rating', 'photo') NOT NULL,
  `options_json` TEXT NULL,
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `required_on_issue` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `updated_by_admin_id` INT UNSIGNED NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_visit_form_active_sort` (`is_active`, `sort_order`),
  CONSTRAINT `fk_visit_form_admin` FOREIGN KEY (`updated_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `supervisor_report_jobs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `job_ref` VARCHAR(100) NOT NULL UNIQUE,
  `report_type` ENUM('Daily Supervisor', 'Monthly Supervisor', 'Monthly Site', 'Excel') NOT NULL,
  `filters_json` LONGTEXT NOT NULL,
  `status` ENUM('Queued', 'Running', 'Completed', 'Failed') NOT NULL DEFAULT 'Queued',
  `progress_total` INT UNSIGNED NOT NULL DEFAULT 0,
  `progress_done` INT UNSIGNED NOT NULL DEFAULT 0,
  `error_message` TEXT NULL,
  `created_by_admin_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_report_job_status` (`status`, `created_at`),
  CONSTRAINT `fk_report_job_admin` FOREIGN KEY (`created_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `supervisor_report_archive` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `report_ref` VARCHAR(100) NOT NULL UNIQUE,
  `report_type` ENUM('Daily Supervisor', 'Monthly Supervisor', 'Monthly Site', 'Excel') NOT NULL,
  `filters_json` LONGTEXT NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `content_sha256` CHAR(64) NOT NULL,
  `issued_by_admin_id` INT UNSIGNED NOT NULL,
  `issued_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_report_archive_issued` (`issued_at`),
  CONSTRAINT `fk_report_archive_admin` FOREIGN KEY (`issued_by_admin_id`) REFERENCES `admins` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
