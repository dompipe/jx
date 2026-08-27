/* JX browser media-analysis host
 * Executes audio spectrum and video frame analysis descriptors and publishes
 * normalized rows through a host-provided Bag callback.
 */
(function (global) {
  'use strict';

  const audioGraphs = new WeakMap();

  function requirePublisher(options) {
    if (!options || typeof options.publishBag !== 'function') {
      throw new Error('JX media analysis requires publishBag(target, rows, meta)');
    }
    return options.publishBag;
  }

  function mediaElement(binding, options) {
    if (options && typeof options.resolveMedia === 'function') {
      const resolved = options.resolveMedia(binding.source || {});
      if (resolved) return resolved;
    }
    const id = binding && binding.source && binding.source.media;
    if (!id || !global.document || typeof global.document.getElementById !== 'function') {
      throw new Error('Media analysis cannot resolve source Media control');
    }
    const media = global.document.getElementById(id);
    if (!media) throw new Error('Media control not mounted: ' + id);
    return media;
  }

  function requestLoop(fn, everyMs) {
    let stopped = false;
    let timer = null;
    function tick() {
      if (stopped) return;
      fn();
      timer = global.setTimeout(tick, Math.max(1, everyMs));
    }
    tick();
    return function stop() { stopped = true; if (timer != null) global.clearTimeout(timer); };
  }

  function audioContext(options) {
    if (options && options.audioContext) return options.audioContext;
    const Ctor = global.AudioContext || global.webkitAudioContext;
    if (!Ctor) throw new Error('Web Audio API is unavailable in this host');
    return new Ctor();
  }

  function graphFor(media, options) {
    if (audioGraphs.has(media)) return audioGraphs.get(media);
    const context = audioContext(options);
    const source = context.createMediaElementSource(media);
    const analyser = context.createAnalyser();
    source.connect(analyser);
    analyser.connect(context.destination);
    const graph = { context, source, analyser };
    audioGraphs.set(media, graph);
    return graph;
  }

  function spectrumRows(binding, analyser, sampleRate) {
    const frequency = binding.frequency || {};
    const buckets = Math.max(1, Number(frequency.buckets || 32) | 0);
    const measure = String(binding.measure || 'db').toLowerCase();
    const nativeBins = analyser.frequencyBinCount;
    const db = new Float32Array(nativeBins);
    analyser.getFloatFrequencyData(db);

    const nyquist = sampleRate / 2;
    const rangeFrom = Number.isFinite(Number(frequency.from)) ? Number(frequency.from) : 0;
    const rangeTo = Number.isFinite(Number(frequency.to)) ? Math.min(nyquist, Number(frequency.to)) : nyquist;
    const hzPerBin = nyquist / nativeBins;
    const startBin = Math.max(0, Math.floor(rangeFrom / hzPerBin));
    const endBin = Math.min(nativeBins, Math.ceil(rangeTo / hzPerBin));
    const span = Math.max(1, endBin - startBin);
    const rows = [];

    for (let bucket = 0; bucket < buckets; bucket++) {
      const a = startBin + Math.floor(span * bucket / buckets);
      const b = startBin + Math.max(1, Math.floor(span * (bucket + 1) / buckets));
      let sum = 0, count = 0;
      for (let i = a; i < Math.min(b, endBin); i++) { sum += db[i]; count++; }
      const dbValue = count ? sum / count : analyser.minDecibels;
      const amplitude = Math.pow(10, dbValue / 20);
      const value = measure === 'amplitude' ? amplitude : measure === 'power' ? amplitude * amplitude : dbValue;
      const from = a * hzPerBin;
      const to = Math.min(rangeTo, Math.max(from, b * hzPerBin));
      rows.push({ bucket, from, to, center: (from + to) / 2, value });
    }
    return rows;
  }

  function mountSpectrum(binding, options) {
    const publish = requirePublisher(options);
    const media = mediaElement(binding, options);
    const graph = graphFor(media, options);
    const frequency = binding.frequency || {};
    const requestedBins = Math.max(32, Number(frequency.buckets || 32) * 4);
    graph.analyser.fftSize = Math.min(32768, Math.pow(2, Math.ceil(Math.log2(requestedBins * 2))));
    graph.analyser.smoothingTimeConstant = Math.max(0, Math.min(1, Number(binding.smoothing == null ? 0.8 : binding.smoothing)));
    const everyMs = Math.max(16, Number((binding.with && binding.with.every_ms) || 50));

    return requestLoop(function () {
      const rows = spectrumRows(binding, graph.analyser, graph.context.sampleRate);
      publish(binding.target, rows, { binding: binding.id, kind: binding.binding, reactive: true });
    }, everyMs);
  }

  function createCanvas(options) {
    if (options && typeof options.createCanvas === 'function') return options.createCanvas();
    if (!global.document || typeof global.document.createElement !== 'function') throw new Error('Video analysis needs Canvas support');
    return global.document.createElement('canvas');
  }

  function analyzePixels(image, previous) {
    const data = image.data || image;
    let sum = 0, sumSq = 0, red = 0, green = 0, blue = 0, motion = 0;
    const pixels = Math.max(1, Math.floor(data.length / 4));
    for (let i = 0; i < data.length; i += 4) {
      const r = data[i], g = data[i + 1], b = data[i + 2];
      const l = 0.2126 * r + 0.7152 * g + 0.0722 * b;
      red += r; green += g; blue += b; sum += l; sumSq += l * l;
      if (previous && previous.length === data.length) {
        motion += (Math.abs(r - previous[i]) + Math.abs(g - previous[i + 1]) + Math.abs(b - previous[i + 2])) / (3 * 255);
      }
    }
    const luma = sum / pixels;
    const variance = Math.max(0, sumSq / pixels - luma * luma);
    return {
      brightness: luma / 255,
      luma,
      contrast: Math.sqrt(variance) / 255,
      red: red / pixels / 255,
      green: green / pixels / 255,
      blue: blue / pixels / 255,
      motion: previous ? motion / pixels : 0,
    };
  }

  function mountVideo(binding, options) {
    const publish = requirePublisher(options);
    const media = mediaElement(binding, options);
    const canvas = createCanvas(options);
    const context = canvas.getContext && canvas.getContext('2d', { willReadFrequently: true });
    if (!context) throw new Error('2D Canvas context is unavailable for video analysis');
    const everyMs = Math.max(1, Number(binding.sampling && binding.sampling.every_ms || 100));
    const threshold = Number(binding.with && binding.with.scene_threshold == null ? 0.25 : binding.with.scene_threshold);
    let previous = null, frame = 0, lastTime = null;

    return requestLoop(function () {
      const width = Number(media.videoWidth || media.width || 0);
      const height = Number(media.videoHeight || media.height || 0);
      if (!(width > 0 && height > 0)) return;
      const sampleWidth = Math.min(320, width);
      const sampleHeight = Math.max(1, Math.round(height * sampleWidth / width));
      if (canvas.width !== sampleWidth) canvas.width = sampleWidth;
      if (canvas.height !== sampleHeight) canvas.height = sampleHeight;
      context.drawImage(media, 0, 0, sampleWidth, sampleHeight);
      const image = context.getImageData(0, 0, sampleWidth, sampleHeight);
      const stats = analyzePixels(image, previous);
      const now = Number(media.currentTime || 0);
      const fps = lastTime !== null && now > lastTime ? 1 / (now - lastTime) : 0;
      const row = Object.assign({
        frame: frame++, time: now, fps, width, height,
        scene_change: stats.motion >= threshold,
      }, stats);
      publish(binding.target, [row], { binding: binding.id, kind: binding.binding, reactive: true });
      previous = new Uint8ClampedArray(image.data);
      lastTime = now;
    }, everyMs);
  }

  function mount(binding, options) {
    if (!binding || binding.kind !== 'binding') throw new Error('JX media analysis host requires a binding descriptor');
    if (binding.binding === 'media.spectrum') return { unmount: mountSpectrum(binding, options) };
    if (binding.binding === 'media.video.frames') return { unmount: mountVideo(binding, options) };
    throw new Error('Unsupported JX media analysis binding: ' + binding.binding);
  }

  const api = { mount, mountSpectrum, mountVideo, spectrumRows, analyzePixels };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  global.JXMediaAnalysisHost = api;
})(typeof window !== 'undefined' ? window : globalThis);
