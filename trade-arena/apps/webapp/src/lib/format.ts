const usd = new Intl.NumberFormat("en-US", { style: "currency", currency: "USD", minimumFractionDigits: 2, maximumFractionDigits: 2 });
const price = new Intl.NumberFormat("en-US", { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const fmtUsd = (n: number) => usd.format(n);
export const fmtPrice = (n: number) => price.format(n);
/** Signed number that never renders "-0.00". */
export const fmtSigned = (n: number, digits = 2) => {
  const r = Number(n.toFixed(digits));
  const v = Object.is(r, -0) ? 0 : r;
  return `${v > 0 ? "+" : ""}${v.toFixed(digits)}`;
};
export const fmtPct = (n: number, digits = 2) => `${fmtSigned(n, digits)}%`;

export function fmtCountdown(ms: number): string {
  const total = Math.max(0, Math.floor(ms / 1000));
  const m = Math.floor(total / 60);
  const s = total % 60;
  return `${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`;
}

export const pnlColor = (n: number) => (n > 0 ? "text-up" : n < 0 ? "text-down" : "text-muted");
