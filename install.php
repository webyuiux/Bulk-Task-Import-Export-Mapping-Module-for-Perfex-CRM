<?php

defined('BASEPATH') or exit('No direct script access allowed');

$CI = &get_instance();
$charset = $CI->db->char_set ?: 'utf8mb4';
$collation = $CI->db->dbcollat ?: $charset . '_general_ci';

if (!$CI->db->table_exists(db_prefix() . 'bulk_task_import_batches')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'bulk_task_import_batches` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `file_name` VARCHAR(255) NOT NULL,
        `uploaded_by` INT UNSIGNED NOT NULL,
        `total_rows` INT UNSIGNED NOT NULL DEFAULT 0,
        `successful_rows` INT UNSIGNED NOT NULL DEFAULT 0,
        `failed_rows` INT UNSIGNED NOT NULL DEFAULT 0,
        `skipped_rows` INT UNSIGNED NOT NULL DEFAULT 0,
        `duplicate_rows` INT UNSIGNED NOT NULL DEFAULT 0,
        `status` VARCHAR(30) NOT NULL DEFAULT "uploaded",
        `error_report` LONGTEXT NULL,
        `created_at` DATETIME NOT NULL,
        `completed_at` DATETIME NULL,
        PRIMARY KEY (`id`),
        KEY `uploaded_by` (`uploaded_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ' COLLATE=' . $collation);
}

if (!$CI->db->table_exists(db_prefix() . 'bulk_task_import_items')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . 'bulk_task_import_items` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `batch_id` INT UNSIGNED NOT NULL,
        `task_id` INT UNSIGNED NULL,
        `row_number` INT UNSIGNED NOT NULL,
        `status` VARCHAR(30) NOT NULL,
        `error_message` TEXT NULL,
        `created_at` DATETIME NOT NULL,
        PRIMARY KEY (`id`),
        KEY `batch_id` (`batch_id`),
        KEY `task_id` (`task_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ' COLLATE=' . $collation);
}

add_option('bulk_task_import_enabled', '1', 0);
add_option('bulk_task_import_max_file_size', '10', 0);
add_option('bulk_task_import_batch_size', '100', 0);
add_option('bulk_task_import_duplicate_detection', '1', 0);
add_option('bulk_task_import_duplicate_action', 'skip', 0);
add_option('bulk_task_import_allow_csv', '1', 0);
add_option('bulk_task_import_allow_xlsx', '1', 0);
add_option('bulk_task_import_delete_data_on_uninstall', '0', 0);