#!/usr/bin/env bash
# =============================================================================
#  stream_to_youtube.sh — 24/7 YouTube Live broadcast of the Trade Arena overlay
#
#  Pipeline:  Xvfb (virtual X display)  →  Chromium kiosk (capture/kiosk.ts)
#             →  FFmpeg x11grab @ 60fps + audio  →  RTMP(S) ingest (YouTube)
#
#  Every stage is supervised: if FFmpeg, Chromium or Xvfb dies, it is restarted
#  with exponential backoff, so the broadcast self-heals without operator action.
#
#  Required:   YOUTUBE_STREAM_KEY
#  Optional:   LAYOUT=landscape|portrait   (1920x1080 or 1080x1920)
#              FPS=60  VIDEO_BITRATE=9000k  AUDIO_BITRATE=128k
#              VIDEO_ENCODER=libx264|h264_nvenc|h264_vaapi   X264_PRESET=veryfast
#              OVERLAY_URL=http://localhost:5174    (serve via `pnpm preview`)
#              YOUTUBE_RTMP_URL=rtmp://a.rtmp.youtube.com/live2
#              AUDIO_FILE=/path/music.mp3           (looped; silent track if unset)
#              DISPLAY_NUM=99   LOG_DIR=./logs
#              OUTPUT_OVERRIDE=out.flv|rtmp://...   (send elsewhere, e.g. for testing)
#              MAX_RUNTIME_SEC=0                    (0 = run forever)
# =============================================================================
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
ROOT_ENV="$APP_DIR/../../.env"
if [[ -f "$ROOT_ENV" ]]; then
  set -a
  # shellcheck disable=SC1090
  source <(grep -E '^(YOUTUBE_|LAYOUT|FPS|VIDEO_|AUDIO_|OVERLAY_URL|DISPLAY_NUM|X264_PRESET|CHROME_PATH)' "$ROOT_ENV" || true)
  set +a
fi

LAYOUT="${LAYOUT:-landscape}"
FPS="${FPS:-60}"
VIDEO_BITRATE="${VIDEO_BITRATE:-9000k}"
AUDIO_BITRATE="${AUDIO_BITRATE:-128k}"
VIDEO_ENCODER="${VIDEO_ENCODER:-libx264}"
X264_PRESET="${X264_PRESET:-veryfast}"
OVERLAY_URL="${OVERLAY_URL:-http://localhost:5174}"
YOUTUBE_RTMP_URL="${YOUTUBE_RTMP_URL:-rtmp://a.rtmp.youtube.com/live2}"
DISPLAY_NUM="${DISPLAY_NUM:-99}"
LOG_DIR="${LOG_DIR:-$APP_DIR/logs}"
MAX_RUNTIME_SEC="${MAX_RUNTIME_SEC:-0}"
AUDIO_FILE="${AUDIO_FILE:-}"

case "$LAYOUT" in
  portrait)  WIDTH=1080; HEIGHT=1920 ;;
  landscape) WIDTH=1920; HEIGHT=1080 ;;
  *) echo "LAYOUT must be portrait or landscape" >&2; exit 64 ;;
esac

if [[ -n "${OUTPUT_OVERRIDE:-}" ]]; then
  OUTPUT="$OUTPUT_OVERRIDE"
else
  : "${YOUTUBE_STREAM_KEY:?YOUTUBE_STREAM_KEY is required (YouTube Studio → Go Live → Stream key)}"
  OUTPUT="${YOUTUBE_RTMP_URL%/}/${YOUTUBE_STREAM_KEY}"
fi

mkdir -p "$LOG_DIR"
export DISPLAY=":${DISPLAY_NUM}"

log() { printf '[%s] %s\n' "$(date -u +%FT%TZ)" "$*"; }
redact() { sed -E 's#(live2/)[^ ]+#\1****#g'; }

for bin in Xvfb ffmpeg node; do
  command -v "$bin" >/dev/null 2>&1 || { echo "Missing dependency: $bin" >&2; exit 69; }
done

XVFB_PID=""; KIOSK_PID=""; FFMPEG_PID=""
cleanup() {
  trap - EXIT INT TERM
  log "shutting down"
  for pid in "$FFMPEG_PID" "$KIOSK_PID" "$XVFB_PID"; do
    [[ -n "$pid" ]] && kill -TERM "$pid" 2>/dev/null || true
  done
  sleep 1
  for pid in "$FFMPEG_PID" "$KIOSK_PID" "$XVFB_PID"; do
    [[ -n "$pid" ]] && kill -KILL "$pid" 2>/dev/null || true
  done
  rm -f "/tmp/.X${DISPLAY_NUM}-lock"
}
trap cleanup EXIT INT TERM

alive() { [[ -n "${1:-}" ]] && kill -0 "$1" 2>/dev/null; }

start_xvfb() {
  if alive "$XVFB_PID"; then return; fi
  rm -f "/tmp/.X${DISPLAY_NUM}-lock" "/tmp/.X11-unix/X${DISPLAY_NUM}"
  log "starting Xvfb $DISPLAY (${WIDTH}x${HEIGHT}x24)"
  Xvfb "$DISPLAY" -screen 0 "${WIDTH}x${HEIGHT}x24" -nolisten tcp -ac +extension RANDR -noreset \
    >>"$LOG_DIR/xvfb.log" 2>&1 &
  XVFB_PID=$!
  for _ in $(seq 1 50); do
    [[ -S "/tmp/.X11-unix/X${DISPLAY_NUM}" ]] && return
    sleep 0.1
  done
  echo "Xvfb failed to start (see $LOG_DIR/xvfb.log)" >&2
  return 1
}

