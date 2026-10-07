// Admin Dashboard UI (no framework)


// i18n helper — uses intentFlowI18n if available, falls back to English
function __(key, fallback) {
  if (window.intentFlowI18n && window.intentFlowI18n[key] !== undefined) {
    return window.intentFlowI18n[key];
  }
  return fallback || key;
}


function el(tag, props = {}, children = []) {
  const n = document.createElement(tag);
  Object.entries(props).forEach(([k, v]) => {
    if (k === "class") n.className = v;
    else if (k === "text") n.textContent = v;
    else if (k === "html") n.innerHTML = v;
    else if (k.startsWith("on") && typeof v === "function") n.addEventListener(k.slice(2).toLowerCase(), v);
    else if (v !== null && v !== undefined) n.setAttribute(k, v);
  });
  (Array.isArray(children) ? children : [children]).forEach((c) => {
    if (c == null) return;
    n.appendChild(typeof c === "string" ? document.createTextNode(c) : c);
  });
  return n;
}

function pretty(obj) {
  return JSON.stringify(obj, null, 2);
}

async function waitForIntentFlow(timeoutMs = 6000) {
  const start = Date.now();
  while (Date.now() - start < timeoutMs) {
    if (window.IntentFlow && typeof window.IntentFlow.run === "function") return true;
    await new Promise((r) => setTimeout(r, 60));
  }
  return false;
}

// ---------- History (localStorage, minimal) ----------
const HISTORY_KEY = "mmi_history_v1";
const HISTORY_LIMIT = 20;

function loadHistory() {
  try {
    const raw = localStorage.getItem(HISTORY_KEY);
    const arr = raw ? JSON.parse(raw) : [];
    return Array.isArray(arr) ? arr : [];
  } catch {
    return [];
  }
}

function saveHistory(items) {
  try {
    localStorage.setItem(HISTORY_KEY, JSON.stringify(items.slice(0, HISTORY_LIMIT)));
  } catch {}
}

function addHistory(entry) {
  const items = loadHistory();
  items.unshift(entry);
  saveHistory(items);
}

function fmtTime(ts) {
  try {
    const d = new Date(ts);
    return d.toLocaleString();
  } catch {
    return String(ts);
  }
}

// ---------- Prefs (localStorage) ----------
const PREFS_KEY = "mmi_prefs_v2";
function loadPrefs() {
  try {
    return JSON.parse(localStorage.getItem(PREFS_KEY) || "{}");
  } catch {
    return {};
  }
}
function savePrefs(p) {
  try {
    localStorage.setItem(PREFS_KEY, JSON.stringify(p || {}));
  } catch {}
}

// ---------- Runner history (localStorage) ----------
const RUNNER_HISTORY_KEY = "mmi_runner_history_v1";
const RUNNER_HISTORY_LIMIT = 10;

function loadRunnerHistory() {
  try {
    const raw = localStorage.getItem(RUNNER_HISTORY_KEY);
    const arr = raw ? JSON.parse(raw) : [];
    return Array.isArray(arr) ? arr : [];
  } catch {
    return [];
  }
}

function saveRunnerHistory(items) {
  try {
    localStorage.setItem(RUNNER_HISTORY_KEY, JSON.stringify(items.slice(0, RUNNER_HISTORY_LIMIT)));
  } catch {}
}

function pushRunnerHistory(entry) {
  const items = loadRunnerHistory();
  const key = (entry.intentId || "") + "::" + (entry.payloadText || "");
  const filtered = items.filter((x) => ((x.intentId || "") + "::" + (x.payloadText || "")) !== key);
  filtered.unshift(entry);
  saveRunnerHistory(filtered);
}

