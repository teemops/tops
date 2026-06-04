#!/usr/bin/env python3
"""Strip the baked-in dark background from teemops-logo.png (RGBA output)."""

from __future__ import annotations

import math
import shutil
from pathlib import Path

from PIL import Image

OUT_DIR = Path(__file__).resolve().parent
SOURCE = OUT_DIR / "teemops-logo-source.png"
OUTPUT = OUT_DIR / "teemops-logo.png"
# Sampled from logo border pixels
BG_COLOR = (36, 40, 44)
TOLERANCE = 48
FEATHER = 32


def background_alpha(rgb: tuple[int, int, int]) -> int:
    distance = math.sqrt(sum((c - b) ** 2 for c, b in zip(rgb, BG_COLOR)))
    if distance <= TOLERANCE:
        return 0
    if distance >= TOLERANCE + FEATHER:
        return 255
    t = (distance - TOLERANCE) / FEATHER
    return int(255 * t)


def make_transparent(src: Path, dest: Path) -> None:
    img = Image.open(src).convert("RGBA")
    pixels = img.load()
    width, height = img.size

    for y in range(height):
        for x in range(width):
            r, g, b, a = pixels[x, y]
            if a == 0:
                continue
            alpha = background_alpha((r, g, b))
            if alpha < 255:
                pixels[x, y] = (r, g, b, min(a, alpha))

    # Trim fully transparent margins
    bbox = img.getbbox()
    if bbox:
        img = img.crop(bbox)

    max_width = 640
    if img.width > max_width:
        ratio = max_width / img.width
        img = img.resize(
            (max_width, max(1, round(img.height * ratio))),
            Image.Resampling.LANCZOS,
        )

    img.save(dest, optimize=True)


def main() -> None:
    src = SOURCE if SOURCE.exists() else OUTPUT
    if not src.exists():
        raise SystemExit(f"Missing logo source: {src}")

    if src == OUTPUT and not SOURCE.exists():
        shutil.copy2(OUTPUT, SOURCE)
        print(f"Backed up opaque logo to {SOURCE.name}")

    make_transparent(src, OUTPUT)
    print(f"Wrote {OUTPUT.name} ({OUTPUT.stat().st_size} bytes, transparent background)")


if __name__ == "__main__":
    main()
