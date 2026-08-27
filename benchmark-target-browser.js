'use strict';

const fs = require('fs');
const { performance } = require('perf_hooks');
require('./pasl/browser/pasl-vm.js');

const asmPath = process.argv[2];
const reps = Number(process.argv[3] || 9);
const expected = BigInt(process.argv[4] || '49995000');
if (!asmPath) throw new Error('usage: node benchmark-target-browser.js program.pasm [reps] [expected]');

const source = fs.readFileSync(asmPath, 'utf8');
for (let i = 0; i < 3; i++) {
  const warm = globalThis.JxPasl.run(source);
  if (warm.result !== expected) throw new Error(`browser result mismatch ${warm.result} != ${expected}`);
}

const samples = [];
let steps = 0;
for (let i = 0; i < reps; i++) {
  const t0 = performance.now();
  const out = globalThis.JxPasl.run(source);
  const t1 = performance.now();
  if (out.result !== expected) throw new Error(`browser result mismatch ${out.result} != ${expected}`);
  steps = out.steps;
  samples.push(t1 - t0);
}
samples.sort((a,b)=>a-b);
const median = samples[Math.floor(samples.length / 2)];
process.stdout.write(JSON.stringify({target:'browser-js-pasm',median_ms:median,steps,result:expected.toString(),reps}) + '\n');
