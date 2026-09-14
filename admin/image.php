<?php
require __DIR__ . '/auth.php';
panel_start();
panel_require_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id < 1) {
    http_response_code(404);
    exit;
}

try {
    $pdo = panel_pdo();
    $stmt = $pdo->prepare('SELECT coupon, phone FROM raffle_entries WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
} catch (Exception $e) {
    http_response_code(500);
    exit;
}

if (!$row) {
    http_response_code(404);
    exit;
}

$path = raffle_entry_image($row['coupon'], $row['phone']);
if ($path === '' || !is_readable($path)) {
    http_response_code(404);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($path) ?: 'application/octet-stream';
if (!preg_match('#^image/(jpeg|png|webp)$#', $mime)) {
    http_response_code(403);
    exit;
}

header('Content-Type: ' . $mime);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
