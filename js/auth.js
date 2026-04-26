// =========================
// js/auth.js
// =========================

async function login(email, clave) {
  return apiFetch("auth.php?action=login", {
    method: "POST",
    body: JSON.stringify({ mail: email, clave })
  });
}

async function register(data) {
  return apiFetch("auth.php?action=registro", {
    method: "POST",
    body: JSON.stringify(data)
  });
}

async function logout() {
  return apiFetch("auth.php?action=logout");
}

async function checkSession() {
  try {
    return await apiFetch("auth.php?action=check_session");
  } catch {
    return { logged: false };
  }
}