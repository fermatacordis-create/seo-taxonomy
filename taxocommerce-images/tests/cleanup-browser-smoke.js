'use strict';
/* Browser smoke: cleanup must continue even if server-side loopbacks are blocked. */
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const { URLSearchParams } = require('url');
const script = fs.readFileSync('taxocommerce-images/assets/background.js', 'utf8');
const status = { textContent: '' };
const progress = { textContent: '' };
const operations = [];
const panel = {
    getAttribute: function (name) { return name === 'data-tci-bg-mode' ? 'cleanup' : ''; },
    querySelector: function (name) {
        if (name === '[data-tci-bg-status]') { return status; }
        if (name === '[data-tci-bg-progress]') { return progress; }
        return null;
    },
    querySelectorAll: function () { return []; }
};
const document = {
    querySelectorAll: function (name) { return name === '[data-tci-bg-mode]' ? [panel] : []; },
    getElementById: function () { return null; }
};
const window = {
    taxocommerceImagesBackground: { ajax: '/wp-admin/admin-ajax.php', nonce: 'nonce' },
    fetch: function (url, options) {
        const body = new URLSearchParams(options.body);
        assert.strictEqual(body.get('action'), 'taxocommerce_images_background_control');
        assert.strictEqual(body.get('mode'), 'cleanup');
        assert.strictEqual(body.get('nonce'), 'nonce');
        assert.strictEqual(options.credentials, 'same-origin');
        const operation = body.get('operation');
        operations.push(operation);
        const job = operation === 'status'
            ? { status: 'running', processed: 3, total: 6, last_tick: 0 }
            : { status: 'complete', processed: 6, total: 6, last_tick: Date.now() / 1000 };
        return Promise.resolve({ ok: true, json: function () {
            return Promise.resolve({ success: true, data: { job: job } });
        } });
    },
    setInterval: function () { return 1; }
};
vm.runInNewContext(script, { document, window, URLSearchParams, Promise, Number, Date, Math });
(async function () {
    for (let i = 0; i < 5 && operations.length < 2; i++) {
        await new Promise(setImmediate);
    }
    assert.deepStrictEqual(operations, ['status', 'drive'], 'stale cleanup must drive next bounded batch');
    assert.strictEqual(status.textContent, 'Completado', 'finished server state displayed');
    assert(progress.textContent.includes('6 / 6'), 'progress refreshed');
    assert.strictEqual(typeof window.taxocommerceImagesBackgroundRequest, 'function', 'controls remain available');
    console.log('Images cleanup browser fallback: PASS');
})().catch(function (error) { console.error(error); process.exitCode = 1; });
