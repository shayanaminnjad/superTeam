"""Packs the small sprites in misc/atlas/sources into img/piecesAtlas.png and
img/iconsAtlas.png, then rewrites the generated block at the end of superteam.css.

Run from anywhere: python misc/atlas/pack_atlases.py
"""

import io
import math
import struct
import zlib
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[2]
SOURCES = Path(__file__).resolve().parent / "sources"
CSS_FILE = ROOT / "superteam.css"
CSS_BEGIN = "/* atlas:generated:begin */"
CSS_END = "/* atlas:generated:end */"

GUTTER = 4
MAX_ATLAS_WIDTH = 512

# A sprite is a source image (optionally cropped) fitted into the element box it is
# drawn in. CSS `contain`/`cover` can not work with atlases, so the letterbox or crop
# is baked into the atlas cell instead, and the cell is then stretched over the box.
#   fit: "exact" | ("contain", box_w, box_h, align_x, align_y) | ("cover", ...)
SPRITES = {
    "pieces": {
        "blueMeepleReady": ("blueMeepleReady.png", None, ("contain", 32, 32, 0, 0)),
        "blueMeepleExhausted": ("blueMeepleExhausted.png", None, ("contain", 32, 32, 0, 0)),
        "blueCaptainReady": ("blueCaptainMeepleReady.png", None, ("contain", 32, 32, 0, 0)),
        "blueCaptainExhausted": ("blueMeepleCaptainExhausted.png", None, ("contain", 32, 32, 0, 0)),
        "redMeepleReady": ("redMeepleReady.png", None, ("contain", 32, 32, 0, 0)),
        "redMeepleExhausted": ("redMeepleExhausted.png", None, ("contain", 32, 32, 0, 0)),
        "redCaptainReady": ("redCaptainMeepleReady.png", None, ("contain", 32, 32, 0, 0)),
        "redCaptainExhausted": ("redMeepleCaptainExhausted.png", None, ("contain", 32, 32, 0, 0)),
        "goalie": ("goalieMeeple.png", None, ("contain", 32, 32, 0, 0)),
        "ball": ("ball.png", None, ("contain", 18, 18, 0, 0)),
        "ballIcon": ("ball.png", None, ("cover", 16, 16, 0.5, 0.5)),
        "blueTargetLeft": ("blueTargetLeft.png", None, ("contain", 37, 43, 0, 0)),
        "blueTargetCenter": ("blueTargetCenter.png", None, ("contain", 37, 43, 0, 0)),
        "blueTargetRight": ("blueTargetRight.png", None, ("contain", 37, 43, 0, 0)),
        "redTargetLeft": ("redTargetLeft.png", None, ("contain", 37, 43, 0, 0)),
        "redTargetCenter": ("redTargetCenter.png", None, ("contain", 37, 43, 0, 0)),
        "redTargetRight": ("redTargetRight.png", None, ("contain", 37, 43, 0, 0)),
        "coachCube": ("coachCube.png", None, ("contain", 22, 22, 0.5, 0.5)),
    },
    "icons": {
        "morale": ("morale.png", None, "exact"),
        "outlinedMorale": ("outLinedMorale.png", None, "exact"),
        "distance": ("distance.png", None, ("contain", 32, 32, 0, 0)),
        "block": ("block.png", None, ("contain", 32, 32, 0, 0)),
        "moveForward": ("moveForwardIcon.png", None, ("contain", 16, 25, 0, 0)),
        "moveForwardNearOpponent": ("moveForwardINearOpponentcon.png", None, ("contain", 24, 27, 0, 0)),
        "moveFree": ("freeMoveIcon.png", None, ("contain", 26, 24, 0, 0)),
        "action": ("actionIcon.png", None, ("contain", 15, 16, 0, 0)),
        "move": ("moveIcon.png", None, ("contain", 19, 16, 0, 0)),
        "blackStar": ("blackStar.png", None, "exact"),
        **{
            f"die{color}{face + 1}": ("diceAtlas.png", (face * 64, row * 64, 64, 64), "exact")
            for row, color in enumerate(["Green", "White"])
            for face in range(6)
        },
    },
}

