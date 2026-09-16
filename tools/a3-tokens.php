<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}
require dirname(__DIR__) . '/includes/coupon-token.php';$cfg = coupon_cards_config();
if (!$cfg) {
    fwrite(STDERR, "cards-secret.php missing\n");
    exit(1);
}
$start = max(1, min(500000, (int) ($argv[1] ?? 1)));
$count = max(1, min(10, (int) ($argv[2] ?? 10)));
$out = [];
for ($n = 0; $n < $count; $n++) {
    $value = $start + $n;
    if ($value > 500000) {
        break;
    }
    $coupon = str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    $token = coupon_encrypt($coupon, $cfg['secret']);
    $out[] = [
        'coupon' => $coupon,
        'label' => coupon_public_label($coupon),
        'url' => $cfg['base_url'] . '?t=' . rawurlencode($token),
    ];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
