<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Returns the list of allowed import fields mapped to their labels.
 * Keys MUST match the exact column names expected by tasks_model->add().
 */
function bulk_task_import_allowed_fields()
{
    return [
        'name'            => _l('bulk_task_import_subject'),        // DB col: name
        'description'     => _l('bulk_task_import_description'),
        'startdate'       => _l('bulk_task_import_start_date'),     // DB col: startdate
        'duedate'         => _l('bulk_task_import_due_date'),       // DB col: duedate
        'priority'        => _l('bulk_task_import_priority'),
        'status'          => _l('bulk_task_import_status'),
        'billable'        => _l('bulk_task_import_billable'),
        'hourly_rate'     => _l('bulk_task_import_hourly_rate'),
        'estimated_hours' => _l('bulk_task_import_estimated_hours'),
        'tags'            => _l('bulk_task_import_tags'),
        'assigned_to'     => _l('bulk_task_import_assigned_to'),    // Custom handler
        'checklist_items' => 'Checklist Items',                     // Custom handler
    ];
}

function bulk_task_import_normalize_header($header)
{
    $header = trim(strtolower((string) $header));
    return preg_replace('/[^a-z0-9]+/', '_', $header);
}

function bulk_task_import_auto_map_headers($headers)
{
    // Keys = DB field names used by tasks_model->add() or custom handlers
    $aliases = [
        'name'            => ['name', 'subject', 'task', 'task_subject', 'title', 'task_name'],
        'description'     => ['description', 'task_description', 'details', 'body', 'notes', 'desc'],
        'startdate'       => ['startdate', 'start_date', 'start', 'startdt', 'start_dt'],
        'duedate'         => ['duedate', 'due_date', 'due', 'deadline', 'end_date', 'duedt'],
        'priority'        => ['priority', 'level', 'importance'],
        'hourly_rate'     => ['hourly_rate', 'rate', 'price', 'hourly'],
        'estimated_hours' => ['estimated_hours', 'hours', 'estimation', 'est_hours'],
        'billable'        => ['billable', 'is_billable', 'billed'],
        'tags'            => ['tags', 'tag', 'keywords', 'labels'],
        'status'          => ['status', 'task_status', 'state'],
        'assigned_to'     => ['assigned_to', 'assigned', 'assignee', 'staff', 'user', 'owner', 'assignees'],
        'checklist_items' => ['checklist_items', 'checklists', 'checklist', 'subtasks', 'subtask'],
    ];

    $map = [];
    foreach ($headers as $index => $header) {
        $normalized = bulk_task_import_normalize_header($header);
        $map[$index] = null;
        foreach ($aliases as $field => $names) {
            if (in_array($normalized, $names, true)) {
                $map[$index] = $field;
                break;
            }
        }
        if (!$map[$index] && array_key_exists($normalized, bulk_task_import_allowed_fields())) {
            $map[$index] = $normalized;
        }
    }
    return $map;
}

/**
 * Parses a date string or Excel numeric serial into Y-m-d format.
 * Returns null for empty, false for invalid.
 */
function bulk_task_import_parse_date($value)
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    // Excel numeric serial date (e.g. 46250)
    if (is_numeric($value) && (int) $value > 30000 && (int) $value < 60000) {
        $unix = ((int) $value - 25569) * 86400;
        return gmdate('Y-m-d', $unix);
    }
    foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d', 'm/d/Y', 'd.m.Y'] as $format) {
        $date = DateTime::createFromFormat('!' . $format, $value);
        if ($date && $date->format($format) === $value) {
            return $date->format('Y-m-d');
        }
    }
    return false;
}

function bulk_task_import_bool($value)
{
    $value = strtolower(trim((string) $value));
    if (in_array($value, ['yes', '1', 'true', 'y'], true)) {
        return 1;
    }
    if (in_array($value, ['no', '0', 'false', 'n', ''], true)) {
        return 0;
    }
    return null;
}

/**
 * Resolves a staff email to their database staffid.
 * Returns null if not found.
 */
function bulk_task_import_get_staff_by_email($email)
{
    $email = trim((string) $email);
    if ($email === '') {
        return null;
    }

    $CI = &get_instance();
    $CI->db->where('email', $email);
    $CI->db->where('active', 1);
    $staff = $CI->db->get(db_prefix() . 'staff')->row();

    return $staff ? (int)$staff->staffid : null;
}

/**
 * Parses a checklist item string containing optional assignee in parentheses/brackets/braces/colon.
 * Example: "Review UI Design (ram@company.com)" -> ['description' => "Review UI Design", 'email' => "ram@company.com"]
 */
function bulk_task_import_parse_checklist_item($item_str)
{
    $item_str = trim((string)$item_str);
    if ($item_str === '') {
        return null;
    }

    // Regex to detect an email address inside parentheses/brackets/braces or after a colon at the end of string
    // e.g. "Review UI Design (ram@company.com)"
    // e.g. "Review UI Design [ram@company.com]"
    // e.g. "Review UI Design: ram@company.com"
    if (preg_match('/^(.*?)\s*[\(\{\[:]?\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\s*[\)\}\]]?$/', $item_str, $matches)) {
        return [
            'description' => trim($matches[1], " \t\n\r\0\x0B:()[]{}"),
            'email'       => trim($matches[2]),
        ];
    }

    return [
        'description' => $item_str,
        'email'       => null,
    ];
}

function bulk_task_import_parse_priority($val)
{
    $val = trim(strtolower((string)$val));
    if ($val === '') {
        return 2; // Default to Medium (2)
    }
    if (is_numeric($val) && in_array((int)$val, [1, 2, 3, 4], true)) {
        return (int)$val;
    }
    $map = [
        'low'    => 1,
        'medium' => 2,
        'high'   => 3,
        'urgent' => 4,
    ];
    return $map[$val] ?? 2;
}

function bulk_task_import_parse_status($val)
{
    $val = trim(strtolower((string)$val));
    if ($val === '') {
        return 1; // Default to Not Started (1)
    }
    if (is_numeric($val) && in_array((int)$val, [1, 2, 3, 4, 5], true)) {
        return (int)$val;
    }
    $map = [
        'not started'       => 1,
        'not_started'       => 1,
        'awaiting feedback' => 2,
        'awaiting_feedback' => 2,
        'testing'           => 3,
        'in progress'       => 4,
        'in_progress'       => 4,
        'complete'          => 5,
        'completed'         => 5,
    ];
    return $map[$val] ?? 1;
}