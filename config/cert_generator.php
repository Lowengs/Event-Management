<?php
/**
 * NAAP Certificate Generator Helper
 * Generates high-quality certificate image with student name overlay using PHP GD and TrueType font.
 */

function createFallbackCertificateCanvas($w, $h, $studentName, $eventTitle, $certCode, $dateStr, $orgName, $fontFile) {
    $im = imagecreatetruecolor($w, $h);
    $bg = imagecolorallocate($im, 253, 252, 248); // warm cream diploma background
    imagefill($im, 0, 0, $bg);

    $gold = imagecolorallocate($im, 197, 155, 39);
    $navy = imagecolorallocate($im, 15, 23, 42);
    $blue = imagecolorallocate($im, 37, 99, 235);
    $gray = imagecolorallocate($im, 100, 116, 139);

    // Double borders
    imagesetthickness($im, 8);
    imagerectangle($im, 40, 40, $w - 40, $h - 40, $navy);
    imagesetthickness($im, 3);
    imagerectangle($im, 54, 54, $w - 54, $h - 54, $gold);
    imagesetthickness($im, 1);
    imagerectangle($im, 62, 62, $w - 62, $h - 62, $gold);

    // Corner decorative accents
    $cornerSize = 30;
    imagefilledrectangle($im, 50, 50, 50 + $cornerSize, 50 + 4, $gold);
    imagefilledrectangle($im, 50, 50, 50 + 4, 50 + $cornerSize, $gold);
    imagefilledrectangle($im, $w - 50 - $cornerSize, 50, $w - 50, 50 + 4, $gold);
    imagefilledrectangle($im, $w - 50 - 4, 50, $w - 50, 50 + $cornerSize, $gold);
    imagefilledrectangle($im, 50, $h - 50 - 4, 50 + $cornerSize, $h - 50, $gold);
    imagefilledrectangle($im, 50, $h - 50 - $cornerSize, 50 + 4, $h - 50, $gold);
    imagefilledrectangle($im, $w - 50 - $cornerSize, $h - 50 - 4, $w - 50, $h - 50, $gold);
    imagefilledrectangle($im, $w - 50 - 4, $h - 50 - $cornerSize, $w - 50, $h - 50, $gold);

    // Draw text lines
    $lines = [
        ['text' => 'NATIONAL ASSOCIATION OF AVIATION PERSONNEL', 'size' => 20, 'y' => 160, 'color' => $gray],
        ['text' => strtoupper($orgName ?: 'STUDENT AFFAIRS & EVENT MANAGEMENT'), 'size' => 18, 'y' => 205, 'color' => $blue],
        ['text' => 'CERTIFICATE OF RECOGNITION', 'size' => 40, 'y' => 300, 'color' => $navy],
        ['text' => 'This certificate is proudly awarded to', 'size' => 20, 'y' => 390, 'color' => $gray],
        ['text' => $studentName, 'size' => 50, 'y' => 490, 'color' => $navy],
        ['text' => 'for active participation and completion of', 'size' => 20, 'y' => 580, 'color' => $gray],
        ['text' => '"' . ($eventTitle ?: 'Event') . '"', 'size' => 28, 'y' => 650, 'color' => $navy],
        ['text' => 'Issued on ' . ($dateStr ?: date('F j, Y')), 'size' => 18, 'y' => 730, 'color' => $gray],
        ['text' => 'Verified ID: ' . ($certCode ?: 'NAAP-' . strtoupper(substr(md5($studentName . $eventTitle), 0, 8))), 'size' => 15, 'y' => 950, 'color' => $gold]
    ];

    foreach ($lines as $item) {
        $t = $item['text'];
        $sz = $item['size'];
        $y = $item['y'];
        $col = $item['color'];

        if ($fontFile && function_exists('imagettftext')) {
            $bbox = @imagettfbbox($sz, 0, $fontFile, $t);
            $tw = $bbox ? abs($bbox[4] - $bbox[0]) : (strlen($t) * ($sz * 0.6));
            $x = (int)(($w - $tw) / 2);
            @imagettftext($im, $sz, 0, $x, $y, $col, $fontFile, $t);
        } else {
            $font = ($sz > 24) ? 5 : 4;
            $tw = imagefontwidth($font) * strlen($t);
            $x = (int)(($w - $tw) / 2);
            imagestring($im, $font, $x, $y - 15, $t, $col);
        }
    }

    return $im;
}

