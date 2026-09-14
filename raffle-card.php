<?php
require __DIR__ . '/coupon-token.php';
require __DIR__ . '/raffle-session.php';
require __DIR__ . '/raffle-db.php';
raffle_session_start();

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, no-cache, must-revalidate');

$cfgFile = __DIR__ . '/cards-secret.php';
if (!is_readable($cfgFile)) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'نظام البطاقات غير جاهز.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$cfg = require $cfgFile;
$token = (string) ($_GET['t'] ?? '');
$coupon = coupon_from_token($token, $cfg['secret']);
if ($coupon === '') {
    unset($_SESSION['raffle_coupon'], $_SESSION['raffle_token']);
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'هذه البطاقة غير صحيحة. امسح رمز QR من البطاقة الأصلية.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (raffle_coupon_used(raffle_pdo(), $coupon)) {
        unset($_SESSION['raffle_coupon'], $_SESSION['raffle_token']);
        http_response_code(409);
        echo json_encode(['ok' => false, 'used' => true, 'error' => 'البطاقة مستخدمة.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'تعذر التحقق من البطاقة الآن.'], JSON_UNESCAPED_UNICODE);
    exit;
}

session_regenerate_id(true);
$_SESSION['raffle_coupon'] = $coupon;
$_SESSION['raffle_token'] = $token;

echo json_encode(['ok' => true, 'coupon' => $coupon], JSON_UNESCAPED_UNICODE);
