export class AppError extends Error {
  constructor(
    public readonly code: string,
    message: string,
    public readonly statusCode = 400,
  ) {
    super(message);
    this.name = "AppError";
  }
}

export function toAckError(err: unknown): { ok: false; error: string; code: string } {
  if (err instanceof AppError) return { ok: false, error: err.message, code: err.code };
  return { ok: false, error: "Internal error", code: "INTERNAL" };
}
