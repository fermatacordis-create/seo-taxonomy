'use strict';
// Browser-level regression for the Run All button using the existing AJAX contract.
// Run: node taxocommerce-system-check/tests/module-audit-all-smoke.js
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../assets/module-audit.js'), 'utf8');

async function main() {
    const ids = ['system_check', 'core'];
    const states = new Map();
    const requests = [];
    const timerQueue = new Map();
    const storage = new Map();
    const nodes = {
        'tcs-module-audit': { addEventListener: (event, fn) => { nodes.panelHandler = fn; } },
        'tcs-module-progress': { textContent: '' },
        'tcs-module-bulk-progress': { textContent: '' },
        'tcs-module-report': { hidden: true },
        'tcs-module-speed': { value: '3' }
    };
    const allButton = { disabled: false };
    let click;
    let clock = 0;
    function summary(id, status) {
        return { plugin: id, name: id, status: status, delay_ms: 1,
            functional: { status: 'checked', done: 1, total: 1, totals: { pass: 1, fail: 0, warning: 0, skip: 0 } },
            code: { status: 'checked', done: 1, total: 1, errors: 0, warnings: 0 } };
    }
    const context = {
        window: {
            tcsModuleAudit: { url: '/wp-admin/admin-ajax.php', nonce: 'fixture', export: '/export', user: 2, site: 1, i18n: {} },
            sessionStorage: {
                getItem: (key) => storage.get(key) || null,
                setItem: (key, value) => storage.set(key, value),
                removeItem: (key) => storage.delete(key)
            },
            setTimeout: (fn) => { const id = ++clock; timerQueue.set(id, fn); return id; },
            clearTimeout: (id) => timerQueue.delete(id)
        },
        document: {
            getElementById: (id) => nodes[id] || null,
            querySelectorAll: (selector) => selector === '[data-tcs-module-start]' ?
                ids.map((id) => ({ getAttribute: () => id })) : [],
            querySelector: (selector) => selector === '[data-tcs-module-all]' ? allButton :
                selector.startsWith('[data-tcs-module-functional=') || selector.startsWith('[data-tcs-module-code=') ||
                selector.startsWith('[data-tcs-module-toggle=') ? { textContent: '', disabled: false } : null,
            addEventListener: (event, handler) => { if (event === 'click') { click = handler; } }
        },
        URLSearchParams: URLSearchParams,
        console: console,
        fetch: (url, options) => {
            const body = options.body;
            const id = body.get('module');
            const op = body.get('operation');
            requests.push(id + ':' + op);
            let status = states.get(id) || 'not_run';
            if (op === 'start' || op === 'resume') { status = 'running'; }
            if (op === 'step') { status = 'completed'; }
            if (op === 'stop') { status = 'paused'; }
            states.set(id, status);
            return Promise.resolve({ ok: true, status: 200,
                text: () => Promise.resolve(JSON.stringify({ success: true, data: summary(id, status) })) });
        }
    };
    function fire(selector, operation) {
        const element = { closest: (key) => key === selector ? (operation ?
            { getAttribute: () => operation } : {}) : null };
        if (selector === '[data-tcs-module-op]') {
            nodes.panelHandler({ target: element });
        } else {
            click({ target: element });
        }
    }
    async function flush() {
        for (let i = 0; i < 8; i++) { await new Promise((resolve) => setImmediate(resolve)); }
    }
    async function drain() {
        for (let i = 0; i < 12 && timerQueue.size; i++) {
            const [key, fn] = timerQueue.entries().next().value;
            timerQueue.delete(key);
            fn();
            await flush();
        }
    }

    vm.runInNewContext(source, context, { filename: 'module-audit.js' });
    assert.equal(typeof click, 'function', 'Main click handler absent');
    fire('[data-tcs-module-all]');
    await flush();
    assert.equal(allButton.disabled, true, 'Bulk button not locked');
    assert.deepEqual(requests, ['system_check:start'], 'First module must start alone');
    await drain();
    assert.deepEqual(requests, ['system_check:start', 'system_check:step', 'core:start', 'core:step'],
        'Modules must run sequentially, without duplicate starts');
    assert.equal(allButton.disabled, false, 'Bulk button must unlock when complete');
    assert.match(nodes['tcs-module-bulk-progress'].textContent, /2\/2/, 'Missing aggregate completion');
    assert.equal(storage.size, 0, 'Completed queue must not persist');

    requests.length = 0;
    states.clear();
    fire('[data-tcs-module-all]');
    await flush();
    fire('[data-tcs-module-op]', 'stop');
    await flush();
    assert.equal(states.get('system_check'), 'paused', 'Stop must pause current plugin');
    assert.equal(requests.some((x) => x.endsWith(':step')), false, 'Stop must prevent further steps');
    assert.equal(storage.size, 1, 'Paused queue must be recoverable');

    // Reload: saved queue is restored paused and must be resumed explicitly.
    vm.runInNewContext(source, context, { filename: 'module-audit.js' });
    assert.match(nodes['tcs-module-bulk-progress'].textContent, /0\/2/, 'Reload lost queue');
    fire('[data-tcs-module-op]', 'resume');
    await flush();
    await drain();
    assert.equal(allButton.disabled, false, 'Recovered queue did not finish');
    assert.equal(storage.size, 0, 'Recovered completion must clear session queue');
    console.log('module-audit-all-smoke: PASS');
}
main().catch((error) => { console.error(error); process.exitCode = 1; });
