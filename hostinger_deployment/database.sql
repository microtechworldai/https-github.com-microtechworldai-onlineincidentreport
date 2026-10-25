-- ====================================================================
-- ULTIMATE SECURITY SERVICES (USS) - INCIDENT REPORT SYSTEM
-- Production Database Schema for Hostinger MySQL / MariaDB
-- Optimized for Long-Term Data Retention (10+ Years)
-- Charset: utf8mb4 / Collation: utf8mb4_unicode_ci
-- ====================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- 1. Administrators Table
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL DEFAULT 'Super Administrator',
  `email` VARCHAR(150) NULL,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL,
  `supervisor_session_revoked_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. System & Branding Settings
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` VARCHAR(50) NOT NULL PRIMARY KEY,
  `company_name` VARCHAR(255) NOT NULL DEFAULT 'ULTIMATE SECURITY SERVICES',
  `tagline` VARCHAR(255) NOT NULL DEFAULT 'Total Security Solutions',
  `address_line` TEXT NULL,
  `phone` VARCHAR(100) NOT NULL DEFAULT '+971 4 251 0050',
  `email` VARCHAR(100) NOT NULL DEFAULT 'ops@ultimatesecurity.ae',
  `website` VARCHAR(150) NULL DEFAULT 'https://www.sprinzo.in',
  `logo_url` VARCHAR(255) NULL DEFAULT 'assets/images/logo.png',
  `timezone` VARCHAR(100) NOT NULL DEFAULT 'Asia/Dubai',
  `supervisor_remarks_min_length` INT NOT NULL DEFAULT 20,
  `supervisor_visit_config` LONGTEXT NULL,
  `shift_options` TEXT NULL,
  `cause_options` TEXT NULL,
  `disciplinary_options` TEXT NULL,
  `incident_options` TEXT NULL,
  `notified_options` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Patrol Sites Table
CREATE TABLE IF NOT EXISTS `sites` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `type` ENUM('Commercial', 'Residential', 'Hospital') NOT NULL DEFAULT 'Commercial',
  `address` TEXT NULL,
  `latitude` VARCHAR(100) NULL,
  `longitude` VARCHAR(100) NULL,
  `client_name` VARCHAR(150) NULL,
  `client_contact` VARCHAR(150) NULL,
  `active_qr_token` VARCHAR(100) NULL UNIQUE,
  `min_visits_per_week` INT NOT NULL DEFAULT 7,
  `min_night_visits_per_week` INT NOT NULL DEFAULT 2,
  `min_minutes_per_visit` INT NOT NULL DEFAULT 20,
  `max_minutes_per_visit` INT NOT NULL DEFAULT 120,
  `supervisor_qr_token` VARCHAR(100) NULL,
  `active_qr_token_hash` CHAR(64) NULL,
  `active_qr_token_encrypted` TEXT NULL,
  `supervisor_qr_token_hash` CHAR(64) NULL,
  `supervisor_qr_token_encrypted` TEXT NULL,
  `geofence_radius_meters` INT UNSIGNED NOT NULL DEFAULT 250,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_sites_code` (`code`),
  INDEX `idx_sites_qr` (`active_qr_token`),
  INDEX `idx_sites_sup_qr` (`supervisor_qr_token`),
  UNIQUE KEY `uniq_sites_active_qr_hash` (`active_qr_token_hash`),
  UNIQUE KEY `uniq_sites_supervisor_qr_hash` (`supervisor_qr_token_hash`),
  INDEX `idx_sites_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Security Guards (Staff) Table