function downloadJson(filename, dataObj) {
  const blob = new Blob([JSON.stringify(dataObj, null, 2)], { type: "application/json" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

function safeParseJson(raw) {
  if (!raw || !raw.trim()) return { ok: true, value: {} };
  try {
    const v = JSON.parse(raw);
    return { ok: true, value: v };
  } catch (e) {
    return { ok: false, error: e?.message || String(e) };
  }
}


function validateAgainstSchema(schema, payload) {
  const errors = [];
  if (!schema || typeof schema !== "object") return errors;

  const req = Array.isArray(schema.required) ? schema.required : [];
  const props = schema.properties && typeof schema.properties === "object" ? schema.properties : {};

  req.forEach((k) => {
    if (payload == null || payload[k] === undefined || payload[k] === null || payload[k] === "") {
      errors.push(`Falta "${k}"`);
    }
  });

  Object.keys(props).forEach((k) => {
    if (payload == null || payload[k] === undefined || payload[k] === null) return;
    const rule = props[k] || {};
    const v = payload[k];

    const t = rule.type;
    if (t === "string" && typeof v !== "string") errors.push(`"${k}" debe ser string`);
    if (t === "number" && typeof v !== "number") errors.push(`"${k}" debe ser number`);
    if (t === "boolean" && typeof v !== "boolean") errors.push(`"${k}" debe ser boolean`);
    if (t === "object" && (typeof v !== "object" || Array.isArray(v))) errors.push(`"${k}" debe ser object`);
    if (t === "array" && !Array.isArray(v)) errors.push(`"${k}" debe ser array`);

    if (rule.enum && Array.isArray(rule.enum) && !rule.enum.includes(v)) {
      errors.push(`"${k}" debe ser uno de: ${rule.enum.join(", ")}`);
    }
    if (typeof v === "string") {
      if (typeof rule.min === "number" && v.length < rule.min) errors.push(`"${k}" mínimo ${rule.min} caracteres`);
      if (typeof rule.max === "number" && v.length > rule.max) errors.push(`"${k}" máximo ${rule.max} caracteres`);
    }
  });

  return errors;
}

// Fallback preset
function fallbackPreset(intentId) {
  const id = String(intentId || "").trim();
  if (id.endsWith(".latest")) return { limit: 50 };
  if (id.endsWith(".export")) return { limit: 200 };
  return {};
}

document.addEventListener("DOMContentLoaded", async () => {
  const root = document.getElementById("mmi-dashboard");
  if (!root) return;

  const loading = document.getElementById("mmi-loading");
  const showFatal = (msg, detail=null) => {
    if (loading) {
      loading.style.display = "";
      loading.innerHTML = "";
      loading.appendChild(el("h2",{text:"Intent Flow (UI)"}));
      loading.appendChild(el("p",{class:"mmi-danger",text: msg || "Error cargando la UI."}));
      if (detail) loading.appendChild(el("pre",{class:"mmi-code",text: (typeof detail==="string"?detail: pretty(detail)).slice(0,2000)}));
    }
    root.style.display = "none";
  };

  // ----- Minimal Dashboard (presentable) -----
  // If we're on Dashboard, render a clean "home" instead of the full developer UI.
  // Advanced/dev tools remain available in their dedicated menus (Flows/Connections/Logs/etc).
  
  function __mmiDetectPageFromUrl() {
    try {
      const p = new URLSearchParams(window.location.search).get('page') || '';
      const norm = String(p).replace(/_/g,'-').toLowerCase();
      if (norm.includes('mmi-')) {
        if (norm.includes('help')) return 'help';
        if (norm.includes('event')) return 'events';
        if (norm.includes('flow')) return 'flows';
        if (norm.includes('connection')) return 'connections';
        if (norm.includes('integration')) return 'integrations';
        if (norm.includes('log')) return 'logs';
        if (norm.includes('sched')) return 'scheduler';
        if (norm.includes('setting')) return 'settings';
        if (norm.includes('dashboard')) return 'dashboard';
      }
    } catch(e) {}
    return null;
  }

const __mmiPageKey = (root && root.dataset && root.dataset.mmiPage) ? String(root.dataset.mmiPage) : (__mmiDetectPageFromUrl() || 'dashboard');
  if (__mmiPageKey === 'dashboard') {
    if (loading) loading.style.display = "none";
    root.style.display = "";

    // Perfil (Novato / Negocio / Técnico) en Dashboard (solo persiste preferencia)
    const PROFILE_KEY = "mmi_profile_v1";
    let __mmiProfile = "business";
    try { __mmiProfile = String(localStorage.getItem(PROFILE_KEY) || "").trim() || "business"; } catch (e) { __mmiProfile = "business"; }

    root.innerHTML = `
      <div class="mmi-home">
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:12px 0 18px;">
          <strong style="margin-right:6px;">Perfil:</strong>
          <button type="button" class="button" data-mmi-profile="novice">👤 Novato</button>
          <button type="button" class="button" data-mmi-profile="business">🧑‍💼 Negocio</button>
          <button type="button" class="button" data-mmi-profile="tech">🧑‍💻 Técnico</button>
          <span id="mmi-profile-saved" style="display:none;margin-left:8px;" class="mmi-muted">Guardado</span>
        </div>

        <div class="mmi-home-grid">
          <div class="mmi-home-card">
            <h2>⚡ Crear Flow</h2>
            <p class="mmi-muted">Crea una automatización nueva.</p>
            <a class="button button-primary" href="admin.php?page=mmi-flows">Nuevo Flow</a>
          </div>

          <div class="mmi-home-card">
            <h2>🔁 Flows</h2>
            <p class="mmi-muted">Ver y editar automatizaciones.</p>
            <a class="button" href="admin.php?page=mmi-flows">Ver Flows</a>
          </div>

          <div class="mmi-home-card">
            <h2>🔌 Connections</h2>
            <p class="mmi-muted">Conecta Telegram, Slack, HTTP…</p>
            <a class="button" href="admin.php?page=mmi-connections">Gestionar Connections</a>
          </div>

          <div class="mmi-home-card">
            <h2>📜 Logs</h2>
            <p class="mmi-muted">Revisa ejecuciones, errores y auditoría.</p>
            <a class="button" href="admin.php?page=mmi-logs">Ver Logs</a>
          </div>

          <div class="mmi-home-card">
            <h2>🧩 Integrations</h2>
            <p class="mmi-muted">Catálogo y acciones listas para usar.</p>
            <a class="button" href="admin.php?page=mmi-integrations">Ver Integrations</a>
          </div>

          <div class="mmi-home-card">
            <h2>⏱️ Scheduler</h2>
            <p class="mmi-muted">Cola, tareas programadas y reintentos.</p>
            <a class="button" href="admin.php?page=mmi-scheduler">Abrir Scheduler</a>
          </div>

          <div class="mmi-home-card">
            <h2>⚙️ Settings</h2>
            <p class="mmi-muted">Ajustes globales del plugin.</p>
            <a class="button" href="admin.php?page=mmi-settings">Abrir Settings</a>
          </div>
        </div>
      </div>
    `;

    // Activar botón actual + persistir
    const savedMsg = root.querySelector("#mmi-profile-saved");
    function setActiveProfile(p) {
      root.querySelectorAll("[data-mmi-profile]").forEach((b) => {
        const isActive = b.getAttribute("data-mmi-profile") === p;
        b.classList.toggle("button-primary", isActive);
      });
    }
    setActiveProfile(__mmiProfile);

    root.querySelectorAll("[data-mmi-profile]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const p = btn.getAttribute("data-mmi-profile") || "business";
        __mmiProfile = p;
        try { localStorage.setItem(PROFILE_KEY, p); } catch (e) {}
        setActiveProfile(p);
        if (savedMsg) {
          savedMsg.style.display = "";
          clearTimeout(savedMsg.__t);
          savedMsg.__t = setTimeout(() => { savedMsg.style.display = "none"; }, 1200);
        }
      });
    });
    return;
  }

  // Para páginas que no son dashboard, el routing se hace más abajo (mountSubpage).
  // Pero primero necesitamos inicializar IntentFlow para que el JS funcione.
  // Si no es dashboard ni una subpágina conocida, salimos.
  if (__mmiPageKey === 'events') {
    const okBoot = await waitForIntentFlow();
    if (!okBoot) { showFatal('No se ha podido inicializar IntentFlow.'); return; }
    if (loading) loading.style.display = "none";
    root.style.display = "";
    root.innerHTML = "";
    const eventsHost = document.createElement('div');
    root.appendChild(eventsHost);
    // mountEvents se define más abajo, usamos setTimeout para garantizar que está disponible
    setTimeout(() => {
      try { mountEvents(eventsHost); } catch(e) { console.error("MMI events mount error", e); }
    }, 0);
    return;
  }

  root.innerHTML = "";
  root.classList.add("mmi-dashboard");

  try {

  const okBoot = await waitForIntentFlow();
  if (!okBoot) { showFatal('No se ha podido inicializar IntentFlow. Revisa errores JS/PHP y recarga.'); return; }

  // Safe API wrapper (never rely on undefined globals)
  async function api(intentId, payload = {}) {
  window.mmiApi = api;
    try {
      const r = await window.IntentFlow.run(intentId, payload, {});
      if (!r) return { ok:false, error:'empty_response' };
      if (r.ok === false) return r;
      // engine format: {ok:true, result:{...}}
      return r.result !== undefined ? r.result : r;
    } catch (e) {
      return { ok:false, error:'js_exception', message: (e && e.message) ? e.message : String(e) };
    }
  }

  if (!okBoot) {
    root.appendChild(
      el("div", { class: "mmi-card" }, [
        el("h2", { text: "Intent Flow" }),
        el("p", { class: "mmi-muted", text: "No se pudo inicializar window.IntentFlow. Asegúrate de que el script bootstrap está cargado y recarga la página." }),
      ])
    );
    return;
  }

  let prefs = loadPrefs();
  let advanced = !!prefs.advanced;

  // ---------- Profile (Novato / Negocio / Técnico) ----------
  const PROFILE_KEY = "mmi_profile_v1";
  function loadProfile() {
    try { return String(localStorage.getItem(PROFILE_KEY) || "").trim() || ""; } catch { return ""; }
  }
  function saveProfile(p) {
    try { localStorage.setItem(PROFILE_KEY, String(p || "")); } catch {}
  }
  let profile = loadProfile() || "business";

  const profileBar = el("div", { class: "mmi-actions", role: "group", "aria-label": "Perfil de uso" }, []);
  function mkProfileBtn(id, label) {
    return el("button", {
      type: "button",
      class: "mmi-button",
      "data-profile": id,
      text: label,
      onclick: () => { profile = id; saveProfile(profile); applyProfile(); },
    });
  }
  const btnPNovice   = mkProfileBtn("novice", "👤 Novato");
  const btnPBusiness = mkProfileBtn("business", "🧑‍💼 Negocio");
  const btnPTech     = mkProfileBtn("tech", "🧑‍💻 Técnico");
  profileBar.appendChild(btnPNovice);
  profileBar.appendChild(btnPBusiness);
  profileBar.appendChild(btnPTech);

  const advancedWrap = el("label", { class: "mmi-badge", style: "display:none;" }, [
    el("input", {
      type: "checkbox",
      ...(advanced ? { checked: "checked" } : {}),
      onchange: (ev) => {
        advanced = !!ev.target.checked;
        prefs = { ...prefs, advanced };
        savePrefs(prefs);
        applyProfile();
      },
    }),
    el("span", { text: "Modo técnico (mostrar todo)" }),
  ]);

  function setActiveProfileButton() {
    const map = { novice: btnPNovice, business: btnPBusiness, tech: btnPTech };
    Object.values(map).forEach((b) => { b.className = "mmi-button"; });
    if (map[profile]) map[profile].className = "mmi-button mmi-button-primary";
  }

  function setSectionVisible(secEl, visible) {
    if (!secEl) return;
    secEl.style.display = visible ? "block" : "none";
  }

  async function applyProfile() {
    setActiveProfileButton();

    // Novato: solo acciones guiadas (crear/actualizar + historial + logs). Sin herramientas.
    if (profile === "novice") {
      advCard.style.display = "none";
      advancedWrap.style.display = "none";
      return;
    }

    // Negocio: presets + flows + auditoría, sin runner/intents
    advCard.style.display = "block";
    advancedWrap.style.display = (profile === "tech") ? "inline-flex" : "none";

    const showAll = (profile === "tech" && advanced);

    setSectionVisible(secTools,  showAll);                 // botones dev
    setSectionVisible(secRunner, showAll);                 // runner
    setSectionVisible(secIntents,showAll);                 // lista intents
    setSectionVisible(secPresets, true);                   // siempre en negocio/tech
    setSectionVisible(secFlows,   true);
    setSectionVisible(secAudit,   true);
    setSectionVisible(secExec,    true);

    // Cargas necesarias (idempotentes)
    try { await loadPresets(); } catch {}
    try { await loadFlows(); } catch {}
    try { await loadAudit(); } catch {}
    try { await loadExecution(); } catch {}
    if (showAll) {
      try { await loadIntents(); } catch {}
      renderRunnerHistory();
    }
  }

  // intent metadata cache
  let intentMeta = {}; // id -> meta

  // ---------- Header ----------
  const header = el("div", { class: "mmi-card mmi-help" }, [
    el("div", { class: "mmi-row" }, [
      el("div", { class: "mmi-col" }, [
        el("h2", { text: "Intent Flow" }),
        el("p", { class: "mmi-muted", html: "Panel para ejecutar <strong>intents</strong> (acciones) de forma segura. Hotkey: <span class=\"mmi-kbd\">Alt</span>+<span class=\"mmi-kbd\">Shift</span>+<span class=\"mmi-kbd\">M</span>." }),
      ]),
      el("div", { class: "mmi-col" }, [
        el("div", { class: "mmi-actions" }, [profileBar, advancedWrap]),
      ]),
    ]),
  ]);

  // ---------- Status / logs ----------
  const statusBox = el("pre", { class: "mmi-pre" }, "");

  // ---------- Live UI ----------
  // Visible badge (countdown). Screen reader announcements handled separately to avoid spam.
  const liveInfo = el("span", { id: "mmi-live-badge", class: "mmi-badge", text: "Live: comprobando...", "aria-hidden": "true", "data-mmi-live": "badge" });
  const liveTime = el("span", { id: "mmi-live-time", class: "mmi-muted", text: "", "aria-hidden": "true", "data-mmi-live": "time" });
  const liveAnnounce = el("span", { class: "screen-reader-text", role: "status", "aria-live": "polite" }, "");

  const liveState = { active: false, remaining: 0, until: 0, lastAnnounced: null };

  function fmtSeconds(s) {
    const n = Math.max(0, Number(s || 0));
    const mm = String(Math.floor(n / 60)).padStart(2, "0");
    const ss = String(n % 60).padStart(2, "0");
    return `${mm}:${ss}`;
  }


  function renderLiveBadge() {
    const s = liveState;
    if (s.active && s.remaining > 0) {
      const n = Math.max(0, Number(s.remaining || 0));
      liveInfo.textContent = `Live: ON (${n}s)`;
      liveInfo.className = "mmi-badge mmi-badge-warn";
      // Extra visible time (more perceptible than small parentheses in some zoom levels)
      liveTime.textContent = `Expira en ${fmtSeconds(n)} (${n}s)`;
    } else {
      liveInfo.textContent = "Live: OFF";
      liveInfo.className = "mmi-badge";
      liveTime.textContent = "";
    }
  }

  function announceIfNeeded() {
    const key = liveState.active ? "on" : "off";
    if (key !== liveState.lastAnnounced) {
      liveState.lastAnnounced = key;
      liveAnnounce.textContent = liveState.active
        ? "Modo Live activado."
        : "Modo Live desactivado.";
    }
  }

  async function refreshLiveStatus() {
    try {
      const s = await window.IntentFlow.run("server.session.live.status");
      const r = s?.result || {};
      liveState.active = !!r.live_active;
      liveState.remaining = Math.max(0, Number(r.remaining || 0));
      liveState.until = Number(r.live_until || 0);
      renderLiveBadge();
      announceIfNeeded();
    } catch {
      liveInfo.textContent = "Live: error";
      liveInfo.className = "mmi-badge mmi-badge-bad";
    }
  }

  // Local countdown tick (no server spam). Periodically resync from server.
  setInterval(() => {
    if (liveState.active && liveState.remaining > 0) {
      liveState.remaining = Math.max(0, liveState.remaining - 1);
      renderLiveBadge();
      if (liveState.remaining === 0) refreshLiveStatus();
    }
  }, 1000);

  setInterval(() => {
    // keep it fresh in case another tab changes live_until
    refreshLiveStatus();
  }, 15000);

  const liveBar = el("div", { class: "mmi-card" }, [
    el("h3", { text: "Modo Live" }),
    el("div", { class: "mmi-actions", role: "group", "aria-label": "Controles de modo Live" }, [
      liveInfo,
      liveTime,
      el("button", { type: "button", class: "mmi-button mmi-button-small", text: "Activar Live (10 min)", onclick: async () => { await window.IntentFlow.enableLive?.(600); await refreshLiveStatus(); } }),
      el("button", { type: "button", class: "mmi-button mmi-button-small", text: "Desactivar Live", onclick: async () => { await window.IntentFlow.disableLive?.(); await refreshLiveStatus(); } }),
      liveAnnounce,
    ]),
    el("p", { class: "mmi-muted", text: "Las acciones unsafe (crear/actualizar posts, limpiar auditoría, etc.) se ejecutan con Live automático desde este panel." }),
  ]);


  // Initial Live status refresh on load (so users see ON/OFF immediately)
  refreshLiveStatus();
  // ---------- Form macro.post.ensure ----------
  const inputTitle = el("input", { type: "text", placeholder: "Título (requerido)", style: "width:100%; max-width:640px;" });
  const inputStatus = el("select", { style: "width:220px;" }, [
    el("option", { value: "draft", text: "draft" }),
    el("option", { value: "publish", text: "publish" }),
    el("option", { value: "pending", text: "pending" }),
    el("option", { value: "private", text: "private" }),
  ]);
  const inputContent = el("textarea", { placeholder: "Contenido (opcional)", style: "width:100%; max-width:900px; height:160px;" });
  const linksBox = el("div", { style: "margin-top:8px;" });
  const inlineMsg = el("div", { style: "margin-top:8px; display:none;" }, "");

  function setInlineError(msg) {
    inlineMsg.style.display = msg ? "block" : "none";
    inlineMsg.className = msg ? "mmi-badge mmi-badge-bad" : "";
    inlineMsg.textContent = msg || "";
  }

  function setBusy(isBusy) {
    btnRunEnsure.disabled = !!isBusy;
    btnRunEnsure.textContent = isBusy ? "Ejecutando..." : "Crear / actualizar";
  }

  function historyEntryFromResult(payload, res) {
    const r = res?.result || {};
    return {
      ts: Date.now(),
      intentId: "macro.post.ensure",
      ok: !!res?.ok,
      error: res?.ok ? "" : res?.error || "",
      reason: res?.ok ? "" : res?.reason || "",
      payload: { title: payload.title, status: payload.status },
      post_id: r?.post_id || null,
      action: r?.action || "",
      edit: r?.edit || "",
      view: r?.view || "",
    };
  }

  function ensureDefaultTitle() {
  const t = (inputTitle.value || "").trim();
  if (t) return t;
  const d = new Date();
  const pad = (n) => String(n).padStart(2,"0");
  return `Nuevo post ${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
function quickEnsure(status) {
  try {
    inputStatus.value = status;
    inputTitle.value = ensureDefaultTitle();
    // contenido puede ir vacío; el server lo permite
    btnRunEnsure.click();
  } catch (e) {
    setInlineError(e?.message || String(e));
  }
}
function quickClear() {
  inputTitle.value = "";
  inputStatus.value = "draft";
  inputContent.value = "";
  setInlineError("");
  linksBox.innerHTML = "";
}

const btnRunEnsure = el("button", {
    type: "button",
    text: "Crear / actualizar",
    onclick: async () => {
      setInlineError("");
      linksBox.innerHTML = "";

      const title = inputTitle.value.trim();
      const status = inputStatus.value;
      const content = inputContent.value;

      if (!title) {
        setInlineError("Falta el título.");
        return;
      }

      if (status === "publish") {
        const ok = window.confirm("Esto publicará el post en la web. ¿Continuar?");
        if (!ok) return;
      }

      const payload = { title, status, content };

      setBusy(true);
      statusBox.textContent = "Ejecutando macro.post.ensure (auto-live)...";

      try {
        const res = await window.IntentFlow.live(600, async () => {
          return await window.IntentFlow.run("macro.post.ensure", payload);
        });

        statusBox.textContent = pretty(res);

        const r = res?.result;
        if (res?.ok && r?.edit) {
          linksBox.innerHTML = "";
          linksBox.appendChild(el("a", { href: r.edit, target: "_blank", text: "Editar post" }));
          linksBox.appendChild(document.createTextNode(" · "));
          linksBox.appendChild(el("a", { href: r.view, target: "_blank", text: "Ver post" }));
        }

        addHistory(historyEntryFromResult(payload, res));
        renderHistory();
        if (advanced) await loadAudit();
      } catch (e) {
        statusBox.textContent = "Error: " + (e?.message || String(e));
      } finally {
        setBusy(false);
        await refreshLiveStatus();
      }
    },
  });

  const ensureCard = el("div", { class: "mmi-card" }, [
    el("h3", { text: "Crear/actualizar post" }),
    el("p", { class: "mmi-muted", text: "Crea o actualiza un post. Usa Live automático para evitar bloqueos con safe_mode." }),
    el("div", { class: "mmi-actions", role: "group", "aria-label": "Acciones rápidas" }, [
      el("button", { type: "button", class: "mmi-button", text: "📝 Crear borrador", onclick: () => quickEnsure("draft") }),
      el("button", { type: "button", class: "mmi-button", text: "🚀 Publicar", onclick: () => quickEnsure("publish") }),
      el("button", { type: "button", class: "mmi-button", text: "🧹 Limpiar", onclick: () => quickClear() }),
    ]),
    el("div", {}, [el("label", { text: "Título" }), el("div", { style: "margin-top:6px;" }, [inputTitle])]),
    el("div", { style: "margin-top:10px;" }, [el("label", { text: "Status" }), el("div", { style: "margin-top:6px;" }, [inputStatus])]),
    el("div", { style: "margin-top:10px;" }, [el("label", { text: "Contenido" }), el("div", { style: "margin-top:6px;" }, [inputContent])]),
    el("div", { style: "margin-top:10px;" }, [btnRunEnsure, inlineMsg, linksBox]),
  ]);

  // ---------- History UI ----------
  const historyBox = el("div", { class: "mmi-card" }, []);
  const btnClearHistory = el("button", {
    type: "button",
    text: "Borrar historial local",
    onclick: () => {
      saveHistory([]);
      renderHistory();
    },
  });

  function renderHistory() {
    const items = loadHistory();
    historyBox.innerHTML = "";
    historyBox.appendChild(el("h3", { text: "Historial (local)" }));
    historyBox.appendChild(el("p", { class: "mmi-muted", text: "Se guarda en este navegador. No guarda el contenido, solo título/status + resultado." }));
    historyBox.appendChild(btnClearHistory);

    if (!items.length) {
      historyBox.appendChild(el("p", { class: "mmi-muted", text: "Sin historial todavía." }));
      return;
    }

    const ul = el("ul", { style: "margin:10px 0 0; padding-left:18px;" });
    items.forEach((it) => {
      const ok = it.ok ? "✅" : "❌";
      const title = it?.payload?.title || "";
      const status = it?.payload?.status || "";
      const when = fmtTime(it.ts);

      const actions = el("div", { class: "mmi-actions", style: "margin-top:6px;" }, []);
      actions.appendChild(
        el("button", {
          type: "button",
          text: "Repetir",
          onclick: () => {
            inputTitle.value = title;
            inputStatus.value = status || "draft";
            inputTitle.focus();
            statusBox.textContent = "Formulario rellenado desde historial.";
          },
        })
      );
      if (it.edit) actions.appendChild(el("a", { href: it.edit, target: "_blank", text: __("edit","Edit") }));
      if (it.view) actions.appendChild(el("a", { href: it.view, target: "_blank", text: "Ver" }));

      ul.appendChild(el("li", { style: "margin:10px 0;" }, [
        el("div", {}, [el("span", { text: `${ok} ${it.intentId} — "${title}" [${status}] ` }), el("span", { class: "mmi-muted", text: `(${when})` })]),
        actions,
      ]));
    });
    historyBox.appendChild(ul);
  }

  // ---------- Advanced: Runner + Intents list + Audit ----------
  const advCard = el("div", { class: "mmi-card", style: "display:none;" }, []);
  const intentsDatalist = el("datalist", { id: "mmi-intents-datalist" }, []);

  // Quick actions
  const quickRow = el("div", { class: "mmi-actions" }, []);
  async function runQuick(id, payload = {}) {
    statusBox.textContent = `Ejecutando ${id}...`;
    try {
      const res = await window.IntentFlow.run(id, payload);
      statusBox.textContent = pretty(res);
      if (advanced) await loadAudit();
      return res;
    } catch (e) {
      statusBox.textContent = "Error: " + (e?.message || String(e));
    } finally {
      await refreshLiveStatus();
    }
  }
  quickRow.appendChild(el("button", { type: "button", text: "Registry", onclick: () => runQuick("server.registry.list") }));
  quickRow.appendChild(el("button", { type: "button", text: "Describe", onclick: () => runQuick("server.registry.describe") }));
  quickRow.appendChild(el("button", { type: "button", text: "Audit latest", onclick: () => runQuick("server.audit.latest", { limit: 50 }) }));
  quickRow.appendChild(el("button", { type: "button", text: "Live status", onclick: () => runQuick("server.session.live.status") }));

  // Runner
  const runnerIntent = el("input", {
    type: "text",
    list: "mmi-intents-datalist",
    placeholder: "intentId (elige o escribe: server.registry.list)",
    style: "width:100%; max-width:520px;",
  });
  const runnerPayload = el("textarea", { placeholder: 'payload JSON (ej: {"limit":20})', style: "width:100%; max-width:900px; height:140px;" });
  const runnerMsg = el("div", { style: "margin-top:8px; display:none;" }, "");
  const runnerInfo = el("div", { style: "margin-top:10px;" }, "");

  // Runner (schema -> form)
  let runnerFormEls = {}; // field -> input
  let runnerCurrentSchema = null;
  let runnerView = "json"; // 'form' | 'json'

  const runnerTabs = el("div", { class: "mmi-actions", style: "margin-top:10px;" }, []);
  const tabForm = el("button", { type: "button", class: "mmi-button", text: "Formulario" });
  const tabJson  = el("button", { type: "button", class: "mmi-button", text: "JSON" });
  runnerTabs.appendChild(tabForm);
  runnerTabs.appendChild(tabJson);

  const runnerFormBox = el("div", { style: "margin-top:10px; display:none;" }, "");

  function setRunnerView(v) {
    runnerView = v;
    const hasSchema = !!runnerCurrentSchema;
    runnerFormBox.style.display = (v === "form" && hasSchema) ? "block" : "none";
    runnerPayload.style.display = (v === "json") ? "block" : "none";
    tabForm.className = "mmi-button" + (v === "form" ? " mmi-button-primary" : "");
    tabJson.className  = "mmi-button" + (v === "json" ? " mmi-button-primary" : "");
  }

  tabForm.addEventListener("click", () => setRunnerView("form"));
  tabJson.addEventListener("click", () => setRunnerView("json"));

  function isTextareaField(key, rule) {
    if (key === "content" || key === "description" || key === "notes") return true;
    if (rule && rule.type === "string" && rule.max && rule.max > 250) return true;
    return false;
  }

  function payloadFromForm() {
    const out = {};
    Object.keys(runnerFormEls).forEach((k) => {
      const elx = runnerFormEls[k];
      if (!elx) return;
      if (elx.type === "checkbox") out[k] = !!elx.checked;
      else if (elx.type === "number") {
        const v = elx.value;
        out[k] = v === "" ? null : Number(v);
      } else {
        out[k] = elx.value;
      }
    });
    // prune nulls
    Object.keys(out).forEach((k)=>{ if (out[k] === null) delete out[k]; });
    return out;
  }

  function setJsonFromPayload(payloadObj) {
    runnerPayload.value = JSON.stringify(payloadObj || {}, null, 2);
  }

  function renderFormFromSchema(schema) {
    runnerFormEls = {};
    runnerFormBox.innerHTML = "";
    if (!schema || typeof schema !== "object") return;

    const req = Array.isArray(schema.required) ? schema.required : [];
    const props = schema.properties && typeof schema.properties === "object" ? schema.properties : {};

    const currentParsed = safeParseJson(runnerPayload.value);
    const current = currentParsed.ok ? (currentParsed.value || {}) : {};

    Object.keys(props).forEach((key) => {
      const rule = props[key] || {};
      const labelTxt = key + (req.includes(key) ? " *" : "");

      let input;
      if (Array.isArray(rule.enum)) {
        input = el("select", { style: "width:260px;" }, rule.enum.map((v)=> el("option", { value: String(v), text: String(v) })));
      } else if (rule.type === "boolean") {
        input = el("input", { type: "checkbox" });
      } else if (rule.type === "number" || rule.type === "integer") {
        input = el("input", { type: "number", style: "width:160px;" });
        if (rule.min !== undefined) input.min = String(rule.min);
        if (rule.max !== undefined) input.max = String(rule.max);
      } else {
        if (isTextareaField(key, rule)) {
          input = el("textarea", { style: "width:100%; max-width:900px; height:110px;" });
        } else {
          input = el("input", { type: "text", style: "width:100%; max-width:520px;" });
        }
      }

      // set current value
      if (rule.type === "boolean") {
        input.checked = !!current[key];
      } else if (current[key] !== undefined && current[key] !== null) {
        input.value = String(current[key]);
      }

      // help text
      const help = [];
      if (rule.enum) help.push("Valores: " + rule.enum.join(", "));
      if (rule.min !== undefined) help.push("min: " + rule.min);
      if (rule.max !== undefined) help.push("max: " + rule.max);

      const row = el("div", { style: "margin:10px 0;" }, [
        el("label", { html: `<strong>${labelTxt}</strong>` }),
        el("div", { style: "margin-top:6px;" }, [input]),
        help.length ? el("div", { class: "mmi-muted", text: help.join(" · ") }) : null,
      ]);

      runnerFormEls[key] = input;
      input.addEventListener("input", () => {
        const p = payloadFromForm();
        setJsonFromPayload(p);
      });

      runnerFormBox.appendChild(row);
    });
  }

  function updateRunnerSchemaUI() {
    const id = runnerIntent.value.trim();
    const meta = intentMeta[id];
    runnerCurrentSchema = meta && meta.schema ? meta.schema : null;

    // Tabs visibility
    if (runnerCurrentSchema) {
      // ensure tabs visible
      if (!runnerTabs.isConnected) {
        // will be inserted later in DOM
      }
      renderFormFromSchema(runnerCurrentSchema);
      // default to form if json is empty
      const p = safeParseJson(runnerPayload.value);
      const isEmpty = p.ok && p.value && Object.keys(p.value).length === 0;
      if (runnerView === "json" && isEmpty) setRunnerView("form");
    } else {
      runnerFormBox.style.display = "none";
      runnerPayload.style.display = "block";
      setRunnerView("json");
    }
  }


    // Si editas el JSON manualmente, refresca el formulario al salir del campo
  runnerPayload.addEventListener("blur", () => {
    if (runnerCurrentSchema) renderFormFromSchema(runnerCurrentSchema);
  });

function setRunnerMsg(msg, isError = true) {
    runnerMsg.style.display = msg ? "block" : "none";
    runnerMsg.className = msg ? (isError ? "mmi-badge mmi-badge-bad" : "mmi-badge mmi-badge-ok") : "";
    runnerMsg.textContent = msg || "";
  }

  function renderRunnerInfo() {
    const id = runnerIntent.value.trim();
    const meta = intentMeta[id];
    runnerInfo.innerHTML = "";
    if (!id) return;
    if (!meta) {
      runnerInfo.appendChild(el("div", { class: "mmi-muted" }, "Sin metadatos (usa Describe/Registry)."));
      return;
    }
    const risk = meta.risk || (meta.policy?.unsafe ? "unsafe" : "safe");
    const badgeClass = risk === "unsafe" ? "mmi-badge-warn" : "mmi-badge-ok";
    runnerInfo.appendChild(el("div", { class: "mmi-actions" }, [
      el("span", { class: `mmi-badge ${badgeClass}`, text: `${risk === "unsafe" ? "⚠️" : "✅"} ${risk}` }),
      meta.policy?.capability ? el("span", { class: "mmi-badge", text: `cap: ${meta.policy.capability}` }) : null,
      meta.policy?.scope ? el("span", { class: "mmi-badge", text: `scope: ${meta.policy.scope}` }) : null,
    ]));
    runnerInfo.appendChild(el("div", { style: "margin-top:8px;" }, [
      el("strong", { text: meta.title || id }),
      meta.description ? el("div", { class: "mmi-muted", text: meta.description }) : null,
    ]));
  }

  function getPresetForIntent(intentId) {
    const id = String(intentId || "").trim();
    const meta = intentMeta[id];
    if (meta && meta.example_payload && typeof meta.example_payload === "object") return meta.example_payload;
    return fallbackPreset(id);
  }

  const runnerHistoryTitle = el("h4", { text: "Recientes (runner)" });
  const runnerHistoryBox = el("div", { style: "margin-top:6px;" }, "");
  function renderRunnerHistory() {
    const items = loadRunnerHistory();
    runnerHistoryBox.innerHTML = "";
    if (!items.length) {
      runnerHistoryBox.appendChild(el("p", { class: "mmi-muted", text: "Sin recientes todavía." }));
      return;
    }
    const ul = el("ul", { style: "margin:0; padding-left:18px;" });
    items.forEach((it) => {
      ul.appendChild(el("li", { style: "margin:8px 0;" }, [
        el("code", { text: it.intentId || "" }),
        el("span", { class: "mmi-muted", text: ` (${fmtTime(it.ts)})` }),
        el("button", {
          type: "button",
          style: "margin-left:10px;",
          text: "Cargar",
          onclick: () => {
            runnerIntent.value = it.intentId || "";
            runnerPayload.value = it.payloadText || "";
            renderRunnerInfo();
            setRunnerMsg("Cargado desde recientes.", false);
          },
        }),
      ]));
    });
    runnerHistoryBox.appendChild(ul);
  }

  function applyPreset() {
    const id = runnerIntent.value.trim();
    if (!id) return setRunnerMsg("Primero selecciona un intentId.", true);
    const preset = getPresetForIntent(id);
    runnerPayload.value = JSON.stringify(preset, null, 2);
    setRunnerMsg("Preset aplicado.", false);
    updateRunnerSchemaUI();
  }

  function validateRunnerJson() {
    const p = safeParseJson(runnerPayload.value);
    if (!p.ok) {
      setRunnerMsg("JSON inválido: " + p.error, true);
      return null;
    }
    setRunnerMsg("JSON OK.", false);
    return p.value;
  }

  const btnPreset = el("button", { type: "button", text: "Preset", onclick: applyPreset });
  const btnValidate = el("button", { type: "button", text: "Validar JSON", onclick: validateRunnerJson });
  const btnRun = el("button", {
    type: "button",
    text: "Run intent",
    onclick: async () => {
      setRunnerMsg("");
      const id = runnerIntent.value.trim();
      if (!id) return setRunnerMsg("Falta intentId.", true);
      const parsed = safeParseJson(runnerPayload.value);
      if (!parsed.ok) return setRunnerMsg("JSON inválido: " + parsed.error, true);

      // schema light (si el servidor lo publica en describe)
      const meta = intentMeta[id];
      const schema = meta && meta.schema ? meta.schema : null;
      const errors = validateAgainstSchema(schema, parsed.value || {});
      if (errors.length) {
        return setRunnerMsg("Validación: " + errors.join(" · "), true);
      }

      statusBox.textContent = `Ejecutando ${id}...`;
      try {
        const res = await window.IntentFlow.run(id, parsed.value || {});
        statusBox.textContent = pretty(res);
        pushRunnerHistory({ ts: Date.now(), intentId: id, payloadText: runnerPayload.value.trim() || "" });
        renderRunnerHistory();
        if (advanced) await loadAudit();
      } catch (e) {
        statusBox.textContent = "Error runner: " + (e?.message || String(e));
      } finally {
        await refreshLiveStatus();
      }
    },
  });

  runnerIntent.addEventListener("input", () => {
    renderRunnerInfo();
    updateRunnerSchemaUI();
  });

  // Intents list
  const intentSearch = el("input", { type: "text", placeholder: "Buscar intent (macro., server., diagnostics...)", style: "width:100%; max-width:520px;" });
  const intentsListBox = el("div", { style: "margin-top:10px;" }, el("p", { class: "mmi-muted", text: "Cargando..." }));
  let allIntents = [];

  async function loadDescribe() {
    try {
      const res = await window.IntentFlow.run("server.registry.describe", {});
      if (res?.ok && res?.result?.ok && res?.result?.intents) {
        intentMeta = res.result.intents || {};
        allIntents = Object.keys(intentMeta).slice().sort();
        return true;
      }
    } catch {}
    return false;
  }

  async function loadIntents() {
    intentsListBox.innerHTML = "Cargando intents...";
    let ok = await loadDescribe();
    if (!ok) {
      const res = await window.IntentFlow.run("server.registry.list");
      allIntents = (res?.result?.intents || []).slice().sort();
      intentMeta = {};
      allIntents.forEach((id) => (intentMeta[id] = { id }));
    }

    // datalist
    intentsDatalist.innerHTML = "";
    allIntents.forEach((id) => intentsDatalist.appendChild(el("option", { value: id })));
    renderIntents();
    // refresca UI del runner si ya hay intent seleccionado
    renderRunnerInfo();
    updateRunnerSchemaUI();
  }

  function renderIntents() {
    const q = (intentSearch.value || "").trim().toLowerCase();
    const intents = q ? allIntents.filter((id) => String(id).toLowerCase().includes(q)) : allIntents;
    const ul = el("ul", { style: "columns:2; -webkit-columns:2; -moz-columns:2;" });

    intents.forEach((id) => {
      const meta = intentMeta[id] || {};
      const title = meta.title ? ` — ${meta.title}` : "";
      const pre = el("pre", { class: "mmi-pre", style: "display:none; margin-top:8px;" }, "");

      const btnRun = el("button", {
        type: "button",
        text: "Run",
        onclick: async () => {
          pre.style.display = "block";
          pre.textContent = `Running ${id}...`;
          try {
            const r = await window.IntentFlow.run(id);
            pre.textContent = pretty(r);
            if (advanced) await loadAudit();
          } catch (e) {
            pre.textContent = "Error: " + (e?.message || String(e));
          }
        },
      });

      const btnUse = el("button", {
        type: "button",
        text: "Usar",
        onclick: () => {
          runnerIntent.value = id;
          runnerPayload.value = JSON.stringify(getPresetForIntent(id), null, 2);
          renderRunnerInfo();
          setRunnerMsg(`Intent cargado: ${id}`, false);
          runnerIntent.scrollIntoView({ behavior: "smooth", block: "center" });
        },
      });

      ul.appendChild(
        el("li", { style: "break-inside: avoid; margin-bottom:12px;" }, [
          el("div", {}, [
            el("code", { text: id }),
            el("span", { class: "mmi-muted", text: title }),
          ]),
          meta.description ? el("div", { class: "mmi-muted", text: meta.description, style: "margin-top:4px;" }) : null,
          el("div", { class: "mmi-actions", style: "margin-top:6px;" }, [btnRun, btnUse]),
          pre,
        ])
      );
    });

    intentsListBox.innerHTML = "";
    intentsListBox.appendChild(ul);
  }

  intentSearch.addEventListener("input", () => renderIntents());

  // Audit
  const auditLimit = el("input", { type: "number", value: "50", min: "1", max: "200", style: "width:100px;" });
  const auditBox = el("div", { style: "margin-top:10px;" }, el("p", { class: "mmi-muted", text: "Cargando..." }));

  async function loadAudit() {
    auditBox.innerHTML = "Cargando auditoría...";
    try {
      const limit = Math.max(1, Math.min(200, parseInt(auditLimit.value || "50", 10) || 50));
      const res = await window.IntentFlow.run("server.audit.latest", { limit });
      if (!res.ok) {
        auditBox.textContent = "Error auditoría: " + pretty(res);
        return;
      }
      const events = res?.result?.events || [];
      renderAudit(Array.isArray(events) ? events : []);
    } catch (e) {
      auditBox.textContent = "Error: " + (e?.message || String(e));
    }
  }

  function renderAudit(events) {
    auditBox.innerHTML = "";
    if (!events.length) {
      auditBox.appendChild(el("p", { class: "mmi-muted", text: "Sin eventos todavía." }));
      return;
    }
    const table = el("table", { class: "mmi-table" });
    table.appendChild(
      el("thead", {},
        el("tr", {}, [
          el("th", { text: "Fecha" }),
          el("th", { text: "User" }),
          el("th", { text: "Intent" }),
          el("th", { text: "OK" }),
          el("th", { text: "Blocked" }),
          el("th", { text: "Reason" }),
          el("th", { text: "ms" }),
          el("th", { text: "dry" }),
          el("th", { text: "source" }),
        ])
      )
    );
    const tbody = el("tbody");
    events.forEach((ev) => {
      tbody.appendChild(
        el("tr", {}, [
          el("td", { text: String(ev.ts || "") }),
          el("td", { text: String(ev.user_id ?? "") }),
          el("td", { text: String(ev.intentId || "") }),
          el("td", { text: ev.ok ? "✅" : "❌" }),
          el("td", { text: ev.blocked ? "⛔" : "" }),
          el("td", { text: String(ev.reason || "") }),
          el("td", { text: String(ev.duration_ms ?? 0) }),
          el("td", { text: ev.dry_run ? "1" : "0" }),
          el("td", { text: String(ev.source || "") }),
        ])
      );
    });
    table.appendChild(tbody);
    auditBox.appendChild(table);
  }

  const btnAuditRefresh = el("button", { type: "button", text: "Actualizar", onclick: () => loadAudit() });
  const btnAuditExport = el("button", {
    type: "button",
    text: __("export_json","Export JSON"),
    onclick: async () => {
      statusBox.textContent = "Exportando auditoría...";
      try {
        const limit = Math.max(1, Math.min(1000, parseInt(auditLimit.value || "50", 10) || 50));
        const res = await window.IntentFlow.run("server.audit.export", { limit });
        statusBox.textContent = pretty(res);
        if (res.ok && res.result) downloadJson(`mmi-audit-${Date.now()}.json`, res.result);
      } catch (e) {
        statusBox.textContent = "Error export: " + (e?.message || String(e));
      }
    },
  });
  const btnAuditClear = el("button", {
    type: "button",
    text: __("clear_audit","Clear audit"),
    onclick: async () => {
      const ok = window.confirm("¿Borrar auditoría del servidor? Esta acción no se puede deshacer.");
      if (!ok) return;
      statusBox.textContent = "Borrando auditoría...";
      const res = await window.IntentFlow.run("server.audit.clear", {});
      statusBox.textContent = pretty(res);
      await loadAudit();
    },
  });

  // Execution Log (global)
  const execLimit = el("input", { type: "number", value: "50", min: "1", max: "500", style: "width:100px;" });
  const execSource = el(
    "select",
    { style: "min-width:180px;" },
    [
      el("option", { value: "", text: "(cualquier source)" }),
      el("option", { value: "ajax", text: "ajax" }),
      el("option", { value: "scheduler.cron", text: "scheduler.cron" }),
      el("option", { value: "events.wp", text: "events.wp" }),
      el("option", { value: "webhook", text: "webhook" }),
    ]
  );
  const execTargetType = el(
    "select",
    { style: "min-width:140px;" },
    [
      el("option", { value: "", text: "(cualquier tipo)" }),
      el("option", { value: "intent", text: "intent" }),
      el("option", { value: "app", text: "app" }),
    ]
  );
  const execTargetId = el("input", { type: "text", placeholder: "target id (contiene)", style: "min-width:240px;" });
  const execAuto = el("input", { type: "checkbox" });
  const execBox = el("div", { style: "margin-top:10px;" }, el("p", { class: "mmi-muted", text: "Cargando..." }));
  let execTimer = null;

  function buildExecFilters() {
    const limit = Math.max(1, Math.min(500, parseInt(execLimit.value || "50", 10) || 50));
    const source = (execSource.value || "").trim();
    const target_type = (execTargetType.value || "").trim();
    const target_id = (execTargetId.value || "").trim();
    const p = { limit };
    if (source) p.source = source;
    if (target_type) p.target_type = target_type;
    if (target_id) p.target_id = target_id;
    return p;
  }

  function openModal(title, bodyText) {
    const overlay = el("div", {
      style:
        "position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:99999; display:flex; align-items:center; justify-content:center; padding:18px;",
      role: "dialog",
      "aria-modal": "true",
    });
    const card = el("div", { style: "background:#fff; max-width:980px; width:100%; max-height:85vh; overflow:auto; border-radius:10px; padding:14px;" }, []);
    const header = el("div", { style: "display:flex; gap:10px; align-items:center; justify-content:space-between;" }, [
      el("strong", { text: title || "Detalle" }),
      el("button", { type: "button", text: "Cerrar", onclick: () => overlay.remove() }),
    ]);
    const pre = el("pre", { class: "mmi-pre", style: "margin-top:10px;" }, bodyText || "");
    card.appendChild(header);
    card.appendChild(pre);
    overlay.appendChild(card);
    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) overlay.remove();
    });
    document.body.appendChild(overlay);
  }

  async function loadExecution() {
    execBox.innerHTML = "Cargando ejecución...";
    try {
      const res = await window.IntentFlow.run("server.execution.list", buildExecFilters());
      if (!res?.ok) {
        execBox.textContent = "Error execution.list: " + pretty(res);
        return;
      }
      const items = res?.result?.items || res?.result?.executions || [];
      renderExecution(Array.isArray(items) ? items : []);
    } catch (e) {
      execBox.textContent = "Error: " + (e?.message || String(e));
    }
  }

  function renderExecution(items) {
    execBox.innerHTML = "";
    if (!items.length) {
      execBox.appendChild(el("p", { class: "mmi-muted", text: __("no_executions","No executions yet.") }));
      return;
    }
    const table = el("table", { class: "mmi-table" });
    table.appendChild(
      el("thead", {},
        el("tr", {}, [
          el("th", { text: "Fecha" }),
          el("th", { text: "source" }),
          el("th", { text: "target" }),
          el("th", { text: "OK" }),
          el("th", { text: "ms" }),
          el("th", { text: "msg" }),
          el("th", { text: "ver" }),
        ])
      )
    );
    const tbody = el("tbody");
    items.forEach((it) => {
      const ts = it.ts || it.started_at || "";
      const source = it.source || "";
      const t = it.target || {};
      const targetText = t && typeof t === "object" ? `${t.type || ""}:${t.id || ""}` : String(t || "");
      const ok = !!it.ok;
      const msg = String(it.message || it.error || "").slice(0, 120);

      const btn = el("button", {
        type: "button",
        text: "Detalle",
        onclick: async () => {
          const id = it.exec_id || it.id;
          if (!id) return openModal("Detalle", pretty(it));
          try {
            const r = await window.IntentFlow.run("server.execution.get", { exec_id: id });
            openModal(`Execution ${id}`, pretty(r));
          } catch (e) {
            openModal("Error", "No se pudo cargar detalle: " + (e?.message || String(e)));
          }
        },
      });

      tbody.appendChild(
        el("tr", {}, [
          el("td", { text: String(ts) }),
          el("td", { text: String(source) }),
          el("td", {}, el("code", { text: targetText })),
          el("td", { text: ok ? "✅" : "❌" }),
          el("td", { text: String(it.duration_ms ?? "") }),
          el("td", { text: msg }),
          el("td", {}, btn),
        ])
      );
    });
    table.appendChild(tbody);
    execBox.appendChild(table);
  }

  const btnExecRefresh = el("button", { type: "button", text: "Actualizar", onclick: () => loadExecution() });
  const btnExecClear = el("button", {
    type: "button",
    text: __("clear_executions","Clear executions"),
    onclick: async () => {
      const ok = window.confirm("¿Borrar el log de ejecuciones?\nSugerencia: úsalo solo para depuración.");
      if (!ok) return;
      statusBox.textContent = "Borrando execution log...";
      const res = await window.IntentFlow.run("server.execution.clear", {});
      statusBox.textContent = pretty(res);
      await loadExecution();
    },
  });

  function setupExecAutoRefresh() {
    if (execTimer) {
      clearInterval(execTimer);
      execTimer = null;
    }
    if (execAuto.checked) {
      execTimer = setInterval(() => loadExecution().catch(() => {}), 5000);
    }
  }
  execAuto.addEventListener("change", setupExecAutoRefresh);
  execSource.addEventListener("change", () => loadExecution());
  execTargetType.addEventListener("change", () => loadExecution());
  execTargetId.addEventListener("keydown", (e) => { if (e.key === "Enter") loadExecution(); });

  
  // ---------------- Presets (server) ----------------
  const presetsState = { items: [] };

  const presetsSelect = el("select", { style: "min-width:280px;" }, [el("option", { value: "", text: "— Selecciona un preset —" })]);
  const presetNameInput = el("input", { type: "text", placeholder: "Nombre del preset (ej: Borrador rápido)", style: "min-width:320px;" });

  async function loadPresets() {
    try {
      const res = await window.IntentFlow.run("server.presets.list", {});
      if (res?.ok && res?.result?.ok) {
        presetsState.items = Array.isArray(res.result.presets) ? res.result.presets : [];
      } else {
        presetsState.items = [];
      }
    } catch {
      presetsState.items = [];
    }

    // render select
    presetsSelect.innerHTML = "";
    presetsSelect.appendChild(el("option", { value: "", text: "— Selecciona un preset —" }));
    presetsState.items.forEach((p) => {
      const label = `${p.name || p.id}  ·  ${p.intentId || ""}`;
      presetsSelect.appendChild(el("option", { value: p.id, text: label }));
    });
  }

  async function savePresetFromRunner() {
    const name = presetNameInput.value.trim();
    const intentId = runnerIntent.value.trim();
    if (!name) return setRunnerMsg("Falta el nombre del preset.", true);
    if (!intentId) return setRunnerMsg("Falta intentId en el runner.", true);

    const parsed = safeParseJson(runnerPayload.value);
    if (!parsed.ok) return setRunnerMsg("JSON inválido: " + parsed.error, true);

    statusBox.textContent = "Guardando preset...";
    const res = await window.IntentFlow.run("server.presets.save", {
      name,
      intentId,
      payload: parsed.value || {},
    });
    statusBox.textContent = pretty(res);
    if (res?.ok && res?.result?.ok) {
      setRunnerMsg("Preset guardado en el servidor.", false);
      await loadPresets();
    } else {
      setRunnerMsg("No se pudo guardar el preset.", true);
    }
  }

  async function loadSelectedPresetIntoRunner() {
    const id = presetsSelect.value;
    if (!id) return;
    statusBox.textContent = "Cargando preset...";
    const res = await window.IntentFlow.run("server.presets.get", { id });
    statusBox.textContent = pretty(res);
    if (res?.ok && res?.result?.ok && res.result.preset) {
      const p = res.result.preset;
      runnerIntent.value = p.intentId || "";
      runnerPayload.value = JSON.stringify(p.payload || {}, null, 2);
      renderRunnerInfo();
      setRunnerMsg("Preset cargado en el runner.", false);
    }
  }

  async function deleteSelectedPreset() {
    const id = presetsSelect.value;
    if (!id) return;
    const ok = window.confirm("¿Borrar este preset del servidor? No se puede deshacer.");
    if (!ok) return;
    statusBox.textContent = "Borrando preset...";
    const res = await window.IntentFlow.run("server.presets.delete", { id });
    statusBox.textContent = pretty(res);
    await loadPresets();
  }

  async function exportPresets() {
    statusBox.textContent = "Exportando presets...";
    const res = await window.IntentFlow.run("server.presets.export", {});
    statusBox.textContent = pretty(res);
    if (res?.ok && res?.result?.ok) {
      downloadJson(`mmi-presets-${Date.now()}.json`, res.result);
    }
  }

  async function importPresetsFromFile(file) {
    if (!file) return;
    statusBox.textContent = "Importando presets...";
    try {
      const txt = await file.text();
      const data = JSON.parse(txt);
      const presets = Array.isArray(data.presets) ? data.presets : (Array.isArray(data) ? data : []);
      const res = await window.IntentFlow.run("server.presets.import", { overwrite: true, presets });
      statusBox.textContent = pretty(res);
      await loadPresets();
    } catch (e) {
      statusBox.textContent = "Error import presets: " + (e?.message || String(e));
    }
  }

  const presetsFile = el("input", { type: "file", accept: "application/json", style: "display:none;" });
  presetsFile.addEventListener("change", () => importPresetsFromFile(presetsFile.files && presetsFile.files[0]));

  const btnPresetSaveServer = el("button", { type: "button", text: "Guardar preset", onclick: savePresetFromRunner });
  const btnPresetLoadServer = el("button", { type: "button", text: "Cargar", onclick: loadSelectedPresetIntoRunner });
  const btnPresetDeleteServer = el("button", { type: "button", text: "Borrar", onclick: deleteSelectedPreset });
  const btnPresetExport = el("button", { type: "button", text: __("export","Export"), onclick: exportPresets });
  const btnPresetImport = el("button", { type: "button", text: __("import","Import"), onclick: () => presetsFile.click() });

  // ---------------- Flows (server) ----------------
  const flowsState = { items: [] };

  const flowsSelect = el("select", { style: "min-width:280px;" }, [el("option", { value: "", text: "— Selecciona un flow —" })]);
  const flowEditor = el("textarea", { style: "width:100%; max-width:920px; height:180px;", placeholder: "Flow JSON (name + steps...)" });


// ---------- FLOW BUILDER VISUAL v1 ----------
const flowModeToggle = el("label", { class:"mmi-pill" }, [
  el("input", { type:"checkbox", id:"mmi-flow-visual-toggle" }),
  el("span", { text:"Modo visual" }),
]);

const flowVisualWrap = el("div", { class:"mmi-flow-builder", style:"display:none;" }, []);
const flowMetaName = el("input", { type:"text", placeholder:"Nombre del flow (requerido)" });
const flowMetaId = el("input", { type:"text", placeholder:"ID (opcional)" });
const flowMetaEnabled = el("input", { type:"checkbox" });
const flowMetaEnabledWrap = el("label", { class:"mmi-pill" }, [flowMetaEnabled, el("span", { text:"Enabled" })]);


const flowTrigger = el("select", {});
flowTrigger.appendChild(el("option", { value:"", text:"(sin trigger / solo manual)" }));
const flowTriggerHelp = el("div", { class:"mmi-muted", style:"margin-top:6px;" }, []);
const flowTriggerTest = el("button", { type:"button", text:"Test trigger", style:"margin-left:6px;" });

async function loadTriggerCatalog(){
  try{
    const r = await api('server.events.catalog', {});
    const items = (r && r.items) ? r.items : [];
    const groups = {};
    items.forEach(it=>{
      const g = it.group || 'other';
      if(!groups[g]) groups[g] = [];
      groups[g].push(it);
    });
    Object.keys(groups).forEach(g=>{
      const og = el('optgroup', { label: g });
      groups[g].forEach(it=>{
        og.appendChild(el('option', { value: it.id, text: it.label || it.id }));
      });
      flowTrigger.appendChild(og);
    });
    flowTrigger.addEventListener('change', ()=>{
      const id = flowTrigger.value;
      const it = items.find(x=>x.id===id);
      if(!id){
        flowTriggerHelp.textContent = 'Este flow solo se ejecutará manualmente.';
        return;
      }
      flowTriggerHelp.textContent = it?.description || '';
    });
    flowTriggerHelp.textContent = 'Selecciona un trigger para auto-ejecución por eventos.';
  }catch(e){
    flowTriggerHelp.textContent = 'No se pudo cargar el catálogo de triggers.';
  }
}

flowTriggerTest.addEventListener('click', async ()=>{
  const id = flowTrigger.value;
  if(!id){
    statusBox.textContent = 'Selecciona un trigger para testear.';
    return;
  }
  try{
    const cat = await api('server.events.catalog', {});
    const items = (cat && cat.items) ? cat.items : [];
    const it = items.find(x=>x.id===id);
    const payload = it?.example_payload || {};
    await api('server.events.emit', { event: id, payload });
    statusBox.textContent = 'Trigger emitido. Mira Execution Log para ver ejecuciones.';
  }catch(err){
    statusBox.textContent = 'Error al emitir trigger: ' + (err?.message || err);
  }
});
const flowStepsList = el("div", { class:"mmi-step-list" }, []);
const flowBtnAddStep = el("button", { type:"button", text:__("add_action","Add action") });
const flowBtnSyncJson = el("button", { type:"button", text:"Sincronizar JSON" });

let flowActionsCache = null; // action_id -> meta

async function ensureActionsCache(){
  if(flowActionsCache) return flowActionsCache;
  try{
    const r = await api('server.actions.list', {});
    flowActionsCache = (r && r.items) ? r.items : {};
  }catch(e){
    flowActionsCache = {};
  }
  return flowActionsCache;
}

function findActionByIntent(intentId){
  if(!flowActionsCache) return null;
  const keys = Object.keys(flowActionsCache);
  for(const k of keys){
    const a = flowActionsCache[k];
    if(a && a.intent === intentId) return a;
  }
  return null;
}

function getActionOptions(){
  const items = flowActionsCache || {};
  const keys = Object.keys(items).sort((a,b)=>{
    const la = (items[a]?.label||a).toLowerCase();
    const lb = (items[b]?.label||b).toLowerCase();
    return la.localeCompare(lb);
  });
  return keys.map(k=>({ id:k, label: (items[k]?.integration ? (items[k].integration + " · ") : "") + (items[k]?.label||k), intent: items[k]?.intent }));
}

function parseFlowFromEditor(){
  const parsed = safeParseJson(flowEditor.value);
  if(!parsed.ok) return null;
  return parsed.value || null;
}

function setEditorFromFlow(flowObj){
  flowEditor.value = JSON.stringify(flowObj, null, 2);
}

function buildFlowObjectFromVisual(){
  const name = (flowMetaName.value || '').trim();
  const id = (flowMetaId.value || '').trim();
  const enabled = !!flowMetaEnabled.checked;

  // steps from cards
  const steps = [];
  const cards = Array.from(flowStepsList.querySelectorAll('[data-step-idx]'));
  cards.forEach(card=>{
    const stepIntent = card.querySelector('[data-step-intent]')?.value || '';
    if(!stepIntent) return;
    let payload = {};
    const payloadRaw = card.querySelector('[data-step-payload-raw]');
    if(payloadRaw){
      const p = safeParseJson(payloadRaw.value || '{}');
      payload = p.ok ? (p.value || {}) : {};
    } else {
      // build from fields
      payload = {};
      card.querySelectorAll('[data-field-key]').forEach(inp=>{
        const k = inp.getAttribute('data-field-key');
        const t = inp.getAttribute('data-field-type') || 'string';
        if(!k) return;
        if(t === 'object'){
          const p = safeParseJson(inp.value || '{}');
          payload[k] = p.ok ? (p.value || {}) : {};
        } else {
          payload[k] = inp.value;
        }
      });
    }
    steps.push({ id: stepIntent, payload, meta: {} });
  });

  const flow = { name, steps };
  if(id) flow.id = id;
  // keep trigger if you want to extend later
  if((flowTrigger.value||'').trim() !== '') flow.trigger = (flowTrigger.value||'').trim();
  flow.enabled = enabled;
  return flow;
}

function renderStepCard(idx, step){
  const action = findActionByIntent(step.id);
  const title = action ? `${action.integration || 'action'} · ${action.label || action.id}` : step.id;

  const intentInput = el('input', { type:'text', value: step.id || '', 'data-step-intent':'1', placeholder:'Intent ID (ej: slack.send)' });

  // If we can map to an action, render fields; else raw JSON
  const body = el('div');
  if(action && action.fields){
    const grid = el('div', { class:'mmi-field-grid' }, []);
    Object.entries(action.fields).forEach(([key, meta])=>{
      const fType = meta?.type || 'string';
      const label = meta?.label || key;
      const val = (step.payload && step.payload[key] !== undefined) ? step.payload[key] : (fType==='object' ? {} : '');
      const input = (fType === 'object')
        ? el('textarea', { 'data-field-key': key, 'data-field-type':'object' }, [])
        : el('input', { type:'text', 'data-field-key': key, 'data-field-type':'string' });
      if(fType === 'object') input.value = JSON.stringify(val || {}, null, 2);
      else input.value = (val ?? '');

      input.addEventListener('input', ()=>{
        // live sync optional
      });

      grid.appendChild(el('div', { class:'mmi-label', text: label }));
      grid.appendChild(input);
    });
    body.appendChild(el('div', { class:'mmi-field-grid' }, [
      el('div', { class:'mmi-label', text:'Intent' }),
      intentInput
    ]));
    body.appendChild(el('div', { style:'height:8px;' }));
    body.appendChild(grid);
  } else {
    const raw = el('textarea', { 'data-step-payload-raw':'1' }, []);
    raw.value = JSON.stringify(step.payload || {}, null, 2);
    body.appendChild(el('div', { class:'mmi-field-grid' }, [
      el('div', { class:'mmi-label', text:'Intent' }),
      intentInput,
      el('div', { class:'mmi-label', text:'Payload (JSON)' }),
      raw
    ]));
  }

  const btnUp = el('button', { type:'button', text:'↑' });
  const btnDown = el('button', { type:'button', text:'↓' });
  const btnDel = el('button', { type:'button', text:'Borrar' });

  btnUp.addEventListener('click', ()=> moveStep(idx, -1));
  btnDown.addEventListener('click', ()=> moveStep(idx, +1));
  btnDel.addEventListener('click', ()=> removeStep(idx));

  const head = el('div', { class:'mmi-step-head' }, [
    el('div', {}, [ el('div', { class:'mmi-step-title', text: `Paso ${idx+1}` }), el('div', { class:'mmi-muted', text: title }) ]),
    el('div', { class:'mmi-step-actions' }, [btnUp, btnDown, btnDel]),
  ]);

  const card = el('div', { class:'mmi-step-card', 'data-step-idx': String(idx) }, [head, body]);
  return card;
}

function renderVisualFromFlow(flow){
  // meta
  flowMetaName.value = flow?.name || '';
  flowMetaId.value = flow?.id || '';
  flowMetaEnabled.checked = flow?.enabled !== false;
  flowTrigger.value = (flow?.trigger || ''); flowTrigger.dispatchEvent(new Event('change'));

  // steps
  flowStepsList.innerHTML = '';
  const steps = Array.isArray(flow?.steps) ? flow.steps : [];
  steps.forEach((s, idx)=>{
    flowStepsList.appendChild(renderStepCard(idx, { id: s.id, payload: s.payload || {} }));
  });
}

function syncVisualToJson(){
  const flow = buildFlowObjectFromVisual();
  setEditorFromFlow(flow);
}

function removeStep(idx){
  const flow = parseFlowFromEditor();
  if(!flow) return;
  const steps = Array.isArray(flow.steps) ? flow.steps : [];
  steps.splice(idx,1);
  flow.steps = steps;
  setEditorFromFlow(flow);
  renderVisualFromFlow(flow);
}

function moveStep(idx, delta){
  const flow = parseFlowFromEditor();
  if(!flow) return;
  const steps = Array.isArray(flow.steps) ? flow.steps : [];
  const j = idx + delta;
  if(j < 0 || j >= steps.length) return;
  const tmp = steps[idx];
  steps[idx] = steps[j];
  steps[j] = tmp;
  flow.steps = steps;
  setEditorFromFlow(flow);
  renderVisualFromFlow(flow);
}

async function addStep(){
  await ensureActionsCache();
  const flow = parseFlowFromEditor() || { name:'', steps:[] };
  const options = getActionOptions();

  const sel = el('select', {});
  sel.appendChild(el('option', { value:'', text:'— seleccionar acción —' }));
  options.forEach(o=> sel.appendChild(el('option', { value:o.id, text:o.label })));
  const custom = el('input', { type:'text', placeholder:'O intent manual (ej: server.audit.latest)' });

  const ok = window.confirm('Se abrirá un selector básico en la parte superior del Flow Builder. Pulsa OK para continuar.');
  if(!ok) return;

  // simple inline picker: add a temporary card at top
  const picker = el('div', { class:'mmi-step-card' }, [
    el('div', { class:'mmi-step-head' }, [
      el('div', {}, [ el('div', { class:'mmi-step-title', text:'Nueva acción' }), el('div', { class:'mmi-muted', text:'Selecciona una acción o escribe un intent' }) ]),
      el('div', { class:'mmi-step-actions' }, [])
    ]),
    el('div', { class:'mmi-field-grid' }, [
      el('div', { class:'mmi-label', text:'Acción' }),
      sel,
      el('div', { class:'mmi-label', text:'Intent manual' }),
      custom,
    ]),
    el('div', { class:'mmi-flow-footer' }, [
      el('button', { type:'button', text:'Añadir', onclick: ()=>{
        const actionId = sel.value;
        let intentId = '';
        let payload = {};
        if(actionId){
          const a = flowActionsCache[actionId];
          intentId = a && a.intent ? a.intent : '';
          // init payload with empty fields
          if(a && a.fields){
            Object.entries(a.fields).forEach(([k, m])=>{
              payload[k] = (m && m.type==='object') ? {} : '';
            });
          }
        } else {
          intentId = (custom.value || '').trim();
        }
        if(!intentId){
          statusBox.textContent = 'Selecciona una acción o escribe un intent.';
          return;
        }
        const steps = Array.isArray(flow.steps) ? flow.steps : [];
        steps.push({ id:intentId, payload, meta:{} });
        flow.steps = steps;
        setEditorFromFlow(flow);
        renderVisualFromFlow(flow);
        picker.remove();
      }}),
      el('button', { type:'button', text:__("cancel","Cancel"), onclick: ()=> picker.remove() }),
    ])
  ]);

  flowStepsList.prepend(picker);
  sel.focus();
}

// build visual wrap
flowVisualWrap.appendChild(el('div', { class:'mmi-flow-top' }, [
  el('div', {}, [ el('label', { text:'Nombre' }), flowMetaName ]),
  el('div', {}, [ el('label', { text:'ID' }), flowMetaId ]),
  el('div', {}, [ el('label', { text:'Trigger' }), el('div', {}, [flowTrigger, flowTriggerTest]), flowTriggerHelp ]),
  el('div', {}, [ el('label', { text:'Estado' }), flowMetaEnabledWrap ]),
]));
flowVisualWrap.appendChild(flowStepsList);
flowVisualWrap.appendChild(el('div', { class:'mmi-flow-footer' }, [flowBtnAddStep, flowBtnSyncJson]));

flowBtnAddStep.addEventListener('click', addStep);
flowBtnSyncJson.addEventListener('click', syncVisualToJson);

document.addEventListener('change', async (e)=>{
  if(e.target && e.target.id === 'mmi-flow-visual-toggle'){
    const on = !!e.target.checked;
    flowVisualWrap.style.display = on ? '' : 'none';
    flowEditor.style.display = on ? 'none' : '';
    if(on){
      await ensureActionsCache();
      const flow = parseFlowFromEditor() || { name:'', steps:[] };
      renderVisualFromFlow(flow);
    }
  }
});

// keep JSON updated when editing meta in visual mode
[flowMetaName, flowMetaId, flowTrigger].forEach(inp=>{
  inp.addEventListener('input', ()=>{
    if(document.getElementById('mmi-flow-visual-toggle')?.checked){
      syncVisualToJson();
    }
  });
});
flowMetaEnabled.addEventListener('change', ()=>{
  if(document.getElementById('mmi-flow-visual-toggle')?.checked){
    syncVisualToJson();
  }
});

// ---------- END FLOW BUILDER VISUAL v1 ----------


  function defaultFlowFromRunner() {
    const intentId = runnerIntent.value.trim();
    const parsed = safeParseJson(runnerPayload.value);
    const step = intentId ? [{ id: intentId, payload: parsed.ok ? (parsed.value || {}) : {} }] : [];
    return { name: "Nuevo flow", steps: step };
  }

  async function loadFlows() {
    try {
      const res = await window.IntentFlow.run("flow.list", {});
      if (res?.ok && res?.result?.ok) {
        flowsState.items = Array.isArray(res.result.flows) ? res.result.flows : [];
      } else {
        flowsState.items = [];
      }
    } catch {
      flowsState.items = [];
    }

    flowsSelect.innerHTML = "";
    flowsSelect.appendChild(el("option", { value: "", text: "— Selecciona un flow —" }));
    flowsState.items.forEach((f) => {
      const label = `${f.name || f.id}  ·  pasos: ${f.steps ?? 0}`;
      flowsSelect.appendChild(el("option", { value: f.id, text: label }));
    });
  }

  async function loadSelectedFlow() {
    const id = flowsSelect.value;
    if (!id) return;
    statusBox.textContent = "Cargando flow...";
    const res = await window.IntentFlow.run("flow.get", { id });
    statusBox.textContent = pretty(res);
    if (res?.ok && res?.result?.ok && res.result.flow) {
      flowEditor.value = JSON.stringify(res.result.flow, null, 2);
      if (document.getElementById('mmi-flow-visual-toggle')?.checked) {
        await ensureActionsCache();
        renderVisualFromFlow(res.result.flow);
      }
    }
  }

  async function saveFlowFromEditor() {
    const parsed = safeParseJson(flowEditor.value);
    if (!parsed.ok) {
      statusBox.textContent = "JSON inválido: " + parsed.error;
      return;
    }
    statusBox.textContent = "Guardando flow...";
    const res = await window.IntentFlow.run("flow.save", parsed.value || {});
    statusBox.textContent = pretty(res);
    if (res?.ok && res?.result?.ok) {
      await loadFlows();
    }
  }

  async function deleteSelectedFlow() {
    const id = flowsSelect.value;
    if (!id) return;
    const ok = window.confirm("¿Borrar este flow? No se puede deshacer.");
    if (!ok) return;
    statusBox.textContent = "Borrando flow...";
    const res = await window.IntentFlow.run("flow.delete", { id });
    statusBox.textContent = pretty(res);
    await loadFlows();
    flowEditor.value = "";
  }

  async function runSelectedFlow() {
    const id = flowsSelect.value;
    const parsed = safeParseJson(flowEditor.value);
    const hasInline = parsed.ok && parsed.value && parsed.value.steps;
    statusBox.textContent = "Ejecutando flow (auto-live)...";

    const payload = id ? { id } : (hasInline ? { flow: parsed.value } : null);
    if (!payload) {
      statusBox.textContent = "Selecciona un flow o pega un flow JSON válido.";
      return;
    }

    const res = await window.IntentFlow.live(600, async () => {
      return await window.IntentFlow.run("flow.run", payload);
    });

    statusBox.textContent = pretty(res);
    if (advanced) await loadAudit();
  }

  async function exportFlows() {
    statusBox.textContent = "Exportando flows...";
    const res = await window.IntentFlow.run("flow.export", {});
    statusBox.textContent = pretty(res);
    if (res?.ok && res?.result?.ok) {
      downloadJson(`mmi-flows-${Date.now()}.json`, res.result);
    }
  }

  async function importFlowsFromFile(file) {
    if (!file) return;
    statusBox.textContent = "Importando flows...";
    try {
      const txt = await file.text();
      const data = JSON.parse(txt);
      const flows = Array.isArray(data.flows) ? data.flows : (Array.isArray(data) ? data : []);
      const res = await window.IntentFlow.run("flow.import", { overwrite: true, flows });
      statusBox.textContent = pretty(res);
      await loadFlows();
    } catch (e) {
      statusBox.textContent = "Error import flows: " + (e?.message || String(e));
    }
  }

  const flowsFile = el("input", { type: "file", accept: "application/json", style: "display:none;" });
  flowsFile.addEventListener("change", () => importFlowsFromFile(flowsFile.files && flowsFile.files[0]));

  const btnFlowNew = el("button", { type: "button", text: "Nuevo (desde runner)", onclick: () => { flowEditor.value = JSON.stringify(defaultFlowFromRunner(), null, 2); } });
  const btnFlowLoad = el("button", { type: "button", text: "Cargar", onclick: loadSelectedFlow });
  const btnFlowSave = el("button", { type: "button", text: __("save","Save"), onclick: saveFlowFromEditor });
  const btnFlowRun  = el("button", { type: "button", text: "Run flow", onclick: runSelectedFlow });
  const btnFlowDelete = el("button", { type: "button", text: "Borrar", onclick: deleteSelectedFlow });
  const btnFlowExport = el("button", { type: "button", text: __("export","Export"), onclick: exportFlows });
  const btnFlowImport = el("button", { type: "button", text: __("import","Import"), onclick: () => flowsFile.click() });

// Build advanced card (sections for profiles)
  const secTools  = el("div", { "data-mmi-sec": "tools" }, []);
  const secRunner = el("div", { "data-mmi-sec": "runner" }, []);
  const secIntents= el("div", { "data-mmi-sec": "intents" }, []);
  const secPresets= el("div", { "data-mmi-sec": "presets" }, []);
  const secFlows  = el("div", { "data-mmi-sec": "flows" }, []);

  // Modules / Integrations catalog (toggleable)
  const secModules = el("div", { class: "mmi-card", id: "mmi-modules" }, [
    el("h2", { text: "Integrations (Modules)" }),
    el("div", { class: "mmi-muted", text: "Activa/desactiva integraciones. Están activadas por defecto, pero el loader es seguro: si una falla, no rompe WordPress." }),
  ]);




// ---------- MMI UX PAGES v1 ----------
// Connections (reusable credentials)

// ---- Connections (UX v2) ----
const secConnections = el("div", { class: "mmi-card", id: "mmi-connections" }, [
  el("h2", { text: "Connections" }),
  el("div", { class: "mmi-muted", text: "Credenciales y endpoints reutilizables (Telegram, Slack, HTTP, etc.)." }),
]);

const CONNECTION_TYPES = {
  telegram: {
    label: "Telegram (Bot)",
    help: "Usa un bot_token de @BotFather. El chat_id por defecto es opcional.",
    fields: [
      { key: "bot_token", label: "Bot token", type: "password", required: true, placeholder: "123456:ABC-DEF..." },
      { key: "default_chat_id", label: "Chat ID por defecto", type: "text", required: false, placeholder: "-100123..." },
    ],
  },
  slack_webhook: {
    label: "Slack (Incoming Webhook)",
    fields: [
      { key: "webhook_url", label: "Webhook URL", type: "url", required: true, placeholder: "https://hooks.slack.com/..." },
      { key: "default_channel", label: "Canal (opcional)", type: "text", required: false, placeholder: "#alerts" },
    ],
  },
  http: {
    label: "HTTP (Endpoint + Headers)",
    fields: [
      { key: "base_url", label: "Base URL", type: "url", required: true, placeholder: "https://api.ejemplo.com" },
      { key: "auth_header", label: "Auth header (opcional)", type: "text", required: false, placeholder: "Authorization: Bearer XXX" },
    ],
  },
  openai: {
    label: "OpenAI (API Key)",
    help: "Introduce tu API key de OpenAI. La encontrarás en platform.openai.com/api-keys.",
    fields: [
      { key: "api_key", label: "API Key", type: "password", required: true, placeholder: "sk-..." },
    ],
  },
  stripe: {
    label: "Stripe (Secret Key)",
    help: "Usa tu Secret Key de Stripe. La encontrarás en dashboard.stripe.com/apikeys.",
    fields: [
      { key: "secret_key", label: "Secret Key", type: "password", required: true, placeholder: "sk_live_..." },
      { key: "webhook_secret", label: "Webhook Secret (opcional)", type: "password", required: false, placeholder: "whsec_..." },
    ],
  },
  whatsapp: {
    label: "WhatsApp Business (Meta API)",
    help: "Credentials from Meta for Developers. You need a Phone Number ID and a permanent Access Token.",
    fields: [
      { key: "phone_number_id", label: "Phone Number ID", type: "text", required: true, placeholder: "123456789012345" },
      { key: "access_token", label: "Access Token", type: "password", required: true, placeholder: "EAABs..." },
    ],
  },
};

// ---- Modules UI (Integrations catalog) ----
async function loadModulesUI(){
  // Build UI shell once
  if(secModules.__mmiReady) return;
  secModules.__mmiReady = true;

  const top = el("div", { style: "display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin:10px 0;" }, []);
  const btnRefresh = el("button", { type:"button", class:"button", text:"Actualizar" });
  const safeToggle = el("button", { type:"button", class:"button", text:"Modo seguro: OFF" });
  const status = el("div", { class:"mmi-muted", text:"" });

  top.appendChild(btnRefresh);
  top.appendChild(safeToggle);
  top.appendChild(status);

  const table = el("table", { class:"widefat striped", style:"margin-top:10px;" });
  secModules.appendChild(top);
  secModules.appendChild(table);

  async function refresh(){
    status.textContent = "Cargando módulos…";
    table.innerHTML = "";
    const r = await api("server.modules.list", {});
    if(!r?.ok){
      status.textContent = "Error: " + pretty(r);
      return;
    }
    safeToggle.textContent = "Modo seguro: " + (r.safe_mode ? "ON" : "OFF");

    const items = Array.isArray(r.items) ? r.items : [];
    const thead = el("thead", {}, [el("tr", {}, [
      el("th", { text:"Módulo" }),
      el("th", { text:"Descripción" }),
      el("th", { text:"Versión" }),
      el("th", { text:"Estado" }),
      el("th", { text:"Acciones" }),
    ])]);
    const tbody = el("tbody");

    items.forEach(it=>{
      const enabled = !!it.enabled;
      const depOk = !!it.dependency_ok;
      const err = (it.error || "").trim();

      const pill = err ? '<span class="mmi-pill mmi-pill-bad">ERROR</span>'
        : (!depOk ? '<span class="mmi-pill mmi-pill-warn">DEPENDENCIA</span>'
        : (enabled ? '<span class="mmi-pill mmi-pill-ok">ON</span>' : '<span class="mmi-pill">OFF</span>'));

      const btn = el("button", {
        type:"button",
        class:"button" + (enabled ? "" : " button-primary"),
        text: enabled ? "Desactivar" : "Activar",
        onclick: async ()=>{
          status.textContent = "Guardando…";
          const rr = await api("server.modules.toggle", { slug: it.slug, enabled: !enabled });
          if(!rr?.ok){
            status.textContent = "Error: " + pretty(rr);
            return;
          }
          await refresh();
        }
      });

      const btnDetails = el("button", {
        type:"button",
        class:"button",
        text:"Detalle",
        onclick: ()=>{
          const detail = {
            slug: it.slug,
            name: it.name,
            version: it.version,
            enabled: enabled,
            dependency_ok: depOk,
            missing: it.missing,
            error: it.error,
          };
          openModal("Módulo: " + (it.name || it.slug), JSON.stringify(detail, null, 2));
        }
      });

      const tr = el("tr", {}, [
        el("td", {}, [ el("strong", { text: it.name || it.slug }), el("div",{class:"mmi-muted", text: it.slug}) ]),
        el("td", { text: it.description || "" }),
        el("td", {}, [ el("code", { text: it.version || "" }) ]),
        el("td", { html: pill }),
        el("td", {}, [ btn, el("span",{text:" "}), btnDetails ]),
      ]);
      tbody.appendChild(tr);
    });

    table.appendChild(thead);
    table.appendChild(tbody);
    status.textContent = items.length ? "" : "No hay módulos.";
  }

  btnRefresh.addEventListener("click", refresh);
  safeToggle.addEventListener("click", async ()=>{
    const cur = safeToggle.textContent.includes("ON");
    status.textContent = "Guardando modo seguro…";
    const rr = await api("server.modules.safe_mode", { on: !cur });
    if(!rr?.ok){
      status.textContent = "Error: " + pretty(rr);
      return;
    }
    await refresh();
  });

  await refresh();
}



function slugify(s){
  return String(s||"").toLowerCase().trim()
    .replace(/[\s\_]+/g,'-')
    .replace(/[^\w\-]+/g,'')
    .replace(/\-\-+/g,'-')
    .replace(/^-+|-+$/g,'');
}

function getTypeDef(type){
  return CONNECTION_TYPES[type] || { label: type || "custom", fields: [] };
}

function buildModal(){
  const overlay = el("div",{class:"mmi-modal-overlay",style:"display:none;"});
  const box = el("div",{class:"mmi-modal"});
  overlay.appendChild(box);
  document.body.appendChild(overlay);
  return { overlay, box };
}

const connModal = buildModal();

function openModal(title, bodyNode, actionsNode){
  connModal.box.innerHTML = "";
  connModal.box.appendChild(el("div",{class:"mmi-modal-header"},[
    el("h3",{text:title, style:"margin:0;"}),
    el("button",{type:"button",class:"mmi-icon-btn",text:"×",onclick:closeModal,"aria-label":"Cerrar"})
  ]));
  connModal.box.appendChild(el("div",{class:"mmi-modal-body"},[bodyNode]));
  connModal.box.appendChild(el("div",{class:"mmi-modal-actions"},[actionsNode]));
  connModal.overlay.style.display = "";
  connModal.overlay.setAttribute("aria-hidden","false");
}

function closeModal(){
  connModal.overlay.style.display = "none";
  connModal.overlay.setAttribute("aria-hidden","true");
}

connModal.overlay.addEventListener("click",(ev)=>{ if(ev.target===connModal.overlay) closeModal(); });

const connToolbar = el("div", { class: "mmi-actions" }, []);
const btnConnNew = el("button", { type:"button", text:__("new_connection","New connection") });
const btnConnReload = el("button", { type:"button", text:__("reload","Reload") });
const connMsg = el("div",{class:"mmi-muted",style:"margin-left:auto;"});
connToolbar.appendChild(btnConnNew);
connToolbar.appendChild(btnConnReload);
connToolbar.appendChild(connMsg);
secConnections.appendChild(connToolbar);

const connList = el("div", {});
secConnections.appendChild(connList);

function renderConnRow(id, c){
  const typeDef = getTypeDef(c.type);
  const title = (c.name || id) + (c.type ? ` — ${typeDef.label || c.type}` : "");
  const meta = [];
  if (c.updated_at) meta.push("Actualizado: " + new Date(c.updated_at*1000).toLocaleString());
  const btnEdit = el("button",{type:"button",text:__("edit","Edit"),onclick:()=>openConnEditor(id,c)});
  const btnDel = el("button",{type:"button",text:"Borrar",class:"mmi-danger",onclick:()=>deleteConn(id)});
  const btnTest = el("button",{type:"button",text:"Test",onclick:()=>testConn(id)});
  return el("div",{class:"mmi-card",style:"margin-top:10px;"},[
    el("div",{style:"display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;"},[
      el("div",{},[
        el("div",{style:"font-weight:600;" ,text:title}),
        el("div",{class:"mmi-muted",text: meta.join(" · ")}),
      ]),
      el("div",{class:"mmi-actions"},[btnTest, btnEdit, btnDel]),
    ]),
  ]);
}

function buildConnForm(initial){
  const state = Object.assign({ id:"", name:"", type:"telegram", config:{} }, initial||{});
  // Backward-compat: some connections may store fields at top level
  const cfg = Object.assign({}, state.config || {});
  const typeDef = getTypeDef(state.type);

  const fId = el("input",{type:"text",value: state.id || "", placeholder:"mi-connection-id"});
  const fName = el("input",{type:"text",value: state.name || "", placeholder:"Nombre descriptivo"});
  const fType = el("select",{});
  Object.entries(CONNECTION_TYPES).forEach(([k,def])=>{
    fType.appendChild(el("option",{value:k,text:def.label || k, selected: k===state.type}));
  });
  // allow custom type
  if(!CONNECTION_TYPES[state.type]){
    fType.appendChild(el("option",{value:state.type,text:state.type,selected:true}));
  }

  const fieldsHost = el("div",{});
  const errorBox = el("div",{class:"mmi-danger",style:"display:none;"});

  function renderFields(){
    const t = fType.value;
    const def = getTypeDef(t);
    fieldsHost.innerHTML = "";
    if(def.help) fieldsHost.appendChild(el("div",{class:"mmi-muted",text:def.help,style:"margin-bottom:8px;"}));
    def.fields.forEach(field=>{
      const val = (cfg[field.key] !== undefined) ? cfg[field.key] : (state[field.key] !== undefined ? state[field.key] : "");
      const input = el("input",{type: field.type||"text", value: val || "", placeholder: field.placeholder||""});
      input.dataset.key = field.key;
      fieldsHost.appendChild(el("div",{style:"margin:10px 0;"},[
        el("label",{style:"display:block;font-weight:600;margin-bottom:4px;", text: field.label + (field.required ? " *" : "")}),
        input,
      ]));
    });
  }

  renderFields();
  fType.addEventListener("change", ()=>renderFields());
  fName.addEventListener("input", ()=>{
    if(!fId.value) fId.value = slugify(fName.value);
  });

  const form = el("div",{},[
    errorBox,
    el("div",{style:"display:grid;grid-template-columns:1fr 1fr;gap:10px;align-items:end;"},[
      el("div",{},[
        el("label",{style:"display:block;font-weight:600;margin-bottom:4px;",text:"ID (único) *"}),
        fId,
      ]),
      el("div",{},[
        el("label",{style:"display:block;font-weight:600;margin-bottom:4px;",text:"Tipo *"}),
        fType,
      ]),
    ]),
    el("div",{style:"margin-top:10px;"},[
      el("label",{style:"display:block;font-weight:600;margin-bottom:4px;",text:"Nombre"}),
      fName,
    ]),
    el("hr",{style:"margin:14px 0;"}),
    fieldsHost,
  ]);

  function getPayload(){
    const id = slugify(fId.value);
    const type = String(fType.value||"").trim();
    const name = String(fName.value||"").trim();
    const def = getTypeDef(type);

    const outCfg = {};
    def.fields.forEach(field=>{
      const input = fieldsHost.querySelector(`[data-key="${field.key}"]`);
      const v = input ? String(input.value||"").trim() : "";
      if(field.required && !v) throw new Error(`Falta: ${field.label}`);
      if(v) outCfg[field.key] = v;
    });

    if(!id) throw new Error("Falta: ID");
    if(!type) throw new Error("Falta: tipo");

    return { id, type, name, config: outCfg };
  }

  function setError(msg){
    errorBox.style.display = msg ? "" : "none";
    errorBox.textContent = msg || "";
  }

  return { node: form, getPayload, setError };
}

async function loadConnectionsUI(){
  connMsg.textContent = "Cargando…";
  const r = await api("server.connections.list", {});
  if(!r || r.ok === false){
    connMsg.textContent = "";
    connList.innerHTML = "";
    const msg = (r && (r.message || r.error)) ? `${r.message || r.error}` : "Error cargando connections";
    connList.appendChild(el("div",{class:"mmi-danger",text: msg}));
    if(r) connList.appendChild(el("pre",{class:"mmi-code",text: pretty(r).slice(0,2000)}));
    return;
  }
  const items = r.items || {};
  connList.innerHTML = "";
  const keys = Object.keys(items);
  connMsg.textContent = keys.length ? `${keys.length} connection(s)` : "0 connection(s)";
  if(keys.length === 0){
    connList.appendChild(el("div",{class:"mmi-muted",text:"No hay connections aún. Pulsa “Nueva connection”."}));
    return;
  }
  keys.sort().forEach(id=>{
    const c = items[id] || {};
    connList.appendChild(renderConnRow(id,c));
  });
}

async function saveConn(payload){
  const r = await api("server.connections.save", payload);
  if(!r || r.ok === false){
    throw new Error((r && (r.message||r.error)) ? (r.message||r.error) : "save_failed");
  }
  return r;
}

async function deleteConn(id){
  if(!confirm(`¿Borrar connection "${id}"?`)) return;
  connMsg.textContent = "Borrando…";
  const r = await api("server.connections.delete", { id });
  connMsg.textContent = "";
  if(!r || r.ok === false){
    alert("Error borrando: " + ((r && (r.message||r.error)) ? (r.message||r.error) : "delete_failed"));
    return;
  }
  await loadConnectionsUI();
}

async function testConn(id){
  connMsg.textContent = "Probando…";
  const r = await api("server.connections.test", { id });
  connMsg.textContent = "";
  if(!r || r.ok === false){
    alert("Test falló: " + ((r && (r.message||r.error)) ? (r.message||r.error) : "test_failed"));
    return;
  }
  const msg = r.message || "OK";
  alert("Connection OK: " + msg);
}

function openConnEditor(id, c){
  const form = buildConnForm(Object.assign({}, c || {}, { id }));
  const actions = el("div",{style:"display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;"},[]);
  const btnCancel = el("button",{type:"button",text:__("cancel","Cancel"),onclick:closeModal});
  const btnSave = el("button",{type:"button",text:__("save","Save"),onclick: async ()=>{
    try{
      form.setError("");
      const payload = form.getPayload();
      await saveConn(payload);
      closeModal();
      await loadConnectionsUI();
    }catch(e){
      form.setError(e && e.message ? e.message : String(e));
    }
  }});
  actions.appendChild(btnCancel);
  actions.appendChild(btnSave);
  openModal(id ? "Editar connection" : "Nueva connection", form.node, actions);
}

btnConnNew.addEventListener("click", ()=>openConnEditor("", {}));
btnConnReload.addEventListener("click", ()=>loadConnectionsUI());
const secTemplates = el("div", { class: "mmi-card", id: "mmi-templates" }, [
  el("h2", { text: "Templates" }),
  el("div", { class: "mmi-muted", text: "Crea Flows desde plantillas (1 click)." }),
]);
const tplList = el("div",{});
const btnTplReload = el("button",{type:"button",text:__("reload","Reload")});
secTemplates.appendChild(el("div",{class:"mmi-actions"},[btnTplReload]));
secTemplates.appendChild(tplList);

async function loadTemplatesUI(){
  try{
    const r = await api("server.templates.list", {});
    const items = r.items || [];
    tplList.innerHTML="";
    if(!items.length){
      tplList.appendChild(el("div",{class:"mmi-muted",text:"No hay templates disponibles."}));
      return;
    }
    items.forEach(t=>{
      const btn = el("button",{type:"button",text:__("create_flow","Create flow")});
      btn.addEventListener("click", async ()=>{
        await api("server.flows.save", t.flow);
        alert("Flow creado. Ve a Flows para editarlo.");
      });
      tplList.appendChild(el("div",{class:"mmi-card",style:"margin-top:10px;"},[
        el("div",{style:"display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap;"},[
          el("div",{},[
            el("div",{text:t.label || t.id}),
            el("div",{class:"mmi-muted",text:t.id||""})
          ]),
          el("div",{class:"mmi-actions"},[btn])
        ])
      ]));
    });
  }catch(e){
    tplList.innerHTML="";
    tplList.appendChild(el("div",{class:"mmi-muted",text:"Error cargando templates."}));
  }
}
btnTplReload.addEventListener("click", loadTemplatesUI);

// ---------- END MMI UX PAGES v1 ----------
  const secAudit  = el("div", { "data-mmi-sec": "audit" }, []);
  const secExec   = el("div", { "data-mmi-sec": "exec" }, []);

  advCard.appendChild(el("h3", { text: "Herramientas" }));
  advCard.appendChild(el("p", { class: "mmi-muted", text: "Runner guiado, presets/flows y auditoría del servidor." }));

  // Tools
  secTools.appendChild(el("div", { class: "mmi-actions" }, [quickRow]));
  secTools.appendChild(el("hr", { class: "mmi-hr" }));

  // Runner
  secRunner.appendChild(intentsDatalist);
  secRunner.appendChild(el("h4", { text: "Runner" }));
  secRunner.appendChild(el("div", {}, [el("label", { text: "intentId" }), el("div", { style: "margin-top:6px;" }, [runnerIntent])])) ;
  secRunner.appendChild(el("div", { style: "margin-top:10px;" }, [el("label", { text: "payload" }), el("div", { style: "margin-top:6px;" }, [runnerPayload, runnerFormBox])])) ;
  secRunner.appendChild(runnerTabs);
  secRunner.appendChild(el("div", { class: "mmi-actions", style: "margin-top:10px;" }, [btnPreset, btnValidate, btnRun]));
  secRunner.appendChild(runnerMsg);
  secRunner.appendChild(runnerInfo);
  secRunner.appendChild(el("div", { style: "margin-top:12px;" }, [runnerHistoryTitle, runnerHistoryBox]));
  secRunner.appendChild(el("hr", { class: "mmi-hr" }));

  // Intents list
  secIntents.appendChild(el("h4", { text: "Intents disponibles" }));
  secIntents.appendChild(intentSearch);
  secIntents.appendChild(intentsListBox);
  secIntents.appendChild(el("hr", { class: "mmi-hr" }));

  // Presets
  secPresets.appendChild(el("h4", { text: "Presets (servidor)" }));
  secPresets.appendChild(el("p", { class: "mmi-muted", text: "Guarda y reutiliza plantillas en el servidor." }));
  secPresets.appendChild(el("div", { class: "mmi-actions" }, [presetNameInput, btnPresetSaveServer, btnPresetExport, btnPresetImport]));
  secPresets.appendChild(presetsFile);
  secPresets.appendChild(el("div", { class: "mmi-actions", style: "margin-top:8px;" }, [presetsSelect, btnPresetLoadServer, btnPresetDeleteServer]));
  secPresets.appendChild(el("hr", { class: "mmi-hr" }));

  // Flows
  secFlows.appendChild(el("h4", { text: "Flows (secuencias)" }));
  secFlows.appendChild(el("p", { class: "mmi-muted", text: "Un flow es una secuencia de intents. Puedes guardarlo y ejecutarlo (auto-live)." }));
  secFlows.appendChild(el("div", { class: "mmi-actions" }, [flowsSelect, btnFlowLoad, btnFlowNew, btnFlowRun, btnFlowSave, btnFlowDelete, btnFlowExport, btnFlowImport]));
  secFlows.appendChild(flowsFile);
  secFlows.appendChild(el("div", { class: "mmi-actions", style:"margin-top:10px;" }, [flowModeToggle]));
  secFlows.appendChild(flowVisualWrap);
  secFlows.appendChild(el("div", { style: "margin-top:10px;" }, [flowEditor]));
  secFlows.appendChild(el("hr", { class: "mmi-hr" }));

  // Audit
  secAudit.appendChild(el("h4", { text: "Auditoría del servidor" }));
  secAudit.appendChild(el("p", { class: "mmi-muted", text: "Historial real guardado en WordPress (últimos eventos de ejecución)." }));
  secAudit.appendChild(el("div", { class: "mmi-actions" }, [
    el("label", { text: "Límite" }),
    auditLimit,
    btnAuditRefresh,
    btnAuditExport,
    btnAuditClear,
  ]));
  secAudit.appendChild(auditBox);

  // Execution log
  secExec.appendChild(el("h4", { text: "Ejecuciones (Execution Log)" }));
  secExec.appendChild(el("p", { class: "mmi-muted", text: "Log global de ejecuciones (scheduler, eventos, webhooks, manual). Útil para depurar." }));
  secExec.appendChild(el("div", { class: "mmi-actions" }, [
    el("label", { text: "Límite" }),
    execLimit,
    el("label", { text: "Source" }),
    execSource,
    el("label", { text: "Tipo" }),
    execTargetType,
    execTargetId,
    btnExecRefresh,
    el("label", { class: "mmi-badge" }, [execAuto, el("span", { text: "Auto-actualizar" })]),
    btnExecClear,
  ]));
  secExec.appendChild(execBox);

  advCard.appendChild(secTools);
  advCard.appendChild(secRunner);
  advCard.appendChild(secIntents);
  advCard.appendChild(secPresets);
  advCard.appendChild(secFlows);
  advCard.appendChild(secModules);
  advCard.appendChild(secConnections);
  advCard.appendChild(secTemplates);
  advCard.appendChild(secAudit);
  advCard.appendChild(secExec);
  function renderMode() {
    // Back-compat: delega al sistema de perfiles
    applyProfile();
  }
  // Layout
  const topRow = el("div", { class: "mmi-row" }, [
    el("div", { class: "mmi-col" }, [liveBar, ensureCard]),
    el("div", { class: "mmi-col" }, [
      el("div", { class: "mmi-card" }, [el("h3", { text: "Resultado / logs" }), statusBox]),
      historyBox,
    ]),
  ]);

  root.appendChild(header);
  root.appendChild(topRow);
  root.appendChild(advCard);


// ===== Flows Wizard (Uncanny-like) =====
function buildFlowsWizard() {
  const state = {
    flows: {},
    actions: [],
    events: [],
    selectedFlowId: "",
    selectedEventId: "",
    selectedActionIntegration: "",
    selectedActionId: "",
    actionInput: {},
  };

  const makeId = (name) => {
    const base = String(name || "flow").toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "") || "flow";
    const rand = Math.random().toString(36).slice(2, 7);
    return `${base}-${rand}`;
  };

  const card = (title, children=[]) => el("div", { class: "mmi-card" }, [
    el("h3", { style:"margin-top:0", text: title }),
    ...children
  ]);

  const msg = el("div", { class:"mmi-muted", text:"" });
  const listBox = el("div", { class:"mmi-card", style:"padding:12px" }, []);
  const editorBox = el("div", { class:"mmi-card", style:"padding:12px" }, []);
  const rootBox = el("div", { class:"mmi-flow-wizard" }, [
    el("div", { class:"mmi-flow-wizard-header" }, [
      el("div", { class:"mmi-wizard-steps" }, [
        el("div", { class:"mmi-step", "data-step":"1", text:"1) Trigger" }),
        el("div", { class:"mmi-step", "data-step":"2", text:"2) Action" }),
        el("div", { class:"mmi-step", "data-step":"3", text:"3) Options" }),
      ]),
      el("div", { style:"text-align:right" }, [
        el("button", { class:"button button-primary", type:"button", text:__("new_flow","New flow"), onclick: () => startNewFlow() }),
      el("button", { class:"button", type:"button", text:"✨ Generar con IA", onclick: () => showAIFlowGenerator() }),
      ])
    ]),
    el("div", { class:"mmi-flow-wizard-grid" }, [
      el("div", {}, [ listBox ]),
      el("div", {}, [ editorBox ]),
    ]),
    msg
  ]);

  function setMsg(t, kind="") {
    msg.textContent = t || "";
    msg.className = "mmi-muted" + (kind ? (" mmi-msg-"+kind) : "");
  }

  async function loadAll() {
    setMsg("Cargando Flows…");
    const [flowsRes, actionsRes, eventsRes] = await Promise.all([
      api("server.flows.list", {}),
      api("server.actions.list", {}),
      api("server.events.catalog", {}),
    ]);
    state.flows = (flowsRes && (flowsRes.flows || (flowsRes.result && flowsRes.result.flows))) ? (flowsRes.flows || flowsRes.result.flows) : {};
    state.actions = (actionsRes && (actionsRes.items || (actionsRes.result && actionsRes.result.items))) ? (actionsRes.items || actionsRes.result.items) : []; if(state.actions && !Array.isArray(state.actions) && typeof state.actions==='object') state.actions = Object.values(state.actions);
    state.events = (eventsRes && (eventsRes.items || (eventsRes.result && eventsRes.result.items))) ? (eventsRes.items || eventsRes.result.items) : [];
    renderList();
    startNewFlow();
    setMsg("");
  }

  function renderList() {
    listBox.innerHTML = "";
    const search = el("input", { type:"search", placeholder:"Buscar flows…", style:"width:100%;margin-bottom:10px;" });
    const ul = el("div", { class:"mmi-list" }, []);
    const rows = Object.values(state.flows || {}).map(f => ({
      id: f.id || "",
      name: f.name || f.id || "(sin nombre)",
      enabled: (f.enabled !== false),
      trigger: f.trigger || ""
    })).sort((a,b)=>a.name.localeCompare(b.name));

    const draw = () => {
      ul.innerHTML = "";
      const q = (search.value||"").toLowerCase().trim();
      rows.filter(r => !q || r.name.toLowerCase().includes(q) || r.id.toLowerCase().includes(q))
        .forEach(r => {
          const row = el("div", { class:"mmi-list-row" }, [
            el("div", {}, [
              el("div", { style:"font-weight:600", text: r.name }),
              el("div", { class:"mmi-muted", text: `${r.enabled ? "Activo" : "Pausado"} · ${r.trigger || "Sin trigger"}` })
            ]),
            el("div", { style:"text-align:right;white-space:nowrap" }, [
              el("button", { class:"button", type:"button", text:__("edit","Edit"), onclick: () => loadFlow(r.id) }),
              el("button", { class:"button", type:"button", text:"Run", onclick: () => runFlow(r.id) , style:"margin-left:6px" }),
            ])
          ]);
          ul.appendChild(row);
        });
      if (!ul.children.length) ul.appendChild(el("div", { class:"mmi-muted", text:__("no_flows","No flows yet.") }));
    };
    search.addEventListener("input", draw);

    listBox.appendChild(el("div", { style:"font-weight:700;margin-bottom:8px", text:"Flows" }));
    listBox.appendChild(search);
    listBox.appendChild(ul);
    draw();
  }

  function startNewFlow() {
    state.selectedFlowId = "";
    state.selectedEventId = state.events[0]?.id || "";
    state.selectedActionIntegration = "";
    state.selectedActionId = "";
    state.actionInput = {};
    renderEditor({ mode:"new" });
  }

  function loadFlow(id) {
    const f = (state.flows||{})[id];
    if (!f) return;
    state.selectedFlowId = id;
    state.selectedEventId = f.trigger || "";
    const act0 = (Array.isArray(f.actions) ? f.actions[0] : null) || null;
    state.selectedActionId = act0?.action_id || "";
    const actionMeta = state.actions.find(a => a.id === state.selectedActionId);
    state.selectedActionIntegration = actionMeta?.integration || "";
    state.actionInput = (act0 && act0.input && typeof act0.input === "object") ? act0.input : {};
    renderEditor({ mode:"edit", flow:f });
  }

  async function runFlow(id) {
    const f = (state.flows||{})[id];
    if (!f) return;
    const ev = state.events.find(e => e.id === (f.trigger||""));
    const payload = ev?.example_payload || {};
    setMsg("Ejecutando flow…");
    const res = await api("server.flows.run", { id, payload });
    setMsg(res?.ok ? "Flow ejecutado OK" : ("Error al ejecutar: " + (res?.message || res?.error || "unknown")), res?.ok ? "ok":"err");
  }

  function renderEditor({mode, flow}) {
    editorBox.innerHTML = "";

    const nameInput = el("input", { type:"text", placeholder:"Nombre del flow", style:"width:100%;max-width:520px;" });
    const enabledChk = el("input", { type:"checkbox" });
    enabledChk.checked = flow ? (flow.enabled !== false) : true;

    if (flow) nameInput.value = flow.name || "";

    // Step 1: trigger picker
    const triggerSearch = el("input", { type:"search", placeholder:"Buscar trigger…", style:"width:100%;max-width:520px;margin-bottom:8px;" });
    const triggerGrid = el("div", { class:"mmi-picker-grid" }, []);
    const drawTriggers = () => {
      triggerGrid.innerHTML = "";
      const q = (triggerSearch.value||"").toLowerCase().trim();
      const items = (state.events||[]).filter(e => !q || (e.label||"").toLowerCase().includes(q) || (e.id||"").toLowerCase().includes(q) || (e.group||"").toLowerCase().includes(q));
      items.forEach(e => {
        const isSel = (state.selectedEventId === e.id);
        const c = el("button", {
          type:"button",
          class:"mmi-pick-card" + (isSel ? " is-selected" : ""),
          onclick: () => { state.selectedEventId = e.id; drawTriggers(); setStepActive(1); }
        }, [
          el("div", { class:"mmi-pick-title", text: e.label || e.id }),
          el("div", { class:"mmi-muted", text: (e.group ? e.group + " · " : "") + (e.id || "") })
        ]);
        triggerGrid.appendChild(c);
      });
      if (!triggerGrid.children.length) triggerGrid.appendChild(el("div", { class:"mmi-muted", text:"No se encontraron triggers." }));
    };
    triggerSearch.addEventListener("input", drawTriggers);

    // Step 2: choose integration for action
    const actionSearch = el("input", { type:"search", placeholder:"Buscar acción…", style:"width:100%;max-width:520px;margin-bottom:8px;" });
    const integrationGrid = el("div", { class:"mmi-picker-grid" }, []);
    const actionsGrid = el("div", { class:"mmi-picker-grid" }, []);

    const integrations = (() => {
      const set = new Map();
      (state.actions||[]).forEach(a => {
        const integ = a.integration || "other";
        if (!set.has(integ)) set.set(integ, { slug: integ, name: integ.charAt(0).toUpperCase()+integ.slice(1) });
      });
      return Array.from(set.values()).sort((a,b)=>a.name.localeCompare(b.name));
    })();

    const drawIntegrations = () => {
      integrationGrid.innerHTML = "";
      integrations.forEach(i => {
        const isSel = (state.selectedActionIntegration === i.slug);
        integrationGrid.appendChild(el("button", {
          type:"button",
          class:"mmi-pick-card" + (isSel ? " is-selected" : ""),
          onclick: () => { state.selectedActionIntegration = i.slug; state.selectedActionId = ""; state.actionInput = {}; drawIntegrations(); drawActions(); setStepActive(2); }
        }, [
          el("div", { class:"mmi-pick-title", text: i.name }),
          el("div", { class:"mmi-muted", text: i.slug })
        ]));
      });
      if (!integrationGrid.children.length) integrationGrid.appendChild(el("div", { class:"mmi-muted", text:"No hay integraciones de acciones registradas." }));
    };

    const drawActions = () => {
      actionsGrid.innerHTML = "";
      const q = (actionSearch.value||"").toLowerCase().trim();
      const items = (state.actions||[]).filter(a => {
        if (state.selectedActionIntegration && a.integration !== state.selectedActionIntegration) return false;
        if (!q) return true;
        return (a.label||"").toLowerCase().includes(q) || (a.id||"").toLowerCase().includes(q) || (a.description||"").toLowerCase().includes(q);
      });
      items.forEach(a => {
        const isSel = (state.selectedActionId === a.id);
        actionsGrid.appendChild(el("button", {
          type:"button",
          class:"mmi-pick-card" + (isSel ? " is-selected" : ""),
          onclick: () => { state.selectedActionId = a.id; state.actionInput = state.actionInput || {}; drawActions(); renderActionOptions(a); setStepActive(3); }
        }, [
          el("div", { class:"mmi-pick-title", text: a.label || a.id }),
          el("div", { class:"mmi-muted", text: a.description || a.id })
        ]));
      });
      if (!actionsGrid.children.length) actionsGrid.appendChild(el("div", { class:"mmi-muted", text:"No hay acciones para ese filtro." }));
    };
    actionSearch.addEventListener("input", drawActions);

    // Step 3: options form
    const optionsBox = el("div", {}, []);

    async function renderActionOptions(action) {
      optionsBox.innerHTML = "";
      if (!action) return;

      // preload connections for connection_id fields
      let connections = [];
      try {
        const cRes = await api("server.connections.list", {});
        connections = cRes?.result?.items || [];
      } catch(e) {}

      const fields = action.fields || {};
      const form = el("div", { class:"mmi-form" }, []);

      Object.keys(fields).forEach((k) => {
        const f = fields[k] || {};
        const label = f.label || k;
        const req = !!f.required;

        let inputEl = null;

        if (k === "connection_id") {
          const sel = el("select", { style:"min-width:320px;max-width:520px;" }, [
            el("option", { value:"", text:"— Selecciona conexión —" })
          ]);
          (connections||[]).forEach(c => {
            const opt = el("option", { value: c.id, text: `${c.name || c.id} (${c.type || "custom"})` });
            sel.appendChild(opt);
          });
          sel.value = state.actionInput[k] || "";
          sel.addEventListener("change", () => { state.actionInput[k] = sel.value; });
          inputEl = sel;
        } else if (f.type === "object") {
          const ta = el("textarea", { rows:"5", style:"width:100%;max-width:520px;", placeholder:"{ }" });
          ta.value = state.actionInput[k] ? JSON.stringify(state.actionInput[k], null, 2) : "";
          ta.addEventListener("change", () => {
            try {
              state.actionInput[k] = ta.value.trim() ? JSON.parse(ta.value) : {};
              ta.style.borderColor = "";
            } catch(err) {
              ta.style.borderColor = "#d63638";
            }
          });
          inputEl = ta;
        } else {
          const inp = el("input", { type:"text", style:"width:100%;max-width:520px;" });
          inp.value = (state.actionInput[k] ?? "");
          inp.addEventListener("input", () => { state.actionInput[k] = inp.value; });
          inputEl = inp;
        }

        form.appendChild(el("div", { class:"mmi-form-row" }, [
          el("label", { style:"font-weight:600;display:block;margin-bottom:4px", text: label + (req ? " *" : "") }),
          inputEl,
        ]));
      });

      optionsBox.appendChild(form);
    }

    // Save / delete
    const btnSave = el("button", { class:"button button-primary", type:"button", text:__("save_flow","Save Flow") });
    const btnDelete = el("button", { class:"button", type:"button", text:__("delete","Delete"), style:"margin-left:8px" });
    const btnTest = el("button", { class:"button", type:"button", text:"Run test", style:"margin-left:8px" });

    btnSave.onclick = async () => {
      const name = nameInput.value.trim();
      if (!name) return setMsg("Pon un nombre al flow.", "err");
      if (!state.selectedEventId) return setMsg("Selecciona un trigger.", "err");
      if (!state.selectedActionId) return setMsg("Selecciona una acción.", "err");

      const id = state.selectedFlowId || makeId(name);
      const payload = {
        id,
        name,
        enabled: enabledChk.checked,
        trigger: state.selectedEventId,
        actions: [
          { action_id: state.selectedActionId, input: state.actionInput || {} }
        ],
      };

      setMsg("Guardando…");
      const res = await api("server.flows.save", payload);
      if (res?.ok) {
        setMsg("Guardado OK", "ok");
        const flowsRes = await api("server.flows.list", {});
        state.flows = flowsRes?.result?.flows || {};
        renderList();
        loadFlow(id);
      } else {
        setMsg("Error al guardar: " + (res?.message || res?.error || "unknown"), "err");
      }
    };

    btnDelete.onclick = async () => {
      const id = state.selectedFlowId;
      if (!id) return;
      if (!confirm("¿Eliminar este flow?")) return;
      setMsg("Eliminando…");
      const res = await api("server.flows.delete", { id });
      if (res?.ok) {
        setMsg("Eliminado.", "ok");
        const flowsRes = await api("server.flows.list", {});
        state.flows = flowsRes?.result?.flows || {};
        renderList();
        startNewFlow();
      } else {
        setMsg("Error al eliminar: " + (res?.message || res?.error || "unknown"), "err");
      }
    };

    btnTest.onclick = async () => {
      const id = state.selectedFlowId;
      if (!id) return setMsg("Guarda el flow antes de probarlo.", "err");
      await runFlow(id);
    };

    const top = el("div", {}, [
      el("div", { style:"display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:12px" }, [
        el("div", { style:"flex:1;min-width:260px" }, [
          el("label", { style:"display:block;font-weight:600;margin-bottom:4px", text:"Nombre" }),
          nameInput,
        ]),
        el("label", { style:"display:flex;gap:6px;align-items:center;margin-top:22px" }, [
          enabledChk,
          el("span", { text:"Activo" }),
        ]),
      ]),
    ]);

    editorBox.appendChild(top);

    editorBox.appendChild(card("1) Elige un trigger", [triggerSearch, triggerGrid]));
    editorBox.appendChild(card("2) Elige una integración para la acción", [integrationGrid]));
    editorBox.appendChild(card("3) Elige una acción", [actionSearch, actionsGrid]));
    editorBox.appendChild(card("4) Configura opciones", [optionsBox]));
    editorBox.appendChild(el("div", { style:"margin-top:12px" }, [btnSave, btnTest, (mode==="edit" ? btnDelete : null)].filter(Boolean)));

    drawIntegrations();
    drawActions();
    drawTriggers();

    if (state.selectedActionId) {
      const a = state.actions.find(x => x.id === state.selectedActionId);
      if (a) renderActionOptions(a);
    }
  }

  function setStepActive(n) {
    try {
      rootBox.querySelectorAll(".mmi-step").forEach(s => s.classList.remove("is-active"));
      const elStep = rootBox.querySelector(`.mmi-step[data-step="${n}"]`);
      if (elStep) elStep.classList.add("is-active");
    } catch(e) {}
  }

  loadAll().catch(e => setMsg("Error cargando Flows: " + (e?.message || String(e)), "err"));
  return rootBox;
}
// ===== END Flows Wizard =====

// --- HARD ROUTING (subpages) ---
// Some parts of the legacy dashboard (profiles/applyProfile) may re-show blocks after initial filtering.
// To guarantee submenu UX, we hard-route by clearing the root and mounting ONLY the relevant section
// on non-dashboard pages. This prevents "Dashboard completo" from appearing on Integrations/Flows/etc.
const __mmiUrlPage = (new URLSearchParams(window.location.search).get('page') || '').replace(/_/g,'-');
const __mmiPageKey = (root && root.dataset && root.dataset.mmiPage) ? String(root.dataset.mmiPage) :
    (slugMap[__mmiUrlPage] || (__mmiUrlPage.includes('event')?'events':__mmiUrlPage.includes('flow')?'flows':__mmiUrlPage.includes('connection')?'connections':__mmiUrlPage.includes('integration')?'integrations':__mmiUrlPage.includes('log')?'logs':__mmiUrlPage.includes('sched')?'scheduler':__mmiUrlPage.includes('setting')?'settings':'dashboard'));

const mountSubpage = (title, nodes, onMount) => {
  // Hide loader and show only this page
  if (loading) loading.style.display = "none";
  root.style.display = "";
  root.innerHTML = "";
  const head = el("div", { class: "mmi-card mmi-help" }, [
    el("div", { class: "mmi-row" }, [
      el("div", { class: "mmi-col" }, [
        el("h2", { text: "Intent Flow" }),
        el("p", { class: "mmi-muted", text: title }),
      ]),
      el("div", { class: "mmi-col", style: "text-align:right" }, [
        el("a", { class: "button", href: "admin.php?page=mmi-dashboard", text: __("back_dashboard","Back to Dashboard") }),
      ])
    ])
  ]);
  root.appendChild(head);
  (nodes || []).filter(Boolean).forEach(n => root.appendChild(n));
  try { if (typeof onMount === "function") onMount(); } catch(e) {}
};

if (__mmiPageKey !== "dashboard") {
  // Scheduler is rendered by PHP in its own box.
  if (__mmiPageKey === "scheduler") {
    if (loading) loading.style.display = "none";
    root.style.display = "none";
    return;
  }
  if (__mmiPageKey === "flows") {
    // Bloque de templates para la página Flows
    const tplBlock = el("div", { class:"mmi-card", style:"margin-top:16px;" });
    tplBlock.appendChild(el("h3", { text: __('recipes_title', '⚡ Ready-to-use recipes'), style:"margin-top:0;" }));
    tplBlock.appendChild(el("p", { class:"mmi-muted", style:"margin:0 0 12px;font-size:13px;", text: __('recipes_desc', 'Create a flow in 1 click from a pre-built recipe. You can customize it afterwards.') }));
    const tplGrid = el("div", { style:"display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:10px;" });
    tplBlock.appendChild(tplGrid);

    async function loadTplBlock(){
      tplGrid.innerHTML = "";
      try {
        const r = await api("server.templates.list", {});
        const items = r && r.items ? r.items : (r && r.templates ? r.templates : []);
        if(!items.length){ tplGrid.appendChild(el("p",{class:"mmi-muted",text:"No hay recetas disponibles."})); return; }
        items.forEach(t => {
          const card = el("div", { style:"border:1px solid rgba(0,0,0,0.08);border-radius:12px;padding:12px;background:#fff;" });
          card.appendChild(el("div", { style:"font-weight:600;margin-bottom:4px;", text: t.label || t.id }));
          card.appendChild(el("div", { style:"font-size:12px;color:#666;margin-bottom:10px;", text: t.description || "" }));
          const btn = el("button", { class:"button button-primary", text:"▶ "+__("create_flow","Create flow") });
          btn.addEventListener("click", async () => {
            btn.disabled = true; btn.textContent = "Creando…";
            try {
              const res = await api("flow.import", { flows:[t.flow], overwrite:false });
              if(res && res.ok !== false){ btn.textContent = "✅ Creado"; btn.style.background="#00a32a"; }
              else { btn.textContent = "❌ Error"; btn.disabled = false; }
            } catch(e){ btn.textContent = "❌ Error"; btn.disabled = false; }
          });
          card.appendChild(btn);
          tplGrid.appendChild(card);
        });
      } catch(e){ tplGrid.appendChild(el("p",{class:"mmi-muted",text:"Error cargando recetas."})); }
    }

    mountSubpage("Flows", [buildFlowsWizard(), tplBlock], () => { loadTplBlock(); });
    return;
  }
  if (__mmiPageKey === "connections") {
    mountSubpage("Connections", [secConnections], () => { try { loadConnectionsUI && loadConnectionsUI(); } catch(e) {} });
    return;
  }
  if (__mmiPageKey === "integrations") {
    mountSubpage("Integrations", [secModules], () => { try { loadModulesUI && loadModulesUI(); } catch(e) {} });
    return;
  }
  if (__mmiPageKey === "logs") {
    mountSubpage("Logs", [secAudit, secExec], () => { try { loadAudit && loadAudit(); } catch(e) {}; try { loadExecution && loadExecution(); } catch(e) {} });
    return;
  }
  if (__mmiPageKey === "events") {
    const eventsHost = el("div", {});
    mountSubpage("Events — Automatizaciones por evento", [eventsHost], () => {
      try { mountEvents(eventsHost); } catch(e) { console.error("MMI events mount error", e); }
    });
    return;
  }
  if (__mmiPageKey === "settings") {
    // Bloque de configuración general
    const secSettings = el("div", {});

    const settingsHeader = el("div", { class:"mmi-card", style:"border-left:4px solid #2271b1;background:#f0f6fc;margin-bottom:12px;" }, [
      el("h3", { text:"⚙️ Configuración general", style:"margin-top:0;" }),
      el("p", { class:"mmi-muted", style:"margin:0;font-size:13px;", text:"Ajusta el comportamiento global del plugin. Los cambios se aplican inmediatamente." }),
    ]);

    const liveStatus = el("div", { role:"status", "aria-live":"polite", style:"margin:8px 0;min-height:20px;font-size:13px;" });

    function tip(text){ return el("span", { title:text, "aria-label":text, style:"display:inline-block;margin-left:4px;cursor:help;color:#888;font-size:13px;", text:"ⓘ" }); }

    // Toggle dry_run
    const dryToggle = el("input", { type:"checkbox", id:"mmi-dry-run", style:"margin-right:6px;" });
    const dryLabel  = el("label", { for:"mmi-dry-run", style:"font-weight:500;cursor:pointer;" }, [
      el("span", { text:"Modo dry-run (simulación)" }),
      tip("Cuando está activo, todas las acciones se simulan pero NO se ejecutan realmente. Útil para probar sin riesgos. Desactívalo en producción."),
    ]);
    const dryDesc = el("p", { class:"mmi-muted", style:"margin:4px 0 0 22px;font-size:12px;", text:"Las acciones no se ejecutan realmente. Ideal para pruebas." });

    // Toggle safe_mode
    const safeToggle = el("input", { type:"checkbox", id:"mmi-safe-mode", style:"margin-right:6px;" });
    const safeLabel  = el("label", { for:"mmi-safe-mode", style:"font-weight:500;cursor:pointer;" }, [
      el("span", { text:"Modo seguro (safe mode)" }),
      tip("Bloquea acciones potencialmente peligrosas como borrar posts o modificar opciones del sistema. Recomendado mantenerlo activo."),
    ]);
    const safeDesc = el("p", { class:"mmi-muted", style:"margin:4px 0 0 22px;font-size:12px;", text:"Bloquea acciones peligrosas. Recomendado en producción." });

    const btnSave = el("button", { class:"button button-primary", text:__("save_config","💾 Save configuration"), style:"margin-top:16px;" });

    // Cargar estado actual
    async function loadSettings(){
      liveStatus.textContent = "Cargando…";
      liveStatus.style.color = "#888";
      try {
        const r = await api("server.settings.get", {});
        const s = r && r.settings ? r.settings : (r || {});
        dryToggle.checked  = !!s.dry_run;
        safeToggle.checked = s.safe_mode !== false;
        liveStatus.textContent = "";
      } catch(e) {
        liveStatus.textContent = "❌ Error cargando configuración.";
        liveStatus.style.color = "#d63638";
      }
    }

    btnSave.addEventListener("click", async () => {
      liveStatus.textContent = "Guardando…"; liveStatus.style.color = "#2271b1";
      try {
        // Activar Live mode temporalmente (requerido por server.settings.set)
        await api("server.session.live.enable", {});
        // Obtener token de seguridad primero
        const ch = await api("server.settings.challenge", {});
        const token = ch && ch.token ? ch.token : (ch && ch.result && ch.result.token ? ch.result.token : "");
        if (!token) {
          liveStatus.textContent = "❌ Error obteniendo token de seguridad.";
          liveStatus.style.color = "#d63638";
          return;
        }
        const r = await api("server.settings.set", {
          token:     token,
          dry_run:   dryToggle.checked,
          safe_mode: safeToggle.checked,
        });
        if (!r || r.ok === false) {
          liveStatus.textContent = "❌ Error: " + (r?.error || "unknown");
          liveStatus.style.color = "#d63638";
          return;
        }
        liveStatus.textContent = "✅ Configuración guardada.";
        liveStatus.style.color = "#00a32a";
      } catch(e) {
        liveStatus.textContent = "❌ Error inesperado.";
        liveStatus.style.color = "#d63638";
      }
    });

    const settingsForm = el("div", { class:"mmi-card" }, [
      el("h4", { text:"Modo de ejecución", style:"margin-top:0;" }),
      el("div", { style:"margin-bottom:16px;" }, [ dryToggle, dryLabel, dryDesc ]),
      el("div", { style:"margin-bottom:8px;" }, [ safeToggle, safeLabel, safeDesc ]),
      liveStatus,
      btnSave,
    ]);

    secSettings.appendChild(settingsHeader);
    secSettings.appendChild(settingsForm);

    mountSubpage("Settings", [secSettings, secPresets], () => {
      try { loadSettings(); } catch(e) {}
      try { loadPresets && loadPresets(); } catch(e) {}
    });
    return;
  }
}
// --- END HARD ROUTING ---



// Page filtering (submenu UX)
// Source of truth: PHP renders data-mmi-page / data-mmi-slug on #mmi-dashboard.
// We also fallback to URL ?page=... and fuzzy matching so submenu routing never "falls back to Dashboard" silently.
const slugMap = {
  'mmi-dashboard':'dashboard',
  'mmi-flows':'flows',
  'mmi-help':'help',
  'mmi-events':'events',
  'mmi-connections':'connections',
  'mmi-integrations':'integrations',
  'mmi-logs':'logs',
  'mmi-scheduler':'scheduler',
  'mmi-settings':'settings',
};

function detectPageKey() {
  try {
    // 1) Prefer dataset (comes from PHP, independent of URL variations)
    const ds = (root && root.dataset) ? root.dataset : {};
    if (ds.mmiPage) return String(ds.mmiPage);

    // 2) URL param ?page=
    const p = new URLSearchParams(window.location.search).get('page') || '';
    const norm = String(p).replace(/_/g, '-');
    if (slugMap[norm]) return slugMap[norm];

    // 3) Fuzzy match (future-proof)
    const s = norm.toLowerCase();
    if (s.includes('flow')) return 'flows';
    if (s.includes('connection')) return 'connections';
    if (s.includes('integration')) return 'integrations';
    if (s.includes('log')) return 'logs';
    if (s.includes('sched')) return 'scheduler';
    if (s.includes('setting')) return 'settings';
  } catch (e) {}
  return 'dashboard';
}

const mmiPage = detectPageKey();
const showOnly = (sections) => {
  // hide everything in advCard
  [secTools, secRunner, secIntents, secPresets, secFlows, secModules, secConnections, secTemplates, secAudit, secExec].forEach(s=>{
    if(!s) return;
    s.style.display = 'none';
  });
  sections.forEach(s=>{ if(s) s.style.display = ''; });
};

// Strong routing: for subpages we *remove* non-relevant blocks from the DOM.
// This avoids any later JS (profiles) or CSS from re-showing them.
const removeOthers = (keepSections) => {
  const keep = new Set((keepSections || []).filter(Boolean));
  [secTools, secRunner, secIntents, secPresets, secFlows, secModules, secConnections, secTemplates, secAudit, secExec].forEach(s=>{
    if(!s) return;
    if(!keep.has(s)) {
      try { s.remove(); } catch(e) { s.style.display = 'none'; }
    }
  });
};

// Reveal UI only after router has a chance to filter (prevents "Dashboard completo" flash)
if (loading) loading.style.display = "none";
if (mmiPage !== 'scheduler') root.style.display = '';

if (mmiPage === 'flows') {
  // keep header + topRow (runner) minimal
  topRow.style.display = 'none';
  showOnly([secFlows]);
  removeOthers([secFlows]);
} else if (mmiPage === 'connections') {
  topRow.style.display = 'none';
  showOnly([secConnections]);
  removeOthers([secConnections]);
  if (typeof loadConnectionsUI === 'function') loadConnectionsUI();
} else if (mmiPage === 'integrations') {
  topRow.style.display = 'none';
  showOnly([secModules]);
  removeOthers([secModules]);
  if (typeof loadModulesUI === 'function') loadModulesUI();
} else if (mmiPage === 'logs') {
  topRow.style.display = 'none';
  showOnly([secAudit, secExec]);
  removeOthers([secAudit, secExec]);
} else if (mmiPage === 'scheduler') {
  // hide JS dashboard, show PHP scheduler box
  root.style.display = 'none';
} else if (mmiPage === 'settings') {
  topRow.style.display = 'none';
  showOnly([secPresets]);
  removeOthers([secPresets]);
} else {
  // dashboard
  topRow.style.display = '';
  // ensure optional sections are visible
  [secConnections, secTemplates].forEach(s=>{ if(s) s.style.display = ''; });
  if (typeof loadConnectionsUI === 'function') loadConnectionsUI();
  if (typeof loadTemplatesUI === 'function') loadTemplatesUI();
}

  // Init
  renderHistory();
  await refreshLiveStatus();
  await applyProfile();

  // applyProfile() puede cambiar visibilidad (perfil). En subpáginas, el routing manda SIEMPRE.
  if (mmiPage && mmiPage !== 'dashboard') {
    try {
      if (mmiPage === 'flows') {
        topRow.style.display = 'none';
        showOnly([secFlows]);
        removeOthers([secFlows]);
      } else if (mmiPage === 'connections') {
        topRow.style.display = 'none';
        showOnly([secConnections]);
        removeOthers([secConnections]);
        if (typeof loadConnectionsUI === 'function') loadConnectionsUI();
      } else if (mmiPage === 'integrations') {
        topRow.style.display = 'none';
        showOnly([secTools, secIntents]);
        removeOthers([secTools, secIntents]);
      } else if (mmiPage === 'logs') {
        topRow.style.display = 'none';
        showOnly([secAudit, secExec]);
        removeOthers([secAudit, secExec]);
      } else if (mmiPage === 'scheduler') {
        root.style.display = 'none';
      } else if (mmiPage === 'settings') {
        topRow.style.display = 'none';
        showOnly([secPresets]);
        removeOthers([secPresets]);
      }
    } catch (e) {
      // silent - avoid breaking the whole UI if a section ref is missing
      console.warn('MMI routing guard error', e);
    }
  }

  } catch (e) {
    console.error("MMI admin UI fatal", e);
    showFatal("Error cargando la UI (JS).", (e && (e.stack || e.message)) ? (e.stack || e.message) : String(e));
  }
});



// === MMI Audit Filters (UI-only, safe incremental) ===

function buildHelpPage() {

  function acc(icon, title, contentEl, open) {
    const d = document.createElement('details');
    if (open) d.setAttribute('open', '');
    d.className = 'mmi-card';
    d.style.cssText = 'margin-bottom:10px;padding:0;overflow:hidden;';
    const s = document.createElement('summary');
    s.style.cssText = 'cursor:pointer;font-weight:600;font-size:14px;padding:14px 18px;list-style:none;display:flex;align-items:center;gap:10px;background:var(--color-background-secondary);border-bottom:0.5px solid var(--color-border-tertiary);';
    s.innerHTML = '<span style="font-size:18px;">' + icon + '</span>' + title;
    const body = document.createElement('div');
    body.style.cssText = 'padding:16px 18px;';
    body.appendChild(contentEl);
    d.appendChild(s);
    d.appendChild(body);
    return d;
  }

  function p(text) {
    const el = document.createElement('p');
    el.style.cssText = 'font-size:14px;color:var(--color-text-secondary);line-height:1.7;margin:8px 0;';
    el.innerHTML = text;
    return el;
  }

  function h(text) {
    const el = document.createElement('h4');
    el.style.cssText = 'margin:14px 0 6px;font-size:13px;font-weight:600;color:var(--color-text-primary);border-bottom:0.5px solid var(--color-border-tertiary);padding-bottom:4px;';
    el.textContent = text;
    return el;
  }

  function badge(text, color) {
    return '<span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:11px;font-weight:600;background:' + (color||'rgba(34,113,177,.12)') + ';color:' + (color?'#fff':'#2271b1') + ';">' + text + '</span>';
  }

  function infoBox(text, type) {
    const colors = { info: '#f0f6fc', warn: '#fff8e5', success: '#f0faf4' };
    const borders = { info: '#2271b1', warn: '#f59e0b', success: '#00a32a' };
    const d = document.createElement('div');
    d.style.cssText = 'background:' + (colors[type]||colors.info) + ';border-left:4px solid ' + (borders[type]||borders.info) + ';border-radius:6px;padding:10px 14px;margin:10px 0;font-size:13px;color:var(--color-text-secondary);line-height:1.6;';
    d.innerHTML = text;
    return d;
  }

  function stepList(items) {
    const ol = document.createElement('ol');
    ol.style.cssText = 'margin:8px 0;padding-left:22px;font-size:14px;color:var(--color-text-secondary);line-height:2.2;';
    items.forEach(i => { const li = document.createElement('li'); li.innerHTML = i; ol.appendChild(li); });
    return ol;
  }

  function ul(items) {
    const list = document.createElement('ul');
    list.style.cssText = 'margin:6px 0;padding-left:20px;font-size:13px;color:var(--color-text-secondary);line-height:2;';
    items.forEach(i => { const li = document.createElement('li'); li.innerHTML = i; list.appendChild(li); });
    return list;
  }

  function tbl(headers, rows) {
    const t = document.createElement('table');
    t.style.cssText = 'width:100%;border-collapse:collapse;font-size:12px;margin:10px 0;';
    const thead = document.createElement('thead');
    thead.innerHTML = '<tr style="background:var(--color-background-secondary);">' + headers.map(h => '<th style="padding:7px 10px;text-align:left;border:0.5px solid var(--color-border-tertiary);font-size:12px;">' + h + '</th>').join('') + '</tr>';
    const tbody = document.createElement('tbody');
    rows.forEach((row, ri) => {
      const tr = document.createElement('tr');
      tr.style.background = ri % 2 === 0 ? 'transparent' : 'var(--color-background-secondary)';
      tr.innerHTML = row.map((c,ci) => '<td style="padding:6px 10px;border:0.5px solid var(--color-border-tertiary);' + (ci===0?'font-family:monospace;':'') + '">' + c + '</td>').join('');
      tbody.appendChild(tr);
    });
    t.appendChild(thead);
    t.appendChild(tbody);
    return t;
  }

  function lnk(text, url) { return '<a href="' + url + '" target="_blank" style="color:#2271b1;font-weight:500;">' + text + ' &rarr;</a>'; }

  function connCard(icon, name, steps, docsUrl) {
    const d = document.createElement('div');
    d.style.cssText = 'border:0.5px solid var(--color-border-tertiary);border-radius:8px;padding:12px 14px;margin:10px 0;';
    const header = document.createElement('div');
    header.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:8px;';
    header.innerHTML = '<span style="font-size:20px;">' + icon + '</span><strong style="font-size:14px;">' + name + '</strong>' + (docsUrl ? '<a href="' + docsUrl + '" target="_blank" style="margin-left:auto;font-size:12px;color:#2271b1;">Docs &rarr;</a>' : '');
    d.appendChild(header);
    const list = document.createElement('ol');
    list.style.cssText = 'margin:0;padding-left:18px;font-size:13px;color:var(--color-text-secondary);line-height:1.9;';
    steps.forEach(s => { const li = document.createElement('li'); li.innerHTML = s; list.appendChild(li); });
    d.appendChild(list);
    return d;
  }

  const wrap = document.createElement('div');

  // Header card
  const hdr = document.createElement('div');
  hdr.className = 'mmi-card';
  hdr.style.cssText = 'border-left:4px solid #2271b1;background:#f0f6fc;margin-bottom:16px;display:flex;align-items:center;gap:16px;';
  hdr.innerHTML = '<span style="font-size:40px;">&#10145;</span><div><h2 style="margin:0 0 4px;">Intent Flow — Help</h2><p style="margin:0;font-size:14px;color:var(--color-text-secondary);">Complete guide to automate your WordPress site without limits or subscriptions.</p></div>';
  wrap.appendChild(hdr);

  // 1. Getting started
  const s1 = document.createElement('div');
  const ib = infoBox('<strong>Tip:</strong> Enable <strong>Dry-run mode</strong> in Settings before testing. Actions will be simulated without executing, so you can test safely.', 'info');
  s1.appendChild(ib);
  s1.appendChild(h('Free version — Quick start'));
  s1.appendChild(stepList([
    'Go to <strong>Events</strong> &rarr; choose a trigger (e.g. <em>User registered</em>) and an action (e.g. <em>Send email</em>).',
    'Or go to <strong>Flows</strong> &rarr; pick a ready-to-use recipe and click <em>Create flow</em>.',
    'Check <strong>Logs</strong> to verify the automation ran correctly.',
    'Disable dry-run in <strong>Settings</strong> when ready for production.',
  ]));
  s1.appendChild(h('Pro version — Quick start'));
  s1.appendChild(stepList([
    'Go to <strong>License</strong> and enter your Pro key to unlock all features.',
    'Go to <strong>Connections</strong> and add your credentials (OpenAI, Stripe, WhatsApp...).',
    'Go to <strong>Flows</strong> and click <strong>Generate with AI</strong> to create automations in plain language.',
    'Link flows to triggers and let Intent Flow do the rest automatically.',
  ]));
  wrap.appendChild(acc('&#128640;', 'Getting started', s1, true));

  // 2. Events
  const s2 = document.createElement('div');
  s2.appendChild(p('Each <strong>Event rule</strong> has one trigger and one action. The action runs every time the trigger fires. You can add a JSON payload with dynamic variables.'));
  s2.appendChild(h('WordPress triggers (Free)'));
  s2.appendChild(tbl(['Trigger ID', 'When it fires'], [
    ['user.registered', 'A new user registers on the site'],
    ['user.login', 'A user logs in successfully'],
    ['user.login_failed', 'A failed login attempt occurs'],
    ['user.logout', 'A user logs out'],
    ['user.profile_updated', 'A user updates their profile'],
    ['user.password_reset', 'A user resets their password'],
    ['user.role_changed', 'A user role is changed by an admin'],
    ['user.deleted', 'A user account is deleted'],
    ['post.created', 'A new post or page is created'],
    ['post.updated', 'A post or page is updated'],
    ['post.status_changed', 'A post changes status (draft, publish, etc.)'],
    ['post.deleted', 'A post is permanently deleted'],
    ['comment.posted', 'A new comment is submitted'],
    ['plugin.updated', 'A plugin is updated'],
    ['cron.tick', 'Every time WP-Cron runs (~every minute)'],
    ['webhook.received', 'An external webhook is received'],
  ]));
  s2.appendChild(h('WooCommerce triggers (Pro)'));
  s2.appendChild(tbl(['Trigger ID', 'When it fires'], [
    ['wc.order.created', 'A new order is placed'],
    ['wc.order.status_changed', 'An order status changes'],
    ['wc.payment.complete', 'A payment is completed'],
    ['wc.order.refunded', 'An order is refunded'],
    ['wc.customer.registered', 'A new WooCommerce customer registers'],
    ['wc.coupon.applied', 'A coupon is applied to a cart'],
    ['wc.product.low_stock', 'A product reaches low stock threshold'],
  ]));
  s2.appendChild(h('Stripe triggers (Pro)'));
  s2.appendChild(tbl(['Trigger ID', 'When it fires'], [
    ['stripe.payment.completed', 'A Stripe payment succeeds'],
    ['stripe.subscription.created', 'A new Stripe subscription is created'],
    ['stripe.subscription.cancelled', 'A Stripe subscription is cancelled'],
    ['stripe.refund.created', 'A Stripe refund is issued'],
  ]));
  wrap.appendChild(acc('&#9889;', 'Events — Triggers reference', s2));

  // 3. Flows
  const s3 = document.createElement('div');
  s3.appendChild(p('Flows are <strong>multi-step automations</strong>. Each step runs an action and can use the output of previous steps. Link a flow to a trigger to run it automatically.'));
  s3.appendChild(h('Create a flow'));
  s3.appendChild(stepList([
    'Click <strong>New flow</strong> to open the editor.',
    'Enter a name and optionally select a trigger.',
    'Add steps: pick an action and configure its payload.',
    'Use <code>{{payload.field}}</code> to inject trigger data into the payload.',
    'Use <code>{{steps.0.result.field}}</code> to use the output of a previous step.',
    'Click <strong>Save Flow</strong>.',
  ]));
  s3.appendChild(h('AI Flow Generator (Pro)'));
  const aiBox = infoBox('Click <strong>Generate with AI</strong>, select your OpenAI connection, and describe what you want in plain language.<br><br><em>Example: "When a user registers, send a welcome email and add a row to Google Sheets with their name and email."</em><br><br>The AI will generate the complete flow JSON automatically. Review it in the editor before saving.', 'success');
  s3.appendChild(aiBox);
  s3.appendChild(h('Ready-to-use recipes'));
  s3.appendChild(p('The <strong>Recipes</strong> section offers pre-built flows you can create with one click. Categories include: WordPress, WooCommerce, Google Sheets, OpenAI and Zapier.'));
  wrap.appendChild(acc('&#128260;', 'Flows — Multi-step automations', s3));

  // 4. Connections
  const s4 = document.createElement('div');
  s4.appendChild(p('Connections store your API credentials securely in WordPress. Create one connection per service and reuse it in all your flows.'));
  s4.appendChild(connCard('&#128225;', 'Telegram Bot', [
    'Create a bot with ' + lnk('@BotFather', 'https://t.me/BotFather') + ' — send /newbot.',
    'Copy the <strong>bot token</strong> (format: 123456789:ABC-DEF...).',
    'Get your <strong>Chat ID</strong> by messaging ' + lnk('@userinfobot', 'https://t.me/userinfobot') + '.',
  ], 'https://core.telegram.org/bots/api'));
  s4.appendChild(connCard('&#128172;', 'Slack Incoming Webhook', [
    'Go to ' + lnk('api.slack.com/apps', 'https://api.slack.com/apps') + ' and create a new app.',
    'Enable <strong>Incoming Webhooks</strong> and add it to your workspace.',
    'Copy the Webhook URL (starts with https://hooks.slack.com/).',
  ], 'https://api.slack.com/messaging/webhooks'));
  s4.appendChild(connCard('&#129302;', 'OpenAI API Key (Pro)', [
    'Go to ' + lnk('platform.openai.com/api-keys', 'https://platform.openai.com/api-keys') + '.',
    'Create a new Secret Key (starts with sk-...).',
    'Make sure your account has available credits.',
  ], 'https://platform.openai.com/docs'));
  s4.appendChild(connCard('&#128202;', 'Google Sheets Service Account (Pro)', [
    'Go to ' + lnk('console.cloud.google.com', 'https://console.cloud.google.com') + ' and create a project.',
    'Enable the <strong>Google Sheets API</strong> in the API Library.',
    'Go to <em>IAM &amp; Admin &rarr; Service Accounts</em> and create one.',
    'Download the <strong>JSON key file</strong> and paste its contents in the connection.',
    'Share your Google Sheet with the Service Account email address.',
  ], 'https://developers.google.com/sheets/api'));
  s4.appendChild(connCard('&#128179;', 'Stripe Secret Key (Pro)', [
    'Go to ' + lnk('dashboard.stripe.com/apikeys', 'https://dashboard.stripe.com/apikeys') + '.',
    'Copy your <strong>Secret Key</strong> (sk_live_... or sk_test_... for testing).',
    'For webhook triggers: run the <code>stripe.webhook.url</code> action to get your endpoint URL, then add it in ' + lnk('dashboard.stripe.com/webhooks', 'https://dashboard.stripe.com/webhooks') + '.',
  ], 'https://stripe.com/docs/api'));
  s4.appendChild(connCard('&#128241;', 'WhatsApp Business API (Pro)', [
    'Go to ' + lnk('developers.facebook.com', 'https://developers.facebook.com') + ' and create an app with the WhatsApp product.',
    'Get your <strong>Phone Number ID</strong> from the WhatsApp &rarr; API Setup section.',
    'Generate a <strong>permanent Access Token</strong> (the temporary one expires in 24h).',
    'Use international phone format without +: <code>34612345678</code>.',
  ], 'https://developers.facebook.com/docs/whatsapp/cloud-api'));
  wrap.appendChild(acc('&#128279;', 'Connections — Setup guide', s4));

  // 5. Variables
  const s5 = document.createElement('div');
  s5.appendChild(p('Use these variables in payload JSON fields to inject dynamic data from the trigger event:'));
  s5.appendChild(tbl(['Variable', 'Description', 'Available in'], [
    ['{{payload.user_email}}', 'User email address', 'user.*, wc.customer.*'],
    ['{{payload.user_login}}', 'Username / login', 'user.registered, user.login'],
    ['{{payload.user_id}}', 'WordPress user ID', 'All user events'],
    ['{{payload.new_role}}', 'New role after change', 'user.role_changed'],
    ['{{payload.post_id}}', 'Post / page ID', 'post.*'],
    ['{{payload.post_type}}', 'Post type (post, page, product...)', 'post.*'],
    ['{{payload.new_status}}', 'New post status', 'post.status_changed'],
    ['{{payload.order_id}}', 'WooCommerce order ID', 'wc.order.*'],
    ['{{payload.total}}', 'Order total amount', 'wc.order.*, wc.payment.*'],
    ['{{payload.currency}}', 'Currency code (EUR, USD...)', 'wc.*, stripe.*'],
    ['{{payload.email}}', 'Customer email (WC)', 'wc.order.*, wc.customer.*'],
    ['{{payload.payment_id}}', 'Stripe payment intent ID', 'stripe.payment.*'],
    ['{{payload.subscription_id}}', 'Stripe subscription ID', 'stripe.subscription.*'],
    ['{{payload.product_name}}', 'WC product name', 'wc.product.low_stock'],
    ['{{payload.stock}}', 'WC product stock qty', 'wc.product.low_stock'],
    ['{{payload.plugin}}', 'Plugin file updated', 'plugin.updated'],
    ['{{meta.source}}', 'Event source label', 'All events'],
    ['{{steps.0.result.text}}', 'Output of step 0 (text field)', 'Multi-step flows'],
    ['{{steps.1.result.summary}}', 'Output of step 1 (summary field)', 'Multi-step flows'],
  ]));
  wrap.appendChild(acc('&#128196;', 'Variables — Dynamic payload reference', s5));

  // 6. Pro modules summary
  const s6 = document.createElement('div');

  function modCard(icon, name, triggers, actions, example) {
    const d = document.createElement('div');
    d.style.cssText = 'border:0.5px solid var(--color-border-tertiary);border-radius:8px;padding:14px;margin:10px 0;';
    d.innerHTML = '<div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;"><span style="font-size:22px;">' + icon + '</span><strong style="font-size:15px;">' + name + '</strong></div>';
    if (triggers.length) {
      const tp = document.createElement('p');
      tp.style.cssText = 'margin:4px 0;font-size:12px;color:var(--color-text-secondary);';
      tp.innerHTML = '<strong>Triggers:</strong> ' + triggers.join(', ');
      d.appendChild(tp);
    }
    if (actions.length) {
      const ap = document.createElement('p');
      ap.style.cssText = 'margin:4px 0;font-size:12px;color:var(--color-text-secondary);';
      ap.innerHTML = '<strong>Actions:</strong> ' + actions.join(', ');
      d.appendChild(ap);
    }
    if (example) {
      const ex = document.createElement('div');
      ex.style.cssText = 'margin-top:10px;background:var(--color-background-secondary);border-radius:6px;padding:8px 12px;font-size:12px;color:var(--color-text-secondary);';
      ex.innerHTML = '<strong>Example:</strong> ' + example;
      d.appendChild(ex);
    }
    return d;
  }

  s6.appendChild(modCard('&#128722;', 'WooCommerce',
    ['wc.order.created', 'wc.order.status_changed', 'wc.payment.complete', 'wc.order.refunded', 'wc.customer.registered', 'wc.product.low_stock'],
    ['wc.order.update_status', 'wc.order.add_note', 'wc.coupon.create', 'wc.order.get', 'wc.customer.get'],
    'Send a WhatsApp message when an order is completed &rarr; trigger: wc.order.status_changed + action: whatsapp.message.send'
  ));
  s6.appendChild(modCard('&#128202;', 'Google Sheets',
    [],
    ['gsheets.append_row', 'gsheets.update_cell', 'gsheets.get_range'],
    'Log every new user registration to a spreadsheet &rarr; trigger: user.registered + action: gsheets.append_row with {{payload.user_email}}'
  ));
  s6.appendChild(modCard('&#129302;', 'OpenAI',
    [],
    ['ai.text.generate', 'ai.text.summarize', 'ai.text.classify', 'ai.text.translate', 'ai.text.moderate'],
    'Auto-moderate comments before publishing &rarr; trigger: comment.posted + action: ai.text.moderate'
  ));
  s6.appendChild(modCard('&#128179;', 'Stripe',
    ['stripe.payment.completed', 'stripe.subscription.created', 'stripe.subscription.cancelled', 'stripe.refund.created'],
    ['stripe.customer.get', 'stripe.payment.create', 'stripe.webhook.url'],
    'Send a Slack message when a payment is received &rarr; trigger: stripe.payment.completed + action: slack.send'
  ));
  s6.appendChild(modCard('&#128241;', 'WhatsApp Business',
    [],
    ['whatsapp.message.send', 'whatsapp.template.send', 'whatsapp.media.send'],
    'Send a WhatsApp confirmation when an order ships &rarr; trigger: wc.order.status_changed + action: whatsapp.message.send with {{payload.email}}'
  ));
  wrap.appendChild(acc('&#128640;', 'Pro modules — Features and examples', s6));

  // 7. FAQ
  const s7 = document.createElement('div');
  [
    ['Is the free version really unlimited?', 'Yes. No execution limits, no credits, no expiry date. The free version is fully functional.'],
    ['Does my data leave my server?', 'No. Everything stays in your WordPress database. Data is only sent to external services if you explicitly configure an integration.'],
    ['How do I test without risk?', 'Enable dry-run mode in Settings. All actions will be simulated without executing. Results appear in Logs with a [dry-run] tag.'],
    ['What happens if an action fails?', 'The plugin retries automatically with exponential backoff. You can see the full result in Logs.'],
    ['How do I chain steps in a flow?', 'Add multiple steps to a flow. Use {{steps.0.result.field}} to pass the output of one step as input to the next.'],
    ['How do I set up Stripe webhook triggers?', 'Run the stripe.webhook.url action to get your endpoint URL. Then go to your Stripe dashboard and add it under Webhooks with the events you want.'],
    ['Can I use WhatsApp for free?', 'Meta offers a free sandbox for testing. For production you need a WhatsApp Business account with Cloud API access, which has a free tier for the first 1000 messages/month.'],
    ['How do I update the Pro version?', 'WordPress detects updates automatically via your license key. You will see an update notification in the Plugins page.'],
  ].forEach(([q, a]) => {
    const d = document.createElement('details');
    d.style.cssText = 'margin-bottom:6px;border:0.5px solid var(--color-border-tertiary);border-radius:8px;overflow:hidden;';
    const sum = document.createElement('summary');
    sum.style.cssText = 'cursor:pointer;font-weight:500;font-size:14px;padding:11px 14px;background:var(--color-background-secondary);list-style:none;';
    sum.textContent = q;
    const ans = document.createElement('p');
    ans.style.cssText = 'margin:0;padding:12px 14px;font-size:13px;color:var(--color-text-secondary);line-height:1.7;border-top:0.5px solid var(--color-border-tertiary);';
    ans.textContent = a;
    d.appendChild(sum);
    d.appendChild(ans);
    s7.appendChild(d);
  });
  wrap.appendChild(acc('&#10067;', 'FAQ — Frequently asked questions', s7));

  // 8. Support
  const s8 = document.createElement('div');
  const proCard = document.createElement('div');
  proCard.style.cssText = 'background:#f0f6fc;border-left:4px solid #2271b1;border-radius:6px;padding:14px;margin-bottom:12px;';
  proCard.innerHTML = '<h3 style="margin-top:0;color:#2271b1;">Intent Flow Pro</h3><p style="margin:0 0 8px;font-size:14px;color:var(--color-text-secondary);">Unlock WooCommerce, Google Sheets, OpenAI, Stripe, WhatsApp and the AI Flow Generator.</p><p style="margin:0;font-size:13px;color:var(--color-text-secondary);"><strong>One-time payment &middot; No subscription &middot; Data stays on your server</strong></p>';
  s8.appendChild(proCard);
  s8.appendChild(p('Developed by <strong>Alvaro Martinez</strong> &mdash; ' + lnk('adaptatuweb.com', 'https://adaptatuweb.com')));
  s8.appendChild(p('Email: ' + lnk('proyectos@adaptatuweb.com', 'mailto:proyectos@adaptatuweb.com')));
  wrap.appendChild(acc('&#9993;', 'Support and contact', s8));


  // ---- PRO BANNER ----
  const proBanner = document.createElement('div');
  proBanner.style.cssText = 'background:linear-gradient(135deg,#1a3a5c 0%,#2271b1 100%);border-radius:12px;padding:24px;margin-bottom:20px;color:#fff;';

  const proTitle = document.createElement('div');
  proTitle.style.cssText = 'display:flex;align-items:center;gap:12px;margin-bottom:16px;';
  proTitle.innerHTML = '<span style="font-size:32px;">&#128640;</span><div><h2 style="margin:0;color:#fff;font-size:20px;">Intent Flow Pro</h2><p style="margin:4px 0 0;font-size:13px;opacity:.85;">Everything in Free, plus powerful integrations and AI automation</p></div>';
  proBanner.appendChild(proTitle);

  const proGrid = document.createElement('div');
  proGrid.style.cssText = 'display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin-bottom:20px;';

  const proFeatures = [
    { icon: '&#128722;', name: 'WooCommerce', desc: '7 triggers + 5 actions for orders, customers and coupons' },
    { icon: '&#128202;', name: 'Google Sheets', desc: 'Read and write spreadsheets automatically' },
    { icon: '&#129302;', name: 'OpenAI / AI', desc: 'Generate, summarize, classify and translate with AI' },
    { icon: '&#128179;', name: 'Stripe', desc: 'Payment and subscription triggers + API actions' },
    { icon: '&#128241;', name: 'WhatsApp Business', desc: 'Send messages and media via Meta API' },
    { icon: '&#10024;', name: 'AI Flow Generator', desc: 'Describe your automation in plain language, AI builds it' },
  ];

  proFeatures.forEach(f => {
    const card = document.createElement('div');
    card.style.cssText = 'background:rgba(255,255,255,.12);border-radius:8px;padding:12px;display:flex;gap:10px;align-items:flex-start;';
    card.innerHTML = '<span style="font-size:22px;min-width:28px;">' + f.icon + '</span><div><strong style="font-size:13px;display:block;margin-bottom:3px;">' + f.name + '</strong><span style="font-size:12px;opacity:.85;line-height:1.4;">' + f.desc + '</span></div>';
    proGrid.appendChild(card);
  });
  proBanner.appendChild(proGrid);

  const proFooter = document.createElement('div');
  proFooter.style.cssText = 'display:flex;align-items:center;gap:16px;flex-wrap:wrap;';
  proFooter.innerHTML = '<div style="font-size:13px;opacity:.9;">&#10003; One-time payment &nbsp; &#10003; No subscription &nbsp; &#10003; Lifetime updates &nbsp; &#10003; Data stays on your server</div>';

  const proBtn = document.createElement('a');
  proBtn.href = 'https://adaptatuweb.com/intent-flow-pro/';
  proBtn.target = '_blank';
  proBtn.style.cssText = 'display:inline-block;background:#fff;color:#2271b1;font-weight:700;font-size:14px;padding:10px 22px;border-radius:6px;text-decoration:none;margin-left:auto;white-space:nowrap;';
  proBtn.textContent = 'Get Intent Flow Pro';
  proFooter.appendChild(proBtn);
  proBanner.appendChild(proFooter);

  wrap.insertBefore(proBanner, wrap.children[1]);

  return wrap;
}

(function(){
  function enhanceAuditUI(){
    const auditCard = document.querySelector('#mmi-audit, .mmi-audit, [data-mmi-audit]');
    if(!auditCard || auditCard.dataset.mmiEnhanced) return;
    auditCard.dataset.mmiEnhanced = "1";

    const controls = document.createElement('div');
    controls.style.marginBottom = '8px';
    controls.innerHTML = `
      <label style="margin-right:8px;">Mostrar:
        <select id="mmi-audit-filter-status">
          <option value="all">Todo</option>
          <option value="ok">OK</option>
          <option value="error">Errores</option>
          <option value="blocked">Blocked</option>
        </select>
      </label>
      <label style="margin-left:12px;">
        <input type="checkbox" id="mmi-audit-autorefresh">
        Auto‑actualizar
      </label>
    `;
    auditCard.prepend(controls);

    const select = controls.querySelector('#mmi-audit-filter-status');
    select.addEventListener('change', ()=>{
      const val = select.value;
      auditCard.querySelectorAll('[data-status]').forEach(row=>{
        if(val==='all') row.style.display='';
        else row.style.display = row.dataset.status===val ? '' : 'none';
      });
    });

    const auto = controls.querySelector('#mmi-audit-autorefresh');
    let timer = null;
    auto.addEventListener('change', ()=>{
      if(auto.checked){
        timer = setInterval(()=>{
          if(window.IntentFlow?.run){
            window.IntentFlow.run('server.audit.latest', {limit:50}).catch(()=>{});
          }
        }, 5000);
      }else if(timer){
        clearInterval(timer);
      }
    });
  }

  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded', enhanceAuditUI);
  }else{
    enhanceAuditUI();
  }
})();
// === end MMI Audit Filters ===



// === MMI Audit Filters FIXED ===
(function(){
  function findServerAuditTable(){
    const tables = document.querySelectorAll('table');
    for(const t of tables){
      if(t.innerText && t.innerText.includes('Intent') && t.innerText.includes('User')){
        return t;
      }
    }
    return null;
  }

  function enhance(){
    const table = findServerAuditTable();
    if(!table || table.dataset.mmiAuditEnhanced) return;
    table.dataset.mmiAuditEnhanced = "1";

    const container = table.parentElement;
    if(!container) return;

    const controls = document.createElement('div');
    controls.style.marginBottom = '10px';
    controls.innerHTML = `
      <label>Mostrar:
        <select id="mmi-audit-filter-status">
          <option value="all">Todo</option>
          <option value="ok">OK</option>
          <option value="blocked">Blocked</option>
        </select>
      </label>
      <label style="margin-left:12px;">
        <input type="checkbox" id="mmi-audit-autorefresh"> Auto-actualizar
      </label>
    `;

    container.insertBefore(controls, table);

    const select = controls.querySelector('#mmi-audit-filter-status');
    select.addEventListener('change', ()=>{
      const val = select.value;
      table.querySelectorAll('tbody tr').forEach(row=>{
        const ok = row.innerText.includes('✔');
        const blocked = row.innerText.includes('✖');
        if(val==='all') row.style.display='';
        else if(val==='ok') row.style.display = ok ? '' : 'none';
        else if(val==='blocked') row.style.display = blocked ? '' : 'none';
      });
    });

    const auto = controls.querySelector('#mmi-audit-autorefresh');
    let timer=null;
    auto.addEventListener('change', ()=>{
      if(auto.checked){
        timer=setInterval(()=>{
          const btn=[...document.querySelectorAll('button')].find(b=>b.innerText.includes('Actualizar'));
          if(btn) btn.click();
        },5000);
      }else if(timer){
        clearInterval(timer);
      }
    });
  }

  setTimeout(enhance,1000);
})();
// === end FIXED ===



// === MMI Audit Filters FINAL RELIABLE ===
(function(){
  function enhance(){
    const buttons = [...document.querySelectorAll('button')];
    const updateBtn = buttons.find(b => b.innerText.includes('Actualizar'));
    if(!updateBtn) return;

    const container = updateBtn.parentElement;
    if(!container || container.dataset.mmiAuditEnhanced) return;

    container.dataset.mmiAuditEnhanced = "1";

    const controls = document.createElement('div');
    controls.style.marginBottom = '8px';
    controls.innerHTML = `
      <label style="margin-right:10px;">
        Mostrar:
        <select id="mmi-audit-filter-status">
          <option value="all">Todo</option>
          <option value="ok">OK</option>
          <option value="blocked">Blocked</option>
        </select>
      </label>
      <label>
        <input type="checkbox" id="mmi-audit-autorefresh"> Auto-actualizar
      </label>
    `;

    container.parentElement.insertBefore(controls, container);

    const table = container.parentElement.querySelector('table');
    if(!table) return;

    controls.querySelector('#mmi-audit-filter-status').addEventListener('change', (e)=>{
      const val = e.target.value;
      table.querySelectorAll('tbody tr').forEach(row=>{
        const ok = row.innerText.includes('✔');
        const blocked = row.innerText.includes('✖');
        if(val==='all') row.style.display='';
        else if(val==='ok') row.style.display = ok ? '' : 'none';
        else if(val==='blocked') row.style.display = blocked ? '' : 'none';
      });
    });

    let timer=null;
    controls.querySelector('#mmi-audit-autorefresh').addEventListener('change', (e)=>{
      if(e.target.checked){
        timer=setInterval(()=>updateBtn.click(), 5000);
      } else if(timer){
        clearInterval(timer);
      }
    });
  }

  setTimeout(enhance, 800);
})();
// === END FINAL ===



// === MMI Flows One-Click (Business) ===
(function(){
  function ensureFlowsQuickRun(){
    // Find the flows section by looking for the "Flows (secuencias)" heading or the flow select.
    const flowSelect = document.querySelector('select') && [...document.querySelectorAll('select')].find(s => (s.innerText||'').includes('— Selecciona un flow —') || (s.id||'').toLowerCase().includes('flow'));
    // Prefer to anchor near the existing flows block: search for a button "Run flow"
    const runBtn = [...document.querySelectorAll('button')].find(b => (b.innerText||'').trim().toLowerCase() === 'run flow');
    if(!runBtn) return;

    const container = runBtn.parentElement;
    if(!container || container.dataset.mmiFlowQuick) return;
    container.dataset.mmiFlowQuick = "1";

    const wrap = document.createElement('div');
    wrap.style.display = 'flex';
    wrap.style.gap = '8px';
    wrap.style.alignItems = 'center';
    wrap.style.margin = '8px 0 10px 0';

    // Find the flow select in the same block (closest textarea/select)
    let select = null;
    const block = runBtn.closest('div');
    if(block){
      select = block.querySelector('select');
    }
    if(!select){
      select = [...document.querySelectorAll('select')].find(s => (s.innerText||'').includes('Selecciona un flow'));
    }
    if(!select) return;

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = runBtn.className || 'button button-primary';
    btn.textContent = '▶ Ejecutar flow (1 clic)';
    btn.setAttribute('aria-label', 'Ejecutar el flow seleccionado');

    const btn2 = document.createElement('button');
    btn2.type = 'button';
    btn2.className = runBtn.className || 'button';
    btn2.textContent = '▶ Ejecutar + refrescar auditoría';
    btn2.setAttribute('aria-label', 'Ejecutar el flow y refrescar la auditoría');

    const status = document.createElement('span');
    status.style.fontSize = '12px';
    status.style.opacity = '0.8';
    status.setAttribute('role','status');
    status.textContent = '';

    function clickRun(){
      // Keep compatibility: select must have a value
      if(!select.value){
        status.textContent = 'Selecciona un flow primero.';
        return false;
      }
      status.textContent = 'Ejecutando…';
      runBtn.click();
      return true;
    }

    btn.addEventListener('click', ()=>{
      if(clickRun()){
        setTimeout(()=>{ status.textContent = 'Listo (ver Resultado/logs).'; }, 800);
      }
    });

    btn2.addEventListener('click', ()=>{
      if(clickRun()){
        // click "Actualizar" on server audit
        setTimeout(()=>{
          const update = [...document.querySelectorAll('button')].find(b => (b.innerText||'').includes('Actualizar'));
          if(update) update.click();
          status.textContent = 'Listo. Auditoría actualizada.';
        }, 1200);
      }
    });

    wrap.appendChild(btn);
    wrap.appendChild(btn2);
    wrap.appendChild(status);

    // Insert right above the existing buttons row
    container.parentElement.insertBefore(wrap, container);
  }

  setTimeout(ensureFlowsQuickRun, 900);
})();
// === END Flows One-Click ===



// === MMI Flow Gallery (Business) ===
(function(){
  function getProfile(){
    // Profile is stored under PROFILE_KEY (mmi_profile_v1). Keep backward-compat with older key.
    try {
      return localStorage.getItem('mmi_profile_v1') || localStorage.getItem('mmi_profile_v1') || localStorage.getItem('mmi_profile') || 'business';
    } catch(e){
      return 'business';
    }
  }

  function findFlowsBlock(){
    // Identify by the "Run flow" button, then climb to a reasonable container.
    const runBtn = [...document.querySelectorAll('button')].find(b => (b.innerText||'').trim().toLowerCase() === 'run flow');
    if(!runBtn) return null;
    const block = runBtn.closest('div');
    return { runBtn, block: block || runBtn.parentElement };
  }

  function buildCards(select){
    const opts = [...select.querySelectorAll('option')];
    const items = [];
    for(const o of opts){
      const val = (o.value||'').trim();
      const label = (o.textContent||'').trim();
      if(!val || label.toLowerCase().includes('selecciona')) continue;
      items.push({ id: val, name: label });
    }
    return items;
  }

  function enhance(){
    const profile = getProfile();
    if(profile !== 'business') return; // only business for now

    const found = findFlowsBlock();
    if(!found || !found.block) return;

    const { runBtn, block } = found;
    const select = block.querySelector('select');
    if(!select) return;

    // Wait until flows are loaded (options > 1)
    const cards = buildCards(select);
    if(cards.length === 0) return;

    if(block.querySelector('#mmi-flow-gallery')) return;

    // Find Load button (Cargar) and textarea (Flow JSON)
    const loadBtn = [...block.querySelectorAll('button')].find(b => (b.innerText||'').trim().toLowerCase() === 'cargar');
    const textarea = block.querySelector('textarea');

    const gallery = document.createElement('div');
    gallery.id = 'mmi-flow-gallery';
    gallery.style.margin = '10px 0 12px 0';

    const header = document.createElement('div');
    header.style.display = 'flex';
    header.style.justifyContent = 'space-between';
    header.style.alignItems = 'center';
    header.style.gap = '10px';
    header.style.marginBottom = '8px';

    const h = document.createElement('div');
    h.innerHTML = '<strong>Flows guardados</strong> <span style="opacity:.7">— Ejecuta en 1 clic</span>';
    header.appendChild(h);

    const refresh = document.createElement('button');
    refresh.type = 'button';
    refresh.className = 'button';
    refresh.textContent = 'Recargar lista';
    refresh.addEventListener('click', ()=>{
      // Try to refresh by calling flow.list (will refresh select in existing UI if it listens)
      if(window.IntentFlow?.run){
        window.IntentFlow.run('flow.list', {}).catch(()=>{});
      }
      // Rebuild after a moment
      setTimeout(()=>{
        const newCards = buildCards(select);
        renderList(newCards);
      }, 400);
    });
    header.appendChild(refresh);

    gallery.appendChild(header);

    const list = document.createElement('div');
    list.style.display = 'grid';
    list.style.gridTemplateColumns = 'repeat(auto-fit, minmax(240px, 1fr))';
    list.style.gap = '10px';

    function renderList(cardItems){
      list.innerHTML = '';
      for(const it of cardItems){
        const card = document.createElement('div');
        card.style.border = '1px solid rgba(0,0,0,.08)';
        card.style.borderRadius = '12px';
        card.style.padding = '10px';
        card.style.background = '#fff';

        const title = document.createElement('div');
        title.style.display = 'flex';
        title.style.justifyContent = 'space-between';
        title.style.gap = '10px';
        title.style.alignItems = 'baseline';

        const name = document.createElement('div');
        name.style.fontWeight = '600';
        name.textContent = it.name;

        const id = document.createElement('code');
        id.style.fontSize = '11px';
        id.style.opacity = '.75';
        id.textContent = it.id;

        title.appendChild(name);
        title.appendChild(id);
        card.appendChild(title);

        const actions = document.createElement('div');
        actions.style.display = 'flex';
        actions.style.flexWrap = 'wrap';
        actions.style.gap = '8px';
        actions.style.marginTop = '10px';

        const run = document.createElement('button');
        run.type = 'button';
        run.className = runBtn.className || 'button button-primary';
        run.textContent = '▶ Ejecutar';
        run.addEventListener('click', ()=>{
          select.value = it.id;
          select.dispatchEvent(new Event('change', { bubbles: true }));
          runBtn.click();
          // Refresh audit shortly
          setTimeout(()=>{
            const update = [...document.querySelectorAll('button')].find(b => (b.innerText||'').includes('Actualizar'));
            if(update) update.click();
          }, 900);
        });

        const view = document.createElement('button');
        view.type = 'button';
        view.className = 'button';
        view.textContent = 'Ver JSON';
        view.addEventListener('click', ()=>{
          select.value = it.id;
          select.dispatchEvent(new Event('change', { bubbles: true }));
          if(loadBtn) loadBtn.click();
          setTimeout(()=>{
            if(textarea){
              textarea.focus();
              textarea.select?.();
              textarea.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
          }, 250);
        });

        actions.appendChild(run);
        actions.appendChild(view);
        card.appendChild(actions);
        list.appendChild(card);
      }
    }

    renderList(cards);
    gallery.appendChild(list);

    // Insert gallery above the select row
    block.insertBefore(gallery, block.firstChild);

    // Keep gallery in sync if select options change later
    const mo = new MutationObserver(()=>{
      const updated = buildCards(select);
      if(updated.length) renderList(updated);
    });
    mo.observe(select, { childList: true, subtree: true });
  }

  // retry a few times (flows list may arrive async)
  let tries = 0;
  const t = setInterval(()=>{
    tries++;
    enhance();
    if(document.querySelector('#mmi-flow-gallery') || tries > 20) clearInterval(t);
  }, 350);
})();
// === END MMI Flow Gallery ===



// === MMI Focus/Selected States (A11y) ===
(function(){
  if(document.getElementById('mmi-a11y-focus-styles')) return;
  const style = document.createElement('style');
  style.id = 'mmi-a11y-focus-styles';
  style.textContent = `
    /* Visible focus ring for all buttons/inputs inside Intent Flow page */
    #mmi-dashboard button:focus-visible,
    #mmi-dashboard [role="button"]:focus-visible,
    #mmi-dashboard a:focus-visible,
    #mmi-dashboard select:focus-visible,
    #mmi-dashboard input:focus-visible,
    #mmi-dashboard textarea:focus-visible {
      outline: 3px solid rgba(26, 115, 232, 0.9);
      outline-offset: 2px;
      border-radius: 10px;
    }

    /* Subtle hover/active for secondary buttons (Cargar/Exportar/Importar/Guardar/etc.) */
    #mmi-dashboard button.button,
    #mmi-dashboard button:not(.button-primary):not(.mmi-primary) {
      transition: transform 120ms ease, box-shadow 120ms ease, background 120ms ease, border-color 120ms ease;
    }
    #mmi-dashboard button.button:hover,
    #mmi-dashboard button:not(.button-primary):not(.mmi-primary):hover {
      box-shadow: 0 1px 0 rgba(0,0,0,.06), 0 4px 14px rgba(0,0,0,.06);
    }
    #mmi-dashboard button.button:active,
    #mmi-dashboard button:not(.button-primary):not(.mmi-primary):active {
      transform: translateY(1px);
    }

    /* Profile buttons: selected state via aria-pressed */
    #mmi-dashboard [data-profile][aria-pressed="true"] {
      background: rgba(26,115,232,.12) !important;
      border-color: rgba(26,115,232,.55) !important;
      box-shadow: 0 0 0 3px rgba(26,115,232,.18);
    }
    #mmi-dashboard [data-profile] {
      cursor: pointer;
    }

    /* Disabled buttons visible */
    #mmi-dashboard button:disabled {
      opacity: .55;
      cursor: not-allowed;
      box-shadow: none !important;
    }
  `;
  document.head.appendChild(style);
})();
// === END Focus/Selected States ===



// === MMI Profile aria-pressed sync ===
(function(){
  function sync(){
    let p = 'business';
    try { p = localStorage.getItem('mmi_profile_v1') || localStorage.getItem('mmi_profile') || 'business'; } catch(e){}
    document.querySelectorAll('#mmi-dashboard [data-profile]').forEach(btn=>{
      const val = btn.getAttribute('data-profile');
      btn.setAttribute('aria-pressed', val === p ? 'true' : 'false');
    });
  }
  document.addEventListener('click', (e)=>{
    const btn = e.target.closest && e.target.closest('#mmi-dashboard [data-profile]');
    if(!btn) return;
    const val = btn.getAttribute('data-profile');
    try { localStorage.setItem('mmi_profile_v1', val); } catch(err){}
    sync();
  });
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', sync);
  else sync();
})();
// === END Profile aria-pressed sync ===



// === MMI Apps (configurable) ===
(function(){

  function downloadJson(filename, obj){
    const blob = new Blob([JSON.stringify(obj, null, 2)], {type:'application/json'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(()=>URL.revokeObjectURL(url), 500);
  }
  const DEFAULT_APPS = [
    {
      id: "app.create_draft",
      title: "Crear borrador",
      description: "Crea un nuevo borrador con fecha actual.",
      flow: {
        name: "App: Crear borrador",
        steps: [
          {
            id: "macro.post.ensure",
            payload: {
              title: "Borrador automático — " + new Date().toLocaleString(),
              status: "draft",
              content: "Creado desde Apps."
            }
          }
        ]
      }
    },
    {
      id: "app.publish_post",
      title: "Publicar post",
      description: "Publica un post nuevo con fecha actual.",
      flow: {
        name: "App: Publicar post",
        steps: [
          {
            id: "macro.post.ensure",
            payload: {
              title: "Post publicado — " + new Date().toLocaleString(),
              status: "publish",
              content: "Publicado desde Apps."
            }
          }
        ]
      }
    },
    {
      id: "app.audit_latest",
      title: "Actualizar auditoría",
      description: "Refresca la auditoría del servidor.",
      intent: "server.audit.latest",
      payload: { limit: 50 }
    }
  ];

  function getProfile(){
    // Profile is stored under PROFILE_KEY (mmi_profile_v1). Keep backward-compat with older key.
    try {
      return localStorage.getItem('mmi_profile_v1') || localStorage.getItem('mmi_profile_v1') || localStorage.getItem('mmi_profile') || 'business';
    } catch(e){
      return 'business';
    }
  }

  function safeJsonParse(text){
    try { return { ok:true, value: JSON.parse(text) }; } catch(e){ return { ok:false, error: e?.message || String(e) }; }
  }

  function unwrapAppsExport(parsed){
    // Accept both formats:
    // 1) legacy: [ {id,title,...}, ... ]
    // 2) wrapped: { schema:'mmi_apps_v1', exported_at:'...', apps:[...] }
    if(Array.isArray(parsed)) return { ok:true, apps: parsed, wrapper: null };
    if(parsed && typeof parsed === 'object' && Array.isArray(parsed.apps)){
      return { ok:true, apps: parsed.apps, wrapper: parsed };
    }
    return { ok:false, error: 'Formato no reconocido (esperado array o {apps:[]}).' };
  }

  function diffApps(currentApps, nextApps){
    const cur = new Map();
    const nxt = new Map();
    (Array.isArray(currentApps) ? currentApps : []).forEach(a=>{ if(a && a.id) cur.set(a.id, a); });
    (Array.isArray(nextApps) ? nextApps : []).forEach(a=>{ if(a && a.id) nxt.set(a.id, a); });

    const added = [];
    const updated = [];
    const removed = [];

    for(const [id, a] of nxt.entries()){
      if(!cur.has(id)) { added.push(id); continue; }
      const b = cur.get(id);
      // Compare stable JSON (order-insensitive for simple objects)
      const aj = JSON.stringify(a);
      const bj = JSON.stringify(b);
      if(aj !== bj) updated.push(id);
    }
    for(const [id] of cur.entries()){
      if(!nxt.has(id)) removed.push(id);
    }

    added.sort(); updated.sort(); removed.sort();
    return { added, updated, removed, nextCount: nxt.size, currentCount: cur.size };
  }

  function showConfirmDialog({ title, bodyHtml, confirmText='Confirmar', cancelText='Cancelar' }){
    const overlay = document.createElement('div');
    overlay.style.position = 'fixed';
    overlay.style.inset = '0';
    overlay.style.background = 'rgba(0,0,0,.35)';
    overlay.style.zIndex = '100000';
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'center';
    overlay.style.justifyContent = 'center';
    overlay.style.padding = '16px';

    const dialog = document.createElement('div');
    dialog.setAttribute('role','dialog');
    dialog.setAttribute('aria-modal','true');
    dialog.style.maxWidth = '720px';
    dialog.style.width = '100%';
    dialog.style.background = '#fff';
    dialog.style.borderRadius = '12px';
    dialog.style.boxShadow = '0 10px 30px rgba(0,0,0,.2)';
    dialog.style.padding = '14px 14px 12px 14px';

    const h = document.createElement('div');
    h.style.fontWeight = '700';
    h.style.fontSize = '14px';
    h.style.marginBottom = '8px';
    h.textContent = title;
    dialog.appendChild(h);

    const b = document.createElement('div');
    b.innerHTML = bodyHtml;
    b.style.fontSize = '13px';
    b.style.lineHeight = '1.45';
    dialog.appendChild(b);

    const footer = document.createElement('div');
    footer.style.display = 'flex';
    footer.style.gap = '8px';
    footer.style.justifyContent = 'flex-end';
    footer.style.marginTop = '12px';

    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'button';
    cancel.textContent = cancelText;

    const ok = document.createElement('button');
    ok.type = 'button';
    ok.className = 'button button-primary';
    ok.textContent = confirmText;

    footer.appendChild(cancel);
    footer.appendChild(ok);
    dialog.appendChild(footer);
    overlay.appendChild(dialog);

    const prevActive = document.activeElement;
    function close(){
      overlay.remove();
      try{ prevActive && prevActive.focus && prevActive.focus(); }catch(e){}
    }

    return new Promise((resolve)=>{
      cancel.addEventListener('click', ()=>{ close(); resolve(false); });
      ok.addEventListener('click', ()=>{ close(); resolve(true); });
      overlay.addEventListener('click', (e)=>{ if(e.target === overlay){ close(); resolve(false); } });
      document.addEventListener('keydown', function onKey(e){
        if(e.key === 'Escape'){
          document.removeEventListener('keydown', onKey);
          close();
          resolve(false);
        }
      });
      document.body.appendChild(overlay);
      ok.focus();
    });
  
  function showConfirmDialogWithValue({ title, bodyHtml, confirmText='Confirmar', cancelText='Cancelar', getValue }){
    const overlay = document.createElement('div');
    overlay.style.position = 'fixed';
    overlay.style.inset = '0';
    overlay.style.background = 'rgba(0,0,0,.35)';
    overlay.style.zIndex = '100000';
    overlay.style.display = 'flex';
    overlay.style.alignItems = 'center';
    overlay.style.justifyContent = 'center';
    overlay.style.padding = '16px';

    const dialog = document.createElement('div');
    dialog.setAttribute('role','dialog');
    dialog.setAttribute('aria-modal','true');
    dialog.style.maxWidth = '720px';
    dialog.style.width = '100%';
    dialog.style.background = '#fff';
    dialog.style.borderRadius = '12px';
    dialog.style.boxShadow = '0 10px 30px rgba(0,0,0,.2)';
    dialog.style.padding = '14px 14px 12px 14px';

    const h = document.createElement('div');
    h.style.fontWeight = '700';
    h.style.fontSize = '14px';
    h.style.marginBottom = '8px';
    h.textContent = title;
    dialog.appendChild(h);

    const b = document.createElement('div');
    b.innerHTML = bodyHtml;
    b.style.fontSize = '13px';
    b.style.lineHeight = '1.45';
    dialog.appendChild(b);

    const footer = document.createElement('div');
    footer.style.display = 'flex';
    footer.style.gap = '8px';
    footer.style.justifyContent = 'flex-end';
    footer.style.marginTop = '12px';

    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'button';
    cancel.textContent = cancelText;

    const ok = document.createElement('button');
    ok.type = 'button';
    ok.className = 'button button-primary';
    ok.textContent = confirmText;

    footer.appendChild(cancel);
    footer.appendChild(ok);
    dialog.appendChild(footer);
    overlay.appendChild(dialog);

    const prevActive = document.activeElement;
    function close(){
      overlay.remove();
      try{ prevActive && prevActive.focus && prevActive.focus(); }catch(e){}
    }

    return new Promise((resolve)=>{
      cancel.addEventListener('click', ()=>{ close(); resolve({ ok:false, value:null }); });
      ok.addEventListener('click', ()=>{
        let val = null;
        try{ val = typeof getValue === 'function' ? getValue(dialog) : null; }catch(e){ val = null; }
        close();
        resolve({ ok:true, value:val });
      });
      overlay.addEventListener('click', (e)=>{ if(e.target === overlay){ close(); resolve({ ok:false, value:null }); } });
      document.body.appendChild(overlay);
      setTimeout(()=>{ try{ ok.focus(); }catch(e){} }, 30);
    });
  }

}

  function normalizeApps(apps){
    if(!Array.isArray(apps)) return [];
    const out = [];
    for(const a of apps){
      if(!a || typeof a !== 'object') continue;
      const id = (a.id || '').toString().trim();
      const title = (a.title || '').toString().trim();
      if(!id || !title) continue;

      const item = { id, title };
      if(a.description) item.description = (a.description||'').toString();

      if(a.intent){
        item.intent = (a.intent||'').toString();
        item.payload = (a.payload && typeof a.payload === 'object') ? a.payload : {};
      }
      if(a.flow && typeof a.flow === 'object'){
        item.flow = a.flow;
      }
      out.push(item);
      if(out.length >= 50) break;
    }
    return out;
  }

  async function loadAppsFromServer(){
    if(!window.IntentFlow?.run) return null;
    try{
      const res = await window.IntentFlow.run('server.apps.get', {});
      if(res?.ok && Array.isArray(res.apps)) return normalizeApps(res.apps);
      return null;
    }catch(e){
      return null;
    }
  }

  async function _runAndUnwrap(intentId, payload){
    if(!window.IntentFlow?.run) return { ok:false, error:'no_engine' };
    const r = await window.IntentFlow.run(intentId, payload || {});
    // IntentFlow.run devuelve un wrapper { ok, intentId, result }. Normalizamos a result.
    if(r && r.ok === false) return r;
    return (r && typeof r === 'object' && 'result' in r) ? (r.result || {}) : (r || {});
  }

  async function saveAppsToServer(apps){
    return _runAndUnwrap('server.apps.save', { apps });
  }

  async function exportAppsFromServer(){
    return _runAndUnwrap('server.apps.export', {});
  }

  async function importAppsToServer(jsonText, mode){
  const payload = { json: jsonText };
  if(mode) payload.mode = mode;
  return _runAndUnwrap('server.apps.import', payload);
}

async function listAppsHistory(){
  return _runAndUnwrap('server.apps.history.list', {});
}

async function getAppsHistoryItem(id){
  return _runAndUnwrap('server.apps.history.get', { id });
}

async function restoreAppsHistoryItem(id){
  return _runAndUnwrap('server.apps.history.restore', { id });
}

async function clearAppsHistory(){
  return _runAndUnwrap('server.apps.history.clear', {});
}

function runFlow(flow){
    if(!window.IntentFlow?.run) return;
    window.IntentFlow.run('flow.run', flow).then(()=>{
      setTimeout(()=>{
        const update = [...document.querySelectorAll('button')].find(b => (b.innerText||'').includes('Actualizar'));
        if(update) update.click();
      }, 700);
    }).catch(()=>{});
  }

  function runIntent(intent, payload){
    if(!window.IntentFlow?.run) return;
    window.IntentFlow.run(intent, payload).catch(()=>{});
  }

  function createCard(app){
    const card = document.createElement('div');
    card.style.border = '1px solid rgba(0,0,0,.08)';
    card.style.borderRadius = '12px';
    card.style.padding = '10px';
    card.style.background = '#fff';

    const title = document.createElement('div');
    title.style.fontWeight = '600';
    title.textContent = app.title;
    card.appendChild(title);

    const desc = document.createElement('div');
    desc.style.fontSize = '12px';
    desc.style.opacity = '.8';
    desc.style.margin = '4px 0 8px 0';
    desc.textContent = app.description || '';
    card.appendChild(desc);

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'button button-primary';
    btn.textContent = '▶ Ejecutar';
    btn.addEventListener('click', async ()=>{
      const original = btn.textContent;
      btn.disabled = true;
      btn.textContent = '⏳ Ejecutando…';
      try{
        // Prefer server-side execution for consistent logging/auditing.
        if(app && app.id && window.IntentFlow && typeof window.IntentFlow.run === 'function'){
          const out = await window.IntentFlow.run('server.apps.run', { id: app.id, input: {} });
          console.log('[MMI] app.run', app.id, out);
        } else {
          // Fallback: legacy local execution
          if(app.flow) await runFlow(app.flow);
          else if(app.intent) await runIntent(app.intent, app.payload || {});
        }
      } finally {
        btn.disabled = false;
        btn.textContent = original;
      }
    });

    card.appendChild(btn);
    return card;
  }

  function renderAppsBlock(apps){
    const dash = document.querySelector('#mmi-dashboard');
    if(!dash) return;

    // Remove existing block (for rerender)
    const existing = dash.querySelector('#mmi-apps-block');
    if(existing) existing.remove();

    const block = document.createElement('div');
    block.id = 'mmi-apps-block';
    block.style.margin = '10px 0 16px 0';

    const header = document.createElement('div');
    header.style.display = 'flex';
    header.style.justifyContent = 'space-between';
    header.style.alignItems = 'baseline';
    header.style.gap = '10px';
    header.style.marginBottom = '8px';

    const left = document.createElement('div');
    left.innerHTML = '<strong>Apps</strong> <span style="opacity:.7">— Acciones listas para usar</span>';
    header.appendChild(left);

    const profile = getProfile();
    if(profile === 'tech'){
      const editBtn = document.createElement('button');
      editBtn.type = 'button';
      editBtn.className = 'button';
      editBtn.textContent = 'Editar Apps (JSON)';
      editBtn.addEventListener('click', ()=>{
        const panel = block.querySelector('#mmi-apps-editor');
        if(panel){
          const open = (panel.style.display === 'none');
          panel.style.display = open ? '' : 'none';
          localStorage.setItem('mmi_apps_editor_open', open ? '1' : '0');
        }
      });
      header.appendChild(editBtn);
    }

    block.appendChild(header);

    const grid = document.createElement('div');
    grid.style.display = 'grid';
    grid.style.gridTemplateColumns = 'repeat(auto-fit, minmax(220px, 1fr))';
    grid.style.gap = '10px';

    (apps && apps.length ? apps : DEFAULT_APPS).forEach(app=> grid.appendChild(createCard(app)));
    block.appendChild(grid);

    // Tech editor
    if(getProfile() === 'tech'){
      const editor = document.createElement('div');
      editor.id = 'mmi-apps-editor';
      editor.style.marginTop = '12px';
      editor.style.display = (localStorage.getItem('mmi_apps_editor_open') === '1' ? '' : 'none');

      editor.innerHTML = `
        <div style="margin:8px 0;"><strong>Apps (JSON)</strong> <span style="opacity:.7">— se guarda en el servidor</span></div>
        <textarea id="mmi-apps-json" rows="10" style="width:100%; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;"></textarea>
        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; align-items:center;">
          <button type="button" class="button button-primary" id="mmi-apps-save">Guardar Apps</button>
          <button type="button" class="button" id="mmi-apps-export">Exportar archivo</button>
          <label for="mmi-apps-file" class="button" style="display:inline-flex; align-items:center; gap:6px;">
            <span>Elegir .json</span>
            <input id="mmi-apps-file" type="file" accept="application/json,.json" style="display:none;" />
          </label>
          <button type="button" class="button" id="mmi-apps-import-file">Importar archivo</button>
          <button type="button" class="button" id="mmi-apps-import">Importar (editor)</button>
          <button type="button" class="button" id="mmi-apps-reset">Restaurar defaults</button>
          <span id="mmi-apps-status" role="status" style="font-size:12px; opacity:.8;"></span>
        </div>
      
