-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: uss_attendance
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


--
-- Table structure for table `att_audit`
--

DROP TABLE IF EXISTS `att_audit`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `att_audit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `prev_hash` varchar(64) NOT NULL DEFAULT 'GENESIS_ATTENDANCE_HASH',
  `record_hash` varchar(64) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(80) NOT NULL,
  `entity_id` varchar(80) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `actor_name` varchar(150) NOT NULL DEFAULT 'Super Admin',
  `ip_address` varchar(60) NOT NULL DEFAULT '127.0.0.1',
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_att_audit_action` (`action`),
  KEY `idx_att_audit_entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `att_audit`
--

LOCK TABLES `att_audit` WRITE;
/*!40000 ALTER TABLE `att_audit` DISABLE KEYS */;
INSERT INTO `att_audit` VALUES (1,'GENESIS_ATTENDANCE_HASH','77b49ba0586f84996bc1a9f35ce1101f451f5f560ca2e2ddcd6ddd31ff4fb200','SITE_CREATE','att_sites','1','{\"name\":\"Danat Al Rolla Building\",\"code\":\"DANATALROLLA\"}','Administrator','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-23 20:03:37'),(2,'77b49ba0586f84996bc1a9f35ce1101f451f5f560ca2e2ddcd6ddd31ff4fb200','b65534c1309f25f5d6aeb2dc44e0faca6a9707a436113f91946bf916af5b0eb0','SHEET_CREATE','att_sheets','1','{\"site_id\":1,\"year\":2026,\"month\":10}','Super Admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-23 20:03:37'),(3,'b65534c1309f25f5d6aeb2dc44e0faca6a9707a436113f91946bf916af5b0eb0','2b3e8dff15c603c4c1ee6d3f797f38380f1549e4ec8918d0d54b69d8a8903a5b','STAFF_UPSERT','att_staff','SG1001','{\"name\":\"AUSAF AHMED\",\"designation\":\"Security Supervisor\"}','Administrator','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-23 20:04:17'),(4,'2b3e8dff15c603c4c1ee6d3f797f38380f1549e4ec8918d0d54b69d8a8903a5b','10d54ea9e6320141a255f27457ca21122c686eac7c0c9879259b3dcdd82010a8','STAFF_UPSERT','att_staff','SG1002','{\"name\":\"RANA RIZWAN\",\"designation\":\"Security Officer\"}','Administrator','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-23 20:23:42'),(5,'10d54ea9e6320141a255f27457ca21122c686eac7c0c9879259b3dcdd82010a8','2fac51fcc38b4f816b5e496d14d241bd872a4584d6a0ad96055f36393488f8c2','STAFF_UPSERT','att_staff','SG1003','{\"name\":\"KASHEM FORAZI\",\"designation\":\"Security Officer\"}','Administrator','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-23 20:24:00'),(6,'2fac51fcc38b4f816b5e496d14d241bd872a4584d6a0ad96055f36393488f8c2','4b8575d66e413834eae23fe9dce736a2450cf53346779b60c9881422be14abdc','SITE_CREATE','att_sites','2','{\"name\":\"NASA Building\",\"code\":\"NASABUILDI\"}','Administrator','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-23 20:36:25'),(7,'4b8575d66e413834eae23fe9dce736a2450cf53346779b60c9881422be14abdc','0f256e784a343482459c6e86fe3d6e6616b8a5b8b43a557f716492b11f87541a','SHEET_CREATE','att_sheets','2','{\"site_id\":2,\"year\":2026,\"month\":10}','Super Admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-23 20:36:25'),(8,'0f256e784a343482459c6e86fe3d6e6616b8a5b8b43a557f716492b11f87541a','c711fc9db47b20508f7af2126c7a2f080c631562e4b66c1ea381ece6a2c132ba','STAFF_UPSERT','att_staff','SG1008','{\"name\":\"SELVARAJ DAVDOSS\",\"designation\":\"Security Officer\"}','Administrator','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-23 20:49:03'),(9,'c711fc9db47b20508f7af2126c7a2f080c631562e4b66c1ea381ece6a2c132ba','b7689045823fa3499a93c927ddf52df16ce2ae2abc8954c4ca2f614b653af18e','SHEET_CREATE','att_sheets','3','{\"site_id\":1,\"year\":2026,\"month\":11}','Super Admin','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-23 21:25:43'),(10,'b7689045823fa3499a93c927ddf52df16ce2ae2abc8954c4ca2f614b653af18e','db9799164787b29f50e3872a73d2937103d668ea66279b239f2a7851521d4126','STAFF_UPSERT','att_staff','SG1001','{\"name\":\"AUSAF AHMED\",\"designation\":\"Security Officer\"}','Administrator','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/155.0.0.0 Safari/537.36','2026-10-23 21:47:49');
/*!40000 ALTER TABLE `att_audit` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `att_cells`
--

DROP TABLE IF EXISTS `att_cells`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `att_cells` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `row_id` int(10) unsigned NOT NULL,
  `day_number` tinyint(3) unsigned NOT NULL,
  `code` varchar(5) NOT NULL DEFAULT '',
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cell_row_day` (`row_id`,`day_number`),
  KEY `idx_att_cells_code` (`code`),
  CONSTRAINT `fk_att_cells_row` FOREIGN KEY (`row_id`) REFERENCES `att_rows` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=172 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `att_cells`
