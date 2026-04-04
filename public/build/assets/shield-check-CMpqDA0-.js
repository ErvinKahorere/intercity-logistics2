import{O as a}from"./app-Bh51FikA.js";/**
 * @license lucide-vue-next v0.548.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const h=e=>e.replace(/([a-z0-9])([A-Z])/g,"$1-$2").toLowerCase(),C=e=>e.replace(/^([A-Z])|[\s-_]+(\w)/g,(t,r,o)=>o?o.toUpperCase():r.toLowerCase()),k=e=>{const t=C(e);return t.charAt(0).toUpperCase()+t.slice(1)},p=(...e)=>e.filter((t,r,o)=>!!t&&t.trim()!==""&&o.indexOf(t)===r).join(" ").trim(),u=e=>e==="";/**
 * @license lucide-vue-next v0.548.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */var c={xmlns:"http://www.w3.org/2000/svg",width:24,height:24,viewBox:"0 0 24 24",fill:"none",stroke:"currentColor","stroke-width":2,"stroke-linecap":"round","stroke-linejoin":"round"};/**
 * @license lucide-vue-next v0.548.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const g=({name:e,iconNode:t,absoluteStrokeWidth:r,"absolute-stroke-width":o,strokeWidth:s,"stroke-width":n,size:i=c.width,color:w=c.stroke,...l},{slots:d})=>a("svg",{...c,...l,width:i,height:i,stroke:w,"stroke-width":u(r)||u(o)||r===!0||o===!0?Number(s||n||c["stroke-width"])*24/Number(i):s||n||c["stroke-width"],class:p("lucide",l.class,...e?[`lucide-${h(k(e))}-icon`,`lucide-${h(e)}`]:["lucide-icon"])},[...t.map(m=>a(...m)),...d.default?[d.default()]:[]]);/**
 * @license lucide-vue-next v0.548.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const f=(e,t)=>(r,{slots:o,attrs:s})=>a(g,{...s,...r,iconNode:t,name:e},o);/**
 * @license lucide-vue-next v0.548.0 - ISC
 *
 * This source code is licensed under the ISC license.
 * See the LICENSE file in the root directory of this source tree.
 */const x=f("shield-check",[["path",{d:"M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z",key:"oel41y"}],["path",{d:"m9 12 2 2 4-4",key:"dzmm74"}]]);export{x as S,f as c};