CREATE TABLE IF NOT EXISTS `guards` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `staff_id` VARCHAR(50) NOT NULL UNIQUE,
  `full_name` VARCHAR(150) NOT NULL,
  `designation` VARCHAR(100) NOT NULL DEFAULT 'Security Guard',
  `is_supervisor` TINYINT(1) NOT NULL DEFAULT 0,
  `assigned_camp_id` INT UNSIGNED NULL,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_guards_username` (`username`),
  INDEX `idx_guards_staff_id` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Guard Site Assignments (Multi-Site Authorization)
CREATE TABLE IF NOT EXISTS `guard_site_assignments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `guard_id` INT UNSIGNED NOT NULL,
  `site_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_guard_site` (`guard_id`, `site_id`),
  INDEX `idx_assignment_guard` (`guard_id`),
  INDEX `idx_assignment_site` (`site_id`),
  CONSTRAINT `fk_assignment_guard` FOREIGN KEY (`guard_id`) REFERENCES `guards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignment_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Incident Reports Table
CREATE TABLE IF NOT EXISTS `incidents` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `ref_number` VARCHAR(100) NOT NULL UNIQUE,
  `idempotency_key` VARCHAR(100) NULL,
  `site_id` INT UNSIGNED NOT NULL,
  `site_name` VARCHAR(200) NOT NULL,
  `site_code` VARCHAR(50) NOT NULL,
  `guard_id` INT UNSIGNED NULL,
  `reported_by_name` VARCHAR(150) NOT NULL,
  `reported_by_designation` VARCHAR(100) NOT NULL,
  `incident_date` DATE NOT NULL,
  `incident_time` VARCHAR(10) NOT NULL,
  `shift` VARCHAR(50) NOT NULL DEFAULT 'Day',
  `priority` ENUM('Critical', 'High', 'Medium', 'Low') NOT NULL DEFAULT 'Medium',
  `cause_main` TEXT NULL,                -- JSON array e.g. ["Incident", "Disciplinary"]
  `disciplinary_reasons` TEXT NULL,      -- JSON array
  `disciplinary_other_text` TEXT NULL,
  `incident_types` TEXT NULL,            -- JSON array
  `incident_other_text` TEXT NULL,
  `summary_line` VARCHAR(255) NOT NULL,
  `description` LONGTEXT NOT NULL,
  `who_notified` TEXT NULL,              -- JSON array
  `notified_other_text` TEXT NULL,
  `status` ENUM('Submitted', 'Under Review', 'Follow-up Required', 'Resolved', 'Submitted to Client') NOT NULL DEFAULT 'Submitted',
  
  -- Official USS Management Sign-Off
  `site_supervisor_name` VARCHAR(150) NULL,
  `site_supervisor_id` VARCHAR(100) NULL,
  `site_supervisor_signature` TEXT NULL,
  `site_supervisor_date` DATE NULL,
  `recommendation_action_taken` TEXT NULL,
  `operations_manager_name` VARCHAR(150) NULL,
  `operations_manager_signature` TEXT NULL,
  `operations_manager_date` DATE NULL,
  `client_manager_action_taken` TEXT NULL,
  `client_manager_name` VARCHAR(150) NULL,
  `client_manager_signature` TEXT NULL,
  `client_manager_date` DATE NULL,
  
  `submitted_to_client_at` DATETIME NULL,
  `deleted_at` DATETIME NULL,            -- Soft-delete flag
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_incidents_site` (`site_id`),
  INDEX `idx_incidents_date` (`incident_date`),
  INDEX `idx_incidents_status` (`status`),
  INDEX `idx_incidents_priority` (`priority`),
  INDEX `idx_incidents_deleted` (`deleted_at`),
  INDEX `idx_incidents_ref` (`ref_number`),
  INDEX `idx_incidents_active_created` (`deleted_at`, `created_at`, `id`),
  INDEX `idx_incidents_guard_perf` (`guard_id`, `deleted_at`, `created_at`),
  INDEX `idx_incidents_site_perf` (`site_id`, `deleted_at`, `incident_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Incident Photos Table
CREATE TABLE IF NOT EXISTS `incident_photos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `incident_id` INT UNSIGNED NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `thumbnail_path` VARCHAR(255) NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
  `mime_type` VARCHAR(100) NOT NULL DEFAULT 'image/jpeg',
  `latitude` VARCHAR(50) NULL,
  `longitude` VARCHAR(50) NULL,
  `caption` VARCHAR(255) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_photos_incident` (`incident_id`),
  INDEX `idx_photos_incident_perf` (`incident_id`, `sort_order`, `id`),
  CONSTRAINT `fk_photos_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Incident Internal Follow-up Notes
CREATE TABLE IF NOT EXISTS `incident_notes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `incident_id` INT UNSIGNED NOT NULL,
  `admin_id` INT UNSIGNED NOT NULL,
  `admin_name` VARCHAR(150) NOT NULL,
  `note` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_notes_incident` (`incident_id`),
  CONSTRAINT `fk_notes_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Comprehensive Audit Logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `actor_type` VARCHAR(50) NOT NULL DEFAULT 'SUPER_ADMIN',
  `actor_id` VARCHAR(100) NULL,
  `actor_name` VARCHAR(150) NULL,
  `action` VARCHAR(100) NOT NULL,
  `target_type` VARCHAR(100) NULL,
  `target_id` VARCHAR(100) NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(50) NULL,
  `user_agent` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_audit_created` (`created_at`),
  INDEX `idx_audit_action` (`action`),
  INDEX `idx_audit_chain_perf` (`id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Database & System Backups Registry
CREATE TABLE IF NOT EXISTS `backups` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `filename` VARCHAR(255) NOT NULL,
  `filepath` VARCHAR(255) NOT NULL,
  `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `backup_type` ENUM('database', 'full') NOT NULL DEFAULT 'database',
  `created_by` VARCHAR(150) NOT NULL DEFAULT 'System Cron',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL DEFAULT NULL,
  INDEX `idx_backups_created` (`created_at`),
  INDEX `idx_backups_deleted` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Camps Table (Accommodations for Camp Out & Camp In)
CREATE TABLE IF NOT EXISTS `camps` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `camp_code` VARCHAR(50) NOT NULL UNIQUE,
  `camp_name` VARCHAR(200) NOT NULL,
  `location` TEXT NULL,
  `qr_token` VARCHAR(100) NULL UNIQUE,
  `qr_token_hash` CHAR(64) NULL UNIQUE,
  `qr_token_encrypted` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_camp_code` (`camp_code`),
  INDEX `idx_camp_token` (`qr_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Supervisor Duty Shifts (Camp Out -> Camp In)
CREATE TABLE IF NOT EXISTS `supervisor_duties` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `duty_ref` VARCHAR(100) NOT NULL UNIQUE,
  `supervisor_id` INT UNSIGNED NOT NULL,
  `camp_id` INT UNSIGNED NOT NULL,
  `duty_date` DATE NOT NULL,
  `shift_type` ENUM('Day', 'Night') NOT NULL DEFAULT 'Day',
  `camp_out_time` DATETIME NOT NULL,
  `client_camp_out_time` DATETIME NULL,
  `camp_in_time` DATETIME NULL,
  `client_camp_in_time` DATETIME NULL,
  `status` ENUM('Active', 'Completed') NOT NULL DEFAULT 'Active',
  `total_sites` INT NOT NULL DEFAULT 0,
  `total_minutes` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_duty_sup_date` (`supervisor_id`, `duty_date`),
  INDEX `idx_duty_status` (`status`),
  CONSTRAINT `fk_duty_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `guards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_duty_camp` FOREIGN KEY (`camp_id`) REFERENCES `camps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Supervisor Site Visits Table (Time In to Time Out with Mandatory Remarks, Report & Photos)
CREATE TABLE IF NOT EXISTS `supervisor_visits` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `visit_ref` VARCHAR(100) NULL UNIQUE,
  `duty_id` INT UNSIGNED NOT NULL,
  `supervisor_id` INT UNSIGNED NOT NULL,
  `site_id` INT UNSIGNED NOT NULL,
  `time_in` DATETIME NOT NULL,
  `time_out` DATETIME NULL,
  `minutes_spent` INT NOT NULL DEFAULT 0,
  `travel_minutes_from_previous` INT NOT NULL DEFAULT 0,
  `remarks` TEXT NULL,
  `purpose_of_visit` VARCHAR(50) NOT NULL DEFAULT 'Routine',
  `guards_count` INT NOT NULL DEFAULT 0,
  `guards_attendance` VARCHAR(50) NOT NULL DEFAULT 'All present',
  `guards_names` TEXT NULL,
  `rating_turnout` VARCHAR(20) NOT NULL DEFAULT 'OK',
  `rating_records` VARCHAR(20) NOT NULL DEFAULT 'OK',
  `rating_condition` VARCHAR(20) NOT NULL DEFAULT 'OK',
  `rating_equipment` VARCHAR(20) NOT NULL DEFAULT 'OK',
  `has_issues` TINYINT(1) NOT NULL DEFAULT 0,
  `issues_action_taken` TEXT NULL,
  `site_report` TEXT NULL,
  `manual_timeout_requested` TINYINT(1) NOT NULL DEFAULT 0,
  `manual_timeout_time` DATETIME NULL,
  `manual_timeout_reason` TEXT NULL,
  `manual_timeout_remarks` TEXT NULL,
  `manual_timeout_status` ENUM('None', 'Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'None',
  `corrected_by_admin_id` INT UNSIGNED NULL,
  `correction_notes` TEXT NULL,
  `status` ENUM('In Progress', 'Completed', 'Manual correction (pending)') NOT NULL DEFAULT 'In Progress',
  `is_draft` TINYINT(1) NOT NULL DEFAULT 0,
  `is_offline_sync` TINYINT(1) NOT NULL DEFAULT 0,
  `client_scan_time` DATETIME NULL,
  `corrected_time_in` DATETIME NULL,
  `corrected_time_out` DATETIME NULL,
  `correction_version` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_visit_duty` (`duty_id`),
  INDEX `idx_visit_sup` (`supervisor_id`, `time_in`),
  INDEX `idx_visit_site` (`site_id`, `time_in`),
  INDEX `idx_visit_status` (`status`),
  CONSTRAINT `fk_visit_duty` FOREIGN KEY (`duty_id`) REFERENCES `supervisor_duties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visit_sup` FOREIGN KEY (`supervisor_id`) REFERENCES `guards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visit_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Supervisor Visit Photos Table (Up to 5 Inspection Photos)
CREATE TABLE IF NOT EXISTS `supervisor_visit_photos` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `visit_id` INT UNSIGNED NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `file_size` INT UNSIGNED NOT NULL DEFAULT 0,
  `caption` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_visit_photos_visit` (`visit_id`),
  CONSTRAINT `fk_visit_photos_visit` FOREIGN KEY (`visit_id`) REFERENCES `supervisor_visits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Supervisor Schedules Table (Weekly Templates & Date Overrides)
CREATE TABLE IF NOT EXISTS `supervisor_schedules` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `supervisor_id` INT UNSIGNED NOT NULL,
  `site_id` INT UNSIGNED NOT NULL,
  `day_of_week` TINYINT UNSIGNED NULL COMMENT '1=Monday...7=Sunday for recurring weekly template',
  `specific_date` DATE NULL COMMENT 'Date-specific override if set',
  `shift_type` ENUM('Day', 'Night') NOT NULL DEFAULT 'Day',
  `expected_time_start` TIME NULL,
  `expected_time_end` TIME NULL,
  `min_minutes` INT NOT NULL DEFAULT 30,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_sched_sup_day` (`supervisor_id`, `day_of_week`),
  INDEX `idx_sched_sup_date` (`supervisor_id`, `specific_date`),
  CONSTRAINT `fk_sched_sup` FOREIGN KEY (`supervisor_id`) REFERENCES `guards` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sched_site` FOREIGN KEY (`site_id`) REFERENCES `sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Supervisor Alerts & Exceptions Table
CREATE TABLE IF NOT EXISTS `supervisor_alerts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `supervisor_id` INT UNSIGNED NOT NULL,
  `duty_id` INT UNSIGNED NULL,
  `visit_id` INT UNSIGNED NULL,
  `site_id` INT UNSIGNED NULL,
  `alert_type` VARCHAR(50) NOT NULL COMMENT 'VISIT_TOO_SHORT, VISIT_TOO_LONG, MISSED_PLANNED_VISIT, DUTY_NOT_STARTED, DUTY_NOT_ENDED, UNASSIGNED_SITE, OFFLINE_SCAN',
  `severity` ENUM('Info', 'Warning', 'Critical') NOT NULL DEFAULT 'Warning',
  `details` TEXT NOT NULL,
  `is_acknowledged` TINYINT(1) NOT NULL DEFAULT 0,
  `acknowledged_by` VARCHAR(100) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_alerts_sup` (`supervisor_id`),
  INDEX `idx_alerts_ack` (`is_acknowledged`),
  INDEX `idx_alerts_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Idempotent scan receipts and client/server timestamp comparison
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

-- 18. Immutable before/after history for Super Admin corrections
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

-- 19. Date-specific leave and supervisor/site swaps
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

-- 20. Admin-configurable report fields; values are stored in supervisor_visits.site_report
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

-- 21. Admin-triggered bulk report generation and immutable issued copies
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

SET FOREIGN_KEY_CHECKS = 1;

-- ====================================================================
-- INITIAL CONFIGURATION
-- ====================================================================

-- Default Settings
INSERT INTO `system_settings` (`id`, `company_name`, `tagline`, `address_line`, `phone`, `email`, `website`, `logo_url`, `timezone`, `supervisor_remarks_min_length`, `shift_options`, `cause_options`, `disciplinary_options`, `incident_options`, `notified_options`)
VALUES (
  'default-settings',
  'ULTIMATE SECURITY SERVICES',
  'Total Security Solutions',
  'P O Box No: 61591, Al Maktoum Street | Dubai | UAE',
  '+971 4 251 0050',
  'ops@ultimatesecurity.ae',
  'https://www.sprinzo.in',
  'assets/images/logo.png',
  'Asia/Dubai',
  20,
  '["Day", "Night"]',
  '["Incident", "Disciplinary", "Others"]',
  '["Sleeping", "Poor Grooming", "Argument", "Missing", "Misbehavior", "Uniform", "Late Reporting", "Mobile", "Others"]',
  '["Move In & Out", "Fire & Safety", "Intrusion & Access", "Theft and loss", "CCTV & Systems", "Utilities & Building", "Accident & Medical", "Disturbance", "Property Damage", "Parking & Vehicles", "Visitors & Contractors", "Others"]',
  '["Police", "Ambulance", "Civil Defense", "Client Management", "USS Management", "Others"]'
) ON DUPLICATE KEY UPDATE `updated_at` = CURRENT_TIMESTAMP;
