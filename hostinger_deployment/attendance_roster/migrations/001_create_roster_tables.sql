-- ============================================================================
-- Universal Security Services - Attendance Roster Subsystem
-- Migration 001: Create Additive roster_* Tables
-- File: /attendance_roster/migrations/001_create_roster_tables.sql
-- ============================================================================

CREATE TABLE IF NOT EXISTS `roster_staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `emp_no` varchar(50) NOT NULL,
  `name` varchar(180) NOT NULL,
  `designation` varchar(100) NOT NULL DEFAULT 'Security Guard',
  `user_id` int(10) unsigned DEFAULT NULL,
  `sira_expiry` date DEFAULT NULL,
  `visa_expiry` date DEFAULT NULL,
  `passport_expiry` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_roster_emp_no` (`emp_no`),
  KEY `idx_roster_staff_active` (`is_active`),
  KEY `idx_roster_staff_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roster_sites_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `site_name` varchar(180) NOT NULL,
  `site_code` varchar(50) DEFAULT NULL,
  `client_name` varchar(180) DEFAULT NULL,
  `req_day_base` int(11) NOT NULL DEFAULT 1,
  `req_night_base` int(11) NOT NULL DEFAULT 1,
  `effective_from` date DEFAULT NULL,
  `effective_to` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_roster_sites_site` (`site_id`),
  KEY `idx_roster_sites_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roster_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date DEFAULT NULL,
  `created_by` varchar(100) NOT NULL DEFAULT 'Super Admin',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_assign_staff` (`staff_id`),
  KEY `idx_assign_site` (`site_id`),
  KEY `idx_assign_range` (`date_from`, `date_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roster_entries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `work_date` date NOT NULL,
  `code` varchar(10) NOT NULL,
  `is_forced_double` tinyint(1) NOT NULL DEFAULT 0,
  `force_reason` text DEFAULT NULL,
  `moved_from_site_id` int(11) DEFAULT NULL,
  `moved_from_entry_id` int(11) DEFAULT NULL,
  `source` varchar(50) NOT NULL DEFAULT 'manual',
  `created_by` varchar(100) NOT NULL DEFAULT 'Super Admin',
  `updated_by` varchar(100) NOT NULL DEFAULT 'Super Admin',
  `version` int(11) NOT NULL DEFAULT 1,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_roster_staff_date_work` (`staff_id`, `work_date`, `is_forced_double`),
  KEY `idx_roster_site_date` (`site_id`, `work_date`),
  KEY `idx_roster_work_date` (`work_date`),
  KEY `idx_roster_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roster_additional_deployments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL,
  `shift` enum('D','N') NOT NULL,
  `staff_count` int(11) NOT NULL DEFAULT 1,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `reason` text NOT NULL,
  `approved_by` varchar(100) NOT NULL DEFAULT 'Super Admin',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_add_site_dates` (`site_id`, `date_from`, `date_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roster_sheet_status` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `month` int(11) NOT NULL,
  `status` enum('Draft','Checked','Approved','Locked') NOT NULL DEFAULT 'Draft',
  `checked_by` varchar(100) DEFAULT NULL,
  `checked_at` datetime DEFAULT NULL,
  `approved_by` varchar(100) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `locked_by` varchar(100) DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `lock_reason` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_roster_sheet_site_ym` (`site_id`, `year`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roster_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action_type` varchar(50) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `site_id` int(11) DEFAULT NULL,
  `work_date` date DEFAULT NULL,
  `before_state` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_state`)),
  `after_state` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_state`)),
  `reason` text DEFAULT NULL,
  `undo_token` varchar(64) DEFAULT NULL,
  `is_undone` tinyint(1) NOT NULL DEFAULT 0,
  `performed_by` varchar(100) NOT NULL DEFAULT 'Super Admin',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_roster_undo` (`undo_token`),
  KEY `idx_roster_audit_date` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roster_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `site_id` int(11) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `alert_date` date NOT NULL,
  `alert_type` varchar(50) NOT NULL,
  `severity` enum('INFO','WARNING','CRITICAL') NOT NULL DEFAULT 'WARNING',
  `message` text NOT NULL,
  `is_resolved` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_roster_alerts_date` (`alert_date`),
  KEY `idx_roster_alerts_res` (`is_resolved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

