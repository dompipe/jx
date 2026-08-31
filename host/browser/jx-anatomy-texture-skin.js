/* JX Anatomy PNG skinning for the image Designer.
 * A PNG is mapped into a strip mesh around the semantic muscle envelope.
 * Each quad is split into affine-warped triangles so the texture bends with
 * the bone chain on CPU Canvas2D. No WebGL or remote service is required.
 */
(function(global){
'use strict';

const clamp=(v,a,b)=>Math.max(a,Math.min(b,Number.isFinite(Number(v))?Number(v):a));
const hypot=(x,y)=>Math.sqrt(x*x+y*y);

function siblingSource(name){
  const mine=document.currentScript&&document.currentScript.src;
  return mine?mine.replace(/jx-anatomy-texture-skin\.js(?:\?.*)?$/,name):'../host/browser/'+name;
}
function loadSibling(name,test){
  if(typeof document==='undefined'||(test&&test()))return;
  if([...document.scripts].some(s=>s.src&&s.src.includes('/'+name)))return;
  const s=document.createElement('script');s.src=siblingSource(name);s.async=true;document.head.appendChild(s);
}
function installDesignerFamily(){
  if(typeof document==='undefined')return;
  const designer=document.getElementById('exportGLB')||/anatomy-image-skeleton-designer/i.test(location.pathname);if(!designer)return;
  loadSibling('jx-anatomy-preview.js',()=>!!global.JXAnatomyPreview);
  loadSibling('jx-anatomy-texture-bake.js',()=>!!global.JXAnatomyTextureBake);
  loadSibling('jx-anatomy-json-project.js',()=>!!global.JXAnatomyJsonProject);
  loadSibling('jx-anatomy-torso-tool.js',()=>!!global.JXAnatomyTorsoTool);
  loadSibling('jx-anatomy-head-tool.js',()=>!!global.JXAnatomyHeadTool);
}

/* The image Designer already loads this file last. Use that point to bridge
 * the live 2D JX anatomy state into the optional 3D JX preview without making
 * the editor's private state global. */
function installPreviewBridge(){
  if(typeof document==='undefined')return;
  if(global.JXAnatomySurface&&!global.JXAnatomySurface.__previewBridge){
    const original=global.JXAnatomySurface.drawSurfaces;
    global.JXAnatomySurface.drawSurfaces=function(ctx,parts,joints,bones,opts){
      const out=original.apply(this,arguments);
      global.__JXAnatomyPreviewState={parts,joints,bones,opts};
      if(global.JXAnatomyPreview)global.JXAnatomyPreview.update(parts,joints,bones,opts);
      return out;
    };
    global.JXAnatomySurface.__previewBridge=true;
  }
}

/* Preserve the exact GLB returned by the JX/PHP exporter, then expose it to
 * the first-class JX GLB Viewer. This avoids rebuilding or approximating the
 * exported model in the viewer. */
function installGLBViewerBridge(){
  if(typeof document==='undefined'||global.__JXGLBViewerBridgeInstalled)return;
  const exportBtn=document.getElementById('exportGLB');if(!exportBtn)return;
  global.__JXGLBViewerBridgeInstalled=true;
  const storeSrc=siblingSource('jx-glb-store.js');
  const storeReady=new Promise((resolve,reject)=>{
    if(global.JXGLBStore)return resolve(global.JXGLBStore);
    const existing=[...document.scripts].find(s=>/jx-glb-store\.js(?:\?|$)/.test(s.src));
    if(existing){existing.addEventListener('load',()=>resolve(global.JXGLBStore),{once:true});existing.addEventListener('error',reject,{once:true});return;}
    const s=document.createElement('script');s.src=storeSrc;s.async=true;s.onload=()=>resolve(global.JXGLBStore);s.onerror=reject;document.head.appendChild(s);
  });
  const viewBtn=document.createElement('button');viewBtn.type='button';viewBtn.id='openGLBViewer';viewBtn.className='action';viewBtn.disabled=true;viewBtn.textContent='Open exported GLB in JX Viewer';exportBtn.insertAdjacentElement('afterend',viewBtn);
  viewBtn.onclick=()=>global.open('jx-glb-viewer.html?source=last','_blank','noopener');
  storeReady.then(store=>store&&store.has('last')).then(has=>{viewBtn.disabled=!has}).catch(()=>{});
  const originalFetch=global.fetch.bind(global);
  global.fetch=async function(input,init){
    const response=await originalFetch(input,init);
    const url=typeof input==='string'?input:(input&&input.url)||'';
    if(response.ok&&/anatomy-export-glb\.php(?:\?|$)/.test(url)){
      const copy=response.clone();
      Promise.all([storeReady,copy.blob()]).then(([store,blob])=>store.save('last',blob,{name:'jx-anatomy-model.glb',source:'JX Anatomy Designer'})).then(()=>{viewBtn.disabled=false}).catch(()=>{});
    }
    return response;
  };
}

function triangleTransform(s0,s1,s2,d0,d1,d2){
  const x0=s0.x,y0=s0.y,x1=s1.x,y1=s1.y,x2=s2.x,y2=s2.y;
  const den=x0*(y1-y2)+x1*(y2-y0)+x2*(y0-y1);
  if(Math.abs(den)<1e-9)return null;
  const inv00=(y1-y2)/den,inv01=(x2-x1)/den,inv02=(x1*y2-x2*y1)/den;
  const inv10=(y2-y0)/den,inv11=(x0-x2)/den,inv12=(x2*y0-x0*y2)/den;
  const inv20=(y0-y1)/den,inv21=(x1-x0)/den,inv22=(x0*y1-x1*y0)/den;
  return {
    a:d0.x*inv00+d1.x*inv10+d2.x*inv20,
    c:d0.x*inv01+d1.x*inv11+d2.x*inv21,
    e:d0.x*inv02+d1.x*inv12+d2.x*inv22,
    b:d0.y*inv00+d1.y*inv10+d2.y*inv20,
    d:d0.y*inv01+d1.y*inv11+d2.y*inv21,
    f:d0.y*inv02+d1.y*inv12+d2.y*inv22
  };
}

function drawTriangle(ctx,img,s0,s1,s2,d0,d1,d2,alpha){
  const m=triangleTransform(s0,s1,s2,d0,d1,d2);if(!m)return;
  ctx.save();ctx.beginPath();ctx.moveTo(d0.x,d0.y);ctx.lineTo(d1.x,d1.y);ctx.lineTo(d2.x,d2.y);ctx.closePath();ctx.clip();
  ctx.globalAlpha=clamp(alpha,.02,1);ctx.setTransform(m.a,m.b,m.c,m.d,m.e,m.f);ctx.drawImage(img,0,0);ctx.restore();
}

function ringSides(r){
  return {
    top:{x:r.cx+r.nx*r.skinRadius,y:r.cy+r.ny*r.skinRadius},
    bottom:{x:r.cx-r.nx*r.skinRadius,y:r.cy-r.ny*r.skinRadius}
  };
}

function partMesh(part,jointsById,bonesById,surfaceApi,opts){
  const pieces=[],segments=(part&&part.segments)||[];
  let total=0;
  const lengths=segments.map(seg=>{const a=jointsById.get(seg.a),b=jointsById.get(seg.b);const L=a&&b?Math.max(1,hypot(b.x-a.x,b.y-a.y)):1;total+=L;return L});
  let acc=0;
  segments.forEach((seg,si)=>{
    const bone=bonesById.get(seg.id),a=jointsById.get(seg.a),b=jointsById.get(seg.b);if(!bone||!a||!b)return;
    const rings=surfaceApi.segmentRings(bone,a,b,opts),L=lengths[si];
    for(let i=0;i<rings.length-1;i++){
      const r0=rings[i],r1=rings[i+1],u0=(acc+L*r0.t)/Math.max(1,total),u1=(acc+L*r1.t)/Math.max(1,total);
      pieces.push({u0,u1,r0,r1,segment:seg.id});
    }
    acc+=L;
  });
  return pieces;
}

function drawPartTexture(ctx,part,jointsById,bonesById,surfaceApi,texture,opts){
  if(!texture||!texture.image||!texture.image.complete)return 0;
  const img=texture.image,w=Math.max(1,img.naturalWidth||img.width),h=Math.max(1,img.naturalHeight||img.height),flipU=!!texture.flipU,flipV=!!texture.flipV;
  const mesh=partMesh(part,jointsById,bonesById,surfaceApi,opts),alpha=clamp(texture.opacity??opts.textureOpacity??1,.02,1);
  let tris=0;
  mesh.forEach(q=>{
    const A=ringSides(q.r0),B=ringSides(q.r1),u0=(flipU?1-q.u0:q.u0)*w,u1=(flipU?1-q.u1:q.u1)*w,vt=flipV?h:0,vb=flipV?0:h;
    const sTL={x:u0,y:vt},sTR={x:u1,y:vt},sBR={x:u1,y:vb},sBL={x:u0,y:vb};
    drawTriangle(ctx,img,sTL,sTR,sBR,A.top,B.top,B.bottom,alpha);tris++;
    drawTriangle(ctx,img,sTL,sBR,sBL,A.top,B.bottom,A.bottom,alpha);tris++;
  });
  return tris;
}

function drawTextures(ctx,parts,joints,bones,surfaceApi,textures,opts){
  global.__JXAnatomyPreviewTextures=textures;
  if(global.JXAnatomyPreview)global.JXAnatomyPreview.setTextures(textures);
  const jointsById=new Map((joints||[]).map(j=>[j.id,j])),bonesById=new Map((bones||[]).map(b=>[b.id,b]));let tris=0;
  (parts||[]).forEach(part=>{const tex=textures instanceof Map?textures.get(part.id):textures&&textures[part.id];if(tex)tris+=drawPartTexture(ctx,part,jointsById,bonesById,surfaceApi,tex,opts);});
  return tris;
}

function textureDescriptor(partId,texture){
  if(!texture)return null;
  const desc={bodyPart:partId,mode:'png-skinned-mesh',source:texture.name||null,mime:texture.mime||'image/png',uv:{axis:'chain',u:'root-to-tip',v:'across-envelope'},flipU:!!texture.flipU,flipV:!!texture.flipV,opacity:clamp(texture.opacity??1,0,1)};
  if(texture.generated){desc.generated=true;desc.bake=texture.bake||null;desc.source='reference-image'}
  return desc;
}

const api={triangleTransform,drawTriangle,partMesh,drawPartTexture,drawTextures,textureDescriptor};
if(typeof module!=='undefined'&&module.exports)module.exports=api;
global.JXAnatomyTextureSkin=api;
installPreviewBridge();
installGLBViewerBridge();
installDesignerFamily();
})(typeof window!=='undefined'?window:globalThis);
