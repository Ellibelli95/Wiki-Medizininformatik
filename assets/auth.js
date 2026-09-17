/**
 * MedizinInformatik Wiki – Anmeldung, Sitzung und Rollen (Admin / Listener)
 * Clientseitige Demo-Authentifizierung für das statische Wiki.
 */
(function (global) {
  "use strict";

  var SESSION_KEY = "miw-session";
  var EXTRA_USERS_KEY = "miw-extra-users";
  var SESSION_HOURS = 12;

  var DEFAULT_USERS = [
    {
      username: "admin",
      displayName: "Administrator",
      role: "admin",
      passwordHash: "059a50ce956b7ec61527c7ecc0c55b5a009dc54ab4acddce8852b46baa2aba30"
    },
    {
      username: "listener",
      displayName: "Listener",
      role: "listener",
      passwordHash: "e9c8dd95481bcfe3b50ebbdda2ca42491b3085784da8362f4c24bafd28e04c8a"
    }
  ];

  var ROLE_LABELS = {
    admin: "Admin",
    listener: "Listener"
  };

  (function hideAdminOnlyEarly() {
    if (document.getElementById("miw-hide-admin")) return;
    var style = document.createElement("style");
    style.id = "miw-hide-admin";
    style.textContent = "[data-admin-only]{display:none !important}";
    (document.head || document.documentElement).appendChild(style);
  })();

  function getSiteRoot() {
    var el = document.querySelector('script[src*="assets/auth.js"]');
    if (el) {
      return (el.getAttribute("src") || "").replace(/assets\/auth\.js(\?.*)?$/, "");
    }
    return "";
  }

  function bytesToHex(bytes) {
    var hex = "";
    for (var i = 0; i < bytes.length; i++) {
      hex += ("00" + bytes[i].toString(16)).slice(-2);
    }
    return hex;
  }

  function sha256Pure(ascii) {
    function rightRotate(value, amount) {
      return (value >>> amount) | (value << (32 - amount));
    }
    var mathPow = Math.pow;
    var maxWord = mathPow(2, 32);
    var lengthProperty = "length";
    var i, j;
    var result = "";
    var words = [];
    var asciiBitLength = ascii[lengthProperty] * 8;
    var hash = [];
    var k = [];
    var primeCounter = 0;
    var isComposite = {};
    for (var candidate = 2; primeCounter < 64; candidate++) {
      if (!isComposite[candidate]) {
        for (i = 0; i < 313; i += candidate) {
          isComposite[i] = candidate;
        }
        hash[primeCounter] = (mathPow(candidate, 0.5) * maxWord) | 0;
        k[primeCounter++] = (mathPow(candidate, 1 / 3) * maxWord) | 0;
      }
    }
    ascii += "\x80";
    while (ascii[lengthProperty] % 64 - 56) ascii += "\x00";
    for (i = 0; i < ascii[lengthProperty]; i++) {
      j = ascii.charCodeAt(i);
      if (j >> 8) return "";
      words[i >> 2] |= j << ((3 - i) % 4) * 8;
    }
    words[words[lengthProperty]] = (asciiBitLength / maxWord) | 0;
    words[words[lengthProperty]] = asciiBitLength;
    for (j = 0; j < words[lengthProperty]; ) {
      var w = words.slice(j, (j += 16));
      var oldHash = hash;
      hash = hash.slice(0, 8);
      for (i = 0; i < 64; i++) {
        var w15 = w[i - 15];
        var w2 = w[i - 2];
        var a = hash[0];
        var e = hash[4];
        var temp1 =
          hash[7] +
          (rightRotate(e, 6) ^ rightRotate(e, 11) ^ rightRotate(e, 25)) +
          ((e & hash[5]) ^ (~e & hash[6])) +
          k[i] +
          (w[i] =
            i < 16
              ? w[i]
              : (w[i - 16] +
                  (rightRotate(w15, 7) ^ rightRotate(w15, 18) ^ (w15 >>> 3)) +
                  w[i - 7] +
                  (rightRotate(w2, 17) ^ rightRotate(w2, 19) ^ (w2 >>> 10))) |
                0);
        var temp2 =
          (rightRotate(a, 2) ^ rightRotate(a, 13) ^ rightRotate(a, 22)) +
          ((a & hash[1]) ^ (a & hash[2]) ^ (hash[1] & hash[2]));
        hash = [(temp1 + temp2) | 0].concat(hash);
        hash[4] = (hash[4] + temp1) | 0;
      }
      for (i = 0; i < 8; i++) {
        hash[i] = (hash[i] + oldHash[i]) | 0;
      }
    }
    for (i = 0; i < 8; i++) {
      for (j = 3; j + 1; j--) {
        var b = (hash[i] >> (j * 8)) & 255;
        result += (b < 16 ? "0" : "") + b.toString(16);
      }
    }
    return result;
  }

  function hashPassword(password) {
    if (global.crypto && crypto.subtle && global.TextEncoder) {
      return crypto.subtle
        .digest("SHA-256", new TextEncoder().encode(password))
        .then(function (buf) {
          return bytesToHex(new Uint8Array(buf));
        })
        .catch(function () {
          return sha256Pure(password);
        });
    }
    return Promise.resolve(sha256Pure(password));
  }

  function safeEqual(a, b) {
    if (!a || !b || a.length !== b.length) return false;
    var out = 0;
    for (var i = 0; i < a.length; i++) {
      out |= a.charCodeAt(i) ^ b.charCodeAt(i);
    }
    return out === 0;
  }

  function readJson(key, fallback) {
    try {
      var raw = localStorage.getItem(key);
      return raw ? JSON.parse(raw) : fallback;
    } catch (e) {
      return fallback;
    }
  }

  function writeJson(key, value) {
    localStorage.setItem(key, JSON.stringify(value));
  }

  function getExtraUsers() {
    var list = readJson(EXTRA_USERS_KEY, []);
    return Array.isArray(list) ? list : [];
  }

  function getAllUsers() {
    var extras = getExtraUsers();
    var names = {};
    extras.forEach(function (u) {
      if (u && u.username) names[u.username.toLowerCase()] = true;
    });
    var defaults = DEFAULT_USERS.filter(function (u) {
      return !names[u.username.toLowerCase()];
    });
    return defaults.concat(extras);
  }

  function findUser(username) {
    var needle = String(username || "").trim().toLowerCase();
    var users = getAllUsers();
    for (var i = 0; i < users.length; i++) {
      if (String(users[i].username || "").toLowerCase() === needle) {
        return users[i];
      }
    }
    return null;
  }

  function getSession() {
    var session = readJson(SESSION_KEY, null);
    if (!session || !session.username || !session.role) return null;
    if (session.expiresAt && Date.now() > session.expiresAt) {
      localStorage.removeItem(SESSION_KEY);
      return null;
    }
    return session;
  }

  function setSession(user) {
    var session = {
      username: user.username,
      displayName: user.displayName || user.username,
      role: user.role,
      expiresAt: Date.now() + SESSION_HOURS * 60 * 60 * 1000
    };
    writeJson(SESSION_KEY, session);
    return session;
  }

  function clearSession() {
    localStorage.removeItem(SESSION_KEY);
  }

  function isAdmin(session) {
    return !!(session && session.role === "admin");
  }

  function login(username, password) {
    var user = findUser(username);
    if (!user) {
      return Promise.resolve({ ok: false, error: "Benutzername oder Passwort ist falsch." });
    }
    return hashPassword(password).then(function (hash) {
      if (!safeEqual(hash, String(user.passwordHash || "").toLowerCase())) {
        return { ok: false, error: "Benutzername oder Passwort ist falsch." };
      }
      var session = setSession(user);
      return { ok: true, session: session };
    });
  }

  function logout() {
    clearSession();
    global.location.replace(getSiteRoot() + "login.html");
  }

  function createUser(payload) {
    var username = String((payload && payload.username) || "").trim().toLowerCase();
    var displayName = String((payload && payload.displayName) || username).trim();
    var role = payload && payload.role === "admin" ? "admin" : "listener";
    var password = String((payload && payload.password) || "");

    if (!/^[a-z0-9._-]{3,32}$/.test(username)) {
      return Promise.resolve({
        ok: false,
        error: "Benutzername: 3–32 Zeichen, nur Buchstaben, Zahlen, Punkt, Unterstrich oder Bindestrich."
      });
    }
    if (password.length < 8) {
      return Promise.resolve({ ok: false, error: "Das Passwort muss mindestens 8 Zeichen haben." });
    }
    if (findUser(username)) {
      return Promise.resolve({ ok: false, error: "Dieser Benutzername ist bereits vergeben." });
    }

    return hashPassword(password).then(function (passwordHash) {
      var extras = getExtraUsers();
      extras.push({
        username: username,
        displayName: displayName || username,
        role: role,
        passwordHash: passwordHash,
        createdAt: new Date().toISOString()
      });
      writeJson(EXTRA_USERS_KEY, extras);
      return { ok: true };
    });
  }

  function deleteUser(username) {
    var needle = String(username || "").trim().toLowerCase();
    if (needle === "admin" || needle === "listener") {
      return { ok: false, error: "Die Standardkonten können nicht gelöscht werden." };
    }
    var extras = getExtraUsers().filter(function (u) {
      return String(u.username || "").toLowerCase() !== needle;
    });
    writeJson(EXTRA_USERS_KEY, extras);
    return { ok: true };
  }

  function injectStyles() {
    if (document.getElementById("miw-auth-styles")) return;
    var style = document.createElement("style");
    style.id = "miw-auth-styles";
    style.textContent =
      "#miw-userbar{background:#041e38;color:#fff;font-family:Arial,Helvetica,sans-serif;font-size:13px;border-bottom:1px solid rgba(255,255,255,.08)}" +
      "#miw-userbar .miw-userbar-inner{max-width:1400px;margin:auto;padding:8px 18px;display:flex;align-items:center;gap:12px;flex-wrap:wrap}" +
      "#miw-userbar .miw-brand{font-weight:800;letter-spacing:.02em;margin-right:auto}" +
      "#miw-userbar .miw-meta{display:flex;align-items:center;gap:8px;flex-wrap:wrap}" +
      "#miw-userbar .miw-badge{display:inline-block;border-radius:999px;padding:3px 10px;font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase}" +
      "#miw-userbar .miw-badge.admin{background:#19a9ff;color:#04233d}" +
      "#miw-userbar .miw-badge.listener{background:#d9e7f2;color:#07335f}" +
      "#miw-userbar a,#miw-userbar button{font:inherit;color:#fff;background:transparent;border:1px solid rgba(255,255,255,.28);border-radius:6px;padding:5px 10px;text-decoration:none;cursor:pointer}" +
      "#miw-userbar a:hover,#miw-userbar button:hover{background:rgba(255,255,255,.12)}" +
      "#miw-userbar a.miw-admin-link{background:#0b6ebc;border-color:#0b6ebc;font-weight:700}" +
      "#miw-denied{max-width:1400px;margin:0 auto;background:#fff3cd;color:#5c4813;border-bottom:1px solid #efe0a8;padding:10px 18px;font-family:Arial,Helvetica,sans-serif;font-size:14px}";
    document.head.appendChild(style);
  }

  function applyRoleVisibility(session) {
    var admin = isAdmin(session);
    var hideStyle = document.getElementById("miw-hide-admin");
    if (admin && hideStyle) hideStyle.remove();
    var nodes = document.querySelectorAll("[data-admin-only]");
    for (var i = 0; i < nodes.length; i++) {
      nodes[i].hidden = !admin;
    }
  }

  function renderChrome(session) {
    if (!session || document.getElementById("miw-userbar")) return;
    injectStyles();
    var root = getSiteRoot();
    var bar = document.createElement("div");
    bar.id = "miw-userbar";
    bar.innerHTML =
      '<div class="miw-userbar-inner">' +
      '<span class="miw-brand">MedizinInformatik Wiki</span>' +
      '<span class="miw-meta">' +
      '<span class="miw-badge ' +
      session.role +
      '">' +
      (ROLE_LABELS[session.role] || session.role) +
      "</span>" +
      "<span>Angemeldet als <strong>" +
      escapeHtml(session.displayName || session.username) +
      "</strong></span>" +
      (isAdmin(session)
        ? '<a class="miw-admin-link" href="' + root + 'admin.html">Verwaltung</a>'
        : "") +
      '<button type="button" id="miw-logout">Abmelden</button>' +
      "</span></div>";
    document.body.insertBefore(bar, document.body.firstChild);
    document.getElementById("miw-logout").addEventListener("click", logout);

    try {
      var params = new URLSearchParams(global.location.search);
      if (params.get("zugriff") === "verweigert") {
        var note = document.createElement("div");
        note.id = "miw-denied";
        note.textContent =
          "Kein Zugriff: Der Admin-Bereich ist nur für Administratorinnen und Administratoren sichtbar.";
        bar.insertAdjacentElement("afterend", note);
      }
    } catch (e) {}

    applyRoleVisibility(session);
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }

  function guard() {
    var isPublic = document.documentElement.hasAttribute("data-public");
    var needsAdmin = document.documentElement.hasAttribute("data-admin");
    var session = getSession();
    var root = getSiteRoot();

    if (isPublic) {
      if (session && /login\.html$/i.test(global.location.pathname || global.location.href)) {
        var next = "";
        try {
          next = new URLSearchParams(global.location.search).get("next") || "";
        } catch (e) {}
        global.location.replace(next || root + "index.html");
      }
      return session;
    }

    if (!session) {
      var nextUrl = global.location.pathname + global.location.search + global.location.hash;
      global.location.replace(root + "login.html?next=" + encodeURIComponent(nextUrl));
      return null;
    }

    if (needsAdmin && !isAdmin(session)) {
      global.location.replace(root + "index.html?zugriff=verweigert");
      return null;
    }

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", function () {
        renderChrome(session);
      });
    } else {
      renderChrome(session);
    }
    return session;
  }

  var api = {
    getSiteRoot: getSiteRoot,
    getSession: getSession,
    getAllUsers: getAllUsers,
    isAdmin: isAdmin,
    login: login,
    logout: logout,
    createUser: createUser,
    deleteUser: deleteUser,
    hashPassword: hashPassword,
    roleLabels: ROLE_LABELS
  };

  global.MIWAuth = api;
  guard();
})(window);
