import importlib.util
import io
from pathlib import Path

import fitz
from PIL import Image, ImageDraw

SRC = Path(r"C:\xampp\htdocs\yamoonbaby.com\cards\pdf\A4-cutstack-015001-030000\A4-p0001-p0250.pdf")
spec = importlib.util.spec_from_file_location(
    "build_a4_sheet",
    Path(r"C:\xampp\htdocs\yamoonbaby.com\tools\build-a4-sheet.py"),
)
mod = importlib.util.module_from_spec(spec)
spec.loader.exec_module(mod)


def to_1bit_png(img):
    bw = img.convert("1", dither=Image.Dither.NONE)
    buf = io.BytesIO()
    bw.save(buf, "PNG", compress_level=9)
    return buf.getvalue()


def make_qr_png(url, size=280):
    import qrcode
    from qrcode.constants import ERROR_CORRECT_M

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
    font = mod.font_at(size)
    bbox = draw.textbbox((0, 0), text, font=font)
    tw, th = bbox[2] - bbox[0], bbox[3] - bbox[1]
    while tw > rw - 10 and size > 12:
        size -= 1
        font = mod.font_at(size)
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


def extract_bg_jpeg(path):
    doc = fitz.open(path)
    for im in doc[0].get_images(full=True):
        xref = im[0]
        info = doc.xref_object(xref, compressed=True)
        if "/DCTDecode" not in info:
            continue
        data = doc.xref_stream_raw(xref)
        rect = doc[0].rect
        doc.close()
        if data[:2] != b"\xff\xd8":
            raise SystemExit("background stream is not a JPEG")
        return data, rect
    raise SystemExit("JPEG background not found")


def main():
    mod.make_qr_png = make_qr_png
    mod.make_label_png = make_label_png
    old = SRC.stat().st_size
    jpeg, rect = extract_bg_jpeg(SRC)
    secret, base_url = mod.load_config()
    pages = 2500
    start_number = 15001
    max_number = 30000
    chunk_start, chunk_end = 0, 250
    needed = []
    for p in range(chunk_start, chunk_end):
        for value in mod.stack_values(p, pages, start_number):
            if value <= max_number:
                needed.append(value)
    tokens = {n: mod.coupon_card(n, secret, base_url) for n in needed}
    out = fitz.open()
    bg_xref = None
    for p in range(chunk_start, chunk_end):
        page = out.new_page(width=rect.width, height=rect.height)
        if bg_xref is None:
            bg_xref = page.insert_image(page.rect, stream=jpeg, keep_proportion=False)
        else:
            page.insert_image(page.rect, xref=bg_xref, keep_proportion=False)
        mod.overlay_front(page, mod.page_cards(p, pages, start_number, max_number, tokens))
        if (p + 1) % 50 == 0:
            print("page", p + 1, flush=True)
    tmp = SRC.with_suffix(".tmp.pdf")
    out.save(tmp, deflate=True, garbage=4, use_objstms=1)
    out.close()
    new = tmp.stat().st_size
    tmp.replace(SRC)
    print(f"old {old/1048576:.2f} MB -> new {new/1048576:.2f} MB")


if __name__ == "__main__":
    main()
