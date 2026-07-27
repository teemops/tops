#!/usr/bin/env python3
"""Build Teemops combined mark: Teem 3-links + ops cog."""

from __future__ import annotations

import math
from pathlib import Path

from PIL import Image, ImageDraw

BRAND_ORANGE = (246, 128, 0)  # #F68000 — Teem brand orange
DARK_BG = (36, 40, 44)  # #24282C — preview background
OUT_DIR = Path(__file__).resolve().parent


def rounded_rect(draw: ImageDraw.ImageDraw, xy, radius: float, fill):
    x0, y0, x1, y1 = xy
    draw.rounded_rectangle((x0, y0, x1, y1), radius=radius, fill=fill)


def cog_polygon(cx: float, cy: float, outer_r: float, inner_r: float, teeth: int = 6) -> list[tuple[float, float]]:
    """Six-tooth cog with softly rounded teeth (matches teemops reference)."""
    points: list[tuple[float, float]] = []
    step = 2 * math.pi / teeth
    gap = step * 0.34
    tooth = step - gap

    for i in range(teeth):
        base = i * step - math.pi / 2
        a0 = base + gap / 2
        a1 = a0 + tooth * 0.55
        a2 = a0 + tooth * 0.45
        a3 = base + step - gap / 2

        for angle, radius in (
            (a0, inner_r),
            (a1, outer_r),
            (a2, outer_r),
            (a3, inner_r),
        ):
            points.append((cx + math.cos(angle) * radius, cy + math.sin(angle) * radius))

    return points


def bar_polygon(x0, y0, x1, y1, width: float) -> list[tuple[float, float]]:
    angle = math.atan2(y1 - y0, x1 - x0)
    dx = math.sin(angle) * width / 2
    dy = math.cos(angle) * width / 2
    return [
        (x0 - dx, y0 + dy),
        (x0 + dx, y0 - dy),
        (x1 + dx, y1 - dy),
        (x1 - dx, y1 + dy),
    ]


def polygon_to_svg(points: list[tuple[float, float]]) -> str:
    if not points:
        return ""
    parts = [f"M {points[0][0]:.1f} {points[0][1]:.1f}"]
    parts.extend(f"L {x:.1f} {y:.1f}" for x, y in points[1:])
    parts.append("Z")
    return " ".join(parts)


def draw_mark(
    size: int,
    *,
    background: tuple[int, int, int, int] | None = None,
    padding: float = 0.08,
) -> tuple[Image.Image, dict]:
    """Render the combined Teem links + cog mark."""
    img = Image.new("RGBA", (size, size), background or (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)

    s = size
    pad = s * padding
    usable = s - 2 * pad

    hub_cx = pad + usable * 0.28
    hub_cy = pad + usable * 0.50
    hub_outer = usable * 0.22
    hub_inner = hub_outer * 0.70
    hub_hole = hub_outer * 0.30

    node_size = usable * 0.17
    node_radius = node_size * 0.22
    top_cx = pad + usable * 0.78
    top_cy = pad + usable * 0.28
    bot_cx = top_cx
    bot_cy = pad + usable * 0.72
    bar_w = usable * 0.085

    hub_right_x = hub_cx + hub_inner * 0.95
    hub_right_top_y = hub_cy - hub_inner * 0.42
    hub_right_bot_y = hub_cy + hub_inner * 0.42

    top_left = (top_cx - node_size / 2, top_cy - node_size / 2)
    bot_left = (bot_cx - node_size / 2, bot_cy - node_size / 2)

    top_bar = bar_polygon(hub_right_x, hub_right_top_y, top_left[0], top_cy, bar_w)
    bot_bar = bar_polygon(hub_right_x, hub_right_bot_y, bot_left[0], bot_cy, bar_w)
    cog = cog_polygon(hub_cx, hub_cy, hub_outer, hub_inner)

    draw.polygon(top_bar, fill=BRAND_ORANGE)
    draw.polygon(bot_bar, fill=BRAND_ORANGE)

    rounded_rect(
        draw,
        (top_left[0], top_left[1], top_left[0] + node_size, top_left[1] + node_size),
        node_radius,
        BRAND_ORANGE,
    )
    rounded_rect(
        draw,
        (bot_left[0], bot_left[1], bot_left[0] + node_size, bot_left[1] + node_size),
        node_radius,
        BRAND_ORANGE,
    )

    draw.polygon(cog, fill=BRAND_ORANGE)
    hole_fill = background or (0, 0, 0, 0)
    draw.ellipse(
        (hub_cx - hub_hole, hub_cy - hub_hole, hub_cx + hub_hole, hub_cy + hub_hole),
        fill=hole_fill,
    )

    meta = {
        "top_bar": top_bar,
        "bot_bar": bot_bar,
        "cog": cog,
        "top_rect": (top_left[0], top_left[1], top_left[0] + node_size, top_left[1] + node_size, node_radius),
        "bot_rect": (bot_left[0], bot_left[1], bot_left[0] + node_size, bot_left[1] + node_size, node_radius),
        "hole": (hub_cx, hub_cy, hub_hole),
    }
    return img, meta


def build_svg(meta: dict, scale: float = 1.0) -> str:
    orange = "#F68000"

    def sxy(points):
        return [(x * scale, y * scale) for x, y in points]

    tx0, ty0, tx1, ty1, tr = meta["top_rect"]
    bx0, by0, bx1, by1, br = meta["bot_rect"]
    hx, hy, hr = meta["hole"]

    return f"""<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {512 * scale:.0f} {512 * scale:.0f}" fill="none">
  <title>Teemops mark</title>
  <desc>Teem three-link topology with operations cog hub — icon only, no wordmark</desc>
  <g fill="{orange}">
    <path d="{polygon_to_svg(sxy(meta['top_bar']))}"/>
    <path d="{polygon_to_svg(sxy(meta['bot_bar']))}"/>
    <rect x="{tx0 * scale:.1f}" y="{ty0 * scale:.1f}" width="{(tx1 - tx0) * scale:.1f}" height="{(ty1 - ty0) * scale:.1f}" rx="{tr * scale:.1f}"/>
    <rect x="{bx0 * scale:.1f}" y="{by0 * scale:.1f}" width="{(bx1 - bx0) * scale:.1f}" height="{(by1 - by0) * scale:.1f}" rx="{br * scale:.1f}"/>
    <path d="{polygon_to_svg(sxy(meta['cog']))}"/>
  </g>
  <circle cx="{hx * scale:.1f}" cy="{hy * scale:.1f}" r="{hr * scale:.1f}" fill="black" fill-opacity="0"/>
</svg>
"""


def main() -> None:
    base_size = 512
    _, meta = draw_mark(base_size)

    svg_path = OUT_DIR / "teemops-mark.svg"
    svg_path.write_text(build_svg(meta, scale=base_size / 512), encoding="utf-8")

    exports = [
        ("teemops-mark.png", 512, None),
        ("teemops-mark-256.png", 256, None),
        ("teemops-mark-128.png", 128, None),
        ("teemops-mark-64.png", 64, None),
        ("teemops-mark-on-dark.png", 512, (*DARK_BG, 255)),
    ]

    for filename, size, bg in exports:
        img, _ = draw_mark(size, background=bg)
        img.save(OUT_DIR / filename, optimize=True)
        print(f"Wrote {filename} ({size}x{size})")

    print(f"Wrote {svg_path.name}")


if __name__ == "__main__":
    main()
