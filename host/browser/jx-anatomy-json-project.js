/* JX Anatomy project JSON round-trip support.
 *
 * Loads both the canonical JSON previously emitted by the Anatomy Designer and
 * the richer reloadable project JSON emitted by this module.  Existing JSON
 * with imageSkeleton data restores editable semantic geometry.  New project
 * JSON additionally carries the reference image and PNG texture assets so a
 * session can be reopened without rebuilding the model from scratch.
 */
(function(global){
'use strict';
if(typeof document==='undefined')return;

const api={};
let ui=null,busy=false;
const clone=v=>JSON.parse(JSON.stringify(v));
const sleep=ms=>new Promise(r=>setTimeout(r,ms));

function status(text,good){
  const f=document.getElementById('status');if(f)f.textContent=text;
  if(ui&&ui.info){ui.info.textContent=text;ui.info.style.color=good===true?'#89d895':good===false?'#f1bf69':'#94a3b7'}
}
function dataUrlToFile(dataUrl,name){
  const m=String(dataUrl||'').match(/^data:([^;,]+)(;base64)?,(.*)$/);if(!m)throw new Error('Invalid embedded reference image');
  const mime=m[1]||'image/png',raw=m[2]?atob(m[3]):decodeURIComponent(m[3]),bytes=new Uint8Array(raw.length);for(let i=0;i<raw.length;i++)bytes[i]=raw.charCodeAt(i);
  return new File([bytes],name||'jx-reference.png',{type:mime});
}
function canvasBlankFile(w,h){
  const c=document.createElement('canvas');c.width=Math.max(2,Math.round(w||960));c.height=Math.max(2,Math.round(h||640));
  c.getContext('2d').fillRect(0,0,c.width,c.height);
  const data=c.toDataURL('image/png');return dataUrlToFile(data,'jx-json-reference-placeholder.png');
}
function assignFile(input,file){
  const dt=new DataTransfer();dt.items.add(file);input.files=dt.files;input.dispatchEvent(new Event('change',{bubbles:true}));
}
async function waitEditorReady(w,h){
  const canvas=document.getElementById('canvas'),start=document.getElementById('startPart');
  for(let i=0;i<100;i++){
    if(start&&!start.disabled&&canvas&&(!w||Math.abs(canvas.width-w)<=2)&&(!h||Math.abs(canvas.height-h)<=2))return;
    await sleep(30);
  }
  if(!start||start.disabled)throw new Error('Designer did not finish loading the JSON workspace');
}
function canvasEvent(x,y){
  const c=document.getElementById('canvas'),r=c.getBoundingClientRect(),cx=r.left+(x/c.width)*r.width,cy=r.top+(y/c.height)*r.height;
  const C=global.PointerEvent||global.MouseEvent;return new C('pointerdown',{bubbles:true,clientX:cx,clientY:cy,button:0,pointerId:1});
}
async function exposeLiveArrays(){
  // A temporary two-point semantic part forces drawSurfaces(), whose bridge
  // publishes direct references to the Designer's private arrays.
  const type=document.getElementById('partType'),side=document.getElementById('side'),start=document.getElementById('startPart'),canvas=document.getElementById('canvas');
  type.value='beak';side.value='center';start.click();canvas.dispatchEvent(canvasEvent(12,12));canvas.dispatchEvent(canvasEvent(28,28));
  for(let i=0;i<30;i++){const st=global.__JXAnatomyPreviewState;if(st&&Array.isArray(st.joints)&&Array.isArray(st.bones))return st;await sleep(20)}
  throw new Error('Could not attach JSON loader to live JX anatomy state');
}
function sourceSkeleton(doc){
  if(doc&&doc.imageSkeleton&&Array.isArray(doc.imageSkeleton.joints)&&Array.isArray(doc.imageSkeleton.bones))return doc.imageSkeleton;
  if(doc&&doc.editorState&&Array.isArray(doc.editorState.joints)&&Array.isArray(doc.editorState.bones))return doc.editorState;
  throw new Error('JSON has no JX imageSkeleton/editorState to reload');
}
function currentPassId(){
  const b=document.querySelector('#passes button.active .mini')||document.querySelector('#passes button .mini');
  const s=b&&b.textContent||'pass-1';return /^pass-/.test(s)?s:'pass-1';
}
function remapSkeleton(sk){
  const stamp='r'+Date.now().toString(36),jMap=new Map(),bpMap=new Map(),pass=currentPassId();
  const joints=(sk.joints||[]).map((j,i)=>{const old=String(j.id||('joint-'+i)),id=stamp+'-j-'+i;jMap.set(old,id);const q=clone(j);q.id=id;q.pass=pass;if(q.bodyPart){const b=String(q.bodyPart);if(!bpMap.has(b))bpMap.set(b,stamp+'-bp-'+bpMap.size);q.bodyPart=bpMap.get(b)}if(Array.isArray(q.bodyParts))q.bodyParts=q.bodyParts.map(b=>{b=String(b);if(!bpMap.has(b))bpMap.set(b,stamp+'-bp-'+bpMap.size);return bpMap.get(b)});return q});
  const bones=(sk.bones||[]).map((b,i)=>{const q=clone(b),oldBp=q.bodyPart!=null?String(q.bodyPart):null;q.id=stamp+'-b-'+i;q.a=jMap.get(String(b.a))||String(b.a);q.b=jMap.get(String(b.b))||String(b.b);q.pass=pass;if(oldBp){if(!bpMap.has(oldBp))bpMap.set(oldBp,stamp+'-bp-'+bpMap.size);q.bodyPart=bpMap.get(oldBp)}return q});
  return{joints,bones,bpMap,stamp};
}
function setControl(id,value){const el=document.getElementById(id);if(el&&value!=null&&Number.isFinite(Number(value)))el.value=String(value)}
function restoreControls(doc){
  const bp=(doc.bodyParts||[])[0]||{},c=bp.controls||(bp.anatomy&&bp.anatomy.controls)||((doc.surfaces||[])[0]&&doc.surfaces[0].controls)||{};
  setControl('mass',c.mass);setControl('muscleTone',c.muscleTone);setControl('pumpedness',c.pumpedness);setControl('fatCover',c.fatCover);
  const species=document.getElementById('species');if(species&&doc.species&&[...species.options].some(o=>o.value===doc.species||o.text===doc.species))species.value=doc.species;
}
function redraw(){const el=document.getElementById('mass')||document.getElementById('muscleTone');if(el)el.dispatchEvent(new Event('input',{bubbles:true}))}
function textureAssets(doc){return Array.isArray(doc.textureAssets)?doc.textureAssets:[]}
async function restoreTextures(doc,bpMap){
  const assets=textureAssets(doc);if(!assets.length)return 0;
  redraw();await sleep(30);const map=global.__JXAnatomyPreviewTextures;if(!(map instanceof Map))return 0;
  map.clear();let count=0;
  for(const a of assets){if(!a||!a.dataUrl)continue;const target=bpMap.get(String(a.bodyPart))||String(a.bodyPart),img=new Image();await new Promise((resolve,reject)=>{img.onload=resolve;img.onerror=reject;img.src=a.dataUrl});map.set(target,{image:img,url:a.dataUrl,dataUrl:a.dataUrl,name:a.name||('jx-restored-'+target+'.png'),mime:'image/png',flipU:!!a.flipU,flipV:!!a.flipV,opacity:Number.isFinite(Number(a.opacity))?Number(a.opacity):1,generated:!!a.generated,source:a.source||'project-json',bake:a.bake||null});count++}
  redraw();return count;
}
async function loadDocument(doc,fileName){
  if(busy)return;busy=true;try{
    if(!doc||typeof doc!=='object')throw new Error('Invalid JSON object');
    if(doc.model&&doc.model!=='anatomy'&&doc.kind!=='jx-anatomy-project')throw new Error('JSON is not a JX Anatomy model');
    const sk=sourceSkeleton(doc),w=Number(sk.width)||Number(doc.canvas&&doc.canvas.width)||960,h=Number(sk.height)||Number(doc.canvas&&doc.canvas.height)||640,input=document.getElementById('imageFile');
    let refFile;if(doc.referenceImage&&doc.referenceImage.dataUrl)refFile=dataUrlToFile(doc.referenceImage.dataUrl,doc.referenceImage.name||'jx-reference');else refFile=canvasBlankFile(w,h);
    status('Reloading JX anatomy JSON…');assignFile(input,refFile);await waitEditorReady(w,h);
    const live=await exposeLiveArrays(),mapped=remapSkeleton(sk);live.joints.splice(0,live.joints.length,...mapped.joints);live.bones.splice(0,live.bones.length,...mapped.bones);
    restoreControls(doc);redraw();await sleep(50);const textures=await restoreTextures(doc,mapped.bpMap);redraw();
    status('Reloaded '+(fileName||'JX anatomy JSON')+': '+mapped.joints.length+' ports · '+mapped.bones.length+' segments'+(textures?' · '+textures+' PNG textures':'')+'.',true);
  }catch(err){status('JSON reload failed: '+String(err&&err.message||err),false)}finally{busy=false;if(ui)ui.load.disabled=false}
}
async function loadFile(file){if(!file)return;try{const text=await file.text(),doc=JSON.parse(text);await loadDocument(doc,file.name)}catch(err){status('JSON reload failed: '+String(err&&err.message||err),false)}}

async function readReference(){
  const input=document.getElementById('imageFile'),file=input&&input.files&&input.files[0];if(!file)return null;
  const dataUrl=await new Promise((resolve,reject)=>{const r=new FileReader();r.onload=()=>resolve(String(r.result||''));r.onerror=()=>reject(r.error);r.readAsDataURL(file)});
  return{name:file.name||'reference',mime:file.type||'image/png',dataUrl};
}
function buildProjectBase(){
  const st=global.__JXAnatomyPreviewState;if(!st||!Array.isArray(st.joints)||!Array.isArray(st.bones)||!st.bones.length)throw new Error('Create or reload anatomy before saving project JSON');
  const canvas=document.getElementById('canvas'),species=(document.getElementById('species')||{}).value||'generic',passes=[{id:currentPassId(),kind:'fundamental',locked:false}],skeleton={joints:clone(st.joints),bones:clone(st.bones),passes};
  const desc=global.JXAnatomyImageFit.toAnatomyDescriptor(skeleton,canvas.width,canvas.height,{id:'jx-reloadable-anatomy',species});global.JXAnatomySemantics.attachBodyParts(desc,skeleton);
  const controls={mass:Number((document.getElementById('mass')||{}).value)||1,muscleTone:Number((document.getElementById('muscleTone')||{}).value)||.45,pumpedness:Number((document.getElementById('pumpedness')||{}).value)||.35,fatCover:Number((document.getElementById('fatCover')||{}).value)||.15,boneProminence:.35,size:1};
  for(const p of desc.bodyParts||[]){p.controls=clone(controls);p.anatomy=p.anatomy||{};p.anatomy.controls=clone(controls)}
  desc.kind='model';desc.model='anatomy';desc.projectFormat='jx.anatomy-project/1';desc.canvas={width:canvas.width,height:canvas.height};return desc;
}
function collectTextures(){
  const map=global.__JXAnatomyPreviewTextures,out=[];if(!(map instanceof Map))return out;
  for(const [bodyPart,t] of map.entries()){if(!t||!t.dataUrl)continue;out.push({bodyPart,name:t.name||('jx-'+bodyPart+'.png'),mime:'image/png',dataUrl:t.dataUrl,flipU:!!t.flipU,flipV:!!t.flipV,opacity:Number.isFinite(Number(t.opacity))?Number(t.opacity):1,generated:!!t.generated,source:t.source||null,bake:t.bake||null})}
  return out;
}
async function saveProject(){
  if(busy)return;busy=true;try{status('Packaging reloadable JX anatomy project…');const doc=buildProjectBase();doc.textureAssets=collectTextures();doc.referenceImage=await readReference();doc.savedAt=new Date().toISOString();const blob=new Blob([JSON.stringify(doc,null,2)],{type:'application/json'}),a=document.createElement('a'),url=URL.createObjectURL(blob);a.href=url;a.download='jx-anatomy-project.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1500);status('Saved reloadable JX project JSON with '+doc.textureAssets.length+' texture asset'+(doc.textureAssets.length===1?'':'s')+'.',true)}catch(err){status('Project JSON save failed: '+String(err&&err.message||err),false)}finally{busy=false}}

function makeUi(){
  if(ui)return ui;const imageInput=document.getElementById('imageFile');if(!imageInput)return null;
  const anchor=imageInput.closest('label'),box=document.createElement('div');box.className='box';box.style.marginTop='8px';box.innerHTML='<b style="color:#d9ebff">Reload JX model</b><label class="drop" style="margin-top:7px">Load JX anatomy JSON<input id="jxProjectJsonFile" type="file" accept="application/json,.json"></label><div id="jxProjectInfo" class="mini" style="margin-top:6px">Loads existing Anatomy JSON or a reloadable JX project JSON.</div>';anchor.insertAdjacentElement('afterend',box);
  const exportBtn=document.getElementById('exportJSON'),save=document.createElement('button');save.type='button';save.id='saveJXProjectJSON';save.className='action';save.textContent='Save reloadable JX project JSON';if(exportBtn)exportBtn.insertAdjacentElement('afterend',save);
  const load=box.querySelector('#jxProjectJsonFile');load.addEventListener('change',e=>{const f=e.target.files&&e.target.files[0];if(f)loadFile(f);e.target.value=''});save.addEventListener('click',saveProject);ui={box,load,save,info:box.querySelector('#jxProjectInfo')};return ui;
}
function init(){makeUi()}
api.loadDocument=loadDocument;api.loadFile=loadFile;api.saveProject=saveProject;api.buildProjectBase=buildProjectBase;
global.JXAnatomyJsonProject=api;if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})(typeof window!=='undefined'?window:globalThis);
