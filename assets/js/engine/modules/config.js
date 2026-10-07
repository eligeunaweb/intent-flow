export function normalizeConfig(raw = {}) {
  return {
    version: String(raw.version || "0.0.0"),
    safe_mode: !!raw.safe_mode,
    dry_run: !!raw.dry_run,
    enabled: raw.enabled === undefined ? true : !!raw.enabled,
    cap: String(raw.cap || "manage_options"),
    nonce: raw.nonce ? String(raw.nonce) : "",
    ajaxUrl: raw.ajaxUrl ? String(raw.ajaxUrl) : "",
    homeUrl: raw.homeUrl ? String(raw.homeUrl) : "",
  };
}
