<?php

session_start();
require_once '../db.php';
require_once '../gemini.php';
header('Content-Type: application/json');

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST required']); exit;
}

if (empty($_FILES['document']['tmp_name'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']); exit;
}

$file    = $_FILES['document'];
$tmpPath = $file['tmp_name'];
$origName= $file['name'];
$ext     = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$mime    = mime_content_type($tmpPath);

$extractedText = '';
$useVision     = false;
$visionBase64  = '';
$visionMime    = '';

// ── 1. Extract text / prepare vision payload ─────────────────────────────────
if (in_array($ext, ['jpg','jpeg','png','gif','webp']) || str_starts_with($mime, 'image/')) {
    // Image: use Gemini Vision
    $useVision    = true;
    $visionBase64 = base64_encode(file_get_contents($tmpPath));
    $visionMime   = $mime ?: 'image/jpeg';

} elseif ($ext === 'pdf') {
    // Try pdftotext (common on XAMPP via poppler)
    $escaped = escapeshellarg($tmpPath);
    $text    = shell_exec("pdftotext {$escaped} - 2>/dev/null");
    if ($text && strlen(trim($text)) > 30) {
        $extractedText = trim($text);
    } else {
        // Fallback: treat as binary, grab any readable ASCII
        $raw = file_get_contents($tmpPath);
        preg_match_all('/[ -~\n\r\t]{4,}/', $raw, $m);
        $extractedText = substr(implode(' ', $m[0]), 0, 4000);
    }

} elseif (in_array($ext, ['docx'])) {
    // DOCX is a ZIP containing word/document.xml
    $za = new ZipArchive();
    if ($za->open($tmpPath) === true) {
        $xmlContent = $za->getFromName('word/document.xml');
        $za->close();
        if ($xmlContent) {
            // Strip XML tags
            $extractedText = strip_tags(str_replace(
                ['</w:p>', '</w:tr>'],
                ["\n", "\n"],
                $xmlContent
            ));
            $extractedText = preg_replace('/\s+/', ' ', $extractedText);
            $extractedText = substr(trim($extractedText), 0, 4000);
        }
    }

} elseif ($ext === 'doc') {
    // Legacy .doc: basic readable text extraction
    $raw = file_get_contents($tmpPath);
    preg_match_all('/[\x20-\x7E\n\r\t]{4,}/', $raw, $m);
    $extractedText = substr(implode(' ', $m[0]), 0, 4000);

} elseif ($ext === 'txt') {
    $extractedText = substr(file_get_contents($tmpPath), 0, 4000);

} else {
    echo json_encode(['success' => false, 'message' => 'Unsupported file type. Use PDF, DOCX, DOC, TXT, or an image.']); exit;
}

// ── 2. Build Gemini prompt ────────────────────────────────────────────────────
$systemPrompt = <<<PROMPT
You are an AI assistant that extracts structured event information from an event proposal or OPLAN document.

Extract the following fields from the document and return them as a JSON object (no markdown, no explanation):
{
  "EventName": "Full name/title of the event",
  "EventType": "One of: General Assembly, Leadership Summit, Seminar / Workshop, Sports Event, Cultural Event, Community Service, Induction Ceremony, Team Building, Other",
  "EventDescription": "A 2-3 sentence description of the event",
  "EventDate": "YYYY-MM-DD format if found, else empty string",
  "EventTimeStart": "HH:MM 24h format if found, else empty string",
  "EventTimeEnd": "HH:MM 24h format if found, else empty string",
  "EventPlace": "Venue or location name",
  "EventSpeaker": "Speaker or resource person name (if mentioned)",
  "EventCapacity": "Expected number of attendees as integer (if mentioned), else 0",
  "EventMode": "One of: On-site, Online, Hybrid (On-site + Online)",
  "AISummary": "A 3-4 sentence human-friendly summary of what this event is about"
}

If any field is not clearly stated in the document, use an empty string or 0.
PROMPT;

// ── 3. Call Gemini ────────────────────────────────────────────────────────────
$rawResponse = null;

if ($useVision) {
    // Multimodal call
    $payload = json_encode([
        'contents' => [[
            'parts' => [
                ['text' => $systemPrompt . "\n\nAnalyze the document image above and extract the event information."],
                ['inline_data' => ['mime_type' => $visionMime, 'data' => $visionBase64]],
            ]
        ]],
        'generationConfig' => ['maxOutputTokens' => 1024, 'temperature' => 0.2],
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
    curl_close($ch);

    $data = json_decode($resp, true);
    $rawResponse = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

} else {
    if (empty(trim($extractedText))) {
        echo json_encode(['success' => false, 'message' => 'Could not extract readable text from the document. Try uploading an image instead.']); exit;
    }

    $fullPrompt = $systemPrompt . "\n\nDocument text:\n" . $extractedText;
    $rawResponse = geminiAsk($fullPrompt, 1024);
}

if (!$rawResponse) {
    echo json_encode(['success' => false, 'message' => 'AI could not analyze the document. Check your Gemini API key or try again.']); exit;
}

// ── 4. Parse JSON from response ───────────────────────────────────────────────
$rawResponse = preg_replace('/^```(?:json)?\s*/m', '', trim($rawResponse));
$rawResponse = preg_replace('/```\s*$/m', '', $rawResponse);

$extracted = json_decode(trim($rawResponse), true);
if (!is_array($extracted)) {
    echo json_encode(['success' => false, 'message' => 'AI returned an unparseable response. Try again.', 'raw' => $rawResponse]); exit;
}

echo json_encode(['success' => true, 'data' => $extracted]);
