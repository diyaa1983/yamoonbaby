import base64
import hashlib
import io
import os
import re
import sys
from pathlib import Path
from urllib.parse import quote

import fitz
import qrcode
from cryptography.hazmat.primitives.ciphers.aead import AESGCM
from PIL import Image, ImageDraw, ImageFont
from qrcode.constants import ERROR_CORRECT_M

ROOT = Path(__file__).resolve().parents[1]
BLANK = ROOT / "cards" / "yamoon-ticket-blank.jpg"
BACK = ROOT / "cards" / "yamoon-ticket-back.jpg"
FONT = Path(r"C:\Windows\Fonts\arialbd.ttf")
OUT_DIR = ROOT / "cards" / "pdf"

PAGE_W = 841.8897637795276
PAGE_H = 1190.551181102362
CARD_W = 398.2913513183594
CARD_H = 207.59315490722656
ORIGIN_X = 22.901432037353516
ORIGIN_Y = 54.62632751464844
GAP_X = 3.2639427185058594
GAP_Y = 3.50238037109375
QR = (0.7666, 0.2808, 0.1719, 0.3050)
NUM = (0.7646, 0.6586, 0.1816, 0.0532)


def card_rect(col, row):
    x = ORIGIN_X + col * (CARD_W + GAP_X)
    y = ORIGIN_Y + row * (CARD_H + GAP_Y)
    return fitz.Rect(x, y, x + CARD_W, y + CARD_H)


def load_config():
    text = (ROOT / "cards-secret.php").read_text(encoding="utf-8")
    secret = re.search(r"'secret'\s*=>\s*'([^']+)'", text)
    base_url = re.search(r"'base_url'\s*=>\s*'([^']+)'", text)
    if not secret or not base_url:
        raise SystemExit("cards-secret.php is missing secret or base_url")
    return secret.group(1), base_url.group(1)


def coupon_encrypt(coupon, secret):
    key = hashlib.sha256(secret.encode("utf-8")).digest()
    iv = os.urandom(12)
    packed = AESGCM(key).encrypt(iv, coupon.encode("utf-8"), None)
    raw = iv + packed[-16:] + packed[:-16]
    return base64.urlsafe_b64encode(raw).decode("ascii").rstrip("=")


def load_tokens(start, count):
    secret, base_url = load_config()
    cards = []
    for n in range(count):
        value = start + n
        if value > 500000:
            break
        coupon = f"{value:06d}"
        token = coupon_encrypt(coupon, secret)
        cards.append({
            "coupon": coupon,
            "label": "YM" + coupon,
            "url": base_url + "?t=" + quote(token, safe="-_.~"),
        })
    return cards


def make_qr(url, size):
    qr = qrcode.QRCode(error_correction=ERROR_CORRECT_M, box_size=12, border=0)
    qr.add_data(url)
    qr.make(fit=True)
    img = qr.make_image(fill_color="black", back_color="white").convert("RGB")
    return img.resize((size, size), Image.Resampling.LANCZOS)


def render_front(blank, card, font):
    img = blank.copy()
    w, h = img.size
    qx, qy, qw, qh = [int(round(v * dim)) for v, dim in zip(QR, (w, h, w, h))]
    nx, ny, nw, nh = [int(round(v * dim)) for v, dim in zip(NUM, (w, h, w, h))]
    qr_img = make_qr(card["url"], min(qw, qh))
    img.paste(Image.new("RGB", (qw, qh), "white"), (qx, qy))
    ox = qx + (qw - qr_img.width) // 2
    oy = qy + (qh - qr_img.height) // 2
    img.paste(qr_img, (ox, oy))
    draw = ImageDraw.Draw(img)
    label = card["label"]
    size = max(18, int(nh * 0.78))
    use_font = font.font_variant(size=size)
    bbox = draw.textbbox((0, 0), label, font=use_font)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    while tw > nw - 8 and size > 12:
        size -= 1
        use_font = font.font_variant(size=size)
        bbox = draw.textbbox((0, 0), label, font=use_font)
        tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    draw.text(
        (nx + (nw - tw) / 2 - bbox[0], ny + (nh - th) / 2 - bbox[1]),
        label,
        font=use_font,
        fill=(0, 0, 0),
    )
    buf = io.BytesIO()
    img.save(buf, "JPEG", quality=92, optimize=True)
    return buf.getvalue()


def page_to_jpeg(image):
    buf = io.BytesIO()
    image.save(buf, "JPEG", quality=92, optimize=True)
    return buf.getvalue()


def paste_card(canvas, card_img, col, row, scale):
    rect = card_rect(col, row)
    box = (
        int(round(rect.x0 * scale)),
        int(round(rect.y0 * scale)),
        int(round(rect.x1 * scale)),
        int(round(rect.y1 * scale)),
    )
    resized = card_img.resize((box[2] - box[0], box[3] - box[1]), Image.Resampling.LANCZOS)
    canvas.paste(resized, (box[0], box[1]))


def build(start=1):
    cards = load_tokens(start, 10)
    if not cards:
        raise SystemExit("no cards")
    blank = Image.open(BLANK).convert("RGB")
    back_art = Image.open(BACK).convert("RGB")
    font = ImageFont.truetype(str(FONT), 28)
    scale = 300 / 72.0
    page_size = (int(round(PAGE_W * scale)), int(round(PAGE_H * scale)))
    front_page = Image.new("RGB", page_size, "white")
    back_page = Image.new("RGB", page_size, "white")
    for i, card in enumerate(cards):
        col, row = i % 2, i // 2
        front_img = Image.open(io.BytesIO(render_front(blank, card, font))).convert("RGB")
        paste_card(front_page, front_img, col, row, scale)
        paste_card(back_page, back_art, 1 - col, row, scale)
    first, last = cards[0]["coupon"], cards[-1]["coupon"]
    out = OUT_DIR / f"A3-{first}-{last}.pdf"
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    front_page.save(
        out,
        "PDF",
        resolution=300.0,
        save_all=True,
        append_images=[back_page],
    )
    print(out)
    return out


if __name__ == "__main__":
    build(int(sys.argv[1]) if len(sys.argv) > 1 else 1)