--

LOCK TABLES `att_cells` WRITE;
/*!40000 ALTER TABLE `att_cells` DISABLE KEYS */;
INSERT INTO `att_cells` VALUES (1,1,1,'D','2026-10-23 20:12:13'),(2,1,2,'D','2026-10-23 20:12:14'),(3,1,3,'D','2026-10-23 20:12:14'),(4,1,4,'D','2026-10-23 20:34:15'),(5,1,5,'D','2026-10-23 20:12:16'),(6,1,6,'D','2026-10-23 20:34:15'),(7,1,7,'D','2026-10-23 20:34:15'),(8,1,8,'D','2026-10-23 20:34:16'),(9,1,9,'D','2026-10-23 20:34:16'),(10,1,10,'D','2026-10-23 20:34:16'),(11,1,11,'D','2026-10-23 20:34:16'),(12,1,12,'D','2026-10-23 20:34:16'),(13,1,13,'D','2026-10-23 20:34:17'),(14,1,14,'D','2026-10-23 20:34:17'),(15,1,15,'S','2026-10-23 20:34:46'),(16,1,16,'S','2026-10-23 20:34:48'),(17,1,17,'S','2026-10-23 20:34:48'),(18,1,18,'S','2026-10-23 20:34:48'),(19,1,19,'S','2026-10-23 20:34:49'),(20,1,20,'S','2026-10-23 20:34:49'),(21,1,21,'S','2026-10-23 20:34:50'),(22,1,22,'D','2026-10-23 20:35:19'),(23,1,23,'D','2026-10-23 20:34:19'),(24,1,24,'B','2026-10-23 20:34:56'),(25,1,25,'B','2026-10-23 20:34:56'),(26,1,26,'B','2026-10-23 20:34:56'),(27,1,27,'B','2026-10-23 20:34:57'),(28,1,28,'D','2026-10-23 20:12:29'),(29,1,29,'D','2026-10-23 20:12:30'),(30,1,30,'D','2026-10-23 20:12:30'),(31,1,31,'D','2026-10-23 20:12:31'),(38,2,1,'N','2026-10-23 20:47:36'),(39,2,2,'N','2026-10-23 20:33:49'),(40,2,3,'N','2026-10-23 20:47:39'),(41,2,4,'N','2026-10-23 20:33:50'),(42,2,5,'N','2026-10-23 20:47:40'),(43,2,6,'N','2026-10-23 20:33:51'),(44,2,7,'N','2026-10-23 20:47:40'),(45,2,8,'N','2026-10-23 20:33:51'),(46,2,9,'OFF','2026-10-23 20:47:42'),(47,2,10,'N','2026-10-23 20:33:51'),(48,2,11,'N','2026-10-23 20:47:44'),(49,2,12,'N','2026-10-23 20:33:52'),(50,2,13,'N','2026-10-23 20:47:44'),(51,2,14,'N','2026-10-23 20:33:52'),(52,2,15,'N','2026-10-23 20:47:45'),(53,2,16,'OFF','2026-10-23 20:47:45'),(54,2,17,'N','2026-10-23 20:47:46'),(55,2,18,'N','2026-10-23 20:33:53'),(56,2,19,'N','2026-10-23 20:47:47'),(57,2,20,'N','2026-10-23 20:47:47'),(58,2,21,'N','2026-10-23 20:47:47'),(59,2,22,'OFF','2026-10-23 20:47:48'),(60,2,23,'N','2026-10-23 20:47:49'),(61,2,24,'N','2026-10-23 20:47:49'),(62,2,25,'N','2026-10-23 20:47:49'),(63,2,26,'N','2026-10-23 20:47:49'),(64,2,27,'OFF','2026-10-23 20:47:51'),(65,2,28,'N','2026-10-23 20:47:52'),(66,2,29,'N','2026-10-23 20:47:52'),(67,2,30,'N','2026-10-23 20:47:53'),(68,2,31,'N','2026-10-23 20:47:53'),(152,3,15,'D','2026-10-23 20:48:17'),(153,3,17,'D','2026-10-23 20:48:18'),(154,3,16,'D','2026-10-23 20:48:19'),(156,3,18,'D','2026-10-23 20:48:20'),(157,3,19,'D','2026-10-23 20:48:21'),(158,3,20,'D','2026-10-23 20:48:22'),(159,3,21,'D','2026-10-23 20:48:23'),(160,3,22,'N','2026-10-23 20:48:28'),(161,3,23,'OFF','2026-10-23 20:48:30'),(162,3,24,'D','2026-10-23 20:48:32'),(163,3,25,'D','2026-10-23 20:48:32'),(164,3,26,'D','2026-10-23 20:48:32'),(165,3,27,'D','2026-10-23 20:48:37'),(166,4,27,'N','2026-10-23 20:49:20'),(167,3,9,'N','2026-10-23 21:03:51'),(168,4,16,'N','2026-10-23 21:04:04'),(169,5,1,'D','2026-10-23 21:47:55'),(170,5,2,'D','2026-10-23 21:47:56'),(171,5,3,'D','2026-10-23 21:47:56');
/*!40000 ALTER TABLE `att_cells` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `att_payroll_columns`
--

DROP TABLE IF EXISTS `att_payroll_columns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `att_payroll_columns` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `col_name` varchar(100) NOT NULL,
  `col_type` enum('text','number') NOT NULL DEFAULT 'number',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `att_payroll_columns`
--

LOCK TABLES `att_payroll_columns` WRITE;
/*!40000 ALTER TABLE `att_payroll_columns` DISABLE KEYS */;
INSERT INTO `att_payroll_columns` VALUES (3,'Basic Salary','text',1,1,'2026-10-23 21:41:20');
/*!40000 ALTER TABLE `att_payroll_columns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `att_payroll_remarks`
--

DROP TABLE IF EXISTS `att_payroll_remarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `att_payroll_remarks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `period_key` varchar(50) NOT NULL,
  `emp_no` varchar(60) NOT NULL,
  `remarks` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payroll_period_emp_rem` (`period_key`,`emp_no`),
  KEY `idx_payroll_rem_period` (`period_key`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `att_payroll_remarks`
--

LOCK TABLES `att_payroll_remarks` WRITE;
/*!40000 ALTER TABLE `att_payroll_remarks` DISABLE KEYS */;
INSERT INTO `att_payroll_remarks` VALUES (1,'2026-10-01_2026-10-31','SG1001','Basic salary aed. 1200/- for 28/29 days including sundays and public holidays','2026-10-23 21:22:22');
/*!40000 ALTER TABLE `att_payroll_remarks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `att_payroll_values`
--

DROP TABLE IF EXISTS `att_payroll_values`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `att_payroll_values` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `period_key` varchar(50) NOT NULL,
  `emp_no` varchar(60) NOT NULL,
  `column_id` int(10) unsigned NOT NULL,
  `val_text` varchar(255) DEFAULT NULL,
  `val_number` decimal(12,2) DEFAULT 0.00,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payroll_period_emp_col` (`period_key`,`emp_no`,`column_id`),
  KEY `idx_payroll_period` (`period_key`),
  KEY `fk_att_pval_col` (`column_id`),
  CONSTRAINT `fk_att_pval_col` FOREIGN KEY (`column_id`) REFERENCES `att_payroll_columns` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `att_payroll_values`
--

LOCK TABLES `att_payroll_values` WRITE;
/*!40000 ALTER TABLE `att_payroll_values` DISABLE KEYS */;
INSERT INTO `att_payroll_values` VALUES (1,'2026-10-01_2026-10-31','SG1001',3,'1200',NULL,'2026-10-23 21:41:34');
/*!40000 ALTER TABLE `att_payroll_values` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `att_rows`
--

DROP TABLE IF EXISTS `att_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `att_rows` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sheet_id` int(10) unsigned NOT NULL,
  `emp_no` varchar(60) NOT NULL,
  `emp_name_snapshot` varchar(180) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_row_sheet_emp` (`sheet_id`,`emp_no`),
  KEY `idx_att_rows_sheet` (`sheet_id`,`sort_order`),
  CONSTRAINT `fk_att_rows_sheet` FOREIGN KEY (`sheet_id`) REFERENCES `att_sheets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `att_rows`
--

LOCK TABLES `att_rows` WRITE;
/*!40000 ALTER TABLE `att_rows` DISABLE KEYS */;
INSERT INTO `att_rows` VALUES (1,1,'SG1001','AUSAF AHMED',1,0,'2026-10-23 20:04:17','2026-10-23 20:04:17'),(2,1,'SG1002','RANA RIZWAN',2,0,'2026-10-23 20:23:42','2026-10-23 20:23:42'),(3,1,'SG1003','KASHEM FORAZI',3,0,'2026-10-23 20:24:00','2026-10-23 20:24:00'),(4,1,'SG1008','SELVARAJ DAVDOSS',4,0,'2026-10-23 20:49:03','2026-10-23 20:49:03'),(5,2,'SG1001','AUSAF AHMED',1,0,'2026-10-23 21:47:49','2026-10-23 21:47:49');
/*!40000 ALTER TABLE `att_rows` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `att_settings`
--

DROP TABLE IF EXISTS `att_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `att_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_val` text DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `att_settings`
--

LOCK TABLES `att_settings` WRITE;
/*!40000 ALTER TABLE `att_settings` DISABLE KEYS */;
INSERT INTO `att_settings` VALUES (1,'company_name','ULTIMATE SECURITY SERVICES','2026-10-23 19:23:07'),(2,'company_tagline','SECURITY OPERATIONS & MANPOWER MANAGEMENT','2026-10-23 19:23:07'),(3,'currency','AED','2026-10-23 19:23:07'),(4,'default_day_hours','12','2026-10-23 19:23:07'),(5,'payroll_cycle_start','27','2026-10-23 19:23:07'),(6,'payroll_cycle_end','26','2026-10-23 19:23:07');
/*!40000 ALTER TABLE `att_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `att_sheets`
--

DROP TABLE IF EXISTS `att_sheets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `att_sheets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int(10) unsigned NOT NULL,
  `year` smallint(5) unsigned NOT NULL,
  `month` tinyint(3) unsigned NOT NULL,
  `status` enum('Open','Locked') NOT NULL DEFAULT 'Open',
  `locked_by` varchar(100) DEFAULT NULL,
  `lock_reason` varchar(255) DEFAULT NULL,
  `locked_at` datetime DEFAULT NULL,
  `version` int(10) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_sheet_site_period` (`site_id`,`year`,`month`),
  KEY `idx_att_sheets_period` (`year`,`month`),
  CONSTRAINT `fk_att_sheets_site` FOREIGN KEY (`site_id`) REFERENCES `att_sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `att_sheets`
--

LOCK TABLES `att_sheets` WRITE;
/*!40000 ALTER TABLE `att_sheets` DISABLE KEYS */;
INSERT INTO `att_sheets` VALUES (1,1,2026,10,'Open',NULL,NULL,NULL,169,'2026-10-23 20:03:37','2026-10-23 21:04:04'),(2,2,2026,10,'Open',NULL,NULL,NULL,4,'2026-10-23 20:36:25','2026-10-23 21:47:56'),(3,1,2026,11,'Open',NULL,NULL,NULL,1,'2026-10-23 21:25:43','2026-10-23 21:25:43');
/*!40000 ALTER TABLE `att_sheets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `att_site_assignments`
--

DROP TABLE IF EXISTS `att_site_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `att_site_assignments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `emp_no` varchar(60) NOT NULL,
  `site_id` int(10) unsigned NOT NULL,
  `date_from` date NOT NULL,
  `date_to` date NOT NULL,
  `default_code` enum('D','N','B') NOT NULL DEFAULT 'D',
  `is_conflict_override` tinyint(1) NOT NULL DEFAULT 0,
  `override_reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_att_assign_emp` (`emp_no`,`date_from`,`date_to`),
  KEY `idx_att_assign_site` (`site_id`),
  CONSTRAINT `fk_att_assign_site` FOREIGN KEY (`site_id`) REFERENCES `att_sites` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `att_site_assignments`
--

LOCK TABLES `att_site_assignments` WRITE;
/*!40000 ALTER TABLE `att_site_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `att_site_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `att_sites`
--

DROP TABLE IF EXISTS `att_sites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `att_sites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_name` varchar(180) NOT NULL,
  `site_code` varchar(50) NOT NULL,
  `client_name` varchar(180) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `site_code` (`site_code`),
  KEY `idx_att_sites_active` (`is_active`),
  KEY `idx_att_sites_name` (`site_name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `att_sites`
--

LOCK TABLES `att_sites` WRITE;
/*!40000 ALTER TABLE `att_sites` DISABLE KEYS */;
INSERT INTO `att_sites` VALUES (1,'Danat Al Rolla Building','DANATALROLLA','',1,'2026-10-23 20:03:37','2026-10-23 20:03:37'),(2,'NASA Building','NASABUILDI','',1,'2026-10-23 20:36:25','2026-10-23 20:36:25');
/*!40000 ALTER TABLE `att_sites` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `att_staff`
--

DROP TABLE IF EXISTS `att_staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `att_staff` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `emp_no` varchar(60) NOT NULL,
  `emp_name` varchar(180) NOT NULL,
  `designation` varchar(100) NOT NULL DEFAULT 'Security Officer',
  `mobile_phone` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `emp_no` (`emp_no`),
  KEY `idx_att_staff_emp_no` (`emp_no`),
  KEY `idx_att_staff_name` (`emp_name`),
  KEY `idx_att_staff_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `att_staff`
--

LOCK TABLES `att_staff` WRITE;
/*!40000 ALTER TABLE `att_staff` DISABLE KEYS */;
INSERT INTO `att_staff` VALUES (1,'SG1001','AUSAF AHMED','Security Officer','',1,'2026-10-23 20:04:17','2026-10-23 21:47:49'),(2,'SG1002','RANA RIZWAN','Security Officer','',1,'2026-10-23 20:23:42','2026-10-23 20:23:42'),(3,'SG1003','KASHEM FORAZI','Security Officer','',1,'2026-10-23 20:24:00','2026-10-23 20:24:00'),(4,'SG1008','SELVARAJ DAVDOSS','Security Officer','',1,'2026-10-23 20:49:03','2026-10-23 20:49:03');
/*!40000 ALTER TABLE `att_staff` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping routines for database 'uss_attendance'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-24 15:35:18
