<?php
require dirname(__DIR__) . '/includes/tool-auth.php';
require dirname(__DIR__) . '/includes/coupon-token.php';
$cfg = coupon_cards_config_or_fail();
$cards = [
    '000001',
    '100000',
    '200000',
    '300000',
    '350000',
    '400000',
    '450000',
    '480000',
    '490000',
    '500000',
];
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بطاقات السحب التجريبية - 10 بطاقات</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px;
            background: #e8f4ff;
            font-family: Cairo, Tahoma, Arial, sans-serif;
        }
        .toolbar {
            max-width: 1024px;
            margin: 0 auto 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .toolbar h1 {
            margin: 0;
            font-size: 1.4rem;
            color: #0b4f86;
        }
        .toolbar button {
            border: none;
            border-radius: 999px;
            padding: 10px 22px;
            font-family: inherit;
            font-weight: 800;
            background: #ffc107;
            color: #1a1a1a;
            cursor: pointer;
        }
        .sheet {
            display: flex;
            flex-direction: column;
            gap: 28px;
            align-items: center;
        }
        .ticket-wrap {
            width: min(1024px, 96vw);
        }
        .ticket {
            position: relative;
            width: 100%;
            aspect-ratio: 1024 / 682;
            direction: ltr;
            background: #fff;
            box-shadow: 0 10px 30px rgba(11, 79, 134, 0.15);
        }
        .ticket-bg {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: fill;
        }
        .qr-box {
            position: absolute;
            left: 77.246%;
            top: 34.457%;
            width: 16.309%;
            height: 23.314%;
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
            left: 75.5%;
            top: 63.1%;
            width: 20.1%;
            height: 6.8%;
            background: #fff;
            border: 2.5px solid #1a2744;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, Helvetica, sans-serif;
            font-weight: 900;
            font-size: clamp(14px, 1.8vw, 18px);
            letter-spacing: 0.5px;
            color: #000;
            box-sizing: border-box;
        }
        .open-link {
            display: block;
            margin-top: 8px;
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            color: #0b4f86;
            word-break: break-all;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar, .open-link { display: none !important; }
            .sheet { gap: 0; }
            .ticket-wrap {
                width: 100%;
                page-break-after: always;
                break-after: page;
            }
            .ticket { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <h1>10 بطاقات تجريبية — اطبع أو امسح رمز QR</h1>
        <button type="button" onclick="window.print()">طباعة البطاقات</button>
    </div>
    <div class="sheet">
        <?php foreach ($cards as $i => $coupon):
            $token = coupon_encrypt($coupon, $cfg['secret']);
            $url = $cfg['base_url'] . '?t=' . rawurlencode($token);
            $display = $coupon;
        ?>
        <div class="ticket-wrap">
            <div class="ticket">
                <img class="ticket-bg" src="../cards/yamoon-ticket-blank.jpg" alt="بطاقة سحب <?php echo htmlspecialchars($display, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="qr-box" id="qr-<?php echo $i; ?>" data-url="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>"></div>
                <div class="num-box"><?php echo htmlspecialchars($display, ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <a class="open-link" href="<?php echo htmlspecialchars($url, ENT_QUOTES, 'UTF-8'); ?>">فتح النموذج — <?php echo htmlspecialchars($display, ENT_QUOTES, 'UTF-8'); ?></a>
        </div>
        <?php endforeach; ?>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        document.querySelectorAll('.qr-box').forEach(function (box) {
            new QRCode(box, {
                text: box.getAttribute('data-url'),
                width: 176,
                height: 176,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
        });
    </script>
</body>
</html>
