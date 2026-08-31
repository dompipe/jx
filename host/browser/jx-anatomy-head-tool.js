/* JX Anatomy four-point head constructor.
 *
 * Head input is an envelope, not a chain:
 *   1. top of head
 *   2. chin / bottom of head
 *   3. left side of head
 *   4. right side of head
 *
 * JX derives a vertical head axis plus width/depth evidence. The ordinary
 * Designer still owns the semantic bone, texture binding, JSON, fitting and
 * GLB pipeline. This module upgrades that one semantic segment into a real
 * head volume for descriptor generation.
 */
(function(global){
'use strict';
if(typeof document==='undefined')return;

const $=id=>document.getElementById(id);
const dist=(a,b)=>Math.hypot(b.x-a.x,b.y-a.y);
const prompts=['top of head','chin / bottom of head','left side of head','right side of head'];
let points=[],markers=[],busy=false;

function registerHead(){
  const sem=global.JXAnatomySemantics;
  if(sem&&sem.TEMPLATES&&!sem.TEMPLATES.head){
    sem.TEMPLATES.head={
      family:'head',label:'Head / skull',variable:false,
      ports:['head-top','chin'],bones:['head'],ik:false,
      surface:{archetype:'head',volume:true,muscleGroups:['scalp','temporal','jaw-support']}
    };
  }
  const surface=global.JXAnatomySurface;
  if(surface&&surface.MUSCLE_PROFILES&&!surface.MUSCLE_PROFILES.head){
    surface.MUSCLE_PROFILES.head={start:.68,mid:1.0,end:.76,offset:0,groups:['head']};
  }
}
function installOption(){
  const sel=$('partType');if(!sel)return;
  if([...sel.options].some(o=>o.value==='head'))return;
  const o=document.createElement('option');o.value='head';o.textContent='Head / skull';
  const neck=[...sel.options].find(x=>x.value==='neck');
  if(neck)sel.insertBefore(o,neck);else sel.appendChild(o);
}
function activeHead(){const type=$('partType'),finish=$('finishPart');return !!(type&&type.value==='head'&&finish&&!finish.disabled)}
function status(text){const f=$('status');if(f)f.textContent=text}
function guide(text){const g=$('partGuide');if(g)g.innerHTML=text}
function clearMarkers(){markers.forEach(m=>m.remove());markers=[]}
function reset(){points=[];clearMarkers()}
function pointFromEvent(ev){const c=$('canvas'),r=c.getBoundingClientRect();return{x:(ev.clientX-r.left)*c.width/r.width,y:(ev.clientY-r.top)*c.height/r.height}}
function marker(p,index){
  const stage=$('stage'),c=$('canvas');if(!stage||!c)return;
  const m=document.createElement('div');m.className='jx-head-corner';
  m.style.cssText='position:absolute;z-index:13;pointer-events:none;transform:translate(-50%,-50%);width:16px;height:16px;border-radius:50%;background:#7fd6ff;border:2px solid #111820;box-shadow:0 0 0 2px rgba(255,209,106,.82);';
  m.style.left=(p.x/c.width*100)+'%';m.style.top=(p.y/c.height*100)+'%';m.title=prompts[index];stage.appendChild(m);markers.push(m);
}
function synthPoint(p){
  const c=$('canvas'),r=c.getBoundingClientRect(),cx=r.left+(p.x/c.width)*r.width,cy=r.top+(p.y/c.height)*r.height,C=global.PointerEvent||global.MouseEvent;
  c.dispatchEvent(new C('pointerdown',{bubbles:true,clientX:cx,clientY:cy,button:0,pointerId:1}));
}
function liveBones(){const s=global.__JXAnatomyPreviewState;return s&&Array.isArray(s.bones)?s.bones:[]}
function decorateHeadBone(before,frame){
  const bone=liveBones().find(b=>!before.has(String(b.id))&&b.bodyPartType==='head');if(!bone)return 0;
  bone.fit=Object.assign({},bone.fit||{},{width:Math.max(3,frame.width*.5),confidence:1,score:1,source:'four-point-head'});
  bone.headFrame={source:'four-point',top:[frame.top.x,frame.top.y],chin:[frame.chin.x,frame.chin.y],left:[frame.left.x,frame.left.y],right:[frame.right.x,frame.right.y],width:frame.width,height:frame.height,depth:frame.depth};
  const mass=$('mass');if(mass)mass.dispatchEvent(new Event('input',{bubbles:true}));
  return 1;
}
function complete(){
  if(points.length!==4||busy)return;busy=true;
  const [top,chin,left,right]=points,width=dist(left,right),height=dist(top,chin),depth=width*.82,frame={top,chin,left,right,width,height,depth};
  const before=new Set(liveBones().map(b=>String(b.id)));
  clearMarkers();
  // Internally the head remains one semantic axis. Its width/depth live on the
  // bone as headFrame and are consumed by the descriptor upgrade below.
  synthPoint(top);synthPoint(chin);
  setTimeout(()=>{
    const n=decorateHeadBone(before,frame);
    guide('<b>Head created.</b><br>JX derived the skull axis, width and depth from the four head points.<br><span class="mini">width: '+Math.round(width)+' px · height: '+Math.round(height)+' px · depth estimate: '+Math.round(depth)+' px</span>');
    status(n?'Head volume created. You can bake its texture, add neck/jaw/snout/beak, and build the GLB.':'Head axis created; width evidence was not attached.');
    points=[];busy=false;
  },25);
}
function capture(ev){
  if(!ev.isTrusted||busy||!activeHead())return;
  ev.preventDefault();ev.stopImmediatePropagation();
  const p=pointFromEvent(ev),index=points.length;points.push(p);marker(p,index);
  if(points.length<4){
    guide('<b>Head · four-point mode</b><br>Captured '+prompts[index]+'.<br>Next: <b>'+prompts[points.length]+'</b><br><span class="mini">Order: top → chin → left edge → right edge</span>');
    status('Head: click '+prompts[points.length]+'.');
  }else complete();
}
function patchDescriptor(){
  const fit=global.JXAnatomyImageFit;if(!fit||fit.__jxHeadVolumePatched)return;
  fit.__jxHeadVolumePatched=true;
  const original=fit.toAnatomyDescriptor.bind(fit);
  fit.toAnatomyDescriptor=function(skeleton,imageW,imageH,opts){
    const desc=original(skeleton,imageW,imageH,opts),parts=new Map((desc.parts||[]).map(p=>[String(p.id),p]));
    const scale=(Number(opts&&opts.modelScale)||2.2)/Math.max(2,Number(imageW)||2,Number(imageH)||2);
    for(const b of (skeleton&&skeleton.bones)||[]){
      if(String(b.bodyPartType||'')!=='head'&&String(b.semantic||'')!=='head')continue;
      const p=parts.get(String(b.id));if(!p)continue;
      const frame=b.headFrame||{},half=Number(b.fit&&b.fit.width)||0;
      const widthPx=Math.max(8,Number(frame.width)||half*2||24),heightPx=Math.max(8,Number(frame.height)||((Number(p.params&&p.params.length)||.2)/scale)),depthPx=Math.max(6,Number(frame.depth)||widthPx*.82);
      const worldW=widthPx*scale,worldH=heightPx*scale,worldD=depthPx*scale,diameter=.68;
      p.type='head';p.semantic='head';p.params=Object.assign({},p.params||{},{width:worldW,height:worldH,depth:worldD,radius:worldW*.5,length:worldH,sourceWidthPx:widthPx,headVolume:true});
      p.transform=p.transform||{};p.transform.scale=[worldW/diameter,worldH/diameter,worldD/diameter];
    }
    return desc;
  };
}
function refreshGuide(){
  reset();const type=$('partType');
  if(type&&type.value==='head')guide('<b>Head · four-point mode</b><br>Press Start selected body part, then click:<br><b>1 top of head · 2 chin · 3 left side · 4 right side</b><br><span class="mini">JX derives skull width, height and depth automatically.</span>');
}
function init(){
  registerHead();installOption();patchDescriptor();
  const canvas=$('canvas'),type=$('partType'),start=$('startPart');if(!canvas||!type||!start)return;
  canvas.addEventListener('pointerdown',capture,true);type.addEventListener('change',refreshGuide);
  start.addEventListener('click',()=>setTimeout(()=>{if(type.value==='head'&&activeHead()){reset();guide('<b>Head · four-point mode</b><br>Click <b>top of head</b> first.<br><span class="mini">Then chin → left side → right side.</span>');status('Head: click top of head.')}},0));
  refreshGuide();
}

global.JXAnatomyHeadTool={reset,registerHead,patchDescriptor};
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})(typeof window!=='undefined'?window:globalThis);
