import base64
import hashlib
import io
import os
import re
import sys
from concurrent.futures import ProcessPoolExecutor, as_completed
from pathlib import Path
from urllib.parse import quote

import fitz
import qrcode
from cryptography.hazmat.primitives.ciphers.aead import AESGCM
from PIL import Image, ImageDraw, ImageFont
from qrcode.constants import ERROR_CORRECT_M

ROOT = Path(__file__).resolve().parents[1]
FRONT_PDF = ROOT / "cards" / "Final" / "1.pdf"
FONT = Path(r"C:\Windows\Fonts\arialbd.ttf")
OUT_DIR = ROOT / "cards" / "pdf"

# Inner white of the gold QR frame / number capsule on cards/Final/1.pdf
QR = (0.7848, 0.2652, 0.1661, 0.2944)
NUM = (0.7832, 0.6179, 0.1773, 0.0575)

FRONT_CARDS = [
    (0.07, 4.03, 377.07, 195.41),
    (0.0, 217.81, 376.98, 409.2),
    (0.18, 434.86, 377.19, 626.24),
    (0.18, 651.14, 377.19, 841.89),
    (399.74, 0.0, 591.12, 376.01),
    (399.74, 398.01, 591.12, 775.02),
]


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


def coupon_card(value, secret, base_url):
    coupon = f"{value:06d}"
    token = coupon_encrypt(coupon, secret)
    return {
        "coupon": coupon,
        "label": "YM" + coupon,
        "url": base_url + "?t=" + quote(token, safe="-_.~"),
    }


def load_tokens(start, count):
    secret, base_url = load_config()
    cards = []
    for n in range(count):
        value = start + n
        if value > 500000:
            break
        cards.append(coupon_card(value, secret, base_url))
    return cards


_FONT = None


def font_at(size):
    global _FONT
    if _FONT is None:
        _FONT = str(FONT)
    return ImageFont.truetype(_FONT, size)


def to_1bit_png(img):
    bw = img.convert("1", dither=Image.Dither.NONE)
    buf = io.BytesIO()
    bw.save(buf, "PNG", compress_level=9)
    return buf.getvalue()


def make_qr_png(url, size=280):
    qr = qrcode.QRCode(error_correction=ERROR_CORRECT_M, box_size=6, border=0)
    qr.add_data(url)
    qr.make(fit=True)
    img = qr.make_image(fill_color="black", back_color="white").convert("RGB")
    if img.size[0] != size:
        img = img.resize((size, size), Image.Resampling.NEAREST)
    return to_1bit_png(img)


def make_label_png(text, dest_w, dest_h, rotate_cw=False):
    scale = 5
    if rotate_cw:
        rw = max(12, int(round(dest_h * scale)))
        rh = max(12, int(round(dest_w * scale)))
    else:
        rw = max(12, int(round(dest_w * scale)))
        rh = max(12, int(round(dest_h * scale)))
    img = Image.new("RGB", (rw, rh), "white")
    draw = ImageDraw.Draw(img)
    size = max(20, int(rh * 0.72))
    font = font_at(size)
    bbox = draw.textbbox((0, 0), text, font=font)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    while tw > rw - 10 and size > 12:
        size -= 1
        font = font_at(size)
        bbox = draw.textbbox((0, 0), text, font=font)
        tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    draw.text(
        ((rw - tw) / 2 - bbox[0], (rh - th) / 2 - bbox[1]),
        text,
        font=font,
        fill=(0, 0, 0),
    )
    if rotate_cw:
        img = img.transpose(Image.Transpose.ROTATE_270)
    return to_1bit_png(img)


def card_boxes(bbox):
    x0, y0, x1, y1 = bbox
    cw, ch = x1 - x0, y1 - y0
    landscape = cw >= ch
    if landscape:
        qr = fitz.Rect(
            x0 + QR[0] * cw,
            y0 + QR[1] * ch,
            x0 + (QR[0] + QR[2]) * cw,
            y0 + (QR[1] + QR[3]) * ch,
        )
        num = fitz.Rect(
            x0 + NUM[0] * cw,
            y0 + NUM[1] * ch,
            x0 + (NUM[0] + NUM[2]) * cw,
            y0 + (NUM[1] + NUM[3]) * ch,
        )
        return qr, num, False
    W, H = ch, cw
    qx, qy, qw, qh = QR[0] * W, QR[1] * H, QR[2] * W, QR[3] * H
    nx, ny, nw, nh = NUM[0] * W, NUM[1] * H, NUM[2] * W, NUM[3] * H
    qr = fitz.Rect(
        x0 + (H - (qy + qh)),
        y0 + qx,
        x0 + (H - qy),
        y0 + qx + qw,
    )
    num = fitz.Rect(
        x0 + (H - (ny + nh)),
        y0 + nx,
        x0 + (H - ny),
        y0 + nx + nw,
    )
    return qr, num, True


