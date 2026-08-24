# Premium Bulk Task Import & Export Mapping Module for Perfex CRM

A highly polished, premium multi-step importer and exporter module for Perfex CRM. It allows bulk task importing from CSV and Excel spreadsheets directly inside the native **Tasks** and **Project View** modules with intelligent mapping, real-time validation, and a rollback feature.

---

## 📸 Screenshots & Workflow Steps

### 1. File Upload & Project Association
Upload your CSV or Excel (`.xlsx`) files. You can associate all imported tasks with a selected project using the live-search picker (automatically pre-selected when launching the importer from inside a project details view).
![Upload Screen](screenshots/bulk_task_import_upload.png)

### 2. Intelligent Column Mapping
Match headers from your spreadsheet to Perfex CRM task fields (Subject, Description, Priority, Status, Billable, Hourly Rate, Estimated Hours, Tags, Assignees, Checklist Items). Click **Auto Map Columns** to automatically align standard headers.
![Column Mapping](screenshots/bulk_task_import_mapping.png)

### 3. Real-Time Validation Results
Review data validation reports before importing. Succeeded tasks are marked as **Valid**, while errors/missing required fields are flagged with details. You can filter the table dynamically or download a CSV error log.
![Validation results](screenshots/bulk_task_import_validation.png)

### 4. Import Summary & Rollback
After importing, view a complete count of imported tasks. Administrators can review the **Import History** log and perform a **Rollback** to delete all tasks created by a specific batch in case of upload errors.
![Import Complete](screenshots/bulk_task_import_complete.png)

---

## 🌟 Key Features

* **Inline Action Buttons**: Injects the **Bulk Import** and **Export Tasks** buttons inline next to the native *New Task* CTA on Tasks and Projects View pages.
* **Full-Page Wizard Stepper**: Overhauled from a simple popup overlay to a premium full-page card layout with wizard stepper navigation.
* **Subtasks & Checklist Assignees**: Import subtask checklists and assign them to specific staff members using the syntax: `Subtask Description (email@domain.com)` separated by a pipe `|` (e.g. `Design Layout (designer@test.com) | Write Code (coder@test.com)`).
* **Database Column Safeguards**: Dynamically inspects database columns before saving. If columns like `estimated_hours` do not exist in the active Perfex CRM version, they are automatically stripped to prevent database insertion crashes.
* **MIME-Type Cache Buster**: Downloads the CSV import template through a dedicated controller route, guaranteeing it saves as a `.csv` file regardless of server mime-type configuration.
* **Clear History Utility**: Admins can wipe all log history records with a single click via the **Clear Import History** button on the audit trail page.

---

## 📋 CSV Import Template Columns

The template CSV includes the following headers:
1. `Subject` (Required) — The title of the task.
2. `Description` — The detailed text description (supports newlines).
3. `Start Date` — Formatted as `YYYY-MM-DD`.
4. `Due Date` — Formatted as `YYYY-MM-DD`.
5. `Priority` — `Low`, `Medium`, `High`, or `Urgent`.
6. `Status` — `Not Started`, `In Progress`, `Testing`, `Awaiting Feedback`, or `Complete`.
7. `Billable` — `Yes` or `No`.
8. `Hourly Rate` — The billing rate value.
9. `Estimated Hours` — Task estimation value (automatically ignored if database table does not support it).
10. `Tags` — Comma-separated list of tags (e.g. `UI, Design, Frontend`).
11. `Assigned To` — Comma-separated list of assignee emails.
12. `Checklist Items` — Pipe-separated list of checklist items with optional assignees (e.g. `Item 1 (staff@mail.com) | Item 2`).

---

## ⚙️ Settings & Configuration

Configure the maximum file upload size, duplicate check rules, and batch configuration from **Setup > Settings > Bulk Task Import**. 

* **CSV Import**: Works out of the box with zero dependencies.
* **Excel XLSX Support**: For Excel `.xlsx` spreadsheets, ensure `PhpSpreadsheet` is installed and autoloaded on the server.

---

## 🚀 Safe Installation

1. Copy the folder to `modules/bulk_task_import`.
2. Go to **Setup > Modules** in Perfex CRM and click **Activate**.
3. Go to **Setup > Staff** to assign bulk import permissions to your staff roles.
4. You're ready to import! Use the **Bulk Import** button beside the *New Task* CTA on Tasks or Projects.