<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Bulk_task_import_reader
{
    public function read($path, $extension)
    {
        if ($extension === 'csv') {
            return $this->read_csv($path);
        }

        if ($extension === 'xlsx') {
            if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                throw new RuntimeException('XLSX_IMPORT_LIBRARY_MISSING');
            }
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        }

        throw new RuntimeException('UNSUPPORTED_FILE');
    }

    private function read_csv($path)
    {
        $handle = fopen($path, 'rb');
        if (!$handle) {
            throw new RuntimeException('FILE_NOT_READABLE');
        }
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === 1 && trim((string) $row[0]) === '') {
                continue;
            }
            if (isset($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
            }
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }
}