<?php
require dirname(__DIR__) . '/includes/tool-auth.php';
require dirname(__DIR__) . '/includes/coupon-token.php';
$cfg = coupon_cards_config_or_fail();
$start = max(1, min(500000, (int) ($_GET['start'] ?? 1)));
$cards = [];
for ($n = 0; $n < 10; $n++) {
    $value = $start + $n;
    if ($value > 500000) {
        break;
    }
    $cards[] = str_pad((string) $value, 6, '0', STR_PAD_LEFT);
}
$first = $cards[0] ?? '000001';
$last = $cards[count($cards) - 1] ?? $first;
$next = min(500000, ((int) $last) + 1);
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بطاقات A3 — YM<?php echo htmlspecialchars($first . '-' . $last, ENT_QUOTES, 'UTF-8'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #dbe7f3;
            font-family: Cairo, Tahoma, Arial, sans-serif;
        }
        .toolbar {
            max-width: 297mm;
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
            width: 297mm;
            height: 420mm;
            margin: 0 auto 24px;
            background: #fff;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.12);
            overflow: hidden;
        }
        .card {
            position: absolute;
            width: 140.511mm;
            height: 73.237mm;
            overflow: hidden;
            background: #fff;
        }
        .ticket {
            position: relative;
            width: 100%;
            height: 100%;
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
            left: 76.66%;
            top: 28.08%;
            width: 17.19%;
            height: 30.50%;
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
            left: 76.46%;
            top: 65.86%;
            width: 18.16%;
            height: 5.32%;
            background: transparent;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, Helvetica, sans-serif;
            font-weight: 900;
            font-size: 3.1mm;
            letter-spacing: 0.06mm;
            color: #000;
        }
        @page { size: A3 portrait; margin: 0; }
        @media print {
            html, body { width: 297mm; background: #fff; }
            .toolbar { display: none !important; }
            .page { margin: 0; box-shadow: none; }
            .page + .page { page-break-before: always; break-before: page; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <h1>A3 — 10 بطاقات — من YM<?php echo htmlspecialchars($first, ENT_QUOTES, 'UTF-8'); ?> إلى YM<?php echo htmlspecialchars($last, ENT_QUOTES, 'UTF-8'); ?></h1>
        <div>
            <a href="print-a3-cards.php?start=<?php echo (int) $next; ?>">الصفحة التالية</a>
            <button type="button" onclick="window.print()">طباعة / حفظ PDF</button>
        </div>
    </div>
    <div class="page">
        <?php foreach ($cards as $i => $coupon):
            $col = $i % 2;
            $row = intdiv($i, 2);
            $left = 8.081 + $col * (140.511 + 1.151);
            $top = 19.276 + $row * (73.237 + 1.236);
            $token = coupon_encrypt($coupon, $cfg['secret']);
            $url = $cfg['base_url'] . '?t=' . rawurlencode($token);
        ?>
        <div class="card" style="left:<?php echo number_format($left, 3, '.', ''); ?>mm;top:<?php echo number_format($top, 3, '.', ''); ?>mm;">
            <div class="ticket">
                <img class="ticket-bg" src="<?php echo htmlspecialchars(coupon_ticket_src('yamoon-ticket-blank.jpg'), ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($coupon, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="qr-box" id="qr-<?php echo $i; ?>" data-url="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"></div>
                <div class="num-box"><?php echo htmlspecialchars(coupon_public_label($coupon), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="page">
        <?php foreach ($cards as $i => $coupon):
            $col = 1 - ($i % 2);
            $row = intdiv($i, 2);
            $left = 8.081 + $col * (140.511 + 1.151);
            $top = 19.276 + $row * (73.237 + 1.236);
        ?>
        <div class="card" style="left:<?php echo number_format($left, 3, '.', ''); ?>mm;top:<?php echo number_format($top, 3, '.', ''); ?>mm;">
            <div class="ticket">
                <img class="ticket-bg" src="<?php echo htmlspecialchars(coupon_ticket_src('yamoon-ticket-back.jpg'), ENT_QUOTES, 'UTF-8'); ?>" alt="ظهر البطاقة">
            </div>
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
