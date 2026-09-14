<?php
require dirname(__DIR__) . '/includes/tool-auth.php';
require dirname(__DIR__) . '/includes/coupon-token.php';
$cfg = coupon_cards_config_or_fail();

$start = max(1, min(500000, (int) ($_GET['start'] ?? 1)));
$cards = [];
for ($n = 0; $n < 6; $n++) {
    $value = $start + $n;
    if ($value > 500000) {
        break;
    }
    $coupon = str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    $token = coupon_encrypt($coupon, $cfg['secret']);
    $url = $cfg['base_url'] . '?t=' . rawurlencode($token);
    $cards[] = ['coupon' => $coupon, 'url' => $url];
}
$first = $cards[0]['coupon'];
$last = $cards[count($cards) - 1]['coupon'];

$dpi = 300;
$mm = static function ($value) use ($dpi) {
    return (int) round($value / 25.4 * $dpi);
};

$pageW = $mm(274);
$pageH = $mm(222);
$cardW = $mm(137);
$cardH = $mm(74);
$originX = 0;
$originY = 0;

$blankPath = dirname(__DIR__) . '/cards/yamoon-ticket-blank.jpg';
$blank = imagecreatefromjpeg($blankPath);
$srcW = imagesx($blank);
$srcH = imagesy($blank);

$page = imagecreatetruecolor($pageW, $pageH);
$white = imagecolorallocate($page, 255, 255, 255);
$black = imagecolorallocate($page, 0, 0, 0);
$navy = imagecolorallocate($page, 26, 39, 68);
imagefilledrectangle($page, 0, 0, $pageW, $pageH, $white);

$qrX = (int) round($cardW * 0.7676);
$qrY = (int) round($cardH * 0.2817);
$qrW = (int) round($cardW * 0.1689);
$qrH = (int) round($cardH * 0.3022);
$numX = (int) round($cardW * 0.7588);
$numY = (int) round($cardH * 0.6549);
$numW = (int) round($cardW * 0.1934);
$numH = (int) round($cardH * 0.0616);
$fontFile = 'C:\\Windows\\Fonts\\arialbd.ttf';

foreach ($cards as $i => $card) {
    $col = $i % 2;
    $row = intdiv($i, 2);
    $x = $originX + $col * $cardW;
    $y = $originY + $row * $cardH;

    imagecopyresampled($page, $blank, $x, $y, 0, 0, $cardW, $cardH, $srcW, $srcH);

    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=0&data=' . rawurlencode($card['url']);
    $qrData = @file_get_contents($qrUrl);
    if ($qrData) {
        $qr = imagecreatefromstring($qrData);
        if ($qr) {
            imagefilledrectangle($page, $x + $qrX, $y + $qrY, $x + $qrX + $qrW, $y + $qrY + $qrH, $white);
            imagecopyresampled($page, $qr, $x + $qrX, $y + $qrY, 0, 0, $qrW, $qrH, imagesx($qr), imagesy($qr));
            imagedestroy($qr);
        }
    }

    imagefilledrectangle($page, $x + $numX, $y + $numY, $x + $numX + $numW, $y + $numY + $numH, $white);
    imagerectangle($page, $x + $numX, $y + $numY, $x + $numX + $numW, $y + $numY + $numH, $navy);

    $label = coupon_public_label($card['coupon']);
    if (is_file($fontFile)) {
        $size = max(9, (int) ($numH * 0.52));
        $bbox = imagettfbbox($size, 0, $fontFile, $label);
        $tw = $bbox[2] - $bbox[0];
        $th = $bbox[1] - $bbox[7];
        imagettftext(
            $page,
            $size,
            0,
            $x + $numX + (int) (($numW - $tw) / 2),
            $y + $numY + (int) (($numH + $th) / 2),
            $black,
            $fontFile,
            $label
        );
    } else {
        $font = 5;
        $tw = imagefontwidth($font) * strlen($label);
        $th = imagefontheight($font);
        imagestring(
            $page,
            $font,
            $x + $numX + (int) (($numW - $tw) / 2),
            $y + $numY + (int) (($numH - $th) / 2),
            $label,
            $black
        );
    }

}

imagedestroy($blank);

$jpgPath = sys_get_temp_dir() . '/yamoon-a4.jpg';
imagejpeg($page, $jpgPath, 94);
imagedestroy($page);

$jpg = file_get_contents($jpgPath);
$jpgLen = strlen($jpg);
$pdfName = 'yamoon-cards-' . $first . '-' . $last . '.pdf';
$pdfPath = dirname(__DIR__) . '/cards/' . $pdfName;
$ptW = 274 * 72 / 25.4;
$ptH = 222 * 72 / 25.4;

$content = sprintf("q\n%.4f 0 0 %.4f 0 0 cm\n/Im1 Do\nQ\n", $ptW, $ptH);
$objects = [];
$objects[] = "<< /Type /Catalog /Pages 2 0 R /ViewerPreferences << /PrintScaling /None >> >>";
$objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
$objects[] = sprintf(
    "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.4f %.4f] /CropBox [0 0 %.4f %.4f] /Resources << /XObject << /Im1 5 0 R >> >> /Contents 4 0 R >>",
    $ptW,
    $ptH,
    $ptW,
    $ptH
);
$objects[] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
$objects[] = "<< /Type /XObject /Subtype /Image /Width {$pageW} /Height {$pageH} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$jpgLen} >>\nstream\n" . $jpg . "\nendstream";

$pdf = "%PDF-1.4\n";
$offsets = [0];
foreach ($objects as $i => $obj) {
    $offsets[] = strlen($pdf);
    $pdf .= ($i + 1) . " 0 obj\n" . $obj . "\nendobj\n";
}
$xref = strlen($pdf);
$pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
$pdf .= "0000000000 65535 f \n";
foreach (array_slice($offsets, 1) as $off) {
    $pdf .= sprintf("%010d 00000 n \n", $off);
}
$pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
file_put_contents($pdfPath, $pdf);
@unlink($jpgPath);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $pdfName . '"');
header('Content-Length: ' . filesize($pdfPath));
readfile($pdfPath);
exit;
