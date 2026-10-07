export async function enforceEnabled(intent, ctx) {
  if (!ctx?.config?.enabled) {
    return { ok: false, blocked: true, reason: "engine_disabled", intentId: intent.id };
  }
  return null;
}

export async function enforceAdminOnly(intent, ctx) {
  const cap = ctx?.config?.cap || "manage_options";
  if (cap !== "manage_options") {
    return { ok: false, blocked: true, reason: "cap_mismatch", intentId: intent.id };
  }
  return null;
}

export async function enforceSafeMode(intent, ctx) {
  if (ctx?.config?.safe_mode && String(intent.id || "").startsWith("unsafe.")) {
    return { ok: false, blocked: true, reason: "safe_mode_block", intentId: intent.id };
  }
  return null;
}
