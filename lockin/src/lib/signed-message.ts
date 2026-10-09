// Shared between client (signing) and server (verification).
export function buildSignedMessage(action: string, payload: string, issuedAt: number) {
  return `LockIn\naction: ${action}\npayload: ${payload}\nissuedAt: ${issuedAt}`;
}

// Stable string form of the signed fields (key order independent).
export function canonicalPayload(obj: Record<string, unknown>) {
  return JSON.stringify(Object.keys(obj).sort().map((k) => [k, obj[k]]));
}
