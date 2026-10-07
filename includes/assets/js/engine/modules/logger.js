export function createLogger({ prefix = "LOG", enabled = true } = {}) {
  const tag = `[${prefix}]`;
  const safe = (fn, ...args) => {
    if (!enabled) return;
    try { fn(tag, ...args); } catch (_) {}
  };
  return {
    info: (...args) => safe(console.info, ...args),
    warn: (...args) => safe(console.warn, ...args),
    error: (...args) => safe(console.error, ...args),
    debug: (...args) => safe(console.debug, ...args),
  };
}
