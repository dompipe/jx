/* JX Anatomy IK host addon: deterministic FABRIK chains for jx.anatomy/2.
 *
 * A chain is defined by joint part ids and optional bone part ids. Dragging the
 * end target solves all joints behind it while preserving segment lengths.
 * No AI, GPU compute, or remote service is required.
 */
(function (global) {
  'use strict';

  function num(v, f) { v = Number(v); return Number.isFinite(v) ? v : f; }
  function clamp(v, a, b) { return Math.max(a, Math.min(b, num(v, a))); }

  function worldPosition(obj, out) {
    out = out || new global.THREE.Vector3();
    obj.updateMatrixWorld(true);
    return obj.getWorldPosition(out);
  }

  function setWorldPosition(obj, world) {
    const p = world.clone();
    if (obj.parent) obj.parent.worldToLocal(p);
    obj.position.copy(p);
    obj.updateMatrixWorld(true);
  }

  function safeDirection(from, to, fallback) {
    const d = to.clone().sub(from);
    if (d.lengthSq() < 1e-12) return (fallback || new global.THREE.Vector3(0, 1, 0)).clone().normalize();
    return d.normalize();
  }

  function distanceToSegment(p, a, b) {
    const ab = b.clone().sub(a), den = ab.lengthSq();
    if (den < 1e-12) return p.distanceTo(a);
    const t = clamp(p.clone().sub(a).dot(ab) / den, 0, 1);
    return p.distanceTo(a.clone().addScaledVector(ab, t));
  }

  function applyPole(points, pole, lengths) {
    if (!pole || points.length < 3) return;
    const THREE = global.THREE;
    for (let i = 1; i < points.length - 1; i++) {
      const prev = points[i - 1], next = points[i + 1];
      const axis = safeDirection(prev, next, new THREE.Vector3(0, 1, 0));
      const jointVec = points[i].clone().sub(prev);
      const poleVec = pole.clone().sub(prev);
      const jointProj = jointVec.clone().sub(axis.clone().multiplyScalar(jointVec.dot(axis)));
      const poleProj = poleVec.clone().sub(axis.clone().multiplyScalar(poleVec.dot(axis)));
      if (jointProj.lengthSq() < 1e-12 || poleProj.lengthSq() < 1e-12) continue;
      jointProj.normalize(); poleProj.normalize();
      let angle = Math.acos(clamp(jointProj.dot(poleProj), -1, 1));
      const sign = Math.sign(axis.dot(jointProj.clone().cross(poleProj))) || 1;
      angle *= sign;
      const q = new THREE.Quaternion().setFromAxisAngle(axis, angle);
      const moved = points[i].clone().sub(prev).applyQuaternion(q).add(prev);
      points[i].copy(moved);

      // Re-enforce the two adjacent segment lengths after pole correction.
      points[i].copy(prev.clone().add(safeDirection(prev, points[i]).multiplyScalar(lengths[i - 1])));
      points[i + 1].copy(points[i].clone().add(safeDirection(points[i], points[i + 1]).multiplyScalar(lengths[i])));
    }
  }

  function solveFABRIK(initial, lengths, target, opts) {
    opts = opts || {};
    const points = initial.map(p => p.clone());
    const root = points[0].clone();
    const iterations = Math.max(1, Math.min(64, Math.round(num(opts.iterations, 12))));
    const tolerance = Math.max(1e-7, num(opts.tolerance, 0.0005));
    const total = lengths.reduce((a, b) => a + b, 0);
    const rootToTarget = root.distanceTo(target);

    if (rootToTarget >= total - 1e-9) {
      const dir = safeDirection(root, target);
      points[0].copy(root);
      for (let i = 0; i < lengths.length; i++) {
        points[i + 1].copy(points[i]).addScaledVector(dir, lengths[i]);
      }
      return points;
    }

    for (let pass = 0; pass < iterations; pass++) {
      points[points.length - 1].copy(target);
      for (let i = points.length - 2; i >= 0; i--) {
        const dir = safeDirection(points[i + 1], points[i]);
        points[i].copy(points[i + 1]).addScaledVector(dir, lengths[i]);
      }

      points[0].copy(root);
      for (let i = 0; i < points.length - 1; i++) {
        const dir = safeDirection(points[i], points[i + 1]);
        points[i + 1].copy(points[i]).addScaledVector(dir, lengths[i]);
      }

      if (opts.pole) applyPole(points, opts.pole, lengths);
      if (points[points.length - 1].distanceTo(target) <= tolerance) break;
    }
    return points;
  }

  function orientBone(obj, a, b, lengthScale) {
    const THREE = global.THREE;
    if (!obj) return;
    const mid = a.clone().add(b).multiplyScalar(0.5);
    setWorldPosition(obj, mid);

    const dir = safeDirection(a, b);
    const parentQ = new THREE.Quaternion();
    if (obj.parent) obj.parent.getWorldQuaternion(parentQ);
    const worldQ = new THREE.Quaternion().setFromUnitVectors(new THREE.Vector3(0, 1, 0), dir);
    obj.quaternion.copy(parentQ.invert().multiply(worldQ));

    if (lengthScale !== false && obj.geometry && obj.geometry.parameters) {
      const base = num(obj.userData.jxBaseLength, num(obj.geometry.parameters.height, 1));
      if (!obj.userData.jxBaseLength) obj.userData.jxBaseLength = base;
      obj.scale.y = a.distanceTo(b) / Math.max(1e-9, base);
    }
    obj.updateMatrixWorld(true);
  }

  function makeChain(host, spec) {
    if (!host || !host.objects) throw new Error('IK chain requires a mounted JX Anatomy host');
    spec = spec || {};
    const ids = Array.isArray(spec.joints) ? spec.joints.slice() : [];
    if (ids.length < 2) throw new Error('IK chain needs at least two joint ids');
    const joints = ids.map(id => {
      const obj = host.objects.get(id);
      if (!obj) throw new Error('Unknown IK joint part: ' + id);
      return obj;
    });
    const bones = (Array.isArray(spec.bones) ? spec.bones : []).map(id => host.objects.get(id) || null);
    const initial = joints.map(j => worldPosition(j));
    const lengths = [];
    for (let i = 0; i < initial.length - 1; i++) lengths.push(initial[i].distanceTo(initial[i + 1]));

    const state = {
      id: String(spec.id || ids.join('-')),
      jointIds: ids,
      joints,
      boneIds: Array.isArray(spec.bones) ? spec.bones.slice() : [],
      bones,
      lengths,
      rootLocked: spec.rootLocked !== false,
      iterations: Math.max(1, Math.min(64, Math.round(num(spec.iterations, 14)))),
      tolerance: Math.max(1e-7, num(spec.tolerance, 0.0004)),
      pole: null,
      lastTarget: initial[initial.length - 1].clone()
    };

    if (Array.isArray(spec.pole) && spec.pole.length >= 3) {
      state.pole = new global.THREE.Vector3(num(spec.pole[0], 0), num(spec.pole[1], 0), num(spec.pole[2], 0));
    }

    state.solve = function (target, options) {
      options = options || {};
      const current = state.joints.map(j => worldPosition(j));
      const t = target && target.isVector3 ? target.clone() : new global.THREE.Vector3(
        num(target && target[0], current[current.length - 1].x),
        num(target && target[1], current[current.length - 1].y),
        num(target && target[2], current[current.length - 1].z)
      );
      const pole = options.pole && options.pole.isVector3 ? options.pole : state.pole;
      const solved = solveFABRIK(current, state.lengths, t, {
        iterations: options.iterations || state.iterations,
        tolerance: options.tolerance || state.tolerance,
        pole
      });

      for (let i = 0; i < state.joints.length; i++) setWorldPosition(state.joints[i], solved[i]);
      for (let i = 0; i < state.bones.length; i++) if (state.bones[i]) orientBone(state.bones[i], solved[i], solved[i + 1], true);

      // Persist solved transforms back into ordinary JX part data so animation/export sees them.
      if (host.parts) {
        for (let i = 0; i < state.jointIds.length; i++) {
          const part = host.parts.get(state.jointIds[i]);
          const obj = state.joints[i];
          if (part && obj) {
            part.transform = part.transform || {};
            part.transform.position = [obj.position.x, obj.position.y, obj.position.z];
            part.transform.rotation = [obj.rotation.x, obj.rotation.y, obj.rotation.z];
            part.transform.scale = [obj.scale.x, obj.scale.y, obj.scale.z];
          }
        }
        for (let i = 0; i < state.boneIds.length; i++) {
          const id = state.boneIds[i], part = host.parts.get(id), obj = state.bones[i];
          if (part && obj) {
            part.transform = part.transform || {};
            part.transform.position = [obj.position.x, obj.position.y, obj.position.z];
            part.transform.rotation = [obj.rotation.x, obj.rotation.y, obj.rotation.z];
            part.transform.scale = [obj.scale.x, obj.scale.y, obj.scale.z];
          }
        }
      }

      state.lastTarget.copy(t);
      return solved.map(p => [p.x, p.y, p.z]);
    };

    state.endWorldPosition = function () { return worldPosition(state.joints[state.joints.length - 1]); };
    state.distanceTo = function (p) {
      const points = state.joints.map(j => worldPosition(j));
      let best = Infinity;
      for (let i = 0; i < points.length - 1; i++) best = Math.min(best, distanceToSegment(p, points[i], points[i + 1]));
      return best;
    };
    return state;
  }

  function attach(host, chainSpecs) {
    const chains = new Map();
    (Array.isArray(chainSpecs) ? chainSpecs : []).forEach(spec => {
      const c = makeChain(host, spec); chains.set(c.id, c);
    });
    const api = {
      chains,
      add(spec) { const c = makeChain(host, spec); chains.set(c.id, c); return c; },
      get(id) { return chains.get(id) || null; },
      solve(id, target, opts) { const c = chains.get(id); return c ? c.solve(target, opts) : null; },
      remove(id) { return chains.delete(id); },
      describe() {
        return Array.from(chains.values()).map(c => ({
          id:c.id, joints:c.jointIds.slice(), bones:c.boneIds.slice(), lengths:c.lengths.slice(),
          iterations:c.iterations, tolerance:c.tolerance,
          pole:c.pole ? [c.pole.x,c.pole.y,c.pole.z] : null
        }));
      }
    };
    host.ik = api;
    return api;
  }

  const api = { attach, makeChain, solveFABRIK };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  global.JXAnatomyIK = api;
})(typeof window !== 'undefined' ? window : globalThis);
