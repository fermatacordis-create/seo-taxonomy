/* global taxocommerceAuditorWatch */
(function () {
    'use strict';

    var config = window.taxocommerceAuditorWatch;
    var monitor = document.querySelector('[data-tca-live]');
    if (!monitor) {
        return;
    }
    var progress = monitor.querySelector('[data-tca-progress]');
    var warning = monitor.querySelector('[data-tca-warning]');
    if (!progress || !warning) {
        return;
    }
    if (!config || !window.fetch || !window.URLSearchParams) {
        warning.textContent = config && config.failure ? config.failure : progress.textContent;
        warning.hidden = false;
        return;
    }
    var scope = monitor.getAttribute('data-scope');
    var failures = 0;
    var idleRuns = 0;
    var stopped = false;
    var busy = false;
    var timer = null;

    function readableTime(timestamp) {
        return Number(timestamp) > 0
            ? new Date(Number(timestamp) * 1000).toLocaleTimeString()
            : config.none;
    }

    function schedule(milliseconds) {
        if (stopped) {
            return;
        }
        if (timer !== null) {
            window.clearTimeout(timer);
        }
        timer = window.setTimeout(function () {
            timer = null;
            poll();
        }, milliseconds);
    }

    function adaptiveDelay(data, totalRequestMs) {
        var baseline = Number(data.client_delay_ms);
        if (!Number.isFinite(baseline)) {
            baseline = 2000;
        }
        baseline = Math.max(200, Math.min(10000, baseline));

        var budgetMs = Number(data.batch_budget_ms);
        var workMs = Number(data.batch_duration_ms);
        var cpuMs = data.batch_cpu_ms === null || data.batch_cpu_ms === undefined
            ? NaN : Number(data.batch_cpu_ms);
        var memoryPercent = Number(data.memory_pressure_percent);
        var observed = Math.max(
            Number.isFinite(workMs) ? workMs : 0,
            Number.isFinite(totalRequestMs) ? totalRequestMs : 0
        );

        if (!Number.isFinite(budgetMs) || budgetMs <= 0 || observed <= 0) {
            return baseline;
        }

        // Execution time + PHP memory are observable resource proxies, not
        // global server CPU percentage. The selected speed is the upper cap.
        var ratio = Math.max(observed / budgetMs,
            Number.isFinite(cpuMs) ? (cpuMs / budgetMs) * 1.15 : 0);
        if ((Number.isFinite(memoryPercent) && memoryPercent >= 80) || ratio >= 0.8) {
            return Math.min(30000, Math.ceil(baseline * 2.5));
        }
        if ((Number.isFinite(memoryPercent) && memoryPercent >= 70) || ratio >= 0.45) {
            return Math.min(30000, Math.ceil(baseline * 1.5));
        }
        if ((!Number.isFinite(memoryPercent) || memoryPercent < 65) && ratio <= 0.25) {
            return Math.max(200, Math.ceil(baseline * 0.65));
        }
        return baseline;
    }

    function poll() {
        if (stopped || busy) {
            return;
        }

        busy = true;
        var requestedAt = Date.now();
        var nextDelay = 2000;
        var body = new window.URLSearchParams();
        body.set('action', 'taxocommerce_auditor_watch');
        body.set('nonce', config.nonce);
        body.set('scope', scope);

        // A single request executes at most one batch. The following request
        // is only scheduled AFTER that response, independently of WP-Cron.
        var controller = window.AbortController ? new window.AbortController() : null;
        var watchdog = controller ? window.setTimeout(function () {
            controller.abort();
        }, 60000) : null;

        window.fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString(),
            signal: controller ? controller.signal : undefined
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        }).then(function (result) {
            if (!result || result.success !== true || !result.data) {
                throw new Error('Invalid progress response');
            }
            var data = result.data;
            failures = 0;
            if (typeof data.nonce === 'string' && data.nonce !== '') {
                config.nonce = data.nonce;
            }

            var statusLabel = document.querySelector('[data-tca-status-label]');
            var counter = document.querySelector('[data-tca-counter]');
            if (statusLabel) {
                statusLabel.textContent = data.status_label;
            }
            if (counter) {
                counter.textContent = data.processed + ' / ' +
                    (data.total === null ? '—' : data.total);
            }
            progress.textContent = data.status_label + ' · ' + data.processed +
                ' / ' + (data.total === null ? '—' : data.total) +
                ' · ' + config.latest + ': ' + readableTime(data.last_batch_timestamp) +
                ' · ' + config.continuous;
            if (Number(data.batch_duration_ms) > 0 && Number(data.effective_batch_size) > 0) {
                progress.textContent += ' · ' + config.nextBatch + ': ' +
                    Number(data.effective_batch_size) + ' · ' + config.duration + ': ' +
                    (Number(data.batch_duration_ms) / 1000).toFixed(1) + ' s';
                if (data.batch_cpu_ms !== null && data.batch_cpu_ms !== undefined) {
                    progress.textContent += ' · ' + config.cpu + ': ' + Number(data.batch_cpu_ms) + ' ms';
                }
            }

            warning.hidden = true;
            warning.textContent = '';
            // WP-Cron health does not govern the browser worker and must not
            // generate a warning during a healthy AJAX audit.
            if (data.reason) {
                warning.textContent = data.reason;
                warning.hidden = false;
            }

            if (data.status === 'completed') {
                stopped = true;
                window.location.reload();
                return;
            }
            if (data.status !== 'queued' && data.status !== 'running') {
                stopped = true;
                return;
            }

            // Actual server duration, observed request latency and PHP memory
            // pressure adapt the pause. A faster response gets more throughput
            // without exceeding the selected speed's server-side batch cap.
            nextDelay = adaptiveDelay(data, Date.now() - requestedAt);
            if (Number(data.batch_processed) <= 0) {
                idleRuns += 1;
                // Another worker may own the atomic lock. Never busy-loop.
                nextDelay = Math.max(nextDelay,
                    Math.min(15000, 1000 * Math.pow(2, Math.min(idleRuns, 4))));
            } else {
                idleRuns = 0;
            }
        }).catch(function (error) {
            failures += 1;
            // Keep the exact HTTP failure visible (403, 500, timeout etc.)
            // instead of silently leaving the user with a stalled counter.
            var reason = error && typeof error.message === 'string' ? error.message : '';
            warning.textContent = config.failure + (reason ? ' (' + reason + ')' : '');
            warning.hidden = false;
            // Transport errors back off; the next request can resume safely
            // from the persisted cursor without replaying completed objects.
            nextDelay = Math.min(60000, 5000 * Math.pow(2, Math.min(failures, 4)));
        }).finally(function () {
            if (watchdog !== null) {
                window.clearTimeout(watchdog);
            }
            busy = false;
            if (!stopped) {
                schedule(nextDelay);
            }
        });
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && !stopped && !busy) {
            schedule(300);
        }
    });

    // Start with the first batch immediately after the admin screen loads.
    schedule(300);
}());
