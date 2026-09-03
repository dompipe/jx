(function (global) {
  'use strict';

  const VERSION = 'jx.host/2';
  let sequence = 0;
  let handleSequence = 0;
  const handles = new Map();
  const reverseHandles = new WeakMap();
  const listeners = new Map();
  const routes = new Map();

  function serial(value) {
    if (typeof value === 'bigint') return value.toString();
    if (value == null || typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') return value;
    if (Array.isArray(value)) return value.map(serial);
    if (typeof value === 'object') {
      const out = {};
      for (const [key, entry] of Object.entries(value)) out[key] = serial(entry);
      return out;
    }
    return String(value);
  }

  function expose(node) {
    if (!node) return 0;
    const existing = reverseHandles.get(node);
    if (existing) return existing;
    const id = ++handleSequence;
    handles.set(id, node);
    reverseHandles.set(node, id);
    return id;
  }

  function handleNumber(value) {
    if (typeof value === 'bigint') return Number(value);
    if (typeof value === 'number') return value;
    return null;
  }

  function resolve(target) {
    if (target == null || target === 0 || target === 0n) return null;
    const id = handleNumber(target);
    if (id != null) return handles.get(id) || null;
    if (typeof target === 'string') return document.querySelector(target);
    if (target && typeof target === 'object' && typeof target.nodeType === 'number') return target;
    return null;
  }

  function requireNode(target) {
    const node = resolve(target);
    if (!node) throw new Error(`JX DOM target not found: ${String(target)}`);
    return node;
  }

  function eventPayload(event, currentTarget) {
    const target = event.target;
    return {
      type: event.type,
      key: event.key || '',
      code: event.code || '',
      button: typeof event.button === 'number' ? event.button : -1,
      buttons: typeof event.buttons === 'number' ? event.buttons : 0,
      clientX: typeof event.clientX === 'number' ? event.clientX : 0,
      clientY: typeof event.clientY === 'number' ? event.clientY : 0,
      value: target && 'value' in target ? target.value : null,
      checked: target && 'checked' in target ? !!target.checked : null,
      target: expose(target),
      currentTarget: expose(currentTarget),
    };
  }

  const dom = {
    get(selector) { return expose(document.querySelector(selector)); },
    getAll(selector) { return Array.from(document.querySelectorAll(selector), expose); },
    create(tagName, namespace) {
      const node = namespace ? document.createElementNS(namespace, tagName) : document.createElement(tagName);
      return expose(node);
    },
    release(handle) {
      const id = handleNumber(handle);
      const node = id == null ? null : handles.get(id);
      if (!node) return false;
      handles.delete(id);
      reverseHandles.delete(node);
      return true;
    },
    text(target, value) { requireNode(target).textContent = value == null ? '' : String(value); return true; },
    html(target, value) { requireNode(target).innerHTML = value == null ? '' : String(value); return true; },
    value(target, value) {
      const node = requireNode(target);
      if (!('value' in node)) throw new Error('JX DOM target has no value property');
      if (arguments.length === 1) return node.value;
      node.value = value == null ? '' : value;
      return node.value;
    },
    attr(target, name, value) {
      const node = requireNode(target);
      if (arguments.length === 2) return node.getAttribute(name);
      if (value == null || value === false) node.removeAttribute(name);
      else node.setAttribute(name, value === true ? '' : String(value));
      return true;
    },
    prop(target, name, value) {
      const node = requireNode(target);
      if (arguments.length === 2) return node[name];
      node[name] = value;
      return true;
    },
    style(target, name, value) {
      const node = requireNode(target);
      if (!node.style) throw new Error('JX DOM target has no style object');
      if (value == null || value === '') node.style.removeProperty(name);
      else node.style.setProperty(name, String(value));
      return true;
    },
    classAdd(target, name) { requireNode(target).classList.add(name); return true; },
    classRemove(target, name) { requireNode(target).classList.remove(name); return true; },
    classToggle(target, name, force) {
      const node = requireNode(target);
      return arguments.length >= 3 ? node.classList.toggle(name, !!force) : node.classList.toggle(name);
    },
    append(parent, child) { requireNode(parent).appendChild(requireNode(child)); return true; },
    prepend(parent, child) { requireNode(parent).prepend(requireNode(child)); return true; },
    before(target, child) { requireNode(target).before(requireNode(child)); return true; },
    after(target, child) { requireNode(target).after(requireNode(child)); return true; },
    remove(target) { requireNode(target).remove(); return true; },
    clear(target) { requireNode(target).replaceChildren(); return true; },
    show(target, display) { requireNode(target).style.display = display || ''; return true; },
    hide(target) { requireNode(target).style.display = 'none'; return true; },
    focus(target) { const node = requireNode(target); if (typeof node.focus === 'function') node.focus(); return true; },
    on(target, type, callback, options) {
      const node = requireNode(target);
      if (typeof callback !== 'function') throw new Error('JX DOM event callback must be a function');
      const id = ++sequence;
      const wrapped = (event) => callback(eventPayload(event, node));
      node.addEventListener(type, wrapped, options || false);
      listeners.set(id, { node, type, wrapped, options: options || false });
      return id;
    },
    off(listenerId) {
      const id = handleNumber(listenerId) ?? listenerId;
      const listener = listeners.get(id);
      if (!listener) return false;
      listener.node.removeEventListener(listener.type, listener.wrapped, listener.options);
      listeners.delete(id);
      return true;
    },
  };

  function normalizePath(url) {
    const parsed = new URL(url, global.location.href);
    return parsed.pathname + parsed.search + parsed.hash;
  }

  function dispatchRoute(path, state, source) {
    const url = new URL(path, global.location.href);
    const exact = routes.get(url.pathname) || routes.get('*');
    if (!exact) return false;
    exact({ path: url.pathname, search: url.search, hash: url.hash, state: state == null ? global.history.state : state, source: source || 'navigate' });
    return true;
  }

  const router = {
    route(path, callback) { if (typeof callback !== 'function') throw new Error('JX route callback must be a function'); routes.set(path, callback); return true; },
    unroute(path) { return routes.delete(path); },
    go(url, state, replace) {
      const path = normalizePath(url);
      if (replace) global.history.replaceState(state || null, '', path);
      else global.history.pushState(state || null, '', path);
      dispatchRoute(path, state || null, replace ? 'replace' : 'push');
      return path;
    },
    replace(url, state) { return router.go(url, state, true); },
    current() { return global.location.pathname + global.location.search + global.location.hash; },
    dispatch() { return dispatchRoute(router.current(), global.history.state, 'dispatch'); },
  };

  function pasmHost(operation, args) {
    switch (String(operation).toUpperCase()) {
      case 'DOM.GET': return dom.get(String(args[0]));
      case 'DOM.CREATE': return dom.create(String(args[0]), args[1] == null ? null : String(args[1]));
      case 'DOM.RELEASE': return dom.release(args[0]);
      case 'DOM.TEXT': return dom.text(args[0], args[1]);
      case 'DOM.HTML': return dom.html(args[0], args[1]);
      case 'DOM.ATTR': return dom.attr(args[0], String(args[1]), args[2]);
      case 'DOM.PROP': return dom.prop(args[0], String(args[1]), args[2]);
      case 'DOM.STYLE': return dom.style(args[0], String(args[1]), args[2]);
      case 'DOM.CLASS.ADD': return dom.classAdd(args[0], String(args[1]));
      case 'DOM.CLASS.REMOVE': return dom.classRemove(args[0], String(args[1]));
      case 'DOM.CLASS.TOGGLE': return dom.classToggle(args[0], String(args[1]), args.length > 2 ? !!args[2] : undefined);
      case 'DOM.APPEND': return dom.append(args[0], args[1]);
      case 'DOM.PREPEND': return dom.prepend(args[0], args[1]);
      case 'DOM.BEFORE': return dom.before(args[0], args[1]);
      case 'DOM.AFTER': return dom.after(args[0], args[1]);
      case 'DOM.REMOVE': return dom.remove(args[0]);
      case 'DOM.CLEAR': return dom.clear(args[0]);
      case 'DOM.SHOW': return dom.show(args[0], args[1] == null ? '' : String(args[1]));
      case 'DOM.HIDE': return dom.hide(args[0]);
      case 'DOM.FOCUS': return dom.focus(args[0]);
      case 'ROUTE.GO': router.go(String(args[0]), null, false); return true;
      case 'ROUTE.REPLACE': router.replace(String(args[0]), null); return true;
      default: throw new Error(`Unsupported JX browser HOST operation ${operation}`);
    }
  }

  async function sendDrop(windowSpec, type, payload) {
    const drop = { version: VERSION, type, host: 'browser', window: windowSpec.window, book: windowSpec.book, leaf: windowSpec.leaf, sequence: ++sequence, payload };
    global.dispatchEvent(new CustomEvent('jx:drop', { detail: drop }));
    try {
      const response = await fetch(`/jx/drop?book=${encodeURIComponent(windowSpec.book)}`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(drop),
      });
      return response.ok;
    } catch (_) { return false; }
  }

  async function boot(program) {
    const windowSpec = JSON.parse(program.dataset.window);
    const output = document.querySelector(`[data-pasl-result="${CSS.escape(windowSpec.window)}"]`);
    try {
      const state = global.JxPasl.run(JSON.parse(program.textContent), 1000000, { host: pasmHost });
      const result = serial(state.result);
      if (output) output.textContent = String(result);
      await sendDrop(windowSpec, 'pasl.result', { result, steps: state.steps });
    } catch (error) {
      if (output) output.textContent = `PASL error: ${error.message}`;
      await sendDrop(windowSpec, 'pasl.error', { message: error.message });
    }
  }

  function interceptNavigation(event) {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const anchor = event.target && event.target.closest ? event.target.closest('a[data-jx-route]') : null;
    if (!anchor || anchor.target || anchor.hasAttribute('download')) return;
    const url = new URL(anchor.href, global.location.href);
    if (url.origin !== global.location.origin) return;
    event.preventDefault();
    router.go(url.href, null, false);
  }

  global.addEventListener('popstate', () => dispatchRoute(router.current(), global.history.state, 'pop'));
  document.addEventListener('click', interceptNavigation);

  global.JxBrowser = Object.freeze({
    version: VERSION,
    dom: Object.freeze(dom),
    router: Object.freeze(router),
    handle: expose,
    resolve,
    serial,
    pasmHost,
    sendDrop,
  });

  global.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('script[type="application/jx-pasl"]').forEach(boot);
    router.dispatch();
  });
})(globalThis);
