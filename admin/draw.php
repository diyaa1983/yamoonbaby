<?php
require __DIR__ . '/draw-boot.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختيار نموذج السحب</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700;900&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Cairo, Tahoma, sans-serif;
            min-height: 100vh;
            background: radial-gradient(circle at top, #2b1748, #0d0714);
            color: #fff;
            padding: 28px 16px 48px;
        }
        .wrap { max-width: 1040px; margin: 0 auto; text-align: center; }
        a.back { color: #ffd166; text-decoration: none; font-weight: 800; }
        h1 { margin: 12px 0 8px; }
        p { color: rgba(255,255,255,.72); margin-bottom: 26px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 16px; }
        a.card {
            display: block;
            text-decoration: none;
            color: #fff;
            background: rgba(255,255,255,.07);
            border: 1px solid rgba(255,209,102,.28);
            border-radius: 22px;
            padding: 26px 18px;
            min-height: 170px;
        }
        a.card strong { display: block; color: #ffd166; font-size: 1.15rem; margin-bottom: 8px; }
        a.card span { color: rgba(255,255,255,.75); }
    </style>
</head>
<body>
    <div class="wrap">
        <a class="back" href="home.php">رجوع للوحة المدير</a>
        <h1>نماذج السحب على الفائز</h1>
        <p>العجلة الأولى من 0 إلى 4، وباقي العجلات من 1 إلى 9. التشغيل بالترتيب من 1 إلى 6، ولا تعمل عجلتان معاً.</p>
        <div class="grid">
            <a class="card" href="draw-wheels-festival.php">
                <strong>1) ست عجلات</strong>
                <span>عجلات دائرية تُدار واحدة تلو الأخرى.</span>
            </a>
            <a class="card" href="draw-child.php">
                <strong>2) الطفل يلف العجلات</strong>
                <span>طفل يمشي إلى كل عجلة ويلفّها ثم يتوقف.</span>
            </a>
            <a class="card" href="draw-reels-gold.php">
                <strong>3) عدادات ذهبية</strong>
                <span>أعمدة دوّارة مثل ماكينة الأرقام الفاخرة.</span>
            </a>
        </div>
    </div>
</body>
</html>
