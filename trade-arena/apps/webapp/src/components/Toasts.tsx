"use client";
import { useToasts } from "@/store/toast";

const STYLE = {
  success: "border-up/40 bg-up/15 text-up",
  error: "border-down/40 bg-down/15 text-down",
  info: "border-line bg-panel-2 text-text",
} as const;

export function Toasts() {
  const toasts = useToasts((s) => s.toasts);
  const dismiss = useToasts((s) => s.dismiss);
  return (
    <div className="pointer-events-none fixed inset-x-0 top-14 z-40 mx-auto flex max-w-md flex-col items-center gap-2 px-4">
      {toasts.map((t) => (
        <button
          key={t.id}
          onClick={() => dismiss(t.id)}
          className={`pointer-events-auto w-full rounded-xl border px-4 py-2.5 text-left text-sm font-medium shadow-lg backdrop-blur ${STYLE[t.kind]}`}
        >
          {t.text}
        </button>
      ))}
    </div>
  );
}
