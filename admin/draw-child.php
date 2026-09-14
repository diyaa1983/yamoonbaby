<?php require __DIR__ . '/draw-boot.php'; ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>السحب — الطفل يلف العجلات</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700;800;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Cairo, Tahoma, sans-serif;
            min-height: 100vh;
            color: #12315a;
            text-align: center;
            background: linear-gradient(180deg, #dff3ff 0%, #f7fbff 48%, #fff 100%);
        }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 16px 12px 40px; }
        .back { display: inline-block; margin-bottom: 10px; color: #0b4f86; font-weight: 800; text-decoration: none; }
        .ticket { position: relative; background: #fff; border-radius: 28px; box-shadow: 0 18px 50px rgba(11, 79, 134, .14); overflow: hidden; padding: 0 0 22px; }
        .hero { display: block; width: 100%; max-height: 180px; object-fit: cover; object-position: center 20%; border-bottom: 6px solid #f4c430; }
        .ribbon { background: #e11d48; color: #fff; font-weight: 800; padding: 8px 16px; }
        .board-wrap { padding: 16px 16px 0; }
        .board-label { color: #0b4f86; font-weight: 800; margin-bottom: 6px; }
        .board { display: inline-block; min-width: 280px; padding: 8px 22px; border: 3px solid #1a2744; border-radius: 999px; background: #fff; font-size: clamp(2rem, 6vw, 3.1rem); letter-spacing: 8px; direction: ltr; unicode-bidi: isolate; font-weight: 900; }
        .row { display: flex; justify-content: center; flex-wrap: wrap; gap: 14px; margin: 18px 12px 0; direction: ltr; }
        .unit { width: 168px; direction: rtl; }
        .unit h3 { margin-bottom: 6px; color: #0b4f86; }
        .stage { position: relative; width: 156px; height: 176px; margin: 0 auto 8px; }
        .pin { position: absolute; top: 0; left: 50%; transform: translateX(-50%); border-left: 10px solid transparent; border-right: 10px solid transparent; border-top: 20px solid #ff7eb3; z-index: 4; }
        .rim { position: absolute; top: 16px; width: 156px; height: 156px; border-radius: 50%; background: radial-gradient(circle at 30% 30%, #fff, #ffd36a 42%, #5ec8d8); padding: 11px; }
        .wheel { width: 134px; height: 134px; border-radius: 50%; position: relative; transform-origin: 50% 50%; }
        .wheel span { position: absolute; left: 50%; top: 50%; width: 22px; margin: -11px 0 0 -11px; font-weight: 900; color: #fff; text-align: center; line-height: 22px; }
        .hub { position: absolute; inset: 48px; border-radius: 50%; background: radial-gradient(circle at 30% 30%, #fff, #ffd36a); }
        .btns { display: none; }
        .child-track { position: relative; height: 150px; margin: 0 12px 8px; }
        .kid { position: absolute; top: 4px; left: 0; width: 118px; height: 142px; transition: transform .7s ease; z-index: 3; }
        .kid img { display: block; width: 100%; height: 100%; object-fit: contain; filter: drop-shadow(0 6px 10px rgba(11,79,134,.18)); }
        .kid.walk img { animation: bob .32s ease-in-out infinite; }
        .kid.push img { transform: rotate(-8deg) scale(1.04); transform-origin: bottom center; }
        @keyframes bob { 50% { transform: translateY(-5px); } }
        .actions { display: flex; justify-content: center; gap: 8px; flex-wrap: wrap; padding: 0 16px; }
        .actions button { border: 0; border-radius: 999px; padding: 11px 18px; font: inherit; font-weight: 800; cursor: pointer; }
        .start { background: #ffe66d; color: #3b2a00; }
        .stop { background: #c0392b; color: #fff; }
        #resetAll { background: #fff; color: #0b4f86; border: 2px solid #dbe7f3; }
        button:disabled { opacity: .4; }
        .info { min-height: 1.2em; margin-top: 10px; font-weight: 800; color: #0b4f86; }
        .date { margin-top: 10px; color: #e11d48; font-weight: 800; }
    </style>
</head>
<body>
    <div class="wrap">
        <a class="back" href="draw.php">كل النماذج</a>
        <div class="ticket">
            <img class="hero" src="../cards/yamoon-ticket.jpg" alt="">
            <div class="ribbon">الطفل يلف العجلات بالترتيب من 1 إلى 6</div>
            <div class="board-wrap">
                <div class="board-label">رقم المشاركة</div>
                <div class="board" id="board">------</div>
            </div>
            <div class="row" id="root"></div>
            <div class="child-track" id="childTrack">
                <div class="kid" id="yamoonChild">
                    <img src="../assets/img/baby.png" alt="طفل يامون">
                </div>
            </div>
            <div class="actions">
                <button type="button" class="start" id="startAll">الطفل يلف التالية</button>
                <button type="button" class="stop" id="stopAll">إيقاف</button>
                <button type="button" id="resetAll">تصفير</button>
            </div>
            <div class="info" id="info" data-idle=""></div>
            <div class="date">موعد السحب: 25/05/2027</div>
        </div>
    </div>
    <script src="draw-engine.js"></script>
    <script>
        var child = document.getElementById('yamoonChild');
        var track = document.getElementById('childTrack');
        var root = document.getElementById('root');

        function moveChild(index) {
            var units = root.querySelectorAll('.unit');
            if (!units[index]) return;
            var ur = units[index].getBoundingClientRect();
            var tr = track.getBoundingClientRect();
            var x = ur.left + ur.width / 2 - tr.left - child.offsetWidth / 2;
            child.classList.remove('push');
            child.classList.add('walk');
            child.style.transform = 'translateX(' + Math.max(0, x) + 'px)';
        }

        function standChild() {
            child.classList.remove('walk');
        }

        var api = YamoonDraw.createWheels({
            root: '#root',
            board: '#board',
            info: '#info',
            entries: <?php echo $drawEntriesJson; ?>,
            radius: -46,
            walkDelay: 800,
            onStart: function (i) { moveChild(i); },
            onSpin: function () { child.classList.remove('walk'); child.classList.add('push'); },
            onStop: function () { child.classList.remove('push'); },
            onReset: function () { child.classList.remove('walk', 'push'); moveChild(0); }
        });
        window.addEventListener('load', function () { moveChild(0); });
        document.getElementById('startAll').onclick = api.startAll;
        document.getElementById('stopAll').onclick = api.stopAll;
        document.getElementById('resetAll').onclick = api.resetAll;
    </script>
</body>
</html>
