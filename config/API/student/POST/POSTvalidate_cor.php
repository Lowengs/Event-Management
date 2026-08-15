<?php
/**
 * Student API: POST Validate Certificate of Registration (COR)
 * Endpoint: /config/API/endpoints/index.php?action=validate_cor
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../gemini.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$file = $_FILES['cor'] ?? $_FILES['cor_document'] ?? null;

if (!$file || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'message' => 'Please select and upload your Certificate of Registration (COR) document.'
    ]);
    exit;
}

$studentId  = trim($_POST['student_id']  ?? '');
$firstName  = trim($_POST['first_name']  ?? '');
$middleName = trim($_POST['middle_name'] ?? '');
$lastName   = trim($_POST['last_name']   ?? '');
$course     = trim($_POST['course']      ?? '');
$yearLevel  = trim($_POST['year_level']  ?? '');
$section    = trim($_POST['section']     ?? '');

$ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
$allowedExts = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];

if (!in_array($ext, $allowedExts, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file format. Please upload a PDF or image file (PNG, JPG, WEBP).'
    ]);
    exit;
}

// Determine MIME type
$mimeType = mime_content_type($file['tmp_name']) ?: ($ext === 'pdf' ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext));

// Read file data
$fileData = @file_get_contents($file['tmp_name']);
if (!$fileData) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to read the uploaded document. Please try uploading again.'
    ]);
    exit;
}

$base64Data = base64_encode($fileData);

// Check if Gemini API is available for validation
$apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
$hasValidGeminiKey = !empty($apiKey) && $apiKey !== 'YOUR_GEMINI_API_KEY_HERE';

if ($hasValidGeminiKey) {
    $prompt = <<<PROMPT
You are an automated document validation engine for university enrollment and student registration.
Analyze this uploaded Certificate of Registration (COR) / Enrollment Assessment Form and compare it with the student registration details:

Submitted Student Details:
- Student ID: {$studentId}
- First Name: {$firstName}
- Middle Name: {$middleName}
- Last Name: {$lastName}
- Course: {$course}
- Year Level: {$yearLevel}

Instructions:
1. Determine if the document is an official Certificate of Registration (COR), Enrollment Assessment Form, or official student record.
2. Check if the Student ID and/or Name present in the document match the submitted student details.
3. Allow standard name formats (e.g. "LASTNAME, FIRSTNAME MIDDLE", "FIRSTNAME LASTNAME", abbreviated middle initials, or minor whitespace/casing variations).
4. If either the Student ID or the Student's Full Name matches the document, mark is_valid as true.
5. If the document is completely invalid (e.g. a different student's document, blank page, unreadable image, or unrelated file), mark is_valid as false with a concise, polite explanation in "reason".

Respond ONLY with valid JSON in this exact structure without markdown or backticks:
{
  "is_valid": true,
  "confidence": "high",
  "detected_student_id": "...",
  "detected_name": "...",
  "reason": "Validation message"
}
PROMPT;

    try {
        $raw = geminiAnalyzeBase64Image($prompt, $base64Data, $mimeType, 1024);
        
        if ($raw && strpos($raw, 'CURL_ERROR') === false && strpos($raw, 'API_ERROR') === false && strpos($raw, 'NO_CONTENT') === false) {
            $cleaned = preg_replace('/^```(?:json)?\s*/m', '', trim($raw));
            $cleaned = preg_replace('/```\s*$/m', '', $cleaned);
            $parsed = json_decode($cleaned, true);

            if (is_array($parsed) && isset($parsed['is_valid'])) {
                if ($parsed['is_valid'] === true) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Certificate of Registration (COR) validated successfully.',
                        'data'    => $parsed
                    ]);
                    exit;
                } else {
                    $reason = !empty($parsed['reason']) ? $parsed['reason'] : 'The uploaded COR does not match your inputted student ID or name.';
                    echo json_encode([
                        'success' => false,
                        'message' => $reason,
                        'details' => $parsed
                    ]);
                    exit;
                }
            }
        }
    } catch (\Throwable $e) {
        error_log('COR AI Validation error: ' . $e->getMessage());
    }
}

// Fallback: If AI is offline/unreachable or key is not set, accept upload gracefully
echo json_encode([
    'success' => true,
    'message' => 'COR document accepted successfully.',
    'fallback' => true
]);
exit;
