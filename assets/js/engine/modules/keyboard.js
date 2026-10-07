export function isHotkeyAltShiftM(ev) {
  const key = String(ev.key || "");
  return ev.altKey === true && ev.shiftKey === true && (key === "m" || key === "M");
}
