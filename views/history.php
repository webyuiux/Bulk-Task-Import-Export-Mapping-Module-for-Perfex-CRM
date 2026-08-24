<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">

    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="bi-breadcrumb-nav">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo admin_url('tasks'); ?>"><i class="fa fa-tasks"></i> Tasks</a></li>
        <li class="breadcrumb-item"><a href="<?php echo admin_url('bulk_task_import'); ?>">Bulk Task Import</a></li>
        <li class="breadcrumb-item active" aria-current="page">Import History</li>
      </ol>
    </nav>

    <div class="row">
      <div class="col-md-12">
        <div class="panel_s bi-panel-card">
          <div class="panel-body">

            <!-- Header -->
            <div class="bi-view-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
              <div>
                <h2 class="bi-title"><i class="fa fa-history text-indigo"></i> <?php echo html_escape($title); ?></h2>
                <p class="bi-subtitle">View and audit all past spreadsheet imports.</p>
              </div>
              <?php if (is_admin()) : ?>
                <a href="<?php echo admin_url('bulk_task_import/clear_history'); ?>" class="bi-btn bi-btn-default text-danger" onclick="return confirm('Are you sure you want to delete all import history logs? This cannot be undone.');">
                  <i class="fa fa-trash"></i> Clear Import History
                </a>
              <?php endif; ?>
            </div>

            <!-- History Table -->
            <?php if (empty($history)) : ?>
              <div class="bi-empty-state" style="padding: 60px 0; text-align:center;">
                <i class="fa-regular fa-folder-open" style="font-size:45px; color:var(--bi-gray-400); margin-bottom:12px;"></i>
                <h4 style="font-weight:600; color:var(--bi-gray-900);">No Import History found</h4>
                <p class="text-muted" style="font-size:13px;">No spreadsheets have been uploaded yet.</p>
              </div>
            <?php else : ?>
              <div class="table-responsive bi-table-wrapper">
                <table class="table bi-table table-bordered table-hover">
                  <thead>
                    <tr>
                      <th style="width:70px;">ID</th>
                      <th><?php echo _l('bulk_task_import_file'); ?></th>
                      <th><?php echo _l('bulk_task_import_user'); ?></th>
                      <th style="text-align:center;"><?php echo _l('bulk_task_import_total_rows'); ?></th>
                      <th style="text-align:center;"><?php echo _l('bulk_task_import_imported'); ?></th>
                      <th style="text-align:center;"><?php echo _l('bulk_task_import_failed'); ?></th>
                      <th><?php echo _l('bulk_task_import_date'); ?></th>
                      <th style="text-align:center; width:120px;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($history as $item) : 
                      $is_rolled_back = ($item->status === 'rolled_back');
                    ?>
                      <tr class="<?php echo $is_rolled_back ? 'bi-muted-row' : ''; ?>">
                        <td><strong>#<?php echo (int) $item->id; ?></strong></td>
                        <td>
                          <span style="font-weight:600; color:var(--bi-gray-900);"><i class="fa-regular fa-file-excel text-indigo"></i> <?php echo html_escape($item->file_name); ?></span>
                          <?php if ($is_rolled_back) : ?>
                            <span class="label label-danger" style="margin-left:8px;">Rolled Back</span>
                          <?php endif; ?>
                        </td>
                        <td><?php echo html_escape($item->uploaded_by_name); ?></td>
                        <td style="text-align:center;"><?php echo (int) $item->total_rows; ?></td>
                        <td class="text-success" style="text-align:center; font-weight:600;"><?php echo (int) $item->successful_rows; ?></td>
                        <td class="text-danger" style="text-align:center; font-weight:600;"><?php echo (int) $item->failed_rows; ?></td>
                        <td class="text-muted"><?php echo date('M d, Y H:i', strtotime($item->created_at)); ?></td>
                        <td style="text-align:center;">
                          <?php if (!$is_rolled_back && $item->successful_rows > 0 && is_admin()) : ?>
                            <a href="<?php echo admin_url('bulk_task_import/rollback/' . (int)$item->id); ?>" class="btn btn-warning btn-xs" onclick="return confirm('Are you sure you want to rollback this import? This will delete all tasks created by this batch.');">
                              <i class="fa fa-rotate-left"></i> Rollback
                            </a>
                          <?php else : ?>
                            <span class="text-muted">—</span>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>

            <!-- Action Bar -->
            <div class="bi-action-footer">
              <a href="<?php echo admin_url('bulk_task_import'); ?>" class="bi-btn bi-btn-default"><i class="fa fa-arrow-left"></i> Back to Importer</a>
              <div></div>
            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php init_tail(); ?>