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
$next = min(500000, ((int) $last) + 1);
$boxes = [
    ['qr' => [50.435, 6.644, 9.079, 6.419], 'num' => [49.874, 14.590, 10.708, 1.176], 'rot' => false],
    ['qr' => [50.420, 32.037, 9.079, 6.420], 'num' => [49.859, 39.984, 10.707, 1.177], 'rot' => false],
    ['qr' => [50.455, 57.818, 9.079, 6.419], 'num' => [49.894, 65.764, 10.708, 1.176], 'rot' => false],
    ['qr' => [50.471, 83.488, 9.047, 6.397], 'num' => [49.894, 91.408, 10.708, 1.172], 'rot' => false],
    ['qr' => [81.504, 35.551, 9.079, 6.419], 'num' => [77.680, 35.164, 1.664, 7.550], 'rot' => true],
    ['qr' => [81.504, 82.930, 9.079, 6.419], 'num' => [77.680, 82.533, 1.664, 7.572], 'rot' => true],
];
$boxCss = static function (array $box) {
    return 'left:' . $box[0] . '%;top:' . $box[1] . '%;width:' . $box[2] . '%;height:' . $box[3] . '%';
};
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بطاقات A4 — YM<?php echo htmlspecialchars($first . '-' . $last, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #dbe7f3;
            font-family: Cairo, Tahoma, Arial, sans-serif;
        }
        .toolbar {
            max-width: 210mm;
            margin: 16px auto;
            padding: 0 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .toolbar h1 { margin: 0; font-size: 1.05rem; color: #0b4f86; }
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
            position: relative;
            width: 210mm;
            height: 297mm;
            margin: 0 auto 24px;
            background: #fff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
            overflow: hidden;
        }
        .page-bg {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: fill;
        }
        .qr-box, .num-box {
            position: absolute;
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
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, Helvetica, sans-serif;
            font-weight: 900;
            font-size: 2.2mm;
            letter-spacing: 0.04mm;
            color: #000;
        }
        .num-box.rot {
            overflow: hidden;
        }
        .num-box.rot span {
            display: block;
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
            font-size: 2.3mm;
            font-weight: 900;
            letter-spacing: 0.08mm;
        }
        @page { size: A4 portrait; margin: 0; }
        @media print {
            html, body { width: 210mm; background: #fff; }
            .toolbar { display: none !important; }
            .page { margin: 0; box-shadow: none; }
            .page + .page { page-break-before: always; break-before: page; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <h1>A4 — 6 بطاقات — من YM<?php echo htmlspecialchars($first, ENT_QUOTES, 'UTF-8'); ?> إلى YM<?php echo htmlspecialchars($last, ENT_QUOTES, 'UTF-8'); ?></h1>
        <div>
            <a href="print-a3-cards.php?start=<?php echo (int) $next; ?>">الصفحة التالية</a>
            <button type="button" onclick="window.print()">طباعة / حفظ PDF</button>
        </div>
    </div>
    <div class="page">
        <img class="page-bg" src="../cards/Final/page-1.jpg?v=20260917c" alt="">
        <?php foreach ($cards as $i => $coupon):
            $box = $boxes[$i];
            $token = coupon_encrypt($coupon, $cfg['secret']);
            $url = $cfg['base_url'] . '?t=' . rawurlencode($token);
        ?>
        <div class="qr-box" id="qr-<?php echo $i; ?>" data-url="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>" style="<?php echo $boxCss($box['qr']); ?>"></div>
        <div class="num-box<?php echo $box['rot'] ? ' rot' : ''; ?>" style="<?php echo $boxCss($box['num']); ?>">
            <span><?php echo htmlspecialchars(coupon_public_label($coupon), ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.querySelectorAll('.qr-box').forEach(function (box) {
            new QRCode(box, {
                text: box.getAttribute('data-url'),
                width: 160,
                height: 160,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        });
    </script>
</body>
</html>
