<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="bi-breadcrumb-nav">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo admin_url('tasks'); ?>"><i class="fa fa-tasks"></i> Tasks</a></li>
        <li class="breadcrumb-item"><a href="<?php echo admin_url('bulk_task_import'); ?>">Bulk Task Import</a></li>
        <li class="breadcrumb-item active" aria-current="page">Validation Results</li>
      </ol>
    </nav>

    <div class="row">
      <div class="col-md-12">
        <div class="panel_s bi-panel-card">
          <div class="panel-body">
            
            <!-- Header -->
            <div class="bi-view-header">
              <h2 class="bi-title"><i class="fa fa-shield text-indigo"></i> <?php echo _l('bulk_task_import_validation_results'); ?></h2>
              <p class="bi-subtitle">Review your data before importing. Rows with errors will be skipped.</p>
            </div>

            <!-- Stepper -->
            <div class="bi-stepper-row">
              <div class="bi-step completed">
                <span class="bi-step-num"><i class="fa fa-check"></i></span>
                <span class="bi-step-label"><?php echo _l('bulk_task_import_upload'); ?></span>
              </div>
              <div class="bi-step-divider active"></div>
              <div class="bi-step completed">
                <span class="bi-step-num"><i class="fa fa-check"></i></span>
                <span class="bi-step-label"><?php echo _l('bulk_task_import_map'); ?></span>
              </div>
              <div class="bi-step-divider active"></div>
              <div class="bi-step active">
                <span class="bi-step-num">3</span>
                <span class="bi-step-label"><?php echo _l('bulk_task_import_validate'); ?></span>
              </div>
              <div class="bi-step-divider"></div>
              <div class="bi-step">
                <span class="bi-step-num">4</span>
                <span class="bi-step-label"><?php echo _l('bulk_task_import_import'); ?></span>
              </div>
            </div>

            <!-- Validation Cards -->
            <div class="bi-summary-grid">
              <div class="bi-summary-card">
                <div class="bi-summary-num"><?php echo count($rows); ?></div>
                <div class="bi-summary-label">Total Rows</div>
              </div>
              <div class="bi-summary-card text-success">
                <div class="bi-summary-num text-success"><?php echo (int) $valid_count; ?></div>
                <div class="bi-summary-label">Valid Rows</div>
              </div>
              <div class="bi-summary-card text-warning">
                <div class="bi-summary-num text-warning"><?php echo (int) $warning_count; ?></div>
                <div class="bi-summary-label">Warnings</div>
              </div>
              <div class="bi-summary-card text-danger">
                <div class="bi-summary-num text-danger"><?php echo (int) $error_count; ?></div>
                <div class="bi-summary-label">Errors</div>
              </div>
            </div>

            <!-- Validation Summary Message -->
            <?php if ($error_count > 0) : ?>
              <div class="bi-alert bi-alert-danger" style="margin-bottom:20px;">
                <i class="fa fa-exclamation-triangle"></i> Found <strong><?php echo $error_count; ?></strong> blocking error(s). Please review and correct before continuing.
              </div>
            <?php elseif ($warning_count > 0) : ?>
              <div class="bi-alert bi-alert-warning" style="margin-bottom:20px;">
                <i class="fa fa-exclamation-circle"></i> Found <strong><?php echo $warning_count; ?></strong> warning(s). Rows with warnings will still import.
              </div>
            <?php else : ?>
              <div class="bi-alert bi-alert-success" style="margin-bottom:20px;">
                <i class="fa fa-check-circle"></i> All tasks are valid and ready to import!
              </div>
            <?php endif; ?>

            <!-- Table Filters and Action -->
            <div class="bi-controls-row" style="margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
              <div class="bi-table-filters">
                <button type="button" class="bi-pill active" data-filter="all">All (<?php echo count($rows); ?>)</button>
                <button type="button" class="bi-pill" data-filter="valid">Valid (<?php echo $valid_count; ?>)</button>
                <button type="button" class="bi-pill" data-filter="warning">Warnings (<?php echo $warning_count; ?>)</button>
                <button type="button" class="bi-pill" data-filter="error">Errors (<?php echo $error_count; ?>)</button>
              </div>
              <a href="<?php echo admin_url('bulk_task_import/download_error_report/' . (int)$batch->id); ?>" class="bi-btn bi-btn-default btn-sm">
                <i class="fa fa-download"></i> Download Error Report
              </a>
            </div>

            <!-- Rows Table -->
            <div class="table-responsive bi-table-wrapper">
              <table class="table bi-table table-bordered table-hover" id="bi-validation-table">
                <thead>
                  <tr>
                    <th style="width:70px;">Row</th>
                    <th style="width:250px;"><?php echo _l('bulk_task_import_subject'); ?></th>
                    <th>Issues / Messages</th>
                    <th style="width:100px;"><?php echo _l('bulk_task_import_status'); ?></th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($rows as $row) : 
                    $badge_cls = $row['status'] === 'error' ? 'bi-badge-danger' : ($row['status'] === 'warning' ? 'bi-badge-warning' : 'bi-badge-ok');
                    $msgs = implode('; ', array_merge($row['errors'], $row['warnings'])) ?: '—';
                    $subj = !empty($row['task']['name']) ? html_escape($row['task']['name']) : '<span class="bi-danger-txt"><i>Missing Subject</i></span>';
                  ?>
                    <tr data-status="<?php echo html_escape($row['status']); ?>">
                      <td><strong>#<?php echo (int) $row['row_number']; ?></strong></td>
                      <td><?php echo $subj; ?></td>
                      <td class="bi-msg-cell <?php echo $row['status'] !== 'valid' ? 'text-semibold' : 'text-muted'; ?>">
                        <?php echo html_escape($msgs); ?>
                      </td>
                      <td>
                        <span class="bi-badge <?php echo $badge_cls; ?>">
                          <?php echo html_escape(ucfirst($row['status'])); ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <!-- Action Bar -->
            <?php echo form_open(admin_url('bulk_task_import/import_batch/' . (int) $batch->id)); ?>
              <div class="bi-action-footer">
                <a href="<?php echo admin_url('bulk_task_import/map/' . (int)$batch->id); ?>" class="bi-btn bi-btn-default"><i class="fa fa-arrow-left"></i> Change Mapping</a>
                <button type="submit" class="bi-btn bi-btn-primary" <?php echo $valid_count === 0 ? 'disabled' : ''; ?>>
                  <i class="fa fa-check"></i> <?php echo _l('bulk_task_import_import_valid', $valid_count); ?>
                </button>
              </div>
            <?php echo form_close(); ?>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var $ = window.jQuery;
    if (!$) return;

    $('.bi-pill').on('click', function () {
        $('.bi-pill').removeClass('active');
        $(this).addClass('active');

        var filter = $(this).data('filter');
        $('#bi-validation-table tbody tr').each(function () {
            var status = $(this).data('status');
            if (filter === 'all' || status === filter) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });
});
</script>
<?php init_tail(); ?>