/* JX GLB Viewer host. Visual inspection only; JX/PHP remains responsible for model generation/export. */
import * as THREE from 'three';
import {OrbitControls} from 'three/addons/controls/OrbitControls.js';
import {GLTFLoader} from 'three/addons/loaders/GLTFLoader.js';

class JXGLBViewer {
  constructor(root,opts={}){
    if(!root)throw new Error('JXGLBViewer requires a root element');
    this.root=root;this.opts=opts;this.model=null;this.gltf=null;this.mixer=null;this.clock=new THREE.Clock();this.animations=[];this.currentAction=null;
    this.scene=new THREE.Scene();this.scene.background=new THREE.Color(opts.background||0x0c1118);
    this.camera=new THREE.PerspectiveCamera(42,1,.01,5000);this.camera.position.set(2.8,1.8,4.2);
    this.renderer=new THREE.WebGLRenderer({antialias:true,alpha:false,preserveDrawingBuffer:false});this.renderer.setPixelRatio(Math.min(2,window.devicePixelRatio||1));this.renderer.outputColorSpace=THREE.SRGBColorSpace;this.renderer.toneMapping=THREE.ACESFilmicToneMapping;this.renderer.toneMappingExposure=1.0;root.appendChild(this.renderer.domElement);
    this.controls=new OrbitControls(this.camera,this.renderer.domElement);this.controls.enableDamping=true;this.controls.dampingFactor=.07;this.controls.screenSpacePanning=true;
    this.grid=new THREE.GridHelper(10,20,0x43546a,0x283342);this.grid.position.y=0;this.scene.add(this.grid);
    this.axes=new THREE.AxesHelper(1.25);this.scene.add(this.axes);
    this.scene.add(new THREE.HemisphereLight(0xffffff,0x263243,2.2));const key=new THREE.DirectionalLight(0xffffff,3.0);key.position.set(4,7,5);this.scene.add(key);const fill=new THREE.DirectionalLight(0xbad8ff,1.3);fill.position.set(-4,2,2);this.scene.add(fill);
    this.loader=new GLTFLoader();this._resize=()=>this.resize();window.addEventListener('resize',this._resize);this.resize();this._animate();
  }
  resize(){const w=Math.max(1,this.root.clientWidth||900),h=Math.max(1,this.root.clientHeight||600);this.renderer.setSize(w,h,false);this.camera.aspect=w/h;this.camera.updateProjectionMatrix()}
  clear(){if(this.currentAction)this.currentAction.stop();this.currentAction=null;this.mixer=null;this.animations=[];this.gltf=null;if(this.model){this.scene.remove(this.model);this.model.traverse(o=>{if(o.geometry)o.geometry.dispose();if(o.material){const mats=Array.isArray(o.material)?o.material:[o.material];mats.forEach(m=>{for(const k of Object.keys(m)){const v=m[k];if(v&&v.isTexture)v.dispose()}m.dispose&&m.dispose()})}});this.model=null}}
  loadBlob(blob,name='model.glb'){return blob.arrayBuffer().then(buffer=>this.loadArrayBuffer(buffer,name))}
  loadArrayBuffer(buffer,name='model.glb'){return new Promise((resolve,reject)=>{this.loader.parse(buffer,'',gltf=>{this.clear();this.gltf=gltf;this.model=gltf.scene;this.model.name=this.model.name||name;this.scene.add(this.model);this.animations=gltf.animations||[];this.mixer=this.animations.length?new THREE.AnimationMixer(this.model):null;this.frame();resolve(this.describe())},reject)})}
  frame(){if(!this.model)return;const box=new THREE.Box3().setFromObject(this.model);if(box.isEmpty())return;const sphere=box.getBoundingSphere(new THREE.Sphere()),r=Math.max(.05,sphere.radius);this.controls.target.copy(sphere.center);const dir=new THREE.Vector3(1,.55,1).normalize();this.camera.position.copy(sphere.center).addScaledVector(dir,r*2.8);this.camera.near=Math.max(.001,r/1000);this.camera.far=Math.max(100,r*100);this.camera.updateProjectionMatrix();this.controls.update()}
  view(kind){if(!this.model)return;const box=new THREE.Box3().setFromObject(this.model),sphere=box.getBoundingSphere(new THREE.Sphere()),r=Math.max(.05,sphere.radius),c=sphere.center;this.controls.target.copy(c);const pos={front:[0,0,1],back:[0,0,-1],left:[-1,0,0],right:[1,0,0],top:[0,1,0]}[kind]||[1,.55,1];const d=new THREE.Vector3(...pos).normalize();this.camera.position.copy(c).addScaledVector(d,r*2.8);if(kind==='top')this.camera.up.set(0,0,-1);else this.camera.up.set(0,1,0);this.camera.lookAt(c);this.controls.update()}
  setGrid(v){this.grid.visible=!!v}
  setAxes(v){this.axes.visible=!!v}
  setWireframe(v){if(!this.model)return;this.model.traverse(o=>{if(!o.isMesh||!o.material)return;(Array.isArray(o.material)?o.material:[o.material]).forEach(m=>{m.wireframe=!!v;m.needsUpdate=true})})}
  playAnimation(index=0){if(!this.mixer||!this.animations[index])return false;if(this.currentAction)this.currentAction.stop();this.currentAction=this.mixer.clipAction(this.animations[index]);this.currentAction.reset().play();return true}
  stopAnimation(){if(this.currentAction)this.currentAction.stop();this.currentAction=null}
  describe(){let meshes=0,triangles=0,materials=new Set(),textures=new Set(),vertices=0;if(this.model)this.model.traverse(o=>{if(!o.isMesh)return;meshes++;const g=o.geometry;if(g){const p=g.getAttribute('position');if(p)vertices+=p.count;const count=g.index?g.index.count:(p?p.count:0);triangles+=Math.floor(count/3)}const mats=Array.isArray(o.material)?o.material:[o.material];mats.filter(Boolean).forEach(m=>{materials.add(m.uuid);for(const k of Object.keys(m)){const v=m[k];if(v&&v.isTexture)textures.add(v.uuid)}})});return{name:this.model&&this.model.name||'GLB',meshes,vertices,triangles,materials:materials.size,textures:textures.size,animations:this.animations.map((a,i)=>({index:i,name:a.name||('Animation '+(i+1)),duration:a.duration}))}}
  _animate(){this._raf=requestAnimationFrame(()=>this._animate());const dt=this.clock.getDelta();if(this.mixer)this.mixer.update(dt);this.controls.update();this.renderer.render(this.scene,this.camera)}
  dispose(){cancelAnimationFrame(this._raf);window.removeEventListener('resize',this._resize);this.clear();this.controls.dispose();this.renderer.dispose();this.renderer.domElement.remove()}
}

window.JXGLBViewer=JXGLBViewer;
export {JXGLBViewer};
