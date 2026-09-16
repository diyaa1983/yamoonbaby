<?php
require __DIR__ . '/includes/coupon-token.php';
require __DIR__ . '/includes/raffle-session.php';
require __DIR__ . '/includes/raffle-db.php';
raffle_session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'طريقة الطلب غير مسموحة.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$host = strtolower(preg_replace('/^www\./', '', preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '')));
$originHost = strtolower(preg_replace('/^www\./', '', (string) parse_url($_SERVER['HTTP_ORIGIN'] ?? '', PHP_URL_HOST)));
$refererHost = strtolower(preg_replace('/^www\./', '', (string) parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST)));
$sameOrigin = ($originHost !== '' && hash_equals($host, $originHost))
    || ($originHost === '' && $refererHost !== '' && hash_equals($host, $refererHost));
if ($host === '' || !$sameOrigin) {
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
function raffle_save_small_jpeg($tmpPath, $destPath) {
    $info = @getimagesize($tmpPath);
    if (!$info) {
        return false;
    }
    $mime = (string) ($info['mime'] ?? '');
    if ($mime === 'image/jpeg') {
        $src = @imagecreatefromjpeg($tmpPath);
    } elseif ($mime === 'image/png') {
        $src = @imagecreatefrompng($tmpPath);
    } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $src = @imagecreatefromwebp($tmpPath);
    } else {
        return false;
    }
    if (!$src) {
        return false;
    }
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmpPath);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        if ($orientation === 3) {
            $src = imagerotate($src, 180, 0);
        } elseif ($orientation === 6) {
            $src = imagerotate($src, -90, 0);
        } elseif ($orientation === 8) {
            $src = imagerotate($src, 90, 0);
        }
    }
    $w = imagesx($src);
    $h = imagesy($src);
    $max = 1200;
    if ($w > $max || $h > $max) {
        $scale = min($max / $w, $max / $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        $src = $dst;
    }
    $ok = imagejpeg($src, $destPath, 72);
    imagedestroy($src);
    return $ok;
}

try {
    $pdo = raffle_pdo();
    if (!raffle_registration_open($pdo)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'closed' => true, 'error' => 'التسجيل غير مفعّل حالياً. سيتم فتحه لاحقاً.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'تعذر التحقق من حالة التسجيل.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$cardCfg = coupon_cards_config();
if (!$cardCfg) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'نظام البطاقات غير جاهز.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$sessionCoupon = (string) ($_SESSION['raffle_coupon'] ?? '');
$sessionToken = (string) ($_SESSION['raffle_token'] ?? '');
$postedToken = (string) ($data['card_token'] ?? '');
$coupon = '';
if (
    $sessionToken !== ''
    && $sessionCoupon !== ''
    && $postedToken !== ''
    && hash_equals($sessionToken, $postedToken)
) {
    $fromToken = coupon_from_token($sessionToken, $cardCfg['secret']);
    if ($fromToken !== '' && hash_equals($sessionCoupon, $fromToken)) {
        $coupon = $fromToken;
    }
}

if ($coupon === '') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'هذه البطاقة غير صحيحة. امسح رمز QR من البطاقة الأصلية.'], JSON_UNESCAPED_UNICODE);
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

$imageName = $coupon . '_' . $phone . '.jpg';
$imagePath = $archiveDir . DIRECTORY_SEPARATOR . $imageName;

try {
    $stmt = $pdo->prepare(
        'INSERT INTO raffle_entries (full_name, phone, governorate, coupon) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$fullName, $phone, $governorate, $coupon]);
    $entryId = (int) $pdo->lastInsertId();
    foreach (glob($archiveDir . DIRECTORY_SEPARATOR . $coupon . '_' . $phone . '.*') ?: [] as $old) {
        if (is_file($old)) {
            @unlink($old);
        }
    }
    if (!raffle_save_small_jpeg($upload['tmp_name'], $imagePath)) {
        $pdo->prepare('DELETE FROM raffle_entries WHERE coupon = ?')->execute([$coupon]);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'تم رفض حفظ صورة الكوبون.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'تعذر حفظ المشاركة الآن.'], JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    $sqlState = (string) $e->getCode();
    $driverCode = (int) ($e->errorInfo[1] ?? 0);
    if ($sqlState === '23000' || $driverCode === 1062) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'البطاقة مستخدمة.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'تعذر حفظ المشاركة الآن.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    panel_ensure_schema($pdo);
    panel_audit($pdo, 'الموقع', 'created', $coupon, $entryId, $fullName);
} catch (Exception $e) {
}

echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