<div id="mmi-apps-history" style="display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; align-items:center;">
  <strong style="font-size:12px; opacity:.85;">Versiones</strong>
  <select id="mmi-apps-history-select" style="min-width:260px; max-width:100%;">
    <option value="">— Sin historial —</option>
  </select>
  <button type="button" class="button" id="mmi-apps-history-load">Cargar en editor</button>
  <button type="button" class="button" id="mmi-apps-history-restore">Restaurar</button>
  <button type="button" class="button" id="mmi-apps-history-undo" style="display:none;">Deshacer</button>
  <button type="button" class="button" id="mmi-apps-history-clear">Borrar historial</button>
  <span style="font-size:12px; opacity:.75;">(Se guarda en el servidor, máx. 20)</span>
</div>

<div id="mmi-apps-history-diff" style="margin-top:10px; padding:10px; border:1px solid rgba(0,0,0,.08); border-radius:10px; background:#fbfbfc;">
  <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
    <strong style="font-size:12px; opacity:.85;">Diff</strong>
    <label style="font-size:12px; opacity:.85;">Base
      <select id="mmi-apps-diff-base" style="margin-left:6px;">
        <option value="current">Actual</option>
      </select>
    </label>
    <label style="font-size:12px; opacity:.85;">Comparar con
      <select id="mmi-apps-diff-target" style="margin-left:6px; min-width:240px; max-width:100%;">
        <option value="">— Selecciona una versión —</option>
      </select>
    </label>
    <button type="button" class="button" id="mmi-apps-diff-run">Ver diff</button>
  </div>
  <div id="mmi-apps-diff-output" style="margin-top:10px;" role="region" aria-label="Cambios entre versiones"></div>
