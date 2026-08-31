/* JX browser Anatomy host: jx.anatomy/1
 * Requires THREE to render. Geometry and texture state remain ordinary JX data.
 */
(function (global) {
  'use strict';

  function n(v, fallback) { v = Number(v); return Number.isFinite(v) ? v : fallback; }
  function vec3(v, fallback) { v = Array.isArray(v) ? v : []; return [n(v[0], fallback[0]), n(v[1], fallback[1]), n(v[2], fallback[2])]; }
  function clone(v) { return JSON.parse(JSON.stringify(v)); }

  function applyTransform(obj, t) {
    t = t || {};
    const p = vec3(t.position, [0,0,0]), r = vec3(t.rotation,[0,0,0]), s = vec3(t.scale,[1,1,1]);
    obj.position.set(p[0],p[1],p[2]); obj.rotation.set(r[0],r[1],r[2]); obj.scale.set(s[0],s[1],s[2]);
  }

  function applyTextureTransform(tex, tr) {
    if (!tex) return;
    tr = tr || {};
    const off = Array.isArray(tr.offset) ? tr.offset : [0,0];
    const scale = Array.isArray(tr.scale) ? tr.scale : [1,1];
    const pivot = Array.isArray(tr.pivot) ? tr.pivot : [.5,.5];
    tex.wrapS = tex.wrapT = global.THREE.RepeatWrapping;
    tex.offset.set(n(off[0],0), n(off[1],0));
    tex.repeat.set(n(scale[0],1), n(scale[1],1));
    tex.center.set(n(pivot[0],.5), n(pivot[1],.5));
    tex.rotation = n(tr.rotation,0);
    tex.needsUpdate = true;
  }

  function materialFor(part, options) {
    const THREE = global.THREE;
    const spec = Array.isArray(part.textures) ? part.textures[0] : null;
    const mat = new THREE.MeshStandardMaterial({ roughness:.72, metalness:0.0 });
    if (!spec) { mat.color.set(part.type === 'joint' ? 0xb7c3cf : 0xe4d2b7); return mat; }
    if (spec.mode === 'procedural') {
      const kind = String(spec.with && spec.with.kind || 'skin');
      const color = spec.with && spec.with.color;
      mat.color.set(color || (kind === 'bone' ? 0xe7dfc9 : kind === 'scale' ? 0x7d8970 : kind === 'fur' ? 0x8b725c : 0xc59678));
      mat.roughness = kind === 'scale' ? .56 : .78;
      return mat;
    }
    if ((spec.mode === 'uv' || spec.mode === 'project' || spec.mode === 'paint') && spec.source && options.textureLoader) {
      const tex = options.textureLoader(spec.source);
      applyTextureTransform(tex, spec.with && spec.with.transform);
      mat.map = tex;
    }
    return mat;
  }

  function geometryFor(part) {
    const THREE = global.THREE, p = part.params || {};
    const type = String(part.type || '').toLowerCase();
    if (type === 'joint' || type === 'ball-joint') {
      return new THREE.SphereGeometry(Math.max(.001,n(p.radius,.12)), Math.max(8,n(p.segments,18)), Math.max(6,n(p.rings,12)));
    }
    if (type === 'pipe' || type === 'bone' || type.indexOf('arm') >= 0 || type.indexOf('leg') >= 0 || type.indexOf('limb') >= 0) {
      const radius = Math.max(.001,n(p.radius,p.thickness || .1));
      const length = Math.max(.001,n(p.length,1));
      const pumped = Math.max(0,n(p.pumpedness,p.muscle || 0));
      const rr = radius * (1 + pumped * .45);
      return new THREE.CylinderGeometry(rr * n(p.taperEnd, .82), rr * n(p.taperStart,1), length, Math.max(8,n(p.radialSegments,20)), Math.max(1,n(p.lengthSegments,6)), false);
    }
    if (type.indexOf('torso') >= 0) return new THREE.SphereGeometry(.6,28,18);
    if (type.indexOf('skull') >= 0 || type.indexOf('head') >= 0) return new THREE.SphereGeometry(.34,28,20);
    if (type.indexOf('beak') >= 0 || type.indexOf('snout') >= 0 || type.indexOf('nose') >= 0) {
      const len = Math.max(.02,n(p.length,.5)), w = Math.max(.02,n(p.width,.22)), d = Math.max(.02,n(p.depth,.16));
      const g = new THREE.ConeGeometry(w, len, Math.max(12,n(p.radialSegments,20)), 6);
      g.rotateZ(-Math.PI/2); g.scale(1,d/w,1); return g;
    }
    return new THREE.BoxGeometry(.3,.3,.3);
  }

  function mount(descriptor, root, options) {
    options = options || {};
    const THREE = global.THREE;
    if (!THREE) throw new Error('JX Anatomy host requires THREE');
    if (!descriptor || descriptor.model !== 'anatomy') throw new Error('JX Anatomy host requires an anatomy descriptor');
    root = root || global.document.body;

    const canvas = options.canvas || global.document.createElement('canvas');
    if (!options.canvas) root.appendChild(canvas);
    const renderer = options.renderer || new THREE.WebGLRenderer({canvas, antialias:true, alpha:true});
    const scene = options.scene || new THREE.Scene();
    const camera = options.camera || new THREE.PerspectiveCamera(42,1,.01,1000);
    camera.position.set(0,1.4,4.5);
    if (!options.scene) {
      scene.add(new THREE.HemisphereLight(0xffffff,0x334455,2.0));
      const dl = new THREE.DirectionalLight(0xffffff,2.0); dl.position.set(3,5,4); scene.add(dl);
    }
    const loader = new THREE.TextureLoader();
    const hostOptions = Object.assign({}, options, { textureLoader: options.textureLoader || (src => loader.load(src)) });

    const modelRoot = new THREE.Group(); modelRoot.name = descriptor.id || 'anatomy'; scene.add(modelRoot);
    const objects = new Map(), parts = new Map();

    (descriptor.parts || []).forEach(part => {
      parts.set(part.id, clone(part));
      const mesh = new THREE.Mesh(geometryFor(part), materialFor(part,hostOptions));
      mesh.name = part.id; mesh.userData.jxPartId = part.id; applyTransform(mesh,part.transform); objects.set(part.id,mesh);
    });
    (descriptor.parts || []).forEach(part => {
      const mesh = objects.get(part.id), parent = part.parent && objects.get(part.parent);
      (parent || modelRoot).add(mesh);
    });

    let selected = null;
    function select(id) { selected = id && objects.has(id) ? id : null; return selected && parts.get(selected); }
    function selectedPart() { return selected ? parts.get(selected) : null; }

    function movePart(id, transform) {
      const part = parts.get(id), obj = objects.get(id); if (!part || !obj) return false;
      part.transform = Object.assign({},part.transform || {},clone(transform || {})); applyTransform(obj,part.transform); return true;
    }

    function alignTexture(id, textureId, transform) {
      const part = parts.get(id), obj = objects.get(id); if (!part || !obj) return false;
      const textures = Array.isArray(part.textures) ? part.textures : [];
      const spec = textures.find(t => t.id === textureId) || textures[0]; if (!spec) return false;
      spec.with = spec.with || {}; spec.with.transform = Object.assign({},spec.with.transform || {},clone(transform || {}));
      if (obj.material && obj.material.map) applyTextureTransform(obj.material.map,spec.with.transform);
      return true;
    }

    function pinTexture(id, textureId, pins) {
      const part = parts.get(id); if (!part) return false;
      const spec = (part.textures || []).find(t => t.id === textureId) || (part.textures || [])[0]; if (!spec) return false;
      spec.with = spec.with || {}; spec.with.pins = clone(pins || []); return true;
    }

    function setShape(id, params) {
      const part = parts.get(id), old = objects.get(id); if (!part || !old) return false;
      part.params = Object.assign({},part.params || {},clone(params || {}));
      const replacement = new THREE.Mesh(geometryFor(part),old.material); replacement.name=old.name; replacement.userData=old.userData; applyTransform(replacement,part.transform);
      while(old.children.length) replacement.add(old.children[0]);
      const parent=old.parent; parent.add(replacement); parent.remove(old); objects.set(id,replacement); old.geometry.dispose(); return true;
    }

    function resize() {
      const w = Math.max(1,options.width || root.clientWidth || 900), h = Math.max(1,options.height || root.clientHeight || 650);
      renderer.setSize(w,h,false); camera.aspect=w/h; camera.updateProjectionMatrix();
    }
    function render(){ resize(); renderer.render(scene,camera); }
    function exportDescriptor(){ return {kind:'model',model:'anatomy',plugin:'anatomy',version:descriptor.version||'jx.anatomy/1',id:descriptor.id,species:descriptor.species,parts:Array.from(parts.values()).map(clone)}; }

    return {canvas,renderer,scene,camera,root:modelRoot,objects,parts,select,selectedPart,movePart,alignTexture,pinTexture,setShape,render,exportDescriptor};
  }

  const api={mount,applyTextureTransform};
  if(typeof module!=='undefined'&&module.exports) module.exports=api;
  global.JXAnatomyHost=api;
})(typeof window!=='undefined'?window:globalThis);
