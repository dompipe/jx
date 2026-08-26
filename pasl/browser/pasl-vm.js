(function (global) {
  'use strict';

  const registers = ['ecx', 'ah', 'rdx', 'adx', 'bdx', 'cdx', 'cl'];

  function parse(source) {
    const labels = new Map();
    const code = [];
    for (const raw of source.split(/\r?\n/)) {
      const line = raw.replace(/;.*/, '').trim();
      if (!line) continue;
      if (line.endsWith(':')) {
        labels.set(line.slice(0, -1), code.length);
        continue;
      }
      code.push(line.split(/[\s,]+/));
    }
    return { code, labels };
  }

  function run(source, budget = 1000000) {
    const program = parse(source);
    const r = Object.fromEntries(registers.map((name) => [name, 0n]));
    let pc = 0;
    let steps = 0;
    let zero = false;

    const value = (token) => Object.prototype.hasOwnProperty.call(r, token)
      ? r[token]
      : BigInt(token);
    const jump = (label) => {
      if (!program.labels.has(label)) throw new Error(`Unknown PASM label ${label}`);
      pc = program.labels.get(label);
    };

    while (pc < program.code.length) {
      if (++steps > budget) throw new Error('PASM instruction budget exceeded');
      const [opRaw, a, b, c] = program.code[pc++];
      const op = opRaw.toUpperCase();
      switch (op) {
        case 'MOVI': r[a] = value(b); break;
        case 'MOVR': r[a] = value(b); break;
        case 'ADD': r[a] = value(b) + value(c); break;
        case 'SUB': r[a] = value(b) - value(c); break;
        case 'MUL': r[a] = value(b) * value(c); break;
        case 'DIV': if (value(c) === 0n) throw new Error('Division by zero'); r[a] = value(b) / value(c); break;
        case 'MOD': if (value(c) === 0n) throw new Error('Modulo by zero'); r[a] = value(b) % value(c); break;
        case 'AND': r[a] = value(b) & value(c); break;
        case 'OR': r[a] = value(b) | value(c); break;
        case 'XOR': r[a] = value(b) ^ value(c); break;
        case 'SHL': r[a] = value(b) << value(c); break;
        case 'SHR': r[a] = value(b) >> value(c); break;
        case 'INC': r[a] = value(a) + 1n; break;
        case 'DEC': r[a] = value(a) - 1n; break;
        case 'NEG': r[a] = -value(a); break;
        case 'JMP': jump(a); break;
        case 'CMP': zero = value(a) === value(b); break;
        case 'JNZ': if (!zero) jump(a); break;
        case 'JZ': if (zero) jump(a); break;
        case 'RET': return { result: value(a), steps, registers: r };
        case 'HALT': return { result: 0n, steps, registers: r };
        default: throw new Error(`Unsupported browser PASM opcode ${op}`);
      }
    }
    return { result: 0n, steps, registers: r };
  }

  global.JxPasl = { parse, run };
})(globalThis);
