<?php

header('Content-Type: application/json');
require_once '../gemini.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']); exit;
}

if (empty($_FILES['cor']['tmp_name'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']); exit;
}

$file    = $_FILES['cor'];
$tmpPath = $file['tmp_name'];
$origName= $file['name'];
$ext     = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$mime    = mime_content_type($tmpPath);

$useVision    = false;
$visionBase64 = '';
$visionMime   = '';
$extractedText= '';

// ── 1. Prepare file data ──────────────────────────────────────────────────────
if (in_array($ext, ['jpg','jpeg','png','gif','webp','bmp']) || str_starts_with($mime, 'image/')) {
    $useVision    = true;
    $visionBase64 = base64_encode(file_get_contents($tmpPath));
    $visionMime   = $mime ?: 'image/jpeg';

} elseif ($ext === 'pdf') {
    // Try pdftotext
    $escaped = escapeshellarg($tmpPath);
    $text    = @shell_exec("pdftotext {$escaped} - 2>/dev/null");
    if ($text && strlen(trim($text)) > 20) {
        $extractedText = trim($text);
    } else {
        // Try reading as image (some PDFs are image-only)
        // Convert first page using Ghostscript if available
        $imgOut = sys_get_temp_dir() . '/cor_page_' . time() . '.png';
        $gs = @shell_exec("gswin64c -dNOPAUSE -dBATCH -sDEVICE=png16m -r150 -sOutputFile={$imgOut} {$escaped} 2>/dev/null");
        if (!$gs) {
            $gs = @shell_exec("gs -dNOPAUSE -dBATCH -sDEVICE=png16m -r150 -sOutputFile={$imgOut} {$escaped} 2>/dev/null");
        }
        if (file_exists($imgOut)) {
            $useVision    = true;
            $visionBase64 = base64_encode(file_get_contents($imgOut));
            $visionMime   = 'image/png';
            @unlink($imgOut);
        } else {
            // Raw text fallback
            $raw = file_get_contents($tmpPath);
            preg_match_all('/[\x20-\x7E\n\r\t]{4,}/', $raw, $m);
            $extractedText = substr(implode(' ', $m[0]), 0, 3000);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Unsupported COR format. Use JPG, PNG, or PDF.']); exit;
}

// ── 2. Build prompt ───────────────────────────────────────────────────────────
$prompt = <<<PROMPT
You are an AI assistant that reads a Certificate of Registration (COR) from a Philippine school or university.

Extract the following student information and return ONLY a JSON object (no markdown, no explanation, no extra text):
{
  "StudentId": "Student ID number (e.g. 2024MN-001234)",
  "FirstName": "Student's first name only",
  "MiddleName": "Student's middle name (if present, else empty string)",
  "LastName": "Student's last name/surname only",
  "Course": "Course or program code (e.g. BSAIT, BSAMT, AAMT)",
  "YearLevel": "Year level as text (e.g. 1st Year, 2nd Year, 3rd Year, 4th Year)",
  "Section": "Section number if visible (integer or empty string)",
  "SchoolYear": "Academic/school year if visible (e.g. 2024-2025)",
  "Confidence": "Your confidence level: high, medium, or low"
}

If a field is not clearly visible in the document, use an empty string.
Focus on accuracy. The Student ID is very important.
PROMPT;

// ── 3. Call Gemini ────────────────────────────────────────────────────────────
$rawResponse = null;

if ($useVision) {
    $payload = json_encode([
        'contents' => [[
            'parts' => [
                ['text' => $prompt],
                ['inline_data' => ['mime_type' => $visionMime, 'data' => $visionBase64]],
            ]
        ]],
        'generationConfig' => ['maxOutputTokens' => 512, 'temperature' => 0.1],
    ]);

    $ch = curl_init(GEMINI_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || !$resp) {
        echo json_encode(['success' => false, 'message' => 'AI connection failed: ' . $err]); exit;
    }

    $data = json_decode($resp, true);
    $rawResponse = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

} else {
    if (empty(trim($extractedText))) {
        echo json_encode(['success' => false, 'message' => 'Could not read text from COR. Please upload a clearer image.']); exit;
    }
    $fullPrompt  = $prompt . "\n\nCOR Text:\n" . $extractedText;
    $rawResponse = geminiAsk($fullPrompt, 512);
}

if (!$rawResponse) {
    echo json_encode(['success' => false, 'message' => 'AI did not return a response. Check Gemini API key.']); exit;
}

// ── 4. Parse JSON ─────────────────────────────────────────────────────────────
$clean = preg_replace('/^```(?:json)?\s*/m', '', trim($rawResponse));
$clean = preg_replace('/```\s*$/m', '', $clean);

$extracted = json_decode(trim($clean), true);
if (!is_array($extracted)) {
    echo json_encode(['success' => false, 'message' => 'AI response could not be parsed.', 'raw' => $rawResponse]); exit;
}

echo json_encode(['success' => true, 'data' => $extracted]);
