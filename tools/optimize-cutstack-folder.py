import re
import sys
from concurrent.futures import ProcessPoolExecutor, as_completed
from pathlib import Path

import fitz

from importlib.util import module_from_spec, spec_from_file_location

ROOT = Path(__file__).resolve().parents[1]
spec = spec_from_file_location("build_a4_sheet", ROOT / "tools" / "build-a4-sheet.py")
mod = module_from_spec(spec)
spec.loader.exec_module(mod)

PAGE_RE = re.compile(r"^A4-p(\d+)-p(\d+)\.pdf$", re.I)
RANGE_RE = re.compile(r"^A4-cutstack-(\d{6})-(\d{6})$")


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


def optimize_chunk(job):
    (
        dest,
        old_name,
        stack_pages,
        start_number,
        max_number,
        chunk_start,
        chunk_end,
        bg_path,
        width,
        height,
    ) = job
    dest = Path(dest)
    first = start_number + chunk_start
    last = start_number + chunk_end - 1
    new_path = dest / f"A4-{first:06d}-{last:06d}.pdf"
    old_path = dest / old_name
    secret, base_url = mod.load_config()
    needed = []
    for p in range(chunk_start, chunk_end):
        for value in mod.stack_values(p, stack_pages, start_number):
            if value <= max_number:
                needed.append(value)
    tokens = {n: mod.coupon_card(n, secret, base_url) for n in needed}
    jpeg = Path(bg_path).read_bytes()
    out = fitz.open()
    bg_xref = None
    for p in range(chunk_start, chunk_end):
        page = out.new_page(width=width, height=height)
        if bg_xref is None:
            bg_xref = page.insert_image(page.rect, stream=jpeg, keep_proportion=False)
        else:
            page.insert_image(page.rect, xref=bg_xref, keep_proportion=False)
        mod.overlay_front(page, mod.page_cards(p, stack_pages, start_number, max_number, tokens))
    tmp = new_path.with_suffix(".tmp.pdf")
    out.save(tmp, deflate=True, garbage=4, use_objstms=1)
    out.close()
    new_size = tmp.stat().st_size
    tmp.replace(new_path)
    old_size = old_path.stat().st_size if old_path.exists() else 0
    if old_path.resolve() != new_path.resolve() and old_path.exists():
        old_path.unlink()
    return f"{old_name} -> {new_path.name}  {old_size/1048576:.1f}MB -> {new_size/1048576:.1f}MB"


def optimize_folder(folder, start_number, stack_pages, max_number, workers=3):
    folder = Path(folder)
    files = []
    for path in sorted(folder.glob("A4-p*.pdf")):
        match = PAGE_RE.match(path.name)
        if not match:
            continue
        chunk_start = int(match.group(1)) - 1
        chunk_end = int(match.group(2))
        files.append((path.name, chunk_start, chunk_end, path.stat().st_size))
    if not files:
        raise SystemExit(f"no A4-p*.pdf files in {folder}")
    jpeg, rect = extract_bg_jpeg(folder / files[0][0])
    bg_path = folder / "_bg.jpg"
    bg_path.write_bytes(jpeg)
    jobs = [
        (
            str(folder),
            name,
            stack_pages,
            start_number,
            max_number,
            chunk_start,
            chunk_end,
            str(bg_path),
            float(rect.width),
            float(rect.height),
        )
        for name, chunk_start, chunk_end, _size in files
    ]
    print(f"{folder.name}: {len(jobs)} files, start YM{start_number:06d}, {workers} workers", flush=True)
    workers = max(1, min(workers, len(jobs)))
    with ProcessPoolExecutor(max_workers=workers) as pool:
        futs = [pool.submit(optimize_chunk, job) for job in jobs]
        for fut in as_completed(futs):
            print(fut.result(), flush=True)
    if bg_path.exists():
        bg_path.unlink()


def optimize_remaining(workers=3):
    pdf_root = ROOT / "cards" / "pdf"
    folders = []
    for folder in sorted(pdf_root.glob("A4-cutstack-*")):
        if not folder.is_dir():
            continue
        match = RANGE_RE.match(folder.name)
        if not match:
            continue
        pending = list(folder.glob("A4-p*.pdf"))
        if not pending:
            print(f"skip {folder.name} (already done)", flush=True)
            continue
        start_number = int(match.group(1))
        max_number = int(match.group(2))
        stack_pages = (max_number - start_number + 6) // 6
        folders.append((folder, start_number, stack_pages, max_number, len(pending)))
    print(f"{len(folders)} folders remaining", flush=True)
    for i, (folder, start_number, stack_pages, max_number, pending) in enumerate(folders, start=1):
        print(
            f"[{i}/{len(folders)}] {folder.name}  {pending} files  "
            f"YM{start_number:06d}-YM{max_number:06d}  pages={stack_pages}",
            flush=True,
        )
        optimize_folder(folder, start_number, stack_pages, max_number, workers=workers)
    print("all done", flush=True)


if __name__ == "__main__":
    if len(sys.argv) > 1 and sys.argv[1] == "--all":
        optimize_remaining()
    else:
        folder = Path(sys.argv[1]) if len(sys.argv) > 1 else ROOT / "cards" / "pdf" / "A4-cutstack-2500"
        start_number = int(sys.argv[2]) if len(sys.argv) > 2 else 1
        stack_pages = int(sys.argv[3]) if len(sys.argv) > 3 else 2500
        max_number = int(sys.argv[4]) if len(sys.argv) > 4 else 15000
        optimize_folder(folder, start_number, stack_pages, max_number)
