/* TaxoCommerce Insights: sequential Analysis reports, including mobile browsers. */
jQuery(function ($) {
    'use strict';

    var config = window.seoAnalistaLazyConfig || {};
    var buttons = $('.seo-analista-lazy-button');
    var fullButton = $('#tgb-analysis-run-all');
    var stopButton = $('#tgb-analysis-cancel');
    var status = $('#seo-analista-lazy-status');
    var result = $('#seo-analista-lazy-result');
    var running = false;
    var cancelled = false;
    var pending = null;
    function showSavedReport(item, data) {
        var card = $('.seo-analista-report-card').filter(function () {
            return $(this).attr('data-report') === item.report;
        });
        if (!card.length || !data) {
            return;
        }
        var isPlan = item.report === 'directrices';
        var date = isPlan ? data.plan_saved_at_display : data.report_saved_at_display;
        var url = isPlan ? config.planDownloadUrl : data.report_download_url;
        if (!date || !url) {
            return;
        }
        card.find('.tgb-report-date').text(date);
        card.find('.tgb-report-json-button').removeClass('disabled')
            .attr('href', url).attr('aria-disabled', 'false');
    }

    function showSavedPlanDate(data) {
        showSavedReport({ report: 'directrices' }, data);
    }

    function setBusy(busy) {
        running = busy;
        buttons.prop('disabled', busy);
        fullButton.prop('disabled', busy);
        stopButton.prop('disabled', !busy);
    }

    function showStatus(message, failed) {
        status.empty().append(
            $('<div>').toggleClass('notice notice-error inline', !!failed).append(
                $('<p>').text(message)
            )
        );
    }

    function run(queue, complete) {
        if (running || !queue.length) {
            return;
        }
        if (!config.ajaxUrl || !config.nonce) {
            showStatus('No se ha podido inicializar Analista. Recarga la página e inténtalo de nuevo.', true);
            return;
        }

        cancelled = false;
        setBusy(true);
        result.empty();
        var finished = 0;
        var total = queue.length;

        function finish(message, error) {
            pending = null;
            setBusy(false);
            showStatus(message, error);
        }

        function next() {
            if (cancelled) {
                finish('Proceso detenido. Se conservan los ' + finished + ' bloques completados; el informe NO está completo.', true);
                return;
            }
            if (finished === total) {
                finish((complete ? 'Informe completo generado: ' : 'Informe generado: ') + finished + ' de ' + total + ' bloques.', false);
                return;
            }

            var item = queue[finished];
            showStatus('Generando ' + (finished + 1) + ' de ' + total + ': ' + item.label + '…', false);
            pending = $.ajax({
                url: config.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                timeout: 240000,
                data: {
                    action: 'taxocommerce_insights_analysis',
                    nonce: config.nonce,
                    report: item.report,
                    days: config.days,
                    period_mode: String($('#tgb-indicators-period').val() || 'week')
                }
            }).done(function (response) {
                if (cancelled) {
                    return;
                }
                if (!response || !response.success || !response.data || typeof response.data.html !== 'string') {
                    var explanation = response && response.data && response.data.message ?
                        response.data.message : 'Respuesta del servidor no válida.';
                    finish('Error en ' + item.label + ': ' + explanation + ' (' + finished + '/' + total + ' completados).', true);
                    return;
                }
                var reportContainer = $('<div>').addClass('tgb-analysis-report')
                    .attr('data-report', item.report).html(response.data.html);
                result.append(reportContainer);
                if (item.report === 'directrices') {
                    showSavedPlanDate(response.data);
                } else {
                    showSavedReport(item, response.data);
                }
                finished++;
                next();
            }).fail(function (xhr, reason) {
                if (cancelled || reason === 'abort') {
                    finish('Proceso detenido. Se conservan ' + finished + ' de ' + total + ' bloques; el informe NO está completo.', true);
                    return;
                }
                var message = reason === 'timeout' ? 'Tiempo de espera agotado.' :
                    (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ||
                    'Error de conexión o servidor.';
                finish('Error en ' + item.label + ': ' + message + ' (' + finished + '/' + total + ' completados).', true);
            });
        }
        next();
    }

    buttons.on('click', function () {
        var button = $(this);
        run([{
            report: String(button.attr('data-report') || ''),
            label: button.closest('.seo-analista-report-card').find('h3').first().text()
        }], false);
    });

    // The competitor report is a separate POST form injected by the lazy
    // renderer. Delegation also works when its card is opened through AJAX.
    // One bounded request per source, then the existing nonce-protected POST.
    $(document).on('submit', '#taxocommerce-insights-competition-generate', function (event) {
        var form = $(this);
        if (form.data('refreshComplete')) {
            return;
        }
        var nonce = form.find('[name="taxocommerce_competition_refresh_nonce"]').val();
        if (!config.ajaxUrl || !nonce) {
            return; // Non-JS / unavailable AJAX fallback: existing POST.
        }
        var steps = [];
        if (form.attr('data-refresh-merchant') === '1') {
            steps.push('merchant');
        }
        if (form.attr('data-refresh-trends') === '1') {
            steps.push('trends');
        }
        if (form.attr('data-refresh-visibility') === '1') {
            steps.push('visibility:0');
        }
        if (!steps.length) {
            return;
        }
        event.preventDefault();
        if (form.data('refreshRunning')) {
            return;
        }
        form.data('refreshRunning', true);
        var button = form.find('[type="submit"]');
        var message = form.find('#taxocommerce-insights-competition-refresh-status');
        button.prop('disabled', true);
        var position = 0;
        function continueToReport() {
            form.data('refreshComplete', true);
            button.prop('disabled', false);
            // Native submit bypasses the jQuery handler, retaining the
            // existing WordPress admin-post nonce and redirect behaviour.
            form.get(0).submit();
        }
        function nextSource() {
            if (position >= steps.length) {
                message.text('Fuentes consultadas. Preparando informe…');
                continueToReport();
                return;
            }
            var stage = steps[position++];
            var parts = stage.split(':');
            var source = parts[0];
            var step = parts.length > 1 ? Number(parts[1]) || 0 : 0;
            var label = source === 'merchant' ? 'Merchant Best Sellers' :
                (source === 'visibility' ? 'Merchant Visibilidad' : 'Google Trends');
            message.text('Consultando ' + label + ' (' + position + '/' + steps.length + ')…');
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                timeout: 100000,
                data: {
                    action: 'taxocommerce_insights_competitive_source_refresh',
                    nonce: nonce,
                    source: source,
                    step: step,
                    days: config.days
                }
            }).done(function (response) {
                var result = response && response.data ? response.data : {};
                if (response && response.success) {
                    message.text(label + ': ' + (result.status || 'sin estado') +
                        (result.message ? ' · ' + result.message : ''));
                    if (source === 'visibility' && result.next_step !== null &&
                        typeof result.next_step !== 'undefined' && result.next_step < 3) {
                        steps.push('visibility:' + result.next_step);
                    }
                } else {
                    message.text(source + ': no se pudo completar la consulta. El informe conservará los datos disponibles.');
                }
                nextSource();
            }).fail(function () {
                message.text(source + ': error de comunicación. Se conservarán los datos disponibles.');
                nextSource();
            });
        }
        nextSource();
    });

    // Complete JSON: one authenticated request per section. No browser Blob,
    // no WP-Cron and no server-wide CPU percentage estimates.
    var exportStart = fullButton;
    var exportStop = stopButton;
    var exportDownload = $('#tgb-chunked-json-download');
    var exportJob = '';
    var exportActive = false;
    var exportTimer = null;
    var exportRequest = null;
    var exportFailures = 0;
    var exportPhase = '';
    var sourceWarning = '';


    function exportBusy(active) {
        exportActive = active;
        exportStart.prop('disabled', active);
        buttons.prop('disabled', active);
        exportStop.prop('disabled', !active);
    }

    function exportMessage(message, failed) {
        showStatus(message, failed);
    }

    function exportReady(url) {
        exportDownload.removeClass('disabled').attr('aria-disabled', 'false').attr('href', url);
    }

    function exportClear() {
        exportDownload.addClass('disabled').attr('aria-disabled', 'true').removeAttr('href');
    }

    function exportAjax(operation, extra) {
        return $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            timeout: 90000,
            data: $.extend({
                action: 'taxocommerce_insights_chunked_export',
                nonce: config.chunkNonce,
                op: operation,
                job: exportJob
            }, extra || {})
        });
    }

    function exportNext() {
        if (!exportActive) {
            return;
        }
        exportRequest = exportAjax('step');
        exportRequest.done(function (response) {
            if (!exportActive) {
                return;
            }
            if (!response || !response.success || !response.data) {
                exportFailure('El servidor ha rechazado el bloque.');
                return;
            }
            var state = response.data;
            exportFailures = 0;
            exportMessage('Informe completo: ' + state.done + ' de ' + state.total + ' bloques guardados.', false);
            if (state.complete && state.download_url) {
                exportBusy(false);
                exportReady(state.download_url);
                exportMessage('Informe completo generado en ' + state.done + ' bloques. Ya puedes descargar el JSON.' +
                    (state.gsc_warning ? ' Aviso: ' + state.gsc_warning : ''), Boolean(state.gsc_warning));
                return;
            }
            exportTimer = setTimeout(exportNext, Math.max(300, Math.min(6000, Number(state.delay_ms) || 1000)));
        }).fail(function (xhr, reason) {
            if (!exportActive || reason === 'abort') {
                return;
            }
            var msg = reason === 'timeout' ? 'Tiempo agotado en este bloque.' :
                (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) ||
                'Error de conexión.';
            if (exportFailures < 2) {
                exportFailures++;
                exportMessage(msg + ' Reintentando el bloque (' + exportFailures + '/2)…', true);
                exportTimer = setTimeout(exportNext, 2500);
                return;
            }
            exportFailure(msg + ' El archivo no se puede descargar mientras esté incompleto.');
        });
    }

    function exportFailure(message) {
        exportBusy(false);
        exportMessage(message, true);
    }

    function exportError(xhr, fallback) {
        return (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) || fallback;
    }

    function gscRequest(action, extra) {
        return $.ajax({
            url: config.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            timeout: 120000,
            data: $.extend({
                action: action,
                nonce: config.gscSyncNonce
            }, extra || {})
        });
    }

    function exportBeginBlocks() {
        if (!exportActive) return;
        exportPhase = 'report';
        exportMessage('Generando informe completo con los datos comprobados…', false);
        exportRequest = exportAjax('start', { days: config.days, speed: $('#tgb-chunked-json-speed').val() || 3 });
        exportRequest.done(function (response) {
            if (!exportActive) return;
            if (!response || !response.success || !response.data || !response.data.id) {
                exportFailure((response && response.data && response.data.message) || 'No se pudo iniciar el informe.');
                return;
            }
            exportJob = response.data.id;
            exportNext();
        }).fail(function (xhr, reason) {
            if (exportActive && reason !== 'abort') exportFailure(exportError(xhr, 'No se pudo iniciar el informe.'));
        });
    }

    function exportSyncDays(run, failures) {
        if (!exportActive) return;
        exportPhase = 'sync';
        exportMessage('Actualizando Search Console: ' + (run.days_completed || 0) +
            ' de ' + (run.days_total || 0) + ' días sincronizados antes de generar el informe.', false);
        exportRequest = gscRequest('seo_google_sync_day', { run_id: run.run_id });
        exportRequest.done(function (response) {
            if (!exportActive) return;
            if (!response || !response.success || !response.data) {
                exportFailure((response && response.data && response.data.message) ||
                    'Search Console no ha podido sincronizarse. No se genera un informe aparentemente actualizado.');
                return;
            }
            if (response.data.done) {
                // The backend independently verifies freshness again before starting the report.
                exportBeginBlocks();
                return;
            }
            var speed = Math.max(1, Math.min(5, Number($('#tgb-chunked-json-speed').val()) || 3));
            var delays = { 1: 6000, 2: 3500, 3: 2000, 4: 1000, 5: 300 };
            exportTimer = setTimeout(function () {
                exportSyncDays({
                    run_id: run.run_id,
                    days_total: response.data.days_total,
                    days_completed: response.data.days_completed
                }, 0);
            }, delays[speed]);
        }).fail(function (xhr, reason) {
            if (!exportActive || reason === 'abort') return;
            if (reason === 'timeout' && failures < 2) {
                exportMessage('La sincronización ha tardado demasiado. Reintentando el día…', true);
                exportTimer = setTimeout(function () { exportSyncDays(run, failures + 1); }, 2500);
                return;
            }
            exportFailure('No se pudo actualizar Search Console: ' + exportError(xhr,
                reason === 'timeout' ? 'Tiempo de espera agotado.' : 'Error de conexión.') +
                ' El informe completo no se ha generado.');
        });
    }

    function exportPreflight() {
        exportMessage('Comprobando la fecha real de Search Console…', false);
        exportRequest = exportAjax('preflight');
        exportRequest.done(function (response) {
            if (!exportActive) return;
            if (!response || !response.success || !response.data) {
                exportFailure('No se ha podido verificar la actualidad de Search Console.');
                return;
            }
            var health = response.data;
            sourceWarning = health.warning || '';
            if (!health.needs_sync || !health.can_sync) {
                exportBeginBlocks();
                return;
            }
            if (!config.gscSyncNonce) {
                exportFailure('Falta la autorización para sincronizar Search Console.');
                return;
            }
            exportMessage('Search Console está desactualizado (SQL hasta ' +
                (health.sql_date || 'sin datos') + ', datos hasta ' + health.target_date +
                '). Iniciando sincronización por días…', false);
            exportPhase = 'sync';
            exportRequest = gscRequest('seo_google_sync_start');
            exportRequest.done(function (reply) {
                if (!exportActive) return;
                if (!reply || !reply.success || !reply.data || !reply.data.run_id) {
                    exportFailure((reply && reply.data && reply.data.message) ||
                        'No se pudo iniciar la sincronización de Search Console.');
                    return;
                }
                exportSyncDays(reply.data, 0);
            }).fail(function (xhr, reason) {
                if (exportActive && reason !== 'abort') exportFailure('No se pudo sincronizar Search Console: ' +
                    exportError(xhr, 'Error al iniciar la sincronización.'));
            });
        }).fail(function (xhr, reason) {
            if (exportActive && reason !== 'abort') exportFailure(exportError(xhr,
                'No se ha podido verificar la fecha de los datos.'));
        });
    }

    exportStart.on('click', function () {
        if (exportActive || !config.ajaxUrl || !config.chunkNonce) return;
        exportClear();
        exportJob = '';
        exportFailures = 0;
        sourceWarning = '';
        exportPhase = 'preflight';
        exportBusy(true);
        exportPreflight();
    });

    exportStop.on('click', function () {
        if (exportActive) {
            exportBusy(false);
            if (exportTimer !== null) clearTimeout(exportTimer);
            if (exportRequest) exportRequest.abort();
            if (exportJob) exportAjax('cancel');
            exportMessage(exportPhase === 'sync'
                ? 'Sincronización detenida en este navegador. Al volver a ejecutar el informe podrá reanudar los días pendientes.'
                : 'Informe detenido. No se puede descargar un archivo incompleto.', false);
            return;
        }
        if (!running) return;
        cancelled = true;
        if (pending && typeof pending.abort === 'function') pending.abort();
    });

    // Restore the completed file without generating any new blocks.
    if (config.ajaxUrl && config.chunkNonce) {
        exportAjax('status').done(function (response) {
            if (!exportActive && response && response.success && response.data &&
                response.data.complete && response.data.download_url) {
                exportJob = response.data.id;
                exportReady(response.data.download_url);
            }
        });
    }
});
