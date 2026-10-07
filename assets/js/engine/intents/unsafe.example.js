export function registerUnsafeExample(registry, ctx) {
  registry.register("unsafe.example", async ({ payload }) => {
    return {
      ok: true,
      note: "Si estás viendo esto con safe_mode ON, algo falló en el guard.",
      echo: payload || null,
      context: {
        safe_mode: ctx.config.safe_mode,
        dry_run: ctx.config.dry_run,
      },
    };
  });
}
