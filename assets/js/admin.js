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
				roster.querySelectorAll('.sms-roster-item, .sms-roster-row').forEach(function (item) {
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

		/* ---------- Yoklama: not alanını aç/kapat (mobilde yer kazanmak için gizli) ---------- */
		document.querySelectorAll('[data-sms-note-toggle]').forEach(function (btn) {
			btn.addEventListener('click', function () {
				var row = btn.closest('.sms-att-row');
				var field = row ? row.querySelector('[data-sms-note-field]') : null;
				if (!field) { return; }
				var hidden = field.style.display === 'none';
				field.style.display = hidden ? '' : 'none';
				btn.classList.toggle('is-active', hidden);
				if (hidden) {
					var input = field.querySelector('input');
					if (input) { input.focus(); }
				}
			});
		});

		/* ---------- Alışkanlık: tümünü "Yaptı" işaretle ---------- */
		var allDoneBtn = document.querySelector('[data-sms-all-done]');
		if (allDoneBtn) {
			allDoneBtn.addEventListener('click', function () {
				document.querySelectorAll('input[type="radio"][name^="log_value"][value="1"]').forEach(function (radio) {
					radio.checked = true;
				});
			});
		}

		/* ---------- Not girişi: sınav bilgileriyle önceden doldurulmuş liste indir ---------- */
		var gradeTpl = document.querySelector('[data-sms-grade-template]');
		if (gradeTpl) {
			gradeTpl.addEventListener('click', function () {
				var form = gradeTpl.closest('form');
				if (!form) { return; }
				var url = gradeTpl.getAttribute('data-url');
				['title', 'exam_type', 'exam_date', 'max_score'].forEach(function (name) {
					var field = form.querySelector('[name="' + name + '"]');
					url += '&' + name + '=' + encodeURIComponent(field ? field.value : '');
				});
				window.location.href = url;
			});
		}

		/* ---------- Öğretmen formu: sınıf öğretmeni seçilince sorumlu sınıflar ---------- */
		var ctToggle = document.querySelector('[data-sms-ct-toggle]');
		var ctGrades = document.querySelector('[data-sms-ct-grades]');
		if (ctToggle && ctGrades) {
			ctToggle.addEventListener('change', function () {
				ctGrades.style.display = ctToggle.checked ? '' : 'none';
			});
		}

		/* ---------- Alışkanlık formu: takip türüne göre ek alanları göster ---------- */
		var scaleField = document.querySelector('[data-sms-scale-field]');
		if (scaleField) {
			document.querySelectorAll('input[name="track_type"]').forEach(function (radio) {
				radio.addEventListener('change', function () {
					scaleField.style.display = radio.value === 'scale' && radio.checked ? '' : 'none';
				});
			});
		}

		/* ---------- Raporlar: tarih aralığı / ay-yıl geçişi ---------- */
		var dateModeSelect = document.querySelector('[data-sms-datemode-toggle]');
		if (dateModeSelect) {
			var rangeFields = document.querySelector('.sms-daterange-fields');
			var monthFields = document.querySelector('.sms-monthyear-fields');
			dateModeSelect.addEventListener('change', function () {
				var isMonth = dateModeSelect.value === 'month';
				if (rangeFields) { rangeFields.style.display = isMonth ? 'none' : ''; }
				if (monthFields) { monthFields.style.display = isMonth ? '' : 'none'; }
			});
		}
	});
})();
