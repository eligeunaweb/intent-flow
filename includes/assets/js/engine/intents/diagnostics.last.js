export function registerDiagnosticsLast(registry, ctx) {
  registry.register("diagnostics.last", async () => {
    const st = typeof ctx.getState === "function" ? ctx.getState() : null;
    return {
      ok: true,
      lastRun: st?.lastRun || null,
      lastResult: st?.lastResult || null,
      config: st?.config || null,
    };
  });
}
