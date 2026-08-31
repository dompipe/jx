/* JX Anatomy reference-image texture baker.
 *
 * Converts the pixels already supplied to the Anatomy Designer into real
 * per-body-part PNG textures.  Each semantic body part is unwrapped along its
 * chain (root -> tip) and across its fitted skin envelope.  The visible side
 * of the reference image is mirrored onto the back half of the circumference
 * so the exported tube has a continuous 360-degree texture instead of an
 * untextured rear seam.
 *
 * Browser image decoding is used only to obtain source pixels.  The resulting
 * PNG/data URL is placed in the same JX texture map consumed by AnatomyGLB.php,
 * so model export embeds the baked texture without a separate manual upload.
 */
(function(global){
'use strict';
if(typeof document==='undefined')return;

const api={};
let sourceImage=null,sourceName='reference',sourceUrl=null,ui=null;
const clamp=(v,a,b)=>Math.max(a,Math.min(b,Number.isFinite(Number(v))?Number(v):a));
const sleep=()=>new Promise(r=>requestAnimationFrame(()=>r()));

function setStatus(text,good){
  if(ui&&ui.info){ui.info.textContent=text;ui.info.style.color=good===true?'#89d895':good===false?'#f1bf69':'#94a3b7'}
  const footer=document.getElementById('status');if(footer&&text)footer.textContent=text;
}

function state(){return global.__JXAnatomyPreviewState||null}
function textureMap(){return global.__JXAnatomyPreviewTextures instanceof Map?global.__JXAnatomyPreviewTextures:null}
function sourceCanvas(){
  const editor=document.getElementById('canvas');
  if(!editor||!sourceImage)return null;
  const c=document.createElement('canvas');c.width=editor.width;c.height=editor.height;
  const cx=c.getContext('2d',{alpha:false});cx.drawImage(sourceImage,0,0,c.width,c.height);return c;
}
function ringSides(r){return{top:{x:r.cx+r.nx*r.skinRadius,y:r.cy+r.ny*r.skinRadius},bottom:{x:r.cx-r.nx*r.skinRadius,y:r.cy-r.ny*r.skinRadius}}}

function drawQuad(dst,src,q,w,h,mirrorBack){
  const A=ringSides(q.r0),B=ringSides(q.r1),x0=q.u0*w,x1=q.u1*w;
  const draw=global.JXAnatomyTextureSkin&&global.JXAnatomyTextureSkin.drawTriangle;
  if(typeof draw!=='function')throw new Error('JX texture skin mapper is not loaded');
  if(mirrorBack){
    const mid=h/2;
    // Visible/front hemisphere: top silhouette -> bottom silhouette.
    draw(dst,src,A.top,B.top,B.bottom,{x:x0,y:0},{x:x1,y:0},{x:x1,y:mid},1);
    draw(dst,src,A.top,B.bottom,A.bottom,{x:x0,y:0},{x:x1,y:mid},{x:x0,y:mid},1);
    // Rear hemisphere mirrors the visible projection.  This joins continuously
    // at both circumference seams and avoids a blank/uninitialized back side.
    draw(dst,src,A.bottom,B.bottom,B.top,{x:x0,y:mid},{x:x1,y:mid},{x:x1,y:h},1);
    draw(dst,src,A.bottom,B.top,A.top,{x:x0,y:mid},{x:x1,y:h},{x:x0,y:h},1);
  }else{
    draw(dst,src,A.top,B.top,B.bottom,{x:x0,y:0},{x:x1,y:0},{x:x1,y:h},1);
    draw(dst,src,A.top,B.bottom,A.bottom,{x:x0,y:0},{x:x1,y:h},{x:x0,y:h},1);
  }
}

function bakeCanvas(part,opts){
  const st=state(),src=sourceCanvas();if(!st)throw new Error('Draw/finalize a body part first');if(!src)throw new Error('Load a reference image first');
  const width=Math.max(128,Math.min(4096,Math.round(Number(opts&&opts.width)||1024))),height=Math.max(64,Math.min(2048,Math.round(Number(opts&&opts.height)||width/2)));
  const out=document.createElement('canvas');out.width=width;out.height=height;
  const cx=out.getContext('2d',{alpha:true});cx.clearRect(0,0,width,height);cx.imageSmoothingEnabled=true;cx.imageSmoothingQuality='high';
  const jointsById=new Map((st.joints||[]).map(j=>[j.id,j])),bonesById=new Map((st.bones||[]).map(b=>[b.id,b]));
  const mesh=global.JXAnatomyTextureSkin.partMesh(part,jointsById,bonesById,global.JXAnatomySurface,st.opts||{});
  if(!mesh.length)throw new Error('Body part has no surface mesh to bake');
  const mirrorBack=(opts&&opts.wrap)!=='stretch-360';
  for(const q of mesh)drawQuad(cx,src,q,width,height,mirrorBack);
  return out;
}

function canvasToTexture(canvas,part,opts){
  return new Promise((resolve,reject)=>{
    const dataUrl=canvas.toDataURL('image/png'),img=new Image();
    img.onload=()=>resolve({
      image:img,url:dataUrl,dataUrl,name:'jx-baked-'+part.id+'.png',mime:'image/png',flipU:false,flipV:false,opacity:1,
      generated:true,source:'reference-image',bake:{engine:'jx.anatomy-texture-bake/1',bodyPart:part.id,width:canvas.width,height:canvas.height,wrap:(opts&&opts.wrap)||'mirror-back',sourceName}
    });
    img.onerror=()=>reject(new Error('Could not create baked PNG'));
    img.src=dataUrl;
  });
}

async function bakePart(part,opts){
  const map=textureMap();if(!map)throw new Error('JX texture map is not ready; draw the model once first');
  const canvas=bakeCanvas(part,opts),texture=await canvasToTexture(canvas,part,opts);map.set(part.id,texture);return texture;
}

function redraw(){
  const el=document.getElementById('mass')||document.getElementById('muscleTone');
  if(el)el.dispatchEvent(new Event('input',{bubbles:true}));
}

function options(){
  const width=ui?Number(ui.resolution.value)||1024:1024;
  return{width,height:Math.round(width/2),wrap:ui&&ui.wrap.value||'mirror-back'};
}

async function bakeSelected(){
  const st=state();if(!st||!(st.parts||[]).length){setStatus('Create a body part before baking texture.',false);return}
  const id=(document.getElementById('texturePart')||{}).value||'';
  const part=(st.parts||[]).find(p=>p.id===id)||(st.parts||[])[0];
  try{setBusy(true);setStatus('JX is baking '+part.label+' from the reference image…');await bakePart(part,options());redraw();setStatus('JX baked '+part.label+' into a PNG texture. It will be embedded in the GLB.',true)}catch(err){setStatus('Texture bake failed: '+String(err&&err.message||err),false)}finally{setBusy(false)}
}

async function bakeAll(){
  const st=state(),parts=st&&st.parts||[];if(!parts.length){setStatus('Create body parts before baking textures.',false);return}
  try{setBusy(true);let i=0;for(const part of parts){setStatus('JX texture bake '+(++i)+' / '+parts.length+': '+part.label+'…');await bakePart(part,options());await sleep()}redraw();setStatus('JX baked '+parts.length+' body-part PNG texture'+(parts.length===1?'':'s')+' from the reference image.',true)}catch(err){setStatus('Texture bake failed: '+String(err&&err.message||err),false)}finally{setBusy(false)}
}

function setBusy(busy){if(!ui)return;ui.selected.disabled=busy;ui.all.disabled=busy;ui.resolution.disabled=busy;ui.wrap.disabled=busy}

function captureReferenceFile(file){
  if(!file||!/^image\//i.test(file.type||''))return;
  if(sourceUrl)URL.revokeObjectURL(sourceUrl);sourceUrl=URL.createObjectURL(file);sourceName=file.name||'reference';
  const img=new Image();img.onload=()=>{sourceImage=img;setStatus('Reference pixels ready for JX texture baking.',true)};img.onerror=()=>setStatus('Could not decode reference image for texture baking.',false);img.src=sourceUrl;
}

function installSourceCapture(){
  const input=document.getElementById('imageFile');if(!input||input.dataset.jxTextureBakeCapture)return;
  input.dataset.jxTextureBakeCapture='1';input.addEventListener('change',e=>{const f=e.target.files&&e.target.files[0];if(f)captureReferenceFile(f)});
}

function makeUi(){
  if(ui)return ui;
  const textureInfo=document.getElementById('textureInfo');if(!textureInfo)return null;
  const box=document.createElement('div');box.className='box';box.style.marginTop='8px';box.style.borderColor='#4a6585';
  box.innerHTML='<b style="color:#d9ebff">JX Texture Baker</b><div class="hint" style="margin:5px 0 8px">Generate real PNG skins from the reference image and bind them to the semantic model.</div>'+
    '<div class="row"><label>Atlas width</label><select id="jxBakeResolution"><option value="512">512</option><option value="1024" selected>1024</option><option value="2048">2048</option></select></div>'+
    '<div class="row"><label>Back side</label><select id="jxBakeWrap"><option value="mirror-back" selected>Mirror visible skin</option><option value="stretch-360">Stretch around 360°</option></select></div>'+
    '<button type="button" class="action primary" id="jxBakeSelected">Bake selected part from image</button>'+
    '<button type="button" class="action" id="jxBakeAll">Bake ALL body-part textures</button>'+
    '<div id="jxBakeInfo" class="mini" style="margin-top:6px">Load a reference image, place anatomy, then bake.</div>';
  textureInfo.insertAdjacentElement('afterend',box);
  ui={box,selected:box.querySelector('#jxBakeSelected'),all:box.querySelector('#jxBakeAll'),resolution:box.querySelector('#jxBakeResolution'),wrap:box.querySelector('#jxBakeWrap'),info:box.querySelector('#jxBakeInfo')};
  ui.selected.addEventListener('click',bakeSelected);ui.all.addEventListener('click',bakeAll);return ui;
}

function init(){makeUi();installSourceCapture();if(global.__JXAnatomyReferenceFile)captureReferenceFile(global.__JXAnatomyReferenceFile)}

api.bakeCanvas=bakeCanvas;api.bakePart=bakePart;api.bakeSelected=bakeSelected;api.bakeAll=bakeAll;api.captureReferenceFile=captureReferenceFile;api.options=options;
global.JXAnatomyTextureBake=api;
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})(typeof window!=='undefined'?window:globalThis);
