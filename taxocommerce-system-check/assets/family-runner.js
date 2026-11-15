(function () {
    'use strict';
    var root = document.getElementById('tcs-family-runner');
    var config = window.tcsFamilyRunner;
    if (!root || !config) { return; }
    var output = document.getElementById('tcs-family-progress');
    var speed = document.getElementById('tcs-family-speed');
    var running = false;
    var pending = null;
    var token = 0;
    function describe(s) {
        var f = s.functional || {};
        var c = s.code || {};
        var totals = f.totals || {};
        output.textContent = 'Estado: ' + (s.status || 'pendiente') +
            ' | Funcionalidades: ' + (f.done || 0) + '/' + (f.total || 0) +
            ' (correctas ' + (totals.pass || 0) + ', fallidas ' + (totals.fail || 0) + ', omitidas ' + (totals.skip || 0) + ')' +
            ' | Código: ' + (c.done || 0) + '/' + (c.total || 0) +
            ' (incidencias ' + (c.findings || 0) + ')' +
            (s.error ? ' | Error: ' + s.error : '');
        var cells = document.querySelectorAll('[data-tcs-kpi]');
        cells.forEach(function (cell) {
            var section = cell.getAttribute('data-tcs-kpi');
            var data = section === 'functional' ? f : c;
            cell.textContent = (data.status || 'pendiente') + ' · ' +
                (data.done || 0) + '/' + (data.total || 0) +
                (section === 'code' ? ' · ' + (data.findings || 0) + ' incidencias' : '');
        });
    }
    function request(op) {
        var body = new URLSearchParams();
        body.set('action', 'tcs_family_validation');
        body.set('nonce', config.nonce);
        body.set('operation', op);
        body.set('speed', speed.value);
        return fetch(config.url, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) { throw new Error((data.data && data.data.message) || 'No se pudo ejecutar el chequeo.'); }
                describe(data.data);
                return data.data;
            });
    }
    function next(state, myToken) {
        if (!running || myToken !== token || state.status !== 'running') { running = false; return; }
        var baseDelay = Number(state.delay_ms) || 2500;
        var elapsed = Number((state.load || {}).elapsed_ms) || 0;
        var delay = Math.max(baseDelay, elapsed > 1500 ? baseDelay * 2 : 0);
        pending = window.setTimeout(function () {
            if (!running || myToken !== token) { return; }
            request('step').then(function (updated) { next(updated, myToken); }).catch(function (error) {
                running = false;
                output.textContent = error.message + ' Puedes reanudar cuando esté disponible.';
            });
        }, delay);
    }
    root.addEventListener('click', function (event) {
        var button = event.target.closest('[data-tcs-op]');
        if (!button) { return; }
        var op = button.getAttribute('data-tcs-op');
        running = false;
        ++token;
        if (pending) { window.clearTimeout(pending); pending = null; }
        var mine = token;
        request(op).then(function (state) {
            if (op === 'start' || op === 'resume') {
                running = state.status === 'running';
                next(state, mine);
            }
        }).catch(function (error) { output.textContent = error.message; });
    });
    describe(config.state);
}());