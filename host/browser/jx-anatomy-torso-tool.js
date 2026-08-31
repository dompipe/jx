/* JX Anatomy four-corner torso constructor.
 *
 * User input for a torso is deliberately different from the internal rig:
 *   1. left shoulder
 *   2. right shoulder
 *   3. left hip
 *   4. right hip
 *
 * The tool derives the canonical torso-root -> spine -> chest -> neck-root
 * centerline and feeds those points back through the Designer's normal
 * semantic placement path. Shoulder/hip width is written into the real torso
 * bones as fitted half-width evidence, so surfacing, texture baking and GLB
 * export all receive torso volume rather than a thin spine tube.
 */
(function(global){
'use strict';
if(typeof document==='undefined')return;

const $=id=>document.getElementById(id);
const lerp=(a,b,t)=>({x:a.x+(b.x-a.x)*t,y:a.y+(b.y-a.y)*t});
const mid=(a,b)=>({x:(a.x+b.x)/2,y:(a.y+b.y)/2});
const dist=(a,b)=>Math.hypot(b.x-a.x,b.y-a.y);
const prompts=['left shoulder','right shoulder','left hip','right hip'];
let points=[],markers=[],busy=false;

function activeTorso(){
  const type=$('partType'),finish=$('finishPart');
  return !!(type&&type.value==='torso'&&finish&&!finish.disabled);
}
function status(text){const f=$('status');if(f)f.textContent=text}
function guide(text){const g=$('partGuide');if(g)g.innerHTML=text}
function clearMarkers(){markers.forEach(m=>m.remove());markers=[]}
function reset(){points=[];clearMarkers()}
function pointFromEvent(ev){
  const c=$('canvas'),r=c.getBoundingClientRect();
  return{x:(ev.clientX-r.left)*c.width/r.width,y:(ev.clientY-r.top)*c.height/r.height};
}
function marker(p,index){
  const stage=$('stage'),c=$('canvas');if(!stage||!c)return;
  const m=document.createElement('div');m.className='jx-torso-corner';m.style.cssText='position:absolute;z-index:12;pointer-events:none;transform:translate(-50%,-50%);width:16px;height:16px;border-radius:50%;background:#ffd16a;border:2px solid #111820;box-shadow:0 0 0 2px rgba(83,168,255,.75);';
  m.style.left=(p.x/c.width*100)+'%';m.style.top=(p.y/c.height*100)+'%';m.title=prompts[index];stage.appendChild(m);markers.push(m);
}
function synthPoint(p){
  const c=$('canvas'),r=c.getBoundingClientRect(),cx=r.left+(p.x/c.width)*r.width,cy=r.top+(p.y/c.height)*r.height,C=global.PointerEvent||global.MouseEvent;
  c.dispatchEvent(new C('pointerdown',{bubbles:true,clientX:cx,clientY:cy,button:0,pointerId:1}));
}
function liveBones(){const s=global.__JXAnatomyPreviewState;return s&&Array.isArray(s.bones)?s.bones:[]}
function decorateTorsoBones(before,frame){
  const bones=liveBones().filter(b=>!before.has(String(b.id))&&b.bodyPartType==='torso').sort((a,b)=>(Number(a.segmentIndex)||0)-(Number(b.segmentIndex)||0));
  const shoulderHalf=frame.shoulderWidth/2,hipHalf=frame.hipWidth/2;
  const widths=[hipHalf*.62,((hipHalf+shoulderHalf)/2)*.62,shoulderHalf*.62];
  bones.forEach((b,i)=>{
    const width=Math.max(6,widths[Math.min(i,widths.length-1)]||widths[0]||12);
    b.fit=Object.assign({},b.fit||{},{width,confidence:1,score:1,source:'four-corner-torso'});
    b.torsoFrame={
      source:'four-corner',shoulderWidth:frame.shoulderWidth,hipWidth:frame.hipWidth,
      leftShoulder:[frame.leftShoulder.x,frame.leftShoulder.y],rightShoulder:[frame.rightShoulder.x,frame.rightShoulder.y],
      leftHip:[frame.leftHip.x,frame.leftHip.y],rightHip:[frame.rightHip.x,frame.rightHip.y]
    };
  });
  const mass=$('mass');if(mass)mass.dispatchEvent(new Event('input',{bubbles:true}));
  return bones.length;
}
function complete(){
  if(points.length!==4||busy)return;busy=true;
  const [leftShoulder,rightShoulder,leftHip,rightHip]=points,shoulderCenter=mid(leftShoulder,rightShoulder),hipCenter=mid(leftHip,rightHip);
  const centerline=[hipCenter,lerp(hipCenter,shoulderCenter,.34),lerp(hipCenter,shoulderCenter,.72),shoulderCenter];
  const frame={leftShoulder,rightShoulder,leftHip,rightHip,shoulderWidth:dist(leftShoulder,rightShoulder),hipWidth:dist(leftHip,rightHip)};
  const before=new Set(liveBones().map(b=>String(b.id)));
  clearMarkers();
  centerline.forEach(synthPoint);
  const finish=$('finishPart');if(finish&&!finish.disabled)finish.click();
  setTimeout(()=>{
    const n=decorateTorsoBones(before,frame);
    guide('<b>Torso created.</b><br>JX derived pelvis center, spine, chest and neck root from the four body corners.<br><span class="mini">Shoulder width: '+Math.round(frame.shoulderWidth)+' px · hip width: '+Math.round(frame.hipWidth)+' px · '+n+' torso mesh segments</span>');
    status('Torso created from shoulders + hips. JX torso width is ready for fitting, texture baking and GLB build.');
    points=[];busy=false;
  },20);
}
function capture(ev){
  // Only replace real user clicks. Programmatic clicks used by JSON reload and
  // by this tool itself must pass through to the Designer unchanged.
  if(!ev.isTrusted||busy||!activeTorso())return;
  ev.preventDefault();ev.stopImmediatePropagation();
  const p=pointFromEvent(ev),index=points.length;points.push(p);marker(p,index);
  if(points.length<4){
    guide('<b>Torso · four-corner mode</b><br>Captured '+prompts[index]+'.<br>Next: <b>'+prompts[points.length]+'</b><br><span class="mini">Order: left shoulder → right shoulder → left hip → right hip</span>');
    status('Torso: click '+prompts[points.length]+'.');
  }else complete();
}
function refreshGuide(){
  reset();
  const type=$('partType');if(type&&type.value==='torso')guide('<b>Torso · four-corner mode</b><br>Press Start selected body part, then click:<br><b>1 left shoulder · 2 right shoulder · 3 left hip · 4 right hip</b><br><span class="mini">JX derives the centerline and torso volume automatically.</span>');
}
function init(){
  const canvas=$('canvas'),type=$('partType'),start=$('startPart');if(!canvas||!type||!start)return;
  canvas.addEventListener('pointerdown',capture,true);
  type.addEventListener('change',refreshGuide);
  start.addEventListener('click',()=>setTimeout(()=>{if(type.value==='torso'&&activeTorso()){reset();guide('<b>Torso · four-corner mode</b><br>Click <b>left shoulder</b> first.<br><span class="mini">Then right shoulder → left hip → right hip.</span>');status('Torso: click left shoulder.')}},0));
  refreshGuide();
}

global.JXAnatomyTorsoTool={reset};
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})(typeof window!=='undefined'?window:globalThis);
