/* JX browser audio-signal host: jx.audio-signals/1
 * Keeps decoded sample state hot and publishes bounded snapshots to Bags.
 */
(function (global) {
  'use strict';

  const graphs = new WeakMap();

  function requirePublisher(options) {
    if (!options || typeof options.publishBag !== 'function') throw new Error('JX audio signals require publishBag(target, rows, meta)');
    return options.publishBag;
  }

  function resolveMedia(binding, options) {
    if (options && typeof options.resolveMedia === 'function') {
      const media = options.resolveMedia(binding.source || {});
      if (media) return media;
    }
    const id = binding && binding.source && binding.source.media;
    const media = global.document && global.document.getElementById && global.document.getElementById(id);
    if (!media) throw new Error('Audio signal media is not mounted: ' + id);
    return media;
  }

  function graphFor(media, options) {
    if (graphs.has(media)) return graphs.get(media);
    const Ctor = global.AudioContext || global.webkitAudioContext;
    const context = options && options.audioContext || (Ctor && new Ctor());
    if (!context) throw new Error('Web Audio API is unavailable');
    const source = context.createMediaElementSource(media);
    const analyser = context.createAnalyser();
    analyser.fftSize = 2048;
    source.connect(analyser);
    analyser.connect(context.destination);
    const graph = { context, source, analyser };
    graphs.set(media, graph);
    return graph;
  }

  function loop(fn, everyMs) {
    let dead = false, timer = null;
    function tick() { if (dead) return; fn(); timer = global.setTimeout(tick, everyMs); }
    tick();
    return () => { dead = true; if (timer != null) global.clearTimeout(timer); };
  }

  function ring(limit) {
    const rows = [];
    return {
      push(row) { rows.push(row); if (rows.length > limit) rows.splice(0, rows.length - limit); },
      replace(next) { rows.splice(0, rows.length, ...next.slice(-limit)); },
      data() { return rows.slice(); },
    };
  }

  function samples(analyser, size) {
    analyser.fftSize = Math.max(32, Math.min(32768, 1 << Math.ceil(Math.log2(size))));
    const data = new Float32Array(analyser.fftSize);
    if (typeof analyser.getFloatTimeDomainData === 'function') analyser.getFloatTimeDomainData(data);
    else {
      const bytes = new Uint8Array(analyser.fftSize);
      analyser.getByteTimeDomainData(bytes);
      for (let i = 0; i < bytes.length; i++) data[i] = (bytes[i] - 128) / 128;
    }
    return data;
  }

  function levelOf(data) {
    let sum = 0, peak = 0;
    for (const v of data) { const a = Math.abs(v); peak = Math.max(peak, a); sum += v * v; }
    const rms = Math.sqrt(sum / Math.max(1, data.length));
    return { peak, rms, db: 20 * Math.log10(Math.max(rms, 1e-12)) };
  }

  function autoPitch(data, sampleRate) {
    let rms = 0;
    for (const v of data) rms += v * v;
    rms = Math.sqrt(rms / data.length);
    if (rms < 0.01) return { hz: 0, confidence: 0 };

    const minLag = Math.max(2, Math.floor(sampleRate / 2000));
    const maxLag = Math.min(data.length - 2, Math.floor(sampleRate / 40));
    const corrByLag = new Float64Array(maxLag + 1);
    let bestLag = 0, best = -Infinity;

    for (let lag = minLag; lag <= maxLag; lag++) {
      let corr = 0, normA = 0, normB = 0;
      for (let i = 0; i < data.length - lag; i++) {
        const a = data[i], b = data[i + lag];
        corr += a * b; normA += a * a; normB += b * b;
      }
      const n = corr / Math.sqrt(Math.max(1e-20, normA * normB));
      corrByLag[lag] = n;
      if (n > best) { best = n; bestLag = lag; }
    }

    if (!bestLag) return { hz: 0, confidence: 0 };

    // Periodic signals often create nearly identical correlation peaks at
    // 1x, 2x, 3x... the true period. Prefer the earliest peak that is within
    // 0.5% of the global best, which selects the fundamental instead of a
    // later subharmonic while preserving the best correlation as confidence.
    const threshold = Math.max(0.8, best * 0.995);
    for (let lag = minLag + 1; lag < bestLag; lag++) {
      const n = corrByLag[lag];
      if (n >= threshold && n >= corrByLag[lag - 1] && n >= corrByLag[lag + 1]) {
        bestLag = lag;
        break;
      }
    }

    return { hz: sampleRate / bestLag, confidence: Math.max(0, Math.min(1, best)) };
  }

  function noteName(hz) {
    if (!(hz > 0)) return null;
    const midi = Math.round(69 + 12 * Math.log2(hz / 440));
    const names = ['C','C#','D','D#','E','F','F#','G','G#','A','A#','B'];
    return names[((midi % 12) + 12) % 12] + (Math.floor(midi / 12) - 1);
  }

  function mount(binding, options) {
    if (!binding || binding.kind !== 'binding' || !String(binding.binding || '').startsWith('media.audio.')) throw new Error('JX audio signal host requires an audio signal binding');
    const publish = requirePublisher(options);
    const media = resolveMedia(binding, options);
    const graph = graphFor(media, options);
    const withOpts = binding.with || {};
    const everyMs = Math.max(8, Number(withOpts.every_ms || 50));
    const history = Math.max(1, Number(withOpts.history || 256) | 0);
    const size = Math.max(32, Number(withOpts.samples || 1024) | 0);
    const threshold = Math.max(0, Math.min(1, Number(withOpts.threshold == null ? 0.16 : withOpts.threshold)));
    const store = ring(history);
    let sampleIndex = 0, lastBeat = null, tempo = null;

    const stop = loop(function () {
      const data = samples(graph.analyser, size);
      const now = Number(media.currentTime || 0);
      const mode = String(binding.binding).slice('media.audio.'.length);

      if (mode === 'waveform') {
        const rows = [];
        const step = 1 / graph.context.sampleRate;
        for (let i = 0; i < data.length; i++) rows.push({ sample: sampleIndex++, time: now + i * step, value: data[i], channel: 0 });
        store.replace(rows);
      } else if (mode === 'level') {
        store.push(Object.assign({ time: now }, levelOf(data)));
      } else if (mode === 'pitch') {
        const p = autoPitch(data, graph.context.sampleRate);
        store.push({ time: now, hz: p.hz, confidence: p.confidence, note: noteName(p.hz) });
      } else if (mode === 'beat' || mode === 'tempo') {
        const l = levelOf(data);
        const hit = l.rms >= threshold && (lastBeat === null || now - lastBeat > 0.18);
        let interval = null;
        if (hit) {
          interval = lastBeat === null ? null : now - lastBeat;
          if (interval && interval > 0) tempo = 60 / interval;
          lastBeat = now;
        }
        if (mode === 'beat') store.push({ time: now, hit, strength: l.rms, interval });
        else store.push({ time: now, bpm: tempo || 0, confidence: tempo ? Math.min(1, l.rms / Math.max(threshold, 1e-9)) : 0 });
      } else if (mode === 'channels') {
        // MediaElementSource channel extraction is host-specific. Base browser host reports mono-equivalent balance unless a richer callback is supplied.
        const l = levelOf(data);
        const custom = options && typeof options.channelLevels === 'function' ? options.channelLevels(media, data) : null;
        const left = custom && Number.isFinite(custom.left) ? custom.left : l.rms;
        const right = custom && Number.isFinite(custom.right) ? custom.right : l.rms;
        const denom = Math.max(1e-12, left + right);
        store.push({ time: now, left, right, balance: (right - left) / denom, correlation: custom && Number.isFinite(custom.correlation) ? custom.correlation : 1 });
      }

      publish(binding.target, store.data(), { binding: binding.id, kind: binding.binding, reactive: true, hot: true, checkpoint: true });
    }, everyMs);

    return { unmount: stop, snapshot: () => store.data() };
  }

  const api = { mount, samples, levelOf, autoPitch, noteName };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  global.JXAudioSignalHost = api;
})(typeof window !== 'undefined' ? window : globalThis);
