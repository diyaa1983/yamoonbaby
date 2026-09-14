<?php
require __DIR__ . '/auth.php';
panel_start();

if (panel_user()) {
    header('Location: home.php');
    exit;
}

$error = '';
$info = '';

try {
    $pdo = panel_pdo();
    $hasUsers = panel_users_count($pdo) > 0;
} catch (Exception $e) {
    $pdo = null;
    $hasUsers = true;
    if ($e->getMessage() === 'missing-config') {
        $error = 'ملف db-config.php غير موجود على السيرفر. أنشئه في مجلد الموقع (بجانب index.html) وضع فيه اسم القاعدة والمستخدم وكلمة السر من cPanel. هذا الملف لا يُرفع مع Git.';
    } else {
        $error = 'تعذر الدخول لقاعدة البيانات. استخدم في db-config.php نفس بيانات cPanel → MySQL Databases: غالباً host = localhost، واسم القاعدة والمستخدم يظهران كاملين (يبدآن باسم حساب الاستضافة).';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    if (!panel_csrf_ok()) {
        $error = 'انتهت صلاحية الجلسة. أعد المحاولة.';
    } elseif (panel_login_blocked()) {
        $error = 'محاولات كثيرة. انتظر قليلاً ثم أعد المحاولة.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $action = (string) ($_POST['action'] ?? 'login');

        if ($action === 'setup' && !$hasUsers) {
            if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $username) || strlen($password) < 8) {
                $error = 'اسم المستخدم 3 إلى 40 حرفاً، وكلمة السر 8 أحرف على الأقل.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO panel_users (username, password_hash, role) VALUES (?, ?, ?)');
                $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), 'admin']);
                panel_audit($pdo, $username, 'user_added', null, null, 'إنشاء أول مدير');
                panel_login_success((int) $pdo->lastInsertId(), $username, 'admin');
                header('Location: home.php');
                exit;
            }
        } else {
            $stmt = $pdo->prepare('SELECT id, username, password_hash, role FROM panel_users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $row = $stmt->fetch();
            if ($row && password_verify($password, $row['password_hash'])) {
                panel_login_success($row['id'], $row['username'], $row['role']);
                header('Location: home.php');
                exit;
            }
            panel_login_fail();
            $error = 'اسم المستخدم أو كلمة السر غير صحيحة.';
        }
    }
}

$csrf = $pdo ? panel_csrf_token() : '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دخول لوحة السحب</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@600;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="login-screen">
    <div class="login-box">
        <div class="logo-mark" style="margin:0 auto;">YB</div>
        <h1>يامون بيبي</h1>
        <p><?php echo $hasUsers ? 'دخول نظام إدارة السحب' : 'أنشئ حساب المدير الأول'; ?></p>
        <?php if ($error): ?><div class="alert alert-err"><?php echo panel_h($error); ?></div><?php endif; ?>
        <?php if ($pdo): ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo panel_h($csrf); ?>">
            <input type="hidden" name="action" value="<?php echo $hasUsers ? 'login' : 'setup'; ?>">
            <label>اسم المستخدم</label>
            <input type="text" name="username" required maxlength="40" autocomplete="username">
            <label style="margin-top:12px;">كلمة السر</label>
            <input type="password" name="password" required autocomplete="<?php echo $hasUsers ? 'current-password' : 'new-password'; ?>">
            <button class="btn btn-gold" type="submit"><?php echo $hasUsers ? 'دخول' : 'إنشاء المدير ودخول'; ?></button>
        </form>
        <?php endif; ?>
    </div>
    </div>
</body>
</html>
