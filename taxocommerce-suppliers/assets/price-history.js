(function () {
	'use strict';
	const root = document.getElementById('tcsup-history-bulk');
	if (!root) {
		return;
	}
	const exportButton = document.getElementById('tcsup-history-export');
	const importForm = document.getElementById('tcsup-history-import');
	const status = document.getElementById('tcsup-history-status');
	const progress = document.getElementById('tcsup-history-progress');
	const strings = window.tcsupHistoryStrings || {};
	const text = (name, fallback) => strings[name] || fallback;
	let busy = false;

	function update(message, percent) {
		status.textContent = message;
		if (typeof percent === 'number') {
			progress.hidden = false;
			progress.value = Math.max(0, Math.min(100, percent));
		}
	}

	function setBusy(next) {
		busy = next;
		exportButton.disabled = next;
		importForm.querySelectorAll('button,input,select').forEach(function (control) {
			control.disabled = next;
		});
	}

	async function request(action, fields) {
		const form = new FormData();
		form.append('action', action);
		form.append('_ajax_nonce', root.dataset.nonce);
		Object.keys(fields).forEach(function (key) {
			form.append(key, fields[key]);
		});
		const response = await fetch(root.dataset.ajax, {
			method: 'POST',
			credentials: 'same-origin',
			cache: 'no-store',
			body: form
		});
		let payload;
		try {
			payload = await response.json();
		} catch (error) {
			throw new Error(text('serverError', 'Server response was not valid JSON.'));
		}
		if (!response.ok || !payload.success) {
			throw new Error(payload.data && payload.data.message ? payload.data.message : text('serverError', 'Server request failed.'));
		}
		return payload.data;
	}

	function csvCell(value, index) {
		let cell = value == null ? '' : String(value);
		// Guard text columns against spreadsheet formula injection.
		if ([2, 3, 4, 5, 6, 7, 15].indexOf(index) !== -1 && /^[\s]*[=+\-@]/.test(cell)) {
			cell = "'" + cell;
		}
		return '"' + cell.replace(/"/g, '""') + '"';
	}

	function csvLine(row) {
		return row.map(csvCell).join(',') + '\r\n';
	}

	function downloadCsv(content) {
		const blob = new Blob(['\ufeff', content], { type: 'text/csv;charset=utf-8' });
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		const timestamp = new Date().toISOString().slice(0, 10).replace(/-/g, '');
		a.href = url;
		a.download = 'taxocommerce-price-history-' + timestamp + '.csv';
		a.style.display = 'none';
		document.body.appendChild(a);
		a.click();
		a.remove();
		setTimeout(function () { URL.revokeObjectURL(url); }, 2000);
	}

	function activeFilters() {
		const fields = {};
		['ph_supplier', 'ph_search', 'ph_from', 'ph_to'].forEach(function (key) {
			const input = root.querySelector('[name="' + key + '"]');
			fields[key] = input ? input.value : '';
		});
		return fields;
	}

	exportButton.addEventListener('click', async function () {
		if (busy) {
			return;
		}
		setBusy(true);
		update(text('exporting', 'Exporting price history…'), 0);
		let cursorFecha = '';
		let cursorId = 0;
		let totalRows = 0;
		let content = '';
		const filters = activeFilters();
		const batchSize = Number(importForm.querySelector('[name="batch_size"]').value) || 200;
		try {
			for (;;) {
				const result = await request('tcsup_history_export_batch', Object.assign({}, filters, {
					batch_size: batchSize,
					cursor_fecha: cursorFecha,
					cursor_id: cursorId
				}));
				if (!content) {
					content = csvLine(result.headers);
				}
				result.rows.forEach(function (row) {
					content += csvLine(row);
				});
				totalRows += result.rows.length;
				update(text('exporting', 'Exporting price history…') + ' ' + totalRows, 0);
				if (result.done) {
					break;
				}
				if (!result.cursor_fecha || !result.cursor_id) {
					throw new Error(text('serverError', 'Server request failed.'));
				}
				cursorFecha = result.cursor_fecha;
				cursorId = result.cursor_id;
			}
			downloadCsv(content);
			update(text('exportDone', 'CSV exported. Rows: ') + totalRows, 100);
		} catch (error) {
			update(error.message);
		} finally {
			setBusy(false);
		}
	});

	importForm.addEventListener('submit', async function (event) {
		event.preventDefault();
		if (busy) {
			return;
		}
		const file = importForm.querySelector('[name="history_file"]').files[0];
		if (!file) {
			return;
		}
		setBusy(true);
		update(text('importing', 'Importing historical prices…'), 0);
		try {
			const start = await request('tcsup_history_import_start', { history_file: file });
			const batchSize = Number(importForm.querySelector('[name="batch_size"]').value) || 200;
			let result;
			do {
				result = await request('tcsup_history_import_batch', {
					token: start.token,
					batch_size: batchSize
				});
				update(
					text('importing', 'Importing historical prices…') + ' ' +
					text('inserted', 'Added: ') + result.inserted + ' · ' +
					text('duplicates', 'Duplicates: ') + result.duplicates + ' · ' +
					text('unmatched', 'Unmatched: ') + result.unmatched + ' · ' +
					text('invalid', 'Invalid: ') + result.invalid,
					result.progress
				);
				if (!result.done) {
					// Yield between CPU-controlled batches; no cron or Action Scheduler.
					await new Promise(function (resolve) { setTimeout(resolve, 150); });
				}
			} while (!result.done);
			update(text('importDone', 'Import finished. ') + status.textContent, 100);
		} catch (error) {
			update(error.message);
		} finally {
			setBusy(false);
		}
	});
}());
