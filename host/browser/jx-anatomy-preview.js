/* JX Anatomy live 3D preview for the image Designer.
 * Browser display only: the canonical model remains JX Anatomy data and GLB export remains JX/PHP.
 * Loaded automatically by jx-anatomy-texture-skin.js when the image Designer is present.
 */
(function(global){
'use strict';
if(typeof document==='undefined')return;

const api={};
let latest=null,textures=new Map(),host=null,rebuildTimer=null,readyPromise=null,raf=0;
let target=null,yaw=.55,pitch=.18,distance=4.2,framed=false;
let ui=null,drag=null;

const clamp=(v,a,b)=>Math.max(a,Math.min(b,Number.isFinite(Number(v))?Number(v):a));
const clone=v=>JSON.parse(JSON.stringify(v));

function scriptBase(){
  const scripts=[...document.scripts];
  const mine=scripts.find(s=>/jx-anatomy-preview\.js(?:\?|$)/.test(s.src));
  return mine?mine.src.replace(/jx-anatomy-preview\.js(?:\?.*)?$/,''):'';
}
function loadScript(src){
  return new Promise((resolve,reject)=>{
    const existing=[...document.scripts].find(s=>s.src===src);
    if(existing){if(existing.dataset.jxLoaded==='1')return resolve();existing.addEventListener('load',resolve,{once:true});existing.addEventListener('error',reject,{once:true});return;}
    const s=document.createElement('script');s.src=src;s.async=true;s.onload=()=>{s.dataset.jxLoaded='1';resolve()};s.onerror=()=>reject(new Error('Could not load '+src));document.head.appendChild(s);
  });
}
async function ensureDeps(){
  if(!global.THREE){const mod=await import('https://cdn.jsdelivr.net/npm/three@0.179.1/build/three.module.min.js');global.THREE=mod;}
  if(!global.JXAnatomyHost)await loadScript(scriptBase()+'jx-anatomy-host.js');
}

function makeUi(){
  if(ui)return ui;
  const source=document.getElementById('canvas'),stage=source&&source.parentElement,main=source&&source.closest('main');
  if(!source||!stage||!main||!document.getElementById('exportGLB'))return null;
  main.id=main.id||'jx-anatomy-workspace';
  main.style.display='grid';main.style.alignItems='stretch';main.style.justifyContent='stretch';main.style.gap='1px';main.style.overflow='hidden';
  stage.style.minWidth='0';stage.style.minHeight='0';stage.style.overflow='auto';stage.style.display='flex';stage.style.alignItems='center';stage.style.justifyContent='center';stage.style.background='#070b10';
  const panel=document.createElement('div');panel.id='jxModelPreview';panel.style.cssText='position:relative;min-width:0;min-height:320px;overflow:hidden;background:radial-gradient(circle at 50% 35%,#263242,#0c1118 72%);border-left:1px solid #2d3948;';
  const label=document.createElement('div');label.style.cssText='position:absolute;z-index:4;left:10px;top:10px;padding:6px 8px;border:1px solid #405168;border-radius:7px;background:rgba(16,21,28,.88);color:#dcecff;font:12px system-ui;pointer-events:none';label.innerHTML='<b>JX 3D Model Preview</b><br><span id="jxPreviewState">Draw anatomy to create the model.</span>';
  const toolbar=document.createElement('div');toolbar.style.cssText='position:absolute;z-index:5;right:8px;top:8px;display:flex;gap:4px;flex-wrap:wrap;justify-content:flex-end;max-width:75%';
  [['front','Front'],['side','Side'],['top','Top'],['reset','Frame']].forEach(([id,text])=>{const b=document.createElement('button');b.type='button';b.textContent=text;b.dataset.view=id;b.style.cssText='padding:5px 7px;border:1px solid #43546a;border-radius:5px;background:rgba(25,33,44,.9);color:#eef4fb;cursor:pointer;font:11px system-ui';toolbar.appendChild(b)});
  const help=document.createElement('div');help.textContent='drag: orbit · wheel: zoom';help.style.cssText='position:absolute;z-index:4;right:9px;bottom:8px;padding:4px 6px;background:rgba(10,14,20,.72);border-radius:5px;color:#91a2b6;font:11px system-ui;pointer-events:none';
  const mount=document.createElement('div');mount.id='jxPreviewMount';mount.style.cssText='position:absolute;inset:0;';
  panel.append(mount,label,toolbar,help);main.appendChild(panel);
  toolbar.addEventListener('click',e=>{const b=e.target.closest('button[data-view]');if(!b)return;setView(b.dataset.view)});
  panel.addEventListener('pointerdown',e=>{if(e.button!==0)return;drag={x:e.clientX,y:e.clientY,id:e.pointerId};panel.setPointerCapture(e.pointerId)});
  panel.addEventListener('pointermove',e=>{if(!drag||drag.id!==e.pointerId)return;const dx=e.clientX-drag.x,dy=e.clientY-drag.y;drag.x=e.clientX;drag.y=e.clientY;yaw-=dx*.008;pitch=clamp(pitch+dy*.008,-1.45,1.45);applyCamera()});
  const end=e=>{if(!drag||drag.id!==e.pointerId)return;drag=null;try{panel.releasePointerCapture(e.pointerId)}catch(_){}};panel.addEventListener('pointerup',end);panel.addEventListener('pointercancel',end);
  panel.addEventListener('wheel',e=>{e.preventDefault();distance=clamp(distance*Math.exp(e.deltaY*.0012),.25,50);applyCamera()},{passive:false});
  ui={main,stage,panel,mount,label,state:label.querySelector('#jxPreviewState')};
  const layout=()=>{if(!ui)return;const narrow=main.clientWidth<820;main.style.gridTemplateColumns=narrow?'1fr':'minmax(0,1.15fr) minmax(320px,.85fr)';main.style.gridTemplateRows=narrow?'minmax(360px,1fr) 380px':'1fr';};
  layout();global.addEventListener('resize',layout);
  return ui;
}

function profileFor(semantic){
  if(global.JXAnatomySurface&&global.JXAnatomySurface.profileFor)return global.JXAnatomySurface.profileFor(semantic);
  return {start:1,mid:1,end:.8};
}
function decorateDescriptor(desc,bodyParts,opts){
  const byId=new Map((desc.parts||[]).map(p=>[p.id,p]));
  const mass=clamp(opts&&opts.mass,.2,3),tone=clamp(opts&&opts.muscleTone,0,1),pump=clamp(opts&&opts.pumpedness,0,2),fat=clamp(opts&&opts.fatCover,0,2);
  for(const bp of bodyParts||[]){
    const tex=textures instanceof Map?textures.get(bp.id):null;
    for(const seg of bp.segments||[]){
      const part=byId.get(seg.id);if(!part)continue;
      const profile=profileFor(seg.semantic||part.semantic),base=Number(part.params&&part.params.radius)||.04;
      part.params=part.params||{};
      part.params.radius=base*mass*(1+tone*.12+fat*.12);
      part.params.pumpedness=pump+tone*.35;
      part.params.previewProfile={start:Number(profile.start)||1,mid:Number(profile.mid)||1,end:Number(profile.end)||.8};
      if(tex&&tex.url){
        const sx=tex.flipU?-1:1,sy=tex.flipV?-1:1,ox=tex.flipU?1:0,oy=tex.flipV?1:0;
        part.textures=[{id:part.id+'-preview-png',mode:'uv',source:tex.url,with:{space:'part',transform:{offset:[ox,oy],scale:[sx,sy],rotation:0,pivot:[.5,.5]}}}];
        part.previewOpacity=Number.isFinite(tex.opacity)?tex.opacity:1;
      }
    }
  }
  return desc;
}
function descriptorFromLatest(){
  if(!latest||!latest.joints||!latest.bones||!latest.bones.length||!global.JXAnatomyImageFit)return null;
  const source=document.getElementById('canvas'),w=Math.max(2,source&&source.width||960),h=Math.max(2,source&&source.height||640);
  const skeleton={joints:clone(latest.joints),bones:clone(latest.bones),passes:[]};
  const species=(document.getElementById('species')||{}).value||'generic';
  const desc=global.JXAnatomyImageFit.toAnatomyDescriptor(skeleton,w,h,{id:'jx-live-preview',species});
  return decorateDescriptor(desc,latest.parts,latest.opts||{});
}

function muscleGeometry(part){
  const THREE=global.THREE,p=part.params||{},length=Math.max(.001,Number(p.length)||1),base=Math.max(.002,Number(p.radius)||.04),pump=Math.max(0,Number(p.pumpedness)||0),prof=p.previewProfile||{start:1,mid:1,end:.82};
  const rings=14,radial=24,pos=[],norm=[],uv=[],idx=[];
  const radiusAt=t=>{const shape=t<=.5?prof.start+(prof.mid-prof.start)*t*2:prof.mid+(prof.end-prof.mid)*(t-.5)*2;return base*shape*(1+pump*.22)};
  for(let y=0;y<=rings;y++){
    const t=y/rings,r=radiusAt(t),py=(t-.5)*length;
    for(let a=0;a<=radial;a++){
      const u=a/radial,ang=u*Math.PI*2,c=Math.cos(ang),s=Math.sin(ang);pos.push(c*r,py,s*r);norm.push(c,0,s);uv.push(u,t);
    }
  }
  for(let y=0;y<rings;y++)for(let a=0;a<radial;a++){const row=radial+1,i=y*row+a,j=i+row;idx.push(i,j,i+1,j,j+1,i+1)}
  const g=new THREE.BufferGeometry();g.setAttribute('position',new THREE.Float32BufferAttribute(pos,3));g.setAttribute('normal',new THREE.Float32BufferAttribute(norm,3));g.setAttribute('uv',new THREE.Float32BufferAttribute(uv,2));g.setIndex(idx);g.computeBoundingSphere();return g;
}
function disposeHost(){
  if(!host)return;
  host.objects&&host.objects.forEach(obj=>{if(obj.geometry&&obj.geometry.dispose)obj.geometry.dispose();const mats=Array.isArray(obj.material)?obj.material:[obj.material];mats.forEach(m=>{if(!m)return;if(m.map&&m.map.dispose)m.map.dispose();if(m.dispose)m.dispose()})});
  if(host.renderer&&host.renderer.dispose)host.renderer.dispose();if(host.canvas&&host.canvas.parentNode)host.canvas.parentNode.removeChild(host.canvas);host=null;
}
function applyCamera(){
  if(!host||!global.THREE)return;if(!target)target=new global.THREE.Vector3(0,0,0);
  const cp=Math.cos(pitch),sp=Math.sin(pitch),sy=Math.sin(yaw),cy=Math.cos(yaw);
  host.camera.position.set(target.x+distance*sy*cp,target.y+distance*sp,target.z+distance*cy*cp);host.camera.lookAt(target);
}
function frameModel(force){
  if(!host||!global.THREE)return;const box=new global.THREE.Box3().setFromObject(host.root);if(box.isEmpty())return;
  target=box.getCenter(new global.THREE.Vector3());const size=box.getSize(new global.THREE.Vector3()),radius=Math.max(.15,size.length()*.5);if(force||!framed){distance=Math.max(.65,radius*2.45);framed=true}applyCamera();
}
function setView(which){
  if(which==='front'){yaw=0;pitch=0}else if(which==='side'){yaw=Math.PI/2;pitch=0}else if(which==='top'){yaw=0;pitch=1.45}else if(which==='reset'){yaw=.55;pitch=.18;framed=false;frameModel(true);return}applyCamera();
}
async function rebuild(){
  rebuildTimer=null;const u=makeUi();if(!u)return;const desc=descriptorFromLatest();
  if(!desc){disposeHost();u.state.textContent='Draw anatomy to create the model.';return}
  try{
    if(!readyPromise)readyPromise=ensureDeps();await readyPromise;disposeHost();u.mount.innerHTML='';
    host=global.JXAnatomyHost.mount(desc,u.mount,{});
    for(const p of desc.parts||[]){if(!p.params||!p.params.previewProfile)continue;const obj=host.objects.get(p.id);if(!obj)continue;obj.geometry.dispose();obj.geometry=muscleGeometry(p);if(Number.isFinite(p.previewOpacity)&&p.previewOpacity<1){obj.material.transparent=true;obj.material.opacity=p.previewOpacity}}
    host.scene.add(new global.THREE.GridHelper(6,12));
    frameModel(!framed);u.state.textContent=(latest.parts||[]).length+' body part(s) · '+(latest.bones||[]).length+' segment(s) · live JX geometry';
  }catch(err){u.state.textContent='3D preview error: '+String(err&&err.message||err)}
}
function schedule(){if(rebuildTimer)clearTimeout(rebuildTimer);rebuildTimer=setTimeout(rebuild,70)}
function update(parts,joints,bones,opts){latest={parts:clone(parts||[]),joints:clone(joints||[]),bones:clone(bones||[]),opts:clone(opts||{})};schedule()}
function setTextures(value){textures=value instanceof Map?value:new Map(Object.entries(value||{}));schedule()}
function loop(now){if(host)host.render(now);raf=global.requestAnimationFrame(loop)}

api.update=update;api.setTextures=setTextures;api.rebuild=rebuild;api.setView=setView;
global.JXAnatomyPreview=api;
makeUi();if(global.__JXAnatomyPreviewState)update(global.__JXAnatomyPreviewState.parts,global.__JXAnatomyPreviewState.joints,global.__JXAnatomyPreviewState.bones,global.__JXAnatomyPreviewState.opts);if(global.__JXAnatomyPreviewTextures)setTextures(global.__JXAnatomyPreviewTextures);raf=global.requestAnimationFrame(loop);
})(typeof window!=='undefined'?window:globalThis);
