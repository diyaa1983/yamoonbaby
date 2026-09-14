<?php
require dirname(__DIR__) . '/includes/tool-auth.php';
require dirname(__DIR__) . '/includes/coupon-token.php';
$cfg = coupon_cards_config_or_fail();
$start = max(1, min(500000, (int) ($_GET['start'] ?? 1)));
$cards = [];
for ($n = 0; $n < 6; $n++) {
    $value = $start + $n;
    if ($value > 500000) {
        break;
    }
    $cards[] = str_pad((string) $value, 6, '0', STR_PAD_LEFT);
}
$first = $cards[0] ?? '000001';
$last = $cards[count($cards) - 1] ?? $first;
$pdfName = 'yamoon-cards-' . $first . '-' . $last . '.pdf';
$pdfRel = '../cards/' . $pdfName;
$pdfExists = is_file(dirname(__DIR__) . '/cards/' . $pdfName);
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بطاقات 22.2×27.4 — <?php echo htmlspecialchars($first . '-' . $last, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #dbe7f3;
            font-family: Cairo, Tahoma, Arial, sans-serif;
        }
        .toolbar {
            max-width: 274mm;
            margin: 16px auto;
            padding: 0 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .toolbar h1 { margin: 0; font-size: 1.15rem; color: #0b4f86; }
        .toolbar button, .toolbar a {
            border: 0;
            border-radius: 999px;
            padding: 10px 18px;
            font-family: inherit;
            font-weight: 800;
            background: #ffc107;
            color: #1a1a1a;
            text-decoration: none;
            cursor: pointer;
        }
        .page {
            width: 274mm;
            height: 222mm;
            margin: 0 auto 24px;
            background: #fff;
            display: grid;
            grid-template-columns: 137mm 137mm;
            grid-template-rows: 74mm 74mm 74mm;
            justify-content: start;
            align-content: start;
            column-gap: 0;
            row-gap: 0;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
        }
        .card {
            width: 137mm;
            height: 74mm;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
        }
        .ticket {
            position: relative;
            width: 137mm;
            height: 74mm;
            direction: ltr;
            background: #fff;
        }
        .ticket-bg {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: fill;
        }
        .qr-box {
            position: absolute;
            left: 76.855%;
            top: 28.09%;
            width: 16.797%;
            height: 30.15%;
            background: #fff;
            overflow: hidden;
        }
        .qr-box img,
        .qr-box canvas,
        .qr-box table {
            width: 100% !important;
            height: 100% !important;
            display: block;
        }
        .num-box {
            position: absolute;
            left: 76.758%;
            top: 65.543%;
            width: 17.773%;
            height: 5.993%;
            background: #fff;
            border: 0.4mm solid #1a2744;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, Helvetica, sans-serif;
            font-weight: 900;
            font-size: 3.6mm;
            letter-spacing: 0.2mm;
            color: #000;
        }
        @page { size: 274mm 222mm; margin: 0; }
        @media print {
            html, body { width: 274mm; height: 222mm; background: #fff; }
            .toolbar { display: none !important; }
            .page { margin: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <h1>صفحة 27.4 × 22.2 سم — 6 كوبونات 13.7 × 7.4 سم — من <?php echo htmlspecialchars($first, ENT_QUOTES, 'UTF-8'); ?> إلى <?php echo htmlspecialchars($last, ENT_QUOTES, 'UTF-8'); ?></h1>
        <div>
            <?php if ($pdfExists): ?>
            <a href="<?php echo htmlspecialchars($pdfRel, ENT_QUOTES, 'UTF-8'); ?>" download="<?php echo htmlspecialchars($pdfName, ENT_QUOTES, 'UTF-8'); ?>">تحميل ملف PDF</a>
            <?php else: ?>
            <a href="build-a4-pdf.php?start=<?php echo (int) $start; ?>">إنشاء ملف PDF</a>
            <?php endif; ?>
            <button type="button" onclick="window.print()">طباعة</button>
        </div>
    </div>
    <div class="page" id="a4page">
        <?php foreach ($cards as $i => $coupon):
            $token = coupon_encrypt($coupon, $cfg['secret']);
            $url = $cfg['base_url'] . '?t=' . rawurlencode($token);
        ?>
        <div class="card">
            <div class="ticket">
                <img class="ticket-bg" src="../cards/yamoon-ticket-blank.jpg" alt="<?php echo htmlspecialchars($coupon, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="qr-box" id="qr-<?php echo $i; ?>" data-url="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"></div>
                <div class="num-box"><?php echo htmlspecialchars($coupon, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.querySelectorAll('.qr-box').forEach(function (box) {
            new QRCode(box, {
                text: box.getAttribute('data-url'),
                width: 120,
                height: 120,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        });
    </script>
</body>
</html>
