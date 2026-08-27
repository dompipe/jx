/* JX browser Chart host: jx.charts/2
 *
 * Dependency-free SVG renderer for host-neutral chart descriptors. The chart
 * subscribes to a Bag source; it never knows whether rows came from SQL, PASM,
 * audio analysis, video analysis, or another producer.
 */
(function (global) {
  'use strict';

  const NS = 'http://www.w3.org/2000/svg';

  function element(name, attrs, parent) {
    const el = (parent && parent.ownerDocument || global.document).createElementNS(NS, name);
    Object.keys(attrs || {}).forEach(key => el.setAttribute(key, String(attrs[key])));
    if (parent) parent.appendChild(el);
    return el;
  }

  function text(parent, x, y, value, attrs) {
    const el = element('text', Object.assign({ x, y }, attrs || {}), parent);
    el.textContent = String(value);
    return el;
  }

  function pathValue(row, path) {
    if (row == null) return null;
    let value = row;
    for (const part of String(path).split('.')) {
      if (value == null || !(part in Object(value))) return null;
      value = value[part];
    }
    return value;
  }

  function numeric(value, fallback) {
    const n = Number(value);
    return Number.isFinite(n) ? n : fallback;
  }

  function extent(values, fallbackMin, fallbackMax) {
    const finite = values.map(Number).filter(Number.isFinite);
    if (!finite.length) return [fallbackMin, fallbackMax];
    let min = Math.min(...finite), max = Math.max(...finite);
    if (min === max) { min -= 1; max += 1; }
    return [min, max];
  }

  function scale(value, inMin, inMax, outMin, outMax) {
    if (inMax === inMin) return (outMin + outMax) / 2;
    return outMin + (Number(value) - inMin) * (outMax - outMin) / (inMax - inMin);
  }

  function cleanRows(rows) {
    if (!Array.isArray(rows)) return [];
    return rows.filter(row => row && typeof row === 'object');
  }

  function clear(svg) {
    while (svg.firstChild) svg.removeChild(svg.firstChild);
  }

  function frame(svg, width, height, pad, title) {
    element('rect', { x: 0, y: 0, width, height, fill: 'transparent' }, svg);
    element('line', { x1: pad, y1: height - pad, x2: width - pad, y2: height - pad, stroke: 'currentColor', 'stroke-opacity': 0.4 }, svg);
    element('line', { x1: pad, y1: pad, x2: pad, y2: height - pad, stroke: 'currentColor', 'stroke-opacity': 0.4 }, svg);
    if (title) text(svg, pad, 18, title, { 'font-size': 13, 'font-weight': 600, fill: 'currentColor' });
  }

  function renderLine(svg, descriptor, rows, width, height, pad) {
    const xField = descriptor.fields.x;
    const series = descriptor.fields.series || [];
    const xs = rows.map((row, index) => numeric(pathValue(row, xField), index));
    const ys = [];
    series.forEach(s => rows.forEach(row => ys.push(numeric(pathValue(row, s.field), NaN))));
    const [xMin, xMax] = extent(xs, 0, Math.max(1, rows.length - 1));
    const [yMin, yMax] = extent(ys, 0, 1);
    series.forEach((s, si) => {
      const points = [];
      rows.forEach((row, i) => {
        const y = numeric(pathValue(row, s.field), NaN);
        if (!Number.isFinite(y)) return;
        points.push(scale(xs[i], xMin, xMax, pad, width - pad) + ',' + scale(y, yMin, yMax, height - pad, pad));
      });
      if (points.length) element('polyline', {
        points: points.join(' '), fill: 'none', stroke: 'currentColor',
        'stroke-width': descriptor.type === 'waveform' ? 1.25 : 2,
        'stroke-opacity': Math.max(0.35, 1 - si * 0.18),
      }, svg);
    });
  }

  function renderBar(svg, descriptor, rows, width, height, pad) {
    const series = descriptor.fields.series || [];
    const field = series[0] && series[0].field;
    if (!field || !rows.length) return;
    const values = rows.map(row => numeric(pathValue(row, field), 0));
    const [, max] = extent(values.concat([0]), 0, 1);
    const barW = (width - pad * 2) / rows.length;
    rows.forEach((row, i) => {
      const v = numeric(pathValue(row, field), 0);
      const h = scale(v, 0, Math.max(0.000001, max), 0, height - pad * 2);
      element('rect', {
        x: pad + i * barW + barW * 0.08, y: height - pad - h,
        width: Math.max(1, barW * 0.84), height: Math.max(0, h),
        fill: 'currentColor', 'fill-opacity': 0.72,
      }, svg);
    });
  }

  function renderPie(svg, descriptor, rows, width, height) {
    const labelField = descriptor.fields.label, valueField = descriptor.fields.value;
    const values = rows.map(row => Math.max(0, numeric(pathValue(row, valueField), 0)));
    const total = values.reduce((a, b) => a + b, 0);
    if (!(total > 0)) return;
    const cx = width / 2, cy = height / 2, r = Math.max(10, Math.min(width, height) * 0.34);
    let angle = -Math.PI / 2;
    values.forEach((value, i) => {
      const next = angle + value / total * Math.PI * 2;
      const x1 = cx + Math.cos(angle) * r, y1 = cy + Math.sin(angle) * r;
      const x2 = cx + Math.cos(next) * r, y2 = cy + Math.sin(next) * r;
      const large = next - angle > Math.PI ? 1 : 0;
      element('path', {
        d: `M ${cx} ${cy} L ${x1} ${y1} A ${r} ${r} 0 ${large} 1 ${x2} ${y2} Z`,
        fill: 'currentColor', 'fill-opacity': Math.max(0.22, 0.85 - i * 0.08),
        'data-label': pathValue(rows[i], labelField) || '',
      }, svg);
      angle = next;
    });
  }

  function renderCandles(svg, descriptor, rows, width, height, pad) {
    const f = descriptor.fields;
    const lows = rows.map(r => numeric(pathValue(r, f.low), NaN));
    const highs = rows.map(r => numeric(pathValue(r, f.high), NaN));
    const [min, max] = extent(lows.concat(highs), 0, 1);
    const slot = (width - pad * 2) / Math.max(1, rows.length);
    rows.forEach((row, i) => {
      const open = numeric(pathValue(row, f.open), NaN), high = numeric(pathValue(row, f.high), NaN);
      const low = numeric(pathValue(row, f.low), NaN), close = numeric(pathValue(row, f.close), NaN);
      if (![open, high, low, close].every(Number.isFinite)) return;
      const x = pad + slot * (i + 0.5);
      const yH = scale(high, min, max, height - pad, pad), yL = scale(low, min, max, height - pad, pad);
      const yO = scale(open, min, max, height - pad, pad), yC = scale(close, min, max, height - pad, pad);
      element('line', { x1: x, x2: x, y1: yH, y2: yL, stroke: 'currentColor' }, svg);
      element('rect', {
        x: x - slot * 0.28, y: Math.min(yO, yC), width: Math.max(1, slot * 0.56),
        height: Math.max(1, Math.abs(yO - yC)), fill: close >= open ? 'none' : 'currentColor',
        stroke: 'currentColor', 'fill-opacity': 0.65,
      }, svg);
    });
  }

  function renderHeatmap(svg, descriptor, rows, width, height, pad) {
    const f = descriptor.fields;
    const xs = rows.map(r => numeric(pathValue(r, f.x), NaN));
    const ys = rows.map(r => numeric(pathValue(r, f.y), NaN));
    const vs = rows.map(r => numeric(pathValue(r, f.value), NaN));
    const [xMin, xMax] = extent(xs, 0, 1), [yMin, yMax] = extent(ys, 0, 1), [vMin, vMax] = extent(vs, 0, 1);
    const cell = Math.max(2, Math.min(12, Math.sqrt((width * height) / Math.max(1, rows.length)) * 0.45));
    rows.forEach((row, i) => {
      if (![xs[i], ys[i], vs[i]].every(Number.isFinite)) return;
      const opacity = Math.max(0.08, Math.min(1, scale(vs[i], vMin, vMax, 0.1, 1)));
      element('rect', {
        x: scale(xs[i], xMin, xMax, pad, width - pad) - cell / 2,
        y: scale(ys[i], yMin, yMax, height - pad, pad) - cell / 2,
        width: cell, height: cell, fill: 'currentColor', 'fill-opacity': opacity,
      }, svg);
    });
  }

  function renderVectorMap(svg, descriptor, rows, width, height, pad) {
    const f = descriptor.fields;
    const values = rows.map(r => numeric(pathValue(r, f.value), 0));
    const [vMin, vMax] = extent(values, 0, 1);
    element('rect', { x: pad, y: pad, width: width - pad * 2, height: height - pad * 2, fill: 'none', stroke: 'currentColor', 'stroke-opacity': 0.25 }, svg);
    rows.forEach((row, i) => {
      const lat = numeric(pathValue(row, f.latitude), NaN), lon = numeric(pathValue(row, f.longitude), NaN);
      if (!Number.isFinite(lat) || !Number.isFinite(lon)) return;
      const x = scale(Math.max(-180, Math.min(180, lon)), -180, 180, pad, width - pad);
      const y = scale(Math.max(-90, Math.min(90, lat)), -90, 90, height - pad, pad);
      const r = Math.max(2, scale(values[i], vMin, vMax, 3, 10));
      element('circle', { cx: x, cy: y, r, fill: 'currentColor', 'fill-opacity': 0.62 }, svg);
    });
  }

  function render(svg, descriptor, rows, options) {
    rows = cleanRows(rows);
    options = options || {};
    const width = Math.max(160, numeric(options.width || descriptor.with && descriptor.with.width, 640));
    const height = Math.max(120, numeric(options.height || descriptor.with && descriptor.with.height, 320));
    const pad = 28;
    svg.setAttribute('viewBox', `0 0 ${width} ${height}`);
    svg.setAttribute('role', 'img');
    svg.setAttribute('aria-label', String(descriptor.with && descriptor.with.title || descriptor.id || descriptor.type));
    clear(svg);
    frame(svg, width, height, pad, descriptor.with && descriptor.with.title);
    switch (descriptor.type) {
      case 'line': case 'waveform': renderLine(svg, descriptor, rows, width, height, pad); break;
      case 'bar': renderBar(svg, descriptor, rows, width, height, pad); break;
      case 'pie': renderPie(svg, descriptor, rows, width, height); break;
      case 'candles': renderCandles(svg, descriptor, rows, width, height, pad); break;
      case 'heatmap': renderHeatmap(svg, descriptor, rows, width, height, pad); break;
      case 'vectormap': renderVectorMap(svg, descriptor, rows, width, height, pad); break;
      default: throw new Error('Unsupported JX chart type: ' + descriptor.type);
    }
    return svg;
  }

  function mount(descriptor, root, options) {
    options = options || {}; root = root || global.document.body;
    if (!descriptor || descriptor.control !== 'chart') throw new Error('JX Chart host requires a chart descriptor');
    const doc = root.ownerDocument || global.document;
    const svg = doc.createElementNS(NS, 'svg');
    svg.id = descriptor.id;
    svg.dataset.jxControl = 'chart'; svg.dataset.jxPlugin = descriptor.plugin || 'charts';
    root.appendChild(svg);

    let rows = [];
    if (typeof options.resolveBag === 'function') rows = cleanRows(options.resolveBag(descriptor.source));
    render(svg, descriptor, rows, options);

    let unsubscribe = null;
    if (typeof options.subscribeBag === 'function') {
      unsubscribe = options.subscribeBag(descriptor.source, function (nextRows) {
        rows = cleanRows(nextRows);
        render(svg, descriptor, rows, options);
      });
    }

    return {
      element: svg, descriptor,
      update(nextRows) { rows = cleanRows(nextRows); render(svg, descriptor, rows, options); },
      rows() { return rows.slice(); },
      unmount() {
        if (typeof unsubscribe === 'function') { try { unsubscribe(); } catch (_) {} }
        if (svg.parentNode) svg.parentNode.removeChild(svg);
      },
    };
  }

  const api = { mount, render, pathValue, extent, scale };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  global.JXChartHost = api;
})(typeof window !== 'undefined' ? window : globalThis);
