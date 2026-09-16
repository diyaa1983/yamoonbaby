<?php require __DIR__ . '/draw-boot.php'; ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>يامون بيبي</title>
    <?php echo panel_brand_links(); ?>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Cairo, Tahoma, sans-serif;
            min-height: 100vh;
            color: #12315a;
            text-align: center;
            background:
                radial-gradient(circle at 12% 18%, rgba(255,230,109,.35), transparent 26%),
                radial-gradient(circle at 88% 12%, rgba(255,107,157,.18), transparent 22%),
                linear-gradient(180deg, #dff3ff 0%, #f7fbff 48%, #fff 100%);
        }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 16px 12px 40px; }
        .back { display: inline-block; margin-bottom: 10px; color: #0b4f86; font-weight: 800; text-decoration: none; }
        .ticket {
            position: relative;
            background: #fff;
            border-radius: 28px;
            box-shadow: 0 18px 50px rgba(11, 79, 134, .14);
            overflow: hidden;
            padding: 0 0 22px;
        }
        .ticket::before,
        .ticket::after {
            content: '';
            position: absolute;
            top: 46%;
            width: 34px;
            height: 68px;
            background: #e8f6ff;
            border-radius: 50%;
            z-index: 2;
        }
        .ticket::before { left: -17px; }
        .ticket::after { right: -17px; }
        .hero {
            display: block;
            width: 100%;
            max-height: 220px;
            object-fit: cover;
            object-position: center 20%;
            border-bottom: 6px solid #f4c430;
        }
        .ribbon {
            background: #e11d48;
            color: #fff;
            font-weight: 800;
            padding: 8px 16px;
            letter-spacing: .3px;
        }
        .board-wrap { padding: 16px 16px 0; }
        .board-label { color: #0b4f86; font-weight: 800; margin-bottom: 6px; }
        .board {
            display: inline-block;
            min-width: 280px;
            padding: 8px 22px;
            border: 3px solid #1a2744;
            border-radius: 999px;
            background: #fff;
            font-size: clamp(2rem, 6vw, 3.1rem);
            letter-spacing: 8px;
            direction: ltr;
            font-weight: 900;
            color: #111;
        }
        .row { display: flex; justify-content: center; flex-wrap: wrap; gap: 14px; margin: 18px 12px 8px; direction: ltr; }
        .unit { width: 168px; direction: rtl; }
        .unit h3 { margin-bottom: 6px; color: #0b4f86; }
        .stage { position: relative; width: 156px; height: 176px; margin: 0 auto 8px; }
        .pin { position: absolute; top: 0; left: 50%; transform: translateX(-50%); border-left: 10px solid transparent; border-right: 10px solid transparent; border-top: 20px solid #ff7eb3; z-index: 4; }
        .rim { position: absolute; top: 16px; width: 156px; height: 156px; border-radius: 50%; background: radial-gradient(circle at 30% 30%, #fff, #ffd36a 42%, #5ec8d8); padding: 11px; }
        .wheel { width: 134px; height: 134px; border-radius: 50%; position: relative; transform-origin: 50% 50%; }
        .wheel span { position: absolute; left: 50%; top: 50%; width: 22px; margin: -11px 0 0 -11px; font-weight: 900; color: #fff; text-shadow: 0 1px 2px rgba(11,79,134,.28); text-align: center; line-height: 22px; }
        .hub { position: absolute; inset: 48px; border-radius: 50%; background: radial-gradient(circle at 30% 30%, #fff, #ffd36a); }
        .btns { display: flex; gap: 6px; }
        .btns button, .actions button { flex: 1; border: 0; border-radius: 10px; padding: 8px; font-family: inherit; font-weight: 800; cursor: pointer; }
        .start { background: #ffe66d; color: #3b2a00; }
        .stop { background: #c0392b; color: #fff; }
        button:disabled { opacity: .4; }
        .actions { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; padding: 0 16px; }
        .actions button { padding: 11px 18px; border-radius: 999px; }
        .actions #resetAll { background: #fff; color: #0b4f86; border: 2px solid #dbe7f3; }
        .info { min-height: 1.2em; margin-top: 10px; font-weight: 800; color: #0b4f86; }
        .date { margin-top: 10px; color: #e11d48; font-weight: 800; }
    </style>
</head>
<body>
    <div class="wrap">
        <a class="back" href="draw.php">كل النماذج</a>
        <div class="ticket">
            <img class="hero" src="../cards/yamoon-ticket.jpg?v=20260916a" alt="">
            <div class="ribbon">مستقبلك يبدأ من هنا • اربح شقة في طبربور</div>
            <div class="board-wrap">
                <div class="board-label">رقم المشاركة</div>
                <div class="board" id="board">------</div>
            </div>
            <div class="row" id="root"></div>
            <div class="actions">
                <button type="button" class="start" id="startAll">تشغيل التالية</button>
                <button type="button" class="stop" id="stopAll">إيقاف الكل</button>
                <button type="button" id="resetAll">تصفير</button>
            </div>
            <div class="info" id="info" data-idle=""></div>
            <div class="date">موعد السحب: 25/05/2027</div>
        </div>
    </div>
    <script src="draw-engine.js"></script>
    <script>
        var api = YamoonDraw.createWheels({
            root: '#root', board: '#board', info: '#info', entries: <?php echo $drawEntriesJson; ?>, radius: -46
        });
        document.getElementById('startAll').onclick = api.startAll;
        document.getElementById('stopAll').onclick = api.stopAll;
        document.getElementById('resetAll').onclick = api.resetAll;
    </script>
</body>
</html>
