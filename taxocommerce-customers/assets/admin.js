(function () {
	'use strict';
	const strings = window.tccustStrings || {};
	const t = (key, fallback) => strings[key] || fallback;

	function update(root, message, progress) {
		const status = root.querySelector('[id$="-status"]');
		const bar = root.querySelector('progress');
		if (status) { status.textContent = message; }
		if (bar && typeof progress === 'number') {
			bar.hidden = false;
			bar.value = Math.max(0, Math.min(100, progress));
		}
	}

	async function send(root, action, values) {
		const body = new FormData();
		body.append('action', action);
		body.append('_ajax_nonce', root.dataset.nonce);
		Object.keys(values).forEach(function (key) { body.append(key, values[key]); });
		const response = await fetch(root.dataset.ajax, {
			method: 'POST', body: body, credentials: 'same-origin', cache: 'no-store'
		});
		let payload;
		try { payload = await response.json(); } catch (error) {
			throw new Error(t('error', 'Unexpected server response.'));
		}
		if (!response.ok || !payload.success) {
			throw new Error(payload.data && payload.data.message || t('error', 'Unexpected server response.'));
		}
		return payload.data;
	}

	const sync = document.getElementById('tccust-sync');
	if (sync) {
		const startButton = document.getElementById('tccust-sync-start');
		const stopButton = document.getElementById('tccust-sync-stop');
		const speed = document.getElementById('tccust-sync-speed');
		let syncing = false, stopped = false, token = '';

		stopButton.addEventListener('click', function () {
			stopped = true;
			stopButton.disabled = true;
		});
		async function run(restart) {
			if (syncing) { return; }
			syncing = true; stopped = false;
			startButton.disabled = true; stopButton.hidden = false; stopButton.disabled = false;
			update(sync, t('syncing', 'Syncing paid WooCommerce orders...'), 0);
			try {
				const first = await send(sync, 'tccust_sync_start', { restart: restart ? '1' : '0', size: Number(speed.value) || 50 });
				token = first.token;
				let done = false;
				while (!done && !stopped) {
					const result = await send(sync, 'tccust_sync_batch', { token: token, size: Number(speed.value) || 50 });
					done = !!result.done;
					update(sync, t('syncing', 'Syncing paid WooCommerce orders...') + ' ' +
						result.page + '/' + result.pages + ' · ' + result.linked,
						result.progress);
					if (!done && !stopped) {
						await new Promise(function (resolve) { setTimeout(resolve, 150); });
					}
				}
				if (stopped && !done) {
					await send(sync, 'tccust_sync_cancel', { token: token });
					update(sync, t('stopped', 'Operation stopped. Completed batches are retained.'));
				} else {
					update(sync, t('synced', 'Customer synchronization complete.'), 100);
					window.location.reload();
				}
			} catch (error) {
				update(sync, error.message);
			} finally {
				token = ''; syncing = false; startButton.disabled = false;
				stopButton.hidden = true; stopButton.disabled = false;
			}
		}
		startButton.addEventListener('click', function () { run(sync.dataset.auto !== '1'); });
		// Opening the directory must not trigger a high-load historical sync.
	}

	const root = document.getElementById('tccust-transfer');
	if (!root) { return; }
	const form = document.getElementById('tccust-csv-import');
	const exportButton = document.getElementById('tccust-csv-export');
	const stopButton = document.getElementById('tccust-csv-stop');
	const issues = document.getElementById('tccust-csv-issues');
	const exportAllowed = !exportButton.disabled;
	const importAllowed = !form.querySelector('button[type="submit"]').disabled;
	let busy = false, stopped = false, token = '';

	function busyState(state, importing) {
		busy = state;
		exportButton.disabled = state || !exportAllowed;
		form.querySelectorAll('input, select, button').forEach(function (el) { el.disabled = state || !importAllowed; });
		stopButton.hidden = !state || !importing;
	}
	function speed() { return Number(form.querySelector('[name="size"]').value) || 100; }


	// Export is assembled privately on the server; only progress reaches JS.
	// No CSV rows or complete Blob are retained in the browser.
	exportButton.addEventListener('click', async function () {
		if (busy) { return; }
		busyState(true, false);
		update(root, t('exporting', 'Exporting contacts...'), 0);
		let exportToken = '';
		try {
			const first = await send(root, 'tccust_csv_export_start', {});
			exportToken = first.token;
			let next;
			do {
				next = await send(root, 'tccust_csv_export', { token: exportToken, size: speed() });
				update(root, t('exporting', 'Exporting contacts...') + ' ' + next.processed, next.progress);
			} while (!next.done);
			if (!next.download_url) { throw new Error(t('error', 'Unexpected server response.')); }
			update(root, t('exported', 'Contacts exported:') + ' ' + next.processed, 100);
			window.location.assign(next.download_url);
		} catch (error) {
			if (exportToken) {
				try { await send(root, 'tccust_csv_export_cancel', { token: exportToken }); } catch (ignored) { /* Session expires on its own. */ }
			}
			update(root, error.message);
		} finally {
			busyState(false, false);
		}
	});

	stopButton.addEventListener('click', function () {
		stopped = true;
		stopButton.disabled = true;
	});

	form.addEventListener('submit', async function (event) {
		event.preventDefault();
		if (busy) { return; }
		const file = form.querySelector('[name="customers_csv"]').files[0];
		if (!file) { return; }
		busyState(true, true); stopped = false; issues.textContent = '';
		update(root, t('importing', 'Importing contacts...'), 0);
		try {
			const start = await send(root, 'tccust_csv_start', { customers_csv: file });
			token = start.token;
			let done = false, last = null;
			while (!done && !stopped) {
				last = await send(root, 'tccust_csv_batch', { token: token, size: speed() });
				done = !!last.done;
				update(root,
					t('importing', 'Importing contacts...') + ' ' + last.processed +
					' · ' + t('created', 'Created') + ': ' + last.created +
					' · ' + t('updated', 'Updated') + ': ' + last.updated +
					' · ' + t('unchanged', 'Unchanged') + ': ' + last.unchanged +
					' · ' + t('rejected', 'Rejected') + ': ' + last.rejected,
					last.progress);
				if (!done && !stopped) { await new Promise(function (resolve) { setTimeout(resolve, 150); }); }
			}
			if (stopped && !done) {
				await send(root, 'tccust_csv_cancel', { token: token });
				update(root, t('stopped', 'Operation stopped. Completed batches are retained.'));
			} else {
				update(root, t('imported', 'Import complete.') + ' ' + (last ? last.processed : 0), 100);
			}
			if (last && last.problems && last.problems.length) {
				issues.textContent = t('rejected', 'Rejected') + ' — CSV ' + last.problems.join(', ');
			}
		} catch (error) {
			update(root, error.message);
		} finally {
			token = ''; busyState(false, false); stopButton.disabled = false;
		}
	});
}());
