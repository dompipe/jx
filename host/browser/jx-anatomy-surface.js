/* JX Anatomy semantic surface preview.
 * CPU-only 2D anatomical envelopes for the image Designer.
 * Bones remain the rig; muscle masses and skin are derived from semantic body parts.
 */
(function(global){
'use strict';

const clamp=(v,a,b)=>Math.max(a,Math.min(b,Number.isFinite(Number(v))?Number(v):a));
const lerp=(a,b,t)=>a+(b-a)*t;
const hypot=(x,y)=>Math.sqrt(x*x+y*y);

const SKINS={
  human:{label:'Human skin',base:'#c58f70',muscle:'#a95f55',highlight:'#e5b18d',pattern:'skin'},
  pale:{label:'Pale skin',base:'#e2baa2',muscle:'#b66d65',highlight:'#f2d0ba',pattern:'skin'},
  dark:{label:'Dark skin',base:'#6d4335',muscle:'#7d3f3c',highlight:'#9f6752',pattern:'skin'},
  fur:{label:'Fur',base:'#8a6a4d',muscle:'#795046',highlight:'#b39572',pattern:'fur'},
  scales:{label:'Scales',base:'#66785b',muscle:'#7b514a',highlight:'#98aa78',pattern:'scales'},
  feathers:{label:'Feathers',base:'#8a8f96',muscle:'#7f5550',highlight:'#c2c7ce',pattern:'feathers'},
  hide:{label:'Hide',base:'#9a7658',muscle:'#82504a',highlight:'#bea07f',pattern:'hide'}
};

const MUSCLE_PROFILES={
  'upper-arm':{start:.82,mid:1.38,end:.82,offset:.10,groups:['biceps','triceps']},
  forearm:{start:1.12,mid:1.28,end:.62,offset:.08,groups:['flexor','extensor']},
  thigh:{start:1.24,mid:1.48,end:.86,offset:.10,groups:['quadriceps','hamstring']},
  shin:{start:1.05,mid:1.20,end:.55,offset:.08,groups:['calf','tibialis']},
  foot:{start:.70,mid:.82,end:.62,offset:.03,groups:['foot']},
  humerus:{start:1.12,mid:1.34,end:.78,offset:.09,groups:['upper-limb']},
  'radius-ulna':{start:1.02,mid:1.18,end:.58,offset:.07,groups:['forelimb']},
  metacarpal:{start:.65,mid:.72,end:.50,offset:.03,groups:['distal']},
  femur:{start:1.18,mid:1.44,end:.82,offset:.10,groups:['thigh']},
  tibia:{start:1.02,mid:1.20,end:.58,offset:.07,groups:['lower-leg']},
  metatarsal:{start:.60,mid:.70,end:.46,offset:.03,groups:['distal']},
  'wing-upper':{start:.82,mid:1.02,end:.68,offset:.04,groups:['flight']},
  'wing-lower':{start:.70,mid:.88,end:.50,offset:.03,groups:['flight']},
  'wing-hand':{start:.52,mid:.62,end:.34,offset:.02,groups:['flight']},
  neck:{start:1.06,mid:1.14,end:.90,offset:.03,groups:['neck']},
  tail:{start:1.0,mid:.86,end:.26,offset:.02,groups:['tail']},
  spine:{start:1.20,mid:1.35,end:1.05,offset:.02,groups:['torso']},
  jaw:{start:.95,mid:1.05,end:.60,offset:.02,groups:['jaw']},
  beak:{start:.82,mid:.58,end:.15,offset:0,groups:['beak']},
  snout:{start:1.02,mid:1.00,end:.72,offset:.01,groups:['snout']},
  bone:{start:1,mid:1,end:1,offset:0,groups:['generic']}
};

function profileFor(semantic){return MUSCLE_PROFILES[String(semantic||'').toLowerCase()]||MUSCLE_PROFILES.bone;}
function widthAt(profile,t){
  if(t<=.5)return lerp(profile.start,profile.mid,t*2);
  return lerp(profile.mid,profile.end,(t-.5)*2);
}

function boneBaseWidth(bone,a,b,opts){
  const L=Math.max(1,hypot(b.x-a.x,b.y-a.y));
  const fitted=Number(bone.fit&&bone.fit.width);
  if(Number.isFinite(fitted)&&fitted>0)return fitted;
  const semantic=String(bone.semantic||'');
  let f=.09;
  if(/thigh|upper-arm|humerus|femur|spine/.test(semantic))f=.12;
  if(/wing|tail|forearm|shin|tibia|radius/.test(semantic))f=.08;
  if(/foot|metacarpal|metatarsal|beak/.test(semantic))f=.055;
  return Math.max(4,L*f*(opts&&opts.mass||1));
}

function segmentRings(bone,a,b,opts){
  opts=opts||{};
  const dx=b.x-a.x,dy=b.y-a.y,L=Math.max(1,hypot(dx,dy)),tx=dx/L,ty=dy/L,nx=-ty,ny=tx;
  const profile=profileFor(bone.semantic),base=boneBaseWidth(bone,a,b,opts);
  const tone=clamp(opts.muscleTone,0,1),pump=clamp(opts.pumpedness,0,2),fat=clamp(opts.fatCover,0,2),mass=clamp(opts.mass,.2,3);
  const muscleGain=1+tone*.22+pump*.34;
  const skinPad=base*(.18+fat*.30);
  const samples=Math.max(8,Math.min(32,Math.round(L/18)));
  const rings=[];
  for(let i=0;i<=samples;i++){
    const t=i/samples,bulge=widthAt(profile,t),cx=lerp(a.x,b.x,t),cy=lerp(a.y,b.y,t);
    const mr=base*bulge*muscleGain*mass;
    rings.push({t,cx,cy,nx,ny,muscleRadius:mr,skinRadius:mr+skinPad,profile});
  }
  return rings;
}

function pathFromRings(ctx,rings,field){
  if(!rings||rings.length<2)return false;
  ctx.beginPath();
  let r=rings[0],rad=r[field];ctx.moveTo(r.cx+r.nx*rad,r.cy+r.ny*rad);
  for(let i=1;i<rings.length;i++){r=rings[i];rad=r[field];ctx.lineTo(r.cx+r.nx*rad,r.cy+r.ny*rad);}
  for(let i=rings.length-1;i>=0;i--){r=rings[i];rad=r[field];ctx.lineTo(r.cx-r.nx*rad,r.cy-r.ny*rad);}
  ctx.closePath();return true;
}

function muscleLobes(bone,a,b,opts){
  opts=opts||{};
  const dx=b.x-a.x,dy=b.y-a.y,L=Math.max(1,hypot(dx,dy)),tx=dx/L,ty=dy/L,nx=-ty,ny=tx;
  const p=profileFor(bone.semantic),base=boneBaseWidth(bone,a,b,opts),tone=clamp(opts.muscleTone,0,1),pump=clamp(opts.pumpedness,0,2),mass=clamp(opts.mass,.2,3);
  const names=p.groups||['muscle'];
  const lobes=[];
  if(names.length===1){
    lobes.push({name:names[0],t:.50,offset:0,length:.72,radius:base*(1.02+tone*.25+pump*.30)*mass});
  }else{
    names.slice(0,2).forEach((name,i)=>lobes.push({name,t:i? .54:.46,offset:(i?1:-1)*base*(.24+p.offset),length:i?.62:.68,radius:base*(.70+tone*.30+pump*.28)*mass}));
  }
  return lobes.map(l=>({
    name:l.name,
    cx:lerp(a.x,b.x,l.t)+nx*l.offset,
    cy:lerp(a.y,b.y,l.t)+ny*l.offset,
    angle:Math.atan2(dy,dx),
    rx:Math.max(4,L*l.length*.50),
    ry:Math.max(3,l.radius)
  }));
}

function drawLobe(ctx,lobe,color,alpha){
  ctx.save();ctx.translate(lobe.cx,lobe.cy);ctx.rotate(lobe.angle);ctx.beginPath();ctx.ellipse(0,0,lobe.rx,lobe.ry,0,0,Math.PI*2);ctx.fillStyle=color;ctx.globalAlpha=alpha;ctx.fill();ctx.restore();
}

function drawSkinPattern(ctx,pathFn,skin,opts,bounds){
  ctx.save();pathFn();ctx.clip();
  const alpha=clamp(opts.skinOpacity,.05,1);ctx.globalAlpha=alpha;ctx.fillStyle=skin.base;ctx.fillRect(bounds.x,bounds.y,bounds.w,bounds.h);
  ctx.globalAlpha=alpha*.22;ctx.strokeStyle=skin.highlight;ctx.lineWidth=1;
  if(skin.pattern==='fur'){
    for(let y=bounds.y;y<bounds.y+bounds.h;y+=7)for(let x=bounds.x;x<bounds.x+bounds.w;x+=9){ctx.beginPath();ctx.moveTo(x,y);ctx.lineTo(x+3,y-5);ctx.stroke();}
  }else if(skin.pattern==='scales'){
    for(let y=bounds.y;y<bounds.y+bounds.h;y+=10)for(let x=bounds.x+(Math.floor(y/10)%2)*5;x<bounds.x+bounds.w;x+=10){ctx.beginPath();ctx.arc(x,y,5,0,Math.PI);ctx.stroke();}
  }else if(skin.pattern==='feathers'){
    for(let y=bounds.y;y<bounds.y+bounds.h;y+=12)for(let x=bounds.x+(Math.floor(y/12)%2)*7;x<bounds.x+bounds.w;x+=14){ctx.beginPath();ctx.ellipse(x,y,7,3,-.25,0,Math.PI*2);ctx.stroke();}
  }else{
    for(let y=bounds.y;y<bounds.y+bounds.h;y+=14){ctx.beginPath();ctx.moveTo(bounds.x,y);ctx.lineTo(bounds.x+bounds.w,y+3);ctx.stroke();}
  }
  ctx.restore();
}

function segmentBounds(rings){
  let minX=Infinity,minY=Infinity,maxX=-Infinity,maxY=-Infinity;
  rings.forEach(r=>{const d=r.skinRadius+4;minX=Math.min(minX,r.cx-d);minY=Math.min(minY,r.cy-d);maxX=Math.max(maxX,r.cx+d);maxY=Math.max(maxY,r.cy+d);});
  return{x:minX,y:minY,w:maxX-minX,h:maxY-minY};
}

function drawBodyPart(ctx,part,jointsById,bonesById,opts){
  opts=opts||{};const skin=SKINS[opts.skin]||SKINS.human,showMuscle=opts.showMuscle!==false,showSkin=opts.showSkin!==false;
  const rendered=[];
  (part.segments||[]).forEach(seg=>{
    const bone=bonesById.get(seg.id),a=jointsById.get(seg.a),b=jointsById.get(seg.b);if(!bone||!a||!b)return;
    const rings=segmentRings(bone,a,b,opts);rendered.push({seg,bone,rings});
    if(showMuscle){
      pathFromRings(ctx,rings,'muscleRadius');ctx.save();ctx.globalAlpha=clamp(opts.muscleOpacity,.1,1);ctx.fillStyle=skin.muscle;ctx.fill();ctx.restore();
      muscleLobes(bone,a,b,opts).forEach((l,i)=>drawLobe(ctx,l,i%2?skin.muscle:skin.highlight,clamp(opts.muscleOpacity,.1,1)*.45));
    }
    if(showSkin){
      const bounds=segmentBounds(rings),pathFn=()=>pathFromRings(ctx,rings,'skinRadius');drawSkinPattern(ctx,pathFn,skin,opts,bounds);
      pathFn();ctx.save();ctx.strokeStyle=skin.highlight;ctx.globalAlpha=.50;ctx.lineWidth=1.4;ctx.stroke();ctx.restore();
    }
  });
  return rendered;
}

function drawSurfaces(ctx,parts,joints,bones,opts){
  const jointsById=new Map((joints||[]).map(j=>[j.id,j])),bonesById=new Map((bones||[]).map(b=>[b.id,b]));
  (parts||[]).forEach(part=>drawBodyPart(ctx,part,jointsById,bonesById,opts));
}

function surfaceDescriptor(part,opts){
  opts=opts||{};return{
    bodyPart:part.id,type:part.type,skin:opts.skin||'human',
    controls:{mass:clamp(opts.mass,.2,3),muscleTone:clamp(opts.muscleTone,0,1),pumpedness:clamp(opts.pumpedness,0,2),fatCover:clamp(opts.fatCover,0,2)},
    muscleGroups:(part.surface&&part.surface.muscleGroups)||[],archetype:(part.surface&&part.surface.archetype)||part.type
  };
}

const api={SKINS,MUSCLE_PROFILES,profileFor,segmentRings,muscleLobes,drawBodyPart,drawSurfaces,surfaceDescriptor};
if(typeof module!=='undefined'&&module.exports)module.exports=api;
global.JXAnatomySurface=api;
})(typeof window!=='undefined'?window:globalThis);