function generateCertificateImageFile($templateRelativePath, $studentName, $fieldConfig, $eventId, $userId, $extraInfo = []) {
    if (empty($studentName)) {
        return null;
    }

    $baseDir = dirname(__DIR__);
    $cleanPath = !empty($templateRelativePath) ? ltrim(str_replace(['../', '..\\'], '', $templateRelativePath), '/\\') : '';
    $templateFullPath = $cleanPath !== '' ? ($baseDir . '/' . $cleanPath) : '';

    // Locate bundled TTF font
    $fontPaths = [
        $baseDir . '/assets/fonts/arialbd.ttf',
        $baseDir . '/assets/fonts/arial.ttf',
        $baseDir . '/assets/fonts/timesbd.ttf',
        $baseDir . '/assets/fonts/times.ttf',
        $baseDir . '/assets/fonts/Inter-Bold.ttf',
        $baseDir . '/assets/fonts/Inter.ttf',
        'C:/Windows/Fonts/arialbd.ttf',
        'C:/Windows/Fonts/arial.ttf',
        'C:/Windows/Fonts/calibrib.ttf',
        'C:/Windows/Fonts/georgia.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
    ];
    $fontFile = null;
    foreach ($fontPaths as $fp) {
        if (file_exists($fp)) {
            $fontFile = $fp;
            break;
        }
    }

    $im = null;
    if (!empty($templateFullPath) && file_exists($templateFullPath)) {
        $ext = strtolower(pathinfo($templateFullPath, PATHINFO_EXTENSION));
        if ($ext === 'png') {
            $im = @imagecreatefrompng($templateFullPath);
        } elseif ($ext === 'jpg' || $ext === 'jpeg') {
            $im = @imagecreatefromjpeg($templateFullPath);
        } elseif ($ext === 'webp') {
            $im = @imagecreatefromwebp($templateFullPath);
        }
    }

    if (!$im) {
        // Synthesize fallback certificate canvas
        $eventTitle = $extraInfo['EventName'] ?? '';
        $certCode   = $extraInfo['CertCode'] ?? '';
        $dateStr    = $extraInfo['EventDate'] ?? '';
        $orgName    = $extraInfo['OrgName'] ?? '';
        $im = createFallbackCertificateCanvas(1920, 1080, $studentName, $eventTitle, $certCode, $dateStr, $orgName, $fontFile);
    } else {
        // Ensure truecolor
        if (!imageistruecolor($im)) {
            $w = imagesx($im);
            $h = imagesy($im);
            $tc = imagecreatetruecolor($w, $h);
            imagealphablending($tc, false);
            imagesavealpha($tc, true);
            imagecopy($tc, $im, 0, 0, 0, 0, $w, $h);
            imagedestroy($im);
            $im = $tc;
        }

        $w = imagesx($im);
        $h = imagesy($im);

        // Parse field config
        $xPct = 0.50;
        $yPct = 0.48;
        $fontSize = 46;
        $colorHex = '#1e293b';

        if (!empty($fieldConfig)) {
            $cfg = is_array($fieldConfig) ? $fieldConfig : json_decode($fieldConfig, true);
            if (is_array($cfg) && !empty($cfg)) {
                $nameField = null;
                foreach ($cfg as $f) {
                    if (($f['id'] ?? '') === 'student_name' || strpos(strtolower($f['label'] ?? ''), 'name') !== false) {
                        $nameField = $f;
                        break;
                    }
                }
                if (!$nameField) $nameField = $cfg[0];
                if ($nameField) {
                    $rawX = (float)($nameField['x'] ?? 0.5);
                    $rawY = (float)($nameField['y'] ?? 0.48);
                    $xPct = $rawX > 1 ? ($rawX / 100.0) : $rawX;
                    $yPct = $rawY > 1 ? ($rawY / 100.0) : $rawY;
                    $fontSize = (int)($nameField['fontSize'] ?? 46);
                    $colorHex = $nameField['color'] ?? '#1e293b';
                }
            }
        }

        // Convert hex color to RGB
        $hex = ltrim($colorHex, '#');
        if (strlen($hex) === 3) {
            $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
            $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
            $b = hexdec(str_repeat(substr($hex, 2, 2)));
        } elseif (strlen($hex) >= 6) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        } else {
            $r = 30; $g = 41; $b = 59;
        }
        $textColor = imagecolorallocate($im, $r, $g, $b);

        // Scale font size based on template width
        $scaledFontSize = max(18, (int)round($fontSize * ($w / 1200.0)));

        if ($fontFile && function_exists('imagettftext')) {
            $bbox = @imagettfbbox($scaledFontSize, 0, $fontFile, $studentName);
            $textWidth = $bbox ? abs($bbox[4] - $bbox[0]) : (strlen($studentName) * ($scaledFontSize * 0.6));
            $textHeight = $bbox ? abs($bbox[5] - $bbox[1]) : $scaledFontSize;
            $posX = (int)round(($w * $xPct) - ($textWidth / 2));
            $posY = (int)round(($h * $yPct) + ($textHeight / 2));
            @imagettftext($im, $scaledFontSize, 0, $posX, $posY, $textColor, $fontFile, $studentName);
        } else {
            $font = 5;
            $fontWidth = imagefontwidth($font);
            $fontHeight = imagefontheight($font);
            $textWidth = $fontWidth * strlen($studentName);
            $posX = (int)round(($w * $xPct) - ($textWidth / 2));
            $posY = (int)round(($h * $yPct) - ($fontHeight / 2));
            imagestring($im, $font, $posX, $posY, $studentName, $textColor);
        }
    }

    $uploadDir = $baseDir . '/assets/uploads/certificates';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $outFileName = 'cert_' . (int)$eventId . '_' . (int)$userId . '_' . time() . '.png';
    $outFullPath = $uploadDir . '/' . $outFileName;

    // Remove older generated images for this event and user
    $oldFiles = glob($uploadDir . '/cert_' . (int)$eventId . '_' . (int)$userId . '_*.png');
    if ($oldFiles) {
        foreach ($oldFiles as $of) @unlink($of);
    }

    imagepng($im, $outFullPath, 8);
    imagedestroy($im);

    return 'assets/uploads/certificates/' . $outFileName;
}
