import type { Config } from "tailwindcss";

const config: Config = {
  content: ["./src/**/*.{ts,tsx}"],
  theme: {
    extend: {
      colors: {
        obsidian: {
          DEFAULT: "#060709",
          900: "#0B0D10",
          800: "#12151A",
          700: "#1C2028",
          600: "#2A303B",
        },
        acid: { DEFAULT: "#00FF66", dim: "#00B347" },
        cyan: { DEFAULT: "#00E5FF", dim: "#00A3B8" },
        fatal: { DEFAULT: "#FF0055", dim: "#B3003C" },
        bone: { DEFAULT: "#E8EAED", muted: "#8A919C" },
      },
      fontFamily: {
        display: ["var(--font-display)", "system-ui", "sans-serif"],
        mono: ["var(--font-mono)", "ui-monospace", "monospace"],
      },
      borderWidth: { 3: "3px" },
      boxShadow: {
        // Hard, unblurred offset shadows: the brutalist signature.
        brutal: "6px 6px 0 0 #00FF66",
        "brutal-cyan": "6px 6px 0 0 #00E5FF",
        "brutal-fatal": "6px 6px 0 0 #FF0055",
        "brutal-sm": "3px 3px 0 0 #00FF66",
      },
      keyframes: {
        flicker: {
          "0%, 100%": { opacity: "1" },
          "50%": { opacity: "0.55" },
        },
      },
      animation: { flicker: "flicker 1.4s steps(2, end) infinite" },
    },
  },
  plugins: [],
};

export default config;
