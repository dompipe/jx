'use strict';

const host = require('../host/browser/jx-audio-signal-host.js');

function assert(ok, message) { if (!ok) throw new Error('jx-audio-signal-host-smoke failed: ' + message); }

const sr = 48000;
const hz = 440;
const n = 4096;
const tone = new Float32Array(n);
for (let i = 0; i < n; i++) tone[i] = Math.sin(2 * Math.PI * hz * i / sr) * 0.5;

const level = host.levelOf(tone);
assert(level.peak > 0.49 && level.peak <= 0.501, 'peak amplitude');
assert(level.rms > 0.34 && level.rms < 0.36, 'RMS amplitude');
assert(Number.isFinite(level.db) && level.db < 0, 'dB level');

const pitch = host.autoPitch(tone, sr);
assert(pitch.confidence > 0.8, 'pitch confidence');
assert(Math.abs(pitch.hz - 440) < 6, '440 Hz pitch estimate');
assert(host.noteName(440) === 'A4', '440 Hz is A4');
assert(host.noteName(261.63) === 'C4', 'middle C note name');

console.log('jx-audio-signal-host-smoke: ok');
