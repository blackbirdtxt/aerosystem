// =========================
// js/pago.js
// =========================

async function procesarPago(data) {
  return apiFetch("pagos.php?action=procesar", {
    method: "POST",
    body: JSON.stringify(data)
  });
}