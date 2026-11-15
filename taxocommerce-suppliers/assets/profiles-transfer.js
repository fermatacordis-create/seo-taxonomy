(function () {
	'use strict';
	const root = document.getElementById('tcsup-profiles-transfer');
	if (!root) { return; }
	const form = document.getElementById('tcsup-profiles-import');
	const exportButton = document.getElementById('tcsup-profiles-export');
	const stopButton = document.getElementById('tcsup-profiles-stop');
	const status = document.getElementById('tcsup-profiles-status');
	const progress = document.getElementById('tcsup-profiles-progress');
	const errors = document.getElementById('tcsup-profiles-errors');
	const language = window.tcsupProfilesStrings || {};
	const t = (key, fallback) => language[key] || fallback;
	let busy = false;
	let stopped = false;
	let currentToken = '';
	const update = function (message, pct) {
		status.textContent = message;
		if (typeof pct === 'number') {
			progress.hidden = false;
			progress.value = Math.min(100, Math.max(0, pct));
		}
	};
	function setBusy(state, isImport) {
		busy = state;
		exportButton.disabled = state;
		form.querySelectorAll('input,select,button').forEach(function (control) { control.disabled = state; });
		stopButton.hidden = !state || !isImport;
	}
	async function request(action, payload) {
		const body = new FormData();
		body.append('action', action);
		body.append('_ajax_nonce', root.dataset.nonce);
		Object.keys(payload).forEach(function (key) { body.append(key, payload[key]); });
		const response = await fetch(root.dataset.ajax, {
			method: 'POST', credentials: 'same-origin', cache: 'no-store', body: body
		});
		let json;
		try { json = await response.json(); } catch (e) {
			throw new Error(t('serverError', 'Invalid server response.'));
		}
		if (!response.ok || !json.success) {
			throw new Error(json.data && json.data.message || t('serverError', 'Server request failed.'));
		}
		return json.data;
	}
	function csvCell(value) {
		let cell = value == null ? '' : String(value);
		// Prevent formula execution in spreadsheets, including contact names and notes.
		if (/^[\s]*[=+\-@]/.test(cell)) { cell = "'" + cell; }
		return '"' + cell.replace(/"/g, '""') + '"';
	}
	function csvLine(cells) { return cells.map(csvCell).join(',') + '\r\n'; }
	function download(csv) {
		const blob = new Blob(['\ufeff', csv], { type: 'text/csv;charset=utf-8' });
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = 'taxocommerce-proveedores-fichas-' + new Date().toISOString().slice(0, 10).replace(/-/g, '') + '.csv';
		document.body.appendChild(a);
		a.click();
		a.remove();
		setTimeout(function () { URL.revokeObjectURL(url); }, 2000);
	}
	function batchSize() { return Number(form.querySelector('[name="batch_size"]').value) || 100; }
	exportButton.addEventListener('click', async function () {
		if (busy) { return; }
		stopped = false; setBusy(true, false);
		update(t('exporting', 'Exporting supplier records...'), 0);
		let cursor = '', content = '', total = 0, expected = 0;
		try {
			for (;;) {
				const next = await request('tcsup_profiles_export_batch', {
					cursor: cursor, batch_size: batchSize()
				});
				if (!content) { content = csvLine(next.headers); }
				next.rows.forEach(function (row) { content += csvLine(row); });
				total += next.rows.length;
				if (next.total != null) { expected = Number(next.total); }
				update(t('exporting', 'Exporting supplier records...') + ' ' + total,
					expected ? Math.min(99, Math.round(total * 100 / expected)) : 0);
				if (next.done) { break; }
				if (!next.cursor || next.cursor === cursor) { throw new Error(t('serverError', 'Invalid export cursor.')); }
				cursor = next.cursor;
				await new Promise(function (resolve) { setTimeout(resolve, 100); });
			}
			download(content);
			update(t('exportDone', 'Exported supplier records: ') + total, 100);
		} catch (e) { update(e.message); }
		finally { setBusy(false, false); }
	});
	stopButton.addEventListener('click', function () {
		stopped = true;
		stopButton.disabled = true;
		update(t('stopping', 'Stopping import after the current batch...'));
	});
	form.addEventListener('submit', async function (event) {
		event.preventDefault();
		if (busy) { return; }
		const file = form.querySelector('[name="profiles_csv"]').files[0];
		if (!file) { return; }
		stopped = false; errors.textContent = ''; setBusy(true, true);
		update(t('importing', 'Importing supplier records...'), 0);
		let done = false, last = null;
		try {
			const first = await request('tcsup_profiles_import_start', { profiles_csv: file });
			currentToken = first.token;
			while (!done && !stopped) {
				last = await request('tcsup_profiles_import_batch', {
					token: currentToken, batch_size: batchSize()
				});
				done = !!last.done;
				update(t('importing', 'Importing supplier records...') + ' ' +
					last.processed + ' · ' + t('added', 'Created: ') + last.inserted + ' · ' +
					t('updated', 'Updated: ') + last.updated + ' · ' +
					t('unchanged', 'Unchanged: ') + last.unchanged + ' · ' +
					t('unmatched', 'Unknown: ') + last.unmatched + ' · ' +
					t('invalid', 'Invalid: ') + last.invalid, last.progress);
				if (!done && !stopped) {
					await new Promise(function (resolve) { setTimeout(resolve, 150); });
				}
			}
			if (stopped && currentToken && !done) {
				await request('tcsup_profiles_import_cancel', { token: currentToken });
				update(t('stopped', 'Import stopped. Completed batches are retained.'));
			} else {
				update(t('importDone', 'Import completed. ') + status.textContent, 100);
			}
			if (last && Array.isArray(last.problems) && last.problems.length) {
				const details = last.problems.map(function (x) {
					return String(x.row) + ': ' + (x.provider || '-') + ' (' + x.reason + ')';
				}).join('; ');
				errors.textContent = t('sampleErrors', 'First invalid or unmatched rows: ') + details;
			}
			if (last && last.done) {
				// Refresh the provider listing without submitting the import again.
				setTimeout(function () { window.location.reload(); }, 1200);
			}
		} catch (e) { update(e.message); }
		finally { currentToken = ''; setBusy(false, false); stopButton.disabled = false; }
	});
}());