# Emitted in this order, which matters where two equally specific selectors hit the
# same element (e.g. a .resourceIcon.moraleIcon inside .pieceMoraleLine).
RULES = [
    ("pieces", "blueMeepleReady", [".piece.regular.blue.fresh"]),
    ("pieces", "blueMeepleExhausted", [".piece.regular.blue.exhausted"]),
    ("pieces", "blueCaptainReady", [".piece.captain.blue.fresh"]),
    ("pieces", "blueCaptainExhausted", [".piece.captain.blue.exhausted"]),
    ("pieces", "redMeepleReady", [".piece.regular.red.fresh"]),
    ("pieces", "redMeepleExhausted", [".piece.regular.red.exhausted"]),
    ("pieces", "redCaptainReady", [".piece.captain.red.fresh"]),
    ("pieces", "redCaptainExhausted", [".piece.captain.red.exhausted"]),
    ("pieces", "goalie", [".piece.goalie"]),
    ("pieces", "ball", [".ball"]),
    ("pieces", "ballIcon", [".resourceIcon.goalsIcon"]),
    ("icons", "morale", [".resourceIcon.moraleIcon"]),
    ("icons", "outlinedMorale", [".pieceMoraleLine .moraleIcon"]),
    ("icons", "morale", [".moraleIcon.pieceAttachment", ".floatingMorale"]),
    ("icons", "outlinedMorale", [".moraleSpent"]),
    ("icons", "distance", [".powerDistance"]),
    ("icons", "block", [".powerBlockingPieces"]),
    ("pieces", "blueTargetLeft", [".shootTargetTile.target-blue-left"]),
    ("pieces", "blueTargetCenter", [".shootTargetTile.target-blue-center"]),
    ("pieces", "blueTargetRight", [".shootTargetTile.target-blue-right"]),
    ("pieces", "redTargetLeft", [".shootTargetTile.target-red-left"]),
    ("pieces", "redTargetCenter", [".shootTargetTile.target-red-center"]),
    ("pieces", "redTargetRight", [".shootTargetTile.target-red-right"]),
    ("pieces", "coachCube", [".cube"]),
    *[
        ("icons", f"die{color}{face}", [f".superteam-dice-face.die-{color.lower()}.face-{face}"])
        for color in ["Green", "White"]
        for face in range(1, 7)
    ],
    ("icons", "outlinedMorale", [".diceLogMorale"]),
    ("icons", "distance", [".diceLogDistance"]),
    ("icons", "block", [".diceLogBlocking"]),
    ("icons", "moveForward", [".moveForward", ".moveBackward"]),
    ("icons", "moveForwardNearOpponent", [".moveForwardNearOpponent", ".moveBackNearOpponent"]),
    ("icons", "moveFree", [".moveFree"]),
    ("icons", "action", [".action"]),
    ("icons", "move", [".move"]),
    ("icons", "blackStar", [".blackStar"]),
]

ATLAS_FILES = {"pieces": "piecesAtlas.png", "icons": "iconsAtlas.png"}

KEPT_PNG_CHUNKS = {b"IHDR", b"PLTE", b"tRNS", b"IDAT", b"IEND"}


def load_png(path: Path) -> Image.Image:
    # The exported sources carry a broken iCCP chunk (bad CRC) that Pillow refuses.
    # Browsers drop it too, so keeping only the critical chunks matches what players saw.
    data = path.read_bytes()
    out = bytearray(data[:8])
    i = 8
    while i < len(data):
        length = struct.unpack(">I", data[i:i + 4])[0]
        chunk_type = data[i + 4:i + 8]
        if chunk_type in KEPT_PNG_CHUNKS:
            body = data[i + 8:i + 8 + length]
            out += struct.pack(">I", length) + chunk_type + body
            out += struct.pack(">I", zlib.crc32(chunk_type + body) & 0xFFFFFFFF)
        i += 12 + length
    return Image.open(io.BytesIO(bytes(out))).convert("RGBA")


def build_cell(image: Image.Image, fit) -> tuple:
    """Returns the cell image and the (x, y, w, h) part of it that maps onto the box."""
    iw, ih = image.size
    if fit == "exact":
        return image, (0, 0, iw, ih)

    mode, box_w, box_h, align_x, align_y = fit

    # Box aspect rarely matches the image in whole pixels, so both modes map a
    # fractional sub-rect of the cell instead of rounding the letterbox or crop.
    if mode == "contain":
        scale = min(box_w / iw, box_h / ih)
        cw, ch = box_w / scale, box_h / scale
        before_x, before_y = (cw - iw) * align_x, (ch - ih) * align_y
        px, py = math.ceil(before_x - 1e-9), math.ceil(before_y - 1e-9)
        cell_w = px + iw + math.ceil(cw - iw - before_x - 1e-9)
        cell_h = py + ih + math.ceil(ch - ih - before_y - 1e-9)
        cell = Image.new("RGBA", (cell_w, cell_h), (0, 0, 0, 0))
        cell.paste(image, (px, py))
        return cell, (px - before_x, py - before_y, cw, ch)

    scale = max(box_w / iw, box_h / ih)
    cw, ch = box_w / scale, box_h / scale
    return image, ((iw - cw) * align_x, (ih - ch) * align_y, cw, ch)


