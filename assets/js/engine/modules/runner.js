import { runViaAjax } from "./transport.ajax.js";

export function createRunner({ log, registry, guards = [] }) {
  async function execute(intent, ctx) {
    const intentId = String(intent?.id || "");
    if (!intentId) return { ok: false, error: "missing_intent_id" };

    for (const guard of guards) {
      const blocked = await guard(intent, ctx);
      if (blocked) {
        log?.warn?.("Bloqueado por guard", blocked);
        return blocked;
      }
    }

    const needsServer =
      intent?.meta?.requiresServer === true ||
      intentId.startsWith("server.") ||
      intentId.startsWith("macro.");


    if (needsServer) {
      try {
        return await runViaAjax(intent, ctx);
      } catch (e) {
        log?.error?.("Error AJAX", e);
        return { ok: false, error: "ajax_failed", message: String(e?.message || e) };
      }
    }

    const handler = registry.get(intentId);
        if (!handler) {
          // fallback a servidor si no hay handler local
          try {
            return await runViaAjax(intent, ctx);
          } catch (e) {
            log?.error?.("Error AJAX (fallback)", e);
            return { ok: false, error: "unknown_intent", intentId };
          }
        }


    const isDryRun = !!ctx?.config?.dry_run;

    try {
      const res = await handler({ payload: intent.payload || {}, meta: intent.meta || {}, ctx });

      if (isDryRun) {
        return {
          ok: true,
          dry_run: true,
          intentId,
          result: res,
          note: "dry-run ON: resultado simulado / sin efectos externos",
        };
      }

      return { ok: true, intentId, result: res };
    } catch (e) {
      log?.error?.("Error ejecutando intent", intentId, e);
      return { ok: false, intentId, error: "intent_failed", message: String(e?.message || e) };
    }
  }

  return { execute };
}
