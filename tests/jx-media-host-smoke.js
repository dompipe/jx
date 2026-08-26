'use strict';

class FakeEvent {
  constructor(type, init = {}) {
    this.type = type;
    this.detail = init.detail;
    this.bubbles = Boolean(init.bubbles);
    this.target = null;
    this.currentTarget = null;
  }
}

global.CustomEvent = FakeEvent;

class FakeElement {
  constructor(tag, doc) {
    this.tagName = String(tag).toUpperCase();
    this.ownerDocument = doc;
    this.listeners = new Map();
    this.dataset = {};
    this.parentNode = null;
    this.children = [];
    this.id = '';
    this.value = '';
    this.checked = false;
  }
  addEventListener(type, fn) {
    if (!this.listeners.has(type)) this.listeners.set(type, new Set());
    this.listeners.get(type).add(fn);
  }
  removeEventListener(type, fn) {
    this.listeners.get(type)?.delete(fn);
  }
  dispatchEvent(event) {
    event.target = event.target || this;
    event.currentTarget = this;
    for (const fn of this.listeners.get(event.type) || []) fn(event);
    return true;
  }
  appendChild(child) {
    child.parentNode = this;
    this.children.push(child);
    if (child.id) this.ownerDocument.byId.set(child.id, child);
    return child;
  }
  removeChild(child) {
    this.children = this.children.filter(x => x !== child);
    if (child.id) this.ownerDocument.byId.delete(child.id);
    child.parentNode = null;
  }
  removeAttribute(name) {
    if (name === 'src') this.src = '';
  }
}

class FakeMedia extends FakeElement {
  constructor(tag, doc) {
    super(tag, doc);
    this.src = '';
    this.controls = false;
    this.loop = false;
    this.muted = false;
    this.volume = 1;
    this.currentTime = 0;
    this.duration = 300;
    this.paused = true;
    this.playbackRate = 1;
    this.readyState = 1;
    this.playCalls = 0;
    this.pauseCalls = 0;
    this.loadCalls = 0;
  }
  play() {
    this.playCalls++;
    this.paused = false;
    return Promise.resolve();
  }
  pause() {
    this.pauseCalls++;
    this.paused = true;
  }
  load() { this.loadCalls++; }
}

class FakeDocument {
  constructor() {
    this.byId = new Map();
    this.body = new FakeElement('body', this);
  }
  createElement(tag) {
    if (tag === 'audio' || tag === 'video') return new FakeMedia(tag, this);
    return new FakeElement(tag, this);
  }
  getElementById(id) { return this.byId.get(id) || null; }
}

function assert(ok, message) {
  if (!ok) throw new Error('jx-media-host-smoke failed: ' + message);
}

const document = new FakeDocument();
global.document = document;

function control(id, fields = {}) {
  const el = document.createElement('input');
  el.id = id;
  Object.assign(el, fields);
  document.byId.set(id, el);
  document.body.appendChild(el);
  return el;
}

const play = control('play-button');
const volume = control('volume-control', { value: '0.42' });
const scrubber = control('scrubber', { value: '91.5' });
const mute = control('mute-toggle', { checked: true });

const host = require('../host/browser/jx-media-host.js');

const descriptor = {
  kind: 'control',
  control: 'media',
  plugin: 'media',
  version: 'jx.media/1',
  id: 'music',
  type: 'audio',
  mime: 'audio/mpeg',
  source: { kind: 'asset', uri: '/music/song.mp3' },
  with: { controls: true, volume: 0.75 },
  controlBindings: [
    {
      kind: 'control-binding', id: '111111111111111111111111',
      from: { control: 'play-button', event: 'click' },
      to: { control: 'music', action: 'play' }, with: { as: 'raw' },
    },
    {
      kind: 'control-binding', id: '222222222222222222222222',
      from: { control: 'volume-control', event: 'change', value: 'value' },
      to: { control: 'music', action: 'volume' }, with: { as: 'float' },
    },
    {
      kind: 'control-binding', id: '333333333333333333333333',
      from: { control: 'scrubber', event: 'change', value: 'value' },
      to: { control: 'music', action: 'seek' }, with: { as: 'float' },
    },
    {
      kind: 'control-binding', id: '444444444444444444444444',
      from: { control: 'mute-toggle', event: 'change', value: 'checked' },
      to: { control: 'music', action: 'muted' }, with: { as: 'boolean' },
    },
  ],
};

const mounted = host.mount(descriptor, document.body);
const media = mounted.element;
assert(media.tagName === 'AUDIO', 'mounted audio element');
assert(media.controls === true, 'native controls enabled');
assert(media.volume === 0.75, 'initial volume applied');

play.dispatchEvent(new FakeEvent('click'));
assert(media.playCalls === 1 && media.paused === false, 'control click plays media');

volume.dispatchEvent(new FakeEvent('change'));
assert(Math.abs(media.volume - 0.42) < 1e-9, 'control value changes volume');

scrubber.dispatchEvent(new FakeEvent('change'));
assert(Math.abs(media.currentTime - 91.5) < 1e-9, 'control value seeks media');

mute.dispatchEvent(new FakeEvent('change'));
assert(media.muted === true, 'control checked state mutes media');

mounted.unmount();
const previousPlayCalls = media.playCalls;
play.dispatchEvent(new FakeEvent('click'));
assert(media.playCalls === previousPlayCalls, 'unmount removes control listeners');

let rejected = false;
try { host.safeUri('javascript:alert(1)'); } catch (_) { rejected = true; }
assert(rejected, 'executable media URI rejected');

console.log('jx-media-host-smoke: ok');
