(function () {
	'use strict';

	function init() {
		var config = window.taxocommerceIeExporter;
		var form = document.getElementById('tc-ie-export-form');
		if (!config || !form) {
			return;
		}

		var type = document.getElementById('tc-ie-export-type');
		var description = document.getElementById('tc-ie-export-type-description');
		var productFilters = document.getElementById('tc-ie-export-product-filters');
		var statuses = document.getElementById('tc-ie-export-statuses');
		var wpCategoryFilters = document.getElementById('tc-ie-export-wp-category-filters');
		var cluster = document.getElementById('tc-ie-export-cluster');
		var primary = document.getElementById('tc-ie-export-primary');
		var secondary = document.getElementById('tc-ie-export-secondary');
		var start = document.getElementById('tc-ie-export-start');
		var panel = document.getElementById('tc-ie-export-progress');
		var message = document.getElementById('tc-ie-export-message');
		var bar = document.getElementById('tc-ie-export-bar');
		var retry = document.getElementById('tc-ie-export-retry');
		var cancel = document.getElementById('tc-ie-export-cancel');
		var download = document.getElementById('tc-ie-export-download');

		var activeJob = '';
		var processing = false;
		var polling = false;
		var pollTimer = null;
		var browserAssist = false;
		var progressSignature = '';
		var lastProgressAt = Date.now();

		function selectedMeta() {
			return config.types && config.types[type.value] ? config.types[type.value] : {};
		}

		function updateTypeUi() {
			var meta = selectedMeta();
			description.textContent = meta.description || '';
			var group = meta.filters || '';
			if (productFilters) {
				productFilters.style.display = (group === 'products' || group === 'categories') ? '' : 'none';
			}
			if (statuses) {
				statuses.style.display = (group === 'products' || group === 'content') ? '' : 'none';
			}
			if (wpCategoryFilters) {
				wpCategoryFilters.style.display = group === 'wp_categories' ? '' : 'none';
			}
		}

		function filterChildOptions(select, parentId) {
			if (!select) {
				return;
			}
			var current = select.value;
			var visibleCurrent = false;
			Array.prototype.forEach.call(select.options, function (option, index) {
				if (index === 0) {
					option.hidden = false;
					option.disabled = false;
					return;
				}
				var parent = option.getAttribute('data-parent') || '0';
				var visible = !parentId || parent === String(parentId);
				option.hidden = !visible;
				option.disabled = !visible;
				if (visible && option.value === current) {
					visibleCurrent = true;
				}
			});
			if (!visibleCurrent && current !== '0') {
				select.value = '0';
			}
		}

		function updateHierarchy() {
			if (!cluster || !primary || !secondary) {
				return;
			}
			var clusterId = cluster.value === '0' ? '' : cluster.value;
			filterChildOptions(primary, clusterId);

			if (primary.value !== '0') {
				filterChildOptions(secondary, primary.value);
				return;
			}

			var allowedPrimaries = {};
			Array.prototype.forEach.call(primary.options, function (option, index) {
				if (index > 0 && !option.hidden && !option.disabled) {
					allowedPrimaries[option.value] = true;
				}
			});
			Array.prototype.forEach.call(secondary.options, function (option, index) {
				if (index === 0) {
					option.hidden = false;
					option.disabled = false;
					return;
				}
				var parent = option.getAttribute('data-parent') || '0';
				var visible = !clusterId || !!allowedPrimaries[parent];
				option.hidden = !visible;
				option.disabled = !visible;
			});
			if (secondary.selectedOptions.length && secondary.selectedOptions[0].disabled) {
				secondary.value = '0';
			}
		}

		function request(action, fields) {
			var body = new URLSearchParams();
			body.append('action', action);
			body.append('nonce', config.nonce);
			if (fields) {
				fields.forEach(function (value, key) {
					body.append(key, value);
				});
			}
			return fetch(config.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
				body: body.toString()
			}).then(function (response) {
				return response.json().catch(function () {
					throw new Error('Respuesta no válida del servidor (HTTP ' + response.status + ').');
				}).then(function (result) {
					if (!response.ok || !result || !result.success) {
						throw new Error(
							(result && result.data && result.data.message) ||
							('Error HTTP ' + response.status)
						);
					}
					return result.data || {};
				});
			});
		}

		function observeProgress(data, serverOnly) {
			var signature = String(data.job || '') + ':' + Number(data.examined || 0) + ':' + Number(data.blocks || 0) + ':' + String(!!data.complete);
			if (signature !== progressSignature) {
				if (serverOnly && progressSignature && browserAssist) {
					// Background processing recovered; stop browser assistance.
					browserAssist = false;
				}
				progressSignature = signature;
				lastProgressAt = Date.now();
			}
		}

		function showPanel() {
			panel.style.display = '';
		}

		function setProgress(data) {
			var processed = Number(data.processed || 0);
			var examined = Number(data.examined || processed);
			var skipped = Number(data.skipped || 0);
			var blocks = Number(data.blocks || 0);
			var total = Number(data.total || 0);
			var pct = data.complete ? 100 : (total > 0 ? Math.min(99, (examined / total) * 100) : 0);
			bar.value = pct;
			message.textContent = (data.typeLabel ? data.typeLabel + ' · ' : '') +
				processed + ' filas escritas · ' + examined + (total ? ' / ' + total : '') +
				' registros revisados · ' + blocks + ' bloques' +
				(skipped ? ' · ' + skipped + ' omitidos' : '') +
				(data.complete ? '. CSV terminado.' : (data.status === 'cancelling' ? '. Cancelando…' : '. Generando en segundo plano…'));
			if (data.status === 'failed') {
				message.textContent = 'Exportación interrumpida: ' + (data.error || 'Error desconocido del servidor.');
			}
		}

		function setBusy(value) {
			processing = value;
			start.disabled = value || (!!activeJob && !value);
			cancel.style.display = activeJob && !value ? '' : (activeJob ? '' : 'none');
		}

		function showError(error) {
			showPanel();
			message.textContent = 'Exportación interrumpida: ' + (error && error.message ? error.message : error);
			retry.style.display = activeJob ? '' : 'none';
			cancel.style.display = activeJob ? '' : 'none';
		}

		function showComplete(data) {
			setBusy(false);
			retry.style.display = 'none';
			cancel.style.display = 'none';
			download.href = data.downloadUrl || '#';
			download.style.display = data.downloadUrl ? '' : 'none';
			setProgress(data);
			activeJob = '';
			browserAssist = false;
			progressSignature = '';
			start.disabled = false;
		}

		// Background workers remain primary; if they stall, the open browser
		// resumes the same lock-protected, bounded server-side CSV worker via AJAX.
		async function pollStatus() {
			if (polling) {
				return;
			}
			polling = true;
			try {
				var data = await request('taxocommerce_ie_export_status');
				if (!data.active) {
					if (activeJob) {
						message.textContent = 'No hay exportación activa. Ya puedes iniciar una nueva.';
						bar.value = 0;
					}
					activeJob = '';
					browserAssist = false;
					progressSignature = '';
					setBusy(false);
					cancel.style.display = 'none';
					retry.style.display = 'none';
					return;
				}
				activeJob = data.job || '';
				observeProgress(data, true);
				showPanel();
				setProgress(data);
				if (data.complete) {
					showComplete(data);
					return;
				}
				if ((data.status === 'queued' || data.status === 'running') &&
					(browserAssist || Date.now() - lastProgressAt >= 15000)) {
					browserAssist = true;
					var fields = new URLSearchParams();
					fields.append('job', activeJob);
					data = await request('taxocommerce_ie_export_step', fields);
					observeProgress(data, false);
					setProgress(data);
					if (data.complete) {
						showComplete(data);
						return;
					}
				}
				setBusy(false);
				cancel.style.display = '';
				retry.style.display = data.status === 'failed' ? '' : 'none';
				if (data.status !== 'failed') {
					pollTimer = window.setTimeout(pollStatus, browserAssist ? Math.max(2000, Number(data.delay) || 0) : 5000);
				}
			} catch (error) {
				showError(error);
				pollTimer = window.setTimeout(pollStatus, 15000);
			} finally {
				polling = false;
			}
		}

		function runJob() {
			if (pollTimer) {
				window.clearTimeout(pollTimer);
			}
			pollStatus();
		}

		form.addEventListener('submit', async function (event) {
			event.preventDefault();
			if (processing) {
				return;
			}
			showPanel();
			message.textContent = 'Preparando exportación…';
			bar.value = 0;
			retry.style.display = 'none';
			cancel.style.display = 'none';
			download.style.display = 'none';
			activeJob = '';
			setBusy(true);

			try {
				var fields = new FormData(form);
				var params = new URLSearchParams();
				fields.forEach(function (value, key) {
					params.append(key, value);
				});
				var data = await request('taxocommerce_ie_export_start', params);
				activeJob = data.job || '';
				progressSignature = '';
				browserAssist = false;
				observeProgress(data, false);
				setProgress(data);
				setBusy(false);
				if (!activeJob) {
					throw new Error('El servidor no devolvió un identificador de exportación.');
				}
				cancel.style.display = '';
				runJob();
			} catch (error) {
				setBusy(false);
				showError(error);
			}
		});

		retry.addEventListener('click', async function () {
			retry.disabled = true;
			try {
				await request('taxocommerce_ie_export_resume');
				retry.style.display = 'none';
				runJob();
			} catch (error) {
				showError(error);
			} finally {
				retry.disabled = false;
			}
		});

		cancel.addEventListener('click', async function () {
			if (!activeJob) {
				return;
			}
			cancel.disabled = true;
			try {
				var result = await request('taxocommerce_ie_export_cancel');
				if (result.cancelled) {
					activeJob = '';
					browserAssist = false;
					progressSignature = '';
					processing = false;
					message.textContent = 'Exportación cancelada. El archivo parcial se ha eliminado.';
					bar.value = 0;
					retry.style.display = 'none';
					cancel.style.display = 'none';
					download.style.display = 'none';
					start.disabled = false;
				} else {
					message.textContent = 'Cancelación solicitada: el servidor terminará el bloque activo antes de eliminar el CSV.';
					runJob();
				}
			} catch (error) {
				showError(error);
			} finally {
				cancel.disabled = false;
			}
		});

		type.addEventListener('change', updateTypeUi);
		if (cluster) {
			cluster.addEventListener('change', function () {
				if (primary) {
					primary.value = '0';
				}
				if (secondary) {
					secondary.value = '0';
				}
				updateHierarchy();
			});
		}
		if (primary) {
			primary.addEventListener('change', function () {
				if (secondary) {
					secondary.value = '0';
				}
				updateHierarchy();
			});
		}

		updateTypeUi();
		updateHierarchy();

		pollStatus();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
