-- ============================================================================
-- Universal Security Services - Attendance Roster Subsystem
-- Migration Rollback 001: Safely drop roster_* Tables
-- File: /attendance_roster/migrations/001_rollback_roster_tables.sql
-- ============================================================================

DROP TABLE IF EXISTS `roster_alerts`;
DROP TABLE IF EXISTS `roster_audit`;
DROP TABLE IF EXISTS `roster_sheet_status`;
DROP TABLE IF EXISTS `roster_additional_deployments`;
DROP TABLE IF EXISTS `roster_entries`;
DROP TABLE IF EXISTS `roster_assignments`;
DROP TABLE IF EXISTS `roster_sites_config`;
DROP TABLE IF EXISTS `roster_staff`;
