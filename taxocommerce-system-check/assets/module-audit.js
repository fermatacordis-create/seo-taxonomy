(function () {
    'use strict';
    var panel = document.getElementById('tcs-module-audit');
    var config = window.tcsModuleAudit;
    if (!panel || !config) { return; }
    var progress = document.getElementById('tcs-module-progress');
    var result = document.getElementById('tcs-module-report');
    var speed = document.getElementById('tcs-module-speed');
    var i18n = config.i18n || {};
    function t(key, fallback) { return i18n[key] || fallback; }
    function statusLabel(value) {
        var key = String(value || 'not_run');
        if (key === 'not_run') { return t('not_run_status', 'Not run'); }
        if (key === 'paused') { return t('paused_status', 'Paused'); }
        if (key === 'error') { return t('error_status', 'Error'); }
        return Object.prototype.hasOwnProperty.call(i18n, key) ? i18n[key] : key;
    }
    var current = '';
    var token = 0;
    var timer = null;
    // A batch is deliberately browser-driven: one plugin at a time, using
    // precisely the same authenticated AJAX endpoint as the row controls.
    var bulk = null;
    var bulkStorageKey = 'tcs-module-bulk:' + String(config.site || 0) + ':' + String(config.user || 0);
    function persistBulk() {
        try {
            if (bulk) { window.sessionStorage.setItem(bulkStorageKey, JSON.stringify(bulk)); }
            else { window.sessionStorage.removeItem(bulkStorageKey); }
        } catch (error) { /* Storage may be blocked; the current tab still works. */ }
    }
    function restoreBulk() {
        var saved;
        try { saved = JSON.parse(window.sessionStorage.getItem(bulkStorageKey) || 'null'); }
        catch (error) { return; }
        if (!saved || !Array.isArray(saved.ids) || !saved.ids.length) { return; }
        var active = Array.prototype.map.call(document.querySelectorAll('[data-tcs-module-start]'), function (button) {
            return button.getAttribute('data-tcs-module-start');
        });
        if (saved.ids.length !== active.length || saved.ids.some(function (id, i) { return id !== active[i]; }) ||
            !Number.isInteger(saved.index) || saved.index < 0 || saved.index >= saved.ids.length) {
            try { window.sessionStorage.removeItem(bulkStorageKey); } catch (error) { /* Storage unavailable. */ }
            return;
        }
        bulk = { ids: saved.ids, index: saved.index, paused: true,
            findings: Number(saved.findings) || 0, errors: Number(saved.errors) || 0 };
        current = bulk.ids[bulk.index];
        persistBulk();
        bulkMessage();
        progress.textContent = t('resume_hint', 'The browser is not processing batches; press Resume to continue.');
    }
    function bulkMessage() {
        var marker = document.getElementById('tcs-module-bulk-progress');
        if (!marker || !bulk) { return; }
        var done = bulk.index;
        var count = bulk.ids.length;
        marker.textContent = t('all_progress', 'Plugins checked') + ': ' + done + '/' + count +
            ' · ' + t('all_failures', 'Checks with findings') + ': ' + bulk.findings +
            ' · ' + t('all_errors', 'Execution errors') + ': ' + bulk.errors +
            (bulk.paused ? ' · ' + t('all_paused', 'Paused') : '');
        var allButton = document.querySelector('[data-tcs-module-all]');
        if (allButton) { allButton.disabled = true; }
        persistBulk();
    }
    function finishBulk() {
        var marker = document.getElementById('tcs-module-bulk-progress');
        var summary = bulk;
        bulk = null;
        persistBulk();
        if (marker) {
            marker.textContent = t('all_complete', 'All plugin checks finished') + ': ' +
                summary.ids.length + '/' + summary.ids.length +
                ' · ' + t('all_failures', 'Checks with findings') + ': ' + summary.findings +
                ' · ' + t('all_errors', 'Execution errors') + ': ' + summary.errors;
        }
        var button = document.querySelector('[data-tcs-module-all]');
        if (button) { button.disabled = false; }
    }
    function bulkFailure(error, mine) {
        if (!bulk || mine !== token) { return; }
        bulk.paused = true;
        bulk.errors += 1;
        var id = bulk.ids[bulk.index];
        rowMessage(id, error.message);
        progress.textContent = label(id, '') + ': ' + error.message + t('can_resume', ' · You can resume this check.');
        bulkMessage();
    }
    function bulkStep(state, mine) {
        if (!bulk || mine !== token || bulk.paused) { return; }
        current = bulk.ids[bulk.index];
        show(state);
        if (state.status === 'completed') {
            var functional = (state.functional || {}).totals || {};
            var code = state.code || {};
            if ((functional.fail || 0) || (functional.warning || 0) || (code.errors || 0) || (code.warnings || 0)) {
                bulk.findings += 1;
            }
            bulk.index += 1;
            bulkMessage();
            bulkStart(mine);
            return;
        }
        if (state.status !== 'running') {
            bulk.paused = true;
            progress.textContent = label(current, '') + ': ' + t('all_paused', 'Paused') + ' (' + statusLabel(state.status) + ')';
            bulkMessage();
            return;
        }
        timer = window.setTimeout(function () {
            if (!bulk || mine !== token || bulk.paused) { return; }
            request(bulk.ids[bulk.index], 'step').then(function (updated) {
                bulkStep(updated, mine);
            }).catch(function (error) { bulkFailure(error, mine); });
        }, Number(state.delay_ms) || 2500);
    }
    function bulkStart(mine) {
        if (!bulk || mine !== token || bulk.paused) { return; }
        if (bulk.index >= bulk.ids.length) { finishBulk(); return; }
        current = bulk.ids[bulk.index];
        result.hidden = true;
        bulkMessage();
        rowMessage(current, t('preparing', 'Preparing check for ') + current);
        request(current, 'start').then(function (state) {
            bulkStep(state, mine);
        }).catch(function (error) { bulkFailure(error, mine); });
    }
    function bulkPause() {
        if (!bulk || bulk.paused) { return; }
        bulk.paused = true;
        clearTimer();
        bulkMessage();
        // This changes only the cooperative state, not an active PHP request.
        request(bulk.ids[bulk.index], 'stop').catch(function (error) {
            rowMessage(bulk.ids[bulk.index], error.message);
        });
    }
    function bulkResume() {
        if (!bulk || !bulk.paused) { return; }
        bulk.paused = false;
        clearTimer();
        var mine = token;
        bulkMessage();
        var id = bulk.ids[bulk.index];
        request(id, 'status').then(function (state) {
            if (!bulk || mine !== token || bulk.paused) { return null; }
            if (state.status === 'completed') { return state; }
            return request(id, state.status === 'not_run' ? 'start' : 'resume');
        }).then(function (state) {
            if (state) { bulkStep(state, mine); }
        }).catch(function (error) { bulkFailure(error, mine); });
    }
    function rowMessage(module, message) {
        var cell = document.querySelector('[data-tcs-module-functional="' + module + '"]');
        if (cell) { cell.textContent = message; }
    }
    function text(tag, content, parent) {
        var element = document.createElement(tag);
        element.textContent = String(content == null ? '' : content);
        parent.appendChild(element);
        return element;
    }
    function label(value, fallback) { return value == null || value === '' ? fallback : String(value); }
    function request(module, operation) {
        var body = new URLSearchParams();
        body.set('action', 'tcs_module_audit');
        body.set('nonce', config.nonce);
        body.set('module', module);
        body.set('operation', operation);
        body.set('speed', speed.value);
        return fetch(config.url, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (response) {
                return response.text().then(function (raw) {
                    var data;
                    try { data = JSON.parse(raw); }
                    catch (error) {
                        throw new Error(t('invalid_json', 'Invalid server response (PHP notice or HTML). Check the PHP error log.') + ' HTTP ' + response.status);
                    }
                    if (!response.ok && (!data || data.success !== false)) {
                        throw new Error(t('request_failed', 'Unable to execute the check.') + ' HTTP ' + response.status);
                    }
                    return data;
                });
            })
            .then(function (data) {
                if (!data.success) {
                    throw new Error((data.data && data.data.message) || t('request_failed', 'Unable to execute the check.'));
                }
                return data.data;
            });
    }
    function show(state) {
        if (!state) { return; }
        var f = state.functional || {};
        var c = state.code || {};
        var totals = f.totals || {};
        progress.textContent = label(state.name, state.plugin) + ': ' + statusLabel(state.status) +
            ' · ' + t('functions', 'Functions') + ': ' + (f.done || 0) + '/' + (f.total || 0) +
            ' (' + (totals.fail || 0) + ' ' + t('failures', 'failures') + ', ' + (totals.skip || 0) + ' ' + t('skipped', 'skipped') + ', ' + (totals.warning || 0) + ' ' + t('warnings', 'warnings') + ')' +
            ' · ' + t('code', 'Code') + ': ' + (c.done || 0) + '/' + (c.total || 0) +
            ' (' + (c.errors || 0) + ' ' + t('errors', 'errors') + ', ' + (c.warnings || 0) + ' ' + t('warnings', 'warnings') + ')' +
            (state.error ? ' · ' + state.error : '');
        var fcell = document.querySelector('[data-tcs-module-functional="' + state.plugin + '"]');
        var ccell = document.querySelector('[data-tcs-module-code="' + state.plugin + '"]');
        if (fcell) { fcell.textContent = f.status === 'basic_only' ?
            t('basic_only', 'Basic only: ') + (totals.fail || 0) + ' ' + t('failures', 'failures') :
            (f.done || 0) + '/' + (f.total || 0) + ': ' + (totals.fail || 0) + ' ' + t('failures', 'failures'); }
        if (ccell) { ccell.textContent = (c.done || 0) + '/' + (c.total || 0) + ': ' +
            (c.errors || 0) + ' ' + t('errors', 'errors') + ', ' + (c.warnings || 0) + ' ' + t('warnings', 'warnings'); }
        var toggle = document.querySelector('[data-tcs-module-toggle="' + state.plugin + '"]');
        if (toggle) {
            toggle.textContent = state.status === 'running' ? t('stop', 'Stop') : t('resume', 'Resume');
            toggle.disabled = state.status === 'completed' || state.status === 'not_run';
        }
    }
    function clearTimer() {
        ++token;
        if (timer) { window.clearTimeout(timer); timer = null; }
    }
    function renderRow(item, container, type) {
        var row = text('li', '', container);
        if (type === 'functional') {
            text('strong', statusLabel(item.status || 'skip') + ' · ' + label(item.group, '') +
                ' · ' + label(item.label, item.id), row);
            text('p', label(item.message, t('no_details', 'No details available.')), row);
        } else {
            text('strong', statusLabel(item.severity || item.status || type) +
                ' · ' + label(item.file, '') +
                (item.line == null ? '' : ':' + item.line) +
                ' · ' + label(item.rule, item.group || ''), row);
            text('p', label(item.description, t('review_required', 'Review required.')), row);
            if (item.recommendation) { text('p', t('recommendation', 'Recommendation: ') + item.recommendation, row); }
            else if (item.evidence && Object.keys(item.evidence).length) {
                var evidence = JSON.stringify(item.evidence);
                text('pre', evidence.length > 1600 ? evidence.slice(0, 1600) + '…' : evidence, row);
            }
        }
    }
    function section(title, items, type, open) {
        var details = document.createElement('details');
        details.open = !!open;
        result.appendChild(details);
        text('summary', title + ' (' + items.length + ')', details);
        if (!items.length) { text('p', t('none_recorded', 'No results in this category.'), details); return; }
        var list = document.createElement('ol');
        details.appendChild(list);
        items.forEach(function (item) { renderRow(item, list, type); });
    }
    function renderReport(report) {
        result.hidden = false;
        result.textContent = '';
        if (!report || !report.summary) {
            text('p', t('not_run', 'No report exists for this plugin yet.'), result);
            return;
        }
        var title = text('h3', t('results_of', 'Results for ') + label(report.name, report.plugin), result);
        title.setAttribute('tabindex', '-1');
        var summary = report.summary;
        text('p', t('status', 'Status: ') + statusLabel(summary.status || 'pending') +
            t('date', ' · Date: ') + label(report.executed_at, '—'), result);
        if (summary.note) { text('p', summary.note, result); }
        var link = text('a', t('download_json', 'Download complete JSON for this plugin'), result);
        link.href = config.export + '&module=' + encodeURIComponent(report.plugin);
        link.className = 'button button-secondary';
        var events = report.execution_log || [];
        section(t('execution_log', 'Execution log'), events.map(function (event) {
            return { status: label(event.action, 'step'), group: event.phase,
                label: event.at, message: event.message || event.phase + ': ' +
                    label(event.functional_done, '0') + ' / ' + label(event.code_done, '0') };
        }), 'functional', true);
        var functional = report.functional_tests || [];
        section(t('functional_failures', 'Functional failures'), functional.filter(function (x) { return x.status === 'fail'; }), 'functional', true);
        section(t('warnings', 'Warnings') + ' · ' + t('functions', 'Functions'), functional.filter(function (x) { return x.status === 'warning'; }), 'functional', false);
        section(t('skipped_checks', 'Skipped checks'), functional.filter(function (x) { return x.status === 'skip'; }), 'functional', false);
        section(t('passed_checks', 'Passed checks'), functional.filter(function (x) { return x.status === 'pass'; }), 'functional', false);
        var code = report.code_findings || {};
        section(t('code_errors', 'Code errors'), code.errors || [], 'code', true);
        section(t('code_warnings', 'Code warnings (manual review)'), code.warnings || [], 'code', true);
        section(t('unchecked', 'Unchecked files'), code.unchecked || [], 'code', false);
        text('p', t('disclaimer', 'Local code review is not the official WordPress Plugin Check.'), result);
        result.scrollIntoView({ behavior: 'smooth', block: 'start' });
        title.focus({ preventScroll: true });
    }
    function view(module) {
        current = module;
        request(module, 'report').then(renderReport).catch(function (error) {
            result.hidden = false;
            result.textContent = '';
            text('p', error.message, result);
        });
    }
    function next(state, mine) {
        if (mine !== token || state.status !== 'running') {
            if (state.status === 'completed' && mine === token) { view(current); }
            return;
        }
        timer = window.setTimeout(function () {
            if (mine !== token) { return; }
            request(current, 'step').then(function (updated) {
                show(updated);
                next(updated, mine);
            }).catch(function (error) {
                progress.textContent = error.message + t('can_resume', ' · You can resume this check.');
                rowMessage(current, error.message);
            });
        }, Number(state.delay_ms) || 2500);
    }
    document.addEventListener('click', function (event) {
        var all = event.target.closest('[data-tcs-module-all]');
        if (all) {
            if (bulk) { return; }
            var ids = Array.prototype.map.call(document.querySelectorAll('[data-tcs-module-start]'), function (button) {
                return button.getAttribute('data-tcs-module-start');
            }).filter(function (id) { return !!id; });
            if (!ids.length) { return; }
            clearTimer();
            bulk = { ids: ids, index: 0, paused: false, findings: 0, errors: 0 };
            bulkMessage();
            bulkStart(token);
            return;
        }
        var start = event.target.closest('[data-tcs-module-start]');
        var report = event.target.closest('[data-tcs-module-report]');
        var toggle = event.target.closest('[data-tcs-module-toggle]');
        if (toggle) {
            if (bulk) { return; }
            var module = toggle.getAttribute('data-tcs-module-toggle');
            current = module;
            clearTimer();
            request(module, 'status').then(function (existing) {
                if (existing.status === 'running') { return request(module, 'stop'); }
                if (existing.status === 'paused') { return request(module, 'resume'); }
                return existing;
            }).then(function (state) {
                show(state);
                if (state.status === 'running') { next(state, token); }
            }).catch(function (error) {
                progress.textContent = error.message;
                rowMessage(module, error.message);
            });
            return;
        }
        if (report) { view(report.getAttribute('data-tcs-module-report')); return; }
        if (!start || bulk) { return; }
        current = start.getAttribute('data-tcs-module-start');
        clearTimer();
        result.hidden = true;
        progress.textContent = t('preparing', 'Preparing check for ') + current + '…';
        rowMessage(current, t('preparing', 'Preparing check for ') + current + '…');
        var mine = token;
        request(current, 'start').then(function (state) {
            show(state);
            next(state, mine);
        }).catch(function (error) {
            progress.textContent = error.message;
            rowMessage(current, error.message);
        });
    });
    panel.addEventListener('click', function (event) {
        var control = event.target.closest('[data-tcs-module-op]');
        if (!control) { return; }
        var op = control.getAttribute('data-tcs-module-op');
        if (bulk) {
            if (op === 'stop') { bulkPause(); }
            if (op === 'resume') { bulkResume(); }
            return;
        }
        if (!current) { return; }
        clearTimer();
        var mine = token;
        request(current, op).then(function (state) {
            show(state);
            if (op === 'resume') { next(state, mine); }
        }).catch(function (error) { progress.textContent = error.message; });
    });
    restoreBulk();
    // A previously started run must remain stoppable after navigation or reload.
    if (!bulk && config.initial && config.initial.plugin) {
        current = config.initial.plugin;
        show(config.initial);
        if (config.initial.status === 'running') {
            progress.textContent += t('resume_hint', ' · Waiting for browser. Press Resume to continue.');
        }
    }
}());
