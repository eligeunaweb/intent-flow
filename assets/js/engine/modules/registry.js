export function createRegistry({ log }) {
  const map = new Map();

  function register(id, handler) {
    if (!id || typeof id !== "string") throw new Error("Intent id inválido");
    if (typeof handler !== "function") throw new Error("Handler inválido");
    map.set(id, handler);
    log?.debug?.("Intent registrada", id);
  }

  function get(id) { return map.get(id); }
  function has(id) { return map.has(id); }

  return { register, get, has };
}
