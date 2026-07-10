/**
 * Okul Yönetim Sistemi — hafif SVG grafik motoru.
 * Harici bağımlılık yoktur. data-sms-chart="line|bar|donut" öğelerini çizer.
 * Veri biçimi: data-points='[{"label":"1 Eki","value":80}, ...]'
 * Donut için:  data-points='[{"label":"Geldi","value":120,"color":"#22c55e"}, ...]'
 */
(function () {
	'use strict';

	var NS = 'http://www.w3.org/2000/svg';

	function el(tag, attrs) {
		var node = document.createElementNS(NS, tag);
		for (var key in attrs) {
			node.setAttribute(key, attrs[key]);
		}
		return node;
	}

	function showTip(container, x, y, text) {
		hideTip(container);
		var tip = document.createElement('div');
		tip.className = 'sms-chart-tip';
		tip.textContent = text;
		tip.style.left = x + 'px';
		tip.style.top = y + 'px';
		container.appendChild(tip);
	}

	function hideTip(container) {
		var tip = container.querySelector('.sms-chart-tip');
		if (tip) {
			tip.remove();
		}
	}

	/* ---------- Çizgi grafik ---------- */
	function drawLine(container, points, suffix) {
		var W = 560, H = 220, padL = 36, padR = 12, padT = 14, padB = 30;
		var innerW = W - padL - padR, innerH = H - padT - padB;
		var max = 100;

		var svg = el('svg', { viewBox: '0 0 ' + W + ' ' + H });

		// Yatay kılavuz çizgileri
		[0, 25, 50, 75, 100].forEach(function (v) {
			var y = padT + innerH - (v / max) * innerH;
			svg.appendChild(el('line', { x1: padL, y1: y, x2: W - padR, y2: y, stroke: '#eef1f6', 'stroke-width': 1 }));
			var label = el('text', { x: padL - 8, y: y + 4, 'text-anchor': 'end', 'font-size': 10, fill: '#94a3b8' });
			label.textContent = v;
			svg.appendChild(label);
		});

		var step = points.length > 1 ? innerW / (points.length - 1) : 0;
		var path = '', area = '';
		var coords = [];

		points.forEach(function (p, i) {
			if (p.value === null || p.value === undefined) { coords.push(null); return; }
			var x = padL + i * step;
			var y = padT + innerH - (Math.min(p.value, max) / max) * innerH;
			coords.push({ x: x, y: y, p: p });
			path += (path ? ' L ' : 'M ') + x + ' ' + y;
		});

		var valid = coords.filter(Boolean);
		if (valid.length > 1) {
			area = path + ' L ' + valid[valid.length - 1].x + ' ' + (padT + innerH) + ' L ' + valid[0].x + ' ' + (padT + innerH) + ' Z';
			var grad = el('linearGradient', { id: 'smsGrad' + Math.random().toString(36).slice(2, 7), x1: 0, y1: 0, x2: 0, y2: 1 });
			var gradId = grad.getAttribute('id');
			var s1 = el('stop', { offset: '0%', 'stop-color': '#6366f1', 'stop-opacity': 0.25 });
			var s2 = el('stop', { offset: '100%', 'stop-color': '#6366f1', 'stop-opacity': 0.02 });
			grad.appendChild(s1); grad.appendChild(s2);
			var defs = el('defs', {});
			defs.appendChild(grad);
			svg.appendChild(defs);
			svg.appendChild(el('path', { d: area, fill: 'url(#' + gradId + ')' }));
			svg.appendChild(el('path', { d: path, fill: 'none', stroke: '#4f46e5', 'stroke-width': 2.5, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }));
		}

		coords.forEach(function (c, i) {
			// X ekseni etiketleri: sıklığı azalt
			if (points.length > 8 && i % 2 !== 0 && i !== points.length - 1) { /* etiket atla */ } else {
				var lx = padL + i * step;
				var label = el('text', { x: lx, y: H - 8, 'text-anchor': 'middle', 'font-size': 9.5, fill: '#94a3b8' });
				label.textContent = points[i].label;
				svg.appendChild(label);
			}
			if (!c) { return; }
			var dot = el('circle', { cx: c.x, cy: c.y, r: 4, fill: '#fff', stroke: '#4f46e5', 'stroke-width': 2.5, cursor: 'pointer' });
			dot.addEventListener('mouseenter', function (evt) {
				var rect = container.getBoundingClientRect();
				showTip(container, evt.clientX - rect.left, evt.clientY - rect.top, c.p.label + ': ' + c.p.value + (suffix || ''));
			});
			dot.addEventListener('mouseleave', function () { hideTip(container); });
			svg.appendChild(dot);
		});

		if (!valid.length) {
			emptyText(svg, W, H);
		}
		container.style.position = 'relative';
		container.appendChild(svg);
	}

	/* ---------- Bar grafik ---------- */
	function drawBar(container, points, suffix) {
		var W = 560, H = 220, padL = 36, padR = 12, padT = 14, padB = 30;
		var innerW = W - padL - padR, innerH = H - padT - padB;
		var max = 100;

		var svg = el('svg', { viewBox: '0 0 ' + W + ' ' + H });

		[0, 25, 50, 75, 100].forEach(function (v) {
			var y = padT + innerH - (v / max) * innerH;
			svg.appendChild(el('line', { x1: padL, y1: y, x2: W - padR, y2: y, stroke: '#eef1f6', 'stroke-width': 1 }));
			var label = el('text', { x: padL - 8, y: y + 4, 'text-anchor': 'end', 'font-size': 10, fill: '#94a3b8' });
			label.textContent = v;
			svg.appendChild(label);
		});

		var slot = innerW / points.length;
		var barW = Math.min(26, slot * 0.6);
		var hasData = false;

		points.forEach(function (p, i) {
			var cx = padL + i * slot + slot / 2;
			if (points.length <= 8 || i % 2 === 0 || i === points.length - 1) {
				var label = el('text', { x: cx, y: H - 8, 'text-anchor': 'middle', 'font-size': 9.5, fill: '#94a3b8' });
				label.textContent = p.label;
				svg.appendChild(label);
			}
			if (p.value === null || p.value === undefined) { return; }
			hasData = true;
			var h = (Math.min(p.value, max) / max) * innerH;
			var bar = el('rect', {
				x: cx - barW / 2,
				y: padT + innerH - h,
				width: barW,
				height: Math.max(h, 2),
				rx: 5,
				fill: p.value >= 75 ? '#22c55e' : (p.value >= 50 ? '#f59e0b' : '#ef4444'),
				opacity: 0.9,
				cursor: 'pointer'
			});
			bar.addEventListener('mouseenter', function (evt) {
				var rect = container.getBoundingClientRect();
				showTip(container, evt.clientX - rect.left, evt.clientY - rect.top, p.label + ': ' + p.value + (suffix || ''));
			});
			bar.addEventListener('mouseleave', function () { hideTip(container); });
			svg.appendChild(bar);
		});

		if (!hasData) {
			emptyText(svg, W, H);
		}
		container.style.position = 'relative';
		container.appendChild(svg);
	}

	/* ---------- Halka (donut) grafik ---------- */
	function drawDonut(container, points) {
		var total = points.reduce(function (sum, p) { return sum + p.value; }, 0);
		var size = 170, r = 62, cx = size / 2, cy = size / 2, stroke = 26;
		var C = 2 * Math.PI * r;

		var wrap = document.createElement('div');
		wrap.className = 'sms-donut-wrap';

		var svg = el('svg', { viewBox: '0 0 ' + size + ' ' + size, style: 'max-width:' + size + 'px;flex-shrink:0' });
		svg.appendChild(el('circle', { cx: cx, cy: cy, r: r, fill: 'none', stroke: '#eef1f6', 'stroke-width': stroke }));

		var offset = 0;
		points.forEach(function (p) {
			if (!p.value) { return; }
			var frac = p.value / total;
			var seg = el('circle', {
				cx: cx, cy: cy, r: r, fill: 'none',
				stroke: p.color || '#94a3b8',
				'stroke-width': stroke,
				'stroke-dasharray': (frac * C - 2) + ' ' + (C - frac * C + 2),
				'stroke-dashoffset': -offset * C + C / 4,
				'stroke-linecap': 'butt'
			});
			offset += frac;
			svg.appendChild(seg);
		});

		var center = el('text', { x: cx, y: cy + 6, 'text-anchor': 'middle', 'font-size': 20, 'font-weight': 800, fill: '#1e293b' });
		center.textContent = total;
		svg.appendChild(center);

		var legend = document.createElement('div');
		legend.className = 'sms-donut-legend';
		points.forEach(function (p) {
			var item = document.createElement('span');
			var pct = total ? Math.round(p.value / total * 100) : 0;
			item.innerHTML = '<i style="width:10px;height:10px;border-radius:3px;background:' + (p.color || '#94a3b8') + ';display:inline-block"></i>' +
				p.label + ' <strong>' + p.value + '</strong> <span style="color:#94a3b8">(%' + pct + ')</span>';
			legend.appendChild(item);
		});

		wrap.appendChild(svg);
		wrap.appendChild(legend);
		container.appendChild(wrap);
	}

	function emptyText(svg, W, H) {
		var t = el('text', { x: W / 2, y: H / 2, 'text-anchor': 'middle', 'font-size': 13, fill: '#94a3b8' });
		t.textContent = 'Henüz veri yok';
		svg.appendChild(t);
	}

	function init() {
		document.querySelectorAll('[data-sms-chart]').forEach(function (node) {
			var type = node.getAttribute('data-sms-chart');
			var suffix = node.getAttribute('data-suffix') || '';
			var points;
			try {
				points = JSON.parse(node.getAttribute('data-points') || '[]');
			} catch (e) {
				points = [];
			}
			if (type === 'line') {
				drawLine(node, points, suffix);
			} else if (type === 'bar') {
				drawBar(node, points, suffix);
			} else if (type === 'donut') {
				drawDonut(node, points);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
