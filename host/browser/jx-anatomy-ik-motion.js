/* JX Anatomy IK motion: record a hand/paw/foot target path, clean it, and
 * re-solve the IK chain on every playback frame. Limb lengths therefore stay
 * exact even after smoothing or linearization.
 */
(function (global) {
  'use strict';

  function n(v, f) { v = Number(v); return Number.isFinite(v) ? v : f; }
  function clamp(v, a, b) { return Math.max(a, Math.min(b, n(v, a))); }
  function clone(v) { return JSON.parse(JSON.stringify(v)); }
  function nowMs() { return global.performance && global.performance.now ? global.performance.now() : Date.now(); }

  function normalizeFrames(frames) {
    const out = [];
    (Array.isArray(frames) ? frames : []).forEach(f => {
      if (!f) return;
      const p = Array.isArray(f.position) ? f.position : [0,0,0];
      out.push({
        time: Math.max(0, n(f.time, 0)),
        position: [n(p[0],0), n(p[1],0), n(p[2],0)]
      });
    });
    out.sort((a,b) => a.time - b.time);
    return out;
  }

  function smoothFrames(frames, smooth, linearize, passes) {
    let out = normalizeFrames(frames);
    smooth = clamp(smooth, 0, 1);
    linearize = clamp(linearize, 0, 1);
    passes = Math.max(0, Math.min(16, Math.round(n(passes, 3))));
    if (out.length < 3) return out;

    for (let pass = 0; pass < passes; pass++) {
      const before = clone(out);
      for (let i = 1; i < out.length - 1; i++) {
        for (let axis = 0; axis < 3; axis++) {
          const a = before[i-1].position[axis], b = before[i].position[axis], c = before[i+1].position[axis];
          const filtered = (a + 2*b + c) / 4;
          out[i].position[axis] = b + (filtered - b) * smooth;
        }
      }
    }

    if (linearize > 0) {
      const first = out[0], last = out[out.length - 1];
      const span = Math.max(1e-9, last.time - first.time);
      for (let i = 1; i < out.length - 1; i++) {
        const u = clamp((out[i].time - first.time) / span, 0, 1);
        for (let axis = 0; axis < 3; axis++) {
          const line = first.position[axis] + (last.position[axis] - first.position[axis]) * u;
          out[i].position[axis] += (line - out[i].position[axis]) * linearize;
        }
      }
    }
    return out;
  }

  function sample(frames, time) {
    if (!frames || !frames.length) return null;
    if (time <= frames[0].time) return frames[0].position.slice();
    const last = frames[frames.length - 1];
    if (time >= last.time) return last.position.slice();
    let lo = 0, hi = frames.length - 1;
    while (hi - lo > 1) {
      const mid = (lo + hi) >> 1;
      if (frames[mid].time <= time) lo = mid; else hi = mid;
    }
    const a = frames[lo], b = frames[hi];
    const u = clamp((time - a.time) / Math.max(1e-9, b.time - a.time), 0, 1);
    return [
      a.position[0] + (b.position[0] - a.position[0]) * u,
      a.position[1] + (b.position[1] - a.position[1]) * u,
      a.position[2] + (b.position[2] - a.position[2]) * u
    ];
  }

  function attach(ik) {
    if (!ik || !ik.get || !ik.solve) throw new Error('IK motion requires JXAnatomyIK.attach(...) result');
    const clips = new Map();
    let recording = null, playing = null;

    function begin(chainId, opts) {
      opts = opts || {};
      const chain = ik.get(chainId);
      if (!chain) throw new Error('Unknown IK chain: ' + chainId);
      stop();
      const t0 = n(opts.now, nowMs());
      const p = chain.endWorldPosition();
      recording = {
        id: String(opts.id || ('ik-motion-' + chainId + '-' + Math.floor(t0))),
        chain: chainId,
        startedAt: t0,
        maxFrames: Math.max(2, Math.min(32768, Math.round(n(opts.maxFrames, 8192)))),
        frames: [{time:0, position:[p.x,p.y,p.z]}]
      };
      return recording.id;
    }

    function record(chainId, target, at) {
      if (!recording || recording.chain !== chainId || recording.frames.length >= recording.maxFrames) return false;
      const t = Math.max(0, (n(at, nowMs()) - recording.startedAt) / 1000);
      const last = recording.frames[recording.frames.length - 1];
      if (last && t - last.time < 0.004) return false;
      const p = target && target.isVector3 ? [target.x,target.y,target.z] : [n(target&&target[0],0),n(target&&target[1],0),n(target&&target[2],0)];
      recording.frames.push({time:t,position:p});
      return true;
    }

    function finish(opts) {
      opts = opts || {};
      if (!recording) return null;
      if (recording.frames.length === 1) recording.frames.push({time:.001,position:recording.frames[0].position.slice()});
      const raw = normalizeFrames(recording.frames);
      const settings = {
        smooth: clamp(opts.smooth,0,1),
        linearize: clamp(opts.linearize,0,1),
        passes: Math.max(0,Math.min(16,Math.round(n(opts.passes,3))))
      };
      const keyframes = smoothFrames(raw,settings.smooth,settings.linearize,settings.passes);
      const clip = {
        id: recording.id,
        kind: 'ik-motion',
        chain: recording.chain,
        duration: Math.max(.001,keyframes[keyframes.length-1].time),
        loop: !!opts.loop,
        rawKeyframes: raw,
        keyframes,
        settings
      };
      clips.set(clip.id,clip);
      recording = null;
      return clone(clip);
    }

    function resmooth(id, smooth, linearize, passes) {
      const clip = clips.get(id); if (!clip) return false;
      clip.settings = {
        smooth: clamp(smooth,0,1),
        linearize: clamp(linearize,0,1),
        passes: Math.max(0,Math.min(16,Math.round(n(passes,3))))
      };
      clip.keyframes = smoothFrames(clip.rawKeyframes,clip.settings.smooth,clip.settings.linearize,clip.settings.passes);
      clip.duration = Math.max(.001,clip.keyframes[clip.keyframes.length-1].time);
      return true;
    }

    function play(id, opts) {
      opts = opts || {};
      const clip = clips.get(id); if (!clip) return false;
      playing = {
        id,
        startedAt: n(opts.now,nowMs()),
        speed: Math.max(.01,n(opts.speed,1)),
        loop: opts.loop === undefined ? !!clip.loop : !!opts.loop
      };
      return true;
    }

    function update(at) {
      if (!playing) return false;
      const clip = clips.get(playing.id); if (!clip) { playing=null; return false; }
      let t = Math.max(0,(n(at,nowMs()) - playing.startedAt)/1000 * playing.speed);
      if (playing.loop) t = t % clip.duration;
      else if (t >= clip.duration) { t = clip.duration; }
      const p = sample(clip.keyframes,t);
      if (p) ik.solve(clip.chain,p);
      if (!playing.loop && t >= clip.duration) playing = null;
      return true;
    }

    function stop() { playing = null; }
    function cancel() { recording = null; }
    function get(id) { const c=clips.get(id); return c ? clone(c) : null; }
    function list() { return Array.from(clips.values()).map(clone); }
    function importClip(clip) {
      if (!clip || !clip.id || !clip.chain) throw new Error('Invalid IK motion clip');
      const c = clone(clip);
      c.rawKeyframes = normalizeFrames(c.rawKeyframes || c.keyframes || []);
      c.keyframes = normalizeFrames(c.keyframes || c.rawKeyframes);
      c.duration = Math.max(.001,n(c.duration,c.keyframes.length ? c.keyframes[c.keyframes.length-1].time : .001));
      clips.set(c.id,c); return c.id;
    }

    return {clips,begin,record,finish,resmooth,play,update,stop,cancel,get,list,importClip,smoothFrames,sample};
  }

  const api = {attach,smoothFrames,sample};
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  global.JXAnatomyIKMotion = api;
})(typeof window !== 'undefined' ? window : globalThis);
