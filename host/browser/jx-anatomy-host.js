/* JX browser Anatomy host: jx.anatomy/2
 * Requires THREE to render. Geometry, textures, and animation remain ordinary JX data.
 */
(function (global) {
  'use strict';

  function n(v, fallback) { v = Number(v); return Number.isFinite(v) ? v : fallback; }
  function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, n(v, lo))); }
  function vec3(v, fallback) { v = Array.isArray(v) ? v : []; return [n(v[0], fallback[0]), n(v[1], fallback[1]), n(v[2], fallback[2])]; }
  function clone(v) { return JSON.parse(JSON.stringify(v)); }
  function normalizedTransform(t) {
    t = t || {};
    return { position: vec3(t.position,[0,0,0]), rotation: vec3(t.rotation,[0,0,0]), scale: vec3(t.scale,[1,1,1]) };
  }

  function applyTransform(obj, t) {
    t = normalizedTransform(t);
    obj.position.set(t.position[0],t.position[1],t.position[2]);
    obj.rotation.set(t.rotation[0],t.rotation[1],t.rotation[2]);
    obj.scale.set(t.scale[0],t.scale[1],t.scale[2]);
  }

  function captureTransform(obj) {
    return {
      position:[obj.position.x,obj.position.y,obj.position.z],
      rotation:[obj.rotation.x,obj.rotation.y,obj.rotation.z],
      scale:[obj.scale.x,obj.scale.y,obj.scale.z]
    };
  }

  function lerp(a,b,t) { return a + (b-a)*t; }
  function lerpTransform(a,b,t) {
    a=normalizedTransform(a); b=normalizedTransform(b); t=clamp(t,0,1);
    const out={position:[],rotation:[],scale:[]};
    ['position','rotation','scale'].forEach(field=>{
      for(let i=0;i<3;i++) out[field][i]=lerp(a[field][i],b[field][i],t);
    });
    return out;
  }

  function normalizeFrames(frames) {
    const out=[];
    (Array.isArray(frames)?frames:[]).forEach(frame=>{
      const time=Math.max(0,n(frame && frame.time,0));
      const transform=normalizedTransform(frame && frame.transform);
      out.push({time,transform});
    });
    out.sort((a,b)=>a.time-b.time);
    return out;
  }

  /* Deterministic low-pass path cleanup. Endpoints are never moved. */
  function smoothFrames(frames, smooth, linearize, passes) {
    let out=normalizeFrames(frames); smooth=clamp(smooth,0,1); linearize=clamp(linearize,0,1); passes=Math.max(0,Math.min(12,Math.round(n(passes,2))));
    if(out.length<3) return out;
    for(let pass=0;pass<passes;pass++) {
      const before=clone(out);
      for(let i=1;i<out.length-1;i++) {
        ['position','rotation','scale'].forEach(field=>{
          for(let axis=0;axis<3;axis++) {
            const a=before[i-1].transform[field][axis], b=before[i].transform[field][axis], c=before[i+1].transform[field][axis];
            const filtered=(a+2*b+c)/4;
            out[i].transform[field][axis]=b+(filtered-b)*smooth;
          }
        });
      }
    }
    if(linearize>0) {
      const first=out[0], last=out[out.length-1], span=Math.max(1e-9,last.time-first.time);
      for(let i=1;i<out.length-1;i++) {
        const u=clamp((out[i].time-first.time)/span,0,1);
        ['position','rotation','scale'].forEach(field=>{
          for(let axis=0;axis<3;axis++) {
            const line=lerp(first.transform[field][axis],last.transform[field][axis],u);
            const v=out[i].transform[field][axis];
            out[i].transform[field][axis]=lerp(v,line,linearize);
          }
        });
      }
    }
    return out;
  }

  function frameAt(track, time) {
    const frames=track && track.keyframes || [];
    if(!frames.length) return null;
    if(time<=frames[0].time) return clone(frames[0].transform);
    const last=frames[frames.length-1]; if(time>=last.time) return clone(last.transform);
    let lo=0,hi=frames.length-1;
    while(hi-lo>1){const mid=(lo+hi)>>1;if(frames[mid].time<=time)lo=mid;else hi=mid;}
    const a=frames[lo],b=frames[hi],span=Math.max(1e-9,b.time-a.time),u=(time-a.time)/span;
    return lerpTransform(a.transform,b.transform,u);
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
    if (type.indexOf('beak') >= 0 || type.indexOf('snout') >= 0 || type.indexOf('nose') >= 0 || type.indexOf('bill') >= 0) {
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
    const objects = new Map(), parts = new Map(), animations = new Map();

    (descriptor.parts || []).forEach(part => {
      parts.set(part.id, clone(part));
      const mesh = new THREE.Mesh(geometryFor(part), materialFor(part,hostOptions));
      mesh.name = part.id; mesh.userData.jxPartId = part.id; applyTransform(mesh,part.transform); objects.set(part.id,mesh);
    });
    (descriptor.parts || []).forEach(part => {
      const mesh = objects.get(part.id), parent = part.parent && objects.get(part.parent);
      (parent || modelRoot).add(mesh);
    });
    (descriptor.animations || []).forEach(a=>animations.set(a.id,clone(a)));

    let selected = null, recording = null, playing = null;
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

    function beginPathRecording(partId, opts) {
      opts=opts||{}; if(!parts.has(partId)||!objects.has(partId)) return null;
      stopAnimation();
      const now=n(opts.now,global.performance && global.performance.now ? global.performance.now() : Date.now());
      recording={
        id:String(opts.id||('motion-'+partId+'-'+Math.floor(now))), part:partId, startedAt:now,
        maxFrames:Math.max(2,Math.min(16384,Math.round(n(opts.maxFrames,4096)))), frames:[]
      };
      recordPathPoint(partId,null,now);
      return recording.id;
    }

    function recordPathPoint(partId, transform, now) {
      if(!recording||recording.part!==partId||recording.frames.length>=recording.maxFrames) return false;
      const obj=objects.get(partId); if(!obj) return false;
      now=n(now,global.performance && global.performance.now ? global.performance.now() : Date.now());
      const tr=transform ? normalizedTransform(transform) : captureTransform(obj);
      const time=Math.max(0,(now-recording.startedAt)/1000);
      const last=recording.frames[recording.frames.length-1];
      if(last && time-last.time<0.004) return false;
      recording.frames.push({time,transform:tr});
      return true;
    }

    function finishPathRecording(opts) {
      opts=opts||{}; if(!recording) return null;
      if(recording.frames.length===1) recording.frames.push({time:Math.max(.001,recording.frames[0].time+.001),transform:clone(recording.frames[0].transform)});
      const raw=normalizeFrames(recording.frames), smooth=clamp(opts.smooth,0,1), linearize=clamp(opts.linearize,0,1), passes=Math.max(0,Math.min(12,Math.round(n(opts.passes,2))));
      const processed=smoothFrames(raw,smooth,linearize,passes), duration=Math.max(.001,processed[processed.length-1].time);
      const track={id:recording.id+'-track',part:recording.part,interpolation:smooth>0?'smooth':'linear',rawKeyframes:raw,keyframes:processed};
      const animation={id:recording.id,duration,loop:!!opts.loop,tracks:[track],settings:{smooth,linearize,passes}};
      animations.set(animation.id,animation); recording=null; return clone(animation);
    }

    function cancelPathRecording() { recording=null; }

    function resmoothAnimation(id, smooth, linearize, passes) {
      const animation=animations.get(id); if(!animation) return false;
      smooth=clamp(smooth,0,1); linearize=clamp(linearize,0,1); passes=Math.max(0,Math.min(12,Math.round(n(passes,2))));
      (animation.tracks||[]).forEach(track=>{ const raw=track.rawKeyframes||track.keyframes||[]; track.keyframes=smoothFrames(raw,smooth,linearize,passes); track.interpolation=smooth>0?'smooth':'linear'; });
      animation.settings={smooth,linearize,passes}; return true;
    }

    function playAnimation(id, opts) {
      opts=opts||{}; const animation=animations.get(id); if(!animation) return false;
      playing={id,start:n(opts.now,global.performance&&global.performance.now?global.performance.now():Date.now()),speed:Math.max(.01,n(opts.speed,1)),loop:opts.loop===undefined?!!animation.loop:!!opts.loop};
      return true;
    }
    function stopAnimation() { playing=null; }
    function isPlaying() { return !!playing; }
    function isRecording() { return !!recording; }

    function updateAnimation(now) {
      if(!playing) return false;
      const animation=animations.get(playing.id); if(!animation){playing=null;return false;}
      now=n(now,global.performance&&global.performance.now?global.performance.now():Date.now());
      const duration=Math.max(.001,n(animation.duration,1)); let time=((now-playing.start)/1000)*playing.speed;
      if(playing.loop) time=time%duration; else if(time>=duration){time=duration; playing=null;}
      (animation.tracks||[]).forEach(track=>{
        const tr=frameAt(track,time),obj=objects.get(track.part),part=parts.get(track.part); if(!tr||!obj||!part)return;
        part.transform=clone(tr); applyTransform(obj,tr);
      });
      return true;
    }

    function animation(id){const a=animations.get(id);return a?clone(a):null;}
    function animationIds(){return Array.from(animations.keys());}

    function resize() {
      const w = Math.max(1,options.width || root.clientWidth || 900), h = Math.max(1,options.height || root.clientHeight || 650);
      renderer.setSize(w,h,false); camera.aspect=w/h; camera.updateProjectionMatrix();
    }
    function render(now){ updateAnimation(now); resize(); renderer.render(scene,camera); }
    function exportDescriptor(){ return {kind:'model',model:'anatomy',plugin:'anatomy',version:descriptor.version||'jx.anatomy/2',id:descriptor.id,species:descriptor.species,parts:Array.from(parts.values()).map(clone),animations:Array.from(animations.values()).map(clone)}; }

    return {
      canvas,renderer,scene,camera,root:modelRoot,objects,parts,animations,
      select,selectedPart,movePart,alignTexture,pinTexture,setShape,
      beginPathRecording,recordPathPoint,finishPathRecording,cancelPathRecording,resmoothAnimation,
      playAnimation,stopAnimation,isPlaying,isRecording,animation,animationIds,updateAnimation,
      render,exportDescriptor
    };
  }

  const api={mount,applyTextureTransform,smoothFrames,lerpTransform};
  if(typeof module!=='undefined'&&module.exports) module.exports=api;
  global.JXAnatomyHost=api;
})(typeof window!=='undefined'?window:globalThis);
