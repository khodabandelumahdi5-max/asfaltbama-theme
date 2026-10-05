"use client";
import { useEffect, useState } from "react";
import { useSession } from "@/store/session";

/** Server-synchronised clock that re-renders every `intervalMs`. */
export function useServerNow(intervalMs = 250): number {
  const offset = useSession((s) => s.serverOffsetMs);
  const [now, setNow] = useState(() => Date.now() + offset);
  useEffect(() => {
    const id = setInterval(() => setNow(Date.now() + offset), intervalMs);
    return () => clearInterval(id);
  }, [offset, intervalMs]);
  return now;
}
