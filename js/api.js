// =========================
// js/api.js
// =========================

const API_BASE = "/aerosystem/api/";

async function apiFetch(endpoint, options = {}) {
  try {
    const res = await fetch(API_BASE + endpoint, {
      headers: { "Content-Type": "application/json" },
      credentials: "include",
      ...options
    });

    const data = await res.json();

    if (!data.success) {
      throw new Error(data.error || "Error en API");
    }

    return data.data;
  } catch (err) {
    console.error(err);
    alert(err.message);
    throw err;
  }
}