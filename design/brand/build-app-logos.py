#!/usr/bin/env python3
"""Build the app's logo assets from the source exports in new-logo/.

The two source files are exported independently and do not share a canvas: the
light one is 345x124 with 15px of padding, the reversed one is 452x153 with its
own. Dropped into the UI as-is, the logo visibly shifts and resizes when the
theme is toggled, because `h-8 w-auto` resolves to a different width for each.

So this normalises them: trim to the artwork, scale to a common height, then pad
both onto an identical canvas. Same pixel dimensions in, same box out, no shift.

It also cuts the mark out of the wordmark (there is a clear vertical gap between
them) and builds the favicon from it, because public/favicon.ico is an empty
0-byte file.

    python3 design/brand/build-app-logos.py

Re-run it whenever new-logo/ changes. Outputs are checked in — this is not part
of the build.
"""

from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parent.parent.parent
SRC = ROOT / "design/brand/new-logo"
OUT = ROOT / "app/public/images/brand"

SOURCES = {
    "tops-logo.png": SRC / "design3-alias-tops.png",
    "tops-logo-reversed.png": SRC / "design4-alias-tops-reversed.png",
}

# 256px tall covers every call site at 3x — the largest is h-20 (80px).
LOGO_HEIGHT = 256
# Breathing room around the artwork, as a fraction of the height. The sources
# carry their own uneven padding; this replaces it with something consistent.
PAD_RATIO = 0.06
FAVICON_SIZES = [16, 32, 48, 64, 128, 256]


def trim(image: Image.Image) -> Image.Image:
    """Crop to the artwork, discarding whatever padding the export carried."""
    bbox = image.split()[3].getbbox()
    if bbox is None:
        raise SystemExit("source image is fully transparent")
    return image.crop(bbox)


def scale_to_height(image: Image.Image, height: int) -> Image.Image:
    width = round(image.width * height / image.height)
    return image.resize((width, height), Image.LANCZOS)


def centre_on(image: Image.Image, size: tuple[int, int]) -> Image.Image:
    canvas = Image.new("RGBA", size, (0, 0, 0, 0))
    canvas.paste(image, ((size[0] - image.width) // 2, (size[1] - image.height) // 2), image)
    return canvas


def split_mark(image: Image.Image) -> Image.Image:
    """Return just the node mark — everything left of the gap before "tops".

    The wordmark's letters sit a few pixels apart, so the mark/wordmark gap is
    found by taking the widest fully transparent column run rather than the
    first one.
    """
    alpha = image.split()[3]
    columns = [max(alpha.getpixel((x, y)) for y in range(image.height)) for x in range(image.width)]

    runs, start = [], None
    for x, value in enumerate(columns):
        if value < 8:
            start = x if start is None else start
        elif start is not None:
            runs.append((start, x))
            start = None
    if not runs:
        raise SystemExit("no gap found between the mark and the wordmark")

    gap_start, _ = max(runs, key=lambda run: run[1] - run[0])
    return trim(image.crop((0, 0, gap_start, image.height)))


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)

    prepared = {}
    for name, path in SOURCES.items():
        if not path.exists():
            raise SystemExit(f"missing source: {path}")
        prepared[name] = scale_to_height(trim(Image.open(path).convert("RGBA")), LOGO_HEIGHT)

    # One canvas for both variants, sized to whichever is wider. The two exports
    # differ by ~1% in aspect ratio; centring absorbs it.
    pad = round(LOGO_HEIGHT * PAD_RATIO)
    canvas = (max(image.width for image in prepared.values()) + pad * 2, LOGO_HEIGHT + pad * 2)

    for name, image in prepared.items():
        centre_on(image, canvas).save(OUT / name)
        print(f"  {name}  {canvas[0]}x{canvas[1]}")

    # Favicon from the light mark — green/navy/orange reads on light and dark
    # browser chrome alike, which the reversed mark's white outline does not.
    mark = split_mark(trim(Image.open(SOURCES["tops-logo.png"]).convert("RGBA")))
    side = max(mark.size)
    square = centre_on(mark, (side, side))

    square.resize((512, 512), Image.LANCZOS).save(OUT / "tops-mark.png")
    print("  tops-mark.png  512x512")

    # Save from the largest size — the ICO plugin derives the smaller entries
    # from whatever image it is handed, so handing it the 16px one caps them all.
    largest = max(FAVICON_SIZES)
    square.resize((largest, largest), Image.LANCZOS).save(
        ROOT / "app/public/favicon.ico",
        format="ICO",
        sizes=[(size, size) for size in FAVICON_SIZES],
    )
    print(f"  favicon.ico  {', '.join(f'{s}x{s}' for s in FAVICON_SIZES)}")


if __name__ == "__main__":
    print("Building app logo assets from design/brand/new-logo/")
    main()
