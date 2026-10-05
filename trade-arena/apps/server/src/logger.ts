import pino from "pino";
import { config, isProd } from "./config";

export const loggerOptions: pino.LoggerOptions = {
  level: config.LOG_LEVEL,
  base: { instance: config.INSTANCE_ID },
  redact: ["req.headers.authorization", "req.headers['x-internal-key']", "req.headers['x-signature']"],
  ...(isProd
    ? {}
    : { transport: { target: "pino-pretty", options: { translateTime: "HH:MM:ss.l", ignore: "pid,hostname" } } }),
};

export const logger = pino(loggerOptions);
export type Logger = pino.Logger;
