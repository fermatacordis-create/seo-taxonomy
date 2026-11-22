(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var button = document.getElementById('tctk-select-logo');
        var clear = document.getElementById('tctk-remove-logo');
        var field = document.getElementById('tctk_logo');
        var preview = document.getElementById('tctk-logo-preview');
        if (!button || !field || !window.wp || !window.wp.media) { return; }
        button.addEventListener('click', function (event) {
            event.preventDefault();
            var frame = window.wp.media({
                title: button.getAttribute('data-title'),
                button: { text: button.getAttribute('data-choose') },
                library: { type: 'image' },
                multiple: false
            });
            frame.on('select', function () {
                var image = frame.state().get('selection').first().toJSON();
                field.value = image.id;
                preview.src = image.url;
                preview.hidden = false;
            });
            frame.open();
        });
        if (clear) {
            clear.addEventListener('click', function (event) {
                event.preventDefault();
                field.value = '0';
                preview.hidden = true;
                preview.removeAttribute('src');
            });
        }
    });
}());
