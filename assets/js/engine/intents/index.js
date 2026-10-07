import { registerDiagnosticsPing } from "./diagnostics.ping.js";
import { registerDiagnosticsLast } from "./diagnostics.last.js";
import { registerUnsafeExample } from "./unsafe.example.js";

export function registerIntents(registry, ctx) {
  registerDiagnosticsPing(registry, ctx);
  registerDiagnosticsLast(registry, ctx);
  registerUnsafeExample(registry, ctx);
}