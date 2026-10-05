/** Signed percentage that never renders "-0.00%". */
export function pct(value: number, digits = 2): string {
  const rounded = Number(value.toFixed(digits));
  const v = Object.is(rounded, -0) ? 0 : rounded;
  return `${v > 0 ? "+" : ""}${v.toFixed(digits)}%`;
}
