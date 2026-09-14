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
if ($isAdmin && $tab === 'users') {
    $users = $pdo->query('SELECT id, username, role, created_at FROM panel_users ORDER BY id')->fetchAll();
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
    <title><?php echo $isAdmin ? 'لوحة المدير' : 'شاشة المستخدم'; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@600;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="app">
    <aside class="sidebar no-print">
        <div class="logo">
            <div class="logo-mark">YB</div>
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
        <div class="topbar">
            <div>
                <h2><?php echo panel_h($title); ?></h2>
            </div>
            <div class="actions no-print">
                <a class="btn btn-ghost" href="../index.html">الموقع</a>
                <a class="btn btn-gold" href="logout.php">خروج</a>
            </div>
        </div>

        <?php if ($tab === 'cards'): ?>
        <div class="stats">
            <div class="stat"><span>إجمالي البطاقات</span><b><?php echo $totalCards; ?></b></div>
            <div class="stat"><span>نتائج التصفية</span><b><?php echo $filteredCount; ?></b></div>
            <div class="stat"><span><?php echo $isAdmin ? 'المستخدمون' : 'صلاحية العرض'; ?></span><b><?php echo $isAdmin ? $totalUsers : 'قراءة فقط'; ?></b></div>
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
                    <col style="width:7%">
                    <col style="width:13%">
                    <col style="width:16%">
                    <col style="width:14%">
                    <col style="width:12%">
                    <col style="width:16%">
                    <col style="width:10%">
                    <?php if ($isAdmin && $tab === 'cards'): ?><col style="width:12%"><?php endif; ?>
                </colgroup>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>رقم البطاقة</th>
                        <th>الاسم</th>
                        <th>رقم الهاتف</th>
                        <th>المحافظة</th>
                        <th>التاريخ</th>
                        <th>صورة</th>
                        <?php if ($isAdmin && $tab === 'cards'): ?><th>حذف</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$entries): ?>
                    <tr><td colspan="<?php echo $isAdmin && $tab === 'cards' ? 8 : 7; ?>" class="muted">لا توجد بطاقات مطابقة.</td></tr>
                <?php endif; ?>
                <?php foreach ($entries as $index => $row):
                    $hasImage = raffle_entry_image($row['coupon'], $row['phone']) !== '';
                ?>
                    <tr>
                        <td><?php echo $rowStart + $index + 1; ?></td>
                        <td><?php echo panel_h($row['coupon']); ?></td>
                        <td><?php echo panel_h($row['full_name'] !== '' ? $row['full_name'] : '—'); ?></td>
                        <td><?php echo panel_h($row['phone']); ?></td>
                        <td><?php echo panel_h($row['governorate']); ?></td>
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

    <?php
        $registrationOpen = false;
        if ($isAdmin && $tab === 'settings') {
            try {
                $registrationOpen = raffle_registration_open($pdo);
            } catch (Exception $e) {
                $registrationOpen = false;
            }
        }
    ?>
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
