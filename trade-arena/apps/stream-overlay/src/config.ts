export type Layout = "portrait" | "landscape";

const params = new URLSearchParams(window.location.search);

export const SERVER_URL = params.get("server") ?? import.meta.env.VITE_SERVER_URL ?? "http://localhost:4000";
export const JOIN_URL = params.get("join") ?? import.meta.env.VITE_JOIN_URL ?? "https://t.me/TradeArenaBot/arena";
export const LAYOUT: Layout =
  params.get("layout") === "landscape" || (params.get("layout") === null && window.innerWidth > window.innerHeight) ? "landscape" : "portrait";
export const STAGE = LAYOUT === "portrait" ? { width: 1080, height: 1920 } : { width: 1920, height: 1080 };
/** `?capture=1` disables preview scaling and decorative blur for headless capture performance. */
export const CAPTURE = params.get("capture") === "1";
