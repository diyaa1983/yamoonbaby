<?php
function coupon_pad($value) {
    $digits = preg_replace('/\D/', '', (string) $value);
    if ($digits === '') {
        return '';
    }
    return str_pad((string) (int) $digits, 6, '0', STR_PAD_LEFT);
}

function coupon_key($secret) {
    return hash('sha256', (string) $secret, true);
}

function coupon_encrypt($coupon, $secret) {
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($coupon, 'aes-256-gcm', coupon_key($secret), OPENSSL_RAW_DATA, $iv, $tag);
    return rtrim(strtr(base64_encode($iv . $tag . $cipher), '+/', '-_'), '=');
}

function coupon_decrypt($token, $secret) {
    if (!is_string($token) || $token === '') {
        return '';
    }
    $raw = base64_decode(strtr($token, '-_', '+/'), true);
    if ($raw === false || strlen($raw) < 29) {
        return '';
    }
    $iv = substr($raw, 0, 12);
    $tag = substr($raw, 12, 16);
    $cipher = substr($raw, 28);
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', coupon_key($secret), OPENSSL_RAW_DATA, $iv, $tag);
    return $plain === false ? '' : $plain;
}

function coupon_from_token($token, $secret) {
    $coupon = coupon_pad(coupon_decrypt($token, $secret));
    $number = (int) $coupon;
    if (!preg_match('/^\d{6}$/', $coupon) || $number < 1 || $number > 500000) {
        return '';
    }
    return $coupon;
}

function coupon_ticket_src($file) {
    $name = basename((string) $file);
    if ($name === 'yamoon-ticket-blank.jpg') {
        return 'ticket-blank.jpg?v=20260525';
    }
    return '../cards/' . rawurlencode($name) . '?v=20260525';
}

function coupon_cards_config() {
    $root = dirname(__DIR__);
    $parent = dirname($root);
    foreach ([
        $root . '/cards-secret.php',
        $root . '/includes/cards-secret.php',
        $parent . '/cards-secret.php',
        $parent . '/yamoonbaby.com/cards-secret.php',
        $parent . '/www.yamoonbaby.com/cards-secret.php',
        $root . '/includes/cards-secret.example.php',
    ] as $file) {
        if (!is_readable($file)) {
            continue;
        }
        $cfg = require $file;
        if (is_array($cfg) && !empty($cfg['secret']) && !empty($cfg['base_url'])) {
            return $cfg;
        }
    }
    return null;
}

function coupon_cards_config_or_fail() {
    $cfg = coupon_cards_config();
    if ($cfg) {
        return $cfg;
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>إعداد البطاقات</title>';
    echo '<style>body{font-family:Tahoma,Arial,sans-serif;max-width:720px;margin:40px auto;padding:0 20px;line-height:1.7}pre{background:#111;color:#f5f5f5;padding:16px;overflow:auto;border-radius:12px}</style></head><body>';
    echo '<h1>ملف البطاقات غير موجود</h1>';
    echo '<p>أنشئ الملف <strong>cards-secret.php</strong> بجانب <strong>index.html</strong> (نفس مكان db-config.php) بهذا المحتوى:</p>';
    echo '<pre>&lt;?php
return [
    \'secret\' =&gt; \'مفتاح-طويل-وسري-لا-تغيره-بعد-الطباعة\',
    \'base_url\' =&gt; \'https://www.yamoonbaby.com/raffle-open.php\',
];</pre>';
    echo '<p>بعد الحفظ حدّث هذه الصفحة.</p></body></html>';
    exit;
}
