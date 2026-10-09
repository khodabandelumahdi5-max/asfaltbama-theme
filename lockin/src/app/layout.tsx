import type { Metadata, Viewport } from "next";
import { JetBrains_Mono, Space_Grotesk } from "next/font/google";
import { SolanaProviders } from "@/components/wallet/SolanaProviders";
import "@solana/wallet-adapter-react-ui/styles.css";
import "./globals.css";

const display = Space_Grotesk({ subsets: ["latin"], variable: "--font-display", display: "swap" });
const mono = JetBrains_Mono({ subsets: ["latin"], variable: "--font-mono", display: "swap" });

export const metadata: Metadata = {
  title: "LockIn · 21-day commitment arena",
  description: "Stake SOL, post daily video proof, survive peer review for 21 days.",
};

export const viewport: Viewport = { themeColor: "#060709" };

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en" className={`${display.variable} ${mono.variable}`}>
      <body className="min-h-screen">
        <SolanaProviders>{children}</SolanaProviders>
      </body>
    </html>
  );
}
