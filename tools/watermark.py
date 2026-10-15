#!/usr/bin/env python3
"""Stamp the Asfaltbama badge (logo, «آسفالت با ما», asfaltbama.com) on
project photos and videos, bottom right.

    python3 tools/watermark.py photo.jpg [more.jpg ...]   # in place
    python3 tools/watermark.py --video in.mp4 out.mp4

Photos are rewritten as progressive JPEG (quality 86). Videos are
re-encoded H.264 without audio. Keep the originals elsewhere: running it
twice stamps twice. Each photo also gets IPTC credit, creator and
copyright (XMP), which Google Images shows next to the picture. The importer replaces changed files in the media
library in place, so shipping a stamped file is enough.
"""
import subprocess
import sys
from pathlib import Path

from PIL import Image

BADGE = Path(__file__).resolve().parent / "watermark-badge.png"

# IPTC photo metadata (as XMP) that Google Images shows as the image credit.
XMP = (
    '<?xpacket begin="\ufeff" id="W5M0MpCehiHzreSzNTczkc9d"?>'
    '<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
    '<rdf:Description rdf:about="" xmlns:dc="http://purl.org/dc/elements/1.1/"'
    ' xmlns:photoshop="http://ns.adobe.com/photoshop/1.0/"'
    ' xmlns:xmpRights="http://ns.adobe.com/xap/1.0/rights/"'
    ' xmlns:Iptc4xmpCore="http://iptc.org/std/Iptc4xmpCore/1.0/xmlns/"'
    ' photoshop:Credit="آسفالت با ما (asfaltbama.com)"'
    ' xmpRights:WebStatement="https://asfaltbama.com/about-us/"'
    ' xmpRights:Marked="True">'
    '<dc:creator><rdf:Seq><rdf:li>آسفالت با ما - شرکت افق آپادانا پاسارگاد</rdf:li></rdf:Seq></dc:creator>'
    '<dc:rights><rdf:Alt><rdf:li xml:lang="x-default">© آسفالت با ما (asfaltbama.com)</rdf:li></rdf:Alt></dc:rights>'
    '<Iptc4xmpCore:CreatorContactInfo rdf:parseType="Resource"><Iptc4xmpCore:CiUrlWork>https://asfaltbama.com/</Iptc4xmpCore:CiUrlWork></Iptc4xmpCore:CreatorContactInfo>'
    '</rdf:Description></rdf:RDF></x:xmpmeta><?xpacket end="w"?>'
).encode("utf-8")


def stamp(path):
    badge = Image.open(BADGE)
    im = Image.open(path).convert("RGB")
    w, h = im.size
    bw = max(150, int(min(w, h * 1.2) * 0.36))
    b = badge.resize((bw, round(badge.height * bw / badge.width)), Image.LANCZOS)
    m = int(min(w, h) * 0.03)
    im.paste(b, (w - b.width - m, h - b.height - m), b)
    exif = Image.Exif()
    exif[0x013B] = "Asfaltbama - asfaltbama.com"  # Artist
    exif[0x8298] = "(c) Asfaltbama (asfaltbama.com)"  # Copyright
    im.save(path, "JPEG", quality=86, optimize=True, progressive=True, exif=exif, xmp=XMP)


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
