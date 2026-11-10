'use strict';

/**
 * Headless browser contract for the Auditor auto-worker.
 *
 * Only test fixtures; no network, WordPress database or production content.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(
    path.join(__dirname, '../assets/admin-watch.js'),
    'utf8'
);

async function scenario(responses, options = {}) {
    const timers = new Map();
    const scheduled = [];
    const sent = [];
    const elements = {
        progress: { textContent: '' },
        warning: { textContent: '', hidden: true },
        status: { textContent: '' },
        counter: { textContent: '' }
    };
    let id = 0;
    let reloads = 0;
    let pending = 0;
    let maxPending = 0;
    let fetchCount = 0;

    const monitor = {
        getAttribute: function (name) {
            assert.equal(name, 'data-scope');
            return 'products';
        },
        querySelector: function (selector) {
            return selector === '[data-tca-progress]' ? elements.progress :
                selector === '[data-tca-warning]' ? elements.warning : null;
        }
    };

    const config = {
        ajaxUrl: '/wp-admin/admin-ajax.php',
        nonce: 'first-nonce',
        latest: 'Último lote',
        none: 'Sin datos',
        continuous: 'Ritmo adaptativo',
        nextBatch: 'Próximo lote',
        duration: 'Duración',
        cpu: 'CPU PHP',
        failure: 'No se pudo procesar el siguiente lote'
    };

    const windowStub = {
        taxocommerceAuditorWatch: config,
        URLSearchParams,
        AbortController: class {
            constructor() { this.signal = { aborted: false }; }
            abort() { this.signal.aborted = true; }
        },
        location: {
            reload: function () { reloads += 1; }
        },
        setTimeout: function (callback, ms) {
            const timerId = ++id;
            scheduled.push(ms);
            timers.set(timerId, { callback, ms });
            return timerId;
        },
        clearTimeout: function (timerId) {
            timers.delete(timerId);
        },
        fetch: options.noFetch ? null : async function (url, request) {
            assert.equal(url, config.ajaxUrl);
            const form = new URLSearchParams(request.body);
            assert.equal(form.get('action'), 'taxocommerce_auditor_watch');
            assert.equal(form.get('scope'), 'products');
            assert.equal(request.credentials, 'same-origin');
            sent.push({ nonce: form.get('nonce'), body: request.body });
            pending += 1;
            maxPending = Math.max(pending, maxPending);
            const next = responses[fetchCount++];
            pending -= 1;
            if (next instanceof Error) {
                throw next;
            }
            assert.ok(next, 'No unexpected extra batch requests');
            return { ok: true, json: async () => ({ success: true, data: next }) };
        }
    };

    const documentStub = {
        querySelector: function (selector) {
            return selector === '[data-tca-live]' ? monitor :
                selector === '[data-tca-status-label]' ? elements.status :
                selector === '[data-tca-counter]' ? elements.counter : null;
        },
        addEventListener: function () {}
    };

    vm.runInNewContext(source, {
        window: windowStub,
        document: documentStub,
        Number,
        Date,
        Math,
        Promise,
        Error
    }, { filename: 'admin-watch.js' });

    async function tick() {
        const runnable = [...timers.entries()]
            .sort((a, b) => a[1].ms - b[1].ms)[0];
        assert.ok(runnable, 'Expected the next timer');
        timers.delete(runnable[0]);
        runnable[1].callback();
        // Allow fetch(), json(), then/catch/finally to settle.
        await new Promise((resolve) => setImmediate(resolve));
    }

    return {
        tick,
        timers,
        scheduled,
        sent,
        elements,
        config,
        get reloads() { return reloads; },
        get fetchCount() { return fetchCount; },
        get maxPending() { return maxPending; }
    };
}

async function run() {
    const active = await scenario([
        { status: 'queued', processed: 0, total: 120, status_label: 'En cola', client_delay_ms: 6000, batch_processed: 0, nonce: 'rotated-1' },
        { status: 'running', processed: 20, total: 120, status_label: 'En curso', client_delay_ms: 300, batch_processed: 20, nonce: 'rotated-2' },
        { status: 'completed', processed: 120, total: 120, status_label: 'Completado', batch_processed: 100, nonce: 'rotated-3' }
    ]);

    await active.tick();
    assert.equal(active.fetchCount, 1, 'The first batch starts automatically');
    await active.tick();
    assert.equal(active.fetchCount, 2, 'The next request follows the previous response');
    assert.equal(active.sent[1].nonce, 'rotated-1', 'Nonce rotates safely on long scans');
    await active.tick();
    assert.equal(active.fetchCount, 3);
    assert.equal(active.sent[2].nonce, 'rotated-2', 'Subsequent batches reuse refreshed nonce');
    assert.equal(active.reloads, 1, 'Completion refreshes the final report');
    assert.equal(active.timers.size, 0, 'No new batches once completed');
    assert.equal(active.maxPending, 1, 'Workers do not overlap');
    assert.equal(active.elements.counter.textContent, '120 / 120');

    const paused = await scenario([
        { status: 'paused', processed: 20, total: 120, status_label: 'Pausado', client_delay_ms: 6000, batch_processed: 0 }
    ]);
    await paused.tick();
    assert.equal(paused.fetchCount, 1);
    assert.equal(paused.timers.size, 0, 'Pause never schedules another batch');

    const failed = await scenario([
        new Error('HTTP 500'),
        { status: 'completed', processed: 30, total: 30, status_label: 'Completado', batch_processed: 30 }
    ]);
    await failed.tick();
    assert.equal(failed.fetchCount, 1);
    assert.match(failed.elements.warning.textContent, /HTTP 500/, 'Failure is visible');
    assert.ok(failed.scheduled.includes(10000), 'Network retry backs off');
    await failed.tick();
    assert.equal(failed.fetchCount, 2, 'The worker retries without a manual click');
    assert.equal(failed.reloads, 1);

    const healthyNoCron = await scenario([
        { status: 'running', processed: 15, total: 100, status_label: 'En curso',
            batch_processed: 15, client_delay_ms: 2000, batch_budget_ms: 5000,
            batch_duration_ms: 100, batch_cpu_ms: 75, effective_batch_size: 19,
            memory_pressure_percent: 25, cron_disabled: true, cron_overdue: true },
        { status: 'completed', processed: 100, total: 100, status_label: 'Completado', batch_processed: 85 }
    ]);
    await healthyNoCron.tick();
    assert.equal(healthyNoCron.elements.warning.hidden, true, 'Cron health is not a browser audit warning');
    assert.ok(healthyNoCron.scheduled.includes(1300), 'Fast PHP batch accelerates AJAX within its configured cap');
    assert.match(healthyNoCron.elements.progress.textContent, /Próximo lote: 19/, 'Adaptive batch size is visible');
    assert.match(healthyNoCron.elements.progress.textContent, /CPU PHP: 75 ms/, 'Measured PHP CPU time is visible');
    await healthyNoCron.tick();
    assert.equal(healthyNoCron.reloads, 1, 'Cron-disabled audits complete autonomously');

    const overloaded = await scenario([
        { status: 'running', processed: 8, total: 100, status_label: 'En curso',
            batch_processed: 8, client_delay_ms: 2000, batch_budget_ms: 5000,
            batch_duration_ms: 4200, effective_batch_size: 6, memory_pressure_percent: 85 },
        { status: 'paused', processed: 8, total: 100, status_label: 'Pausado', batch_processed: 0 }
    ]);
    await overloaded.tick();
    assert.ok(overloaded.scheduled.includes(5000), 'Slow/high-memory batch backs off to protect hosting');
    await overloaded.tick();
    assert.equal(overloaded.fetchCount, 2, 'Backoff still resumes the next batch automatically');
    assert.equal(overloaded.timers.size, 0, 'Pausing disables scheduling after backoff');

    const unsupported = await scenario([], { noFetch: true });
    assert.equal(unsupported.timers.size, 0, 'An unsupported browser cannot busy-loop');
    assert.equal(unsupported.elements.warning.hidden, false, 'Unsupported browser reports its failure');

    process.stdout.write('Auditor AJAX auto-chain smoke passed (success, adaptive load, cron-free UI, nonce, pause, retry, browser support).\n');
}

run().catch((error) => {
    process.stderr.write(String(error.stack || error) + '\n');
    process.exitCode = 1;
});
