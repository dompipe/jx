/* JX Anatomy image-guided skeleton fitter.
 * Deterministic CPU-only image analysis for rough vector -> anatomy skeleton.
 * The user supplies the structural prior by clicking joints/bones. The fitter
 * searches only near those vectors, using Sobel edges, bilateral edge support,
 * width consistency and endpoint saliency. No AI API is required.
 */
(function (global) {
  'use strict';

  function n(v, f) { v = Number(v); return Number.isFinite(v) ? v : f; }
  function clamp(v, a, b) { return Math.max(a, Math.min(b, n(v, a))); }
  function hypot(x, y) { return Math.sqrt(x*x + y*y); }
  function lerp(a,b,t) { return a + (b-a)*t; }
  function clone(v) { return JSON.parse(JSON.stringify(v)); }

  function grayFromImageData(imageData, maxSide) {
    const sw=imageData.width, sh=imageData.height;
    maxSide=Math.max(64,Math.min(2048,Math.round(n(maxSide,768))));
    const scale=Math.min(1,maxSide/Math.max(sw,sh));
    const w=Math.max(2,Math.round(sw*scale)), h=Math.max(2,Math.round(sh*scale));
    const src=imageData.data, gray=new Float32Array(w*h);
    for(let y=0;y<h;y++) {
      const sy=Math.min(sh-1,Math.round(y/scale));
      for(let x=0;x<w;x++) {
        const sx=Math.min(sw-1,Math.round(x/scale)), i=(sy*sw+sx)*4;
        gray[y*w+x]=(0.2126*src[i]+0.7152*src[i+1]+0.0722*src[i+2])/255;
      }
    }
    return {width:w,height:h,gray,sourceWidth:sw,sourceHeight:sh,scale};
  }

  function sobel(field) {
    const w=field.width,h=field.height,g=field.gray;
    const mag=new Float32Array(w*h), gx=new Float32Array(w*h), gy=new Float32Array(w*h);
    let max=1e-9;
    for(let y=1;y<h-1;y++) for(let x=1;x<w-1;x++) {
      const i=y*w+x;
      const a=g[i-w-1],b=g[i-w],c=g[i-w+1],d=g[i-1],f=g[i+1],q=g[i+w-1],r=g[i+w],s=g[i+w+1];
      const dx=-a+c-2*d+2*f-q+s, dy=-a-2*b-c+q+2*r+s;
      gx[i]=dx; gy[i]=dy; const m=hypot(dx,dy); mag[i]=m; if(m>max)max=m;
    }
    for(let i=0;i<mag.length;i++) mag[i]/=max;
    return Object.assign({},field,{edge:mag,gx,gy});
  }

  function sample(array,w,h,x,y) {
    x=clamp(x,0,w-1); y=clamp(y,0,h-1);
    const x0=Math.floor(x), y0=Math.floor(y), x1=Math.min(w-1,x0+1), y1=Math.min(h-1,y0+1), tx=x-x0,ty=y-y0;
    const a=lerp(array[y0*w+x0],array[y0*w+x1],tx), b=lerp(array[y1*w+x0],array[y1*w+x1],tx);
    return lerp(a,b,ty);
  }

  function endpointScore(a,x,y,radius) {
    radius=Math.max(2,Math.round(radius)); let score=0,weight=0;
    for(let dy=-radius;dy<=radius;dy++) for(let dx=-radius;dx<=radius;dx++) {
      const d=hypot(dx,dy); if(d>radius)continue; const wt=1-d/(radius+1); score+=sample(a.edge,a.width,a.height,x+dx,y+dy)*wt; weight+=wt;
    }
    return weight?score/weight:0;
  }

  function crossSection(a,cx,cy,nx,ny,maxHalf) {
    let bestL={s:0,d:0},bestR={s:0,d:0};
    for(let d=1;d<=maxHalf;d++) {
      const sl=sample(a.edge,a.width,a.height,cx-nx*d,cy-ny*d);
      const sr=sample(a.edge,a.width,a.height,cx+nx*d,cy+ny*d);
      if(sl>bestL.s)bestL={s:sl,d}; if(sr>bestR.s)bestR={s:sr,d};
    }
    const both=Math.sqrt(bestL.s*bestR.s), balance=1-Math.abs(bestL.d-bestR.d)/Math.max(1,bestL.d+bestR.d);
    return {score:both*(0.55+0.45*balance),halfWidth:(bestL.d+bestR.d)/2,left:bestL,right:bestR};
  }

  function scoreSegment(a,p0,p1,opts) {
    opts=opts||{};
    const dx=p1.x-p0.x,dy=p1.y-p0.y,L=Math.max(1,hypot(dx,dy)),tx=dx/L,ty=dy/L,nx=-ty,ny=tx;
    const samples=Math.max(8,Math.min(96,Math.round(L/5))), maxHalf=Math.max(3,Math.min(96,Math.round(n(opts.maxHalfWidth,Math.max(8,L*.18)))));
    let edgeScore=0,widthSum=0,widthSq=0,continuity=0,prevWidth=null;
    for(let i=1;i<samples;i++) {
      const u=i/samples,cx=lerp(p0.x,p1.x,u),cy=lerp(p0.y,p1.y,u),cs=crossSection(a,cx,cy,nx,ny,maxHalf);
      edgeScore+=cs.score;widthSum+=cs.halfWidth;widthSq+=cs.halfWidth*cs.halfWidth;
      if(prevWidth!==null)continuity+=Math.exp(-Math.abs(cs.halfWidth-prevWidth)/Math.max(2,maxHalf*.22)); prevWidth=cs.halfWidth;
    }
    const count=Math.max(1,samples-1),meanW=widthSum/count,varW=Math.max(0,widthSq/count-meanW*meanW),widthConsistency=Math.exp(-Math.sqrt(varW)/Math.max(2,meanW));
    const ends=(endpointScore(a,p0.x,p0.y,4)+endpointScore(a,p1.x,p1.y,4))/2;
    return {score:(edgeScore/count)*.58+widthConsistency*.17+(continuity/Math.max(1,count-1))*.13+ends*.12,width:meanW,edge:edgeScore/count,widthConsistency,endpoints:ends};
  }

  function refineBone(a,bone,opts) {
    opts=opts||{}; const p0=bone.a,p1=bone.b,dx=p1.x-p0.x,dy=p1.y-p0.y,L=Math.max(1,hypot(dx,dy)),nx=-dy/L,ny=dx/L;
    const corridor=Math.max(2,Math.min(80,Math.round(n(opts.corridor,Math.max(5,L*.12))))), endpointRadius=Math.max(0,Math.min(36,Math.round(n(opts.endpointRadius,Math.max(3,L*.05)))));
    let best=null;
    const shifts=[]; for(let s=-corridor;s<=corridor;s+=Math.max(1,Math.round(corridor/8)))shifts.push(s); if(!shifts.includes(0))shifts.push(0);
    const endpointSteps=endpointRadius?[-endpointRadius,0,endpointRadius]:[0];
    for(const shift of shifts) for(const da of endpointSteps) for(const db of endpointSteps) {
      const tx=dx/L,ty=dy/L;
      const q0={x:p0.x+nx*shift+tx*da,y:p0.y+ny*shift+ty*da},q1={x:p1.x+nx*shift+tx*db,y:p1.y+ny*shift+ty*db};
      const scored=scoreSegment(a,q0,q1,opts), shiftPenalty=Math.abs(shift)/Math.max(1,corridor), endpointPenalty=(Math.abs(da)+Math.abs(db))/Math.max(1,2*endpointRadius||1);
      const priorPenalty=shiftPenalty*.16+endpointPenalty*.08, total=scored.score-priorPenalty;
      if(!best||total>best.total)best={a:q0,b:q1,total,rawScore:scored.score,width:scored.width,metrics:scored,shift,da,db};
    }
    return Object.assign({},clone(bone),best,{confidence:clamp(best?best.total:0,0,1)});
  }

  function jointCandidates(a,joint,radius,limit) {
    radius=Math.max(2,Math.min(64,Math.round(radius))); limit=Math.max(1,Math.min(32,Math.round(limit||8)));
    const out=[];
    for(let y=Math.max(1,Math.round(joint.y-radius));y<=Math.min(a.height-2,Math.round(joint.y+radius));y+=2) for(let x=Math.max(1,Math.round(joint.x-radius));x<=Math.min(a.width-2,Math.round(joint.x+radius));x+=2) {
      const d=hypot(x-joint.x,y-joint.y); if(d>radius)continue;
      const e=endpointScore(a,x,y,3), prior=Math.exp(-(d*d)/(2*Math.pow(radius*.55,2))),score=e*.76+prior*.24;
      out.push({x,y,score});
    }
    out.sort((u,v)=>v.score-u.score); return out.slice(0,limit);
  }

  function refineSkeleton(analysis,skeleton,opts) {
    opts=opts||{}; const passes=Math.max(1,Math.min(8,Math.round(n(opts.passes,2))));
    const out=clone(skeleton), byId=new Map((out.joints||[]).map(j=>[j.id,j]));
    for(let pass=0;pass<passes;pass++) {
      for(const bone of out.bones||[]) {
        const a=byId.get(bone.a),b=byId.get(bone.b); if(!a||!b)continue;
        const fit=refineBone(analysis,{id:bone.id,semantic:bone.semantic,a:{x:a.x,y:a.y},b:{x:b.x,y:b.y}},opts);
        const trust=clamp(n(opts.snapStrength,.68),0,1)*(pass===0?1:.55);
        a.x=lerp(a.x,fit.a.x,trust*.5);a.y=lerp(a.y,fit.a.y,trust*.5);b.x=lerp(b.x,fit.b.x,trust*.5);b.y=lerp(b.y,fit.b.y,trust*.5);
        bone.fit={confidence:fit.confidence,width:fit.width,score:fit.rawScore};
      }
      for(const joint of out.joints||[]) {
        const candidates=jointCandidates(analysis,joint,n(opts.jointRadius,10),6); if(!candidates.length)continue;
        const best=candidates[0],trust=clamp(n(opts.jointSnap,.32),0,1);
        joint.x=lerp(joint.x,best.x,trust);joint.y=lerp(joint.y,best.y,trust);joint.saliency=best.score;
      }
    }
    return out;
  }

  function estimateDepth(skeleton,imageW,imageH,opts) {
    opts=opts||{}; const scale=n(opts.modelScale,2.2)/Math.max(imageW,imageH), parts=[],joints=[];
    const byId=new Map((skeleton.joints||[]).map(j=>[j.id,j]));
    function world(j){return [(j.x-imageW/2)*scale,(imageH/2-j.y)*scale,n(j.z,0)];}
    for(const j of skeleton.joints||[]) joints.push({id:j.id,type:'ball-joint',semantic:j.semantic||'joint',params:{radius:Math.max(.025,n(j.radius,.045))},transform:{position:world(j),rotation:[0,0,0],scale:[1,1,1]},textures:[]});
    for(const b of skeleton.bones||[]) {
      const a=byId.get(b.a),c=byId.get(b.b);if(!a||!c)continue;const A=world(a),B=world(c),dx=B[0]-A[0],dy=B[1]-A[1],dz=B[2]-A[2],L=Math.max(.001,Math.sqrt(dx*dx+dy*dy+dz*dz));
      const rz=-Math.atan2(dx,dy), widthPx=n(b.fit&&b.fit.width,Math.max(4,hypot(c.x-a.x,c.y-a.y)*.09)),radius=Math.max(.018,widthPx*scale*.52);
      parts.push({id:b.id,type:'pipe',semantic:b.semantic||'bone',parent:null,from:b.a,to:b.b,params:{length:L,radius,pumpedness:n(b.pumpedness,.15),sourceWidthPx:widthPx},transform:{position:[(A[0]+B[0])/2,(A[1]+B[1])/2,(A[2]+B[2])/2],rotation:[0,0,rz],scale:[1,1,1]},textures:[]});
    }
    return {joints,parts};
  }

  function inferChains(skeleton) {
    const adj=new Map();
    for(const j of skeleton.joints||[])adj.set(j.id,[]);
    for(const b of skeleton.bones||[]) { if(adj.has(b.a))adj.get(b.a).push({to:b.b,bone:b});if(adj.has(b.b))adj.get(b.b).push({to:b.a,bone:b}); }
    const roots=(skeleton.joints||[]).filter(j=>/root|torso|hip|shoulder|pelvis|chest/i.test(j.semantic||j.id));
    const leaves=(skeleton.joints||[]).filter(j=>(adj.get(j.id)||[]).length===1&&!roots.includes(j));
    const chains=[];
    for(const leaf of leaves) {
      let best=null;
      for(const root of roots) {
        const q=[[root.id,[root.id],[]]],seen=new Set([root.id]);
        while(q.length){const [id,path,bones]=q.shift();if(id===leaf.id){best={joints:path,bones};break;}for(const e of adj.get(id)||[])if(!seen.has(e.to)){seen.add(e.to);q.push([e.to,path.concat(e.to),bones.concat(e.bone.id)]);}}
        if(best)break;
      }
      if(best&&best.joints.length>=3)chains.push({id:'ik-'+leaf.id,joints:best.joints,bones:best.bones,pole:[0,0,1],iterations:18,tolerance:.0004});
    }
    return chains;
  }

  function toAnatomyDescriptor(skeleton,imageW,imageH,opts) {
    const d=estimateDepth(skeleton,imageW,imageH,opts), parts=d.joints.concat(d.parts);
    return {kind:'model',model:'anatomy',plugin:'anatomy',version:'jx.anatomy/2',id:(opts&&opts.id)||'image-fit-model',species:(opts&&opts.species)||'generic',parts,animations:[],imageSkeleton:{version:1,joints:clone(skeleton.joints||[]),bones:clone(skeleton.bones||[]),passes:clone(skeleton.passes||[])},ikChains:inferChains(skeleton)};
  }

  function analyze(imageData,opts){return sobel(grayFromImageData(imageData,opts&&opts.maxSide));}
  const api={analyze,grayFromImageData,sobel,scoreSegment,refineBone,refineSkeleton,jointCandidates,toAnatomyDescriptor,inferChains};
  if(typeof module!=='undefined'&&module.exports)module.exports=api;
  global.JXAnatomyImageFit=api;
})(typeof window!=='undefined'?window:globalThis);
