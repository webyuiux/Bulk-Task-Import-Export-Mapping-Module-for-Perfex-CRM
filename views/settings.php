<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php echo render_yes_no_option('bulk_task_import_enabled', _l('bulk_task_import_enable')); ?>
<?php echo render_input('bulk_task_import_max_file_size', _l('bulk_task_import_max_file_size'), get_option('bulk_task_import_max_file_size'), 'number', ['min' => 1]); ?>
<?php echo render_input('bulk_task_import_batch_size', _l('bulk_task_import_batch_size'), get_option('bulk_task_import_batch_size'), 'number', ['min' => 1]); ?>
<?php echo render_yes_no_option('bulk_task_import_duplicate_detection', _l('bulk_task_import_duplicate_detection')); ?>
<?php echo render_select('bulk_task_import_duplicate_action', [['id' => 'skip', 'name' => _l('bulk_task_import_skip')], ['id' => 'warn', 'name' => _l('bulk_task_import_warn')], ['id' => 'import', 'name' => _l('bulk_task_import_import')]], ['id', 'name'], _l('bulk_task_import_duplicate_action'), get_option('bulk_task_import_duplicate_action')); ?>
<?php echo render_yes_no_option('bulk_task_import_allow_csv', _l('bulk_task_import_allow_csv')); ?>
<?php echo render_yes_no_option('bulk_task_import_allow_xlsx', _l('bulk_task_import_allow_xlsx')); ?>