"use client";
import { useSession } from "@/store/session";
import { PriceHeader } from "./PriceHeader";
import { PriceChart } from "./PriceChart";
import { AccountStrip } from "./AccountStrip";
import { JoinCard } from "./JoinCard";
import { OrderPanel } from "./OrderPanel";
import { PositionCard } from "./PositionCard";
import { RecentTrades } from "./RecentTrades";

export function TradeView({ onNeedTickets }: { onNeedTickets: () => void }) {
  const joined = useSession((s) => s.account !== null);
  const hasPosition = useSession((s) => s.position !== null);
  return (
    <div className="space-y-3 pb-4">
      <PriceHeader />
      <PriceChart height={250} />
      {joined ? (
        <>
          <AccountStrip />
          {hasPosition ? <PositionCard /> : <OrderPanel />}
          <RecentTrades />
        </>
      ) : (
        <JoinCard onNeedTickets={onNeedTickets} />
      )}
    </div>
  );
}
