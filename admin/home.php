<?php
require __DIR__ . '/auth.php';
panel_start();
$user = panel_require_login();
panel_lock_draw();
$isAdmin = $user['role'] === 'admin';
$tab = (string) ($_GET['tab'] ?? 'cards');
$allowedTabs = $isAdmin
    ? ['cards', 'report', 'audit', 'users', 'settings']
    : ['cards', 'report', 'settings'];
if (!in_array($tab, $allowedTabs, true)) {
    $tab = 'cards';
}

$message = '';
$error = '';

try {
    $pdo = panel_pdo();
} catch (Exception $e) {
    http_response_code(500);
    exit('تعذر الاتصال بقاعدة البيانات.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!panel_csrf_ok()) {
        $error = 'انتهت صلاحية الجلسة. أعد المحاولة.';
    } elseif ((string) ($_POST['action'] ?? '') === 'save_settings') {
        $size = (int) ($_POST['page_size'] ?? 20);
        if (in_array($size, panel_page_sizes(), true)) {
            $_SESSION['panel_page_size'] = $size;
            $message = 'تم حفظ الإعدادات.';
            $tab = 'settings';
        } else {
            $error = 'اختر عدد الأسطر 20 أو 30 أو 50.';
        }
    } elseif ((string) ($_POST['action'] ?? '') === 'save_registration') {
        $tab = 'settings';
        if (!$isAdmin) {
            $error = 'هذه العملية للمدير فقط.';
        } else {
            $open = isset($_POST['registration_open']) ? '1' : '0';
            raffle_set_setting($pdo, 'registration_open', $open);
            panel_audit($pdo, $user['username'], 'registration_toggled', null, null, $open === '1' ? 'open' : 'closed');
            $message = $open === '1' ? 'تم تفعيل التسجيل عبر QR.' : 'تم إيقاف التسجيل عبر QR.';
        }
    } elseif ((string) ($_POST['action'] ?? '') === 'save_smtp') {
        $tab = 'settings';
        if (!$isAdmin) {
            $error = 'هذه العملية للمدير فقط.';
        } else {
            $host = trim((string) ($_POST['smtp_host'] ?? ''));
            $port = (int) ($_POST['smtp_port'] ?? 587);
            $secure = (string) ($_POST['smtp_secure'] ?? 'tls');
            $smtpUser = trim((string) ($_POST['smtp_user'] ?? ''));
            $smtpPass = (string) ($_POST['smtp_pass'] ?? '');
            $from = trim((string) ($_POST['smtp_from'] ?? ''));
            $fromName = trim((string) ($_POST['smtp_from_name'] ?? 'Yamoon Baby'));
            if (!in_array($secure, ['tls', 'ssl', 'none'], true)) {
                $secure = 'tls';
            }
            if ($host === '' || $smtpUser === '') {
                $error = 'أدخل خادم SMTP واسم مستخدم البريد (الإيميل الكامل).';
            } elseif ($port < 1 || $port > 65535) {
                $error = 'منفذ SMTP غير صحيح.';
            } elseif ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
                $error = 'بريد المرسل غير صحيح.';
            } elseif ($smtpPass === '' && raffle_setting($pdo, 'smtp_pass', '') === '') {
                $error = 'أدخل كلمة سر صندوق البريد.';
            }
            if ($error === '') {
                if ($from === '') {
                    $from = $smtpUser;
                }
                raffle_set_setting($pdo, 'smtp_host', $host);
                raffle_set_setting($pdo, 'smtp_port', (string) $port);
                raffle_set_setting($pdo, 'smtp_secure', $secure);
                raffle_set_setting($pdo, 'smtp_user', $smtpUser);
                raffle_set_setting($pdo, 'smtp_from', $from);
                raffle_set_setting($pdo, 'smtp_from_name', $fromName === '' ? 'Yamoon Baby' : substr($fromName, 0, 80));
                if ($smtpPass !== '') {
                    raffle_set_setting($pdo, 'smtp_pass', panel_encrypt_secret($smtpPass));
                }
                panel_audit($pdo, $user['username'], 'smtp_updated', null, null, $smtpUser);
                $message = 'تم حفظ إعدادات بريد الإرسال.';
            }
        }
    } elseif ((string) ($_POST['action'] ?? '') === 'test_smtp') {
        $tab = 'settings';
        if (!$isAdmin) {
            $error = 'هذه العملية للمدير فقط.';
        } else {
            $to = panel_admin_email($pdo);
            $cfg = panel_smtp_config($pdo);
            if (!panel_smtp_ready($cfg)) {
                $error = 'احفظ إعدادات SMTP أولاً مع كلمة سر البريد.';
            } elseif ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
                $error = 'احفظ بريد المدير الرئيسي من شاشة المستخدمين أولاً ليصله الاختبار.';
            } else {
                try {
                    panel_smtp_send(
                        $cfg,
                        $to,
                        'اختبار البريد — يامون بيبي',
                        panel_mail_html('تم اختبار بريد الإرسال بنجاح.', 'يمكنك الآن تفعيل رمز التحقق عند دخول المدير.')
                    );
                    $message = 'تم إرسال رسالة اختبار إلى ' . panel_mask_email($to) . '.';
                } catch (Exception $e) {
                    $error = 'فشل الاختبار. تأكد من الخادم والمنفذ وكلمة سر البريد.';
                }
            }
        }
    } elseif ((string) ($_POST['action'] ?? '') === 'save_admin_email') {
        $tab = 'users';
        if (!$isAdmin) {
            $error = 'هذه العملية للمدير فقط.';
        } else {
            $adminPassword = (string) ($_POST['admin_password'] ?? '');
            $email = trim((string) ($_POST['admin_email'] ?? ''));
            $stmt = $pdo->prepare('SELECT password_hash FROM panel_users WHERE id = ? LIMIT 1');
            $stmt->execute([$user['id']]);
            $me = $stmt->fetch();
            if (!$me || !password_verify($adminPassword, $me['password_hash'])) {
                $error = 'كلمة سر المدير غير صحيحة.';
            } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'صيغة البريد الإلكتروني غير صحيحة.';
            } elseif (strlen($email) > 120) {
                $error = 'البريد الإلكتروني طويل جداً.';
            } else {
                raffle_set_setting($pdo, 'admin_email', $email);
                panel_audit($pdo, $user['username'], 'email_updated', null, null, $email === '' ? 'disabled' : $email);
                $message = $email === ''
                    ? 'تم إلغاء بريد التحقق. دخول المدير سيتم بكلمة السر فقط.'
                    : 'تم حفظ البريد. عند دخول المدير سيُرسل رمز تحقق إلى هذا الإيميل.';
            }
        }
    } elseif (!$isAdmin) {
        $error = 'هذه العملية للمدير فقط.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        $stmt = $pdo->prepare('SELECT password_hash FROM panel_users WHERE id = ? LIMIT 1');
        $stmt->execute([$user['id']]);
        $me = $stmt->fetch();

        if ($action === 'delete') {
            $password = (string) ($_POST['password'] ?? '');
            $entryId = (int) ($_POST['entry_id'] ?? 0);
            if (!$me || !password_verify($password, $me['password_hash'])) {
                $error = 'كلمة السر غير صحيحة. لم يتم الحذف.';
            } else {
                $find = $pdo->prepare('SELECT id, coupon, phone, full_name FROM raffle_entries WHERE id = ? LIMIT 1');
                $find->execute([$entryId]);
                $entry = $find->fetch();
                if (!$entry) {
                    $error = 'البطاقة غير موجودة.';
                } else {
                    $image = raffle_entry_image($entry['coupon'], $entry['phone']);
                    $pdo->prepare('DELETE FROM raffle_entries WHERE id = ?')->execute([$entryId]);
                    if ($image !== '' && is_file($image)) {
                        @unlink($image);
                    }
                    panel_audit($pdo, $user['username'], 'deleted', $entry['coupon'], $entryId, $entry['full_name']);
                    $message = 'تم حذف البطاقة ' . $entry['coupon'] . '.';
                }
            }
        } elseif ($action === 'add_user') {
            $tab = 'users';
            $username = trim((string) ($_POST['username'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            if (!preg_match('/^[A-Za-z0-9_.-]{3,40}$/', $username) || strlen($password) < 8) {
                $error = 'اسم المستخدم 3 إلى 40 حرفاً إنجليزياً، وكلمة السر 8 أحرف على الأقل.';
            } else {
                try {
                    $ins = $pdo->prepare('INSERT INTO panel_users (username, password_hash, role) VALUES (?, ?, ?)');
                    $ins->execute([$username, password_hash($password, PASSWORD_DEFAULT), 'user']);
                    panel_audit($pdo, $user['username'], 'user_added', null, null, $username);
                    $message = 'تم إنشاء المستخدم ' . $username . '.';
                } catch (PDOException $e) {
                    $error = 'اسم المستخدم موجود مسبقاً.';
                }
            }
        } elseif ($action === 'set_user_password') {
            $tab = 'users';
            $targetId = (int) ($_POST['user_id'] ?? 0);
            $adminPassword = (string) ($_POST['admin_password'] ?? '');
            $new = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');
            $target = null;
            if ($targetId > 0) {
                $findUser = $pdo->prepare('SELECT id, username, role FROM panel_users WHERE id = ? LIMIT 1');
                $findUser->execute([$targetId]);
                $target = $findUser->fetch();
            }
            if (!$me || !password_verify($adminPassword, $me['password_hash'])) {
                $error = 'كلمة سر المدير غير صحيحة.';
            } elseif (!$target) {
                $error = 'المستخدم غير موجود.';
            } elseif (strlen($new) < 8) {
                $error = 'كلمة السر الجديدة يجب ألا تقل عن 8 أحرف.';
            } elseif ($new !== $confirm) {
                $error = 'تأكيد كلمة السر غير مطابق.';
            } else {
                $pdo->prepare('UPDATE panel_users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($new, PASSWORD_DEFAULT), $targetId]);
                panel_audit($pdo, $user['username'], 'password_changed', null, null, $target['username']);
                $message = 'تم تغيير كلمة سر ' . $target['username'] . '.';
            }
        } elseif ($action === 'delete_user') {
            $tab = 'users';
            $targetId = (int) ($_POST['user_id'] ?? 0);
            $adminPassword = (string) ($_POST['admin_password'] ?? '');
            $target = null;
            if ($targetId > 0) {
                $findUser = $pdo->prepare('SELECT id, username, role FROM panel_users WHERE id = ? LIMIT 1');
                $findUser->execute([$targetId]);
                $target = $findUser->fetch();
            }
            if (!$me || !password_verify($adminPassword, $me['password_hash'])) {
                $error = 'كلمة سر المدير غير صحيحة.';
            } elseif (!$target) {
                $error = 'المستخدم غير موجود.';
            } elseif ($target['role'] !== 'user') {
                $error = 'لا يمكن حذف المدير.';
            } elseif ((int) $target['id'] === (int) $user['id']) {
                $error = 'لا يمكن حذف حسابك الحالي.';
            } else {
                $pdo->prepare('DELETE FROM panel_users WHERE id = ? AND role = ?')->execute([$targetId, 'user']);
                panel_audit($pdo, $user['username'], 'user_deleted', null, null, $target['username']);
                $message = 'تم حذف المستخدم ' . $target['username'] . '.';
            }
        }
    }
}

$allEntries = panel_filter_entries($pdo);
$pageSize = panel_page_size();
$page = max(1, (int) ($_GET['page'] ?? 1));
$filteredCount = count($allEntries);
$totalPages = max(1, (int) ceil($filteredCount / $pageSize));
if ($page > $totalPages) {
    $page = $totalPages;
}
$entries = array_slice($allEntries, ($page - 1) * $pageSize, $pageSize);
$rowStart = ($page - 1) * $pageSize;
$users = [];
$audits = [];
$auditPage = 1;
$auditPages = 1;
$adminEmail = '';
$smtpReady = false;
$smtpCfg = [
    'host' => '',
    'port' => '587',
    'secure' => 'tls',
    'user' => '',
    'pass' => '',
    'from' => '',
    'from_name' => 'Yamoon Baby',
];
$registrationOpen = false;
if ($isAdmin && $tab === 'users') {
    $users = $pdo->query('SELECT id, username, role, created_at FROM panel_users ORDER BY id')->fetchAll();
    $adminEmail = panel_admin_email($pdo);
    $smtpReady = panel_smtp_ready(panel_smtp_config($pdo));
}
if ($isAdmin && $tab === 'settings') {
    $registrationOpen = raffle_registration_open($pdo);
    $smtpCfg = panel_smtp_config($pdo);
    $smtpReady = panel_smtp_ready($smtpCfg);
}
if ($isAdmin && $tab === 'audit') {
    $allAudits = $pdo->query('SELECT id, actor, action, coupon, entry_id, details, created_at FROM raffle_audit ORDER BY id DESC LIMIT 500')->fetchAll();
    $auditPages = max(1, (int) ceil(count($allAudits) / $pageSize));
    $auditPage = min($page, $auditPages);
    $audits = array_slice($allAudits, ($auditPage - 1) * $pageSize, $pageSize);
}

$from = panel_h($_GET['from'] ?? '');
$to = panel_h($_GET['to'] ?? '');
$gov = (string) ($_GET['governorate'] ?? '');
$csrf = panel_csrf_token();
$totalCards = (int) $pdo->query('SELECT COUNT(*) FROM raffle_entries')->fetchColumn();
$totalAudits = $isAdmin ? (int) $pdo->query('SELECT COUNT(*) FROM raffle_audit')->fetchColumn() : 0;
$totalUsers = $isAdmin ? (int) $pdo->query('SELECT COUNT(*) FROM panel_users')->fetchColumn() : 0;

function panel_when($value) {
    $ts = strtotime((string) $value);
    return $ts ? date('d-m-Y H:i', $ts) : (string) $value;
}

function panel_pager_url($tab, $pageNum) {
    $query = [
        'tab' => $tab,
        'page' => $pageNum,
        'from' => (string) ($_GET['from'] ?? ''),
        'to' => (string) ($_GET['to'] ?? ''),
        'governorate' => (string) ($_GET['governorate'] ?? ''),
    ];
    return 'home.php?' . http_build_query(array_filter($query, static function ($value) {
        return $value !== '';
    }));
}

$title = $tab === 'report' ? 'تقرير البطاقات' : ($tab === 'audit' ? 'سجل العمليات' : ($tab === 'users' ? 'المستخدمون' : ($tab === 'settings' ? 'الإعدادات' : 'البطاقات المدخلة')));
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>يامون بيبي</title>
    <?php echo panel_brand_links(); ?>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@600;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css?v=20260916g">
</head>
<body class="dash">
<div class="app">
    <aside class="sidebar no-print">
        <div class="logo">
            <img class="sidebar-logo" src="<?php echo panel_h(panel_web_root() . '/assets/img/logo.png'); ?>" alt="يامون بيبي">
            <div>
                <h1>يامون بيبي</h1>
                <p><?php echo $isAdmin ? 'نظام إدارة السحب' : 'شاشة العرض'; ?></p>
            </div>
        </div>
        <nav class="nav">
            <a class="<?php echo $tab === 'cards' ? 'active' : ''; ?>" href="home.php?tab=cards"><i class="fas fa-id-card"></i> البطاقات المدخلة</a>
            <a class="<?php echo $tab === 'report' ? 'active' : ''; ?>" href="home.php?tab=report"><i class="fas fa-chart-line"></i> تقرير البطاقات</a>
            <?php if ($isAdmin): ?>
                <a href="../tools/print-test-cards.php"><i class="fas fa-print"></i> طباعة كوبون تجريبي</a>
                <a href="../tools/print-a3-cards.php"><i class="fas fa-print"></i> طباعة A4 — 6 بطاقات</a>
                <a href="draw.php"><i class="fas fa-dharmachakra"></i> السحب على الفائز</a>
                <a class="<?php echo $tab === 'audit' ? 'active' : ''; ?>" href="home.php?tab=audit"><i class="fas fa-clock-rotate-left"></i> سجل العمليات</a>
                <a class="<?php echo $tab === 'users' ? 'active' : ''; ?>" href="home.php?tab=users"><i class="fas fa-users-gear"></i> المستخدمون</a>
            <?php endif; ?>
            <a class="<?php echo $tab === 'settings' ? 'active' : ''; ?>" href="home.php?tab=settings"><i class="fas fa-gear"></i> الإعدادات</a>
        </nav>
        <div class="side-user">
            <strong><?php echo panel_h($user['username']); ?></strong>
            <span><?php echo $isAdmin ? 'مدير النظام' : 'مستخدم عرض'; ?></span>
        </div>
    </aside>
    <main class="main">
        <section class="dash-hero no-print">
            <img class="dash-hero-bg" src="<?php echo panel_h(panel_web_root() . '/assets/img/login-hero.jpg'); ?>" alt="">
            <div class="dash-hero-shade"></div>
            <div class="dash-hero-inner">
                <div class="dash-hero-copy">
                    <p class="topbar-kicker">لوحة يامون بيبي</p>
                    <h2><?php echo panel_h($title); ?></h2>
                    <span>السحب على الجائزة الكبرى</span>
                    <strong>شقة والعديد من الجوائز</strong>
                    <em>موعد السحب 25 / 05 / 2027</em>
                </div>
            </div>
            <div class="actions dash-hero-actions">
                <a class="btn btn-ghost" href="../index.html"><i class="fas fa-globe"></i> الموقع</a>
                <a class="btn btn-gold" href="logout.php"><i class="fas fa-sign-out-alt"></i> خروج</a>
            </div>
        </section>
        <h2 class="dash-print-title"><?php echo panel_h($title); ?></h2>
        <div class="dash-workspace">

        <?php if ($tab === 'cards'): ?>
        <div class="stats">
            <div class="stat">
                <span class="stat-icon i-ticket"><i class="fas fa-ticket-alt"></i></span>
                <div>
                    <span>إجمالي البطاقات</span>
                    <b><?php echo $totalCards; ?></b>
                </div>
            </div>
            <div class="stat">
                <span class="stat-icon i-filter"><i class="fas fa-filter"></i></span>
                <div>
                    <span>نتائج التصفية</span>
                    <b><?php echo $filteredCount; ?></b>
                </div>
            </div>
            <div class="stat">
                <span class="stat-icon i-users"><i class="fas fa-<?php echo $isAdmin ? 'users' : 'eye'; ?>"></i></span>
                <div>
                    <span><?php echo $isAdmin ? 'المستخدمون' : 'صلاحية العرض'; ?></span>
                    <b><?php echo $isAdmin ? $totalUsers : 'قراءة فقط'; ?></b>
                </div>
            </div>
        </div>
        <?php endif; ?>

    <?php if ($message): ?><div class="alert"><?php echo panel_h($message); ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-err"><?php echo panel_h($error); ?></div><?php endif; ?>

    <?php if ($tab === 'cards' || $tab === 'report'): ?>
    <div class="card">
        <form class="filters no-print" method="get">
            <input type="hidden" name="tab" value="<?php echo panel_h($tab); ?>">
            <label class="filter-item">
                <span>من تاريخ</span>
                <input type="date" name="from" value="<?php echo $from; ?>">
            </label>
            <label class="filter-item">
                <span>إلى تاريخ</span>
                <input type="date" name="to" value="<?php echo $to; ?>">
            </label>
            <label class="filter-item">
                <span>المحافظة</span>
                <select name="governorate">
                    <option value="">كل المحافظات</option>
                    <?php foreach (panel_governorates() as $name): ?>
                        <option value="<?php echo panel_h($name); ?>" <?php echo $gov === $name ? 'selected' : ''; ?>><?php echo panel_h($name); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="filter-actions">
                <button class="btn btn-blue" type="submit">عرض</button>
                <?php if ($tab === 'report'): ?>
                    <button class="btn btn-gold" type="button" onclick="window.print()">طباعة</button>
                <?php endif; ?>
            </div>
        </form>

        <div class="table-wrap">
            <table class="data-table">
                <colgroup>
                    <col style="width:6%">
                    <col style="width:12%">
                    <col style="width:14%">
                    <col style="width:12%">
                    <col style="width:10%">
                    <col style="width:8%">
                    <col style="width:10%">
                    <col style="width:14%">
                    <col style="width:8%">
                    <?php if ($isAdmin && $tab === 'cards'): ?><col style="width:6%"><?php endif; ?>
                </colgroup>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>رقم البطاقة</th>
                        <th>الاسم</th>
                        <th>رقم الهاتف</th>
                        <th>المحافظة</th>
                        <th>التقييم</th>
                        <th>حضور الحفل</th>
                        <th>التاريخ</th>
                        <th>صورة</th>
                        <?php if ($isAdmin && $tab === 'cards'): ?><th>حذف</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$entries): ?>
                    <tr><td colspan="<?php echo $isAdmin && $tab === 'cards' ? 10 : 9; ?>" class="muted">لا توجد بطاقات مطابقة.</td></tr>
                <?php endif; ?>
                <?php foreach ($entries as $index => $row):
                    $hasImage = raffle_entry_image($row['coupon'], $row['phone']) !== '';
                ?>
                    <tr>
                        <td><?php echo $rowStart + $index + 1; ?></td>
                        <td><span class="coupon-code">YM<?php echo panel_h($row['coupon']); ?></span></td>
                        <td><?php echo panel_h($row['full_name'] !== '' ? $row['full_name'] : '—'); ?></td>
                        <td><?php echo panel_h($row['phone']); ?></td>
                        <td><?php echo panel_h($row['governorate']); ?></td>
                        <td><?php echo isset($row['product_rating']) && $row['product_rating'] !== null && $row['product_rating'] !== '' ? (int) $row['product_rating'] : '—'; ?></td>
                        <td><?php echo panel_h(raffle_attend_label($row['attend_ceremony'] ?? null)); ?></td>
                        <td><?php echo panel_h(panel_when($row['created_at'])); ?></td>
                        <td>
                            <?php if ($hasImage): ?>
                                <a href="image.php?id=<?php echo (int) $row['id']; ?>" target="_blank">
                                    <img class="thumb" src="image.php?id=<?php echo (int) $row['id']; ?>" alt="">
                                </a>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <?php if ($isAdmin && $tab === 'cards'): ?>
                        <td>
                            <button class="btn btn-red" type="button" data-del="<?php echo (int) $row['id']; ?>" data-coupon="<?php echo panel_h($row['coupon']); ?>">حذف</button>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="pager no-print">
            <?php if ($page > 1): ?><a class="btn btn-ghost" href="<?php echo panel_h(panel_pager_url($tab, $page - 1)); ?>">السابق</a><?php endif; ?>
            <strong>صفحة <?php echo $page; ?> من <?php echo $totalPages; ?></strong>
            <?php if ($page < $totalPages): ?><a class="btn btn-ghost" href="<?php echo panel_h(panel_pager_url($tab, $page + 1)); ?>">التالي</a><?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'settings'): ?>
    <?php if ($isAdmin): ?>
    <div class="card">
        <h3>تسجيل الكوبونات عبر QR</h3>
        <p class="muted">عند الإيقاف لا تعمل بطاقات QR ولا يمكن التسجيل. فعّلها عند بدء المسابقة.</p>
        <p class="reg-status <?php echo $registrationOpen ? 'on' : 'off'; ?>">
            الحالة الآن: <?php echo $registrationOpen ? 'مفعّل' : 'متوقف'; ?>
        </p>
        <form method="post" class="settings-form">
            <input type="hidden" name="csrf" value="<?php echo panel_h($csrf); ?>">
            <input type="hidden" name="action" value="save_registration">
            <label class="toggle-row">
                <input type="checkbox" name="registration_open" value="1" <?php echo $registrationOpen ? 'checked' : ''; ?>>
                تفعيل التسجيل
            </label>
            <button class="btn btn-gold" type="submit">حفظ حالة التسجيل</button>
        </form>
    </div>
    <div class="card">
        <h3>بريد الإرسال (SMTP)</h3>
        <p class="muted">هذه بيانات صندوق البريد الذي يُرسل منه رمز الدخول. أنشئ إيميلاً من cPanel مثل <strong dir="ltr">noreply@yamoonbaby.com</strong> ثم أدخل بياناته هنا.</p>
        <p class="reg-status <?php echo $smtpReady ? 'on' : 'off'; ?>">
            الحالة الآن: <?php echo $smtpReady ? 'جاهز للإرسال' : 'غير مكتمل'; ?>
        </p>
        <form method="post" class="settings-form settings-form-wide" autocomplete="off">
            <input type="hidden" name="csrf" value="<?php echo panel_h($csrf); ?>">
            <input type="hidden" name="action" value="save_smtp">
            <label for="smtp_host">خادم SMTP</label>
            <input type="text" id="smtp_host" name="smtp_host" required maxlength="120" dir="ltr" value="<?php echo panel_h($smtpCfg['host']); ?>" placeholder="mail.yamoonbaby.com">
            <div class="grid-2">
                <label>
                    <span>المنفذ</span>
                    <input type="number" name="smtp_port" min="1" max="65535" required value="<?php echo panel_h($smtpCfg['port']); ?>">
                </label>
                <label>
                    <span>التشفير</span>
                    <select name="smtp_secure">
                        <option value="tls" <?php echo $smtpCfg['secure'] === 'tls' ? 'selected' : ''; ?>>TLS — المنفذ 587</option>
                        <option value="ssl" <?php echo $smtpCfg['secure'] === 'ssl' ? 'selected' : ''; ?>>SSL — المنفذ 465</option>
                        <option value="none" <?php echo $smtpCfg['secure'] === 'none' ? 'selected' : ''; ?>>بدون</option>
                    </select>
                </label>
            </div>
            <label for="smtp_user">إيميل الإرسال / اسم المستخدم</label>
            <input type="text" id="smtp_user" name="smtp_user" required maxlength="120" dir="ltr" value="<?php echo panel_h($smtpCfg['user']); ?>" placeholder="noreply@yamoonbaby.com">
            <label for="smtp_pass">كلمة سر هذا الإيميل</label>
            <input type="password" id="smtp_pass" name="smtp_pass" maxlength="200" dir="ltr" placeholder="<?php echo $smtpCfg['pass'] !== '' ? 'اتركه فارغاً للإبقاء على المحفوظ' : 'كلمة سر صندوق البريد'; ?>">
            <label for="smtp_from">بريد المرسل الظاهر</label>
            <input type="email" id="smtp_from" name="smtp_from" maxlength="120" dir="ltr" value="<?php echo panel_h($smtpCfg['from']); ?>" placeholder="noreply@yamoonbaby.com">
            <label for="smtp_from_name">الاسم الظاهر</label>
            <input type="text" id="smtp_from_name" name="smtp_from_name" maxlength="80" value="<?php echo panel_h($smtpCfg['from_name']); ?>">
            <button class="btn btn-gold" type="submit">حفظ إعدادات البريد</button>
        </form>
        <form method="post" style="margin-top:12px;">
            <input type="hidden" name="csrf" value="<?php echo panel_h($csrf); ?>">
            <input type="hidden" name="action" value="test_smtp">
            <button class="btn btn-blue" type="submit">إرسال رسالة اختبار</button>
        </form>
    </div>
    <?php endif; ?>
    <div class="card">
        <h3>إعدادات العرض</h3>
        <form method="post" class="settings-form">
            <input type="hidden" name="csrf" value="<?php echo panel_h($csrf); ?>">
            <input type="hidden" name="action" value="save_settings">
            <label for="page_size">عدد الأسطر في الصفحة</label>
            <select id="page_size" name="page_size">
                <?php foreach (panel_page_sizes() as $size): ?>
                    <option value="<?php echo $size; ?>" <?php echo $pageSize === $size ? 'selected' : ''; ?>><?php echo $size; ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-blue" type="submit">حفظ الإعدادات</button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($isAdmin && $tab === 'audit'): ?>
    <div class="card">
        <h3>تقرير العمليات على البطاقات والمستخدمين — <?php echo $totalAudits; ?> عملية</h3>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>المستخدم</th>
                        <th>العملية</th>
                        <th>رقم البطاقة</th>
                        <th>التفاصيل</th>
                        <th>التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$audits): ?>
                    <tr><td colspan="6" class="muted">لا توجد عمليات بعد.</td></tr>
                <?php endif; ?>
                <?php foreach ($audits as $log): ?>
                    <tr>
                        <td><?php echo (int) $log['id']; ?></td>
                        <td><?php echo panel_h($log['actor']); ?></td>
                        <td><span class="pill <?php echo panel_h($log['action']); ?>"><?php echo panel_h(panel_action_label($log['action'])); ?></span></td>
                        <td><?php echo $log['coupon'] ? panel_h($log['coupon']) : '-'; ?></td>
                        <td><?php echo panel_h($log['details'] ?: '-'); ?></td>
                        <td><?php echo panel_h(panel_when($log['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($auditPages > 1): ?>
        <div class="pager no-print">
            <?php if ($auditPage > 1): ?><a class="btn btn-ghost" href="<?php echo panel_h(panel_pager_url('audit', $auditPage - 1)); ?>">السابق</a><?php endif; ?>
            <strong>صفحة <?php echo $auditPage; ?> من <?php echo $auditPages; ?></strong>
            <?php if ($auditPage < $auditPages): ?><a class="btn btn-ghost" href="<?php echo panel_h(panel_pager_url('audit', $auditPage + 1)); ?>">التالي</a><?php endif; ?>
        </div>
        <?php endif; ?>
        <button class="btn btn-gold no-print" type="button" onclick="window.print()">طباعة السجل</button>
    </div>
    <?php endif; ?>

    <?php if ($isAdmin && $tab === 'users'): ?>
    <div class="card">
        <h3>بريد المدير الرئيسي</h3>
        <p class="muted">هذا البريد للمستخدم الرئيسي فقط، ويصل إليه رمز التحقق بعد ضبط <strong>بريد الإرسال (SMTP)</strong> من شاشة الإعدادات. اتركه فارغاً لإيقاف الرمز.</p>
        <?php if ($adminEmail !== ''): ?>
        <p class="reg-status on">بريد الاستلام: <?php echo panel_h(panel_mask_email($adminEmail)); ?></p>
        <?php else: ?>
        <p class="reg-status off">لم يُحفظ بريد الاستلام بعد</p>
        <?php endif; ?>
        <?php if (!$smtpReady): ?>
        <p class="reg-status off">بريد الإرسال غير مكتمل — اضبطه من الإعدادات ثم أعد حفظ هذا البريد</p>
        <?php endif; ?>
        <form method="post" class="settings-form" autocomplete="off">
            <input type="hidden" name="csrf" value="<?php echo panel_h($csrf); ?>">
            <input type="hidden" name="action" value="save_admin_email">
            <label for="admin_email">البريد الإلكتروني</label>
            <input type="email" id="admin_email" name="admin_email" maxlength="120" value="<?php echo panel_h($adminEmail); ?>" placeholder="name@example.com" dir="ltr">
            <label for="admin_email_password">كلمة سر المدير للتأكيد</label>
            <input type="password" id="admin_email_password" name="admin_password" required autocomplete="current-password">
            <button class="btn btn-gold" type="submit">حفظ البريد</button>
        </form>
    </div>
    <div class="card">
        <h3>المستخدمون الحاليون</h3>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>اسم المستخدم</th>
                        <th>الصلاحية</th>
                        <th>تاريخ الإنشاء</th>
                        <th>كلمة السر</th>
                        <th>حذف</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $index => $row): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo panel_h($row['username']); ?></td>
                        <td><span class="pill <?php echo $row['role'] === 'admin' ? 'admin' : ''; ?>"><?php echo $row['role'] === 'admin' ? 'مدير' : 'مستخدم'; ?></span></td>
                        <td><?php echo panel_h(panel_when($row['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-gold" type="button" data-pass-id="<?php echo (int) $row['id']; ?>" data-pass-name="<?php echo panel_h($row['username']); ?>">تغيير</button>
                        </td>
                        <td>
                            <?php if ($row['role'] === 'user'): ?>
                            <button class="btn btn-red" type="button" data-user-del="<?php echo (int) $row['id']; ?>" data-user-name="<?php echo panel_h($row['username']); ?>">حذف</button>
                            <?php else: ?>
                            —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <h3>إضافة مستخدم</h3>
        <p class="muted">المستخدم العادي يرى التقارير فقط ولا يستطيع الحذف أو التعديل.</p>
        <form class="panel-form" method="post">
            <input type="hidden" name="csrf" value="<?php echo panel_h($csrf); ?>">
            <input type="hidden" name="action" value="add_user">
            <label>
                <span>اسم المستخدم</span>
                <input type="text" name="username" required maxlength="40" pattern="[A-Za-z0-9_.-]+">
            </label>
            <label>
                <span>كلمة السر</span>
                <input type="password" name="password" required minlength="8">
            </label>
            <button class="btn btn-blue" type="submit">إنشاء مستخدم</button>
        </form>
    </div>
    <?php endif; ?>
        </div>
    </main>
</div>

<?php if ($isAdmin): ?>
<div class="overlay" id="userDeleteOverlay">
    <form class="modal" method="post">
        <h3>حذف مستخدم</h3>
        <p>أدخل كلمة سر المدير لحذف المستخدم <strong id="userDelName"></strong>.</p>
        <input type="hidden" name="csrf" value="<?php echo panel_h($csrf); ?>">
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="user_id" id="userDelId">
        <label>كلمة سر المدير</label>
        <input type="password" name="admin_password" required>
        <div style="display:flex;gap:8px;margin-top:14px;">
            <button class="btn btn-red" type="submit">حذف</button>
            <button class="btn btn-light" type="button" id="userDelCancel">إلغاء</button>
        </div>
    </form>
</div>
<div class="overlay" id="userPassOverlay">
    <form class="modal" method="post">
        <h3>تغيير كلمة السر</h3>
        <p>تغيير كلمة سر <strong id="userPassName"></strong>.</p>
        <input type="hidden" name="csrf" value="<?php echo panel_h($csrf); ?>">
        <input type="hidden" name="action" value="set_user_password">
        <input type="hidden" name="user_id" id="userPassId">
        <label>كلمة سر المدير</label>
        <input type="password" name="admin_password" required>
        <label style="margin-top:12px;">كلمة السر الجديدة</label>
        <input type="password" name="new_password" required minlength="8">
        <label style="margin-top:12px;">تأكيد كلمة السر</label>
        <input type="password" name="confirm_password" required minlength="8">
        <div style="display:flex;gap:8px;margin-top:14px;">
            <button class="btn btn-gold" type="submit">حفظ</button>
            <button class="btn btn-light" type="button" id="userPassCancel">إلغاء</button>
        </div>
    </form>
</div>
<div class="overlay" id="deleteOverlay">
    <form class="modal" method="post">
        <h3>تأكيد الحذف</h3>
        <p>أدخل كلمة سر المدير لحذف البطاقة <strong id="delCoupon"></strong>.</p>
        <input type="hidden" name="csrf" value="<?php echo panel_h($csrf); ?>">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="entry_id" id="delId">
        <label>كلمة السر</label>
        <input type="password" name="password" required>
        <div style="display:flex;gap:8px;margin-top:14px;">
            <button class="btn btn-red" type="submit">حذف</button>
            <button class="btn btn-light" type="button" id="delCancel">إلغاء</button>
        </div>
    </form>
</div>
<script>
document.querySelectorAll('[data-del]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('delId').value = this.getAttribute('data-del');
        document.getElementById('delCoupon').textContent = this.getAttribute('data-coupon');
        document.getElementById('deleteOverlay').classList.add('show');
    });
});
document.getElementById('delCancel').addEventListener('click', function () {
    document.getElementById('deleteOverlay').classList.remove('show');
});
document.querySelectorAll('[data-user-del]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('userDelId').value = this.getAttribute('data-user-del');
        document.getElementById('userDelName').textContent = this.getAttribute('data-user-name');
        document.getElementById('userDeleteOverlay').classList.add('show');
    });
});
document.getElementById('userDelCancel').addEventListener('click', function () {
    document.getElementById('userDeleteOverlay').classList.remove('show');
});
document.querySelectorAll('[data-pass-id]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('userPassId').value = this.getAttribute('data-pass-id');
        document.getElementById('userPassName').textContent = this.getAttribute('data-pass-name');
        document.getElementById('userPassOverlay').classList.add('show');
    });
});
document.getElementById('userPassCancel').addEventListener('click', function () {
    document.getElementById('userPassOverlay').classList.remove('show');
});
</script>
<?php endif; ?>
</body>
</html>
