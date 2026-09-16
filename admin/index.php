<?php
$root = dirname(__DIR__);
if (!is_readable($root . '/includes/raffle-session.php') || !is_readable($root . '/includes/raffle-db.php')) {
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo 'ملفات النظام ناقصة على السيرفر. من SSH نفّذ: git checkout -- db-config.example.php ثم git pull origin main';
    exit;
}
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
    $code = $e->getMessage();
    if ($code === 'missing-config') {
        $error = 'ملف db-config.php غير موجود على السيرفر. أنشئه بجانب index.html. لا تستخدم ملف المثال.';
    } elseif ($code === 'bad-config') {
        $error = 'ملف db-config.php ناقص أو فيه خطأ. يجب أن ينتهي بـ ]; بعد charset.';
    } elseif ($code === 'pdo-1045') {
        $error = 'MySQL رفض المستخدم أو كلمة السر. من cPanel → MySQL Databases أضف المستخدم إلى القاعدة بصلاحيات ALL PRIVILEGES، وتأكد أن كلمة السر مطابقة.';
    } elseif ($code === 'pdo-1049' || $code === 'pdo-1044') {
        $error = 'اسم القاعدة غير صحيح أو المستخدم بدون صلاحية عليها. انسخ الاسم الكامل من cPanel كما هو.';
    } elseif ($code === 'pdo-2002') {
        $error = 'تعذر الوصول لسيرفر MySQL. أبقِ host على localhost بدون تغيير.';
    } elseif ($code === 'pdo-schema') {
        $error = 'تم الاتصال لكن لا توجد صلاحية إنشاء الجداول. أضف المستخدم للقاعدة بـ ALL PRIVILEGES ثم استورد database/yamoonbaby-data.sql من phpMyAdmin.';
    } else {
        $error = 'تعذر الدخول لقاعدة البيانات (' . $code . '). تأكد من host=localhost واسم القاعدة والمستخدم في cPanel.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    if (!panel_csrf_ok()) {
        $error = 'انتهت صلاحية الجلسة. أعد المحاولة.';
    } elseif (panel_login_blocked()) {
        $error = 'محاولات كثيرة. انتظر قليلاً ثم أعد المحاولة.';
        panel_otp_clear();
    } else {
        $action = (string) ($_POST['action'] ?? 'login');
        $pending = panel_otp_pending();

        if ($action === 'cancel_otp') {
            panel_otp_clear();
            $info = 'تم إلغاء التحقق. أدخل اسم المستخدم وكلمة السر من جديد.';
        } elseif ($action === 'verify_otp') {
            if (!$pending) {
                $error = 'انتهت صلاحية رمز التحقق. سجّل الدخول من جديد.';
            } else {
                $code = preg_replace('/\D+/', '', (string) ($_POST['otp_code'] ?? ''));
                $pending['tries'] = (int) $pending['tries'] + 1;
                $_SESSION['panel_otp'] = $pending;
                if ($pending['tries'] > 5) {
                    panel_otp_clear();
                    panel_login_fail();
                    $error = 'محاولات كثيرة لرمز التحقق. سجّل الدخول من جديد.';
                } elseif (!preg_match('/^\d{6}$/', $code) || !password_verify($code, $pending['hash'])) {
                    $error = 'رمز التحقق غير صحيح.';
                } else {
                    panel_login_success($pending['id'], $pending['username'], $pending['role']);
                    header('Location: home.php');
                    exit;
                }
            }
        } elseif ($action === 'resend_otp') {
            if (!$pending) {
                $error = 'انتهت صلاحية رمز التحقق. سجّل الدخول من جديد.';
            } elseif ((int) $pending['sent_at'] > time() - 45) {
                $error = 'انتظر قليلاً قبل إعادة إرسال الرمز.';
            } else {
                $sendErr = panel_otp_start($pdo, $pending, $pending['email']);
                if ($sendErr !== '') {
                    $error = 'تعذر إعادة إرسال رمز التحقق. راجع إعدادات البريد في الإعدادات.';
                } else {
                    $info = 'تم إرسال رمز جديد إلى ' . panel_mask_email($pending['email']) . '.';
                }
            }
        } elseif ($action === 'setup' && !$hasUsers) {
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
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
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $stmt = $pdo->prepare('SELECT id, username, password_hash, role FROM panel_users WHERE username = ? LIMIT 1');
            $stmt->execute([$username]);
            $row = $stmt->fetch();
            if ($row && password_verify($password, $row['password_hash'])) {
                $adminEmail = $row['role'] === 'admin' ? panel_admin_email($pdo) : '';
                $smtpReady = $row['role'] === 'admin' && panel_smtp_ready(panel_smtp_config($pdo));
                if ($row['role'] === 'admin' && $adminEmail !== '' && $smtpReady) {
                    $sendErr = panel_otp_start($pdo, $row, $adminEmail);
                    if ($sendErr !== '') {
                        $error = 'كلمة السر صحيحة لكن تعذر إرسال رمز التحقق. راجع بيانات البريد في الإعدادات.';
                    } else {
                        $info = 'تم إرسال رمز التحقق إلى ' . panel_mask_email($adminEmail) . '.';
                    }
                } else {
                    panel_login_success($row['id'], $row['username'], $row['role']);
                    header('Location: home.php');
                    exit;
                }
            } else {
                panel_login_fail();
                $error = 'اسم المستخدم أو كلمة السر غير صحيحة.';
            }
        }
    }
}

$otpPending = $pdo ? panel_otp_pending() : null;
$csrf = $pdo ? panel_csrf_token() : '';
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
        <h1>يامون بيبي</h1>
        <?php if ($otpPending): ?>
        <p>أدخل رمز التحقق المرسل إلى <?php echo panel_h(panel_mask_email($otpPending['email'])); ?></p>
        <?php if ($error): ?><div class="alert alert-err"><?php echo panel_h($error); ?></div><?php endif; ?>
        <?php if ($info): ?><div class="alert alert-ok"><?php echo panel_h($info); ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo panel_h($csrf); ?>">
            <input type="hidden" name="action" value="verify_otp">
            <label>رمز التحقق</label>
            <input class="otp-input" type="text" name="otp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
            <button class="btn btn-gold" type="submit">تأكيد الدخول</button>
        </form>
        <form method="post" style="margin-top:8px;">
            <input type="hidden" name="csrf" value="<?php echo panel_h($csrf); ?>">
            <input type="hidden" name="action" value="resend_otp">
            <button class="btn btn-ghost" type="submit">إعادة إرسال الرمز</button>
        </form>
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo panel_h($csrf); ?>">
            <input type="hidden" name="action" value="cancel_otp">
            <button class="btn btn-light" type="submit">رجوع لتسجيل الدخول</button>
        </form>
        <?php else: ?>
        <p><?php echo $hasUsers ? 'دخول نظام إدارة السحب' : 'أنشئ حساب المدير الأول'; ?></p>
        <?php if ($error): ?><div class="alert alert-err"><?php echo panel_h($error); ?></div><?php endif; ?>
        <?php if ($info): ?><div class="alert alert-ok"><?php echo panel_h($info); ?></div><?php endif; ?>
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
        <?php endif; ?>
    </div>
    </section>
    </div>
</body>
</html>
