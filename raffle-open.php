<?php
header('Cache-Control: no-store, no-cache, must-revalidate');
require __DIR__ . '/includes/coupon-token.php';
require __DIR__ . '/includes/raffle-db.php';

$cfg = coupon_cards_config();
if (!$cfg) {
    header('Location: raffle.html');
    exit;
}
$token = (string) ($_GET['t'] ?? '');
$coupon = coupon_from_token($token, $cfg['secret']);
if ($coupon === '') {
    header('Location: raffle.html');
    exit;
}

try {
    $pdo = raffle_pdo();
    if (!raffle_registration_open($pdo)) {
        header('Location: raffle-closed.html');
        exit;
    }
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
