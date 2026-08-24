<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Bulk_task_import extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['bulk_task_import/bulk_task_import']);
        $this->load->model('bulk_task_import_model');
        $this->load->model('tasks_model');
        $this->load->library('bulk_task_import_reader');
    }

    public function index()
    {
        $this->require_capability('import');
        $data['title']      = _l('bulk_task_import');
        $data['project_id'] = $this->input->get('project_id') ? (int) $this->input->get('project_id') : null;
        $data['history']    = $this->bulk_task_import_model->history();
        $this->load->model('projects_model');
        $data['projects']   = $this->projects_model->get('', ['status' => 2]); // get active projects
        $this->load->view('bulk_task_import/manage', $data);
    }

    public function upload()
    {
        $this->require_capability('import');
        if (!get_option('bulk_task_import_enabled')) {
            if ($this->input->is_ajax_request()) {
                echo json_encode(['status' => 'error', 'message' => _l('bulk_task_import_disabled')]);
                return;
            }
            show_error(_l('bulk_task_import_disabled'), 403);
        }

        $max_size = max(1, (int) get_option('bulk_task_import_max_file_size', 10));
        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $msg = _l('bulk_task_import_file_read_failed');
            if ($this->input->is_ajax_request()) {
                echo json_encode(['status' => 'error', 'message' => $msg]);
                return;
            }
            set_alert('danger', $msg);
            redirect(admin_url('bulk_task_import'));
        }
        $extension = strtolower(pathinfo($_FILES['file']['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($extension, ['csv', 'xlsx'], true)) {
            $msg = _l('bulk_task_import_invalid_file');
            if ($this->input->is_ajax_request()) {
                echo json_encode(['status' => 'error', 'message' => $msg]);
                return;
            }
            set_alert('danger', $msg);
            redirect(admin_url('bulk_task_import'));
        }
        if ($_FILES['file']['size'] > $max_size * 1024 * 1024) {
            $msg = _l('bulk_task_import_file_too_large');
            if ($this->input->is_ajax_request()) {
                echo json_encode(['status' => 'error', 'message' => $msg]);
                return;
            }
            set_alert('danger', $msg);
            redirect(admin_url('bulk_task_import'));
        }

        try {
            $rows = $this->bulk_task_import_reader->read($_FILES['file']['tmp_name'], $extension);
        } catch (RuntimeException $exception) {
            log_message('error', 'Bulk Task Import upload failed: ' . $exception->getMessage());
            $msg = _l('bulk_task_import_file_read_failed');
            if ($this->input->is_ajax_request()) {
                echo json_encode(['status' => 'error', 'message' => $msg]);
                return;
            }
            set_alert('danger', $msg);
            redirect(admin_url('bulk_task_import'));
        }
        if (count($rows) < 2 || empty(array_filter($rows[0] ?? []))) {
            $msg = _l('bulk_task_import_empty_file');
            if ($this->input->is_ajax_request()) {
                echo json_encode(['status' => 'error', 'message' => $msg]);
                return;
            }
            set_alert('danger', $msg);
            redirect(admin_url('bulk_task_import'));
        }

        $project_id = $this->input->post('project_id') ? (int) $this->input->post('project_id') : ($this->input->get('project_id') ? (int) $this->input->get('project_id') : null);
        $batch_id = $this->bulk_task_import_model->create_batch($_FILES['file']['name'], count($rows) - 1);
        $headers = $rows[0];
        $rows_data = array_slice($rows, 1);
        $mapping = bulk_task_import_auto_map_headers($headers);

        $state = [
            'headers'    => $headers,
            'rows'       => $rows_data,
            'mapping'    => $mapping,
            'file_name'  => $_FILES['file']['name'],
            'project_id' => $project_id,
        ];
        $this->session->set_userdata('bulk_task_import_' . $batch_id, $state);

        if ($this->input->is_ajax_request()) {
            echo json_encode([
                'status' => 'success',
                'batch_id' => $batch_id,
                'file_name' => $_FILES['file']['name'],
                'file_size' => $_FILES['file']['size'],
                'rows_count' => count($rows_data),
                'columns_count' => count($headers),
                'headers' => $headers,
                'mapping' => $mapping,
                'sample_data' => $rows_data[0] ?? [],
                'allowed_fields' => bulk_task_import_allowed_fields()
            ]);
            return;
        }

        redirect(admin_url('bulk_task_import/map/' . $batch_id));
    }

    public function map($batch_id)
    {
        $this->require_capability('import');
        $state = $this->session->userdata('bulk_task_import_' . (int) $batch_id);
        if (!$state) {
            redirect(admin_url('bulk_task_import'));
        }
        $data = $state;
        $data['batch'] = $this->bulk_task_import_model->get_batch($batch_id);
        $data['fields'] = bulk_task_import_allowed_fields();
        $this->load->view('bulk_task_import/mapping', $data);
    }

    public function validate_rows($batch_id)
    {
        $this->require_capability('import');
        $state = $this->session->userdata('bulk_task_import_' . (int) $batch_id);
        if (!$state) {
            if ($this->input->is_ajax_request()) {
                echo json_encode(['status' => 'error', 'message' => 'Session expired. Please re-upload.']);
                return;
            }
            redirect(admin_url('bulk_task_import'));
        }
        
        $mapping = $this->input->post('mapping', true);
        if (empty($mapping) && $this->input->raw_input_stream) {
            $json = json_decode($this->input->raw_input_stream, true);
            if (isset($json['mapping'])) {
                $mapping = $json['mapping'];
            }
        }
        
        $state['mapping'] = $mapping;
        $this->session->set_userdata('bulk_task_import_' . (int) $batch_id, $state);
        
        $data['batch'] = $this->bulk_task_import_model->get_batch($batch_id);
        $data['rows'] = $this->validate_import_rows($state);
        $data['valid_count'] = count(array_filter($data['rows'], static fn($row) => $row['status'] === 'valid'));
        $data['error_count'] = count(array_filter($data['rows'], static fn($row) => $row['status'] === 'error'));
        $data['warning_count'] = count(array_filter($data['rows'], static fn($row) => $row['status'] === 'warning'));
        
        $this->session->set_userdata('bulk_task_import_validation_' . (int) $batch_id, $data['rows']);

        if ($this->input->is_ajax_request()) {
            echo json_encode([
                'status' => 'success',
                'batch_id' => (int) $batch_id,
                'rows' => $data['rows'],
                'valid_count' => $data['valid_count'],
                'error_count' => $data['error_count'],
                'warning_count' => $data['warning_count']
            ]);
            return;
        }

        $this->load->view('bulk_task_import/validation', $data);
    }

    public function import_batch($batch_id)
    {
        $this->require_capability('import');
        $rows = $this->session->userdata('bulk_task_import_validation_' . (int) $batch_id);
        if (!$rows) {
            if ($this->input->is_ajax_request()) {
                echo json_encode(['status' => 'error', 'message' => 'No validation data found. Please validate first.']);
                return;
            }
            redirect(admin_url('bulk_task_import'));
        }

        // Get project_id from the batch upload state
        $state      = $this->session->userdata('bulk_task_import_' . (int) $batch_id);
        $project_id = isset($state['project_id']) ? (int) $state['project_id'] : null;

        $summary = ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'duplicates' => 0];

        foreach ($rows as $row) {
            if ($row['status'] === 'error') {
                $summary['skipped']++;
                $this->bulk_task_import_model->add_item($batch_id, $row['row_number'], 'skipped', null, implode('; ', $row['errors']));
                continue;
            }

            $task_data = $row['task'];

            // tasks_model->add() unconditionally calls to_sql_date($data['startdate'])
            // so we MUST ensure these keys exist, even if empty.
            if (!isset($task_data['startdate']) || $task_data['startdate'] === '') {
                $task_data['startdate'] = date('Y-m-d'); // default to today
            }
            if (!isset($task_data['duedate']) || $task_data['duedate'] === '') {
                $task_data['duedate'] = ''; // empty is OK — Perfex handles it
            }

            // Ensure 'name' is present (required DB column)
            if (empty($task_data['name'])) {
                $summary['skipped']++;
                $this->bulk_task_import_model->add_item($batch_id, $row['row_number'], 'skipped', null, 'Task name is empty.');
                continue;
            }

            // Set project association if project_id is present
            if ($project_id) {
                $task_data['rel_type'] = 'project';
                $task_data['rel_id']   = $project_id;
            }

            // Map Priority and Status text to numeric IDs
            $task_data['priority'] = bulk_task_import_parse_priority($task_data['priority'] ?? '');
            $task_data['status']   = bulk_task_import_parse_status($task_data['status'] ?? '');

            // Parse assignees
            $assignees = [];
            if (!empty($task_data['assigned_to'])) {
                $emails = preg_split('/[,;|]/', $task_data['assigned_to']);
                foreach ($emails as $email) {
                    $staff_id = bulk_task_import_get_staff_by_email($email);
                    if ($staff_id) {
                        $assignees[] = $staff_id;
                    }
                }
            }
            if (!empty($assignees)) {
                $task_data['assignees'] = $assignees;
            }
            unset($task_data['assigned_to']);

            // Parse checklist items
            $checklist_items = [];
            if (!empty($task_data['checklist_items'])) {
                $items = preg_split('/[|\n]/', $task_data['checklist_items']);
                foreach ($items as $item) {
                    $parsed = bulk_task_import_parse_checklist_item($item);
                    if ($parsed) {
                        $checklist_items[] = $parsed;
                    }
                }
            }
            unset($task_data['checklist_items']);

            // Strip out any keys in task_data that do not exist as columns in tbltasks table
            foreach ($task_data as $key => $val) {
                if ($key !== 'assignees' && !$this->db->field_exists($key, db_prefix() . 'tasks')) {
                    unset($task_data[$key]);
                }
            }

            try {
                $task_id = $this->tasks_model->add($task_data);

                if ($task_id) {
                    $summary['imported']++;
                    $this->bulk_task_import_model->add_item($batch_id, $row['row_number'], 'imported', $task_id);

                    // Add checklist items with their assignees
                    foreach ($checklist_items as $key => $cli) {
                        $assigned_staff = null;
                        if ($cli['email']) {
                            $assigned_staff = bulk_task_import_get_staff_by_email($cli['email']);
                        }
                        $this->db->insert(db_prefix() . 'task_checklist_items', [
                            'taskid'      => $task_id,
                            'description' => $cli['description'],
                            'dateadded'   => date('Y-m-d H:i:s'),
                            'addedfrom'   => get_staff_user_id() ?: 1,
                            'list_order'  => $key,
                            'assigned'    => $assigned_staff,
                        ]);
                    }
                } else {
                    $summary['failed']++;
                    $this->bulk_task_import_model->add_item($batch_id, $row['row_number'], 'failed', null, _l('bulk_task_import_create_failed'));
                }
            } catch (Throwable $e) {
                $summary['failed']++;
                $this->bulk_task_import_model->add_item($batch_id, $row['row_number'], 'failed', null, $e->getMessage());
            }
        }

        $this->bulk_task_import_model->complete_batch($batch_id, $summary);
        $this->session->unset_userdata('bulk_task_import_' . (int) $batch_id);

        if ($this->input->is_ajax_request()) {
            echo json_encode([
                'status'  => 'success',
                'summary' => [
                    'total'    => count($rows),
                    'imported' => $summary['imported'],
                    'skipped'  => $summary['skipped'],
                    'failed'   => $summary['failed'],
                ],
            ]);
            return;
        }

        set_alert('success', _l('bulk_task_import_completed') . ' (' . $summary['imported'] . ' tasks added)');
        if ($project_id) {
            redirect(admin_url('projects/view/' . $project_id . '?group=project_tasks'));
        } else {
            redirect(admin_url('tasks'));
        }
    }


    public function download_error_report($batch_id)
    {
        $this->require_capability('import');
        $rows = $this->session->userdata('bulk_task_import_validation_' . (int) $batch_id);
        if (!$rows) {
            // Fallback: load from database
            $items = $this->bulk_task_import_model->get_items($batch_id);
            $rows = [];
            foreach ($items as $item) {
                if ($item->status !== 'imported') {
                    $rows[] = [
                        'row_number' => $item->row_number,
                        'status' => $item->status,
                        'errors' => [$item->error_message],
                        'warnings' => [],
                        'task' => ['subject' => '']
                    ];
                }
            }
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=bulk-import-errors-batch-' . $batch_id . '.csv');
        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF");
        fputcsv($output, [_l('bulk_task_import_row'), _l('bulk_task_import_status'), _l('bulk_task_import_message')]);
        foreach ($rows as $row) {
            if ($row['status'] === 'error' || $row['status'] === 'warning') {
                $msg = implode('; ', array_merge($row['errors'], $row['warnings']));
                fputcsv($output, [$row['row_number'], ucfirst($row['status']), $msg]);
            }
        }
        fclose($output);
        exit;
    }

    public function download_import_report($batch_id)
    {
        $this->require_capability('import');
        $items = $this->bulk_task_import_model->get_items($batch_id);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=bulk-import-report-batch-' . $batch_id . '.csv');
        $output = fopen('php://output', 'w');
        fputs($output, "\xEF\xBB\xBF");
        fputcsv($output, [_l('bulk_task_import_row'), _l('bulk_task_import_status'), _l('bulk_task_import_message')]);
        foreach ($items as $item) {
            fputcsv($output, [$item->row_number, ucfirst($item->status), $item->error_message ?: '']);
        }
        fclose($output);
        exit;
    }

    public function history()
    {
        $this->require_capability('history');
        $data['title'] = _l('bulk_task_import_history');
        $data['history'] = $this->bulk_task_import_model->history();
        $this->load->view('bulk_task_import/history', $data);
    }

    public function rollback($batch_id)
    {
        $this->require_capability('rollback');
        if (!is_admin()) {
            access_denied('bulk_task_import');
        }

        $items = $this->bulk_task_import_model->get_items($batch_id);
        $deleted = 0;
        foreach ($items as $item) {
            if ($item->status !== 'imported' || !$item->task_id) {
                continue;
            }

            $task = $this->db->where('id', (int) $item->task_id)->get(db_prefix() . 'tasks')->row();
            if (!$task) {
                continue;
            }

            $this->tasks_model->delete_task((int) $item->task_id);
            $deleted++;
        }

        $this->db->where('id', (int) $batch_id)->update(db_prefix() . 'bulk_task_import_batches', [
            'status' => 'rolled_back',
        ]);
        set_alert('success', _l('bulk_task_import_rollback_completed', $deleted));
        redirect(admin_url('bulk_task_import/history'));
    }

    public function download_template()
    {
        $this->require_capability('import');
        $file_path = BULK_TASK_IMPORT_MODULE_PATH . 'assets/templates/bulk-task-import-template.csv';
        if (!file_exists($file_path)) {
            show_error('Template file not found.', 404);
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=bulk-task-import-template.csv');
        readfile($file_path);
        exit;
    }

    public function clear_history()
    {
        $this->require_capability('history');
        if (!is_admin()) {
            access_denied('bulk_task_import');
        }
        $this->db->truncate(db_prefix() . 'bulk_task_import_items');
        $this->db->truncate(db_prefix() . 'bulk_task_import_batches');
        set_alert('success', 'Import history cleared successfully.');
        redirect(admin_url('bulk_task_import/history'));
    }

    public function export()
    {
        $this->require_capability('view');

        $project_id = $this->input->get('project_id') ? (int) $this->input->get('project_id') : null;

        // Fetch tasks
        $this->db->select('t.*');
        $this->db->from(db_prefix() . 'tasks t');

        if ($project_id) {
            $this->db->where('t.rel_type', 'project');
            $this->db->where('t.rel_id', $project_id);
        }

        $this->db->order_by('t.id', 'asc');
        $tasks = $this->db->get()->result_array();

        // CSV Headers matching allowed_fields keys (mapped to labels)
        $fields = [
            'name'            => 'Subject',
            'description'     => 'Description',
            'startdate'       => 'Start Date',
            'duedate'         => 'Due Date',
            'priority'        => 'Priority',
            'status'          => 'Status',
            'billable'        => 'Billable',
            'hourly_rate'     => 'Hourly Rate',
            'estimated_hours' => 'Estimated Hours',
            'tags'            => 'Tags',
            'assigned_to'     => 'Assigned To',
            'checklist_items' => 'Checklist Items',
        ];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=tasks-export' . ($project_id ? '-project-' . $project_id : '') . '.csv');

        $output = fopen('php://output', 'w');
        // BOM for Excel compatibility
        fputs($output, "\xEF\xBB\xBF");

        // Write header row
        fputcsv($output, array_values($fields));

        $priorities = [1 => 'Low', 2 => 'Medium', 3 => 'High', 4 => 'Urgent'];
        $statuses   = [1 => 'Not Started', 2 => 'Awaiting Feedback', 3 => 'Testing', 4 => 'In Progress', 5 => 'Complete'];

        foreach ($tasks as $task) {
            // 1. Get assignees emails
            $this->db->select('s.email');
            $this->db->from(db_prefix() . 'task_assigned ta');
            $this->db->join(db_prefix() . 'staff s', 's.staffid = ta.staffid');
            $this->db->where('ta.taskid', $task['id']);
            $assignees = $this->db->get()->result_array();
            $assignee_emails = [];
            foreach ($assignees as $as) {
                if ($as['email']) {
                    $assignee_emails[] = $as['email'];
                }
            }

            // 2. Get tags
            $tags_arr = get_tags_in($task['id'], 'task');
            $tags_str = is_array($tags_arr) ? implode(', ', $tags_arr) : '';

            // 3. Get checklist items formatted
            $this->db->select('tci.description, s.email');
            $this->db->from(db_prefix() . 'task_checklist_items tci');
            $this->db->join(db_prefix() . 'staff s', 's.staffid = tci.assigned', 'left');
            $this->db->where('tci.taskid', $task['id']);
            $this->db->order_by('tci.list_order', 'asc');
            $checklists = $this->db->get()->result_array();

            $checklist_items = [];
            foreach ($checklists as $cli) {
                $item_str = $cli['description'];
                if ($cli['email']) {
                    $item_str .= ' (' . $cli['email'] . ')';
                }
                $checklist_items[] = $item_str;
            }

            // Write row
            fputcsv($output, [
                $task['name'],
                $task['description'],
                $task['startdate'],
                $task['duedate'],
                $priorities[$task['priority']] ?? 'Medium',
                $statuses[$task['status']] ?? 'Not Started',
                $task['billable'] ? 'Yes' : 'No',
                $task['hourly_rate'],
                $task['estimated_hours'],
                $tags_str,
                implode(', ', $assignee_emails),
                implode(' | ', $checklist_items),
            ]);
        }

        fclose($output);
        exit;
    }

    private function validate_import_rows($state)
    {
        $results = [];
        foreach ($state['rows'] as $index => $raw) {
            $row = [
                'row_number' => $index + 2,
                'status'     => 'valid',
                'errors'     => [],
                'warnings'   => [],
                'task'       => [],
            ];

            // Map raw columns to task fields using the confirmed mapping
            foreach ($state['mapping'] as $column => $field) {
                if ($field && isset($raw[$column])) {
                    $row['task'][$field] = trim((string) $raw[$column]);
                }
            }

            // ── Required: name (DB col in tasks table) ──────────────
            if (empty($row['task']['name'])) {
                $row['errors'][] = _l('bulk_task_import_subject_required');
            }

            // ── Date fields: tasks_model->add() expects startdate/duedate ──
            foreach (['startdate', 'duedate'] as $date_field) {
                if (isset($row['task'][$date_field]) && $row['task'][$date_field] !== '') {
                    $parsed = bulk_task_import_parse_date($row['task'][$date_field]);
                    if ($parsed === false) {
                        $row['errors'][] = _l('bulk_task_import_invalid_date') . ' (' . $date_field . ': ' . $row['task'][$date_field] . ')';
                    } else {
                        $row['task'][$date_field] = $parsed ?? '';
                    }
                }
            }

            // ── Due before start check ───────────────────────────────
            if (!empty($row['task']['startdate']) && !empty($row['task']['duedate'])
                && $row['task']['duedate'] < $row['task']['startdate']) {
                $row['errors'][] = _l('bulk_task_import_due_before_start');
            }

            // ── Billable ─────────────────────────────────────────────
            if (isset($row['task']['billable']) && $row['task']['billable'] !== '') {
                $billable = bulk_task_import_bool($row['task']['billable']);
                if ($billable === null) {
                    $row['errors'][] = _l('bulk_task_import_invalid_billable');
                } else {
                    $row['task']['billable'] = $billable;
                }
            }

            // ── Hourly rate ───────────────────────────────────────────
            if (isset($row['task']['hourly_rate']) && $row['task']['hourly_rate'] !== ''
                && !is_numeric($row['task']['hourly_rate'])) {
                $row['errors'][] = _l('bulk_task_import_invalid_rate');
            }

            // ── Assignees validation ─────────────────────────────────
            if (!empty($row['task']['assigned_to'])) {
                $emails = preg_split('/[,;|]/', $row['task']['assigned_to']);
                foreach ($emails as $email) {
                    $email = trim($email);
                    if ($email !== '') {
                        $staff_id = bulk_task_import_get_staff_by_email($email);
                        if (!$staff_id) {
                            $row['warnings'][] = "Assignee '{$email}' not found in Perfex CRM (will be skipped).";
                        }
                    }
                }
            }

            // ── Checklist items validation ───────────────────────────
            if (!empty($row['task']['checklist_items'])) {
                $items = preg_split('/[|\n]/', $row['task']['checklist_items']);
                foreach ($items as $item) {
                    $parsed = bulk_task_import_parse_checklist_item($item);
                    if ($parsed && $parsed['email']) {
                        $staff_id = bulk_task_import_get_staff_by_email($parsed['email']);
                        if (!$staff_id) {
                            $row['warnings'][] = "Checklist assignee '{$parsed['email']}' not found (checklist item '{$parsed['description']}' will be unassigned).";
                        }
                    }
                }
            }

            // ── Status ────────────────────────────────────────────────
            $row['status'] = $row['errors'] ? 'error' : ($row['warnings'] ? 'warning' : 'valid');
            $results[] = $row;
        }
        return $results;
    }

    private function require_capability($capability)
    {
        if (!is_admin() || !has_permission('bulk_task_import', '', $capability)) {
            access_denied('bulk_task_import');
        }
    }
}