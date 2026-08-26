(function () {
  'use strict';

  const VERSION = 'jx.host/1';
  let sequence = 0;

  function serial(value) {
    return typeof value === 'bigint' ? value.toString() : value;
  }

  async function sendDrop(windowSpec, type, payload) {
    const drop = {
      version: VERSION,
      type,
      host: 'browser',
      window: windowSpec.window,
      book: windowSpec.book,
      leaf: windowSpec.leaf,
      sequence: ++sequence,
      payload,
    };
    window.dispatchEvent(new CustomEvent('jx:drop', { detail: drop }));
    try {
      const response = await fetch(`/jx/drop?book=${encodeURIComponent(windowSpec.book)}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(drop),
      });
      return response.ok;
    } catch (_) {
      return false;
    }
  }

  async function boot(program) {
    const windowSpec = JSON.parse(program.dataset.window);
    const output = document.querySelector(`[data-pasl-result="${CSS.escape(windowSpec.window)}"]`);
    try {
      const state = globalThis.JxPasl.run(JSON.parse(program.textContent));
      const result = serial(state.result);
      if (output) output.textContent = String(result);
      await sendDrop(windowSpec, 'pasl.result', { result, steps: state.steps });
    } catch (error) {
      if (output) output.textContent = `PASL error: ${error.message}`;
      await sendDrop(windowSpec, 'pasl.error', { message: error.message });
    }
  }

  window.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('script[type="application/jx-pasl"]').forEach(boot);
  });
})();
