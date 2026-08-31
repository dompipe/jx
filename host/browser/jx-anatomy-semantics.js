/* JX Anatomy semantic body-part layer.
 * A leg is a leg, an arm is an arm: user clicks place known anatomical templates.
 * This module turns generic clicked vectors into semantic ports/segments and
 * groups fitted bones back into whole body parts for rigging, surfacing and IK.
 */
(function (global) {
  'use strict';

  function clone(v) { return JSON.parse(JSON.stringify(v)); }
  function n(v,f) { v=Number(v); return Number.isFinite(v)?v:f; }

  const TEMPLATES = {
    arm: {
      family:'arm', label:'Arm', variable:false,
      ports:['shoulder','elbow','wrist'],
      bones:['upper-arm','forearm'],
      ik:true,
      surface:{archetype:'human-arm', taper:.76, muscleGroups:['deltoid','biceps','triceps','forearm-flexor','forearm-extensor']}
    },
    leg: {
      family:'leg', label:'Leg', variable:false,
      ports:['hip','knee','ankle','foot'],
      bones:['thigh','shin','foot'],
      ik:true,
      surface:{archetype:'human-leg', taper:.72, muscleGroups:['quadriceps','hamstring','calf','tibialis']}
    },
    'animal-front-leg': {
      family:'animal-front-leg', label:'Animal front leg', variable:false,
      ports:['shoulder','elbow','carpus','paw'],
      bones:['humerus','radius-ulna','metacarpal'],
      ik:true,
      surface:{archetype:'animal-front-leg', taper:.68, muscleGroups:['shoulder','upper-limb','forearm','distal-tendon']}
    },
    'animal-rear-leg': {
      family:'animal-rear-leg', label:'Animal rear leg', variable:false,
      ports:['hip','stifle','hock','paw'],
      bones:['femur','tibia','metatarsal'],
      ik:true,
      surface:{archetype:'animal-rear-leg', taper:.66, muscleGroups:['gluteal','thigh','lower-leg','hock-tendon']}
    },
    wing: {
      family:'wing', label:'Wing', variable:false,
      ports:['wing-root','wing-elbow','wing-wrist','wing-tip'],
      bones:['wing-upper','wing-lower','wing-hand'],
      ik:true,
      surface:{archetype:'wing', taper:.58, membrane:true}
    },
    torso: {
      family:'torso', label:'Torso / spine', variable:true,
      ports:['torso-root','spine','chest','neck-root'],
      bones:['spine'],
      ik:false,
      surface:{archetype:'torso', volume:true}
    },
    neck: {
      family:'neck', label:'Neck', variable:true,
      ports:['neck-root','neck','head'],
      bones:['neck'],
      ik:true,
      surface:{archetype:'neck', taper:.82}
    },
    tail: {
      family:'tail', label:'Tail', variable:true,
      ports:['tail-root','tail','tail-tip'],
      bones:['tail'],
      ik:true,
      surface:{archetype:'tail', taper:.42}
    },
    beak: {
      family:'beak', label:'Beak / bill', variable:false,
      ports:['beak-root','beak-tip'],
      bones:['beak'],
      ik:false,
      surface:{archetype:'beak', taper:.25}
    },
    snout: {
      family:'snout', label:'Snout / nose', variable:false,
      ports:['snout-root','snout-tip'],
      bones:['snout'],
      ik:false,
      surface:{archetype:'snout', taper:.48}
    },
    jaw: {
      family:'jaw', label:'Jaw', variable:false,
      ports:['jaw-root','jaw-tip'],
      bones:['jaw'],
      ik:true,
      surface:{archetype:'jaw', taper:.72}
    },
    generic: {
      family:'generic', label:'Generic chain', variable:true,
      ports:['joint'], bones:['bone'], ik:true,
      surface:{archetype:'generic'}
    }
  };

  function template(name) {
    return clone(TEMPLATES[name] || TEMPLATES.generic);
  }

  function sideForPoint(x, imageWidth, requested) {
    if (requested === 'left' || requested === 'right' || requested === 'center') return requested;
    if (!(imageWidth > 0)) return 'center';
    const u=x/imageWidth;
    if (u < .44) return 'left';
    if (u > .56) return 'right';
    return 'center';
  }

  function portSemantic(type, index) {
    const t=TEMPLATES[type]||TEMPLATES.generic;
    if (t.variable) {
      if (index===0) return t.ports[0] || 'joint';
      return t.ports[Math.min(index,t.ports.length-1)] || t.ports[t.ports.length-1] || 'joint';
    }
    return t.ports[Math.min(index,t.ports.length-1)] || 'joint';
  }

  function boneSemantic(type, index) {
    const t=TEMPLATES[type]||TEMPLATES.generic;
    if (t.variable) return t.bones[Math.min(index,t.bones.length-1)] || t.bones[0] || 'bone';
    return t.bones[Math.min(index,t.bones.length-1)] || 'bone';
  }

  function expectedPortCount(type) {
    const t=TEMPLATES[type]||TEMPLATES.generic;
    return t.variable ? null : t.ports.length;
  }

  function newPlacement(type, id, opts) {
    opts=opts||{};
    const t=template(type);
    return {
      id:id || (type+'-placement'), type, family:t.family, label:t.label,
      side:opts.side||'auto', pass:opts.pass||null,
      portIds:[], boneIds:[], complete:false,
      surface:t.surface, ik:t.ik
    };
  }

  function annotateJoint(placement, joint, index, imageWidth) {
    joint.bodyPart=placement.id;
    joint.bodyPartType=placement.type;
    joint.semantic=portSemantic(placement.type,index);
    joint.portIndex=index;
    if (placement.side==='auto' && index===0) placement.side=sideForPoint(joint.x,imageWidth,'auto');
    joint.side=placement.side;
    return joint;
  }

  function annotateBone(placement, bone, index) {
    bone.bodyPart=placement.id;
    bone.bodyPartType=placement.type;
    bone.semantic=boneSemantic(placement.type,index);
    bone.segmentIndex=index;
    bone.side=placement.side;
    return bone;
  }

  function shouldAutoFinish(placement) {
    const count=expectedPortCount(placement.type);
    return count!==null && placement.portIds.length>=count;
  }

  function finalizePlacement(placement) {
    placement.complete=true;
    return placement;
  }

  function groupBodyParts(skeleton) {
    const groups=new Map(), joints=new Map((skeleton.joints||[]).map(j=>[j.id,j]));
    for(const b of skeleton.bones||[]) {
      const id=b.bodyPart || ('part-'+(b.bodyPartType||b.semantic||b.pass||'generic'));
      if(!groups.has(id)) {
        const type=b.bodyPartType || inferTypeFromSemantic(b.semantic,b.pass,skeleton.passes);
        const t=template(type);
        groups.set(id,{id,type,family:t.family,label:t.label,side:b.side||'auto',boneIds:[],jointIds:[],segments:[],surface:t.surface,ik:t.ik});
      }
      const g=groups.get(id);
      g.boneIds.push(b.id);
      if(!g.jointIds.includes(b.a))g.jointIds.push(b.a);
      if(!g.jointIds.includes(b.b))g.jointIds.push(b.b);
      g.segments.push({id:b.id,semantic:b.semantic||'bone',a:b.a,b:b.b,index:n(b.segmentIndex,g.segments.length),confidence:n(b.fit&&b.fit.confidence,0),width:n(b.fit&&b.fit.width,0)});
    }
    for(const g of groups.values()) {
      g.segments.sort((a,b)=>a.index-b.index);
      g.joints=g.jointIds.map(id=>joints.get(id)).filter(Boolean).map(j=>({id:j.id,semantic:j.semantic,side:j.side||g.side,image:[j.x,j.y]}));
      if(g.side==='auto'&&g.joints.length)g.side=g.joints[0].side||'center';
      g.rootJoint=g.segments.length?g.segments[0].a:null;
      g.endJoint=g.segments.length?g.segments[g.segments.length-1].b:null;
    }
    return Array.from(groups.values());
  }

  function inferTypeFromSemantic(semantic, passId, passes) {
    const s=String(semantic||'').toLowerCase();
    if(/upper-arm|forearm|arm/.test(s))return 'arm';
    if(/thigh|shin|ankle|foot/.test(s))return 'leg';
    if(/wing/.test(s))return 'wing';
    if(/tail/.test(s))return 'tail';
    if(/neck/.test(s))return 'neck';
    if(/beak|bill/.test(s))return 'beak';
    if(/snout|nose/.test(s))return 'snout';
    if(/jaw/.test(s))return 'jaw';
    if(/spine|torso|chest/.test(s))return 'torso';
    const p=(passes||[]).find(p=>p.id===passId), k=String(p&&p.kind||'').toLowerCase();
    if(k==='arms')return 'arm'; if(k==='legs')return 'leg'; if(k==='wings')return 'wing';
    if(k==='tail')return 'tail'; if(k==='head')return 'neck'; if(k==='face')return 'snout';
    return 'generic';
  }

  function attachBodyParts(descriptor, skeleton) {
    const out=descriptor;
    out.bodyParts=groupBodyParts(skeleton);
    out.bodyParts.forEach(part=>{
      part.anatomy={
        type:part.type,
        side:part.side,
        surface:clone(part.surface),
        controls:{size:1,mass:1,muscleTone:.35,pumpedness:.25,fatCover:.15,boneProminence:.35}
      };
    });
    return out;
  }

  const api={TEMPLATES,template,expectedPortCount,portSemantic,boneSemantic,newPlacement,annotateJoint,annotateBone,shouldAutoFinish,finalizePlacement,groupBodyParts,inferTypeFromSemantic,attachBodyParts};
  if(typeof module!=='undefined'&&module.exports)module.exports=api;
  global.JXAnatomySemantics=api;
})(typeof window!=='undefined'?window:globalThis);
