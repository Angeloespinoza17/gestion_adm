import{q as V,ay as C,aJ as p,a5 as D,W as _,a3 as T,a9 as k,h as N,am as O,X as v,ag as d,aI as E}from"../js/app-DhJX9Qgs.js";import{d as K}from"./main--K_XeYoA.js";/**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md.
 *//**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md.
 */new Array(256).fill("").map((e,t)=>("0"+t.toString(16)).slice(-2));/**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md.
 */function R(e,t){const s=e.extraPlugins||[];return{...e,extraPlugins:[...s,...t.filter(a=>!s.includes(a))]}}/**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md.
 */function I(e){return!!e&&/^\d+\.\d+\.\d+/.test(e)}/**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md.
 */function h(e){return e?["nightly","alpha","internal"].some(t=>e.includes(t)):!1}function B(e){return I(e)||h(e)}/**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md.
 */function P(e){if(!I(e))throw new Error(`Invalid semantic version: ${e||"<blank>"}.`);const[t,s,a]=e.split(".");return{major:Number.parseInt(t,10),minor:Number.parseInt(s,10),patch:Number.parseInt(a,10)}}/**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md.
 */function U(e){if(h(e))return 3;const{major:t}=P(e);switch(!0){case t>=44:return 3;case t>=38:return 2;default:return 1}}/**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md.
 */function S(){const{CKEDITOR_VERSION:e,CKEDITOR:t}=window;return B(e)?{source:t?"cdn":"npm",version:e}:null}/**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md.
 */function L(){const e=S();return e?U(e.version):null}/**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md.
 */function w(e,t){switch(t||(t=L()||void 0),t){case 1:case 2:return e===void 0;case 3:return e==="GPL";default:return!1}}/**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md.
 */function j(e,t){return function(a){w(a.config.get("licenseKey"))||a.on("collectUsageData",(u,{setUsageData:i})=>{i(`integration.${e}`,t)})}}/**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md.
 */const x=j("vue",{version:"7.3.0",frameworkVersion:E});/**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md.
 */function M(e){return w(e.licenseKey)?e:R(e,[x])}const f="Lock from Vue integration (@ckeditor/ckeditor5-vue)",W=300,q=V({name:"CKEditor",__name:"ckeditor",props:v({editor:{},config:{default:()=>({})},tagName:{default:"div"},disabled:{type:Boolean,default:!1},disableTwoWayDataBinding:{type:Boolean,default:!1}},{modelValue:{type:String,default:""},modelModifiers:{}}),emits:v(["ready","destroy","blur","focus","input","update:modelValue"],["update:modelValue"]),setup(e,{expose:t,emit:s}){const a=C(e,"modelValue"),u=e,i=s,m=d(),r=d(),c=d();t({instance:r,lastEditorData:c}),p(a,n=>{r.value&&n!==c.value&&r.value.data.set(n)}),p(()=>u.disabled,n=>{n?r.value.enableReadOnlyMode(f):r.value.disableReadOnlyMode(f)});function b(){const n=window.CKEDITOR_VERSION;if(!n)return console.warn('Cannot find the "CKEDITOR_VERSION" in the "window" scope.');const[o]=n.split(".").map(Number);o>=42||n.startsWith("0.0.0")||console.warn("The <CKEditor> component requires using CKEditor 5 in version 42+ or nightly build.")}function y(n){const o=K(l=>{if(u.disableTwoWayDataBinding)return;const g=c.value=n.data.get();i("update:modelValue",g,l,n),i("input",g,l,n)},W,{leading:!0});n.model.document.on("change:data",o),n.editing.view.document.on("focus",l=>{i("focus",l,n)}),n.editing.view.document.on("blur",l=>{i("blur",l,n)})}return b(),D(()=>{const n=M(Object.assign({},u.config));a.value&&(n.initialData=a.value),u.editor.create(m.value,n).then(o=>{r.value=_(o),y(o),a.value!==n.initialData&&o.data.set(a.value),u.disabled&&o.enableReadOnlyMode(f),i("ready",o)}).catch(o=>{console.error(o)})}),T(()=>{r.value&&(r.value.destroy(),r.value=void 0),i("destroy")}),(n,o)=>(k(),N(O(n.tagName),{ref_key:"element",ref:m},null,512))}});/**
 * @license Copyright (c) 2003-2024, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see LICENSE.md.
 *//* istanbul ignore if -- @preserve */if(!E.startsWith("3."))throw new Error("The CKEditor plugin works only with Vue 3+. For more information, please refer to https://ckeditor.com/docs/ckeditor5/latest/builds/guides/integration/frameworks/vuejs-v3.html");export{q as _};
