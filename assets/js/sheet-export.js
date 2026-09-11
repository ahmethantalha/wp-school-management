/**
 * Rapor önizlemesini (.sheet) PNG/JPG olarak indirir.
 *
 * PDF sunucuda dompdf ile üretilir; görsel çıktı ise doğrudan ekrandaki
 * önizlemenin fotoğrafıdır. Önizleme ve PDF aynı şablonu ve aynı CSS'i
 * kullandığı için üç format da birbiriyle örtüşür.
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
		var buttons = document.querySelectorAll('[data-sms-sheet-export]');
		if (!buttons.length || typeof window.html2canvas !== 'function') {
			return;
		}

		var sheet = document.querySelector('[data-sms-sheet]');
		if (!sheet) {
			return;
		}

		function download(blob, filename) {
			var url = URL.createObjectURL(blob);
			var link = document.createElement('a');
			link.href = url;
			link.download = filename;
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
			// Tarayıcıya indirmeyi başlatması için zaman tanınır, sonra bellek serbest bırakılır.
			setTimeout(function () {
				URL.revokeObjectURL(url);
			}, 60000);
		}

		buttons.forEach(function (button) {
			button.addEventListener('click', function () {
				var type = button.getAttribute('data-sms-sheet-export') === 'jpeg' ? 'image/jpeg' : 'image/png';
				var ext = type === 'image/jpeg' ? 'jpg' : 'png';
				var base = button.getAttribute('data-sms-sheet-name') || 'rapor';
				var original = button.innerHTML;

				button.disabled = true;
				button.textContent = 'Hazırlanıyor…';

				// scale: 2 → yaklaşık iki kat çözünürlük; mesajlaşma uygulamalarında
				// okunaklı kalması için. JPG saydamlığı desteklemediğinden zemin beyaz verilir.
				window.html2canvas(sheet, {
					scale: 2,
					backgroundColor: '#ffffff',
					logging: false,
					useCORS: false
				}).then(function (canvas) {
					canvas.toBlob(function (blob) {
						if (blob) {
							download(blob, base + '.' + ext);
						} else {
							window.alert('Görsel oluşturulamadı. PDF olarak indirmeyi deneyin.');
						}
						button.disabled = false;
						button.innerHTML = original;
					}, type, 0.92);
				}).catch(function () {
					window.alert('Görsel oluşturulamadı. PDF olarak indirmeyi deneyin.');
					button.disabled = false;
					button.innerHTML = original;
				});
			});
		});
	});
})();
