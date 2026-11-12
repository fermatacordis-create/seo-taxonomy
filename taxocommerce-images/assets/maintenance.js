/* global taxocommerceImagesMaintenance */
(function () {
    'use strict';
    var config = window.taxocommerceImagesMaintenance;
    if (!config || !window.fetch) { return; }
    document.querySelectorAll('[data-tci-priority]').forEach(function (slider) {
        var form = slider.closest('form');
        var output = form ? form.querySelector('[data-tci-priority-output]') : null;
        var labels = { 1: 'Suave', 2: 'Baja', 3: 'Normal', 4: 'Alta', 5: 'Máxima' };
        if (!output) { return; }
        slider.addEventListener('input', function () {
            output.innerHTML = '<strong>' + slider.value + ' · ' + labels[slider.value] + '</strong>';
        });
    });

    document.querySelectorAll('[data-tci-assignment]').forEach(function (section) {
        var busy = false;
        var paused = false;
        var output = section.querySelector('[data-tci-assignment-progress]');
        var buttons = section.querySelectorAll('[data-tci-assignment-operation]');
        var pause = section.querySelector('[data-tci-assignment-pause]');
        var scope = section.dataset.tciAssignment;
        if (!scope || !output) { return; }

        if (pause) {
            pause.addEventListener('click', function () {
                paused = true;
                if (typeof window.taxocommerceImagesBackgroundRequest === 'function') {
                    window.taxocommerceImagesBackgroundRequest('assignment_' + scope, 'pause').catch(function () {});
                }
            });
        }

        buttons.forEach(function (button) {
            button.addEventListener('click', async function () {
                if (busy) { return; }
                var operation = button.dataset.tciAssignmentOperation;
                if (operation !== 'start' && operation !== 'scan') { return; }

                busy = true;
                paused = false;
                buttons.forEach(function (item) { item.disabled = true; });

                try {
                    while (!paused) {
                        var form = new URLSearchParams();
                        form.set('action', 'taxocommerce_images_assign_auto_batch');
                        form.set('nonce', config.assignmentNonce || '');
                        form.set('scope', scope);
                        form.set('operation', operation);

                        var response = await fetch(config.url, {
                            method: 'POST',
                            credentials: 'same-origin',
                            body: form
                        });
                        var result = await response.json();
                        if (!response.ok || !result.success) {
                            throw new Error(result.data && result.data.message ? result.data.message : 'No se pudo ejecutar el lote de asignación.');
                        }

                        var state = result.data.state || {};
                        var load = result.data.load || {};
                        var total = Number(state.total || 0);
                        var seen = Number(state.seen || 0);
                        var assigned = Number(state.assigned || 0);
                        var remaining = Number(state.remaining || 0);
                        var percent = total > 0 ? Math.min(100, Math.round((seen * 1000) / total) / 10) : 100;
                        var next = Number(load.next_batch || load.batch || 0);
                        var elapsed = Number(load.elapsed || 0);

                        output.textContent = 'Pasada automática: ' + seen + ' / ' + total + ' revisados (' + percent + ' %) · '
                            + assigned + ' asignados · ' + remaining + ' pendientes · último lote '
                            + Number(load.items || 0) + ' · ' + elapsed + ' s · siguiente lote ' + next + '.';

                        if (result.data.done) {
                            output.textContent += ' Proceso terminado. Puedes actualizar la pantalla cuando hayan finalizado los demás procesos.';
                            return;
                        }

                        // The server worker has taken over after this first batch.
                        // Never make the browser responsible for the next batch.
                        output.textContent += ' Continúa en segundo plano; puedes cerrar la pestaña.';
                        return;
                    }

                    output.textContent += ' Pausado al finalizar el lote. Puedes continuar sin repetir lo ya revisado.';
                } catch (error) {
                    output.textContent = 'Proceso detenido: ' + error.message + ' El último lote completado está guardado.';
                } finally {
                    busy = false;
                    buttons.forEach(function (item) { item.disabled = false; });
                }
            });
        });
    });

    document.querySelectorAll('[data-tci-maintenance]').forEach(function (section) {
        var busy = false;
        var paused = false;
        var output = section.querySelector('[data-tci-progress]');
        var buttons = section.querySelectorAll('[data-tci-operation]');
        var pause = section.querySelector('[data-tci-pause]');
        if (pause) { pause.addEventListener('click', function () {
            paused = true;
            if (typeof window.taxocommerceImagesBackgroundRequest === 'function') {
                window.taxocommerceImagesBackgroundRequest(section.dataset.tciMaintenance, 'pause').catch(function () {});
            }
        }); }
        buttons.forEach(function (button) {
            button.addEventListener('click', async function () {
                if (busy) { return; }
                var operation = button.dataset.tciOperation;
                if (operation !== 'start' && operation !== 'scan') { return; }
                busy = true;
                paused = false;
                buttons.forEach(function (item) { item.disabled = true; });
                try {
                    while (!paused) {
                        var form = new URLSearchParams();
                        form.set('action', 'taxocommerce_images_maintenance');
                        form.set('nonce', config.nonce);
                        form.set('mode', section.dataset.tciMaintenance);
                        form.set('operation', operation);
                        var response = await fetch(config.url, { method: 'POST', credentials: 'same-origin', body: form });
                        var result = await response.json();
                        if (!response.ok || !result.success) {
                            throw new Error(result.data && result.data.message ? result.data.message : 'No se pudo ejecutar el lote.');
                        }
                        var state = result.data.state || {};
                        var load = result.data.load || {};
                        var counts = state.counts || {};
                        var cpu = load.cpu_percent === null || typeof load.cpu_percent === 'undefined' ? 'CPU no disponible' : load.cpu_percent + ' % CPU petición';
                        var next = load.next_batch || load.batch || 0;
                        var total = Number(state.total || 0);
                        var scanned = Number(state.scanned || 0);
                        var percent = total > 0 ? Math.min(100, Math.round((scanned * 1000) / total) / 10) : 0;
                        var summary = '';
                        if (section.dataset.tciMaintenance === 'cleanup') {
                            summary = ' · sin uso ' + Number(counts.unused || 0) + ' · duplicadas exactas ' + Number(counts.duplicate || 0);
                        } else if (section.dataset.tciMaintenance === 'labels') {
                            summary = ' · sin ALT ' + Number(counts.missing_alt || 0)
                                + ' · títulos a revisar ' + (Number(counts.missing_title || 0) + Number(counts.review_title || 0))
                                + ' · sin dimensiones ' + Number(counts.missing_dimensions || 0)
                                + ' · archivos ausentes ' + Number(counts.missing_file || 0);
                        }
                        output.textContent = 'Escaneo completo: ' + scanned + ' / ' + total + ' (' + percent + ' %)' + summary + '. Último lote interno: ' + (load.items || 0) + ' elementos · ' + cpu + ' · ' + (load.elapsed || 0) + ' s · siguiente lote ' + next + '.';
                        if (result.data.done) { window.location.reload(); return; }
                        // Cleanup continues via signed internal requests (no WP-Cron);
                        // the open tab also recovers progress if loopback is blocked.
                        output.textContent += section.dataset.tciMaintenance === 'cleanup'
                            ? ' Continúa sin WP-Cron. Mantén la pestaña abierta si el alojamiento bloquea peticiones internas.'
                            : ' Continúa en segundo plano; puedes cerrar la pestaña.';
                        return;
                    }
                    output.textContent += ' Pausado al finalizar el lote. Puedes continuar sin repetir lo analizado.';
                } catch (error) {
                    output.textContent = 'Proceso detenido: ' + error.message + ' El último lote completado está guardado.';
                } finally {
                    busy = false;
                    buttons.forEach(function (item) { item.disabled = false; });
                }
            });
        });
    });
}());
