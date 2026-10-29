#!/usr/bin/env python3
"""Generate the two derived brand assets in public/.

- og.png      the 1200x630 card shown when teemops.com is shared. Link previews
              are the first thing most visitors see, because the site is reached
              from a message someone sent them rather than from search.
- favicon.ico browsers request /favicon.ico whether or not a <link rel="icon">
              points elsewhere, so without this every visit logs a 404.

Both start from Teem's own logo files on teem.cloud, which the page also loads
directly, so the brand has one source. Needs network access and rsvg-convert
(librsvg) for the favicon. Run after changing the headline or the logo:

    python3 www/build-assets.py
"""
import io
import subprocess
import urllib.request
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

HERE = Path(__file__).parent
OUT = HERE / "public" / "og.png"
FAVICON = HERE / "public" / "favicon.ico"
WORDMARK_URL = "https://teem.cloud/logo-dark.png"  # the Teem logo, white wordmark
MARK_URL = "https://teem.cloud/favicon.svg"  # the Teem mark alone, teem.cloud's favicon

W, H = 1200, 630
BG = (15, 19, 27)
TEXT = (232, 235, 241)
MUTED = (154, 163, 178)
BRAND = (245, 130, 31)

FONT_CANDIDATES = {
    "bold": [
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
        "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf",
    ],
    "regular": [
        "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
        "/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf",
    ],
    "mono": [
        "/usr/share/fonts/truetype/dejavu/DejaVuSansMono.ttf",
        "/usr/share/fonts/truetype/liberation/LiberationMono-Regular.ttf",
    ],
}


def font(kind: str, size: int) -> ImageFont.FreeTypeFont:
    for path in FONT_CANDIDATES[kind]:
        if Path(path).exists():
            return ImageFont.truetype(path, size)
    return ImageFont.load_default(size)


def glow(img: Image.Image) -> None:
    """A soft brand-coloured wash in the top-left, echoing the page hero."""
    overlay = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    draw = ImageDraw.Draw(overlay)
    cx, cy, radius = 120, -40, 620
    steps = 60
    for i in range(steps, 0, -1):
        r = radius * i / steps
        alpha = int(30 * (1 - i / steps) ** 1.6)
        draw.ellipse((cx - r, cy - r * 0.72, cx + r, cy + r * 0.72), fill=(*BRAND, alpha))
    img.alpha_composite(overlay)


def fetch(url: str) -> bytes:
    # teem.cloud's Cloudflare answers 403 to urllib's default user agent.
    req = urllib.request.Request(url, headers={"User-Agent": "teemops-www-build-assets"})
    with urllib.request.urlopen(req, timeout=20) as res:
        return res.read()


def main() -> None:
    img = Image.new("RGBA", (W, H), (*BG, 255))
    glow(img)
    draw = ImageDraw.Draw(img)

    margin = 84
    y = 96

    logo = Image.open(io.BytesIO(fetch(WORDMARK_URL))).convert("RGBA")
    logo.thumbnail((200, 75), Image.LANCZOS)
    img.alpha_composite(logo, (margin, y - 8))
    x = margin + logo.width + 22
    draw.line((x, y, x, y + 56), fill=(255, 255, 255, 60), width=2)
    draw.text((x + 22, y + 6), "teemops", font=font("bold", 40), fill=MUTED)

    y += 132
    for line in ("Find the AWS security risks", "that matter, every day"):
        draw.text((margin, y), line, font=font("bold", 62), fill=TEXT)
        y += 76

    y += 26
    for line in (
        "149 checks across 17 AWS services, a fix for every",
        "finding, and read-only access. Scanned daily.",
    ):
        draw.text((margin, y), line, font=font("regular", 30), fill=MUTED)
        y += 42

    # Footer rule and offer row.
    draw.line((margin, H - 106, W - margin, H - 106), fill=(255, 255, 255, 28), width=1)
    draw.text(
        (margin, H - 76),
        "Your first scan is free",
        font=font("mono", 25),
        fill=BRAND,
    )
    draw.text(
        (W - margin - 190, H - 76), "teemops.com", font=font("mono", 25), fill=MUTED
    )

    img.convert("RGB").save(OUT, "PNG", optimize=True)
    print(f"wrote {OUT} ({OUT.stat().st_size // 1024} KB)")


def favicon() -> None:
    """Multi-size .ico rasterised from teem.cloud's favicon.svg, so /favicon.ico
    shows the same mark as the <link rel="icon"> that points at the SVG."""
    png = subprocess.run(
        ["rsvg-convert", "-w", "48", "-h", "48"],
        input=fetch(MARK_URL), capture_output=True, check=True,
    ).stdout
    src = Image.open(io.BytesIO(png)).convert("RGBA")
    src.save(FAVICON, format="ICO", sizes=[(16, 16), (32, 32), (48, 48)])
    print(f"wrote {FAVICON} ({FAVICON.stat().st_size // 1024} KB)")


if __name__ == "__main__":
    main()
    favicon()
