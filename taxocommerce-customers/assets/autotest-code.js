(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('tc-autotest-code-step');
        if (!form) { return; }
        var delay = parseInt(form.getAttribute('data-delay'), 10);
        if (!Number.isFinite(delay)) { delay = 2000; }
        var timer = window.setTimeout(function () { form.submit(); }, Math.max(800, Math.min(5000, delay)));
        document.querySelectorAll('.tc-autotest-code form').forEach(function (item) {
            if (item !== form) { item.addEventListener('submit', function () { window.clearTimeout(timer); }); }
        });
    });
}());