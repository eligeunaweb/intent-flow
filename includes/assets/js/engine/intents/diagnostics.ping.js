export function registerDiagnosticsPing(registry, ctx) {
  registry.register("diagnostics.ping", async ({ payload }) => {
    return {
      ok: true,
      message: "pong",
      echo: payload || null,
      context: {
        version: ctx.config.version,
        safe_mode: ctx.config.safe_mode,
        dry_run: ctx.config.dry_run,
      },
    };
  });
}
