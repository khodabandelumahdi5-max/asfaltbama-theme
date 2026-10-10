// Proof video limits, shared by the recorder, the upload endpoint and Cloudflare.
export const MAX_VIDEO_SECONDS = 120;
export const MAX_VIDEO_BYTES = 512 * 1024 * 1024;
/** Cloudflare requires TUS chunks of at least 5 MiB, in multiples of 256 KiB; it recommends 50 MiB. */
export const TUS_CHUNK_BYTES = 50 * 1024 * 1024;
