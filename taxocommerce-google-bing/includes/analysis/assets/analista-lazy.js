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

    function items() {
        return buttons.map(function () {
            var button = $(this);
            return {
                report: String(button.attr('data-report') || ''),
                label: button.closest('.seo-analista-report-card').find('h3').first().text()
            };
        }).get().filter(function (item) { return item.report !== ''; });
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
                    days: config.days
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

    fullButton.on('click', function () {
        run(items(), true);
    });

    stopButton.on('click', function () {
        if (!running) {
            return;
        }
        cancelled = true;
        if (pending && typeof pending.abort === 'function') {
            pending.abort();
        }
    });
});
