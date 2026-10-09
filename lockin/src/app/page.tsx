import { ArenaDashboard } from "@/components/arena/ArenaDashboard";
import { WalletButton } from "@/components/wallet/WalletButton";

export default function ArenaPage() {
  return (
    <>
      <header className="sticky top-0 z-20 border-b-3 border-bone/90 bg-obsidian/95 backdrop-blur">
        <div className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
          <p className="font-display text-2xl font-black uppercase tracking-tight">
            Lock<span className="text-acid">In</span>
            <span className="ml-2 align-middle font-mono text-[10px] font-normal text-cyan">DEVNET</span>
          </p>
          <WalletButton />
        </div>
      </header>
      <main className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
        <ArenaDashboard />
      </main>
    </>
  );
}
