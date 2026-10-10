"use client";

import { Upload, type HttpRequest, type HttpResponse } from "tus-js-client";
import { TUS_CHUNK_BYTES } from "./video";

export interface UploadAuth {
  wallet: string;
  issuedAt: number;
  signature: string;
}

export interface ProofUploadHandle {
  /** Resolves with the Cloudflare Stream video UID once every byte is stored. */
  done: Promise<string>;
  abort: () => void;
}

const UID_KEY = (uploadUrl: string) => `lockin:tus-uid:${uploadUrl}`;

function remember(uploadUrl: string, uid: string) {
  try {
    localStorage.setItem(UID_KEY(uploadUrl), uid);
  } catch {
    /* storage unavailable: resume across reloads just won't work */
  }
}

function recall(uploadUrl: string): string | null {
  try {
    return localStorage.getItem(UID_KEY(uploadUrl));
  } catch {
    return null;
  }
}

function forget(uploadUrl: string | null) {
  if (!uploadUrl) return;
  try {
    localStorage.removeItem(UID_KEY(uploadUrl));
  } catch {
    /* ignore */
  }
}

/**
 * Uploads a proof video straight to Cloudflare Stream over TUS (resumable, chunked).
 *
 * 1. tus-js-client POSTs to /api/proofs/tus. Only that request carries the
 *    wallet-signed X-LockIn-* headers; the server creates the upload at
 *    Cloudflare and returns its one-time URL (Location) and UID (stream-media-id).
 * 2. The file is PATCHed to that URL in 50 MiB chunks with automatic retries.
 *    The bytes never touch our server.
 * 3. If the same file for the same pool/day was partly uploaded before (e.g. the
 *    tab was closed), the upload resumes from the last stored offset.
 */
export function uploadProofVideo(params: {
  file: File;
  poolId: string;
  dayNumber: number;
  auth: UploadAuth;
  onProgress: (sent: number, total: number) => void;
}): ProofUploadHandle {
  const { file, poolId, dayNumber, auth } = params;
  let uid: string | null = null;
  let upload: Upload;

  const done = new Promise<string>((resolve, reject) => {
    upload = new Upload(file, {
      endpoint: "/api/proofs/tus",
      chunkSize: TUS_CHUNK_BYTES,
      retryDelays: [0, 1000, 3000, 5000, 10000, 20000],
      uploadSize: file.size,
      metadata: { filename: file.name, filetype: file.type },
      removeFingerprintOnSuccess: true,
      fingerprint: async (f) =>
        `lockin-${poolId}-${dayNumber}-${(f as File).name}-${f.size}-${(f as File).lastModified}`,
      onBeforeRequest(req: HttpRequest) {
        if (req.getMethod() !== "POST") return; // never leak auth headers to Cloudflare
        req.setHeader("X-LockIn-Wallet", auth.wallet);
        req.setHeader("X-LockIn-Issued-At", String(auth.issuedAt));
        req.setHeader("X-LockIn-Signature", auth.signature);
        req.setHeader("X-LockIn-Pool-Id", poolId);
        req.setHeader("X-LockIn-Day", String(dayNumber));
      },
      onAfterResponse(req: HttpRequest, res: HttpResponse) {
        if (req.getMethod() !== "POST") return;
        const id = res.getHeader("stream-media-id");
        const location = res.getHeader("Location");
        if (id) uid = id;
        if (id && location) remember(new URL(location, window.location.href).toString(), id);
      },
      onShouldRetry(err) {
        // Don't retry refusals from our endpoint (403 not due, 413 too big, 429 too many).
        const status = err.originalResponse?.getStatus() ?? 0;
        return !(status >= 400 && status < 500 && status !== 409 && status !== 423);
      },
      onProgress: params.onProgress,
      onSuccess() {
        const id = uid ?? (upload.url ? recall(upload.url) : null);
        forget(upload.url);
        if (id) resolve(id);
        else reject(new Error("upload finished but Cloudflare returned no video id"));
      },
      onError(err) {
        const res = (err as { originalResponse?: HttpResponse | null }).originalResponse;
        let message = err.message;
        try {
          const body = res?.getBody();
          if (body) message = (JSON.parse(body) as { error?: string }).error ?? message;
        } catch {
          /* non-JSON body */
        }
        reject(new Error(message));
      },
    });

    upload
      .findPreviousUploads()
      .then((previous) => {
        const resumable = previous.find((p) => p.uploadUrl && recall(p.uploadUrl));
        if (resumable) {
          uid = recall(resumable.uploadUrl!);
          upload.resumeFromPreviousUpload(resumable);
        }
        upload.start();
      })
      .catch(() => upload.start());
  });

  return { done, abort: () => void upload?.abort() };
}
