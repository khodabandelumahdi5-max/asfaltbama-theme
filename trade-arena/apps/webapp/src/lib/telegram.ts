/** Minimal typed surface of https://telegram.org/js/telegram-web-app.js used by the app. */
export interface TelegramWebApp {
  initData: string;
  initDataUnsafe: {
    user?: { id: number; first_name: string; username?: string; photo_url?: string };
    start_param?: string;
  };
  version: string;
  platform: string;
  colorScheme: "light" | "dark";
  viewportStableHeight: number;
  isExpanded: boolean;
  ready(): void;
  expand(): void;
  close(): void;
  setHeaderColor(color: string): void;
  setBackgroundColor(color: string): void;
  disableVerticalSwipes?: () => void;
  enableClosingConfirmation(): void;
  openTelegramLink(url: string): void;
  openLink(url: string): void;
  showAlert(message: string, cb?: () => void): void;
  isVersionAtLeast(version: string): boolean;
  HapticFeedback: {
    impactOccurred(style: "light" | "medium" | "heavy" | "rigid" | "soft"): void;
    notificationOccurred(type: "error" | "success" | "warning"): void;
    selectionChanged(): void;
  };
  onEvent(event: string, cb: () => void): void;
  offEvent(event: string, cb: () => void): void;
}

declare global {
  interface Window {
    Telegram?: { WebApp: TelegramWebApp };
  }
}

export function tg(): TelegramWebApp | null {
  if (typeof window === "undefined") return null;
  const app = window.Telegram?.WebApp;
  return app && app.initData ? app : null;
}

const DEV_USER_KEY = "arena:dev-user";

export interface DevUser {
  id: string;
  username: string;
}

/** Outside Telegram (local browser dev) we fall back to a persistent pseudo user; the server only accepts it when ALLOW_DEV_AUTH=true. */
export function devUser(): DevUser {
  try {
    const saved = localStorage.getItem(DEV_USER_KEY);
    if (saved) return JSON.parse(saved) as DevUser;
  } catch {
    /* storage unavailable */
  }
  const suffix = Math.random().toString(36).slice(2, 10);
  const user = { id: `dev_${suffix}`, username: `trader_${suffix.slice(0, 4)}` };
  try {
    localStorage.setItem(DEV_USER_KEY, JSON.stringify(user));
  } catch {
    /* ignore */
  }
  return user;
}

export function authPayload(): { initData: string } | { devUser: DevUser } {
  const app = tg();
  return app ? { initData: app.initData } : { devUser: devUser() };
}

export function authHeader(): string {
  const app = tg();
  return app ? `tma ${app.initData}` : `dev ${JSON.stringify(devUser())}`;
}

export function initTelegram(): void {
  const app = tg();
  if (!app) return;
  app.ready();
  app.expand();
  app.setHeaderColor("#0b0e14");
  app.setBackgroundColor("#0b0e14");
  if (app.isVersionAtLeast("7.7")) app.disableVerticalSwipes?.();
}

export const haptic = {
  impact(style: "light" | "medium" | "heavy" = "light") {
    tg()?.HapticFeedback.impactOccurred(style);
  },
  notify(type: "error" | "success" | "warning") {
    tg()?.HapticFeedback.notificationOccurred(type);
  },
  select() {
    tg()?.HapticFeedback.selectionChanged();
  },
};
