// =========================
// js/reserva.js
// =========================

let selectedSeats = [];

async function cargarAsientos(idvuelo, clase) {
  return apiFetch(`vuelos.php?action=asientos&id=${idvuelo}&clase=${clase}`);
}

function toggleSeat(seatId) {
  if (selectedSeats.includes(seatId)) {
    selectedSeats = selectedSeats.filter(s => s !== seatId);
  } else {
    selectedSeats.push(seatId);
  }
}

async function crearReserva(data) {
  return apiFetch("reservas.php?action=crear", {
    method: "POST",
    body: JSON.stringify(data)
  });
}