/* JX Anatomy reference-image texture baker.
 *
 * Converts the pixels already supplied to the Anatomy Designer into real
 * per-body-part PNG textures. Each semantic body part is unwrapped along its
 * chain (root -> tip) and across its fitted skin envelope. The visible side
 * of the reference image is mirrored onto the back half of the circumference
 * so the exported tube has a continuous 360-degree texture instead of an
 * untextured rear seam.
 *
 * Browser image decoding is used only to obtain source pixels. The resulting
 * PNG/data URL is placed in the same JX texture map consumed by AnatomyGLB.php,
 * so model export embeds the baked texture without a separate manual upload.
 */
(function(global){
'use strict';
if(typeof document==='undefined')return;

const api={};
let sourceImage=null,sourceName='reference',sourceUrl=null,ui=null;
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
    draw(dst,src,A.top,B.top,B.bottom,{x:x0,y:0},{x:x1,y:0},{x:x1,y:mid},1);
    draw(dst,src,A.top,B.bottom,A.bottom,{x:x0,y:0},{x:x1,y:mid},{x:x0,y:mid},1);
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
  const part=(st.parts||[]).find(p=>p.id===id)||(st.parts||[])[0],label=part.label||part.id;
  try{setBusy(true);setStatus('JX is baking '+label+' from the reference image…');await bakePart(part,options());redraw();setStatus('JX baked '+label+' into a PNG texture. It will be embedded in the GLB.',true)}catch(err){setStatus('Texture bake failed: '+String(err&&err.message||err),false)}finally{setBusy(false)}
}

async function bakeAll(overwrite=true){
  const st=state(),parts=st&&st.parts||[],map=textureMap();if(!parts.length){setStatus('Create body parts before baking textures.',false);return 0}
  let done=0;
  try{setBusy(true);for(const part of parts){if(!overwrite&&map&&map.has(part.id))continue;setStatus('JX texture bake '+(done+1)+' / '+parts.length+': '+(part.label||part.id)+'…');await bakePart(part,options());done++;await sleep()}redraw();setStatus('JX baked '+done+' body-part PNG texture'+(done===1?'':'s')+' from the reference image.',true);return done}catch(err){setStatus('Texture bake failed: '+String(err&&err.message||err),false);throw err}finally{setBusy(false)}
}

function exportTextures(){
  const map=textureMap(),out=[];if(!map)return out;
  for(const [bodyPart,tex] of map.entries()){
    if(!tex||!tex.dataUrl)continue;
    out.push({bodyPart,name:tex.name||('jx-baked-'+bodyPart+'.png'),mime:'image/png',dataUrl:tex.dataUrl,flipU:!!tex.flipU,flipV:!!tex.flipV,opacity:Number.isFinite(Number(tex.opacity))?Number(tex.opacity):1,generated:!!tex.generated,bake:tex.bake||null});
  }
  return out;
}

function refreshDescriptorTextures(model){
  if(!model||typeof model!=='object')return;
  const st=state(),map=textureMap(),skin=global.JXAnatomyTextureSkin;if(!st||!map||!skin)return;
  model.skinTextures=(st.parts||[]).map(p=>{
    const tex=map.get(p.id),desc=skin.textureDescriptor(p.id,tex);if(!desc)return null;
    if(tex&&tex.generated){desc.generated=true;desc.bake=tex.bake||null;desc.source='reference-image'}
    return desc;
  }).filter(Boolean);
}

function installExportBakeBridge(){
  if(global.__JXTextureBakeFetchInstalled)return;global.__JXTextureBakeFetchInstalled=true;
  const previousFetch=global.fetch.bind(global);
  global.fetch=async function(input,init){
    const url=typeof input==='string'?input:(input&&input.url)||'';
    if(/anatomy-export-glb\.php(?:\?|$)/.test(url)&&init&&typeof init.body==='string'&&sourceImage){
      const st=state(),map=textureMap(),missing=st&&map?(st.parts||[]).filter(p=>!map.has(p.id)):[];
      if(missing.length){
        setStatus('JX is auto-baking '+missing.length+' missing texture'+(missing.length===1?'':'s')+' before GLB export…');
        setBusy(true);
        try{for(const part of missing)await bakePart(part,options());redraw()}finally{setBusy(false)}
      }
      try{
        const payload=JSON.parse(init.body);payload.textures=exportTextures();refreshDescriptorTextures(payload.model);
        init=Object.assign({},init,{body:JSON.stringify(payload)});
        if(payload.textures.length)setStatus('JX baked and attached '+payload.textures.length+' texture'+(payload.textures.length===1?'':'s')+'. Building GLB…',true);
      }catch(err){console.warn('JX texture baker could not refresh export payload:',err)}
    }
    return previousFetch(input,init);
  };
}

function setBusy(busy){if(!ui)return;ui.selected.disabled=busy;ui.all.disabled=busy;ui.resolution.disabled=busy;ui.wrap.disabled=busy}

function captureReferenceFile(file){
  if(!file||!/^image\//i.test(file.type||''))return;
  if(sourceUrl)URL.revokeObjectURL(sourceUrl);sourceUrl=URL.createObjectURL(file);sourceName=file.name||'reference';
  const img=new Image();img.onload=()=>{sourceImage=img;setStatus('Reference pixels ready. GLB build will auto-bake missing JX textures.',true)};img.onerror=()=>setStatus('Could not decode reference image for texture baking.',false);img.src=sourceUrl;
}

function installSourceCapture(){
  const input=document.getElementById('imageFile');if(!input||input.dataset.jxTextureBakeCapture)return;
  input.dataset.jxTextureBakeCapture='1';input.addEventListener('change',e=>{const f=e.target.files&&e.target.files[0];if(f)captureReferenceFile(f)});
  if(input.files&&input.files[0])captureReferenceFile(input.files[0]);
}

function makeUi(){
  if(ui)return ui;
  const textureInfo=document.getElementById('textureInfo');if(!textureInfo)return null;
  const box=document.createElement('div');box.className='box';box.style.marginTop='8px';box.style.borderColor='#4a6585';
  box.innerHTML='<b style="color:#d9ebff">JX Texture Baker</b><div class="hint" style="margin:5px 0 8px">Generate real PNG skins from the reference image. Build GLB automatically bakes any missing body-part textures.</div>'+
    '<div class="row"><label>Atlas width</label><select id="jxBakeResolution"><option value="512">512</option><option value="1024" selected>1024</option><option value="2048">2048</option></select></div>'+
    '<div class="row"><label>Back side</label><select id="jxBakeWrap"><option value="mirror-back" selected>Mirror visible skin</option><option value="stretch-360">Stretch around 360°</option></select></div>'+
    '<button type="button" class="action primary" id="jxBakeSelected">Bake selected part from image</button>'+
    '<button type="button" class="action" id="jxBakeAll">Rebake ALL body-part textures</button>'+
    '<div id="jxBakeInfo" class="mini" style="margin-top:6px">Load a reference image, place anatomy, then bake—or just Build GLB.</div>';
  textureInfo.insertAdjacentElement('afterend',box);
  ui={box,selected:box.querySelector('#jxBakeSelected'),all:box.querySelector('#jxBakeAll'),resolution:box.querySelector('#jxBakeResolution'),wrap:box.querySelector('#jxBakeWrap'),info:box.querySelector('#jxBakeInfo')};
  ui.selected.addEventListener('click',bakeSelected);ui.all.addEventListener('click',()=>bakeAll(true).catch(()=>{}));return ui;
}

function init(){makeUi();installSourceCapture();installExportBakeBridge()}

api.bakeCanvas=bakeCanvas;api.bakePart=bakePart;api.bakeSelected=bakeSelected;api.bakeAll=bakeAll;api.exportTextures=exportTextures;api.captureReferenceFile=captureReferenceFile;api.options=options;
global.JXAnatomyTextureBake=api;
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})(typeof window!=='undefined'?window:globalThis);