def extrude(atlas: Image.Image, cell: Image.Image, x: int, y: int, amount: int) -> None:
    # Repeats the cell's border into the gutter so scaled sampling at the edge reads
    # the cell's own pixels, like a standalone image would, instead of the gutter.
    w, h = cell.size
    for i in range(1, amount + 1):
        atlas.paste(cell.crop((0, 0, w, 1)), (x, y - i))
        atlas.paste(cell.crop((0, h - 1, w, h)), (x, y + h - 1 + i))
    column_src = atlas.crop((x, y - amount, x + 1, y + h + amount))
    column_end = atlas.crop((x + w - 1, y - amount, x + w, y + h + amount))
    for i in range(1, amount + 1):
        atlas.paste(column_src, (x - i, y - amount))
        atlas.paste(column_end, (x + w - 1 + i, y - amount))


def pack(cells: dict) -> tuple:
    """Shelf packing, tallest first. Returns (width, height, {name: (x, y, w, h)})."""
    order = sorted(cells, key=lambda n: (-cells[n].height, -cells[n].width, n))

    placed = {}
    x, y, shelf_h = GUTTER, GUTTER, 0
    used_w = 0
    for name in order:
        w, h = cells[name].size
        if x + w + GUTTER > MAX_ATLAS_WIDTH and x > GUTTER:
            x, y, shelf_h = GUTTER, y + shelf_h + GUTTER, 0
        placed[name] = (x, y, w, h)
        x += w + GUTTER
        used_w = max(used_w, x)
        shelf_h = max(shelf_h, h)

    return used_w, y + shelf_h + GUTTER, placed


def pct(value: float) -> str:
    text = f"{value:.4f}".rstrip("0").rstrip(".")
    return f"{text}%"


def css_for(atlas_name: str, atlas_size: tuple, rect: tuple, selectors: list) -> str:
    aw, ah = atlas_size
    x, y, w, h = rect
    pos_x = 0 if aw == w else x / (aw - w) * 100
    pos_y = 0 if ah == h else y / (ah - h) * 100
    return (
        ",\n".join(selectors) + " {\n"
        f"    background-image: url('img/{ATLAS_FILES[atlas_name]}');\n"
        "    background-repeat: no-repeat;\n"
        f"    background-size: {pct(aw / w * 100)} {pct(ah / h * 100)};\n"
        f"    background-position: {pct(pos_x)} {pct(pos_y)};\n"
        "}\n"
    )


def main() -> None:
    layouts = {}
    for atlas_name, sprites in SPRITES.items():
        cells, sub_rects = {}, {}
        for sprite_name, (file_name, crop, fit) in sprites.items():
            image = load_png(SOURCES / file_name)
            if crop:
                cx, cy, cw, ch = crop
                image = image.crop((cx, cy, cx + cw, cy + ch))
            cells[sprite_name], sub_rects[sprite_name] = build_cell(image, fit)

        width, height, placed = pack(cells)
        atlas = Image.new("RGBA", (width, height), (0, 0, 0, 0))
        rects = {}
        for sprite_name, (x, y, _, _) in placed.items():
            atlas.paste(cells[sprite_name], (x, y))
            extrude(atlas, cells[sprite_name], x, y, GUTTER // 2)
            sx, sy, sw, sh = sub_rects[sprite_name]
            rects[sprite_name] = (x + sx, y + sy, sw, sh)

        out_path = ROOT / "img" / ATLAS_FILES[atlas_name]
        atlas.save(out_path, optimize=True)
        layouts[atlas_name] = ((width, height), rects)
        print(f"{out_path.name}: {width}x{height}, {out_path.stat().st_size // 1024} KB")

    blocks = []
    for atlas_name, sprite_name, selectors in RULES:
        size, placed = layouts[atlas_name]
        blocks.append(css_for(atlas_name, size, placed[sprite_name], selectors))

    generated = (
        f"{CSS_BEGIN}\n"
        "/* Written by misc/atlas/pack_atlases.py, edit the script instead. */\n\n"
        + "\n".join(blocks)
        + f"{CSS_END}\n"
    )

    raw = CSS_FILE.read_bytes().decode("utf-8")
    line_ending = "\r\n" if "\r\n" in raw else "\n"
    css = raw.replace("\r\n", "\n")
    if CSS_BEGIN in css:
        start = css.index(CSS_BEGIN)
        end = css.index(CSS_END) + len(CSS_END)
        css = css[:start] + generated.rstrip("\n") + css[end:]
    else:
        css = css.rstrip("\n") + "\n\n" + generated
    CSS_FILE.write_bytes(css.replace("\n", line_ending).encode("utf-8"))
    print(f"updated {CSS_FILE.name}")


if __name__ == "__main__":
    main()
