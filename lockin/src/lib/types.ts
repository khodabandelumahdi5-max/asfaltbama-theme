export type PoolStatus = "ACTIVE" | "COMPLETED" | "SETTLED";
export type ProofStatus = "PENDING" | "VERIFIED" | "REJECTED";

export interface ArenaPool {
  id: string;
  title: string;
  stakeLamports: number;
  startDate: string; // YYYY-MM-DD
  endDate: string;
  totalLockedLamports: number;
  status: PoolStatus;
  participantCount: number;
  eliminatedCount: number;
  currentDay: number; // 1..21, 0 before start, 22 after end
  /** Yesterday's day number while its grace period is still open, else null. */
  graceDay: number | null;
  graceHours: number;
}

export interface ArenaParticipant {
  currentStreak: number;
  isEliminated: boolean;
  eliminatedOnDay: number | null;
  eliminationReason: "MISSED_DEADLINE" | "PEER_REJECTED" | null;
  joinedAt: string;
}

export interface ProofCardData {
  id: string;
  walletAddress: string;
  dayNumber: number;
  videoCfId: string;
  status: ProofStatus;
  createdAt: string;
  approvals: number;
  rejections: number;
  myVote: -1 | 1 | null;
}

export interface PoolResult {
  poolId: string;
  title: string;
  startDate: string;
  endDate: string;
  status: "COMPLETED" | "SETTLED";
  survived: boolean;
  eliminatedOnDay: number | null;
  eliminationReason: "MISSED_DEADLINE" | "PEER_REJECTED" | null;
  currentStreak: number;
  stakeLamports: string;
  /** null until settle-pool has run (status COMPLETED). Lamport amounts are decimal strings. */
  settlement: {
    survivorCount: number;
    totalLamports: string;
    platformFeeLamports: string;
    payoutPerSurvivorLamports: string;
    paidOutAt: string | null;
  } | null;
  /** This wallet's survivor payout; null if eliminated or not settled yet. */
  payout: { amountLamports: string; status: "PENDING" | "SENT" | "CONFIRMED"; txSig: string | null } | null;
}

export interface RefundView {
  depositTxSig: string;
  amountLamports: string;
  reason: "WRONG_AMOUNT" | "UNMATCHED" | "DUPLICATE_ENTRY";
  receivedAt: string;
  status: "QUEUED" | "SENDING" | "REFUNDED" | "UNDER_REVIEW";
  refundTxSig: string | null;
}

export interface ArenaState {
  pool: ArenaPool | null;
  participant: ArenaParticipant | null;
  myProofs: ProofCardData[];
  reviewQueue: ProofCardData[];
  /** Finished pools for the connected wallet. */
  results: PoolResult[];
  /** Deposits from the connected wallet that are being or were refunded. */
  refunds: RefundView[];
}
