import "server-only";

/** Overridable for local testing against a Cloudflare API stand-in. */
const API_BASE = process.env.CF_API_BASE ?? "https://api.cloudflare.com/client/v4";

export function streamConfig() {
  const account = process.env.CF_ACCOUNT_ID;
  const token = process.env.CF_STREAM_API_TOKEN;
  return account && token ? { account, token } : null;
}

/** TUS Upload-Metadata: comma-separated `key base64(value)` pairs. */
export function tusMetadata(pairs: Record<string, string>) {
  return Object.entries(pairs)
    .map(([k, v]) => `${k} ${Buffer.from(v).toString("base64")}`)
    .join(",");
}

/**
 * Starts a Cloudflare Stream "direct creator upload" over TUS. Returns the
 * one-time upload URL the browser sends the bytes to, and the video UID.
 * https://developers.cloudflare.com/stream/uploading-videos/direct-creator-uploads/
 */
export async function createTusUpload(params: {
  uploadLength: number;
  name: string;
  creator: string;
  maxDurationSeconds: number;
}): Promise<{ location: string; uid: string }> {
  const cfg = streamConfig();
  if (!cfg) throw new Error("Cloudflare Stream is not configured");

  const res = await fetch(`${API_BASE}/accounts/${cfg.account}/stream?direct_user=true`, {
    method: "POST",
    headers: {
      Authorization: `Bearer ${cfg.token}`,
      "Tus-Resumable": "1.0.0",
      "Upload-Length": String(params.uploadLength),
      "Upload-Creator": params.creator,
      // Server-chosen only; nothing from the client is forwarded.
      "Upload-Metadata": tusMetadata({
        name: params.name,
        maxDurationSeconds: String(params.maxDurationSeconds),
      }),
    },
  });

  const location = res.headers.get("location");
  const uid = res.headers.get("stream-media-id");
  if (!res.ok || !location || !uid || !/^[a-f0-9]{32}$/.test(uid)) {
    const body = await res.text().catch(() => "");
    throw new Error(`Cloudflare TUS create failed (${res.status}): ${body.slice(0, 200)}`);
  }
  return { location, uid };
}
