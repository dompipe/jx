/* JX Anatomy project JSON round-trip support.
 *
 * Loads canonical Anatomy JSON and reloadable JX project JSON back through the
 * Designer's own semantic placement path.  This is intentionally not a
 * preview-state shortcut: reconstructed points call the same Start Part /
 * canvas placement / Finish Part controls as a user, so the private Designer
 * state, build button, fitting state, texture map and GLB exporter all agree.
 */
(function(global){
'use strict';
if(typeof document==='undefined')return;

const api={};
let ui=null,busy=false;
const clone=v=>JSON.parse(JSON.stringify(v));
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
const byId=id=>document.getElementById(id);

function status(text,good){
  const f=byId('status');if(f)f.textContent=text;
  if(ui&&ui.info){ui.info.textContent=text;ui.info.style.color=good===true?'#89d895':good===false?'#f1bf69':'#94a3b7'}
}
function dataUrlToFile(dataUrl,name){
  const m=String(dataUrl||'').match(/^data:([^;,]+)(;base64)?,(.*)$/);if(!m)throw new Error('Invalid embedded reference image');
  const mime=m[1]||'image/png',raw=m[2]?atob(m[3]):decodeURIComponent(m[3]),bytes=new Uint8Array(raw.length);
  for(let i=0;i<raw.length;i++)bytes[i]=raw.charCodeAt(i);
  return new File([bytes],name||'jx-reference.png',{type:mime});
}
function blankWorkspaceFile(w,h){
  const c=document.createElement('canvas');c.width=Math.max(2,Math.round(w||960));c.height=Math.max(2,Math.round(h||640));
  const cx=c.getContext('2d');cx.fillStyle='#111820';cx.fillRect(0,0,c.width,c.height);
  return dataUrlToFile(c.toDataURL('image/png'),'jx-json-reference-placeholder.png');
}
function assignFile(input,file){
  const dt=new DataTransfer();dt.items.add(file);input.files=dt.files;input.dispatchEvent(new Event('change',{bubbles:true}));
}
async function waitEditorReady(w,h){
  const canvas=byId('canvas'),start=byId('startPart');
  for(let i=0;i<160;i++){
    if(start&&!start.disabled&&canvas&&(!w||Math.abs(canvas.width-w)<=3)&&(!h||Math.abs(canvas.height-h)<=3))return;
    await sleep(25);
  }
  if(!start||start.disabled)throw new Error('Designer did not finish loading the JSON workspace');
}
function canvasEvent(x,y){
  const c=byId('canvas'),r=c.getBoundingClientRect(),cx=r.left+(x/c.width)*r.width,cy=r.top+(y/c.height)*r.height;
  const C=global.PointerEvent||global.MouseEvent;
  return new C('pointerdown',{bubbles:true,clientX:cx,clientY:cy,button:0,pointerId:1});
}
function clickPoint(p){
  const c=byId('canvas');
  const x=Math.max(1,Math.min(c.width-1,Number(p.x)||0)),y=Math.max(1,Math.min(c.height-1,Number(p.y)||0));
  c.dispatchEvent(canvasEvent(x,y));
}
function sourceSkeleton(doc){
  if(doc&&doc.imageSkeleton&&Array.isArray(doc.imageSkeleton.joints)&&Array.isArray(doc.imageSkeleton.bones))return doc.imageSkeleton;
  if(doc&&doc.editorState&&Array.isArray(doc.editorState.joints)&&Array.isArray(doc.editorState.bones))return doc.editorState;
  throw new Error('JSON has no JX imageSkeleton/editorState to reload');
}
function jointMap(sk){return new Map((sk.joints||[]).map(j=>[String(j.id),j]))}
function validType(type){return !!(global.JXAnatomySemantics&&global.JXAnatomySemantics.TEMPLATES&&global.JXAnatomySemantics.TEMPLATES[type])}
function inferType(bone,sk){
  const direct=String(bone&&bone.bodyPartType||'');if(validType(direct))return direct;
  return global.JXAnatomySemantics.inferTypeFromSemantic(bone&&bone.semantic,bone&&bone.pass,sk&&sk.passes||[]);
}
function orderedPorts(segments,joints){
  const sorted=segments.slice().sort((a,b)=>(Number(a.segmentIndex??a.index)||0)-(Number(b.segmentIndex??b.index)||0));
  if(!sorted.length)return [];
  const out=[],first=sorted[0],a0=joints.get(String(first.a)),b0=joints.get(String(first.b));if(!a0||!b0)return [];
  out.push(a0,b0);let last=String(first.b);
  for(let i=1;i<sorted.length;i++){
    const s=sorted[i],a=String(s.a),b=String(s.b);
    if(a===last){const q=joints.get(b);if(q){out.push(q);last=b}}
    else if(b===last){const q=joints.get(a);if(q){out.push(q);last=a}}
    else {const qa=joints.get(a),qb=joints.get(b);if(qa)out.push(qa);if(qb)out.push(qb);last=b}
  }
  return out;
}
function bodyGroups(doc,sk){
  const joints=jointMap(sk),boneLookup=new Map((sk.bones||[]).map(b=>[String(b.id),b])),out=[];
  if(Array.isArray(doc.bodyParts)&&doc.bodyParts.length){
    for(const bp of doc.bodyParts){
      if(!bp)continue;let segs=[];
      if(Array.isArray(bp.segments)&&bp.segments.length)segs=bp.segments.map(s=>boneLookup.get(String(s.id))||s).filter(Boolean);
      else if(Array.isArray(bp.boneIds))segs=bp.boneIds.map(id=>boneLookup.get(String(id))).filter(Boolean);
      if(!segs.length)continue;
      const type=validType(bp.type)?bp.type:validType(bp.anatomy&&bp.anatomy.type)?bp.anatomy.type:inferType(segs[0],sk);
      const ports=orderedPorts(segs,joints);if(ports.length<2)continue;
      out.push({oldId:String(bp.id||segs[0].bodyPart||('part-'+out.length)),type,side:bp.side||segs[0].side||'auto',ports});
    }
    if(out.length)return out;
  }
  const grouped=new Map();
  for(const b of sk.bones||[]){
    const key=String(b.bodyPart||((b.bodyPartType||inferType(b,sk))+'|'+(b.side||'auto')));
    if(!grouped.has(key))grouped.set(key,[]);grouped.get(key).push(b);
  }
  for(const [oldId,segs] of grouped){
    const ports=orderedPorts(segs,joints);if(ports.length<2)continue;
    out.push({oldId,type:inferType(segs[0],sk),side:segs[0].side||'auto',ports});
  }
  return out;
}
function visibleParts(){const st=global.__JXAnatomyPreviewState;return st&&Array.isArray(st.parts)?st.parts:[]}
async function waitPartCount(n){for(let i=0;i<60;i++){if(visibleParts().length>=n)return;await sleep(15)}}
async function rebuildDesigner(doc,sk){
  const groups=bodyGroups(doc,sk);if(!groups.length)throw new Error('JSON contains no reconstructable semantic bone groups');
  const type=byId('partType'),side=byId('side'),start=byId('startPart'),finish=byId('finishPart'),bpMap=new Map();
  let expected=0;
  for(const g of groups){
    type.value=validType(g.type)?g.type:'generic';type.dispatchEvent(new Event('change',{bubbles:true}));
    side.value=['left','right','center'].includes(g.side)?g.side:'auto';
    const before=new Set(visibleParts().map(p=>String(p.id)));start.click();
    if(start.disabled&&byId('finishPart').disabled)throw new Error('Designer refused to start '+g.type);
    for(const p of g.ports){clickPoint(p);await sleep(2)}
    if(!finish.disabled)finish.click();
    expected++;await waitPartCount(expected);
    const added=visibleParts().find(p=>!before.has(String(p.id)));
    if(added)bpMap.set(String(g.oldId),String(added.id));
  }
  const build=byId('exportGLB');
  if(!build||build.disabled){
    // Force one normal Designer control synchronization cycle after the final part.
    const mass=byId('mass');if(mass)mass.dispatchEvent(new Event('input',{bubbles:true}));await sleep(20);
  }
  if(!build||build.disabled)throw new Error('Model reloaded, but Designer still reports no buildable bone segments');
  return bpMap;
}
function setControl(id,value){const el=byId(id);if(el&&value!=null&&Number.isFinite(Number(value)))el.value=String(value)}
function restoreControls(doc){
  const bp=(doc.bodyParts||[])[0]||{},c=bp.controls||(bp.anatomy&&bp.anatomy.controls)||((doc.surfaces||[])[0]&&doc.surfaces[0].controls)||{};
  setControl('mass',c.mass);setControl('muscleTone',c.muscleTone);setControl('pumpedness',c.pumpedness);setControl('fatCover',c.fatCover);
  const species=byId('species');if(species&&doc.species&&[...species.options].some(o=>o.value===doc.species||o.text===doc.species))species.value=doc.species;
}
function redraw(){const el=byId('mass')||byId('muscleTone');if(el)el.dispatchEvent(new Event('input',{bubbles:true}))}
function textureAssets(doc){return Array.isArray(doc.textureAssets)?doc.textureAssets:[]}
async function restoreTextures(doc,bpMap){
  const assets=textureAssets(doc);if(!assets.length)return 0;
  redraw();await sleep(30);const map=global.__JXAnatomyPreviewTextures;if(!(map instanceof Map))return 0;
  map.clear();let count=0;
  for(const a of assets){
    if(!a||!a.dataUrl)continue;const target=bpMap.get(String(a.bodyPart))||String(a.bodyPart),img=new Image();
    await new Promise((resolve,reject)=>{img.onload=resolve;img.onerror=reject;img.src=a.dataUrl});
    map.set(target,{image:img,url:a.dataUrl,dataUrl:a.dataUrl,name:a.name||('jx-restored-'+target+'.png'),mime:'image/png',flipU:!!a.flipU,flipV:!!a.flipV,opacity:Number.isFinite(Number(a.opacity))?Number(a.opacity):1,generated:!!a.generated,source:a.source||'project-json',bake:a.bake||null});count++;
  }
  redraw();return count;
}
async function loadDocument(doc,fileName){
  if(busy)return;busy=true;if(ui)ui.load.disabled=true;
  try{
    if(!doc||typeof doc!=='object')throw new Error('Invalid JSON object');
    if(doc.model&&doc.model!=='anatomy'&&doc.kind!=='jx-anatomy-project')throw new Error('JSON is not a JX Anatomy model');
    const sk=sourceSkeleton(doc),w=Number(sk.width)||Number(doc.canvas&&doc.canvas.width)||960,h=Number(sk.height)||Number(doc.canvas&&doc.canvas.height)||640,input=byId('imageFile');
    const refFile=doc.referenceImage&&doc.referenceImage.dataUrl?dataUrlToFile(doc.referenceImage.dataUrl,doc.referenceImage.name||'jx-reference'):blankWorkspaceFile(w,h);
    status('Reloading JX anatomy JSON into the Designer…');assignFile(input,refFile);await waitEditorReady(w,h);
    const bpMap=await rebuildDesigner(doc,sk);restoreControls(doc);redraw();const textures=await restoreTextures(doc,bpMap);redraw();
    const st=global.__JXAnatomyPreviewState,bones=st&&st.bones?st.bones.length:0;
    status('Reloaded '+(fileName||'JX anatomy JSON')+': '+bones+' buildable segments'+(textures?' · '+textures+' PNG textures':'')+'. GLB build is ready.',true);
  }catch(err){status('JSON reload failed: '+String(err&&err.message||err),false)}finally{busy=false;if(ui)ui.load.disabled=false}
}
async function loadFile(file){if(!file)return;try{const text=await file.text(),doc=JSON.parse(text);await loadDocument(doc,file.name)}catch(err){status('JSON reload failed: '+String(err&&err.message||err),false)}}

async function readReference(){
  const input=byId('imageFile'),file=input&&input.files&&input.files[0];if(!file)return null;
  const dataUrl=await new Promise((resolve,reject)=>{const r=new FileReader();r.onload=()=>resolve(String(r.result||''));r.onerror=()=>reject(r.error);r.readAsDataURL(file)});
  return{name:file.name||'reference',mime:file.type||'image/png',dataUrl};
}
function currentPassId(){
  const b=document.querySelector('#passes button.active .mini')||document.querySelector('#passes button .mini'),s=b&&b.textContent||'pass-1';return /^pass-/.test(s)?s:'pass-1';
}
function buildProjectBase(){
  const st=global.__JXAnatomyPreviewState;if(!st||!Array.isArray(st.joints)||!Array.isArray(st.bones)||!st.bones.length)throw new Error('Create or reload anatomy before saving project JSON');
  const canvas=byId('canvas'),species=(byId('species')||{}).value||'generic',passes=[{id:currentPassId(),kind:'fundamental',locked:false}],skeleton={joints:clone(st.joints),bones:clone(st.bones),passes};
  const desc=global.JXAnatomyImageFit.toAnatomyDescriptor(skeleton,canvas.width,canvas.height,{id:'jx-reloadable-anatomy',species});global.JXAnatomySemantics.attachBodyParts(desc,skeleton);
  const controls={mass:Number((byId('mass')||{}).value)||1,muscleTone:Number((byId('muscleTone')||{}).value)||.45,pumpedness:Number((byId('pumpedness')||{}).value)||.35,fatCover:Number((byId('fatCover')||{}).value)||.15,boneProminence:.35,size:1};
  for(const p of desc.bodyParts||[]){p.controls=clone(controls);p.anatomy=p.anatomy||{};p.anatomy.controls=clone(controls)}
  desc.kind='model';desc.model='anatomy';desc.projectFormat='jx.anatomy-project/1';desc.canvas={width:canvas.width,height:canvas.height};return desc;
}
function collectTextures(){
  const map=global.__JXAnatomyPreviewTextures,out=[];if(!(map instanceof Map))return out;
  for(const [bodyPart,t] of map.entries())if(t&&t.dataUrl)out.push({bodyPart,name:t.name||('jx-'+bodyPart+'.png'),mime:'image/png',dataUrl:t.dataUrl,flipU:!!t.flipU,flipV:!!t.flipV,opacity:Number.isFinite(Number(t.opacity))?Number(t.opacity):1,generated:!!t.generated,source:t.source||null,bake:t.bake||null});
  return out;
}
async function saveProject(){
  if(busy)return;busy=true;try{status('Packaging reloadable JX anatomy project…');const doc=buildProjectBase();doc.textureAssets=collectTextures();doc.referenceImage=await readReference();doc.savedAt=new Date().toISOString();const blob=new Blob([JSON.stringify(doc,null,2)],{type:'application/json'}),a=document.createElement('a'),url=URL.createObjectURL(blob);a.href=url;a.download='jx-anatomy-project.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1500);status('Saved reloadable JX project JSON with '+doc.textureAssets.length+' texture asset'+(doc.textureAssets.length===1?'':'s')+'.',true)}catch(err){status('Project JSON save failed: '+String(err&&err.message||err),false)}finally{busy=false}}
function makeUi(){
  if(ui)return ui;const imageInput=byId('imageFile');if(!imageInput)return null;
  const anchor=imageInput.closest('label'),box=document.createElement('div');box.className='box';box.style.marginTop='8px';box.innerHTML='<b style="color:#d9ebff">Reload JX model</b><label class="drop" style="margin-top:7px">Load JX anatomy JSON<input id="jxProjectJsonFile" type="file" accept="application/json,.json"></label><div id="jxProjectInfo" class="mini" style="margin-top:6px">Reloads geometry through the real Designer state so GLB build is immediately available.</div>';anchor.insertAdjacentElement('afterend',box);
  const exportBtn=byId('exportJSON'),save=document.createElement('button');save.type='button';save.id='saveJXProjectJSON';save.className='action';save.textContent='Save reloadable JX project JSON';if(exportBtn)exportBtn.insertAdjacentElement('afterend',save);
  const load=box.querySelector('#jxProjectJsonFile');load.addEventListener('change',e=>{const f=e.target.files&&e.target.files[0];if(f)loadFile(f);e.target.value=''});save.addEventListener('click',saveProject);ui={box,load,save,info:box.querySelector('#jxProjectInfo')};return ui;
}
function init(){makeUi()}
api.loadDocument=loadDocument;api.loadFile=loadFile;api.saveProject=saveProject;api.buildProjectBase=buildProjectBase;api.bodyGroups=bodyGroups;
global.JXAnatomyJsonProject=api;if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})(typeof window!=='undefined'?window:globalThis);
