/* global taxocommerceImagesBackground */
/**
 * Polls persisted server jobs and recovers read-only cleanup batches when
 * hosting blocks signed internal requests. Other modes remain status-only.
 */
(function () {
    'use strict';
    var cfg = window.taxocommerceImagesBackground || {};
    if (!cfg.ajax || !cfg.nonce || !window.fetch) { return; }
    var panels = document.querySelectorAll('[data-tci-bg-mode]');
    var names = {
        running: 'En ejecución en el servidor',
        paused: 'Pausado',
        complete: 'Completado',
        error: 'Error',
        stopped: 'Detenido',
        'not-started': 'Sin iniciar'
    };

    function request(mode, operation) {
        var body = new URLSearchParams();
        body.set('action', 'taxocommerce_images_background_control');
        body.set('nonce', cfg.nonce);
        body.set('mode', mode);
        body.set('operation', operation);
        return window.fetch(cfg.ajax, {
            method: 'POST',
            credentials: 'same-origin',
            body: body
        }).then(function (response) {
            return response.json().then(function (result) {
                if (!response.ok || !result || !result.success) {
                    throw new Error(result && result.data && result.data.message
                        ? result.data.message : 'No se pudo consultar el proceso.');
                }
                return result.data.job || {};
            });
        });
    }

    function modeMessage(mode) { return mode === 'cleanup' ? ' · sin actividad reciente: reintentando lote sin WP-Cron' : ' · sin actividad reciente: revisar WP-Cron'; }

    function render(panel, job) {
        var status = job.status || 'not-started';
        var label = panel.querySelector('[data-tci-bg-status]');
        var progress = panel.querySelector('[data-tci-bg-progress]');
        if (label) { label.textContent = names[status] || status; }
        var processed = Number(job.processed || 0);
        var total = Number(job.total || 0);
        var percent = total > 0 ? (100 * processed / total).toFixed(1) + '%' : '—';
        var text = total > 0
            ? processed + ' / ' + total + ' (' + percent + ')'
            : 'Sin recuento disponible todavía';
        if (job.changed) { text += ' · modificadas/asignadas ' + job.changed; }
        if (job.last_tick) {
            text += ' · última actividad ' + new Date(1000 * job.last_tick).toLocaleString('es-ES');
        }
        if (job.stalled) { text += modeMessage(panel.getAttribute('data-tci-bg-mode')); }
        if (job.cron_disabled) { text += ' · WP-Cron interno desactivado: necesita cron del hosting'; }
        if (job.message) { text += ' · ' + job.message; }
        if (progress) { progress.textContent = text; }

        panel.querySelectorAll('[data-tci-bg-control]').forEach(function (button) {
            var op = button.getAttribute('data-tci-bg-control');
            button.disabled = op === 'pause' ? status !== 'running'
                : op === 'resume' ? status !== 'paused' && status !== 'error' && !(status === 'not-started' && !['optimize', 'webp'].includes(panel.getAttribute('data-tci-bg-mode')))
                : status !== 'running' && status !== 'paused';
        });
        if (panel.getAttribute('data-tci-bg-mode') === 'audit') {
            var bar = document.getElementById('tci-audit-progress-bar');
            var message = document.getElementById('tc-images-audit-progress');
            if (bar && total > 0) { bar.max = total; bar.value = Math.min(processed, total); }
            if (message) { message.textContent = text; }
        }
    }

    panels.forEach(function (panel) {
        var mode = panel.getAttribute('data-tci-bg-mode');
        var updating = false;
        function refresh() {
            if (updating) { return; }
            updating = true;
            request(mode, 'status').then(function (job) {
                render(panel, job);
                // The signed loopback worker is primary; an open admin tab
                // can recover the same bounded job if the hosting blocks it.
                if (mode === 'cleanup' && job.status === 'running'
                    && (!job.last_tick || Date.now() / 1000 - Number(job.last_tick) > 25)) {
                    return request(mode, 'drive').then(function (updated) {
                        render(panel, updated);
                    });
                }
            }).catch(function (error) {
                var text = panel.querySelector('[data-tci-bg-progress]');
                if (text) { text.textContent = error.message; }
            }).then(function () { updating = false; });
        }
        panel.querySelectorAll('[data-tci-bg-control]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (updating) { return; }
                updating = true;
                var operation = button.getAttribute('data-tci-bg-control');
                request(mode, operation).then(function (job) {
                    render(panel, job);
                }).catch(function (error) {
                    var text = panel.querySelector('[data-tci-bg-progress]');
                    if (text) { text.textContent = error.message; }
                }).then(function () { updating = false; });
            });
        });
        refresh();
        window.setInterval(refresh, 6000);
    });

    window.taxocommerceImagesBackgroundRequest = request;
}());
