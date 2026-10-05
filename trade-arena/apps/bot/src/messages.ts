import type { DepositView } from "@arena/shared";
import type { Summary } from "./api";

export function escapeHtml(s: string): string {
  return s.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
}

export function welcome(name: string, tickets: number, referred: boolean): string {
  return [
    `⚡️ <b>Welcome to Trade Arena, ${escapeHtml(name)}!</b>`,
    "",
    "Fast skill-based BTC/USDT paper-trading tournaments.",
    "• 10-minute Blitz rounds, $10,000 virtual balance",
    "• LONG / SHORT with up to 100× leverage",
    "• Highest PnL % wins the ticket prize pool 🏆",
    "",
    `🎟 Tickets: <b>${tickets}</b>${referred ? " (referral applied ✅)" : ""}`,
    "",
    "Tap <b>Open Arena</b> to start trading.",
  ].join("\n");
}

export function depositInstructions(d: DepositView): string {
  const expires = new Date(d.expiresAt).toISOString().slice(11, 16);
  const lines = [
    `💳 <b>Deposit ${d.tickets} 🎟</b> via USDT ${d.network === "TRC20" ? "TRC20 (Tron)" : "on TON"}`,
    "",
    `Send <b>exactly</b>: <code>${d.amount}</code> USDT`,
    `To address:\n<code>${d.address}</code>`,
  ];
  if (d.memo) lines.push(`Comment / memo (required):\n<code>${d.memo}</code>`);
  lines.push(
    "",
    `Reference: <code>${d.reference}</code>`,
    `⏳ Valid until ${expires} UTC. Tickets are credited automatically after confirmation.`,
  );
  if (d.network === "TRC20") lines.push("⚠️ The exact amount (incl. decimals) identifies your payment.");
  return lines.join("\n");
}

export function summary(s: Summary): string {
  const lines = [`👤 <b>${escapeHtml(s.user.username ? "@" + s.user.username : s.user.firstName)}</b>`, `🎟 Tickets: <b>${s.user.tickets}</b>`];
  if (s.tournament) {
    lines.push("", `🏁 <b>${escapeHtml(s.tournament.name)}</b> — ${s.tournament.status}`, `Players: ${s.tournament.players} · Pool: ${s.tournament.prizePoolTickets} 🎟`);
    if (s.account) {
      const pct = s.account.realizedPnlPct;
      lines.push(`Your PnL: <b>${pct >= 0 ? "+" : ""}${pct.toFixed(2)}%</b> · Rank: <b>${s.account.rank ?? "—"}</b>`);
    } else {
      lines.push("You haven't joined this round yet.");
    }
  }
  if (s.leaderboard && s.leaderboard.entries.length) {
    lines.push("", "<b>Top 5</b>");
    for (const e of s.leaderboard.entries.slice(0, 5)) {
      const medal = ["🥇", "🥈", "🥉"][e.rank - 1] ?? `${e.rank}.`;
      lines.push(`${medal} ${escapeHtml(e.name)} — ${e.pnlPct >= 0 ? "+" : ""}${e.pnlPct.toFixed(2)}%`);
    }
  }
  return lines.join("\n");
}