def inset(rect, left, top, right, bottom):
    return fitz.Rect(rect.x0 + left, rect.y0 + top, rect.x1 - right, rect.y1 - bottom)


def qr_square(rect):
    inner = inset(rect, 1.15, 1.15, 1.15, 1.15)
    side = min(inner.width, inner.height)
    cx = (inner.x0 + inner.x1) / 2
    cy = (inner.y0 + inner.y1) / 2
    return inner, fitz.Rect(cx - side / 2, cy - side / 2, cx + side / 2, cy + side / 2)


def overlay_front(page, cards):
    for i, bbox in enumerate(FRONT_CARDS):
        qr, num, rotated = card_boxes(bbox)
        card = cards[i] if i < len(cards) else None
        cover = inset(qr, 0.65, 0.65, 0.65, 0.25)
        if rotated:
            inner_num = inset(num, 0.55, 1.55, 0.55, 1.55)
        else:
            inner_num = inset(num, 1.55, 0.55, 1.55, 0.55)
        page.draw_rect(cover, color=(1, 1, 1), fill=(1, 1, 1), width=0)
        page.draw_rect(inner_num, color=(1, 1, 1), fill=(1, 1, 1), width=0)
        if not card:
            continue
        _inner_qr, qr_sq = qr_square(qr)
        page.insert_image(qr_sq, stream=make_qr_png(card["url"]), keep_proportion=True)
        page.insert_image(
            inner_num,
            stream=make_label_png(card["label"], inner_num.width, inner_num.height, rotate_cw=rotated),
            keep_proportion=False,
        )


def stack_values(page_index, pages, start_number=1, slots=6):
    base = start_number - 1
    return [base + slot * pages + page_index + 1 for slot in range(slots)]


def page_cards(page_index, pages, start_number, max_number, tokens, slots=6):
    cards = []
    for value in stack_values(page_index, pages, start_number, slots):
        if value > max_number:
            cards.append(None)
        else:
            cards.append(tokens[value])
    return cards


def template_jpeg(cache_path=None):
    cache_path = Path(cache_path) if cache_path else OUT_DIR / "_a4-template.jpg"
    if cache_path.exists() and cache_path.stat().st_mtime >= FRONT_PDF.stat().st_mtime:
        src = fitz.open(FRONT_PDF)
        rect = fitz.Rect(src[0].rect)
        src.close()
        return cache_path.read_bytes(), rect, cache_path
    src = fitz.open(FRONT_PDF)
    rect = fitz.Rect(src[0].rect)
    pix = src[0].get_pixmap(matrix=fitz.Matrix(300 / 72, 300 / 72), alpha=False)
    src.close()
    img = Image.frombytes("RGB", (pix.width, pix.height), pix.samples)
    buf = io.BytesIO()
    img.save(buf, "JPEG", quality=90, optimize=True, subsampling=0)
    data = buf.getvalue()
    cache_path.parent.mkdir(parents=True, exist_ok=True)
    cache_path.write_bytes(data)
    return data, rect, cache_path


def build(start=1, count=6):
    cards = load_tokens(start, count)
    if not cards:
        raise SystemExit("no cards")
    out = fitz.open()
    for offset in range(0, len(cards), 6):
        chunk = cards[offset:offset + 6]
        front = fitz.open(FRONT_PDF)
        overlay_front(front[0], chunk)
        out.insert_pdf(front)
        front.close()
    first, last = cards[0]["coupon"], cards[-1]["coupon"]
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    path = OUT_DIR / f"A4-{first}-{last}.pdf"
    out.save(path)
    out.close()
    print(path)
    return path


def write_stack_chunk(job):
    (
        pages,
        chunk_start,
        chunk_end,
        dest,
        bg_path,
        width,
        height,
        start_number,
        max_number,
    ) = job
    dest = Path(dest)
    path = dest / f"A4-p{chunk_start + 1:04d}-p{chunk_end:04d}.pdf"
    if path.exists() and path.stat().st_size > 50000:
        return f"skip {path.name}"
    secret, base_url = load_config()
    needed = []
    for p in range(chunk_start, chunk_end):
        for value in stack_values(p, pages, start_number):
            if value <= max_number:
                needed.append(value)
    tokens = {n: coupon_card(n, secret, base_url) for n in needed}
    bg = Path(bg_path).read_bytes()
    out = fitz.open()
    bg_xref = None
    for p in range(chunk_start, chunk_end):
        page = out.new_page(width=width, height=height)
        if bg_xref is None:
            bg_xref = page.insert_image(page.rect, stream=bg, keep_proportion=False)
        else:
            page.insert_image(page.rect, xref=bg_xref, keep_proportion=False)
        overlay_front(page, page_cards(p, pages, start_number, max_number, tokens))
        if (p + 1) % 25 == 0:
            print(f"page {p + 1}/{pages} ({path.name})", flush=True)
    out.save(path, deflate=True, garbage=3)
    out.close()
    mb = path.stat().st_size / 1048576
    return f"wrote {path.name} ({mb:.1f} MB)"