start_kiosk() {
  if alive "$KIOSK_PID"; then return; fi
  local kiosk_log="$LOG_DIR/kiosk.log"
  : >"$kiosk_log"
  log "starting Chromium kiosk → $OVERLAY_URL ($LAYOUT)"
  (cd "$APP_DIR" && exec node --import tsx capture/kiosk.ts --url "$OVERLAY_URL" --layout "$LAYOUT") >>"$kiosk_log" 2>&1 &
  KIOSK_PID=$!
  for _ in $(seq 1 120); do
    if grep -q "OVERLAY_READY" "$kiosk_log"; then log "overlay rendered"; return; fi
    alive "$KIOSK_PID" || { echo "kiosk exited (see $kiosk_log)" >&2; return 1; }
    sleep 0.5
  done
  echo "overlay did not become ready within 60s (see $kiosk_log)" >&2
  return 1
}

video_encoder_args() {
  local vb="${VIDEO_BITRATE%k}"
  local gop=$((FPS * 2)) # YouTube recommends a 2s keyframe interval
  case "$VIDEO_ENCODER" in
    libx264)
      echo "-c:v libx264 -preset $X264_PRESET -tune zerolatency -profile:v high -level:v 4.2 -x264-params nal-hrd=cbr:force-cfr=1" ;;
    h264_nvenc)
      echo "-c:v h264_nvenc -preset p4 -tune ll -rc cbr -profile:v high" ;;
    h264_vaapi)
      echo "-vaapi_device /dev/dri/renderD128 -c:v h264_vaapi -rc_mode CBR" ;;
    *) echo "-c:v $VIDEO_ENCODER" ;;
  esac
  echo "-b:v ${vb}k -minrate ${vb}k -maxrate ${vb}k -bufsize $((vb * 2))k -g $gop -keyint_min $gop -sc_threshold 0 -r $FPS"
}

run_ffmpeg() {
  local audio_in
  if [[ -n "$AUDIO_FILE" && -f "$AUDIO_FILE" ]]; then
    audio_in=(-stream_loop -1 -re -i "$AUDIO_FILE")
  else
    audio_in=(-f lavfi -i "anullsrc=channel_layout=stereo:sample_rate=44100")
  fi
  local vf="format=yuv420p"
  [[ "$VIDEO_ENCODER" == "h264_vaapi" ]] && vf="format=nv12,hwupload"
  local out_fmt=(-f flv -flvflags no_duration_filesize)
  [[ "$OUTPUT" == *.mp4 ]] && out_fmt=(-movflags +faststart -f mp4)
  local runtime=()
  [[ "$MAX_RUNTIME_SEC" -gt 0 ]] && runtime=(-t "$MAX_RUNTIME_SEC")

  # shellcheck disable=SC2046
  set -- ffmpeg -hide_banner -loglevel warning -stats -nostdin \
    -thread_queue_size 1024 -f x11grab -draw_mouse 0 -framerate "$FPS" -video_size "${WIDTH}x${HEIGHT}" -i "${DISPLAY}.0+0,0" \
    -thread_queue_size 1024 "${audio_in[@]}" \
    -map 0:v -map 1:a \
    -vf "$vf" $(video_encoder_args) \
    -c:a aac -b:a "$AUDIO_BITRATE" -ar 44100 -ac 2 \
    "${runtime[@]}" \
    -y "${out_fmt[@]}" "$OUTPUT"
  log "ffmpeg: $(echo "$*" | redact)"
  # Process substitution (not a pipe) so $! is FFmpeg's own PID; the stream key is redacted from logs.
  "$@" > >(redact >>"$LOG_DIR/ffmpeg.log") 2>&1 &
  FFMPEG_PID=$!
}

backoff=1
started_at=$(date +%s)
log "broadcasting $LAYOUT ${WIDTH}x${HEIGHT}@${FPS} → $(echo "$OUTPUT" | redact)"
while true; do
  if ! start_xvfb || ! start_kiosk; then
    log "startup failed, retrying in ${backoff}s"
    alive "$KIOSK_PID" && kill "$KIOSK_PID" 2>/dev/null || true
    KIOSK_PID=""
    sleep "$backoff"; backoff=$((backoff < 60 ? backoff * 2 : 60))
    continue
  fi

  run_ffmpeg
  ffmpeg_started=$(date +%s)
  # Supervise: ffmpeg exit, or a dead Xvfb/kiosk, triggers a restart of the failed stage.
  while alive "$FFMPEG_PID"; do
    if ! alive "$XVFB_PID" || ! alive "$KIOSK_PID"; then
      log "display/browser died → restarting pipeline"
      kill -TERM "$FFMPEG_PID" 2>/dev/null || true
      break
    fi
    sleep 2
  done
  wait "$FFMPEG_PID" 2>/dev/null && code=0 || code=$?
  FFMPEG_PID=""

  if [[ "$MAX_RUNTIME_SEC" -gt 0 && $(( $(date +%s) - started_at )) -ge "$MAX_RUNTIME_SEC" ]]; then
    log "max runtime reached, exiting"
    exit 0
  fi
  # A run that lasted >60s was healthy: reset backoff (e.g. YouTube ingest hiccup).
  (( $(date +%s) - ffmpeg_started > 60 )) && backoff=1
  log "ffmpeg exited (code $code), restarting in ${backoff}s — tail of log:"
  tail -n 5 "$LOG_DIR/ffmpeg.log" || true
  sleep "$backoff"; backoff=$((backoff < 60 ? backoff * 2 : 60))
done
