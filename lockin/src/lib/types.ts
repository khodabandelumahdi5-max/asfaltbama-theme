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

export interface ArenaState {
  pool: ArenaPool | null;
  participant: ArenaParticipant | null;
  myProofs: ProofCardData[];
  reviewQueue: ProofCardData[];
}
