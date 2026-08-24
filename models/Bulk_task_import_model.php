<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Bulk_task_import_model extends App_Model
{
    public function create_batch($file_name, $total_rows)
    {
        $this->db->insert(db_prefix() . 'bulk_task_import_batches', [
            'file_name' => $file_name,
            'uploaded_by' => get_staff_user_id(),
            'total_rows' => (int) $total_rows,
            'status' => 'uploaded',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->db->insert_id();
    }

    public function get_batch($id)
    {
        return $this->db->where('id', (int) $id)->get(db_prefix() . 'bulk_task_import_batches')->row();
    }

    public function history()
    {
        return $this->db->select('b.*, CONCAT(s.firstname, " ", s.lastname) AS uploaded_by_name')
            ->from(db_prefix() . 'bulk_task_import_batches b')
            ->join(db_prefix() . 'staff s', 's.staffid = b.uploaded_by', 'left')
            ->order_by('b.id', 'DESC')
            ->get()->result();
    }

    public function add_item($batch_id, $row_number, $status, $task_id = null, $error = null)
    {
        $this->db->insert(db_prefix() . 'bulk_task_import_items', [
            'batch_id' => (int) $batch_id,
            'row_number' => (int) $row_number,
            'status' => $status,
            'task_id' => $task_id ? (int) $task_id : null,
            'error_message' => $error,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function complete_batch($id, $summary, $errors = [])
    {
        $this->db->where('id', (int) $id)->update(db_prefix() . 'bulk_task_import_batches', [
            'successful_rows' => (int) ($summary['imported'] ?? 0),
            'failed_rows' => (int) ($summary['failed'] ?? 0),
            'skipped_rows' => (int) ($summary['skipped'] ?? 0),
            'duplicate_rows' => (int) ($summary['duplicates'] ?? 0),
            'status' => 'completed',
            'error_report' => json_encode($errors),
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function get_items($batch_id)
    {
        return $this->db->where('batch_id', (int) $batch_id)
            ->order_by('row_number', 'ASC')
            ->get(db_prefix() . 'bulk_task_import_items')->result();
    }
}