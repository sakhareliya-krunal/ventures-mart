from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "public" / "images" / "hero"

# Output portrait frames for tall mobile/tablet heroes (9:16).
TARGET_W = 1080
TARGET_H = 1920

SOURCES = [
    # name, path, focus_x (0-1), focus_y (0-1)
    ("lunch-1", ROOT / "public/products/lunch-box/hero-showcase/koi-koi-steel-lunch-box-blue.jpeg", 0.50, 0.42),
    ("lunch-2", ROOT / "public/products/lunch-box/hero-showcase/printed-steel-lunch-box-purple.jpeg", 0.50, 0.42),
    ("lunch-3", ROOT / "public/products/lunch-box/hero-showcase/safari-lunch-box-owl-purple.png", 0.42, 0.45),
    ("lunch-4", ROOT / "public/products/lunch-box/delicious-steel-lunch-box/01.jpg", 0.50, 0.40),
    ("lunch-5", ROOT / "public/products/lunch-box/bear-family-lunch-box/01.jpg", 0.50, 0.42),
    ("toy-1", ROOT / "public/products/toys/wooden-building-blocks/01.jpg", 0.50, 0.45),
    # Bear is on the right; keep headroom and include basket when possible.
    ("toy-2", ROOT / "public/products/toys/soft-plush-buddy/01.jpg", 0.58, 0.48),
    ("toy-3", ROOT / "public/products/toys/color-pattern-tiles/01.jpg", 0.50, 0.50),
]


def cover_crop(im: Image.Image, target_w: int, target_h: int, focus_x: float, focus_y: float) -> Image.Image:
    """Scale to cover target aspect, then crop around the focal point."""
    src_w, src_h = im.size
    target_ratio = target_w / target_h
    src_ratio = src_w / src_h

    if src_ratio > target_ratio:
        # Source wider than target: crop left/right.
        crop_h = src_h
        crop_w = int(round(src_h * target_ratio))
    else:
        # Source taller/narrower: crop top/bottom.
        crop_w = src_w
        crop_h = int(round(src_w / target_ratio))

    crop_w = min(crop_w, src_w)
    crop_h = min(crop_h, src_h)

    cx = focus_x * src_w
    cy = focus_y * src_h
    left = int(round(cx - crop_w / 2))
    top = int(round(cy - crop_h / 2))
    left = max(0, min(left, src_w - crop_w))
    top = max(0, min(top, src_h - crop_h))

    cropped = im.crop((left, top, left + crop_w, top + crop_h))
    return cropped.resize((target_w, target_h), Image.Resampling.LANCZOS)


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)

    for name, src, focus_x, focus_y in SOURCES:
        im = Image.open(src).convert("RGB")
        framed = cover_crop(im, TARGET_W, TARGET_H, focus_x, focus_y)

        jpg = OUT / f"{name}.jpg"
        webp = OUT / f"{name}.webp"
        framed.save(jpg, "JPEG", quality=84, optimize=True)
        framed.save(webp, "WEBP", quality=80, method=6)
        print(
            f"{name}: {framed.size[0]}x{framed.size[1]} "
            f"focus=({focus_x:.2f},{focus_y:.2f}) "
            f"jpg={jpg.stat().st_size // 1024}k webp={webp.stat().st_size // 1024}k"
        )


if __name__ == "__main__":
    main()
