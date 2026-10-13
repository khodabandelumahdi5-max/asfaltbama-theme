#!/usr/bin/env python3
"""Stamp the Asfaltbama badge (logo, «آسفالت با ما», asfaltbama.com) on
project photos and videos, bottom right.

    python3 tools/watermark.py photo.jpg [more.jpg ...]   # in place
    python3 tools/watermark.py --video in.mp4 out.mp4

Photos are rewritten as progressive JPEG (quality 86). Videos are
re-encoded H.264 without audio. Keep the originals elsewhere: running it
twice stamps twice. The importer replaces changed files in the media
library in place, so shipping a stamped file is enough.
"""
import subprocess
import sys
from pathlib import Path

from PIL import Image

BADGE = Path(__file__).resolve().parent / "watermark-badge.png"


def stamp(path):
    badge = Image.open(BADGE)
    im = Image.open(path).convert("RGB")
    w, h = im.size
    bw = max(240, int(min(w, h * 1.2) * 0.36))
    b = badge.resize((bw, round(badge.height * bw / badge.width)), Image.LANCZOS)
    m = int(min(w, h) * 0.03)
    im.paste(b, (w - b.width - m, h - b.height - m), b)
    im.save(path, "JPEG", quality=86, optimize=True, progressive=True)


def stamp_video(src, dst):
    import imageio_ffmpeg
    width = 300
    subprocess.run([imageio_ffmpeg.get_ffmpeg_exe(), "-v", "error", "-y", "-i", src, "-i", str(BADGE),
                    "-filter_complex", f"[1]scale={width}:-1[b];[0][b]overlay=W-w-16:H-h-16",
                    "-c:v", "libx264", "-profile:v", "high", "-crf", "27", "-preset", "slow",
                    "-pix_fmt", "yuv420p", "-movflags", "+faststart", "-an", dst], check=True)


if __name__ == "__main__":
    if sys.argv[1:2] == ["--video"]:
        stamp_video(sys.argv[2], sys.argv[3])
    else:
        for p in sys.argv[1:]:
            stamp(p)
            print("stamped", p)
