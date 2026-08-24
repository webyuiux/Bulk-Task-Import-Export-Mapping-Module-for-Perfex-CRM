<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    
    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="bi-breadcrumb-nav">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?php echo admin_url('tasks'); ?>"><i class="fa fa-tasks"></i> Tasks</a></li>
        <li class="breadcrumb-item"><a href="<?php echo admin_url('bulk_task_import'); ?>">Bulk Task Import</a></li>
        <li class="breadcrumb-item active" aria-current="page">Column Mapping</li>
      </ol>
    </nav>

    <div class="row">
      <div class="col-md-12">
        <div class="panel_s bi-panel-card">
          <div class="panel-body">
            
            <!-- Header -->
            <div class="bi-view-header">
              <h2 class="bi-title"><i class="fa fa-exchange text-indigo"></i> <?php echo _l('bulk_task_import_map_columns'); ?></h2>
              <p class="bi-subtitle"><?php echo _l('bulk_task_import_map_help'); ?></p>
            </div>

            <!-- Stepper -->
            <div class="bi-stepper-row">
              <div class="bi-step completed">
                <span class="bi-step-num"><i class="fa fa-check"></i></span>
                <span class="bi-step-label"><?php echo _l('bulk_task_import_upload'); ?></span>
              </div>
              <div class="bi-step-divider active"></div>
              <div class="bi-step active">
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

            <!-- Map Controls -->
            <div class="bi-controls-row">
              <button type="button" class="bi-btn bi-btn-secondary" id="bi-auto-map">
                <i class="fa fa-magic"></i> Auto Map Columns
              </button>
              <button type="button" class="bi-btn bi-btn-default" id="bi-reset-map">
                <i class="fa fa-rotate-left"></i> Reset Mapping
              </button>
            </div>

            <div id="bi-map-alert"></div>

            <!-- Mapping Form -->
            <?php echo form_open(admin_url('bulk_task_import/validate_rows/' . (int) $batch->id)); ?>
              <div class="table-responsive bi-table-wrapper">
                <table class="table bi-table table-bordered">
                  <thead>
                    <tr>
                      <th>Column Status</th>
                      <th><?php echo _l('bulk_task_import_source_column'); ?></th>
                      <th><?php echo _l('bulk_task_import_target_field'); ?></th>
                      <th><?php echo _l('bulk_task_import_sample_value'); ?></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($headers as $index => $header) : 
                      $sel = $mapping[$index] ?? '';
                      $is_mapped = ($sel !== '');
                    ?>
                      <tr data-index="<?php echo (int) $index; ?>">
                        <td style="width:140px;">
                          <span class="bi-badge <?php echo $is_mapped ? 'bi-badge-ok' : 'bi-badge-grey'; ?>" id="bi-badge-<?php echo $index; ?>">
                            <?php echo $is_mapped ? '✓ Mapped' : 'Unmapped'; ?>
                          </span>
                        </td>
                        <td><strong><?php echo html_escape($header); ?></strong></td>
                        <td>
                          <select class="form-control bi-select" name="mapping[<?php echo (int) $index; ?>]" data-idx="<?php echo $index; ?>" data-header="<?php echo html_escape($header); ?>">
                            <option value="">— Ignore this column —</option>
                            <?php foreach ($fields as $field => $label) : 
                              $req = ($field === 'name') ? ' *' : '';
                              $selected = ($sel === $field) ? 'selected' : '';
                            ?>
                              <option value="<?php echo html_escape($field); ?>" <?php echo $selected; ?>>
                                <?php echo html_escape($label) . $req; ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </td>
                        <td class="text-muted"><?php echo html_escape($rows[0][$index] ?? '—'); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <!-- Action Bar -->
              <div class="bi-action-footer">
                <a href="<?php echo admin_url('bulk_task_import'); ?>" class="bi-btn bi-btn-default"><i class="fa fa-arrow-left"></i> Back to Upload</a>
                <button type="submit" class="bi-btn bi-btn-primary" id="bi-to-validate-btn"><?php echo _l('bulk_task_import_validate'); ?> <i class="fa fa-arrow-right"></i></button>
              </div>
            <?php echo form_close(); ?>

          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Inline Mapping Logic -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    var $ = window.jQuery;
    if (!$) return;

    var aliases = {
        name:             ['name', 'subject', 'task', 'task_subject', 'title', 'task_name'],
        description:      ['description', 'task_description', 'details', 'body', 'notes', 'desc'],
        startdate:        ['startdate', 'start_date', 'start', 'startdt', 'start_dt'],
        duedate:          ['duedate', 'due_date', 'due', 'deadline', 'end_date', 'duedt'],
        priority:         ['priority', 'level', 'importance'],
        hourly_rate:      ['hourly_rate', 'rate', 'price', 'hourly'],
        estimated_hours:  ['estimated_hours', 'hours', 'estimation', 'est_hours'],
        billable:         ['billable', 'is_billable', 'billed'],
        tags:             ['tags', 'tag', 'keywords', 'labels'],
        status:           ['status', 'task_status', 'state'],
        assigned_to:      ['assigned_to', 'assigned', 'assignee', 'staff', 'user', 'owner', 'assignees'],
        checklist_items:  ['checklist_items', 'checklists', 'checklist', 'subtasks', 'subtask']
    };

    function highlightSelects() {
        $('select.bi-select').each(function () {
            var val = $(this).val();
            var idx = $(this).data('idx');
            var $badge = $('#bi-badge-' + idx);
            
            $(this).removeClass('mapped-req mapped-opt');
            if (val === 'name') {
                $(this).addClass('mapped-req');
                $badge.text('✓ Mapped').removeClass('bi-badge-grey').addClass('bi-badge-ok');
            } else if (val) {
                $(this).addClass('mapped-opt');
                $badge.text('✓ Mapped').removeClass('bi-badge-grey').addClass('bi-badge-ok');
            } else {
                $badge.text('Unmapped').removeClass('bi-badge-ok').addClass('bi-badge-grey');
            }
        });
        checkMappingValidity();
    }

    function checkMappingValidity() {
        var nameMapped = false;
        $('select.bi-select').each(function () {
            if ($(this).val() === 'name') {
                nameMapped = true;
                return false;
            }
        });

        var $alert = $('#bi-map-alert');
        var $btn = $('#bi-to-validate-btn');
        if (!nameMapped) {
            $alert.html('<div class="bi-alert bi-alert-danger" style="margin-bottom:16px;"><i class="fa fa-times-circle"></i> <strong>Task Subject (Name) is required.</strong> Please map at least one column to "Task Subject *".</div>');
            $btn.prop('disabled', true);
        } else {
            $alert.empty();
            $btn.prop('disabled', false);
        }
    }

    $('select.bi-select').on('change', highlightSelects);

    $('#bi-auto-map').on('click', function (e) {
        e.preventDefault();
        $('select.bi-select').each(function () {
            var header = $(this).data('header').trim().toLowerCase().replace(/[^a-z0-9]+/g, '_');
            var select = $(this);
            select.val('');
            $.each(aliases, function (field, list) {
                if (list.indexOf(header) !== -1) {
                    select.val(field);
                    return false;
                }
            });
        });
        highlightSelects();
    });

    $('#bi-reset-map').on('click', function (e) {
        e.preventDefault();
        $('select.bi-select').val('');
        highlightSelects();
    });

    // Run initial highlights
    highlightSelects();
});
</script>
<?php init_tail(); ?>