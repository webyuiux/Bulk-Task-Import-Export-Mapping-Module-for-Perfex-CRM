<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Bulk Task Import
Description: Import CSV and XLSX task files into normal Perfex CRM tasks.
Version: 1.0.0
Author: Vaibhav Kondekar
*/

define('BULK_TASK_IMPORT_MODULE_NAME', 'bulk_task_import');
define('BULK_TASK_IMPORT_MODULE_PATH', module_dir_path(BULK_TASK_IMPORT_MODULE_NAME));

hooks()->add_action('admin_init', 'bulk_task_import_register_permissions');
// hooks()->add_action('admin_init', 'bulk_task_import_register_menu');
hooks()->add_action('admin_init', 'bulk_task_import_register_settings');
hooks()->add_action('app_admin_head', 'bulk_task_import_enqueue_styles');
hooks()->add_action('app_admin_footer', 'bulk_task_import_enqueue_scripts');

register_language_files(BULK_TASK_IMPORT_MODULE_NAME, ['bulk_task_import']);

register_activation_hook(BULK_TASK_IMPORT_MODULE_NAME, 'bulk_task_import_activate');
register_deactivation_hook(BULK_TASK_IMPORT_MODULE_NAME, 'bulk_task_import_deactivate');
register_uninstall_hook(BULK_TASK_IMPORT_MODULE_NAME, 'bulk_task_import_uninstall');

function bulk_task_import_activate()
{
    require_once BULK_TASK_IMPORT_MODULE_PATH . 'install.php';
}

function bulk_task_import_deactivate()
{
    // The module intentionally keeps import history when deactivated.
}

function bulk_task_import_uninstall()
{
    $CI = &get_instance();
    if (!get_option('bulk_task_import_delete_data_on_uninstall')) {
        return;
    }

    $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'bulk_task_import_items`');
    $CI->db->query('DROP TABLE IF EXISTS `' . db_prefix() . 'bulk_task_import_batches`');
}

function bulk_task_import_register_permissions()
{
    $capabilities = [
        'view' => _l('bulk_task_import_permission_view'),
        'import' => _l('bulk_task_import_permission_import'),
        'history' => _l('bulk_task_import_permission_history'),
        'rollback' => _l('bulk_task_import_permission_rollback'),
    ];

    register_staff_capabilities('bulk_task_import', $capabilities, _l('bulk_task_import'));
}

function bulk_task_import_register_menu()
{
    // Register sidebar menu item under Utilities or Tasks if desired, or keep hidden.
    if (has_permission('bulk_task_import', '', 'view')) {
        $CI = &get_instance();
        $CI->app_menu->add_sidebar_menu_item('bulk-task-import', [
            'name' => _l('bulk_task_import'),
            'href' => admin_url('bulk_task_import'),
            'icon' => 'fa fa-upload',
            'position' => 45,
        ]);
    }
}

function bulk_task_import_register_settings()
{
    if (!is_admin()) {
        return;
    }

    $CI = &get_instance();
    $CI->app->add_settings_section('bulk_task_import', [
        'name' => _l('bulk_task_import'),
        'view' => 'bulk_task_import/settings',
        'position' => 45,
        'icon' => 'fa fa-upload',
    ]);
}

function bulk_task_import_enqueue_styles()
{
    $uri = uri_string();
    $is_module = (strpos($uri, 'bulk_task_import') !== false);

    // CSS styling only loaded on the module pages to keep the interface highly polished
    if ($is_module) {
        echo '<link rel="stylesheet" type="text/css" href="' .
            module_dir_url(BULK_TASK_IMPORT_MODULE_NAME, 'assets/css/bulk_task_import.css') .
            '?v=1.2.2">';
    }
}

