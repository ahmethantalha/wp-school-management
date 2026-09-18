/**
 * Afiş çıktısındaki elle işaretleme: "Ödeve devam etti" / "Gelmedi".
 *
 * Bu işaretler BİLEREK veritabanına yazılmaz. Okuma kaydı "kitap adı + sayfa"dır
 * ve sayfa boş bırakılınca kayıt hiç oluşmaz; sayfaya 0 yazmak kaydı oluştururdu
 * ama veriyi kirletirdi (okuma kaydı sayılır, kitap adı otomatik tamamlamaya
 * girer, "farklı kitap" sayısını şişirirdi). İşaret yalnızca çıktıda gerektiği
 * için yazdırma anında yaşar:
 *
 *   - Önizleme anında güncellenir → PNG/JPG zaten önizlemenin fotoğrafı olduğu
 *     için doğru çıkar (bkz. sheet-export.js).
 *   - PDF sunucuda üretildiğinden işaretler PDF bağlantısına 'marks' parametresi
 *     olarak yazılır → üç format da örtüşür.
 *   - Sayfa yenilenince kaybolmasın diye tarayıcının localStorage'ında tutulur;
 *     bu da veritabanına değil, yalnızca o tarayıcıya yazılır.
 */
(function () {
	'use strict';

	var STORE_PREFIX = 'nizamiye-marks:';

	function ready(fn) {
		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', fn);
		} else {
			fn();
		}
	}

	ready(function () {
		var panel = document.querySelector('[data-sms-marks]');
		var sheet = document.querySelector('[data-sms-sheet]');
		if (!panel || !sheet) {
			return;
		}

		var scope = panel.getAttribute('data-sms-marks') || '';
		var types = {};
		try {
			types = JSON.parse(panel.getAttribute('data-sms-mark-types') || '{}');
		} catch (e) {
			return;
		}

		var marks = {};

		function storeKey() {
			return STORE_PREFIX + scope;
		}

		function load() {
			try {
				marks = JSON.parse(window.localStorage.getItem(storeKey()) || '{}') || {};
			} catch (e) {
				// Gizli sekmede ya da site verisi kapalıyken okuma hata verebilir;
				// işaretler o oturumda boş başlar, sayfa yine çalışır.
				marks = {};
			}
		}

		function save() {
			try {
				window.localStorage.setItem(storeKey(), JSON.stringify(marks));
			} catch (e) {
				// Yazamamak işlevi bozmaz: işaretler sayfa yenilenene kadar durur.
			}
		}

		/** "12h,15a" — sunucunun nizamiye_parse_sheet_marks() ile okuduğu biçim. */
		function encode() {
			var out = [];
			Object.keys(marks).forEach(function (id) {
				if (types[marks[id]]) {
					out.push(id + marks[id]);
				}
			});
			return out.join(',');
		}

		/** Önizlemedeki satırı işaretli / işaretsiz haline getirir. */
		function paintRow(row, type) {
			var cells = row.querySelectorAll('td');
			if (cells.length < 3) {
				return;
			}
			// Sıra ve ad sütunu korunur; sonrası tek renkli etikete iner.
			// Sunucudaki Nizamiye_Sheet::apply_mark() ile aynı kural.
			for (var i = 2; i < cells.length; i++) {
				if (i === 2 && type) {
					cells[i].innerHTML = '';
					var chip = document.createElement('span');
					chip.className = 'chip chip-' + types[type].klass;
					chip.textContent = types[type].label;
					cells[i].appendChild(chip);
				} else if (type) {
					cells[i].innerHTML = '';
					var dash = document.createElement('span');
					dash.className = 'chip chip-empty';
					dash.textContent = '—';
					cells[i].appendChild(dash);
				} else {
					cells[i].innerHTML = cells[i].getAttribute('data-sms-original') || '';
				}
			}
		}

		/** İlk boyamadan önce her hücrenin özgün içeriği saklanır. */
		function remember(row) {
			row.querySelectorAll('td').forEach(function (cell) {
				if (null === cell.getAttribute('data-sms-original')) {
					cell.setAttribute('data-sms-original', cell.innerHTML);
				}
			});
		}

		function rowFor(id) {
			return sheet.querySelector('[data-sms-student="' + id + '"]');
		}

		function syncPdfLink() {
			var link = document.querySelector('[data-sms-pdf-link]');
			if (!link) {
				return;
			}
			var base = link.getAttribute('data-sms-pdf-link');
			var encoded = encode();
			link.setAttribute('href', encoded ? base + '&marks=' + encodeURIComponent(encoded) : base);
		}

		function apply() {
			panel.querySelectorAll('[data-sms-mark-for]').forEach(function (select) {
				var id = select.getAttribute('data-sms-mark-for');
				var row = rowFor(id);
				if (!row) {
					return;
				}
				remember(row);
				var type = marks[id] || '';
				select.value = type;
				// classList: doğrudan className'e yazmak şerit desenini ('alt') silerdi.
				row.classList.toggle('is-marked', Boolean(type));
				paintRow(row, type);
			});
			syncPdfLink();
		}

		panel.addEventListener('change', function (event) {
			var select = event.target.closest('[data-sms-mark-for]');
			if (!select) {
				return;
			}
			var id = select.getAttribute('data-sms-mark-for');
			if (select.value && types[select.value]) {
				marks[id] = select.value;
			} else {
				delete marks[id];
			}
			save();
			apply();
		});

		var clear = document.querySelector('[data-sms-marks-clear]');
		if (clear) {
			clear.addEventListener('click', function () {
				marks = {};
				save();
				apply();
			});
		}

		load();
		apply();
	});
})();
