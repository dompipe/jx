(function (global) {
  'use strict';

  const registers = ['ecx', 'ah', 'rdx', 'adx', 'bdx', 'cdx', 'cl'];

  function tokens(line) {
    const out = [];
    const re = /"(?:\\.|[^"\\])*"|'(?:\\.|[^'\\])*'|[^\s,]+/g;
    let match;
    while ((match = re.exec(line))) out.push(match[0]);
    return out;
  }

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
      code.push(tokens(line));
    }
    return { code, labels };
  }

  function decodeLiteral(token) {
    if (token == null) return null;
    if (token[0] === '"') return JSON.parse(token);
    if (token[0] === "'") {
      return token.slice(1, -1).replace(/\\'/g, "'").replace(/\\\\/g, '\\');
    }
    if (token === 'true') return true;
    if (token === 'false') return false;
    if (token === 'null') return null;
    if (/^-?\d+$/.test(token)) return BigInt(token);
    return token;
  }

  function run(source, budget = 1000000, options = null) {
    const program = parse(source);
    const r = Object.fromEntries(registers.map((name) => [name, 0n]));
    let pc = 0;
    let steps = 0;
    let zero = false;
    let compare = 0;
    const host = typeof options === 'function'
      ? options
      : options && typeof options.host === 'function'
        ? options.host
        : null;

    const value = (token) => Object.prototype.hasOwnProperty.call(r, token)
      ? r[token]
      : decodeLiteral(token);
    const numeric = (token) => {
      const v = value(token);
      if (typeof v === 'bigint') return v;
      if (typeof v === 'number' && Number.isInteger(v)) return BigInt(v);
      throw new Error(`PASM numeric operand required: ${String(token)}`);
    };
    const writeHostResult = (register, result) => {
      if (!register || register === '_') return;
      if (!Object.prototype.hasOwnProperty.call(r, register)) throw new Error(`Unknown PASM register ${register}`);
      if (typeof result === 'bigint') r[register] = result;
      else if (typeof result === 'number' && Number.isFinite(result)) r[register] = BigInt(Math.trunc(result));
      else if (typeof result === 'boolean') r[register] = result ? 1n : 0n;
      else if (result == null) r[register] = 0n;
      else throw new Error(`PASM HOST result for ${register} is not numeric/handle compatible`);
    };
    const jump = (label) => {
      if (!program.labels.has(label)) throw new Error(`Unknown PASM label ${label}`);
      pc = program.labels.get(label);
    };

    while (pc < program.code.length) {
      if (++steps > budget) throw new Error('PASM instruction budget exceeded');
      const instruction = program.code[pc++];
      const [opRaw, a, b, c] = instruction;
      const op = opRaw.toUpperCase();
      switch (op) {
        case 'MOVI': r[a] = numeric(b); break;
        case 'MOVR': r[a] = numeric(b); break;
        case 'ADD': r[a] = numeric(b) + numeric(c); break;
        case 'SUB': r[a] = numeric(b) - numeric(c); break;
        case 'MUL': r[a] = numeric(b) * numeric(c); break;
        case 'DIV': if (numeric(c) === 0n) throw new Error('Division by zero'); r[a] = numeric(b) / numeric(c); break;
        case 'MOD': if (numeric(c) === 0n) throw new Error('Modulo by zero'); r[a] = numeric(b) % numeric(c); break;
        case 'AND': r[a] = numeric(b) & numeric(c); break;
        case 'OR': r[a] = numeric(b) | numeric(c); break;
        case 'XOR': r[a] = numeric(b) ^ numeric(c); break;
        case 'SHL': r[a] = numeric(b) << numeric(c); break;
        case 'SHR': r[a] = numeric(b) >> numeric(c); break;
        case 'INC': r[a] = numeric(a) + 1n; break;
        case 'DEC': r[a] = numeric(a) - 1n; break;
        case 'NEG': r[a] = -numeric(a); break;
        case 'JMP': jump(a); break;
        case 'CMP': {
          const av = numeric(a), bv = numeric(b);
          compare = av < bv ? -1 : (av > bv ? 1 : 0);
          zero = compare === 0;
          break;
        }
        case 'JNZ': if (!zero) jump(a); break;
        case 'JZ': if (zero) jump(a); break;
        case 'JL': if (compare < 0) jump(a); break;
        case 'JLE': if (compare <= 0) jump(a); break;
        case 'JG': if (compare > 0) jump(a); break;
        case 'JGE': if (compare >= 0) jump(a); break;
        case 'HOST': {
          if (!host) throw new Error('PASM HOST instruction requires a browser host');
          const destination = a;
          const operation = b;
          if (!operation) throw new Error('PASM HOST instruction requires an operation');
          const args = instruction.slice(3).map(value);
          const result = host(operation, args, { registers: r, pc: pc - 1, steps });
          if (result && typeof result.then === 'function') throw new Error('PASM HOST calls must be synchronous');
          writeHostResult(destination, result);
          break;
        }
        case 'RET': return { result: numeric(a), steps, registers: r };
        case 'HALT': return { result: 0n, steps, registers: r };
        default: throw new Error(`Unsupported browser PASM opcode ${op}`);
      }
    }
    return { result: 0n, steps, registers: r };
  }

  global.JxPasl = { parse, run, decodeLiteral };
})(globalThis);