function bulk_task_import_enqueue_scripts()
{
    $uri = uri_string();
    $is_tasks = (strpos($uri, 'tasks') !== false);
    $is_projects = (strpos($uri, 'projects/view') !== false);
    $is_module = (strpos($uri, 'bulk_task_import') !== false);

    if ($is_module) {
        // Enqueue custom helpers on module pages
        echo '<script src="' .
            module_dir_url(BULK_TASK_IMPORT_MODULE_NAME, 'assets/js/bulk_task_import.js') .
            '?v=1.2.2"></script>';
    }

    if ($is_tasks || $is_projects) {
        // Inline script: inject the Bulk Import redirect link and Export Tasks link beside the New Task button
        ?>
        <script>
            (function () {
                var adminUrl = '<?php echo admin_url(); ?>';

                function getProjectId() {
                    var projectId = null;
                    var pathname = window.location.pathname;
                    var matches = pathname.match(/projects\/view\/(\d+)/);
                    if (matches) {
                        projectId = matches[1];
                    } else {
                        var params = new URLSearchParams(window.location.search);
                        projectId = params.get('project_id');
                    }
                    return projectId;
                }

                function tryInject() {
                    if (document.querySelector('.btn-bulk-import')) return true;

                    var target = null;

                    // 1. Tasks page primary button
                    target = document.querySelector('a.btn.btn-primary.pull-left.new');

                    // 2. Projects page view button
                    if (!target) {
                        var projectId = getProjectId();
                        if (projectId) {
                            target = document.querySelector('a[onclick*="new_task_from_relation"][onclick*="project"]');
                        }
                    }

                    // 3. Fallback: Any button with "New Task" text
                    if (!target) {
                        var all = document.querySelectorAll('a.btn, button.btn');
                        for (var i = 0; i < all.length; i++) {
                            var t = all[i].textContent.replace(/\s+/g, ' ').trim().toLowerCase();
                            if (t.indexOf('new task') !== -1 || t.indexOf('new_task') !== -1) {
                                target = all[i];
                                break;
                            }
                        }
                    }

                    // 4. Fallback 2: Row buttons general
                    if (!target) {
                        target = document.querySelector('.row._buttons .btn, ._buttons .btn');
                    }

                    if (!target) return false;

                    var projectId = getProjectId();

                    // ── A. Bulk Import Link ──────────────────────────────────────
                    var btn = document.createElement('a');
                    btn.href = adminUrl + 'bulk_task_import' + (projectId ? '?project_id=' + projectId : '');
                    btn.className = 'btn btn-primary pull-left btn-bulk-import';
                    btn.style.cssText = 'margin-right:6px; background-color: #111827; border-color: #111827; color: #ffffff;';
                    btn.innerHTML = '<i class="fa fa-upload" style="margin-right:5px;"></i>Bulk Import';
                    btn.onmouseover = function() { btn.style.backgroundColor = '#1f2937'; btn.style.borderColor = '#1f2937'; };
                    btn.onmouseout = function() { btn.style.backgroundColor = '#111827'; btn.style.borderColor = '#111827'; };

                    // ── B. Export Tasks Link ─────────────────────────────────────
                    var expBtn = document.createElement('a');
                    expBtn.href = adminUrl + 'bulk_task_import/export' + (projectId ? '?project_id=' + projectId : '');
                    expBtn.className = 'btn btn-default pull-left btn-export-tasks';
                    expBtn.style.cssText = 'margin-right:6px;';
                    expBtn.innerHTML = '<i class="fa fa-download" style="margin-right:5px;"></i>Export Tasks';

                    // Insert before New Task button (inline)
                    target.parentNode.insertBefore(btn, target);
                    target.parentNode.insertBefore(expBtn, btn);
                    return true;
                }

                function boot() {
                    if (!tryInject()) {
                        var n = 0, t = setInterval(function () {
                            if (tryInject() || ++n > 30) clearInterval(t);
                        }, 300);
                    }
                }

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', boot);
                } else {
                    boot();
                }
            })();
        </script>
        <?php
    }
}