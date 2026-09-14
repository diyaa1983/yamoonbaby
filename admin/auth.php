<?php
require_once dirname(__DIR__) . '/raffle-session.php';
require_once dirname(__DIR__) . '/raffle-db.php';

function panel_start() {
    raffle_session_start();
}

function panel_pdo() {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = raffle_pdo();
    panel_ensure_schema($pdo);
    return $pdo;
}

function panel_users_count(PDO $pdo) {
    return (int) $pdo->query('SELECT COUNT(*) FROM panel_users')->fetchColumn();
}

function panel_csrf_token() {
    if (empty($_SESSION['panel_csrf'])) {
        $_SESSION['panel_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['panel_csrf'];
}

function panel_csrf_ok() {
    $token = (string) ($_POST['csrf'] ?? '');
    return $token !== '' && hash_equals((string) ($_SESSION['panel_csrf'] ?? ''), $token);
}

function panel_user() {
    if (empty($_SESSION['panel_user_id'])) {
        return null;
    }
    return [
        'id' => (int) $_SESSION['panel_user_id'],
        'username' => (string) ($_SESSION['panel_username'] ?? ''),
        'role' => (string) ($_SESSION['panel_role'] ?? ''),
    ];
}

function panel_require_login() {
    $user = panel_user();
    if (!$user) {
        header('Location: index.php');
        exit;
    }
    return $user;
}

function panel_require_admin() {
    $user = panel_require_login();
    if ($user['role'] !== 'admin') {
        header('Location: home.php');
        exit;
    }
    return $user;
}

function panel_draw_unlocked() {
    return !empty($_SESSION['panel_draw_ok']);
}

function panel_unlock_draw() {
    $_SESSION['panel_draw_ok'] = time();
}

function panel_lock_draw() {
    unset($_SESSION['panel_draw_ok']);
}

function panel_h($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function panel_login_hits() {
    $rateFile = sys_get_temp_dir() . '/yamoon_panel_login_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $now = time();
    $hits = [];
    if (is_file($rateFile)) {
        $hits = array_filter(array_map('intval', explode(',', (string) file_get_contents($rateFile))), function ($t) use ($now) {
            return $t > $now - 600;
        });
    }
    return [$rateFile, $hits];
}

function panel_login_blocked() {
    [, $hits] = panel_login_hits();
    return count($hits) >= 8;
}

function panel_login_fail() {
    [$rateFile, $hits] = panel_login_hits();
    $hits[] = time();
    file_put_contents($rateFile, implode(',', $hits));
}

function panel_login_success($id, $username, $role) {
    session_regenerate_id(true);
    $_SESSION['panel_user_id'] = (int) $id;
    $_SESSION['panel_username'] = $username;
    $_SESSION['panel_role'] = $role;
    unset($_SESSION['admin_logged_in']);
}

function panel_governorates() {
    return [
        'عمّان', 'الزرقاء', 'إربد', 'البلقاء', 'المفرق', 'جرش',
        'عجلون', 'مادبا', 'الكرك', 'الطفيلة', 'معان', 'العقبة',
    ];
}

function panel_action_label($action) {
    $map = [
        'created' => 'إدخال بطاقة',
        'deleted' => 'حذف بطاقة',
        'user_added' => 'إضافة مستخدم',
        'user_deleted' => 'حذف مستخدم',
        'password_changed' => 'تغيير كلمة السر',
    ];
    return $map[$action] ?? $action;
}

function panel_page_sizes() {
    return [20, 30, 50];
}

function panel_page_size() {
    $size = (int) ($_SESSION['panel_page_size'] ?? 20);
    return in_array($size, panel_page_sizes(), true) ? $size : 20;
}

function panel_filter_entries(PDO $pdo) {
    $from = preg_replace('/[^0-9\-]/', '', (string) ($_GET['from'] ?? ''));
    $to = preg_replace('/[^0-9\-]/', '', (string) ($_GET['to'] ?? ''));
    $governorate = trim((string) ($_GET['governorate'] ?? ''));
    $sql = 'SELECT id, full_name, phone, governorate, coupon, created_at FROM raffle_entries WHERE 1=1';
    $params = [];
    if ($from !== '') {
        $sql .= ' AND DATE(created_at) >= ?';
        $params[] = $from;
    }
    if ($to !== '') {
        $sql .= ' AND DATE(created_at) <= ?';
        $params[] = $to;
    }
    if ($governorate !== '' && in_array($governorate, panel_governorates(), true)) {
        $sql .= ' AND governorate = ?';
        $params[] = $governorate;
    }
    $sql .= ' ORDER BY id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
