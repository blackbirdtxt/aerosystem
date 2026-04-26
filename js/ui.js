// =========================
// js/ui.js
// =========================

function $(sel) { return document.querySelector(sel); }
function $all(sel) { return document.querySelectorAll(sel); }

function show(el) { el.classList.remove("hidden"); }
function hide(el) { el.classList.add("hidden"); }

function setHTML(el, html) { el.innerHTML = html; }

function loading(el) {
  el.innerHTML = `<div class="skeleton" style="height:50px"></div>`;
}