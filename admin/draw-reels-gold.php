<?php require __DIR__ . '/draw-boot.php'; ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>يامون بيبي</title>
    <?php echo panel_brand_links(); ?>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700;900&family=Orbitron:wght@700;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Cairo, sans-serif; min-height: 100vh; background: #120d07; color: #f8e7c0; text-align: center; }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 20px 12px 40px; }
        a { color: #e0b34a; font-weight: 800; }
        .machine { margin: 18px auto; padding: 22px 16px; max-width: 980px; border-radius: 28px; background: linear-gradient(180deg, #3a2a12, #16110a); border: 3px solid #e0b34a; }
        .board { font-size: clamp(2rem, 6vw, 3rem); letter-spacing: 10px; direction: ltr; color: #e0b34a; font-weight: 900; }
        .row { display: flex; justify-content: center; flex-wrap: wrap; gap: 12px; margin-top: 16px; direction: ltr; }
        .unit { width: 110px; direction: rtl; }
        .unit h3 { color: #c9a227; font-size: .8rem; margin-bottom: 6px; }
        .window { height: 120px; overflow: hidden; border-radius: 12px; background: #050508; border: 2px solid #f3d27a; position: relative; }
        .window::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,.45), transparent 28%, transparent 72%, rgba(0,0,0,.45)); pointer-events: none; }
        .strip { position: absolute; left: 0; right: 0; top: 0; }
        .strip div { height: 120px; display: flex; align-items: center; justify-content: center; font-family: Orbitron, sans-serif; font-size: 2.4rem; font-weight: 900; color: #ffe66d; }
        .btns { display: flex; gap: 5px; margin-top: 8px; }
        .btns button, .actions button { flex: 1; border: 0; border-radius: 10px; padding: 8px 6px; font-family: inherit; font-weight: 800; cursor: pointer; }
        .start { background: #e0b34a; color: #2a1a0c; }
        .stop { background: #8b1e1e; color: #fff; }
        button:disabled { opacity: .4; }
        .actions { margin-top: 18px; display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; }
        .actions button { padding: 11px 18px; border-radius: 999px; }
        .info { margin-top: 14px; font-weight: 800; }
        .celebrate { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(0,0,0,.75); }
        .celebrate.show { display: flex; }
        .celebrate strong { display: block; font-size: 4rem; letter-spacing: 10px; direction: ltr; color: #e0b34a; }
    </style>
</head>
<body>
    <div class="wrap">
        <a href="draw.php">كل النماذج</a>
        <h1>عدادات ذهبية</h1>
        <div class="machine">
            <div class="board" id="board">------</div>
            <div class="row" id="root"></div>
        </div>
        <div class="actions">
            <button type="button" class="start" id="resetAll">تصفير</button>
        </div>
        <div class="info" id="info" data-idle=""></div>
    </div>
    <script src="draw-engine.js?v=20260916m"></script>
    <script>
        var api = YamoonDraw.createReels({
            root: '#root', board: '#board', info: '#info', entries: <?php echo $drawEntriesJson; ?>, cell: 120
        });
        document.getElementById('resetAll').onclick = api.resetAll;
    </script>
</body>
</html>
