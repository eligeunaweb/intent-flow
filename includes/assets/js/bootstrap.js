import { isHotkeyAltShiftM } from "./engine/modules/keyboard.js";
import { createEngine } from "./engine/index.js";

const boot = (global) => {
  const cfg = global.IF_BOOT || {};
  const engine = createEngine(cfg);

  let __mmi_live_until = 0;

  // Live session (server) — enables temporary execution of unsafe intents while safe_mode is ON
  let __mmi_live_cache = { active: false, until: 0, token: '' };

  async function __mmi_get_token() {
    const r = await engine.run('server.settings.challenge', {}, {});
    // runner returns {ok, result:{...}}
    if (r && r.ok && r.result && r.result.token) return r.result.token;
    if (r && r.token) return r.token;
    return '';
  }

  async function __mmi_live_enable(ttlSeconds = 600) {
    const token = await __mmi_get_token();
    if (!token) return { ok: false, error: 'missing_token' };
    const ttl = Math.max(60, Math.min(3600, Number(ttlSeconds) || 600));
    const r = await engine.run('server.session.live.enable', { token, ttl }, {});
    if (r && r.ok && r.result && r.result.live_until) {
      __mmi_live_cache = { active: true, until: r.result.live_until, token };
    }
    return r;
  }

  async function __mmi_live_disable() {
    const token = __mmi_live_cache.token || (await __mmi_get_token());
    if (!token) return { ok: false, error: 'missing_token' };
    const r = await engine.run('server.session.live.disable', { token }, {});
    __mmi_live_cache = { active: false, until: 0, token: '' };
    return r;
  }

  global.IntentFlow = {
    async live(ttlSeconds, fn) {
      const enabled = await __mmi_live_enable(ttlSeconds);
      if (!enabled || !enabled.ok) return enabled || { ok: false, error: 'live_enable_failed' };
      // Keep Live enabled until it expires (ttl) or the user disables it.
      return await fn();
    },

    async enableLive(ttlSeconds = 600) {
      return await __mmi_live_enable(ttlSeconds);
    },

    async disableLive() {
      return await __mmi_live_disable();
    },

    run(input, payload = null, meta = null) {
      return engine.run(input, payload, meta);
    },
    getState() {
      return engine.getState();
    },
    version: cfg.version || '0.0.0',
  };

  global.addEventListener(
    "keydown",
    (ev) => {
      if (!isHotkeyAltShiftM(ev)) return;
      engine.run({ id: "diagnostics.ping", payload: { source: "hotkey" } });
    },
    { passive: true }
  );

  engine.log.info("MMI boot OK", { safe_mode: !!cfg.safe_mode, dry_run: !!cfg.dry_run });
};

boot(window);

// cache-bust diagnostics.last