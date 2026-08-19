#!/usr/bin/env python3
"""Generate the two derived brand assets in public/.

- og.png      the 1200x630 card shown when teemops.com is shared. Link previews
              are the first thing most visitors see, because the site is reached
              from a message someone sent them rather than from search.
- favicon.ico browsers request /favicon.ico whether or not a <link rel="icon">
              points elsewhere, so without this every visit logs a 404.

Run after changing the headline or the logo:

    python3 www/build-assets.py
"""
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

HERE = Path(__file__).parent
OUT = HERE / "public" / "og.png"
FAVICON = HERE / "public" / "favicon.ico"
LOGO = HERE / "public" / "logo.png"

W, H = 1200, 630
BG = (10, 14, 23)
TEXT = (233, 237, 245)
MUTED = (152, 163, 184)
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


def main() -> None:
    img = Image.new("RGBA", (W, H), (*BG, 255))
    glow(img)
    draw = ImageDraw.Draw(img)

    margin = 84
    y = 96

    if LOGO.exists():
        logo = Image.open(LOGO).convert("RGBA")
        logo.thumbnail((78, 78), Image.LANCZOS)
        img.alpha_composite(logo, (margin, y - 8))
        draw.text((margin + 96, y + 12), "Teemops", font=font("bold", 42), fill=TEXT)

    y += 132
    for line in ("Open source AWS security", "scanning you run yourself"):
        draw.text((margin, y), line, font=font("bold", 62), fill=TEXT)
        y += 76

    y += 26
    for line in (
        "Overly permissive IAM roles, public S3 buckets, open",
        "security groups. 74 checks, each with the fix.",
    ):
        draw.text((margin, y), line, font=font("regular", 30), fill=MUTED)
        y += 42

    # Footer rule and badge row.
    draw.line((margin, H - 106, W - margin, H - 106), fill=(255, 255, 255, 28), width=1)
    draw.text(
        (margin, H - 76),
        "Apache 2.0   ·   Self-hosted   ·   No paid tier",
        font=font("mono", 25),
        fill=BRAND,
    )
    draw.text(
        (W - margin - 190, H - 76), "teemops.com", font=font("mono", 25), fill=MUTED
    )

    img.convert("RGB").save(OUT, "PNG", optimize=True)
    print(f"wrote {OUT} ({OUT.stat().st_size // 1024} KB)")


def favicon() -> None:
    """Multi-size .ico from the logo. Keeps the transparent background so the
    mark sits on whatever colour the browser's tab strip happens to be."""
    if not LOGO.exists():
        print(f"skipped {FAVICON} — {LOGO} is missing")
        return
    src = Image.open(LOGO).convert("RGBA")
    src.save(FAVICON, format="ICO", sizes=[(16, 16), (32, 32), (48, 48)])
    print(f"wrote {FAVICON} ({FAVICON.stat().st_size // 1024} KB)")


if __name__ == "__main__":
    main()
    favicon()
