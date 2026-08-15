<?php
/**
 * Student API: AI Analyze COR Document
 * Endpoint: /config/API/endpoints/index.php?action=ai_analyze_cor
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';
require_once __DIR__ . '/../../../gemini.php';

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$file = $_FILES['cor'] ?? $_FILES['cor_document'] ?? null;

if (!$file || empty($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'message' => 'Please upload your Certificate of Registration (COR) first.'
    ]);
    exit;
}

$ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
$allowedExts = ['pdf', 'png', 'jpg', 'jpeg', 'webp'];

if (!in_array($ext, $allowedExts, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid file format. Please upload a PDF or image file (PNG, JPG, WEBP).'
    ]);
    exit;
}

$mimeType = mime_content_type($file['tmp_name']) ?: ($ext === 'pdf' ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext));

$fileData = @file_get_contents($file['tmp_name']);
if (!$fileData) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to read the uploaded document. Please try again.'
    ]);
    exit;
}

$base64Data = base64_encode($fileData);

$apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
$hasValidGeminiKey = !empty($apiKey) && $apiKey !== 'YOUR_GEMINI_API_KEY_HERE';

if ($hasValidGeminiKey) {
    $prompt = <<<PROMPT
You are an intelligent Certificate of Registration (COR) / Enrollment Assessment Form reader for a Philippine university.
Analyze this uploaded student document and extract all available registration information.

Extract the following fields accurately:
- StudentId: Student ID / Student Number (e.g. "2026-0001", "2024-00123")
- FirstName: First name of the student
- MiddleName: Middle name or middle initial (if present, else "")
- LastName: Last name / surname of the student
- Course: Degree program / course code (e.g. BSAIT, BSIT, BSCS, BSCpE, BSAvTech, etc.)
- YearLevel: Year level formatted as "1st Year", "2nd Year", "3rd Year", or "4th Year"
- Section: Section if found (e.g. "A", "B", "1A", "2B", etc.)
- SchoolYear: Academic school year (e.g. "2025-2026")
- Confidence: "high", "medium", or "low"

Respond ONLY with a valid JSON object in this exact format (no markdown, no backticks, no extra text):
{
  "StudentId": "...",
  "FirstName": "...",
  "MiddleName": "...",
  "LastName": "...",
  "Course": "...",
  "YearLevel": "...",
  "Section": "...",
  "SchoolYear": "...",
  "Confidence": "high"
}
PROMPT;

    try {
        $raw = geminiAnalyzeBase64Image($prompt, $base64Data, $mimeType, 1024);
        
        if ($raw && strpos($raw, 'CURL_ERROR') === false && strpos($raw, 'API_ERROR') === false && strpos($raw, 'NO_CONTENT') === false) {
            $cleaned = preg_replace('/^```(?:json)?\s*/m', '', trim($raw));
            $cleaned = preg_replace('/```\s*$/m', '', $cleaned);
            $parsed = json_decode($cleaned, true);

            if (is_array($parsed) && (!empty($parsed['StudentId']) || !empty($parsed['FirstName']) || !empty($parsed['LastName']))) {
                echo json_encode([
                    'success' => true,
                    'message' => 'COR scanned successfully!',
                    'data'    => [
                        'StudentId'   => trim($parsed['StudentId'] ?? ''),
                        'FirstName'   => trim($parsed['FirstName'] ?? ''),
                        'MiddleName'  => trim($parsed['MiddleName'] ?? ''),
                        'LastName'    => trim($parsed['LastName'] ?? ''),
                        'Course'      => trim($parsed['Course'] ?? ''),
                        'YearLevel'   => trim($parsed['YearLevel'] ?? ''),
                        'Section'     => trim($parsed['Section'] ?? ''),
                        'SchoolYear'  => trim($parsed['SchoolYear'] ?? ''),
                        'Confidence'  => trim($parsed['Confidence'] ?? 'high')
                    ]
                ]);
                exit;
            }
        }
    } catch (\Throwable $e) {
        error_log('AI COR Scan error: ' . $e->getMessage());
    }
}

// Fallback if AI is unconfigured or unable to extract
echo json_encode([
    'success' => false,
    'message' => 'Could not automatically extract text from this document. Please type your details manually.'
]);
exit;
