/* JX browser Media host: jx.media/1
 *
 * Turns a host-neutral Media descriptor into <audio>/<video> and attaches
 * serialized Control bindings. No database handle or JX runtime secret crosses
 * this boundary. Bag-backed sources are resolved/subscribed through callbacks.
 */
(function (global) {
  'use strict';

  const VALUE_ACTIONS = new Set(['seek', 'volume', 'muted', 'loop', 'rate', 'source']);
  const ACTIONS = new Set(['play', 'pause', 'toggle', 'stop', ...VALUE_ACTIONS]);

  function safeUri(uri) {
    const value = String(uri == null ? '' : uri).trim();
    if (!value || value.length > 4096 || /[\0]/.test(value)) {
      throw new Error('Invalid Media URI');
    }
    if (/^\s*(?:javascript|vbscript):/i.test(value)) {
      throw new Error('Executable URI schemes are not Media sources');
    }
    return value;
  }

  function boolValue(value) {
    if (typeof value === 'boolean') return value;
    if (typeof value === 'number') return value !== 0;
    const text = String(value == null ? '' : value).trim().toLowerCase();
    if (['1', 'true', 'yes', 'on'].includes(text)) return true;
    if (['0', 'false', 'no', 'off', ''].includes(text)) return false;
    return Boolean(value);
  }

  function coerce(value, as, customCoerce) {
    const kind = String(as || 'raw').toLowerCase();
    if (kind === 'raw') return value;
    if (kind === 'string') return String(value == null ? '' : value);
    if (kind === 'boolean') return boolValue(value);
    if (kind === 'integer') {
      const n = Number.parseInt(value, 10);
      if (!Number.isFinite(n)) throw new Error('Control binding did not produce an integer');
      return n;
    }
    if (kind === 'float' || kind === 'number') {
      const n = Number(value);
      if (!Number.isFinite(n)) throw new Error('Control binding did not produce a finite number');
      return n;
    }
    if (kind === 'json') {
      return typeof value === 'string' ? JSON.parse(value) : value;
    }
    if (kind === 'algebra') {
      if (typeof customCoerce !== 'function') {
        throw new Error('Algebra control binding requires the JX coercion callback');
      }
      return customCoerce(value, kind);
    }
    throw new Error('Unsupported Control binding coercion: ' + kind);
  }

  function readPath(source, event, path) {
    if (!path) return undefined;
    const parts = String(path).split('.');
    const roots = [
      source,
      event && event.detail,
      event && event.target,
      event,
    ].filter(Boolean);

    for (const root of roots) {
      let value = root;
      let found = true;
      for (const part of parts) {
        if (value == null || !(part in Object(value))) {
          found = false;
          break;
        }
        value = value[part];
      }
      if (found) return value;
    }
    return undefined;
  }

  function emit(media, name, detail) {
    const payload = Object.assign({
      control: media.id,
      event: name,
      currentTime: Number(media.currentTime || 0),
      duration: Number.isFinite(media.duration) ? media.duration : null,
      volume: media.volume,
      muted: media.muted,
      paused: media.paused,
    }, detail || {});
    media.dispatchEvent(new CustomEvent('jx:media:' + name, { detail: payload, bubbles: true }));
  }

  function applyAction(media, action, value) {
    if (!ACTIONS.has(action)) throw new Error('Unsupported Media action: ' + action);

    switch (action) {
      case 'play': {
        const result = media.play();
        if (result && typeof result.catch === 'function') {
          result.catch(error => emit(media, 'error', { message: String(error && error.message || error) }));
        }
        return;
      }
      case 'pause':
        media.pause();
        return;
      case 'toggle':
        if (media.paused) applyAction(media, 'play');
        else media.pause();
        return;
      case 'stop':
        media.pause();
        try { media.currentTime = 0; } catch (_) {}
        return;
      case 'seek': {
        let next = Number(value);
        if (!Number.isFinite(next)) throw new Error('Seek requires a finite number');
        if (Number.isFinite(media.duration)) next = Math.min(Math.max(0, next), media.duration);
        else next = Math.max(0, next);
        media.currentTime = next;
        return;
      }
      case 'volume': {
        const next = Number(value);
        if (!Number.isFinite(next)) throw new Error('Volume requires a finite number');
        media.volume = Math.min(1, Math.max(0, next));
        return;
      }
      case 'muted':
        media.muted = boolValue(value);
        return;
      case 'loop':
        media.loop = boolValue(value);
        return;
      case 'rate': {
        const next = Number(value);
        if (!Number.isFinite(next) || next <= 0) throw new Error('Playback rate must be positive');
        media.playbackRate = Math.min(16, Math.max(0.0625, next));
        return;
      }
      case 'source':
        media.src = safeUri(value);
        media.load();
        return;
    }
  }

  function resolveSource(descriptor, options) {
    const source = descriptor.source || {};
    if (source.kind === 'asset') return safeUri(source.uri);
    if (source.kind === 'bag') {
      if (typeof options.resolveBag !== 'function') {
        throw new Error('Bag-backed Media source requires resolveBag(source)');
      }
      return safeUri(options.resolveBag(source));
    }
    throw new Error('Media source must be asset or Bag');
  }

  function mount(descriptor, root, options) {
    options = options || {};
    root = root || document.body;
    if (!descriptor || descriptor.control !== 'media') {
      throw new Error('JX Media host requires a Media control descriptor');
    }

    const media = document.createElement(descriptor.type === 'video' ? 'video' : 'audio');
    media.id = descriptor.id;
    media.dataset.jxControl = 'media';
    media.dataset.jxPlugin = descriptor.plugin || 'media';
    media.preload = (descriptor.with && descriptor.with.preload) || 'metadata';
    media.controls = descriptor.with && Object.prototype.hasOwnProperty.call(descriptor.with, 'controls')
      ? Boolean(descriptor.with.controls)
      : true;
    if (descriptor.with) {
      if ('loop' in descriptor.with) media.loop = Boolean(descriptor.with.loop);
      if ('muted' in descriptor.with) media.muted = Boolean(descriptor.with.muted);
      if ('volume' in descriptor.with) media.volume = Math.min(1, Math.max(0, Number(descriptor.with.volume)));
      if (descriptor.type === 'video' && descriptor.with.poster) media.poster = safeUri(descriptor.with.poster);
    }
    media.src = resolveSource(descriptor, options);
    root.appendChild(media);

    const cleanups = [];
    const attached = new Set();

    function attachBinding(binding) {
      if (!binding || binding.kind !== 'control-binding' || attached.has(binding.id)) return false;
      const from = binding.from || {};
      const to = binding.to || {};
      if (to.control !== descriptor.id || !ACTIONS.has(to.action)) return false;
      const source = document.getElementById(from.control);
      if (!source) return false;

      const eventName = String(from.event || '').trim();
      if (!eventName) return false;

      const handler = function (event) {
        try {
          let value;
          if (VALUE_ACTIONS.has(to.action)) {
            value = readPath(source, event, from.value || 'value');
            value = coerce(value, binding.with && binding.with.as, options.coerce);
          }
          applyAction(media, to.action, value);
        } catch (error) {
          emit(media, 'error', {
            binding: binding.id,
            message: String(error && error.message || error),
          });
        }
      };

      source.addEventListener(eventName, handler);
      cleanups.push(() => source.removeEventListener(eventName, handler));
      attached.add(binding.id);
      return true;
    }

    const bindings = Array.isArray(descriptor.controlBindings) ? descriptor.controlBindings : [];
    bindings.forEach(attachBinding);

    // Page Controls may mount after Media. Keep trying only for unresolved
    // bindings and disconnect the observer when everything is attached.
    let observer = null;
    if (bindings.some(binding => !attached.has(binding.id)) && typeof MutationObserver !== 'undefined') {
      observer = new MutationObserver(function () {
        bindings.forEach(attachBinding);
        if (bindings.every(binding => attached.has(binding.id))) {
          observer.disconnect();
          observer = null;
        }
      });
      observer.observe(root.ownerDocument || document, { childList: true, subtree: true });
      cleanups.push(() => { if (observer) observer.disconnect(); });
    }

    const mediaEvents = ['play', 'pause', 'ended', 'timeupdate', 'seeked', 'volumechange', 'error'];
    mediaEvents.forEach(function (eventName) {
      const handler = function () {
        const mapped = ({ timeupdate: 'time', seeked: 'seek', volumechange: 'volume' })[eventName] || eventName;
        emit(media, mapped);
      };
      media.addEventListener(eventName, handler);
      cleanups.push(() => media.removeEventListener(eventName, handler));
    });

    let unsubscribeBag = null;
    if (descriptor.source && descriptor.source.kind === 'bag' && typeof options.subscribeBag === 'function') {
      unsubscribeBag = options.subscribeBag(descriptor.source, function (next) {
        try {
          const uri = safeUri(next);
          if (uri !== media.src) {
            media.src = uri;
            media.load();
          }
        } catch (error) {
          emit(media, 'error', { message: String(error && error.message || error) });
        }
      });
    }

    if (descriptor.with && descriptor.with.start != null) {
      const start = Math.max(0, Number(descriptor.with.start) || 0);
      const setStart = function () {
        try { media.currentTime = start; } catch (_) {}
      };
      if (media.readyState >= 1) setStart();
      else {
        media.addEventListener('loadedmetadata', setStart, { once: true });
        cleanups.push(() => media.removeEventListener('loadedmetadata', setStart));
      }
    }

    if (descriptor.with && descriptor.with.autoplay) {
      applyAction(media, 'play');
    }

    return {
      element: media,
      descriptor: descriptor,
      apply: function (action, value) { return applyAction(media, action, value); },
      refreshBindings: function () { bindings.forEach(attachBinding); },
      unmount: function () {
        cleanups.splice(0).reverse().forEach(fn => { try { fn(); } catch (_) {} });
        if (typeof unsubscribeBag === 'function') {
          try { unsubscribeBag(); } catch (_) {}
        }
        media.pause();
        media.removeAttribute('src');
        media.load();
        if (media.parentNode) media.parentNode.removeChild(media);
      },
    };
  }

  const api = { mount, safeUri, coerce, applyAction };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  global.JXMediaHost = api;
})(typeof window !== 'undefined' ? window : globalThis);
