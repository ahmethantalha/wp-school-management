/**
 * Okul Yönetim Sistemi — arayüz etkileşimleri.
 * Kadro filtreleme, toplu seçim, yoklama kısayolları, silme onayları.
 */
(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	ready(function () {

		/* ---------- Silme onayları ---------- */
		document.querySelectorAll('form.sms-confirm, form[id^="sms-delete"]').forEach(function (form) {
			form.addEventListener('submit', function (evt) {
				var btn = form.querySelector('[data-confirm]') || document.querySelector('[form="' + form.id + '"][data-confirm]');
				var message = btn ? btn.getAttribute('data-confirm') : 'Emin misiniz?';
				if (!window.confirm(message)) {
					evt.preventDefault();
				}
			});
		});

		/* ---------- Kadro listesi: arama + sınıf filtresi + toplu seçim ---------- */
		var roster = document.querySelector('[data-sms-roster]');
		if (roster) {
			var searchInput = document.querySelector('[data-sms-filter-search]');
			var gradeSelect = document.querySelector('[data-sms-filter-grade]');
			var counter = document.querySelector('[data-sms-count-checked]');

			function applyFilter() {
				var query = (searchInput && searchInput.value || '').toLocaleLowerCase('tr');
				var grade = gradeSelect ? gradeSelect.value : '';
				roster.querySelectorAll('.sms-roster-item').forEach(function (item) {
					var matchName = !query || (item.getAttribute('data-name') || '').indexOf(query) !== -1;
					var matchGrade = !grade || item.getAttribute('data-grade') === grade;
					item.classList.toggle('sms-hidden', !(matchName && matchGrade));
				});
			}

			function updateCount() {
				if (counter) {
					counter.textContent = roster.querySelectorAll('input[type="checkbox"]:checked').length;
				}
			}

			if (searchInput) { searchInput.addEventListener('input', applyFilter); }
			if (gradeSelect) { gradeSelect.addEventListener('change', applyFilter); }
			roster.addEventListener('change', updateCount);

			var selectBtn = document.querySelector('[data-sms-select-visible]');
			var clearBtn = document.querySelector('[data-sms-clear-visible]');
			if (selectBtn) {
				selectBtn.addEventListener('click', function () {
					roster.querySelectorAll('.sms-roster-item:not(.sms-hidden) input[type="checkbox"]').forEach(function (cb) { cb.checked = true; });
					updateCount();
				});
			}
			if (clearBtn) {
				clearBtn.addEventListener('click', function () {
					roster.querySelectorAll('.sms-roster-item:not(.sms-hidden) input[type="checkbox"]').forEach(function (cb) { cb.checked = false; });
					updateCount();
				});
			}
			updateCount();
		}

		/* ---------- Yoklama: tümünü "Geldi" işaretle ---------- */
		var allPresentBtn = document.querySelector('[data-sms-all-present]');
		if (allPresentBtn) {
			allPresentBtn.addEventListener('click', function () {
				document.querySelectorAll('input[type="radio"][value="present"]').forEach(function (radio) {
					radio.checked = true;
				});
			});
		}

		/* ---------- Alışkanlık: tümünü "Yaptı" işaretle ---------- */
		var allDoneBtn = document.querySelector('[data-sms-all-done]');
		if (allDoneBtn) {
			allDoneBtn.addEventListener('click', function () {
				document.querySelectorAll('input[type="radio"][name^="log_value"][value="1"]').forEach(function (radio) {
					radio.checked = true;
				});
			});
		}

		/* ---------- Alışkanlık formu: dereceli seçilince ölçek alanını göster ---------- */
		var scaleField = document.querySelector('[data-sms-scale-field]');
		if (scaleField) {
			document.querySelectorAll('input[name="track_type"]').forEach(function (radio) {
				radio.addEventListener('change', function () {
					scaleField.style.display = radio.value === 'scale' && radio.checked ? '' : 'none';
				});
			});
		}
	});
})();
