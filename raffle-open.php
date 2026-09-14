<?php
header('Cache-Control: no-store, no-cache, must-revalidate');
require __DIR__ . '/coupon-token.php';
require __DIR__ . '/raffle-db.php';

$cfgFile = __DIR__ . '/cards-secret.php';
if (!is_readable($cfgFile)) {
    header('Location: raffle-used.html');
    exit;
}
$cfg = require $cfgFile;
$token = (string) ($_GET['t'] ?? '');
$coupon = coupon_from_token($token, $cfg['secret']);
if ($coupon === '') {
    header('Location: raffle.html');
    exit;
}

try {
    $pdo = raffle_pdo();
    if (raffle_coupon_used($pdo, $coupon)) {
        header('Location: raffle-used.html');
        exit;
    }
} catch (Exception $e) {
    header('Location: raffle.html?t=' . rawurlencode($token));
    exit;
}

header('Location: raffle.html?t=' . rawurlencode($token));
exit;
