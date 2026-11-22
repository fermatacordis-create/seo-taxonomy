/* global document, Intl */
(function () {
    'use strict';

    function init() {
        const base = document.getElementById('tca-amount_base');
        const tax = document.getElementById('tca-amount_tax');
        const deductible = document.getElementById('tca-deductible_pct');
        const totalOutput = document.getElementById('tca-purchase-total');
        const deductibleOutput = document.getElementById('tca-purchase-deductible');
        if (!base || !tax || !deductible || !totalOutput || !deductibleOutput) {
            return;
        }

        const currency = totalOutput.getAttribute('data-currency') || 'EUR';
        let formatter;
        try {
            formatter = new Intl.NumberFormat(document.documentElement.lang || 'es-ES', {
                style: 'currency',
                currency: currency
            });
        } catch (error) {
            formatter = new Intl.NumberFormat('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function parseAmount(input) {
            if (input.value.trim() === '' || !input.validity.valid) {
                return null;
            }
            const number = Number(input.value);
            return Number.isFinite(number) && number >= 0 ? number : null;
        }

        function format(amount) {
            return formatter.format(Math.round((amount + Number.EPSILON) * 100) / 100);
        }

        function update() {
            const taxableBase = parseAmount(base);
            const vat = parseAmount(tax);
            const percent = parseAmount(deductible);
            totalOutput.textContent = taxableBase !== null && vat !== null
                ? format(taxableBase + vat) : '—';
            deductibleOutput.textContent = vat !== null && percent !== null && percent <= 100
                ? format(vat * percent / 100) : '—';
        }

        [base, tax, deductible].forEach(function (input) {
            input.addEventListener('input', update);
            input.addEventListener('change', update);
        });
        update();
    }

    function initExpenseType() {
        document.querySelectorAll('select[name="expense_type"]').forEach(function (selector) {
            const form = selector.closest('form');
            if (!form) {
                return;
            }
            const rows = form.querySelectorAll('.tca-asset-fields');
            function sync() {
                const asset = selector.value === 'fixed_asset';
                rows.forEach(function (row) {
                    row.hidden = !asset;
                    row.style.display = asset ? '' : 'none';
                    row.querySelectorAll('input').forEach(function (input) {
                        input.required = asset;
                    });
                });
            }
            selector.addEventListener('change', sync);
            sync();
        });
    }

    function ready() {
        init();
        initExpenseType();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ready);
    } else {
        ready();
    }
}());
