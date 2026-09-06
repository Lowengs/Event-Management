<?php
/**
 * NAAP Certificate Generator Helper
 * Generates high-quality certificate image with student name overlay using PHP GD and TrueType font.
 */

function generateCertificateImageFile($templateRelativePath, $studentName, $fieldConfig, $eventId, $userId) {
    if (empty($templateRelativePath) || empty($studentName)) {
        return null;
    }

    $baseDir = dirname(__DIR__);
    $cleanPath = ltrim(str_replace(['../', '..\\'], '', $templateRelativePath), '/\\');
    $templateFullPath = $baseDir . '/' . $cleanPath;

    if (!file_exists($templateFullPath)) {
        return null;
    }

    $ext = strtolower(pathinfo($templateFullPath, PATHINFO_EXTENSION));
    $im = null;
    if ($ext === 'png') {
        $im = @imagecreatefrompng($templateFullPath);
    } elseif ($ext === 'jpg' || $ext === 'jpeg') {
        $im = @imagecreatefromjpeg($templateFullPath);
    } elseif ($ext === 'webp') {
        $im = @imagecreatefromwebp($templateFullPath);
    }

    if (!$im) {
        return null;
    }

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
        $bbox = imagettfbbox($scaledFontSize, 0, $fontFile, $studentName);
        $textWidth = abs($bbox[4] - $bbox[0]);
        $textHeight = abs($bbox[5] - $bbox[1]);
        $posX = (int)round(($w * $xPct) - ($textWidth / 2));
        $posY = (int)round(($h * $yPct) + ($textHeight / 2));
        imagettftext($im, $scaledFontSize, 0, $posX, $posY, $textColor, $fontFile, $studentName);
    } else {
        $font = 5;
        $fontWidth = imagefontwidth($font);
        $fontHeight = imagefontheight($font);
        $textWidth = $fontWidth * strlen($studentName);
        $posX = (int)round(($w * $xPct) - ($textWidth / 2));
        $posY = (int)round(($h * $yPct) - ($fontHeight / 2));
        imagestring($im, $font, $posX, $posY, $studentName, $textColor);
    }

    $uploadDir = $baseDir . '/assets/uploads/certificates';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $outFileName = 'cert_' . $eventId . '_' . $userId . '_' . time() . '.png';
    $outFullPath = $uploadDir . '/' . $outFileName;

    // Remove older generated images for this event and user
    $oldFiles = glob($uploadDir . '/cert_' . $eventId . '_' . $userId . '_*.png');
    if ($oldFiles) {
        foreach ($oldFiles as $of) @unlink($of);
    }

    imagepng($im, $outFullPath, 8);
    imagedestroy($im);

    return 'assets/uploads/certificates/' . $outFileName;
}