</div>
      `;

      block.appendChild(editor);

      const ta = editor.querySelector('#mmi-apps-json');
      const status = editor.querySelector('#mmi-apps-status');

const histSelect = editor.querySelector('#mmi-apps-history-select');
const histLoadBtn = editor.querySelector('#mmi-apps-history-load');
const histRestoreBtn = editor.querySelector('#mmi-apps-history-restore');
const histUndoBtn = editor.querySelector('#mmi-apps-history-undo');
const histClearBtn = editor.querySelector('#mmi-apps-history-clear');

const diffBaseSel = editor.querySelector('#mmi-apps-diff-base');
const diffTargetSel = editor.querySelector('#mmi-apps-diff-target');
const diffRunBtn = editor.querySelector('#mmi-apps-diff-run');
const diffOut = editor.querySelector('#mmi-apps-diff-output');

let _mmiHistorySeedTried = false;

async function refreshHistory(){
  if(!histSelect) return;
  const r = await listAppsHistory();
  if(r && r.ok === false){
    // Mostrar error en UI para no confundir con 'sin historial'
    try { status.textContent = 'Error historial: ' + (r.error || 'unknown'); } catch(e) {}
  }
  let items = (r && r.ok && Array.isArray(r.items)) ? r.items : [];
  // Si no hay historial, intenta sembrar una versión inicial (una sola vez)
  if(!items.length && !_mmiHistorySeedTried){
    _mmiHistorySeedTried = true;
    const seed = await _runAndUnwrap('server.apps.history.seed', {});
    if(seed && seed.ok){
      const r2 = await listAppsHistory();
      items = (r2 && r2.ok && Array.isArray(r2.items)) ? r2.items : [];
    }
  }
  histSelect.innerHTML = '';
  if(diffTargetSel) diffTargetSel.innerHTML = '';
  if(diffBaseSel) {
    // Keep "Actual" and repopulate history options.
    diffBaseSel.innerHTML = '<option value="current">Actual</option>';
  }
  if(!items.length){
    const opt = document.createElement('option');
    opt.value = '';
    opt.textContent = '— Sin historial —';
    histSelect.appendChild(opt);
    if(diffTargetSel){
      const o2 = document.createElement('option');
      o2.value = '';
      o2.textContent = '— Sin historial —';
      diffTargetSel.appendChild(o2);
    }
    return;
  }
  const opt0 = document.createElement('option');
  opt0.value = '';
  opt0.textContent = '— Selecciona una versión —';
  histSelect.appendChild(opt0);

  items.forEach(it=>{
    const opt = document.createElement('option');
    opt.value = it.id || '';
    const at = it.at ? it.at.replace('T',' ').replace('Z',' UTC') : '';
    const src = it.source ? it.source : 'unknown';
    const mode = it.mode ? `/${it.mode}` : '';
    const count = (typeof it.count === 'number') ? it.count : '';
    opt.textContent = `${at} · ${src}${mode} · ${count} apps`;
    histSelect.appendChild(opt);

    if(diffTargetSel){
      const optT = document.createElement('option');
      optT.value = opt.value;
      optT.textContent = opt.textContent;
      diffTargetSel.appendChild(optT);
    }
    if(diffBaseSel){
      const optB = document.createElement('option');
      optB.value = opt.value;
      optB.textContent = opt.textContent;
      diffBaseSel.appendChild(optB);
    }
  });
}

async function loadAppsSnapshot(ref){
  if(ref === 'current'){
    const cur = await loadAppsFromServer();
    return normalizeApps(cur && cur.length ? cur : (apps && apps.length ? apps : DEFAULT_APPS));
  }
  if(!ref) return [];
  const r = await getAppsHistoryItem(ref);
  if(r && r.ok && Array.isArray(r.apps)) return normalizeApps(r.apps);
  return [];
}

function renderAppsDiff(a, b){
  const d = diffApps(a, b);
  const esc = (s)=> String(s||'').replace(/[&<>"']/g, (c)=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c]));
  const pill = (txt)=>`<code style="display:inline-block; padding:2px 6px; background:#f6f7f7; border-radius:999px; margin:2px 6px 2px 0;">${esc(txt)}</code>`;

  const idxA = {}; a.forEach(x=>{ idxA[x.id]=x; });
  const idxB = {}; b.forEach(x=>{ idxB[x.id]=x; });

  const detailsFor = (id)=>{
    const before = idxA[id] ? JSON.stringify(idxA[id], null, 2) : '';
    const after  = idxB[id] ? JSON.stringify(idxB[id], null, 2) : '';
    const label = id;
    return `
      <details style="margin:8px 0;">
        <summary><strong>${esc(label)}</strong></summary>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-top:8px;">
          <div>
            <div style="font-size:12px; opacity:.8; margin-bottom:4px;">Antes</div>
            <pre style="margin:0; padding:8px; background:#fff; border:1px solid rgba(0,0,0,.08); border-radius:8px; overflow:auto; max-height:240px;">${esc(before || '(no existe)')}</pre>
          </div>
          <div>
            <div style="font-size:12px; opacity:.8; margin-bottom:4px;">Después</div>
            <pre style="margin:0; padding:8px; background:#fff; border:1px solid rgba(0,0,0,.08); border-radius:8px; overflow:auto; max-height:240px;">${esc(after || '(no existe)')}</pre>
          </div>
        </div>
      </details>
    `;
  };

  let html = '';
  html += `<div style="display:flex; flex-wrap:wrap; gap:10px; align-items:baseline;">
    <div><strong>Añadidas:</strong> ${d.added.length}</div>
    <div><strong>Modificadas:</strong> ${d.updated.length}</div>
    <div><strong>Eliminadas:</strong> ${d.removed.length}</div>
  </div>`;

  html += `<div style="margin-top:8px;">
    <div><strong>+ Añadidas</strong></div>
    <div>${d.added.length ? d.added.map(pill).join(' ') : '<span style="opacity:.8">(ninguna)</span>'}</div>
  </div>`;

  html += `<div style="margin-top:8px;">
    <div><strong>~ Modificadas</strong></div>
    <div>${d.updated.length ? d.updated.map(pill).join(' ') : '<span style="opacity:.8">(ninguna)</span>'}</div>
  </div>`;

  html += `<div style="margin-top:8px;">
    <div><strong>- Eliminadas</strong></div>
    <div>${d.removed.length ? d.removed.map(pill).join(' ') : '<span style="opacity:.8">(ninguna)</span>'}</div>
  </div>`;

  const focusIds = [...d.added, ...d.updated, ...d.removed].slice(0, 30);
  if(focusIds.length){
    html += `<div style="margin-top:10px;"><strong>Detalles (máx. 30)</strong>${focusIds.map(detailsFor).join('')}</div>`;
  }
  return html;
}

      const fileInput = editor.querySelector('#mmi-apps-file');
      const importFileBtn = editor.querySelector('#mmi-apps-import-file');

      async function previewAndImportApps(jsonText, sourceLabel){
        const parsed = safeJsonParse(jsonText);
        if(!parsed.ok){ status.textContent = 'JSON inválido: ' + parsed.error; return; }

        const unwrapped = unwrapAppsExport(parsed.value);
        if(!unwrapped.ok){ status.textContent = unwrapped.error; return; }

        const incoming = normalizeApps(unwrapped.apps);
        if(!incoming.length){
          status.textContent = 'No se detectaron apps válidas para importar.';
          return;
        }

        status.textContent = 'Comparando cambios…';
        const current = (await loadAppsFromServer()) || (apps && apps.length ? apps : DEFAULT_APPS);
        const currentNorm = normalizeApps(current);
        const dReplace = diffApps(currentNorm, incoming);
        const dMerge = { added: dReplace.added, updated: dReplace.updated, removed: [] };

        const list = (arr)=> arr.length ? `<code style="display:inline-block; padding:2px 6px; background:#f6f7f7; border-radius:999px; margin:2px 6px 2px 0;">${arr.join('</code> <code style="display:inline-block; padding:2px 6px; background:#f6f7f7; border-radius:999px; margin:2px 6px 2px 0;">')}</code>` : '<span style="opacity:.8">(ninguno)</span>';

                const body = `
          <p style="margin:0 0 10px 0;">Vas a importar Apps desde <strong>${sourceLabel}</strong>.</p>
          <div role="group" aria-label="Modo de importación" style="margin:0 0 10px 0; padding:10px; border:1px solid rgba(0,0,0,.08); border-radius:10px; background:#fbfbfc;">
            <div style="font-weight:600; margin:0 0 6px 0;">Modo</div>
            <label style="display:block; margin:0 0 6px 0;"><input type="radio" name="mmi_apps_import_mode" value="merge" checked> <strong>Merge (recomendado)</strong> — añade y actualiza por ID, sin borrar Apps existentes.</label>
            <label style="display:block; margin:0;"><input type="radio" name="mmi_apps_import_mode" value="replace"> <strong>Replace</strong> — sobrescribe el catálogo (podría eliminar Apps existentes).</label>
          </div>
          <p style="margin:0 0 8px 0;">Resumen (si eliges <strong>Merge</strong>):</p>
          <ul style="margin:0 0 8px 18px;">
            <li><strong>Añadir:</strong> ${dMerge.added.length}</li>
            <li><strong>Actualizar:</strong> ${dMerge.updated.length}</li>
            <li><strong>Eliminar:</strong> 0</li>
          </ul>
          <p style="margin:0 0 8px 0; opacity:.9;">Si eliges <strong>Replace</strong>, se eliminarían: <strong>${dReplace.removed.length}</strong></p>
          <details style="margin:8px 0 0 0;">
            <summary>Ver IDs afectados</summary>
            <div style="margin-top:8px;"><strong>Añadir</strong><div>${list(dMerge.added)}</div></div>
            <div style="margin-top:8px;"><strong>Actualizar</strong><div>${list(dMerge.updated)}</div></div>
            <div style="margin-top:8px;"><strong>Eliminar (solo Replace)</strong><div>${list(dReplace.removed)}</div></div>
          </details>
          <p style="margin:10px 0 0 0; opacity:.85;">Nota: el servidor validará y saneará el contenido. Si algo no es válido, se descartará. En caso de error, se intentará rollback.</p>
        `;

        const dlg = await showConfirmDialogWithValue({
          title: 'Confirmar importación de Apps',
          bodyHtml: body,
          confirmText: 'Importar ahora',
          cancelText: 'Cancelar',
          getValue: (dialogEl)=>{
            const sel = dialogEl.querySelector('input[name="mmi_apps_import_mode"]:checked');
            return sel ? sel.value : 'merge';
          }
        });
        const ok = !!dlg?.ok;
        const mode = (dlg && dlg.value) ? dlg.value : 'merge';

        if(!ok){ status.textContent = 'Importación cancelada.'; return; }

        status.textContent = 'Importando…';
        const res = await importAppsToServer(jsonText, mode);
        if(res?.ok){
          status.textContent = 'Importado (' + (res.count ?? incoming.length) + ')';
          refreshHistory();
          const loaded = await loadAppsFromServer();
          renderAppsBlock(loaded && loaded.length ? loaded : DEFAULT_APPS);
        }else{
          status.textContent = 'Error importando: ' + (res?.error || 'unknown');
        }
      }

      async function importFromFile(file) {
        if(!file) return;
        status.textContent = 'Leyendo archivo…';
        const text = await file.text();
        // Load into textarea for transparency
        const parsed = safeJsonParse(text);
        if(parsed.ok) ta.value = JSON.stringify(parsed.value, null, 2);
        await previewAndImportApps(text, 'archivo');
      }

      if(fileInput){
        fileInput.addEventListener('change', async ()=>{
          const f = fileInput.files && fileInput.files[0];
          if(f) await importFromFile(f);
          // reset so selecting same file again triggers change
          fileInput.value = '';
        });
      }
      if(importFileBtn){
        importFileBtn.addEventListener('click', async ()=>{
          const f = fileInput && fileInput.files && fileInput.files[0];
          if(!f){ status.textContent = 'Selecciona un archivo .json primero.'; if(fileInput){ fileInput.click(); } return; }
          await importFromFile(f);
        });
      }


      // Fill textarea with current apps
      ta.value = JSON.stringify((apps && apps.length ? apps : DEFAULT_APPS), null, 2);

      // Cargar historial de versiones.
      refreshHistory();

      
if(histLoadBtn){
  histLoadBtn.addEventListener('click', async ()=>{
    const id = histSelect ? histSelect.value : '';
    if(!id){ status.textContent = 'Selecciona una versión.'; return; }
    status.textContent = 'Cargando versión…';
    const r = await getAppsHistoryItem(id);
    if(!r || !r.ok || !Array.isArray(r.apps)){
      status.textContent = 'No se pudo cargar la versión.';
      return;
    }
    ta.value = JSON.stringify(r.apps, null, 2);
    status.textContent = 'Versión cargada en el editor (sin aplicar).';
  });
}

if(histRestoreBtn){
  histRestoreBtn.addEventListener('click', async ()=>{
    const id = histSelect ? histSelect.value : '';
    if(!id){ status.textContent = 'Selecciona una versión.'; return; }
    const ok = window.confirm('Vas a RESTAURAR Apps desde el historial. Se guardará un rollback automático. ¿Continuar?');
    if(!ok) return;
    status.textContent = 'Restaurando…';
    const r = await restoreAppsHistoryItem(id);
    if(!r || !r.ok){
      status.textContent = 'Error restaurando: ' + (r && r.error ? r.error : 'unknown');
      return;
    }
    const after = await loadAppsFromServer();
    ta.value = JSON.stringify((after && after.length ? after : DEFAULT_APPS), null, 2);
    status.textContent = 'Restaurado. Apps activas: ' + (r.count || '') + (r.undo_id ? ' (puedes deshacer)' : '');
    if(histUndoBtn && r.undo_id){
      histUndoBtn.dataset.undoId = r.undo_id;
      histUndoBtn.style.display = '';
    }
    refreshHistory();
    // Refrescar tarjetas sin recargar la página
    renderAppsBlock(after && after.length ? after : DEFAULT_APPS);
  });
}

if(histUndoBtn){
  histUndoBtn.addEventListener('click', async ()=>{
    const undoId = histUndoBtn.dataset.undoId || '';
    if(!undoId){ status.textContent = 'No hay nada que deshacer.'; return; }
    const ok = window.confirm('Vas a deshacer la última restauración de Apps. ¿Continuar?');
    if(!ok) return;
    status.textContent = 'Deshaciendo…';
    const r = await restoreAppsHistoryItem(undoId);
    if(!r || !r.ok){
      status.textContent = 'Error deshaciendo: ' + (r && r.error ? r.error : 'unknown');
      return;
    }
    const after = await loadAppsFromServer();
    ta.value = JSON.stringify((after && after.length ? after : DEFAULT_APPS), null, 2);
    status.textContent = 'Deshecho. Apps activas: ' + (r.count || '');
    histUndoBtn.style.display = 'none';
    histUndoBtn.dataset.undoId = '';
    refreshHistory();
    renderAppsBlock(after && after.length ? after : DEFAULT_APPS);
  });
}

if(diffRunBtn){
  diffRunBtn.addEventListener('click', async ()=>{
    const baseRef = diffBaseSel ? diffBaseSel.value : 'current';
    const targetRef = diffTargetSel ? diffTargetSel.value : '';
    if(!targetRef){
      if(diffOut) diffOut.innerHTML = '<span style="opacity:.85;">Selecciona una versión para comparar.</span>';
      return;
    }
    if(diffOut) diffOut.innerHTML = '<span style="opacity:.85;">Calculando diff…</span>';
    const a = await loadAppsSnapshot(baseRef);
    const b = await loadAppsSnapshot(targetRef);
    if(diffOut) diffOut.innerHTML = renderAppsDiff(a, b);
  });
}

if(histClearBtn){
  histClearBtn.addEventListener('click', async ()=>{
    const ok = window.confirm('Esto borrará el historial de Apps del servidor. ¿Continuar?');
    if(!ok) return;
    status.textContent = 'Borrando historial…';
    const r = await clearAppsHistory();
    if(!r || !r.ok){
      status.textContent = 'No se pudo borrar el historial.';
      return;
    }
    status.textContent = 'Historial borrado.';
    refreshHistory();
  });
}

editor.querySelector('#mmi-apps-save').addEventListener('click', async ()=>{
        status.textContent = 'Guardando…';
        const parsed = safeJsonParse(ta.value);
        if(!parsed.ok){ status.textContent = 'JSON inválido: ' + parsed.error; return; }
        const normalized = normalizeApps(parsed.value);
        const res = await saveAppsToServer(normalized);
        if(res?.ok){
          status.textContent = 'Guardado (' + (res.count ?? normalized.length) + ')';
          refreshHistory();
          renderAppsBlock(normalized);
        }else{
          status.textContent = 'Error guardando: ' + (res?.error || 'unknown');
        }
      });

      editor.querySelector('#mmi-apps-export').addEventListener('click', async ()=>{
        status.textContent = 'Exportando…';
        const res = await exportAppsFromServer();
        if(res?.ok && typeof res.json === 'string'){
          ta.value = res.json;
          // Download
          try{
            const d = new Date();
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth()+1).padStart(2,'0');
            const dd = String(d.getDate()).padStart(2,'0');
            const filename = `mmi-apps-${yyyy}-${mm}-${dd}.json`;
            downloadJson(filename, JSON.parse(res.json));
          }catch(e){
            // If JSON parse fails, still allow manual copy from textarea.
          }
          status.textContent = 'Exportado (descargado) ' + (res.count ?? '');
        }else{
          status.textContent = 'Error exportando: ' + (res?.error || 'unknown');
        }
      });


      editor.querySelector('#mmi-apps-import').addEventListener('click', async ()=>{
        await previewAndImportApps(ta.value, 'editor');
      });

      editor.querySelector('#mmi-apps-reset').addEventListener('click', ()=>{
        ta.value = JSON.stringify(DEFAULT_APPS, null, 2);
        status.textContent = 'Defaults listos (pulsa Guardar Apps).';
      });
    }

    dash.insertBefore(block, dash.firstChild.nextSibling || dash.firstChild);
  }

  async function boot(){
    const loaded = await loadAppsFromServer();
    renderAppsBlock(loaded && loaded.length ? loaded : DEFAULT_APPS);
  }

  // Wait for dashboard
  let tries = 0;
  const t = setInterval(()=>{
    tries++;
    if(document.querySelector('#mmi-dashboard') && window.IntentFlow?.run){
      clearInterval(t);
      boot();
    }
    if(tries > 30) clearInterval(t);
  }, 300);
})();
// === END MMI Apps ===












/** ===========================
 * Scheduler (Technical)
 * =========================== */
(function(){
  if (!window.IntentFlow) return;

  const $ = (sel, ctx=document) => ctx.querySelector(sel);

  function unwrap(resp){
    // IntentFlow.run returns { ok, intentId, result: {...} }
    if (!resp) return resp;
    const r = resp.result ?? resp;
    return r.result ?? r;
  }

  function el(tag, attrs={}, children=[]){
    const n=document.createElement(tag);
    Object.entries(attrs||{}).forEach(([k,v])=>{
      if (k==='class') n.className=v;
      else if (k==='text') n.textContent=v;
      else if (k==='html') n.innerHTML=v;
      else if (k==='value') n.value=v;
      else if (k.startsWith('on') && typeof v==='function') n.addEventListener(k.slice(2), v);
      else n.setAttribute(k, String(v));
    });
    (children||[]).forEach(c=>{
      if (c==null) return;
      n.appendChild(typeof c==='string' ? document.createTextNode(c) : c);
    });
    return n;
  }

  function fmtTs(ts){
    if (!ts) return '—';
    try { return new Date(ts*1000).toLocaleString(); } catch(e){ return String(ts); }
  }

  async function api(intentId, payload){
    // Ensure bootstrap has created window.IntentFlow
    for(let i=0;i<40;i++){
      if (window.IntentFlow && typeof window.IntentFlow.run === 'function') break;
      await new Promise(r=>setTimeout(r, 50));
    }
    if (!window.IntentFlow || typeof window.IntentFlow.run !== 'function') {
      throw new Error('IntentFlow engine not ready');
    }
    const r = await window.IntentFlow.run(intentId, payload || {}, {source:'ui'});
    return unwrap(r);
  }

  function parseJsonSafe(str){
    const s = (str||'').trim();
    if (!s) return { ok:true, value:{} };
    try { return { ok:true, value: JSON.parse(s) }; }
    catch(e){ return { ok:false, error:e }; }
  }

  function mountScheduler(host){
    if (!host) return;

    // Avoid double-mount
    if (host.querySelector('[data-mmi-scheduler-pro="1"]')) return;

    const live = el('div', { class:'mmi-muted', role:'status', 'aria-live':'polite' });

    const header = el('div', { class:'mmi-row' }, [
      el('div', {}, [
        el('h3', { text:'Scheduler (UI)' }),
        el('p', { class:'mmi-muted', text:'Programa tareas (jobs) que ejecutan intents en segundo plano mediante WP-Cron. Requiere cron fiable en producción.' }),
      ]),
    ]);

    // Create form
    const fIntent = el('input', { type:'text', placeholder:'Intent (ej: server.audit.latest)', style:'min-width:280px;' });
    const fId = el('input', { type:'text', placeholder:'ID (opcional, ej: job_audit_test)', style:'min-width:220px;' });
    const fInterval = el('input', { type:'number', min:'60', step:'60', value:'300', style:'width:120px;' });
    const fPayload = el('textarea', { rows:'3', placeholder:'Payload JSON (opcional)', style:'min-width:360px;' });

    const btnCreate = el('button', { class:'button button-primary', text:'Crear job', onclick: async ()=>{
      live.textContent='Creando job…';
      const parsed = parseJsonSafe(fPayload.value);
      if (!parsed.ok) { live.textContent='Payload JSON inválido.'; fPayload.focus(); return; }
      const p = {
        id: fId.value.trim() || undefined,
        intent: fIntent.value.trim(),
        interval: Number(fInterval.value||300),
        payload: parsed.value,
      };
      const res = await api('server.scheduler.add', p);
      if (!res || res.ok === false) {
        live.textContent = 'Error creando job: ' + (res?.error || 'unknown');
        return;
      }
      live.textContent='Job creado.';
      fId.value=''; fIntent.value=''; fPayload.value='';
      await refresh();
    }});

    const form = el('div', { class:'mmi-card mmi-row', 'data-mmi-scheduler-pro':'1' }, [
      el('div', {}, [ el('label', { text:'Intent' }), fIntent ]),
      el('div', {}, [ el('label', { text:'ID (opcional)' }), fId ]),
      el('div', {}, [ el('label', { text:'Intervalo (s)' }), fInterval ]),
      el('div', {}, [ el('label', { text:'Payload' }), fPayload ]),
      el('div', {}, [ el('label', { text:' ' }), btnCreate ]),
    ]);

    // Jobs list container
    const list = el('div', { class:'mmi-card' }, [
      el('h4', { text:'Jobs' }),
      el('div', { class:'mmi-muted', text:'Se guardan en el servidor. Cada job almacena last_run/next_run y last_result.' }),
    ]);

    const tableWrap = el('div', { style:'overflow:auto; max-width:100%;' });
    list.appendChild(tableWrap);

    async function refresh(){
      const res = await api('server.scheduler.list', {});
      if (!res || res.ok === false) {
        tableWrap.innerHTML='';
        tableWrap.appendChild(el('p', { text:'Error cargando jobs: ' + (res?.error || 'unknown') }));
        return;
      }
      const jobs = res.jobs || {};
      const ids = Object.keys(jobs);

      tableWrap.innerHTML='';
      if (!ids.length) {
        tableWrap.appendChild(el('p', { text:'No hay jobs aún.' }));
        return;
      }

      const table = el('table', { class:'widefat striped', style:'min-width:960px;' });
      const thead = el('thead', {}, [
        el('tr', {}, [
          el('th', { scope:'col', text:'ID' }),
          el('th', { scope:'col', text:'Intent' }),
          el('th', { scope:'col', text:'Enabled' }),
          el('th', { scope:'col', text:'Intervalo' }),
          el('th', { scope:'col', text:'Last run' }),
          el('th', { scope:'col', text:'Next run' }),
          el('th', { scope:'col', text:'Último resultado' }),
          el('th', { scope:'col', text:'Acciones' }),
        ])
      ]);
      table.appendChild(thead);

      const tbody = el('tbody');
      table.appendChild(tbody);

      ids.forEach(id=>{
        const j = jobs[id] || {};
        const enabled = !!j.enabled;

        const enabledToggle = el('input', { type:'checkbox', checked: enabled ? 'checked' : null, 'aria-label': 'Enabled ' + id });
        const intervalInput = el('input', { type:'number', min:'60', step:'60', value: String(j.interval ?? 3600), style:'width:90px;' });
        const intentInput = el('input', { type:'text', value: String(j.intent ?? ''), style:'min-width:260px;' });
        const payloadArea = el('textarea', { rows:'2', style:'min-width:320px;' });
        payloadArea.value = JSON.stringify(j.payload ?? {}, null, 2);

        const lastOk = (j.last_result && (j.last_result.ok === true || j.last_result.ok === false)) ? j.last_result.ok : null;
        const lastBadge = lastOk === null
          ? el('span', { text:'—' })
          : el('span', { text: lastOk ? 'OK' : 'ERROR' });

        const btnSave = el('button', { class:'button', text:__("save","Save"), onclick: async ()=>{
          live.textContent='Guardando ' + id + '…';
          const parsed = parseJsonSafe(payloadArea.value);
          if (!parsed.ok) { live.textContent='Payload JSON inválido en ' + id; payloadArea.focus(); return; }
          const up = {
            id,
            intent: intentInput.value.trim(),
            enabled: enabledToggle.checked,
            interval: Number(intervalInput.value || 3600),
            payload: parsed.value,
          };
          const rr = await api('server.scheduler.update', up);
          if (!rr || rr.ok === false) {
            live.textContent='Error guardando ' + id + ': ' + (rr?.error || 'unknown');
            return;
          }
          live.textContent='Guardado ' + id + '.';
          await refresh();
        }});

        const btnDelete = el('button', { class:'button-link-delete', text:__("delete","Delete"), onclick: async ()=>{
          if (!confirm('Eliminar job "' + id + '"?')) return;
          live.textContent='Eliminando ' + id + '…';
          const rr = await api('server.scheduler.delete', { id });
          if (!rr || rr.ok === false) {
            live.textContent='Error eliminando ' + id + ': ' + (rr?.error || 'unknown');
            return;
          }
          live.textContent='Eliminado ' + id + '.';
          await refresh();
        }});

        const details = el('details', {}, [
          el('summary', { text:'Editar payload' }),
          payloadArea
        ]);

        const row = el('tr', {}, [
          el('td', {}, [ el('code', { text:id }) ]),
          el('td', {}, [ intentInput ]),
          el('td', {}, [ enabledToggle ]),
          el('td', {}, [ intervalInput, el('span', { class:'mmi-muted', text:' s' }) ]),
          el('td', { class:'mmi-muted' }, [ el('span', { text: fmtTs(j.last_run) }) ]),
          el('td', { class:'mmi-muted' }, [ el('span', { text: fmtTs(j.next_run) }) ]),
          el('td', {}, [
            lastBadge,
            j.last_result ? el('details', {}, [
              el('summary', { text:'Ver' }),
              el('pre', { style:'white-space:pre-wrap; max-width:520px;', text: JSON.stringify(j.last_result, null, 2) })
            ]) : null
          ]),
          el('td', {}, [ btnSave, el('span', { text:' ' }), btnDelete, el('div', { style:'margin-top:6px;' }, [ details ]) ]),
        ]);

        tbody.appendChild(row);
      });

      tableWrap.appendChild(table);
    }

    const wrap = el('div', { class:'mmi-card', 'data-mmi-scheduler-pro':'1' }, [
      header,
      form,
      live,
      list,
    ]);

    host.appendChild(wrap);
    refresh();
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    try{
      const profile = (localStorage.getItem('mmi_profile_v1') || '').toLowerCase();
      if (profile !== 'technical') return;

      // Best-effort mount: in your UI there is a main container.
      const host = document.querySelector('#mmi-dashboard') || document.querySelector('.wrap') || document.body;
      mountScheduler(host);
    }catch(e){}
  });
})();


/** ===========================
 * Integrations (Technical)
 * =========================== */
(function(){
  if (!window.IntentFlow) return;

  function unwrap(resp){
    return resp && resp.result ? (resp.result.result || resp.result) : resp;
  }
  function el(tag, attrs={}, children=[]){
    const n=document.createElement(tag);
    Object.entries(attrs||{}).forEach(([k,v])=>{
      if (k==='class') n.className=v;
      else if (k==='text') n.textContent=v;
      else if (k==='html') n.innerHTML=v;
      else if (k==='value') n.value=v;
      else if (k.startsWith('on') && typeof v==='function') n.addEventListener(k.slice(2).toLowerCase(), v);
      else n.setAttribute(k, v);
    });
    (children||[]).forEach(c=>{ if (c==null) return; n.appendChild(typeof c==='string'?document.createTextNode(c):c); });
    return n;
  }
  async function api(intent, payload){
    const r = await window.IntentFlow.run(intent, payload || {});
    return unwrap(r);
  }

  function typeLabel(t){
    if(t==='slack.webhook') return 'Slack (Webhook)';
    if(t==='zapier.hook') return 'Zapier (Catch Hook)';
    if(t==='http.endpoint') return 'HTTP Endpoint (Base URL)';
    return t || '';
  }

  async function mountIntegrations(host){
    if(!host) return;
    if(host.querySelector('[data-mmi-integrations="1"]')) return;

    const box = el('div', { class:'mmi-card', 'data-mmi-integrations':'1' }, [
      el('h2', { text:'Integrations (v1)' }),
      el('p', { class:'mmi-muted', text:'Conexiones reutilizables para intents: http.request, slack.send, zapier.trigger. Úsalas por connection_id en Apps/Rules/Jobs.' }),
    ]);

    const status = el('div', { class:'mmi-muted', role:'status' });
    const table = el('table', { class:'widefat striped', style:'margin-top:8px;' });
    box.appendChild(status);
    box.appendChild(table);

    // Create form
    const form = el('div', { class:'mmi-row', style:'margin-top:12px; display:flex; gap:10px; flex-wrap:wrap; align-items:end;' });
    const typeS = el('select', { style:'min-width:220px;' }, [
      el('option', { value:'slack.webhook', text:'Slack (Incoming Webhook)' }),
      el('option', { value:'zapier.hook', text:'Zapier (Catch Hook)' }),
      el('option', { value:'http.endpoint', text:'HTTP Endpoint (Base URL)' }),
    ]);
    const nameI = el('input', { type:'text', placeholder:'Nombre', style:'min-width:220px;' });
    const idI   = el('input', { type:'text', placeholder:'connection_id (opcional)', style:'min-width:220px;' });
    const urlI  = el('input', { type:'text', placeholder:'URL (webhook/hook/base)', style:'min-width:420px; flex:1;' });
    const addBtn = el('button', { class:'button button-primary', text:__("save","Save") });
    form.appendChild(el('label', { class:'mmi-field' }, [el('div',{class:'mmi-muted',text:'Tipo'}), typeS]));
    form.appendChild(el('label', { class:'mmi-field' }, [el('div',{class:'mmi-muted',text:'Nombre'}), nameI]));
    form.appendChild(el('label', { class:'mmi-field' }, [el('div',{class:'mmi-muted',text:'ID'}), idI]));
    form.appendChild(el('label', { class:'mmi-field', style:'flex:1;' }, [el('div',{class:'mmi-muted',text:'URL'}), urlI]));
    form.appendChild(addBtn);
    box.appendChild(form);

    function configFor(type, url){
      if(type==='slack.webhook') return { webhook_url: url };
      if(type==='zapier.hook') return { hook_url: url };
      if(type==='http.endpoint') return { base_url: url, headers: {} };
      return {};
    }

    async function refresh(){
      status.textContent = __('loading', 'Loading…');
      table.innerHTML = '';
      try{
        const r = await api('server.integrations.list', {});
        const items = (r && r.items) ? r.items : [];
        const thead = el('thead', {}, [el('tr', {}, [
          el('th', { text:'ID' }),
          el('th', { text:'Tipo' }),
          el('th', { text:'Nombre' }),
          el('th', { text:'Estado' }),
          el('th', { text:'Config' }),
          el('th', { text:'Acciones' }),
        ])]);
        const tbody = el('tbody');

        items.forEach(item=>{
          const enabled = !!item.enabled;
          const cfg = item.config || {};
          const cfgStr = JSON.stringify(cfg, null, 0);
          const tr = el('tr', {}, [
            el('td', {}, [ el('code', { text: item.id || '' }) ]),
            el('td', { text: typeLabel(item.type) }),
            el('td', { text: item.name || '' }),
            el('td', { html: enabled ? '<span class="mmi-pill mmi-pill-ok">ON</span>' : '<span class="mmi-pill">OFF</span>' }),
            el('td', {}, [ el('details', {}, [ el('summary',{text:'Ver'}), el('pre',{style:'white-space:pre-wrap; max-width:520px;', text: cfgStr }) ]) ]),
            el('td', {}, []),
          ]);

          const actions = tr.lastChild;
          const toggleBtn = el('button', { class:'button', text: enabled ? 'Desactivar' : 'Activar', onClick: async ()=>{
            await api('server.integrations.save', { id:item.id, type:item.type, name:item.name, enabled: !enabled, config: cfg });
            refresh();
          }});
          const delBtn = el('button', { class:'button button-link-delete', text:__("delete","Delete"), onClick: async ()=>{
            if(!confirm('¿Eliminar conexión?')) return;
            await api('server.integrations.delete', { id:item.id });
            refresh();
          }});
          actions.appendChild(toggleBtn);
          actions.appendChild(document.createTextNode(' '));
          actions.appendChild(delBtn);
          tbody.appendChild(tr);
        });

        table.appendChild(thead);
        table.appendChild(tbody);
        status.textContent = items.length ? '' : 'Sin conexiones aún.';
      }catch(e){
        status.textContent = 'Error cargando integrations.';
        console.error(e);
      }
    }

    addBtn.addEventListener('click', async ()=>{
      const type = typeS.value;
      const name = (nameI.value||'').trim();
      const id = (idI.value||'').trim();
      const url = (urlI.value||'').trim();
      if(!name){ alert('Pon un nombre.'); return; }
      if(!url){ alert('Pon la URL.'); return; }
      addBtn.disabled = true;
      try{
        await api('server.integrations.save', { id: id || undefined, type, name, enabled:true, config: configFor(type, url) });
        nameI.value=''; idI.value=''; urlI.value='';
        await refresh();
      }finally{ addBtn.disabled = false; }
    });

    host.appendChild(box);
    refresh();
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    try{
      const profile = (localStorage.getItem('mmi_profile_v1') || '').toLowerCase();
      if (profile !== 'technical') return;
      const host = document.querySelector('#mmi-dashboard') || document.querySelector('.wrap') || document.body;
      mountIntegrations(host);
    }catch(e){}
  });
})();



/** ===========================
 * Scheduler (UI) Mount (anchor)
 * =========================== */
(function(){
  function unwrap(resp){
    return resp && resp.result ? (resp.result.result || resp.result) : resp;
  }
  function esc(s){ return String(s||'').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m])); }
  function fmtTs(ts){
    if(!ts) return '—';
    try { return new Date(ts*1000).toLocaleString(); } catch(e){ return String(ts); }
  }
  function parseJsonOrEmpty(txt){
    const t = (txt||'').trim();
    if(!t) return {};
    return JSON.parse(t);
  }

  function mount(container){
    if(!container) return;
    if(!window.IntentFlow || typeof window.IntentFlow.run !== 'function'){
      container.innerHTML = '<p class="mmi-muted">IntentFlow no disponible.</p>';
      return;
    }

    const status = document.createElement('div');
    status.className = 'mmi-muted';
    status.setAttribute('role','status');
    status.setAttribute('aria-live','polite');

    const form = document.createElement('div');
    form.className = 'mmi-row';

    const id = document.createElement('input'); id.placeholder='ID (opcional, ej: job_ui_test)';
    id.style.minWidth='220px';

    const intent = document.createElement('input'); intent.placeholder='Intent (ej: server.audit.latest)';
    intent.style.minWidth='260px';

    const interval = document.createElement('input'); interval.type='number'; interval.min='30'; interval.step='30'; interval.value='120';
    interval.style.width='110px';

    const payload = document.createElement('textarea'); payload.rows=3; payload.placeholder='Payload JSON (opcional)';
    payload.style.minWidth='320px';

    const btnCreate = document.createElement('button');
    btnCreate.className='button button-primary';
    btnCreate.textContent='Crear job';

    const btnRefresh = document.createElement('button');
    btnRefresh.className='button';
    btnRefresh.textContent='Recargar';

    form.append(id,intent,interval,payload,btnCreate,btnRefresh);

    const tableWrap = document.createElement('div');

    container.append(form,status,tableWrap);

    async function api(intentId, payloadObj){
      const r = await window.IntentFlow.run(intentId, payloadObj||{}, {source:'scheduler-ui'});
      return unwrap(r);
    }

    let serverNow = Math.floor(Date.now()/1000);

    function statusBadge(j){
      const enabled = !!j.enabled;
      const next = Number(j.next_run||0);
      const overdue = enabled && next && next < (serverNow - 30);
      const st = String(j.last_status || '').toLowerCase();
      if (!enabled) return '<span class="mmi-muted">OFF</span>';
      if (overdue) return '<strong>OVERDUE</strong>';
      if (st === 'ok') return '<span>OK</span>';
      if (st === 'error') return '<span>ERROR</span>';
      return '<span class="mmi-muted">—</span>';
    }

    function renderTable(jobs){
      const keys = Object.keys(jobs||{});
      if(!keys.length){
        tableWrap.innerHTML = '<p class="mmi-muted">No hay jobs aún.</p>';
        return;
      }
      let html = '<table class="widefat striped"><thead><tr>'
        + '<th>ID</th><th>Estado</th><th>Enabled</th><th>Intent</th><th>Interval</th><th>Last run</th><th>Next run</th><th>Acciones</th></tr></thead><tbody>';
      for(const k of keys){
        const j = jobs[k] || {};
        html += '<tr data-job="'+esc(k)+'">'
          + '<td><code>'+esc(k)+'</code></td>'
          + '<td>'+statusBadge(j)+'</td>'
          + '<td><label><input type="checkbox" class="mmi-job-enabled" '+(j.enabled?'checked':'')+'/> <span class="screen-reader-text">Enabled</span></label></td>'
          + '<td><input class="mmi-job-intent" value="'+esc(j.intent||'')+'" style="min-width:240px"/></td>'
          + '<td><input type="number" class="mmi-job-interval" min="30" step="30" value="'+esc(j.interval||0)+'" style="width:90px"/></td>'
          + '<td>'+esc(fmtTs(j.last_run))+'</td>'
          + '<td>'+esc(fmtTs(j.next_run))+'</td>'
          + '<td>'
          + '<button class="button mmi-job-run">Run now</button> '
          + '<button class="button mmi-job-save">Guardar</button> '
          + '<button class="button-link-delete mmi-job-del">Eliminar</button>'
          + '<details class="mmi-job-runs" style="margin-top:6px;"><summary>Runs</summary><div class="mmi-muted">Cargando…</div></details>'
          + '</td>'
          + '</tr>';
      }
      html += '</tbody></table>';
      tableWrap.innerHTML = html;

      // Run now
      tableWrap.querySelectorAll('button.mmi-job-run').forEach(btn=>{
        btn.addEventListener('click', async (e)=>{
          const tr = e.target.closest('tr[data-job]');
          const jobId = tr.getAttribute('data-job');
          status.textContent = 'Ejecutando ' + jobId + '…';
          const res = await api('server.scheduler.run_job', { id: jobId });
          status.textContent = res && res.ok !== false ? 'Ejecutado.' : ('Error: '+(res?.error||'unknown'));
          await refresh();
        });
      });

      tableWrap.querySelectorAll('button.mmi-job-save').forEach(btn=>{
        btn.addEventListener('click', async (e)=>{
          const tr = e.target.closest('tr[data-job]');
          const jobId = tr.getAttribute('data-job');
          const en = tr.querySelector('.mmi-job-enabled')?.checked ? true : false;
          const inId = tr.querySelector('.mmi-job-intent')?.value || '';
          const iv = Number(tr.querySelector('.mmi-job-interval')?.value || 0);
          status.textContent = 'Guardando…';
          const res = await api('server.scheduler.update', { id: jobId, job: { enabled: en, intent: inId, interval: iv } });
          status.textContent = res && res.ok !== false ? 'Guardado.' : ('Error: '+(res?.error||'unknown'));
          await refresh();
        });
      });

      tableWrap.querySelectorAll('button.mmi-job-del').forEach(btn=>{
        btn.addEventListener('click', async (e)=>{
          const tr = e.target.closest('tr[data-job]');
          const jobId = tr.getAttribute('data-job');
          if(!confirm('Eliminar job '+jobId+'?')) return;
          status.textContent = 'Eliminando…';
          const res = await api('server.scheduler.delete', { id: jobId });
          status.textContent = res && res.ok !== false ? 'Eliminado.' : ('Error: '+(res?.error||'unknown'));
          await refresh();
        });
      });

      // Runs viewer (lazy)
      tableWrap.querySelectorAll('details.mmi-job-runs').forEach(d=>{
        d.addEventListener('toggle', async (e)=>{
          if(!d.open) return;
          const tr = d.closest('tr[data-job]');
          const jobId = tr.getAttribute('data-job');
          const box = d.querySelector('div');
          box.textContent = __('loading', 'Loading…');
          const res = await api('server.scheduler.runs.list', { id: jobId, limit: 20 });
          if(!res || res.ok === false){
            box.textContent = 'Error: ' + (res?.error||'unknown');
            return;
          }
          const runs = res.runs || [];
          if(!runs.length){
            box.innerHTML = '<em>No hay runs aún.</em>';
            return;
          }
          let out = '<div style="display:flex; gap:8px; align-items:center; margin:6px 0;">'
            + '<button class="button mmi-job-runs-refresh">Recargar</button>'
            + '<button class="button mmi-job-runs-clear">Borrar historial</button>'
            + '</div>';
          out += '<div style="max-height:260px; overflow:auto; border:1px solid #ddd; padding:6px;">';
          out += '<table class="widefat striped" style="margin:0; min-width:760px;"><thead><tr><th>Cuando</th><th>Duración</th><th>OK</th><th>Error</th><th>Detalle</th></tr></thead><tbody>';
          for(const r of runs){
            const ok = !!r.ok;
            out += '<tr>'
              + '<td>'+esc(fmtTs(r.started_at))+'</td>'
              + '<td>'+esc(String(r.duration_ms||0))+' ms</td>'
              + '<td>'+(ok?'OK':'ERROR')+'</td>'
              + '<td>'+esc(r.error||'')+'</td>'
              + '<td><details><summary>Ver</summary><pre style="white-space:pre-wrap;">'+esc(JSON.stringify(r.result||r, null, 2))+'</pre></details></td>'
              + '</tr>';
          }
          out += '</tbody></table></div>';
          box.innerHTML = out;

          box.querySelector('.mmi-job-runs-refresh')?.addEventListener('click', async (ev)=>{
            ev.preventDefault();
            d.open = false; // force reload
            d.open = true;
          });
          box.querySelector('.mmi-job-runs-clear')?.addEventListener('click', async (ev)=>{
            ev.preventDefault();
            if(!confirm('Borrar historial de runs de '+jobId+'?')) return;
            const rr = await api('server.scheduler.runs.clear', { id: jobId });
            if(!rr || rr.ok === false){
              status.textContent = 'Error: ' + (rr?.error||'unknown');
              return;
            }
            status.textContent = 'Historial borrado.';
            d.open = false;
            d.open = true;
          });
        });
      });
    }

    async function refresh(){
      const res = await api('server.scheduler.list', {});
      if(!res || res.ok === false){
        status.textContent = 'Error cargando: ' + (res?.error || 'unknown');
        tableWrap.innerHTML = '';
        return;
      }
      serverNow = Number(res.now || Math.floor(Date.now()/1000));
      renderTable(res.jobs || {});
      status.textContent = 'Scheduler cargado.';
    }

    btnRefresh.addEventListener('click', (e)=>{ e.preventDefault(); refresh(); });

    btnCreate.addEventListener('click', async (e)=>{
      e.preventDefault();
      status.textContent='Creando…';
      let payloadObj = {};
      try { payloadObj = parseJsonOrEmpty(payload.value); } catch(err){
        status.textContent='Payload JSON inválido.';
        return;
      }
      const payloadReq = {
        id: (id.value||'').trim() || undefined,
        intent: (intent.value||'').trim(),
        payload: payloadObj,
        interval: Number(interval.value||120)
      };
      const res = await api('server.scheduler.add', payloadReq);
      status.textContent = res && res.ok !== false ? 'Job creado.' : ('Error: '+(res?.error||'unknown'));
      await refresh();
    });

    refresh();
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    const body = document.getElementById('mmi-scheduler-ui-body');
    if(body){
      try{
        mount(body);
        window.MMI_SCHEDULER_UI_LOADED = true;
      }catch(e){
        window.MMI_SCHEDULER_UI_LOADED = false;
        // eslint-disable-next-line no-console
        console.error('MMI Scheduler UI mount error', e);
      }
    }
  });
})();


/** ===========================
 * Events / Triggers (Technical)
 * =========================== */
// Events module (global scope)
(function(){

  function unwrap(resp){
    return resp && resp.result ? (resp.result.result || resp.result) : resp;
  }
  function el(tag, attrs={}, children=[]){
    const n=document.createElement(tag);
    Object.entries(attrs||{}).forEach(([k,v])=>{
      if (k==='class') n.className=v;
      else if (k==='text') n.textContent=v;
      else if (k==='html') n.innerHTML=v;
      else if (k==='value') n.value=v;
      else if (k.startsWith('on') && typeof v==='function') n.addEventListener(k.slice(2), v);
      else if (v !== null && v !== undefined) n.setAttribute(k, String(v));
    });
    (Array.isArray(children)?children:[children]).forEach(c=>{
      if(c==null) return;
      n.appendChild(typeof c==='string' ? document.createTextNode(c) : c);
    });
    return n;
  }
  function parseJsonSafe(str){
    const s=(str||'').trim();
    if(!s) return {ok:true,value:{}};
    try{ return {ok:true,value:JSON.parse(s)}; }catch(e){ return {ok:false,error:e}; }
  }

  async function api(intentId, payload){
    const r = await window.IntentFlow.run(intentId, payload || {}, {source:'events.ui'});
    return unwrap(r);
  }

  window.mountEvents = function mountEvents(host){
    if(!host) return;
    if(host.querySelector('[data-mmi-events="1"]')) return;

    const live = el('div', { class:'mmi-muted', role:'status', 'aria-live':'polite', style:'margin:8px 0;min-height:20px;' });

    // Tooltip helper
    function tip(text){ return el('span', { class:'mmi-tip', title:text, 'aria-label':text, style:'display:inline-block;margin-left:4px;cursor:help;color:#888;font-size:13px;', text:'ⓘ' }); }

    // Header con explicación
    const header = el('div', { class:'mmi-card', style:'border-left:4px solid #2271b1; background:#f0f6fc;' }, [
      el('h3', { text:'⚡ Events — Automatizaciones por evento', style:'margin-top:0;' }),
      el('p', { style:'margin:0 0 8px;', text:'Conecta lo que pasa en WordPress (trigger) con acciones automáticas. Ejemplo: "cuando un usuario se registra → envía un email de bienvenida".' }),
      el('p', { class:'mmi-muted', style:'margin:0;font-size:13px;', text:'💡 Pasos: 1) Elige un evento de la lista. 2) Elige una acción. 3) Guarda la regla. ¡Listo!' }),
    ]);

    // Selectores de evento y acción
    const fEvent  = el('select', { style:'min-width:280px;' });
    const fAction = el('select', { style:'min-width:280px;' });
    const fPayload = el('textarea', { rows:'4', placeholder:'Variables opcionales en JSON.\nEjemplo: {"asunto": "Bienvenido {{payload.user_login}}"}\nPuedes usar {{payload.X}} o {{meta.X}} para datos del evento.', style:'width:100%;max-width:600px;font-family:monospace;font-size:13px;' });

    // Cargar eventos del catálogo
    async function loadEventOptions(){
      fEvent.innerHTML = '<option value="">Cargando eventos…</option>';
      try {
        const r = await api('server.events.catalog', {});
        const items = r && r.items ? r.items : [];
        fEvent.innerHTML = '<option value="">— Elige un evento (trigger) —</option>';
        const groups = {};
        items.forEach(e => { const g = e.group||'other'; if(!groups[g]) groups[g]=[]; groups[g].push(e); });
        Object.keys(groups).sort().forEach(g => {
          const og = document.createElement('optgroup');
          og.label = g.charAt(0).toUpperCase() + g.slice(1);
          groups[g].forEach(e => {
            const o = document.createElement('option');
            o.value = e.id; o.textContent = e.label + ' (' + e.id + ')';
            o.title = e.description || '';
            og.appendChild(o);
          });
          fEvent.appendChild(og);
        });
      } catch(e){ fEvent.innerHTML = '<option value="">Error cargando eventos</option>'; }
    }

    // Cargar acciones del catálogo
    async function loadActionOptions(){
      fAction.innerHTML = '<option value="">Cargando acciones…</option>';
      try {
        const r = await api('server.actions.list', {});
        const items = r && r.items ? Object.values(r.items) : [];
        fAction.innerHTML = '<option value="">— Elige una acción —</option>';
        const groups = {};
        items.forEach(a => { const g = a.integration||'other'; if(!groups[g]) groups[g]=[]; groups[g].push(a); });
        Object.keys(groups).sort().forEach(g => {
          const og = document.createElement('optgroup');
          og.label = g.charAt(0).toUpperCase() + g.slice(1);
          groups[g].forEach(a => {
            const o = document.createElement('option');
            o.value = a.intent||a.id; o.textContent = a.label;
            o.title = a.description || '';
            og.appendChild(o);
          });
          fAction.appendChild(og);
        });
      } catch(e){ fAction.innerHTML = '<option value="">Error cargando acciones</option>'; }
    }

    // Mostrar campos de la acción seleccionada
    const fieldHints = el('div', { style:'margin-top:6px;font-size:12px;color:#666;' });
    fAction.addEventListener('change', async () => {
      fieldHints.innerHTML = '';
      const intent = fAction.value;
      if(!intent) return;
      try {
        const r = await api('server.actions.list', {});
        const items = r && r.items ? r.items : {};
        const action = Object.values(items).find(a => (a.intent||a.id) === intent);
        if(!action || !action.fields) return;
        const fields = action.fields;
        const lines = Object.entries(fields).map(([k,f]) => {
          const req = f.required ? ' (requerido)' : ' (opcional)';
          return k + req + ' — ' + (f.label||k);
        });
        fieldHints.innerHTML = '<strong>Campos para el payload JSON:</strong><br><code style="font-size:12px;">' + lines.join('<br>') + '</code>';
        if(!fPayload.value.trim()){
          const example = {};
          Object.entries(fields).forEach(([k,f]) => { if(f.required) example[k] = ''; });
          if(Object.keys(example).length) fPayload.value = JSON.stringify(example, null, 2);
        }
      } catch(e){}
    });

    // Mostrar descripción del evento seleccionado
    const eventHint = el('div', { style:'margin-top:4px;font-size:12px;color:#666;' });
    fEvent.addEventListener('change', async () => {
      eventHint.innerHTML = '';
      const evId = fEvent.value;
      if(!evId) return;
      try {
        const r = await api('server.events.catalog', {});
        const ev = (r.items||[]).find(e => e.id === evId);
        if(!ev) return;
        let html = '<em>' + (ev.description||'') + '</em>';
        if(ev.example_payload) html += '<br><strong>Datos disponibles:</strong> <code>' + Object.keys(ev.example_payload).map(k=>'{{payload.'+k+'}}').join(', ') + '</code>';
        eventHint.innerHTML = html;
      } catch(e){}
    });

    function parseJsonSafe(str){
      const s=(str||'').trim();
      if(!s) return {ok:true,value:{}};
      try{ return {ok:true,value:JSON.parse(s)}; }catch(e){ return {ok:false,error:e}; }
    }

    const btnAdd = el('button', { class:'button button-primary', text:'➕ Guardar regla', onclick: async ()=>{
      live.textContent='Guardando regla…'; live.style.color='#2271b1';
      const ev = (fEvent.value||'').trim();
      const intent = (fAction.value||'').trim();
      if(!ev){ live.textContent='⚠ Elige un evento.'; live.style.color='#d63638'; return; }
      if(!intent){ live.textContent='⚠ Elige una acción.'; live.style.color='#d63638'; return; }
      const parsed = parseJsonSafe(fPayload.value);
      if(!parsed.ok){ live.textContent='⚠ El JSON del payload no es válido.'; live.style.color='#d63638'; fPayload.focus(); return; }
      const res = await api('server.events.rules.save', { event: ev, rule: { type:'intent', intent, payload: parsed.value, enabled: true } });
      if(!res || res.ok === false){ live.textContent='❌ Error: ' + (res?.error||'unknown'); live.style.color='#d63638'; return; }
      live.textContent='✅ Regla guardada correctamente.'; live.style.color='#00a32a';
      fPayload.value=''; fAction.value=''; fieldHints.innerHTML=''; eventHint.innerHTML='';
      await refresh();
    }});

    const btnTest = el('button', { class:'button', text:'🧪 Test emit', style:'margin-left:8px;', onclick: async ()=>{
      const ev = (fEvent.value||'').trim();
      if(!ev){ live.textContent='⚠ Elige un evento para testear.'; live.style.color='#d63638'; return; }
      live.textContent='Emitiendo ' + ev + '…'; live.style.color='#2271b1';
      const parsed = parseJsonSafe(fPayload.value);
      const res = await api('server.events.emit', { event: ev, payload: parsed.value||{}, meta:{ source:'events.ui.test' } });
      if(!res || res.ok === false){ live.textContent='❌ Error: ' + (res?.error||'unknown'); live.style.color='#d63638'; return; }
      const n = res.dispatched?.length ?? 0;
      live.textContent='✅ Emit OK — ' + n + ' regla(s) ejecutada(s).'; live.style.color = n>0?'#00a32a':'#888';
      try{ const pre = host.querySelector('#mmi-events-last'); if(pre) pre.textContent = JSON.stringify(res,null,2); }catch(e){}
    }});

    const form = el('div', { class:'mmi-card', 'data-mmi-events':'1' }, [
      el('h4', { style:'margin-top:0;', text:'Nueva regla' }),
      el('table', { style:'border-collapse:collapse;width:100%;max-width:700px;' }, [
        el('tbody', {}, [
          el('tr', {}, [
            el('td', { style:'padding:8px 12px 8px 0;vertical-align:top;font-weight:500;white-space:nowrap;' }, [
              el('span', { text:'Evento (trigger) ' }),
              tip('El evento que dispara la automatización. Ej: "user.registered" cuando alguien se registra.'),
            ]),
            el('td', { style:'padding:8px 0;' }, [ fEvent, eventHint ]),
          ]),
          el('tr', {}, [
            el('td', { style:'padding:8px 12px 8px 0;vertical-align:top;font-weight:500;white-space:nowrap;' }, [
              el('span', { text:'Acción ' }),
              tip('Lo que se ejecuta cuando ocurre el evento. Ej: "wp.email.send" para enviar un email.'),
            ]),
            el('td', { style:'padding:8px 0;' }, [ fAction, fieldHints ]),
          ]),
          el('tr', {}, [
            el('td', { style:'padding:8px 12px 8px 0;vertical-align:top;font-weight:500;white-space:nowrap;' }, [
              el('span', { text:'Payload (JSON) ' }),
              tip('Datos que se pasan a la acción. Puedes usar {{payload.user_login}} para insertar datos del evento.'),
            ]),
            el('td', { style:'padding:8px 0;' }, [ fPayload ]),
          ]),
          el('tr', {}, [
            el('td', {}),
            el('td', { style:'padding:8px 0;' }, [ btnAdd, btnTest ]),
          ]),
        ]),
      ]),
    ]);

    // Lista de reglas existentes
    const tableWrap = el('div', { style:'overflow:auto;max-width:100%;' });
    const last = el('pre', { id:'mmi-events-last', style:'white-space:pre-wrap;max-width:100%;margin-top:10px;font-size:12px;', text:'' });
    const list = el('div', { class:'mmi-card', 'data-mmi-events':'1' }, [
      el('h4', { style:'margin-top:0;', text:'Reglas activas' }),
      el('p', { class:'mmi-muted', style:'margin:0 0 8px;font-size:13px;', text:'Aquí aparecen todas las automatizaciones configuradas. Puedes activarlas/desactivarlas o eliminarlas.' }),
      tableWrap,
      el('details', { style:'margin-top:8px;' }, [ el('summary', { text:'Debug — último emit', style:'cursor:pointer;color:#888;font-size:13px;' }), last ]),
    ]);

    async function refresh(){
      const res = await api('server.events.rules.list', {});
      if(!res || res.ok === false){
        tableWrap.innerHTML='';
        tableWrap.appendChild(el('p',{text:'❌ Error cargando reglas: ' + (res?.error||'unknown')}));
        return;
      }
      const rules = res.rules || {};
      const events = Object.keys(rules).sort();
      tableWrap.innerHTML='';
      if(!events.length){
        tableWrap.appendChild(el('p', { style:'color:#888;font-style:italic;', text:__("no_rules","No rules yet. Create the first one above 👆") }));
        return;
      }
      const table = el('table', { class:'widefat striped', style:'min-width:860px;' });
      table.appendChild(el('thead', {}, [
        el('tr', {}, [
          el('th', { text:'Evento' }),
          el('th', { text:'Acción (intent)' }),
          el('th', { text:'Activa' }),
          el('th', { text:'Payload' }),
          el('th', { text:'' }),
        ])
      ]));
      const tbody = el('tbody');
      events.forEach(ev => {
        const rList = Array.isArray(rules[ev]) ? rules[ev] : [];
        rList.forEach(rule => {
          const rid = rule?.id || '';
          const enabled = !!rule?.enabled;
          const intent = rule?.intent || rule?.target?.id || '';
          const payload = rule?.payload || rule?.input || {};
          const toggle = el('input', { type:'checkbox', title:'Activar/desactivar esta regla', 'aria-label':'Activar regla ' + rid });
          if(enabled) toggle.setAttribute('checked', 'checked');
          toggle.checked = enabled;
          toggle.addEventListener('change', async ()=>{
            live.textContent='Actualizando…'; live.style.color='#2271b1';
            const rr = await api('server.events.rules.save', { event: ev, rule: { ...rule, enabled: toggle.checked } });
            if(!rr || rr.ok===false){ live.textContent='❌ Error: '+(rr?.error||'unknown'); live.style.color='#d63638'; return; }
            live.textContent='✅ Actualizado.'; live.style.color='#00a32a';
            await refresh();
          });
          const btnDel = el('button', { class:'button-link-delete', text:__("delete","Delete"), onclick: async ()=>{
            if(!confirm('¿Eliminar esta regla?\n\nEvento: ' + ev + '\nAcción: ' + intent)) return;
            live.textContent='Eliminando…'; live.style.color='#2271b1';
            const rr = await api('server.events.rules.delete', { event: ev, rule_id: rid });
            if(!rr||rr.ok===false){ live.textContent='❌ Error: '+(rr?.error||'unknown'); live.style.color='#d63638'; return; }
            live.textContent='✅ Regla eliminada.'; live.style.color='#00a32a';
            await refresh();
          }});
          tbody.appendChild(el('tr', {}, [
            el('td', {}, [ el('code', { text: ev }) ]),
            el('td', {}, [ el('code', { text: intent }) ]),
            el('td', {}, [ toggle ]),
            el('td', {}, [ el('details', {}, [
              el('summary', { text:'Ver', style:'cursor:pointer;color:#888;font-size:13px;' }),
              el('pre', { style:'white-space:pre-wrap;max-width:400px;font-size:12px;', text: JSON.stringify(payload,null,2) })
            ]) ]),
            el('td', {}, [ btnDel ]),
          ]));
        });
      });
      table.appendChild(tbody);
      tableWrap.appendChild(table);
    }

    const wrap = el('div', { 'data-mmi-events':'1' }, [ header, live, form, list ]);
    host.appendChild(wrap);

    // Cargar datos
    loadEventOptions();
    loadActionOptions();
    refresh();
  }

  function unwrap(resp){
    return resp && resp.result ? (resp.result.result || resp.result) : resp;
  }
  function el(tag, attrs={}, children=[]){
    const n=document.createElement(tag);
    Object.entries(attrs||{}).forEach(([k,v])=>{
      if (k==='class') n.className=v;
      else if (k==='text') n.textContent=v;
      else if (k==='html') n.innerHTML=v;
      else if (k==='value') n.value=v;
      else if (k.startsWith('on') && typeof v==='function') n.addEventListener(k.slice(2).toLowerCase(), v);
      else n.setAttribute(k, v);
    });
    (children||[]).forEach(c=>{ if (c==null) return; n.appendChild(typeof c==='string'?document.createTextNode(c):c); });
    return n;
  }

  async function api(intent, payload){
    const r = await window.IntentFlow.run(intent, payload || {});
    return unwrap(r);
  }

  function maskSecret(secret){
    if(!secret) return '';
    if(secret.length <= 8) return '********';
    return secret.slice(0,4) + '…' + secret.slice(-4);
  }

  async function mountWebhooks(host){
    if(!host) return;
    if(host.querySelector('[data-mmi-webhooks="1"]')) return;

    const box = el('div', { class:'mmi-card', 'data-mmi-webhooks':'1' }, [
      el('h2', { text:'Webhooks (v1)' }),
      el('p', { class:'mmi-muted', text:'Endpoints públicos firmados (HMAC SHA-256). Emiten el evento "webhook.received".' }),
    ]);

    const status = el('div', { class:'mmi-muted', role:'status' });
    box.appendChild(status);

    const table = el('table', { class:'widefat striped' });
    box.appendChild(table);

    const form = el('div', { class:'mmi-row', style:'margin-top:12px; display:flex; gap:8px; flex-wrap:wrap; align-items:end;' });
    const nameI = el('input', { type:'text', placeholder:'Nombre', style:'min-width:220px;' });
    const idI = el('input', { type:'text', placeholder:'id (opcional, ej: wh_github)', style:'min-width:220px;' });
    const addBtn = el('button', { class:'button button-primary', text:'Crear webhook' });
    form.appendChild(el('label', { class:'mmi-field' }, [el('div',{class:'mmi-muted',text:'Nombre'}), nameI]));
    form.appendChild(el('label', { class:'mmi-field' }, [el('div',{class:'mmi-muted',text:'ID'}), idI]));
    form.appendChild(addBtn);
    box.appendChild(form);

    async function refresh(){
      status.textContent = __('loading', 'Loading…');
      table.innerHTML = '';
      try{
        const r = await api('server.webhooks.list', {});
        const items = (r && r.items) ? r.items : (r && r.result && r.result.items) ? r.result.items : [];
        const thead = el('thead', {}, [el('tr', {}, [
          el('th', { text:'ID' }),
          el('th', { text:'Nombre' }),
          el('th', { text:'Estado' }),
          el('th', { text:'URL' }),
          el('th', { text:'Secret' }),
          el('th', { text:'Acciones' }),
        ])]);
        const tbody = el('tbody');

        items.forEach(item=>{
          const enabled = !!item.enabled;
          const url = item.url || '';
          const tr = el('tr', {}, [
            el('td', { text: item.id || '' }),
            el('td', { text: item.name || '' }),
            el('td', { html: enabled ? '<span class="mmi-pill mmi-pill-ok">ON</span>' : '<span class="mmi-pill">OFF</span>' }),
            el('td', {}, [
              el('input', { type:'text', value:url, readonly:'readonly', style:'width:100%; min-width:320px;' })
            ]),
            el('td', {}, [
              el('code', { text: maskSecret(item.secret) })
            ]),
            el('td', {}, [])
          ]);

          const actions = tr.lastChild;
          const toggleBtn = el('button', { class:'button', text: enabled ? 'Desactivar' : 'Activar', onClick: async ()=>{
            await api('server.webhooks.save', { id:item.id, enabled: !enabled, name:item.name });
            refresh();
          }});
          const rotateBtn = el('button', { class:'button', text:'Rotar secret', onClick: async ()=>{
            if(!confirm('¿Rotar el secret? Los clientes deberán actualizar la firma.')) return;
            await api('server.webhooks.rotate_secret', { id:item.id });
            refresh();
          }});
          const delBtn = el('button', { class:'button button-link-delete', text:__("delete","Delete"), onClick: async ()=>{
            if(!confirm('¿Eliminar webhook?')) return;
            await api('server.webhooks.delete', { id:item.id });
            refresh();
          }});

          actions.appendChild(toggleBtn);
          actions.appendChild(document.createTextNode(' '));
          actions.appendChild(rotateBtn);
          actions.appendChild(document.createTextNode(' '));
          actions.appendChild(delBtn);

          tbody.appendChild(tr);
        });

        table.appendChild(thead);
        table.appendChild(tbody);
        status.textContent = items.length ? '' : 'Sin webhooks aún.';
      }catch(e){
        status.textContent = 'Error cargando webhooks.';
        console.error(e);
      }
    }

    addBtn.addEventListener('click', async ()=>{
      const name = (nameI.value || '').trim();
      const id = (idI.value || '').trim();
      if(!name){
        alert('Pon un nombre.');
        return;
      }
      addBtn.disabled = true;
      try{
        await api('server.webhooks.save', { id: id || undefined, name, enabled:true });
        nameI.value='';
        idI.value='';
        await refresh();
      }finally{
        addBtn.disabled = false;
      }
    });

    host.appendChild(box);
    refresh();
  }

  document.addEventListener('DOMContentLoaded', () => {
    try{
      const host = document.querySelector('.mmi-card') || document.querySelector('.wrap') || document.body;
      mountWebhooks(host);
    }catch(e){}
  });
})();


/** ===========================
 * Actions Catalog (Technical)
 * =========================== */
(function(){
  if (!window.IntentFlow) return;

  function unwrap(resp){
    return resp && resp.result ? (resp.result.result || resp.result) : resp;
  }
  function el(tag, attrs={}, children=[]){
    const n=document.createElement(tag);
    Object.entries(attrs||{}).forEach(([k,v])=>{
      if (k==='class') n.className=v;
      else if (k==='text') n.textContent=v;
      else if (k==='html') n.innerHTML=v;
      else if (k==='value') n.value=v;
      else if (k.startsWith('on') && typeof v==='function') n.addEventListener(k.slice(2).toLowerCase(), v);
      else n.setAttribute(k, v);
    });
    (children||[]).forEach(c=>{ if (c==null) return; n.appendChild(typeof c==='string'?document.createTextNode(c):c); });
    return n;
  }
  async function api(intent, payload){
    const r = await window.IntentFlow.run(intent, payload || {});
    return unwrap(r);
  }

  function groupByIntegration(items){
    const g = {};
    Object.values(items||{}).forEach(a=>{
      const key = (a.integration||'other');
      g[key] = g[key] || [];
      g[key].push(a);
    });
    Object.values(g).forEach(arr=>arr.sort((x,y)=>String(x.label||x.id).localeCompare(String(y.label||y.id))));
    return g;
  }

  async function mountActions(host){
    if(!host) return;
    if(host.querySelector('[data-mmi-actions="1"]')) return;

    const box = el('div', { class:'mmi-card', 'data-mmi-actions':'1' }, [
      el('h2', { text:'Actions Catalog (v1)' }),
      el('p', { class:'mmi-muted', text:'Acciones autodescriptivas (UX) que ejecutan intents vía Runner/Target. Sirve como base para editor visual tipo Uncanny.' })
    ]);

    const status = el('div', { class:'mmi-muted', role:'status' });
    const body = el('div');
    box.appendChild(status);
    box.appendChild(body);

    async function refresh(){
      status.textContent = 'Cargando acciones…';
      body.innerHTML = '';
      try{
        const r = await api('server.actions.list', {});
        const items = r && r.items ? r.items : {};
        const groups = groupByIntegration(items);
        const keys = Object.keys(groups).sort();

        if(!keys.length){
          status.textContent = 'No hay acciones registradas.';
          return;
        }
        status.textContent = '';

        keys.forEach(key=>{
          const section = el('div', { style:'margin-top:10px;' }, [
            el('h3', { text: key }),
          ]);

          const table = el('table', { class:'widefat striped', style:'margin-top:6px;' });
          table.appendChild(el('thead', {}, [el('tr', {}, [
            el('th', { text:'Action' }),
            el('th', { text:'Intent' }),
            el('th', { text:'Descripción' }),
            el('th', { text:'Probar' }),
          ])]));

          const tbody = el('tbody');
          groups[key].forEach(a=>{
            const btn = el('button', { class:'button', text:'Test', onClick: async ()=>{
              const defInput = {};
              // Prefill required fields with empty strings.
              const fields = a.fields || {};
              Object.keys(fields).forEach(f=>{ defInput[f] = defInput[f] ?? ''; });
              const raw = prompt('Input JSON para ' + a.id + '\n(Ej: {"connection_id":"slack_main","text":"Hola"})', JSON.stringify(defInput, null, 2));
              if(raw==null) return;
              let input = {};
              try{ input = raw.trim() ? JSON.parse(raw) : {}; }catch(e){ alert('JSON inválido'); return; }

              btn.disabled = true;
              btn.textContent = 'Ejecutando…';
              try{
                const rr = await api('server.actions.execute', { action_id: a.id, input });
                alert('Resultado:\n' + JSON.stringify(rr, null, 2));
              }finally{
                btn.disabled = false;
                btn.textContent = 'Test';
              }
            }});

            const tr = el('tr', {}, [
              el('td', {}, [ el('code', { text: a.id }) , el('div', { class:'mmi-muted', text: a.label || '' }) ]),
              el('td', {}, [ el('code', { text: a.intent || '' }) ]),
              el('td', { class:'mmi-muted', text: a.description || '' }),
              el('td', {}, [ btn ]),
            ]);
            tbody.appendChild(tr);
          });

          table.appendChild(tbody);
          section.appendChild(table);
          body.appendChild(section);
        });

      }catch(e){
        console.error(e);
        status.textContent = 'Error cargando acciones.';
      }
    }

    host.appendChild(box);
    refresh();
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    try{
      const profile = (localStorage.getItem('mmi_profile_v1') || '').toLowerCase();
      if (profile !== 'technical') return;
      const host = document.querySelector('#mmi-dashboard') || document.querySelector('.wrap') || document.body;
      mountActions(host);
    }catch(e){}
  });



})();


// Connection-aware actions UI helper
async function loadConnectionsForSelect(selectEl, type){
  try{
    const r = await api("server.connections.list", {});
    selectEl.innerHTML="";
    Object.values(r.items||{}).forEach(c=>{
      if(!type || c.type===type){
        selectEl.appendChild(el("option",{value:c.id,text:c.id}));
      }
    });
  }catch(e){}
}

// Visual Connection Selector for actions
async function mmiConnectionSelect(type){
  const select = el("select",{class:"mmi-conn-select"});
  select.appendChild(el("option",{value:"",text:"(no connection)"}));
  try{
    const r = await api("server.connections.list",{});
    Object.values(r.items||{}).forEach(c=>{
      if(!type || c.type===type){
        select.appendChild(el("option",{value:c.id,text:c.id+" ("+(c.type||"")+")"}));
      }
    });
  }catch(e){}
  return select;
}


document.addEventListener('DOMContentLoaded', function() {
  const root = document.querySelector('#mmi-dashboard');
  if (!root) return;
  const pageKey = root.dataset && root.dataset.mmiPage ? root.dataset.mmiPage : '';
  if (pageKey !== 'help') return;
  const loading = document.querySelector('#mmi-loading');
  if (loading) loading.style.display = 'none';
  root.style.display = '';
  root.innerHTML = '';
  try { root.appendChild(buildHelpPage()); } catch(e) { console.error('Help page error:', e); }
});


// ---- License page ----
function buildLicensePage() {
  const api = window.mmiApi || (()=>Promise.resolve({ok:false,error:'api_not_ready'}));
  const wrap = document.createElement('div');

  const hdr = document.createElement('div');
  hdr.className = 'mmi-card';
  hdr.style.cssText = 'border-left:4px solid #2271b1;background:#f0f6fc;margin-bottom:16px;';
  hdr.innerHTML = '<h2 style="margin-top:0;">🔑 Licencia — Intent Flow Pro</h2><p style="margin:0;font-size:14px;color:var(--color-text-secondary);">Activa tu licencia Pro para desbloquear WooCommerce, Google Sheets y más.</p>';
  wrap.appendChild(hdr);

  const status = document.createElement('div');
  status.className = 'mmi-card';
  status.style.marginBottom = '12px';
  status.innerHTML = '<p style="margin:0;color:#888;font-size:13px;">Cargando estado de licencia…</p>';
  wrap.appendChild(status);

  const form = document.createElement('div');
  form.className = 'mmi-card';

  const liveMsg = document.createElement('div');
  liveMsg.style.cssText = 'margin:8px 0;min-height:20px;font-size:13px;';

  const input = document.createElement('input');
  input.type = 'text';
  input.placeholder = 'IF-XXXXXXXX-XXXXXXXX-XXXXXXXX';
  input.style.cssText = 'width:100%;max-width:420px;font-family:monospace;margin-bottom:10px;';

  const btnActivate = document.createElement('button');
  btnActivate.className = 'button button-primary';
  btnActivate.textContent = '🔑 Activar licencia';

  const btnDeactivate = document.createElement('button');
  btnDeactivate.className = 'button';
  btnDeactivate.textContent = 'Desactivar';
  btnDeactivate.style.cssText = 'margin-left:8px;display:none;';

  form.appendChild(document.createElement('h3')).textContent = 'Clave de licencia';
  form.appendChild(input);
  form.appendChild(document.createElement('br'));
  form.appendChild(btnActivate);
  form.appendChild(btnDeactivate);
  form.appendChild(liveMsg);
  wrap.appendChild(form);

  // Features Pro
  const featCard = document.createElement('div');
  featCard.className = 'mmi-card';
  featCard.innerHTML = '<h3 style="margin-top:0;">🚀 Intent Flow Pro incluye</h3>' +
    '<ul style="margin:0;padding-left:20px;line-height:2;font-size:14px;color:var(--color-text-secondary);">' +
    '<li>🛒 Módulo WooCommerce — triggers y acciones de pedidos, clientes, cupones</li>' +
    '<li>📊 Google Sheets — leer y escribir en hojas de cálculo</li>' +
    '<li>🔁 Loops / Bulk actions — ejecutar sobre múltiples usuarios o posts</li>' +
    '<li>⏰ Scheduling avanzado con delays</li>' +
    '<li>📋 Recetas Pro predefinidas</li>' +
    '<li>🎯 Soporte prioritario</li>' +
    '</ul>' +
    '<p style="margin:12px 0 0;font-size:13px;color:var(--color-text-secondary);"><strong>Pago único · Sin suscripción · Datos en tu servidor</strong></p>' +
    '<p style="margin:6px 0 0;font-size:13px;"><a href="https://adaptatuweb.com" target="_blank" style="color:#2271b1;">Comprar Intent Flow Pro →</a></p>';
  wrap.appendChild(featCard);

  // Cargar estado actual via API
  async function loadStatus() {
    try {
      const r = await api('if.license.status', {});
      const key = r && r.status ? (r.status.license_key || '') : '';
      if (key) {
        input.value = key;
        btnDeactivate.style.display = '';
        status.innerHTML = '<div style="display:flex;align-items:center;gap:10px;"><span style="font-size:24px;">✅</span><div><strong>Licencia activa</strong><br><span style="font-size:13px;color:#666;">Clave: <code>' + key.substring(0,8) + '…</code></span></div></div>';
      } else {
        status.innerHTML = '<div style="display:flex;align-items:center;gap:10px;"><span style="font-size:24px;">⚠️</span><div><strong>Sin licencia activa</strong><br><span style="font-size:13px;color:#666;">Introduce tu clave para activar el Pro.</span></div></div>';
      }
    } catch(e) {
      status.innerHTML = '<p style="color:#888;font-size:13px;">No se pudo cargar el estado.</p>';
    }
  }

  btnActivate.addEventListener('click', async () => {
    const key = input.value.trim();
    if (!key) { liveMsg.textContent = '⚠ Introduce una clave de licencia.'; liveMsg.style.color = '#d63638'; return; }
    liveMsg.textContent = 'Activando…'; liveMsg.style.color = '#2271b1';
    try {
      const r = await api('if.license.activate', { key });
      if (r && r.ok) {
        liveMsg.textContent = '✅ Licencia activada — Plan: ' + (r.plan || 'pro');
        liveMsg.style.color = '#00a32a';
        btnDeactivate.style.display = '';
        await loadStatus();
      } else {
        liveMsg.textContent = '❌ Error: ' + (r && r.error ? r.error : 'Error desconocido');
        liveMsg.style.color = '#d63638';
      }
    } catch(e) { liveMsg.textContent = '❌ Error inesperado.'; liveMsg.style.color = '#d63638'; }
  });

  btnDeactivate.addEventListener('click', async () => {
    if (!confirm('¿Desactivar la licencia en este dominio?')) return;
    liveMsg.textContent = 'Desactivando…'; liveMsg.style.color = '#2271b1';
    try {
      const r = await api('if.license.deactivate', {});
      if (r && r.ok) {
        liveMsg.textContent = '✅ Licencia desactivada.'; liveMsg.style.color = '#00a32a';
        input.value = ''; btnDeactivate.style.display = 'none';
        await loadStatus();
      } else {
        liveMsg.textContent = '❌ Error: ' + (r && r.error ? r.error : 'unknown');
        liveMsg.style.color = '#d63638';
      }
    } catch(e) { liveMsg.textContent = '❌ Error inesperado.'; liveMsg.style.color = '#d63638'; }
  });

  loadStatus();
  return wrap;
}

document.addEventListener('DOMContentLoaded', function() {
  const root = document.querySelector('#mmi-dashboard');
  if (!root) return;
  const pageKey = root.dataset && root.dataset.mmiPage ? root.dataset.mmiPage : '';
  if (pageKey !== 'license') return;
  const loading = document.querySelector('#mmi-loading');
  if (loading) loading.style.display = 'none';
  root.style.display = '';
  root.innerHTML = '';
  try { root.appendChild(buildLicensePage()); } catch(e) { console.error('License page error:', e); }
});



// ---- AI Flow Generator ----
function showAIFlowGenerator() {
  const overlay = document.createElement('div');
  overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;display:flex;align-items:center;justify-content:center;';

  const modal = document.createElement('div');
  modal.style.cssText = 'background:var(--color-background-primary);border-radius:12px;padding:24px;width:560px;max-width:95vw;box-shadow:0 8px 32px rgba(0,0,0,.3);';

  const liveMsg = document.createElement('div');
  liveMsg.style.cssText = 'margin:10px 0;min-height:20px;font-size:13px;';

  const textarea = document.createElement('textarea');
  textarea.placeholder = 'Describe the flow in natural language...';
  textarea.style.cssText = 'width:100%;height:120px;margin:12px 0;padding:10px;font-size:14px;border:1px solid var(--color-border-primary);border-radius:8px;resize:vertical;';

  const connectionSelect = document.createElement('select');
  connectionSelect.style.cssText = 'width:100%;margin-bottom:10px;padding:8px;border:1px solid var(--color-border-primary);border-radius:8px;';
  const defaultOpt = document.createElement('option');
  defaultOpt.value = '';
  defaultOpt.textContent = 'Select OpenAI connection (optional)';
  connectionSelect.appendChild(defaultOpt);

  (async () => {
    try {
      const r = await window.IntentFlow.run('server.connections.list', {});
      const conns = (r && r.connections) ? r.connections : [];
      conns.filter(c => c.type === 'openai').forEach(c => {
        const opt = document.createElement('option');
        opt.value = c.id;
        opt.textContent = c.name || c.id;
        connectionSelect.appendChild(opt);
      });
    } catch(e) {}
  })();

  const btnGenerate = document.createElement('button');
  btnGenerate.className = 'button button-primary';
  btnGenerate.textContent = 'Generate flow';

  const btnClose = document.createElement('button');
  btnClose.className = 'button';
  btnClose.textContent = 'Cancel';
  btnClose.style.marginLeft = '8px';

  const h3 = document.createElement('h3');
  h3.style.marginTop = '0';
  h3.textContent = 'Generate flow with AI';

  const desc = document.createElement('p');
  desc.style.cssText = 'font-size:13px;color:var(--color-text-secondary);margin:0 0 8px;';
  desc.textContent = 'Describe what you want to automate and AI will generate the flow automatically.';

  modal.appendChild(h3);
  modal.appendChild(desc);
  modal.appendChild(connectionSelect);
  modal.appendChild(textarea);
  modal.appendChild(liveMsg);
  modal.appendChild(btnGenerate);
  modal.appendChild(btnClose);
  overlay.appendChild(modal);
  document.body.appendChild(overlay);

  textarea.focus();

  btnClose.addEventListener('click', () => overlay.remove());
  overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });

  btnGenerate.addEventListener('click', async () => {
    const descText = textarea.value.trim();
    if (!descText) { liveMsg.textContent = 'Please describe the flow first.'; liveMsg.style.color = '#d63638'; return; }
    btnGenerate.disabled = true;
    btnGenerate.textContent = 'Generating...';
    liveMsg.textContent = 'AI is generating the flow...';
    liveMsg.style.color = 'var(--color-text-secondary)';
    try {
      const payload = { description: descText, language: 'es' };
      if (connectionSelect.value) payload.connection_id = connectionSelect.value;
      const r = await window.IntentFlow.run('ai.flow.generate', payload);
      if (r && r.ok && r.flow) {
        liveMsg.textContent = 'Flow generated successfully!';
        liveMsg.style.color = '#00a32a';
        setTimeout(() => {
          overlay.remove();
          if (typeof startNewFlow === 'function') startNewFlow(r.flow);
        }, 800);
      } else {
        liveMsg.textContent = 'Error: ' + (r && r.error ? r.error : 'Unknown error');
        liveMsg.style.color = '#d63638';
        btnGenerate.disabled = false;
        btnGenerate.textContent = 'Generate flow';
      }
    } catch(e) {
      liveMsg.textContent = 'Unexpected error.';
      liveMsg.style.color = '#d63638';
      btnGenerate.disabled = false;
      btnGenerate.textContent = 'Generate flow';
    }
  });
}
