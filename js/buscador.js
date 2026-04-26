// =========================
// js/buscador.js
// =========================

async function buscarVuelos(params) {
  const query = new URLSearchParams(params).toString();
  return apiFetch("vuelos.php?action=buscar&" + query);
}

function initBuscador() {
  const form = document.querySelector("#buscador");

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    const data = new FormData(form);

    const query = new URLSearchParams(data).toString();
    window.location.href = "resultados.html?" + query;
  });
}