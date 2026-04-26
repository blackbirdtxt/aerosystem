// =========================
// js/panel.js
// =========================

async function cargarPanel() {
  const data = await apiFetch("panel.php?action=vuelos24h");
  renderPanel(data);
}

function renderPanel(vuelos) {
  const tbody = document.querySelector("#panel-body");

  tbody.innerHTML = vuelos.map(v => `
    <tr>
      <td>${v.numero_vuelo}</td>
      <td>${v.ciudad_origen}</td>
      <td>${v.ciudad_destino}</td>
      <td>${v.hora_salida}</td>
      <td class="status-${v.estado.toLowerCase().replace(' ','-')}">${v.estado}</td>
      <td>${v.puerta || '-'}</td>
    </tr>
  `).join("");
}

setInterval(cargarPanel, 20000);