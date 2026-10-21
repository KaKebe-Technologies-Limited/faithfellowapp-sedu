"""Makes the three FaithFellows marks the picture placeholders are drawn with, from the app's own logo (never redrawn):
mark.png (white), mark-faint.png (white at 9%, the big watermark) and mark-shadow.png (a soft dark shadow, with padding).

    python web/resources/brand/make-marks.py

The glyph is the app's own logo mark (app/assets/logo-mark.png), cut to its edges and made square.
"""
import pathlib

from PIL import Image, ImageFilter

ROOT = pathlib.Path(__file__).resolve().parents[3]
CANDIDATES = [ROOT / 'app' / 'assets' / 'logo-mark.png']
OUT = pathlib.Path(__file__).resolve().parent
SIZE = 800


def glyph_alpha(path):
    im = Image.open(path).convert('RGBA')
    alpha = im.split()[3]
    box = alpha.getbbox()
    return alpha.crop(box), box


best = max(((glyph_alpha(p), p) for p in CANDIDATES), key=lambda t: t[0][0].size[0])
alpha, _ = best[0]
side = max(alpha.size)
square = Image.new('L', (side, side), 0)
square.paste(alpha, ((side - alpha.size[0]) // 2, (side - alpha.size[1]) // 2))
square = square.resize((SIZE, SIZE), Image.LANCZOS)


def tinted(mask, rgb, opacity):
    img = Image.new('RGBA', mask.size, rgb + (0,))
    img.putalpha(mask.point(lambda v: int(v * opacity)))
    return img


tinted(square, (255, 255, 255), 1.0).save(OUT / 'mark.png', optimize=True)
tinted(square, (255, 255, 255), 0.09).save(OUT / 'mark-faint.png', optimize=True)

pad = SIZE // 5
canvas = Image.new('L', (SIZE + pad * 2, SIZE + pad * 2), 0)
canvas.paste(square, (pad, pad))
shadow_mask = canvas.filter(ImageFilter.GaussianBlur(SIZE // 28))
tinted(shadow_mask, (20, 4, 12), 0.5).save(OUT / 'mark-shadow.png', optimize=True)
print('marks written to', OUT, 'from', best[1].name, best[0][1])
