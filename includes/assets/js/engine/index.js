import { createLogger } from "./modules/logger.js";
import { createRegistry } from "./modules/registry.js";
import { createRunner } from "./modules/runner.js";
import { normalizeConfig } from "./modules/config.js";
import { enforceAdminOnly, enforceEnabled, enforceSafeMode } from "./modules/guards.js";

import { registerIntents } from "./intents/index.js";

export function createEngine(rawConfig = {}) {
  const config = normalizeConfig(rawConfig);
  const log = createLogger({ prefix: "MMI", enabled: true });

  const state = {
    config,
    lastRun: null,
    lastResult: null,
  };

  const registry = createRegistry({ log });
  function getState() {
  return {
    config: { ...state.config },
    lastRun: state.lastRun ? { ...state.lastRun } : null,
    lastResult: state.lastResult,
  };
}

const ctx = { config, log, getState };
registerIntents(registry, ctx);

  const runner = createRunner({
    log,
    registry,
    guards: [enforceEnabled, enforceAdminOnly, enforceSafeMode],
  });

  async function run(input, payloadArg = null, metaArg = null) {
  const startedAt = new Date().toISOString();

  const intent =
    typeof input === "string"
      ? {
          id: input,
          payload: payloadArg && typeof payloadArg === "object" ? payloadArg : {},
          meta: metaArg && typeof metaArg === "object" ? metaArg : {},
        }
      : input && typeof input === "object"
        ? {
            id: input.id || "diagnostics.ping",
            payload: input.payload || {},
            meta: input.meta || {},
          }
        : { id: "diagnostics.ping", payload: { source: "default" }, meta: {} };


    state.lastRun = { startedAt, intentId: intent.id };

    const result = await runner.execute(intent, ctx);
    state.lastResult = result;

    return result;
  }

  

  return { run, getState, log };
}
