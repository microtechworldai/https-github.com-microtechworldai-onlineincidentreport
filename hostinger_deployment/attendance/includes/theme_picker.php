<?php
/**
 * Attendance Module Theme Picker - Delegates to central theme picker or standalone copy
 */
if (file_exists(__DIR__ . '/../../includes/theme_picker.php')) {
    require_once __DIR__ . '/../../includes/theme_picker.php';
} else {
    require_once __DIR__ . '/../../../includes/theme_picker.php';
}
