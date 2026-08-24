<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="bi-breadcrumb-nav">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo admin_url('tasks'); ?>"><i class="fa fa-tasks"></i> Tasks</a></li>
        <li class="breadcrumb-item active" aria-current="page">Bulk Task Import</li>
      </ol>
    </nav>

    <div class="row">
      <div class="col-md-9">
        <div class="panel_s bi-panel-card">
          <div class="panel-body">
            
            <!-- Header -->
            <div class="bi-view-header">
              <h2 class="bi-title"><i class="fa fa-upload text-indigo"></i> <?php echo html_escape($title); ?></h2>
              <p class="bi-subtitle"><?php echo _l('bulk_task_import_subtitle'); ?></p>
            </div>

            <!-- Stepper -->
            <div class="bi-stepper-row">
              <div class="bi-step active">
                <span class="bi-step-num">1</span>
                <span class="bi-step-label"><?php echo _l('bulk_task_import_upload'); ?></span>
              </div>
              <div class="bi-step-divider"></div>
              <div class="bi-step">
                <span class="bi-step-num">2</span>
                <span class="bi-step-label"><?php echo _l('bulk_task_import_map'); ?></span>
              </div>
              <div class="bi-step-divider"></div>
              <div class="bi-step">
                <span class="bi-step-num">3</span>
                <span class="bi-step-label"><?php echo _l('bulk_task_import_validate'); ?></span>
              </div>
              <div class="bi-step-divider"></div>
              <div class="bi-step">
                <span class="bi-step-num">4</span>
                <span class="bi-step-label"><?php echo _l('bulk_task_import_import'); ?></span>
              </div>
            </div>

            <!-- Upload Area Form -->
            <?php echo form_open_multipart(admin_url('bulk_task_import/upload' . ($project_id ? '?project_id=' . $project_id : ''))); ?>
              
              <!-- Project Selector Field -->
              <div class="form-group bi-project-select-group" style="margin-bottom:24px;">
                <label for="project_id" class="control-label" style="font-weight:600; font-size:13px; color:var(--bi-gray-700); margin-bottom:8px; display:block;">
                  Associate with Project (Optional)
                </label>
                <select name="project_id" id="project_id" class="selectpicker" data-width="100%" data-none-selected-text="— No Project (General Tasks) —" data-live-search="true">
                  <option value=""></option>
                  <?php foreach ($projects as $proj) : ?>
                    <option value="<?php echo (int) $proj['id']; ?>" <?php echo ($project_id == $proj['id']) ? 'selected' : ''; ?>>
                      <?php echo html_escape($proj['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <p class="help-block" style="font-size:11.5px; color:var(--bi-gray-500); margin-top:4px;">
                  If selected, all imported tasks in this batch will be linked to this project.
                </p>
              </div>

              <!-- Upload Drag Card -->
              <div class="bi-upload-card" id="bi-dropzone">
                <div class="bi-upload-icon"><i class="fa-regular fa-file-excel text-indigo"></i></div>
                <h4 class="bi-upload-title"><?php echo _l('bulk_task_import_drop_file'); ?></h4>
                <p class="bi-upload-sub"><?php echo _l('bulk_task_import_supported_files'); ?></p>
                <div class="bi-file-input-wrapper">
                  <input type="file" name="file" accept=".csv,.xlsx" required class="bi-file-input">
                </div>
              </div>

              <!-- Action Bar -->
              <div class="bi-action-footer">
                <div></div>
                <button type="submit" class="bi-btn bi-btn-primary"><?php echo _l('bulk_task_import_continue'); ?> <i class="fa fa-arrow-right"></i></button>
              </div>
            <?php echo form_close(); ?>

            <!-- Template Download -->
            <div class="bi-template-card">
              <div class="bi-template-info">
                <h5>Download CSV Import Template</h5>
                <p class="text-muted">Includes all supported task columns: Subject, Checklist Items, Assignees, Tags, Billable, etc.</p>
              </div>
              <a class="bi-btn bi-btn-default" href="<?php echo admin_url('bulk_task_import/download_template'); ?>">
                <i class="fa fa-download"></i> Download Template
              </a>
            </div>

          </div>
        </div>
      </div>

      <!-- Sidebar History -->
      <div class="col-md-3">
        <div class="panel_s bi-panel-card">
          <div class="panel-body">
            <h4 class="bi-sidebar-title"><i class="fa fa-history text-muted"></i> Recent Imports</h4>
            <hr class="bi-hr">
            <?php if (empty($history)) : ?>
              <div class="bi-empty-state">
                <i class="fa-regular fa-folder-open"></i>
                <p class="text-muted"><?php echo _l('bulk_task_import_no_history'); ?></p>
              </div>
            <?php else : foreach (array_slice($history, 0, 5) as $item) : ?>
              <div class="bi-history-card">
                <div class="bi-history-file"><i class="fa-regular fa-file-code"></i> <?php echo html_escape($item->file_name); ?></div>
                <div class="bi-history-meta">
                  <span class="text-success"><i class="fa fa-check"></i> <?php echo (int) $item->successful_rows; ?> success</span>
                  <span class="text-muted"><?php echo date('M d, H:i', strtotime($item->created_at)); ?></span>
                </div>
              </div>
            <?php endforeach; endif; ?>
            <?php if (has_permission('bulk_task_import', '', 'history')) : ?>
              <a href="<?php echo admin_url('bulk_task_import/history'); ?>" class="bi-history-link">View complete history <i class="fa fa-angle-right"></i></a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>