export async function runViaAjax(intent, ctx) {
  const { ajaxUrl, nonce } = ctx.config;
  if (!ajaxUrl) throw new Error("ajaxUrl no configurado");
  if (!nonce) throw new Error("nonce no configurado");

  const body = new URLSearchParams();
  body.set("action", "mmi_intent");
  body.set("nonce", nonce);
  body.set("intent", JSON.stringify({ id: intent.id, payload: intent.payload || {}, meta: intent.meta || {} }));

  const res = await fetch(ajaxUrl, {
    method: "POST",
    credentials: "same-origin",
    headers: { "Content-Type": "application/x-www-form-urlencoded; charset=UTF-8" },
    body,
  });

  const text = await res.text();

  let json;
  try {
    json = JSON.parse(text);
  } catch {
    return {
      ok: false,
      error: "ajax_non_json",
      status: res.status,
      message: "La respuesta no es JSON (probable fatal PHP).",
      snippet: text.slice(0, 400),
    };
  }

  if (json.success === false) return json.data || { ok: false, error: "ajax_error" };
  return json.data || { ok: true };
}
