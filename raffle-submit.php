<?php
require __DIR__ . '/coupon-token.php';
require __DIR__ . '/raffle-session.php';
require __DIR__ . '/raffle-db.php';
raffle_session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'طريقة الطلب غير مسموحة.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$host = $_SERVER['HTTP_HOST'] ?? '';
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$sameOrigin = ($origin !== '' && strpos($origin, $host) !== false)
    || ($referer !== '' && strpos($referer, $host) !== false);
if ($host !== '' && !$sameOrigin) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'الطلب مرفوض.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rateFile = sys_get_temp_dir() . '/yamoon_raffle_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$now = time();
$hits = [];
if (is_file($rateFile)) {
    $hits = array_filter(array_map('intval', explode(',', (string) file_get_contents($rateFile))), function ($t) use ($now) {
        return $t > $now - 600;
    });
}
if (count($hits) >= 8) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'error' => 'محاولات كثيرة. حاول بعد قليل.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$hits[] = $now;
file_put_contents($rateFile, implode(',', $hits));

$data = $_POST;
if (!$data) {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (is_array($json)) {
        $data = $json;
    }
}

if (!empty($data['website_url'])) {
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}

function english_digits($value) {
    $map = [
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
    ];
    return strtr((string) $value, $map);
}

$allowedGovernorates = [
    'عمّان','الزرقاء','إربد','البلقاء','المفرق','جرش','عجلون','مادبا','الكرك','الطفيلة','معان','العقبة',
];

$fullName = trim((string) ($data['full_name'] ?? ''));
$phone = preg_replace('/\D/', '', english_digits($data['phone'] ?? ''));
$governorate = trim((string) ($data['governorate'] ?? ''));
$cardCfgFile = __DIR__ . '/cards-secret.php';
if (!is_readable($cardCfgFile)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'نظام البطاقات غير جاهز.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$cardCfg = require $cardCfgFile;
$sessionCoupon = (string) ($_SESSION['raffle_coupon'] ?? '');
$sessionToken = (string) ($_SESSION['raffle_token'] ?? '');
$postedToken = (string) ($data['card_token'] ?? '');
$coupon = '';
if ($sessionToken !== '' && $sessionCoupon !== '') {
    $fromToken = coupon_from_token($sessionToken, $cardCfg['secret']);
    if (
        $fromToken !== ''
        && hash_equals($sessionCoupon, $fromToken)
        && ($postedToken === '' || hash_equals($sessionToken, $postedToken))
    ) {
        $coupon = $fromToken;
    }
}
if ($coupon === '') {
    $postedCoupon = preg_replace('/\D/', '', english_digits($data['coupon'] ?? ''));
    $postedCoupon = str_pad(substr($postedCoupon, 0, 6), 6, '0', STR_PAD_LEFT);
    $couponNumber = (int) $postedCoupon;
    if (preg_match('/^\d{6}$/', $postedCoupon) && $couponNumber >= 1 && $couponNumber <= 500000) {
        $coupon = $postedCoupon;
    }
}

if ($coupon === '') {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'يرجى إدخال رقم البطاقة المكون من 6 أرقام.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (
    $fullName === '' || mb_strlen($fullName) > 80
    || !preg_match('/^(079|078|077)\d{7}$/', $phone)
    || !in_array($governorate, $allowedGovernorates, true)
) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'يرجى إدخال الاسم الكامل ورقم الهاتف والمحافظة بشكل صحيح.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$upload = $_FILES['coupon_image'] ?? null;
if (!$upload || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'يرجى رفع صورة الكوبون.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (($upload['size'] ?? 0) > 5 * 1024 * 1024) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'حجم صورة الكوبون يجب ألا يتجاوز 5 ميغابايت.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($upload['tmp_name']);
$extensions = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
];
if (!isset($extensions[$mime])) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'صيغة الصورة غير مسموحة. استخدم JPG أو PNG.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$archiveDir = __DIR__ . DIRECTORY_SEPARATOR . 'Archive';
if (!is_dir($archiveDir) && !mkdir($archiveDir, 0755, true)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'تعذر إنشاء مجلد حفظ الصور.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$imageName = $coupon . '_' . $phone . '.' . $extensions[$mime];
$imagePath = $archiveDir . DIRECTORY_SEPARATOR . $imageName;

$configFile = __DIR__ . '/db-config.php';
if (!is_readable($configFile)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'إعداد قاعدة البيانات غير جاهز.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$config = require $configFile;

try {
    $dsn = sprintf(
        'mysql:host=%s;port=%s;charset=%s',
        $config['host'],
        $config['port'] ?? 3306,
        $config['charset'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('USE `' . str_replace('`', '', $config['name']) . '`');
    $stmt = $pdo->prepare(
        'INSERT INTO raffle_entries (full_name, phone, governorate, coupon) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$fullName, $phone, $governorate, $coupon]);
    $entryId = (int) $pdo->lastInsertId();
    if (!move_uploaded_file($upload['tmp_name'], $imagePath)) {
        $pdo->prepare('DELETE FROM raffle_entries WHERE coupon = ?')->execute([$coupon]);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'تم رفض حفظ صورة الكوبون.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (PDOException $e) {
    $sqlState = (string) $e->getCode();
    $driverCode = (int) ($e->errorInfo[1] ?? 0);
    if ($sqlState === '23000' || $driverCode === 1062) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'البطاقة مستخدمة.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($driverCode === 1045) {
        $message = 'كلمة مرور MySQL غير صحيحة. ضع نفس كلمة مرور Workbench في db-config.php';
    } elseif ($driverCode === 1049) {
        $message = 'قاعدة yamoonbaby-data غير موجودة في MySQL.';
    } elseif ($driverCode === 2002) {
        $message = 'تعذر الاتصال بـ MySQL. تأكد أن السيرفر المحلي يعمل كما في Workbench.';
    } else {
        $message = 'تعذر حفظ المشاركة الآن.';
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    panel_ensure_schema($pdo);
    panel_audit($pdo, 'الموقع', 'created', $coupon, $entryId, $fullName);
} catch (Exception $e) {
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