def run_jobs(jobs, workers):
    workers = max(1, min(workers, len(jobs)))
    errors = []
    if workers == 1:
        for job in jobs:
            print(write_stack_chunk(job), flush=True)
        return
    with ProcessPoolExecutor(max_workers=workers) as pool:
        futs = [pool.submit(write_stack_chunk, job) for job in jobs]
        for fut in as_completed(futs):
            try:
                print(fut.result(), flush=True)
            except Exception as exc:
                errors.append(str(exc))
                print(f"ERROR {exc}", flush=True)
    if errors:
        raise SystemExit(f"{len(errors)} chunk(s) failed")


def build_stack(
    pages=2500,
    chunk=250,
    start_page=1,
    end_page=None,
    out_dir=None,
    workers=3,
    start_number=1,
    max_number=500000,
    bg_path=None,
    rect=None,
):
    end_page = pages if end_page is None else end_page
    dest = Path(out_dir) if out_dir else OUT_DIR / f"A4-cutstack-{pages}"
    dest.mkdir(parents=True, exist_ok=True)
    if bg_path is None or rect is None:
        print("rendering shared A4 background")
        _data, rect, bg_path = template_jpeg()
    jobs = []
    for chunk_start in range(start_page - 1, end_page, chunk):
        chunk_end = min(end_page, chunk_start + chunk)
        jobs.append((
            pages,
            chunk_start,
            chunk_end,
            str(dest),
            str(bg_path),
            float(rect.width),
            float(rect.height),
            int(start_number),
            int(max_number),
        ))
    print(
        f"building {end_page - start_page + 1} pages in {len(jobs)} files "
        f"(YM{start_number:06d}+, {min(workers, len(jobs))} workers)",
        flush=True,
    )
    run_jobs(jobs, workers)
    return dest


def iter_batches(start_coupon, max_coupon, batch_pages, slots=6):
    n = start_coupon
    while n <= max_coupon:
        remaining = max_coupon - n + 1
        full = batch_pages * slots
        if remaining >= full:
            pages = batch_pages
            end = n + full - 1
        else:
            pages = (remaining + slots - 1) // slots
            end = max_coupon
        yield n, end, pages
        n = end + 1


def build_to_limit(max_coupon=500000, start_coupon=15001, batch_pages=2500, chunk=250, workers=3):
    print("rendering shared A4 background")
    _data, rect, bg_path = template_jpeg()
    batches = list(iter_batches(start_coupon, max_coupon, batch_pages))
    print(f"{len(batches)} batches from YM{start_coupon:06d} to YM{max_coupon:06d}", flush=True)
    for i, (first, last, pages) in enumerate(batches, start=1):
        dest = OUT_DIR / f"A4-cutstack-{first:06d}-{last:06d}"
        expected = (pages + chunk - 1) // chunk
        existing = list(dest.glob("A4-p*.pdf")) if dest.exists() else []
        if dest.exists() and len(existing) >= expected and all(p.stat().st_size > 50000 for p in existing):
            print(f"[{i}/{len(batches)}] skip {dest.name} ({len(existing)} files)", flush=True)
            continue
        print(f"[{i}/{len(batches)}] YM{first:06d}-YM{last:06d}  {pages} pages -> {dest.name}", flush=True)
        build_stack(
            pages=pages,
            chunk=chunk,
            out_dir=dest,
            workers=workers,
            start_number=first,
            max_number=last,
            bg_path=bg_path,
            rect=rect,
        )
    print("done", flush=True)


if __name__ == "__main__":
    args = sys.argv[1:]
    if args and args[0] == "--to":
        max_coupon = int(args[1]) if len(args) > 1 else 500000
        start_coupon = int(args[2]) if len(args) > 2 else 15001
        build_to_limit(max_coupon, start_coupon)
    elif args and args[0] == "--stack":
        pages = int(args[1]) if len(args) > 1 else 2500
        start_page = int(args[2]) if len(args) > 2 else 1
        end_page = int(args[3]) if len(args) > 3 else pages
        build_stack(pages, start_page=start_page, end_page=end_page)
    else:
        start = int(args[0]) if args else 1
        count = int(args[1]) if len(args) > 1 else 6
        build(start, count)
