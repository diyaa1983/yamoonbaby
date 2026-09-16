<?php
require __DIR__ . '/auth.php';
panel_start();
$user = panel_require_admin();

$drawError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'unlock_draw') {
    if (!panel_csrf_ok()) {
        $drawError = 'انتهت صلاحية الجلسة. أعد المحاولة.';
    } elseif (panel_login_blocked()) {
        $drawError = 'محاولات كثيرة. انتظر قليلاً ثم أعد المحاولة.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        try {
            $pdo = panel_pdo();
            $stmt = $pdo->prepare('SELECT password_hash FROM panel_users WHERE id = ? LIMIT 1');
            $stmt->execute([$user['id']]);
            $row = $stmt->fetch();
        } catch (Exception $e) {
            $row = null;
        }
        if ($row && password_verify($password, $row['password_hash'])) {
            panel_unlock_draw();
            $back = basename((string) ($_SERVER['PHP_SELF'] ?? 'draw.php'));
            if (!preg_match('/^draw(-[a-z0-9-]+)?\.php$/', $back)) {
                $back = 'draw.php';
            }
            header('Location: ' . $back);
            exit;
        }
        panel_login_fail();
        $drawError = 'كلمة السر غير صحيحة.';
    }
}

if (!panel_draw_unlocked()) {
    $csrf = panel_csrf_token();
    ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>يامون بيبي</title>
    <?php echo panel_brand_links(); ?>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@600;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css?v=20260916d">
</head>
<body class="login-page">
    <div class="login-split">
        <?php require __DIR__ . '/login-scene.php'; ?>
        <section class="login-panel">
        <div class="login-box">
            <img class="login-logo" src="<?php echo panel_h(panel_web_root() . '/assets/img/logo.png'); ?>" alt="يامون بيبي">
            <h1>تأكيد فتح السحب</h1>
            <p>أدخل كلمة سر المدير للمتابعة</p>
            <?php if ($drawError): ?><div class="alert alert-err"><?php echo panel_h($drawError); ?></div><?php endif; ?>
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo panel_h($csrf); ?>">
                <input type="hidden" name="action" value="unlock_draw">
                <label>كلمة السر</label>
                <input type="password" name="password" required autocomplete="current-password" autofocus>
                <button class="btn btn-gold" type="submit">فتح السحب</button>
            </form>
            <p style="margin-top:16px;"><a href="home.php" style="color:#0b4f86;font-weight:800;text-decoration:none;">رجوع للوحة المدير</a></p>
        </div>
        </section>
    </div>
</body>
</html>
    <?php
    exit;
}

try {
    $pdo = panel_pdo();
    $drawEntries = $pdo->query(
        'SELECT coupon, full_name, phone, governorate FROM raffle_entries ORDER BY id'
    )->fetchAll();
    foreach ($drawEntries as &$row) {
        $row['coupon'] = str_pad((string) $row['coupon'], 6, '0', STR_PAD_LEFT);
    }
    unset($row);
} catch (Exception $e) {
    $drawEntries = [];
}
$drawEntriesJson = json_encode($drawEntries, JSON_UNESCAPED_UNICODE);
