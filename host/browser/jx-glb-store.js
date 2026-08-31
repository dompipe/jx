/* JX GLB artifact store: shared browser persistence for generated/viewed GLB files. */
(function(global){
'use strict';
const DB='jx-artifacts',VERSION=1,STORE='glb';
function open(){return new Promise((resolve,reject)=>{const r=indexedDB.open(DB,VERSION);r.onupgradeneeded=()=>{const db=r.result;if(!db.objectStoreNames.contains(STORE))db.createObjectStore(STORE,{keyPath:'id'})};r.onsuccess=()=>resolve(r.result);r.onerror=()=>reject(r.error||new Error('IndexedDB open failed'))})}
async function save(id,blob,meta){if(!(blob instanceof Blob))throw new Error('JX GLB store requires a Blob');const db=await open();return new Promise((resolve,reject)=>{const tx=db.transaction(STORE,'readwrite');tx.objectStore(STORE).put({id:String(id||'last'),blob,meta:Object.assign({name:'model.glb',type:blob.type||'model/gltf-binary',size:blob.size,savedAt:Date.now()},meta||{})});tx.oncomplete=()=>{db.close();resolve(true)};tx.onerror=()=>{db.close();reject(tx.error||new Error('GLB save failed'))}})}
async function load(id){const db=await open();return new Promise((resolve,reject)=>{const tx=db.transaction(STORE,'readonly'),r=tx.objectStore(STORE).get(String(id||'last'));r.onsuccess=()=>{db.close();resolve(r.result||null)};r.onerror=()=>{db.close();reject(r.error||new Error('GLB load failed'))}})}
async function has(id){return !!(await load(id))}
async function remove(id){const db=await open();return new Promise((resolve,reject)=>{const tx=db.transaction(STORE,'readwrite');tx.objectStore(STORE).delete(String(id||'last'));tx.oncomplete=()=>{db.close();resolve(true)};tx.onerror=()=>{db.close();reject(tx.error||new Error('GLB delete failed'))}})}
const api={save,load,has,remove};if(typeof module!=='undefined'&&module.exports)module.exports=api;global.JXGLBStore=api;
})(typeof window!=='undefined'?window:globalThis);
