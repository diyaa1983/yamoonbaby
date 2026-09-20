<?php
require_once dirname(__DIR__) . '/includes/raffle-session.php';
require_once dirname(__DIR__) . '/includes/raffle-db.php';

function panel_start() {
    raffle_session_start();
}

function panel_pdo() {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $pdo = raffle_pdo();
    try {
        panel_ensure_schema($pdo);
    } catch (Exception $e) {
        try {
            $pdo->query('SELECT 1 FROM panel_users LIMIT 1');
        } catch (Exception $e2) {
            throw new RuntimeException('pdo-schema');
        }
    }
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

function panel_force_logout() {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    header('Location: ' . panel_web_root() . '/admin/index.php');
    exit;
}

function panel_web_root() {
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (preg_match('#^(.*?)/(admin|tools)/#', $script, $m)) {
        return $m[1];
    }
    return '';
}

function panel_require_login() {
    $user = panel_user();
    if (!$user) {
        header('Location: ' . panel_web_root() . '/admin/index.php');
        exit;
    }
    $idle = 45 * 60;
    $last = (int) ($_SESSION['panel_last_active'] ?? 0);
    if ($last > 0 && (time() - $last) > $idle) {
        panel_force_logout();
    }
    $_SESSION['panel_last_active'] = time();
    try {
        $pdo = panel_pdo();
        $stmt = $pdo->prepare('SELECT id, username, role FROM panel_users WHERE id = ? LIMIT 1');
        $stmt->execute([$user['id']]);
        $row = $stmt->fetch();
        if (!$row) {
            panel_force_logout();
        }
        $_SESSION['panel_username'] = (string) $row['username'];
        $_SESSION['panel_role'] = (string) $row['role'];
        return [
            'id' => (int) $row['id'],
            'username' => (string) $row['username'],
            'role' => (string) $row['role'],
        ];
    } catch (Exception $e) {
        return $user;
    }
}

function panel_require_admin() {
    $user = panel_require_login();
    if ($user['role'] !== 'admin') {
        header('Location: ' . panel_web_root() . '/admin/home.php');
        exit;
    }
    return $user;
}

function panel_draw_unlocked() {
    $opened = (int) ($_SESSION['panel_draw_ok'] ?? 0);
    return $opened > 0 && (time() - $opened) < 900;
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

function panel_brand_links() {
    $href = panel_web_root() . '/assets/img/logo.png';
    return '<link rel="icon" type="image/png" href="' . panel_h($href) . '">'
        . '<link rel="apple-touch-icon" href="' . panel_h($href) . '">';
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
    return count($hits) >= 5;
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
    $_SESSION['panel_last_active'] = time();
    $_SESSION['panel_csrf'] = bin2hex(random_bytes(16));
    unset($_SESSION['admin_logged_in'], $_SESSION['panel_otp']);
}

function panel_admin_email(PDO $pdo) {
    return trim(raffle_setting($pdo, 'admin_email', ''));
}

function panel_mask_email($email) {
    $email = trim((string) $email);
    $at = strpos($email, '@');
    if ($at === false || $at < 1) {
        return $email;
    }
    $name = substr($email, 0, $at);
    $domain = substr($email, $at);
    $keep = min(2, max(1, strlen($name) - 1));
    return substr($name, 0, $keep) . str_repeat('*', max(1, strlen($name) - $keep)) . $domain;
}

function panel_otp_pending() {
    $otp = $_SESSION['panel_otp'] ?? null;
    if (!is_array($otp) || empty($otp['hash']) || empty($otp['expires'])) {
        return null;
    }
    if ((int) $otp['expires'] < time()) {
        unset($_SESSION['panel_otp']);
        return null;
    }
    return $otp;
}

function panel_otp_clear() {
    unset($_SESSION['panel_otp']);
}

function panel_otp_code() {
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function panel_crypto_key() {
    $root = dirname(__DIR__);
    $material = 'yamoon-panel-v1';
    foreach ([$root . '/cards-secret.php', $root . '/db-config.php'] as $file) {
        if (!is_readable($file)) {
            continue;
        }
        $cfg = include $file;
        if (is_array($cfg) && !empty($cfg['secret'])) {
            $material .= '|' . $cfg['secret'];
            break;
        }
        if (is_array($cfg) && !empty($cfg['pass'])) {
            $material .= '|' . $cfg['pass'];
            break;
        }
    }
    return hash('sha256', $material, true);
}

function panel_encrypt_secret($plain) {
    $plain = (string) $plain;
    if ($plain === '' || !function_exists('openssl_encrypt')) {
        return $plain;
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', panel_crypto_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false || $tag === '') {
        return $plain;
    }
    return 'enc:' . rtrim(strtr(base64_encode($iv . $tag . $cipher), '+/', '-_'), '=');
}

function panel_decrypt_secret($stored) {
    $stored = (string) $stored;
    if ($stored === '' || strpos($stored, 'enc:') !== 0 || !function_exists('openssl_decrypt')) {
        return $stored;
    }
    $raw = base64_decode(strtr(substr($stored, 4), '-_', '+/'), true);
    if ($raw === false || strlen($raw) < 29) {
        return '';
    }
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', panel_crypto_key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? '' : $plain;
}

function panel_smtp_config(PDO $pdo) {
    $from = trim(raffle_setting($pdo, 'smtp_from', ''));
    $user = trim(raffle_setting($pdo, 'smtp_user', ''));
    return [
        'host' => trim(raffle_setting($pdo, 'smtp_host', '')),
        'port' => trim(raffle_setting($pdo, 'smtp_port', '587')),
        'secure' => trim(raffle_setting($pdo, 'smtp_secure', 'tls')),
        'user' => $user,
        'pass' => panel_decrypt_secret((string) raffle_setting($pdo, 'smtp_pass', '')),
        'from' => $from !== '' ? $from : $user,
        'from_name' => trim(raffle_setting($pdo, 'smtp_from_name', 'Yamoon Baby')),
    ];
}

function panel_smtp_ready(array $cfg) {
    $port = (int) ($cfg['port'] ?? 0);
    return ($cfg['host'] ?? '') !== ''
        && $port >= 1
        && $port <= 65535
        && ($cfg['user'] ?? '') !== ''
        && ($cfg['pass'] ?? '') !== ''
        && filter_var($cfg['from'] ?? '', FILTER_VALIDATE_EMAIL);
}

function panel_smtp_read($fp) {
    $data = '';
    while (($line = fgets($fp, 1024)) !== false) {
        $data .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    return $data;
}

function panel_smtp_expect($fp, $cmd, $ok) {
    if ($cmd !== '') {
        fwrite($fp, $cmd . "\r\n");
    }
    $resp = panel_smtp_read($fp);
    $code = (int) substr($resp, 0, 3);
    $ok = is_array($ok) ? $ok : [$ok];
    if (!in_array($code, $ok, true)) {
        throw new RuntimeException(trim($resp) !== '' ? trim($resp) : 'لا رد من خادم البريد');
    }
    return $resp;
}

function panel_smtp_send(array $cfg, $to, $subjectText, $htmlBody) {
    $host = $cfg['host'];
    $port = (int) $cfg['port'];
    $secure = strtolower((string) $cfg['secure']);
    $timeout = 25;
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ]);
    $fp = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);
    if (!$fp) {
        throw new RuntimeException('تعذر الاتصال بخادم البريد (' . $errno . ')');
    }
    stream_set_timeout($fp, $timeout);
    try {
        panel_smtp_expect($fp, '', 220);
        $ehloHost = preg_replace('/^ssl:\/\//', '', $host);
        panel_smtp_expect($fp, 'EHLO ' . $ehloHost, 250);
        if ($secure === 'tls') {
            panel_smtp_expect($fp, 'STARTTLS', 220);
            $cryptoOk = false;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $cryptoOk = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT);
            }
            if (!$cryptoOk) {
                $cryptoOk = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            }
            if (!$cryptoOk) {
                throw new RuntimeException('تعذر تفعيل التشفير TLS');
            }
            panel_smtp_expect($fp, 'EHLO ' . $ehloHost, 250);
        }
        $auth = panel_smtp_expect($fp, 'AUTH LOGIN', [334, 504, 535]);
        if ((int) substr($auth, 0, 3) === 334) {
            panel_smtp_expect($fp, base64_encode($cfg['user']), 334);
            panel_smtp_expect($fp, base64_encode($cfg['pass']), 235);
        } else {
            panel_smtp_expect($fp, 'AUTH PLAIN ' . base64_encode("\0" . $cfg['user'] . "\0" . $cfg['pass']), 235);
        }
        $from = $cfg['from'];
        panel_smtp_expect($fp, 'MAIL FROM:<' . $from . '>', 250);
        panel_smtp_expect($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
        panel_smtp_expect($fp, 'DATA', 354);
        $fromName = trim($cfg['from_name']) !== '' ? $cfg['from_name'] : 'Yamoon Baby';
        $fromHeader = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . '>';
        $subject = '=?UTF-8?B?' . base64_encode($subjectText) . '?=';
        $payload = [
            'Date: ' . date('r'),
            'From: ' . $fromHeader,
            'To: <' . $to . '>',
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            $htmlBody,
            '.',
        ];
        panel_smtp_expect($fp, implode("\r\n", $payload), 250);
        fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}

function panel_mail_html($title, $intro, $highlight = '') {
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeIntro = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');
    $block = $highlight === '' ? '' : '<p style="font-size:32px;letter-spacing:8px;font-weight:800;color:#0f172a;margin:0 0 18px;direction:ltr;">'
        . htmlspecialchars($highlight, ENT_QUOTES, 'UTF-8') . '</p>';
    return '<!DOCTYPE html><html lang="ar" dir="rtl"><body style="font-family:Tahoma,Arial,sans-serif;background:#f4f7fb;padding:24px;">'
        . '<div style="max-width:480px;margin:auto;background:#fff;border-radius:18px;padding:28px;text-align:center;">'
        . '<h2 style="color:#0b4f86;margin:0 0 12px;">يامون بيبي</h2>'
        . '<p style="color:#334155;margin:0 0 18px;">' . $safeTitle . '</p>'
        . $block
        . '<p style="color:#64748b;font-size:14px;margin:0;">' . $safeIntro . '</p>'
        . '</div></body></html>';
}

function panel_send_login_code(PDO $pdo, $email, $code) {
    $cfg = panel_smtp_config($pdo);
    if (!panel_smtp_ready($cfg)) {
        return 'not-configured';
    }
    try {
        panel_smtp_send(
            $cfg,
            $email,
            'رمز الدخول — يامون بيبي',
            panel_mail_html('رمز التحقق لدخول المدير الرئيسي:', 'صالح لمدة 10 دقائق. إذا لم تطلب الدخول فتجاهل هذه الرسالة.', $code)
        );
        return '';
    } catch (Exception $e) {
        return 'send-failed';
    }
}

function panel_otp_start(PDO $pdo, $user, $email) {
    $code = panel_otp_code();
    $err = panel_send_login_code($pdo, $email, $code);
    if ($err !== '') {
        return $err;
    }
    $_SESSION['panel_otp'] = [
        'id' => (int) $user['id'],
        'username' => (string) $user['username'],
        'role' => (string) $user['role'],
        'email' => (string) $email,
        'hash' => password_hash($code, PASSWORD_DEFAULT),
        'expires' => time() + 600,
        'tries' => 0,
        'sent_at' => time(),
    ];
    return '';
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
        'email_updated' => 'تحديث بريد المدير',
        'smtp_updated' => 'تحديث بريد الإرسال',
        'registration_toggled' => 'تغيير حالة التسجيل',
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

function panel_digits($value) {
    $map = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];
    $value = strtr((string) $value, $map);
    $value = preg_replace('/[Yy][Mm]/', '', $value);
    return preg_replace('/\D+/', '', $value);
}

function panel_search_coupon() {
    if (trim((string) ($_GET['q'] ?? '')) === '') {
        return '';
    }
    $digits = panel_digits($_GET['q'] ?? '');
    if ($digits === '' || strlen($digits) > 6) {
        return '__none__';
    }
    return str_pad($digits, 6, '0', STR_PAD_LEFT);
}

function panel_rating_label($value) {
    if ($value === null || $value === '') {
        return '—';
    }
    $n = (int) $value;
    return ($n >= 1 && $n <= 10) ? ($n . ' / 10') : '—';
}

function panel_filter_entries(PDO $pdo) {
    $from = preg_replace('/[^0-9\-]/', '', (string) ($_GET['from'] ?? ''));
    $to = preg_replace('/[^0-9\-]/', '', (string) ($_GET['to'] ?? ''));
    $governorate = trim((string) ($_GET['governorate'] ?? ''));
    $coupon = panel_search_coupon();
    raffle_ensure_entries_schema($pdo);
    $sql = 'SELECT id, full_name, phone, governorate, coupon, product_rating, attend_ceremony, created_at FROM raffle_entries WHERE 1=1';
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
    if ($coupon !== '') {
        $sql .= ' AND coupon = ?';
        $params[] = $coupon;
    }
    $sql .= ' ORDER BY id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}
