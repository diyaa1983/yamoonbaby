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
