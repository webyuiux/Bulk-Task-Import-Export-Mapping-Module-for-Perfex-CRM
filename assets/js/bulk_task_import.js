/**
 * Perfex CRM — Bulk Task Importer SPA
 * Design: Stitch-inspired (clean, card-based, modern SaaS)
 * v1.1.0
 */

(function ($) {
    'use strict';

    /* ─────────────────────────────────────────────────────
       STATE
    ───────────────────────────────────────────────────── */
    var state = {
        currentStep: 1,
        batchId: null,
        fileName: '',
        fileSize: 0,
        rowsCount: 0,
        columnsCount: 0,
        headers: [],
        mapping: {},
        sampleData: [],
        allowedFields: {},
        validationRows: [],
        summary: { total: 0, valid: 0, warning: 0, error: 0, imported: 0, skipped: 0 },
        activeFilter: 'all',
        searchQuery: '',
        isUploading: false,
        isValidating: false,
        isImporting: false
    };

    /* ─────────────────────────────────────────────────────
       PROJECT HELPER
    ───────────────────────────────────────────────────── */
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

    /* ─────────────────────────────────────────────────────
       BOOT — runs on DOM ready
    ───────────────────────────────────────────────────── */
    $(function () {
        console.log('[BulkImport] v1.1.1 SPA engine ready.');
        // Expose openSPA globally so the PHP-injected button can call it
        window.openBulkImportSPA = openSPA;
        checkUrlAutoOpen();
    });


    /* ─────────────────────────────────────────────────────
       BUTTON INJECTION
    ───────────────────────────────────────────────────── */

    /**
     * Polls until the "New Task" button exists and injects the Bulk Import button before it.
     * Uses 5 fallback strategies to locate the button.
     */
    function injectBulkImportButton() {
        // Only run on the tasks page
        if (window.location.href.indexOf('/admin/tasks') === -1) return;

        var attempts = 0;
        var maxAttempts = 30; // 15 seconds total

        var timer = setInterval(function () {
            // Already injected?
            if ($('.btn-bulk-import').length > 0) {
                clearInterval(timer);
                return;
            }

            var $target = findNewTaskButton();

            if ($target && $target.length > 0) {
                clearInterval(timer);

                var $btn = $(
                    '<a href="#" class="btn btn-default btn-bulk-import" id="btn-open-bulk-import">' +
                    '<i class="fa fa-upload" style="margin-right:5px;"></i>Bulk Import' +
                    '</a>'
                );

                $btn.css({ marginRight: '5px' });
                $target.before($btn);
                console.log('[BulkImport] Button injected beside:', $target[0].outerHTML.slice(0, 100));

                // Delegate click so it survives DOM re-renders
                $(document).off('click.bulkimport').on('click.bulkimport', '#btn-open-bulk-import, .btn-bulk-import', function (e) {
                    e.preventDefault();
                    openSPA();
                });
            } else {
                attempts++;
                if (attempts >= maxAttempts) {
                    clearInterval(timer);
                    console.warn('[BulkImport] Gave up finding New Task button after', maxAttempts, 'attempts.');
                }
            }
        }, 500);
    }

    /**
     * Tries multiple strategies to locate the New Task button.
     * Returns a jQuery object or null.
     */
    function findNewTaskButton() {
        var $btn;

        // 1. href contains tasks/task
        $btn = $('a[href*="tasks/task"]').first();
        if ($btn.length) return $btn;

        // 2. data-toggle modal with task keyword
        $btn = $('[data-toggle="modal"]').filter(function () {
            return ($(this).attr('href') || $(this).data('target') || '').toLowerCase().indexOf('task') !== -1;
        }).first();
        if ($btn.length) return $btn;

        // 3. Text content "New Task" in any button/link
        $btn = $('a.btn, button.btn').filter(function () {
            return $(this).text().replace(/\s+/g, ' ').trim().toLowerCase().indexOf('new task') !== -1;
        }).first();
        if ($btn.length) return $btn;

        // 4. Button with a + / fa-plus icon  
        $btn = $('a.btn:has(.fa-plus), a.btn:has(.glyphicon-plus), button.btn:has(.fa-plus)').first();
        if ($btn.length) return $btn;

        // 5. First .btn-primary in the content area
        $btn = $('.content .btn-primary, #wrapper .btn-primary').filter(function () {
            return $(this).text().replace(/\s+/g, ' ').trim().toLowerCase().indexOf('task') !== -1;
        }).first();
        if ($btn.length) return $btn;

        return null;
    }

    /* ─────────────────────────────────────────────────────
       SPA OPEN / CLOSE
    ───────────────────────────────────────────────────── */

    function openSPA() {
        var $body = $('body');

        // Create container if missing
        if ($('#bulk-import-spa').length === 0) {
            $('body').append('<div id="bulk-import-spa" style="display:none;"></div>');
            bindContainerEvents();
        }

        $body.addClass('bulk-import-open');
        $('#bulk-import-spa').fadeIn(250);

        state.currentStep = 1;
        state.batchId = null;
        state.fileName = '';
        resetState();
        renderSPA();
    }

    function closeSPA() {
        $('body').removeClass('bulk-import-open');
        $('#bulk-import-spa').fadeOut(200);

        // Reload the tasks DataTable if available
        try {
            if (typeof dataTables !== 'undefined' && dataTables['.table-tasks']) {
                dataTables['.table-tasks'].DataTable().ajax.reload(null, false);
            } else if ($('.table-tasks').length) {
                $('.table-tasks').DataTable().ajax.reload(null, false);
            }
        } catch (e) { /* ignore */ }
    }

    function resetState() {
        state.currentStep = 1;
        state.batchId = null;
        state.fileName = '';
        state.fileSize = 0;
        state.rowsCount = 0;
        state.columnsCount = 0;
        state.headers = [];
        state.mapping = {};
        state.sampleData = [];
        state.allowedFields = {};
        state.validationRows = [];
        state.summary = { total: 0, valid: 0, warning: 0, error: 0, imported: 0, skipped: 0 };
        state.activeFilter = 'all';
        state.searchQuery = '';
        state.isUploading = false;
        state.isValidating = false;
        state.isImporting = false;
    }

    /* ─────────────────────────────────────────────────────
       URL QUERY AUTO-OPEN
    ───────────────────────────────────────────────────── */
    function checkUrlAutoOpen() {
        if (window.location.href.indexOf('/admin/tasks') === -1) return;
        var params = new URLSearchParams(window.location.search);
        if (params.get('bulk_import') === '1') {
            var clean = window.location.protocol + '//' + window.location.host + window.location.pathname;
            window.history.replaceState({}, '', clean);
            setTimeout(openSPA, 400);
        }
    }

    /* ─────────────────────────────────────────────────────
       RENDER ENGINE
    ───────────────────────────────────────────────────── */
    function renderSPA() {
        var $spa = $('#bulk-import-spa');
        if (!$spa.length) return;
        $spa.html(buildSPAHtml());
        afterRender();
    }

    function buildSPAHtml() {
        var bodyHtml = '';

        if (state.isUploading || state.isValidating || state.isImporting) {
            bodyHtml = buildLoaderHtml();
        } else {
            switch (state.currentStep) {
                case 1: bodyHtml = buildStep1Html(); break;
                case 2: bodyHtml = buildStep2Html(); break;
                case 3: bodyHtml = buildStep3Html(); break;
                case 4: bodyHtml = buildStep4Html(); break;
            }
        }

        return (
            '<div class="bi-overlay" id="bi-overlay"></div>' +
            '<div class="bi-panel" id="bi-panel">' +
            '  <div class="bi-panel-inner">' +
            '    <div class="bi-topbar">' +
            '      <div class="bi-topbar-left">' +
            '        <div class="bi-topbar-icon"><i class="fa fa-upload"></i></div>' +
            '        <div>' +
            '          <div class="bi-topbar-title">Bulk Task Import</div>' +
            '          <div class="bi-topbar-sub">Import multiple tasks from a spreadsheet</div>' +
            '        </div>' +
            '      </div>' +
            '      <button class="bi-close-btn" id="bi-close-btn" title="Close">' +
            '        <i class="fa fa-times"></i>' +
            '      </button>' +
            '    </div>' +
            buildStepperHtml() +
            '    <div class="bi-body">' + bodyHtml + '</div>' +
            '  </div>' +
            '</div>'
        );
    }

    function buildStepperHtml() {
        var s = state.currentStep;
        var steps = [
            { n: 1, label: 'Upload' },
            { n: 2, label: 'Mapping' },
            { n: 3, label: 'Validation' },
            { n: 4, label: 'Complete' }
        ];

        var html = '<div class="bi-stepper">';
        steps.forEach(function (step, i) {
            var cls = '';
            if (s === step.n) cls = 'active';
            else if (s > step.n) cls = 'done';

            var circle = s > step.n ? '<i class="fa fa-check"></i>' : step.n;

            html += '<div class="bi-step ' + cls + '">';
            html += '  <div class="bi-step-circle">' + circle + '</div>';
            html += '  <div class="bi-step-label">' + step.label + '</div>';
            html += '</div>';

            if (i < steps.length - 1) {
                html += '<div class="bi-step-line ' + (s > step.n ? 'done' : '') + '"></div>';
            }
        });
        html += '</div>';
        return html;
    }

    /* ─────────────────────────────────────────────────────
       STEP 1 — UPLOAD
    ───────────────────────────────────────────────────── */
    function buildStep1Html() {
        var tplUrl = (typeof admin_url !== 'undefined')
            ? admin_url.replace('index.php/', '').replace('admin/', '') + 'modules/bulk_task_import/assets/templates/bulk-task-import-template.csv'
            : '#';

        if (state.batchId) {
            // File already loaded
            return (
                '<div class="bi-step-header">' +
                '  <h2>Upload Spreadsheet</h2>' +
                '  <p>Your file has been uploaded and is ready for column mapping.</p>' +
                '</div>' +
                '<div class="bi-card">' +
                '  <div class="bi-file-preview">' +
                '    <div class="bi-file-icon"><i class="fa fa-file-excel-o"></i></div>' +
                '    <div class="bi-file-info">' +
                '      <div class="bi-file-name">' + escHtml(state.fileName) + '</div>' +
                '      <div class="bi-file-meta">' +
                '        <span>' + fmtBytes(state.fileSize) + '</span>' +
                '        <span class="bi-dot">·</span>' +
                '        <span><strong>' + state.rowsCount + '</strong> rows detected</span>' +
                '        <span class="bi-dot">·</span>' +
                '        <span><strong>' + state.columnsCount + '</strong> columns detected</span>' +
                '      </div>' +
                '      <div class="bi-file-ok"><i class="fa fa-check-circle"></i> Ready for mapping</div>' +
                '    </div>' +
                '    <button class="bi-btn bi-btn-ghost" id="bi-replace-btn">Replace File</button>' +
                '  </div>' +
                '</div>' +
                '<div class="bi-footer">' +
                '  <div></div>' +
                '  <button class="bi-btn bi-btn-primary" id="bi-to-map-btn">Continue to Mapping <i class="fa fa-arrow-right"></i></button>' +
                '</div>'
            );
        }

        return (
            '<div class="bi-step-header">' +
            '  <h2>Upload Spreadsheet</h2>' +
            '  <p>Drag and drop your CSV or Excel file, or click to browse. Download the template to get started.</p>' +
            '</div>' +
            '<div class="bi-card">' +
            '  <div class="bi-dropzone" id="bi-dropzone">' +
            '    <div class="bi-dropzone-icon"><i class="fa fa-cloud-upload"></i></div>' +
            '    <div class="bi-dropzone-title">Drop your file here</div>' +
            '    <div class="bi-dropzone-sub">Supports <strong>.csv</strong> and <strong>.xlsx</strong> files</div>' +
            '    <button class="bi-btn bi-btn-primary bi-browse-btn" type="button">Choose File</button>' +
            '    <input type="file" id="bi-file-input" accept=".csv,.xlsx" style="display:none;">' +
            '  </div>' +
            '  <div class="bi-template-row">' +
            '    <a href="' + tplUrl + '" class="bi-template-link" download>' +
            '      <i class="fa fa-download"></i> Download Import Template' +
            '    </a>' +
            '    <span class="bi-template-hint">Use this template for best compatibility</span>' +
            '  </div>' +
            '</div>'
        );
    }

    /* ─────────────────────────────────────────────────────
       STEP 2 — MAPPING
    ───────────────────────────────────────────────────── */
    function buildStep2Html() {
        var mappedCount = 0;
        var unmappedCount = 0;

        var rowsHtml = '';
        state.headers.forEach(function (header, idx) {
            var sel = state.mapping[idx] || '';
            var isMapped = sel !== '';
            if (isMapped) mappedCount++; else unmappedCount++;

            var optHtml = '<option value="">— Ignore this column —</option>';
            $.each(state.allowedFields, function (key, label) {
                var selected = sel === key ? 'selected' : '';
                var req = key === 'name' ? ' *' : '';
                optHtml += '<option value="' + key + '" ' + selected + '>' + label + req + '</option>';
            });

            var badgeCls = isMapped ? 'bi-badge-ok' : 'bi-badge-grey';
            var badgeTxt = isMapped ? '✓ Mapped' : 'Unmapped';
            var selCls = isMapped ? (sel === 'name' ? 'mapped-req' : 'mapped-opt') : '';
            var sample = (state.sampleData[idx] !== null && state.sampleData[idx] !== undefined) ? state.sampleData[idx] : '—';

            rowsHtml += '<tr data-index="' + idx + '">';
            rowsHtml += '  <td><strong>' + escHtml(header) + '</strong></td>';
            rowsHtml += '  <td class="bi-muted">' + escHtml(String(sample)) + '</td>';
            rowsHtml += '  <td>';
            rowsHtml += '    <div class="bi-select-wrap">';
            rowsHtml += '      <select class="bi-select ' + selCls + '" data-idx="' + idx + '">' + optHtml + '</select>';
            rowsHtml += '    </div>';
            rowsHtml += '  </td>';
            rowsHtml += '  <td><span class="bi-badge ' + badgeCls + '" id="bi-badge-' + idx + '">' + badgeTxt + '</span></td>';
            rowsHtml += '</tr>';
        });

        return (
            '<div class="bi-step-header">' +
            '  <h2>Map Columns</h2>' +
            '  <p>Match your spreadsheet columns to Perfex CRM task fields. At minimum, <strong>Task Subject</strong> must be mapped.</p>' +
            '</div>' +
            '<div class="bi-card">' +
            '  <div class="bi-map-toolbar">' +
            '    <div class="bi-map-stats">' +
            '      <span class="bi-chip bi-chip-primary"><strong>' + mappedCount + '</strong> Mapped</span>' +
            '      <span class="bi-chip bi-chip-grey"><strong>' + unmappedCount + '</strong> Unmapped</span>' +
            '    </div>' +
            '    <div class="bi-map-actions">' +
            '      <button class="bi-btn bi-btn-ghost" id="bi-auto-map-btn"><i class="fa fa-magic"></i> Auto Map</button>' +
            '      <button class="bi-btn bi-btn-ghost" id="bi-reset-map-btn"><i class="fa fa-refresh"></i> Reset</button>' +
            '    </div>' +
            '  </div>' +
            '  <div id="bi-map-alert"></div>' +
            '  <div class="bi-table-wrap">' +
            '    <table class="bi-table" id="bi-map-table">' +
            '      <thead><tr>' +
            '        <th>Spreadsheet Column</th><th>Sample Data</th><th>Perfex CRM Field</th><th>Status</th>' +
            '      </tr></thead>' +
            '      <tbody>' + rowsHtml + '</tbody>' +
            '    </table>' +
            '  </div>' +
            '</div>' +
            '<div class="bi-footer">' +
            '  <button class="bi-btn bi-btn-ghost" id="bi-back-upload-btn"><i class="fa fa-arrow-left"></i> Back</button>' +
            '  <button class="bi-btn bi-btn-primary" id="bi-to-validate-btn">Continue to Validation <i class="fa fa-arrow-right"></i></button>' +
            '</div>'
        );
    }

    /* ─────────────────────────────────────────────────────
       STEP 3 — VALIDATION
    ───────────────────────────────────────────────────── */
    function buildStep3Html() {
        var hasErrors = state.summary.error > 0;

        var alertHtml = '';
        if (hasErrors) {
            alertHtml = '<div class="bi-alert bi-alert-danger"><i class="fa fa-exclamation-circle"></i> <strong>' + state.summary.error + ' rows have errors</strong> — fix them in your spreadsheet, or they will be skipped during import.</div>';
        } else if (state.summary.warning > 0) {
            alertHtml = '<div class="bi-alert bi-alert-warning"><i class="fa fa-exclamation-triangle"></i> <strong>' + state.summary.valid + ' tasks ready.</strong> ' + state.summary.warning + ' rows have warnings but can still be imported.</div>';
        } else {
            alertHtml = '<div class="bi-alert bi-alert-success"><i class="fa fa-check-circle"></i> <strong>All ' + state.summary.valid + ' task rows are valid</strong> and ready to import!</div>';
        }

        var dlUrl = (typeof admin_url !== 'undefined') ? admin_url + 'bulk_task_import/download_error_report/' + state.batchId : '#';
        var importDisabled = hasErrors ? 'disabled' : '';

        return (
            '<div class="bi-step-header">' +
            '  <h2>Validation Results</h2>' +
            '  <p>Review your data before importing. Rows with errors will be skipped.</p>' +
            '</div>' +
            '<div class="bi-summary-row">' +
            '  <div class="bi-summary-card total"><div class="bi-summary-num">' + state.summary.total + '</div><div class="bi-summary-lbl">Total</div></div>' +
            '  <div class="bi-summary-card valid"><div class="bi-summary-num">' + state.summary.valid + '</div><div class="bi-summary-lbl">Valid</div></div>' +
            '  <div class="bi-summary-card warning"><div class="bi-summary-num">' + state.summary.warning + '</div><div class="bi-summary-lbl">Warnings</div></div>' +
            '  <div class="bi-summary-card error"><div class="bi-summary-num">' + state.summary.error + '</div><div class="bi-summary-lbl">Errors</div></div>' +
            '</div>' +
            alertHtml +
            '<div class="bi-card">' +
            '  <div class="bi-filter-bar">' +
            '    <div class="bi-filter-pills">' +
            '      <button class="bi-pill ' + (state.activeFilter === 'all' ? 'active' : '') + '" data-filter="all">All (' + state.summary.total + ')</button>' +
            '      <button class="bi-pill ' + (state.activeFilter === 'valid' ? 'active' : '') + '" data-filter="valid">Valid (' + state.summary.valid + ')</button>' +
            '      <button class="bi-pill ' + (state.activeFilter === 'warning' ? 'active' : '') + '" data-filter="warning">Warnings (' + state.summary.warning + ')</button>' +
            '      <button class="bi-pill ' + (state.activeFilter === 'error' ? 'active' : '') + '" data-filter="error">Errors (' + state.summary.error + ')</button>' +
            '    </div>' +
            '    <div class="bi-filter-right">' +
            '      <input type="text" class="bi-search" id="bi-search" placeholder="Search rows..." value="' + escHtml(state.searchQuery) + '">' +
            '      <a href="' + dlUrl + '" class="bi-btn bi-btn-ghost"><i class="fa fa-download"></i> Error Report</a>' +
            '    </div>' +
            '  </div>' +
            '  <div class="bi-table-wrap">' +
            '    <table class="bi-table">' +
            '      <thead><tr><th style="width:70px">Row</th><th>Task Subject</th><th>Issues</th><th style="width:110px">Status</th></tr></thead>' +
            '      <tbody id="bi-validation-tbody">' + buildValidationRows() + '</tbody>' +
            '    </table>' +
            '  </div>' +
            '</div>' +
            '<div class="bi-footer">' +
            '  <button class="bi-btn bi-btn-ghost" id="bi-back-map-btn"><i class="fa fa-arrow-left"></i> Back</button>' +
            '  <button class="bi-btn bi-btn-primary" id="bi-import-btn" ' + importDisabled + '><i class="fa fa-check"></i> Import Tasks</button>' +
            '</div>'
        );
    }

    function buildValidationRows() {
        var rows = state.validationRows.filter(function (r) {
            if (state.activeFilter !== 'all' && r.status !== state.activeFilter) return false;
            if (state.searchQuery) {
                var q = state.searchQuery.toLowerCase();
                var subj = (r.task.name || '').toLowerCase();
                var msgs = ([].concat(r.errors || [], r.warnings || [])).join(' ').toLowerCase();
                if (subj.indexOf(q) === -1 && String(r.row_number).indexOf(q) === -1 && msgs.indexOf(q) === -1) return false;
            }
            return true;
        });

        if (rows.length === 0) {
            return '<tr><td colspan="4" class="bi-empty">No matching rows found.</td></tr>';
        }

        return rows.slice(0, 200).map(function (r) {
            var badgeCls = r.status === 'error' ? 'bi-badge-danger' : (r.status === 'warning' ? 'bi-badge-warning' : 'bi-badge-ok');
            var statusLabel = { error: 'Error', warning: 'Warning', valid: 'Valid' }[r.status] || r.status;
            var msgs = [].concat(r.errors || [], r.warnings || []).map(escHtml).join('<br>') || '<span class="bi-muted">—</span>';
            var subj = r.task.name ? escHtml(r.task.name) : '<span class="bi-danger-txt"><i>Missing Subject</i></span>';
            return (
                '<tr>' +
                '  <td><strong>#' + r.row_number + '</strong></td>' +
                '  <td>' + subj + '</td>' +
                '  <td class="bi-msg-cell">' + msgs + '</td>' +
                '  <td><span class="bi-badge ' + badgeCls + '">' + statusLabel + '</span></td>' +
                '</tr>'
            );
        }).join('');
    }

    /* ─────────────────────────────────────────────────────
       STEP 4 — COMPLETE
    ───────────────────────────────────────────────────── */
    function buildStep4Html() {
        var dlUrl = (typeof admin_url !== 'undefined') ? admin_url + 'bulk_task_import/download_import_report/' + state.batchId : '#';

        return (
            '<div class="bi-step-header">' +
            '  <h2>Import Complete</h2>' +
            '  <p>Your tasks have been successfully imported into Perfex CRM.</p>' +
            '</div>' +
            '<div class="bi-card bi-success-card">' +
            '  <div class="bi-success-icon"><i class="fa fa-check"></i></div>' +
            '  <h3 class="bi-success-title">Import Completed!</h3>' +
            '  <p class="bi-success-sub">Here\'s a summary of your import batch.</p>' +
            '  <div class="bi-summary-row">' +
            '    <div class="bi-summary-card total"><div class="bi-summary-num">' + state.summary.total + '</div><div class="bi-summary-lbl">Total</div></div>' +
            '    <div class="bi-summary-card valid"><div class="bi-summary-num" style="color:var(--bi-green)">' + state.summary.imported + '</div><div class="bi-summary-lbl">Imported</div></div>' +
            '    <div class="bi-summary-card warning"><div class="bi-summary-num" style="color:var(--bi-amber)">' + state.summary.warning + '</div><div class="bi-summary-lbl">Warnings</div></div>' +
            '    <div class="bi-summary-card error"><div class="bi-summary-num" style="color:var(--bi-red)">' + state.summary.skipped + '</div><div class="bi-summary-lbl">Skipped</div></div>' +
            '  </div>' +
            '  <div class="bi-complete-actions">' +
            '    <button class="bi-btn bi-btn-primary" id="bi-view-tasks-btn"><i class="fa fa-list"></i> View Tasks</button>' +
            '    <button class="bi-btn bi-btn-ghost" id="bi-import-another-btn"><i class="fa fa-upload"></i> Import Another</button>' +
            '    <a href="' + dlUrl + '" class="bi-btn bi-btn-ghost"><i class="fa fa-download"></i> Download Report</a>' +
            '  </div>' +
            '</div>'
        );
    }

    /* ─────────────────────────────────────────────────────
       LOADER
    ───────────────────────────────────────────────────── */
    function buildLoaderHtml() {
        var msg = state.isUploading ? 'Reading spreadsheet...' :
                  state.isValidating ? 'Validating rows...' : 'Importing tasks...';
        return (
            '<div class="bi-loader-wrap">' +
            '  <div class="bi-spinner"></div>' +
            '  <div class="bi-loader-title">' + msg + '</div>' +
            '  <div class="bi-loader-sub">Please do not close or refresh this window.</div>' +
            '</div>'
        );
    }

    /* ─────────────────────────────────────────────────────
       AFTER RENDER — dropzone + events
    ───────────────────────────────────────────────────── */
    function afterRender() {
        if (state.currentStep === 1 && !state.batchId) {
            initDropzone();
        }
        if (state.currentStep === 2) {
            checkMappingValidity();
        }
    }

    function initDropzone() {
        var $dz = $('#bi-dropzone');
        var $input = $('#bi-file-input');

        if (!$dz.length) return;

        $dz.on('click', function (e) {
            if (!$(e.target).is('button, input')) {
                $input.trigger('click');
            }
        });

        $('.bi-browse-btn').on('click', function (e) {
            e.stopPropagation();
            $input.trigger('click');
        });

        $input.on('change', function () {
            if (this.files[0]) uploadFile(this.files[0]);
        });

        $dz[0].addEventListener('dragover', function (e) { e.preventDefault(); $dz.addClass('drag-over'); });
        $dz[0].addEventListener('dragleave', function () { $dz.removeClass('drag-over'); });
        $dz[0].addEventListener('drop', function (e) {
            e.preventDefault();
            $dz.removeClass('drag-over');
            if (e.dataTransfer.files[0]) uploadFile(e.dataTransfer.files[0]);
        });
    }

    /* ─────────────────────────────────────────────────────
       EVENT DELEGATION
    ───────────────────────────────────────────────────── */
    function bindContainerEvents() {
        var $spa = $('#bulk-import-spa');

        // Close button & overlay
        $spa.on('click', '#bi-close-btn', closeSPA);
        $spa.on('click', '#bi-overlay', closeSPA);

        // Step 1
        $spa.on('click', '#bi-replace-btn', function () {
            state.batchId = null; state.fileName = ''; renderSPA();
        });
        $spa.on('click', '#bi-to-map-btn', function () {
            state.currentStep = 2; renderSPA();
        });

        // Step 2
        $spa.on('click', '#bi-back-upload-btn', function () {
            state.currentStep = 1; renderSPA();
        });
        $spa.on('click', '#bi-auto-map-btn', function () {
            state.mapping = autoMapHeaders(state.headers);
            renderSPA();
        });
        $spa.on('click', '#bi-reset-map-btn', function () {
            state.mapping = {};
            renderSPA();
        });
        $spa.on('change', '.bi-select', function () {
            var idx = parseInt($(this).data('idx'));
            var val = $(this).val();
            state.mapping[idx] = val;

            // Update badge + border
            var $badge = $('#bi-badge-' + idx);
            $(this).removeClass('mapped-req mapped-opt');
            if (val) {
                $badge.text('✓ Mapped').removeClass('bi-badge-grey').addClass('bi-badge-ok');
                $(this).addClass(val === 'name' ? 'mapped-req' : 'mapped-opt');
            } else {
                $badge.text('Unmapped').removeClass('bi-badge-ok').addClass('bi-badge-grey');
            }
            checkMappingValidity();
        });
        $spa.on('click', '#bi-to-validate-btn', validateMapping);

        // Step 3
        $spa.on('click', '#bi-back-map-btn', function () {
            state.currentStep = 2; renderSPA();
        });
        $spa.on('click', '.bi-pill', function () {
            $('.bi-pill').removeClass('active');
            $(this).addClass('active');
            state.activeFilter = $(this).data('filter');
            $('#bi-validation-tbody').html(buildValidationRows());
        });
        $spa.on('input', '#bi-search', function () {
            state.searchQuery = $(this).val();
            $('#bi-validation-tbody').html(buildValidationRows());
        });
        $spa.on('click', '#bi-import-btn', executeImport);

        // Step 4
        $spa.on('click', '#bi-view-tasks-btn', closeSPA);
        $spa.on('click', '#bi-import-another-btn', function () {
            resetState();
            renderSPA();
        });

        // ESC key
        $(document).on('keydown.biesc', function (e) {
            if (e.key === 'Escape' && $('#bulk-import-spa').is(':visible')) {
                closeSPA();
            }
        });
    }

    /* ─────────────────────────────────────────────────────
       MAPPING VALIDITY CHECK
    ───────────────────────────────────────────────────── */
    function checkMappingValidity() {
        var nameMapped = false;
        $.each(state.mapping, function (k, v) { if (v === 'name') { nameMapped = true; return false; } });

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

    /* ─────────────────────────────────────────────────────
       AJAX — UPLOAD
    ───────────────────────────────────────────────────── */
    function uploadFile(file) {
        var ext = file.name.split('.').pop().toLowerCase();
        if (ext !== 'csv' && ext !== 'xlsx') {
            showNotice('danger', 'Please upload a CSV or XLSX file.');
            return;
        }

        var fd = new FormData();
        fd.append('file', file);
        if (typeof csrfData !== 'undefined') {
            fd.append(csrfData.token_name, csrfData.hash);
        }

        state.isUploading = true;
        renderSPA();

        var projectId = getProjectId();
        var uploadUrl = admin_url + 'bulk_task_import/upload';
        if (projectId) {
            uploadUrl += '?project_id=' + projectId;
        }

        $.ajax({
            url: uploadUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function (res) {
                state.isUploading = false;
                if (res.status === 'success') {
                    state.batchId = res.batch_id;
                    state.fileName = res.file_name;
                    state.fileSize = res.file_size;
                    state.rowsCount = res.rows_count;
                    state.columnsCount = res.columns_count;
                    state.headers = res.headers;
                    state.mapping = res.mapping;
                    state.sampleData = res.sample_data;
                    state.allowedFields = res.allowed_fields;
                } else {
                    showNotice('danger', res.message || 'Upload failed. Please check the file and try again.');
                    state.batchId = null;
                }
                renderSPA();
            },
            error: function () {
                state.isUploading = false;
                state.batchId = null;
                showNotice('danger', 'Upload error. Please make sure the file is valid and within the size limit.');
                renderSPA();
            }
        });
    }

    /* ─────────────────────────────────────────────────────
       AJAX — VALIDATE
    ───────────────────────────────────────────────────── */
    function validateMapping() {
        state.isValidating = true;
        renderSPA();

        var data = { mapping: state.mapping };
        if (typeof csrfData !== 'undefined') {
            data[csrfData.token_name] = csrfData.hash;
        }

        $.ajax({
            url: admin_url + 'bulk_task_import/validate_rows/' + state.batchId,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function (res) {
                state.isValidating = false;
                if (res.status === 'success') {
                    state.validationRows = res.rows;
                    state.summary.total = res.rows.length;
                    state.summary.valid = res.valid_count;
                    state.summary.warning = res.warning_count;
                    state.summary.error = res.error_count;
                    state.currentStep = 3;
                    state.activeFilter = 'all';
                    state.searchQuery = '';
                } else {
                    showNotice('danger', res.message || 'Validation failed.');
                    state.currentStep = 2;
                }
                renderSPA();
            },
            error: function () {
                state.isValidating = false;
                showNotice('danger', 'Validation error. Please try again.');
                state.currentStep = 2;
                renderSPA();
            }
        });
    }

    /* ─────────────────────────────────────────────────────
       AJAX — IMPORT
    ───────────────────────────────────────────────────── */
    function executeImport() {
        state.isImporting = true;
        renderSPA();

        var data = {};
        if (typeof csrfData !== 'undefined') {
            data[csrfData.token_name] = csrfData.hash;
        }

        $.ajax({
            url: admin_url + 'bulk_task_import/import_batch/' + state.batchId,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function (res) {
                state.isImporting = false;
                if (res.status === 'success') {
                    state.summary.imported = res.summary.imported;
                    state.summary.skipped = res.summary.skipped;
                    state.summary.total = res.summary.total;
                    state.currentStep = 4;
                    showNotice('success', 'Import completed! ' + res.summary.imported + ' tasks added.');
                } else {
                    showNotice('danger', res.message || 'Import failed.');
                    state.currentStep = 3;
                }
                renderSPA();
            },
            error: function () {
                state.isImporting = false;
                showNotice('danger', 'Import error. Please try again.');
                state.currentStep = 3;
                renderSPA();
            }
        });
    }

    /* ─────────────────────────────────────────────────────
       AUTO MAP
    ───────────────────────────────────────────────────── */
    function autoMapHeaders(headers) {
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
            status:           ['status', 'task_status', 'state']
        };

        var map = {};
        headers.forEach(function (h, i) {
            var norm = h.trim().toLowerCase().replace(/[^a-z0-9]+/g, '_');
            map[i] = '';
            $.each(aliases, function (field, list) {
                if (list.indexOf(norm) !== -1) { map[i] = field; return false; }
            });
            if (!map[i] && state.allowedFields[norm] !== undefined) {
                map[i] = norm;
            }
        });
        return map;
    }

    /* ─────────────────────────────────────────────────────
       UTILITIES
    ───────────────────────────────────────────────────── */
    function showNotice(type, msg) {
        if (typeof alert_float === 'function') {
            alert_float(type, msg);
        } else {
            console.log('[BulkImport] Notice [' + type + ']:', msg);
        }
    }

    function fmtBytes(b) {
        if (!b) return '0 B';
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(b) / Math.log(1024));
        return (b / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
    }

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

})(jQuery);
