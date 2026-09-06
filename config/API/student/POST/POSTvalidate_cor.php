<?php
/**
 * Student API: POST Validate Certificate of Registration (COR)
 * Performs automated document text extraction and verification against inputted student details.
 * Endpoint: /config/API/endpoints/index.php?action=validate_cor
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

$fileData = @file_get_contents($file['tmp_name']);
if (!$fileData) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to read the uploaded document. Please try uploading again.'
    ]);
    exit;
}

/**
 * Robust Local Document Scanner
 * Extracts text from PDF streams and uncompressed content, then checks for student credentials.
 */
function scanDocumentContent(string $fileData, string $ext, string $studentId, string $firstName, string $lastName, string $course = ''): array {
    $extractedText = '';

    if ($ext === 'pdf') {
        // Fast, backtrack-free stream extraction avoiding PCRE limit errors on multi-megabyte PDFs
        $offset = 0;
        $len = strlen($fileData);
        $cleanPdfStr = function(string $raw): string {
            if (strpos($raw, "\x00") !== false) {
                $conv = @mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE');
                if ($conv && preg_match('//u', $conv)) return trim($conv);
                return trim(str_replace("\x00", '', $raw));
            }
            return trim($raw);
        };

        while ($offset < $len) {
            $pos = strpos($fileData, 'stream', $offset);
            if ($pos === false) break;
            $start = $pos + 6;
            if ($start < $len && $fileData[$start] === "\r") $start++;
            if ($start < $len && $fileData[$start] === "\n") $start++;
            $end = strpos($fileData, 'endstream', $start);
            if ($end === false) break;

            $stream = substr($fileData, $start, $end - $start);
            $offset = $end + 9;

            $uncompressed = @gzuncompress($stream);
            if (!$uncompressed) $uncompressed = @gzinflate(substr($stream, 2, -4));
            if (!$uncompressed) $uncompressed = @gzinflate($stream);
            $target = $uncompressed ?: $stream;

            // Extract Tj strings: (text) Tj
            if (preg_match_all('#\((.*?)\)\s*Tj#s', $target, $tjMatches)) {
                foreach ($tjMatches[1] as $str) {
                    $c = $cleanPdfStr($str);
                    if ($c !== '') $extractedText .= ' ' . $c;
                }
            }
            // Extract TJ arrays: [(text) 123 (text)] TJ
            if (preg_match_all('#\[(.*?)\]\s*TJ#s', $target, $tjArrMatches)) {
                foreach ($tjArrMatches[1] as $tjArr) {
                    if (preg_match_all('#\((.*?)\)#s', $tjArr, $m)) {
                        $comb = '';
                        foreach ($m[1] as $part) $comb .= $cleanPdfStr($part);
                        if ($comb !== '') $extractedText .= ' ' . $comb;
                    }
                }
            }
        }
    } else {
        // Image or plain content
        $extractedText = $fileData;
    }

    $docUpper = strtoupper($extractedText);
    $docCompact = preg_replace('/[^A-Z0-9]/', '', $docUpper);

    // Normalize student inputs
    $sidUpper = strtoupper(trim($studentId));
    $sidCompact = preg_replace('/[^A-Z0-9]/', '', $sidUpper);
    $lastUpper = strtoupper(trim($lastName));
    $lastCompact = preg_replace('/[^A-Z0-9]/', '', $lastUpper);
    $firstUpper = strtoupper(trim($firstName));
    $firstParts = preg_split('/[\s,\.\-]+/', $firstUpper);
    $courseUpper = strtoupper(trim($course));
    $courseCode = preg_replace('/^(?:MN\-)?(BS|A)?/i', '', $courseUpper);

    // 1. Check Student ID
    $idMatched = false;
    if (strlen($sidCompact) >= 5 && strpos($docCompact, $sidCompact) !== false) {
        $idMatched = true;
    } elseif (!empty($sidUpper) && strpos($docUpper, $sidUpper) !== false) {
        $idMatched = true;
    }

    // 2. Check Last Name
    $lastMatched = false;
    if (strlen($lastUpper) >= 2) {
        if (preg_match('/\b' . preg_quote($lastUpper, '/') . '\b/i', $docUpper)) {
            $lastMatched = true;
        } elseif (strlen($lastCompact) >= 3 && strpos($docCompact, $lastCompact) !== false) {
            $lastMatched = true;
        }
    }

    // 3. Check First Name (full first name or any component like "Louie" or "Michael")
    $firstMatched = false;
    if (strlen($firstUpper) >= 2) {
        if (preg_match('/\b' . preg_quote($firstUpper, '/') . '\b/i', $docUpper)) {
            $firstMatched = true;
        } else {
            foreach ($firstParts as $part) {
                if (strlen($part) >= 3 && (preg_match('/\b' . preg_quote($part, '/') . '\b/i', $docUpper) || strpos($docCompact, $part) !== false)) {
                    $firstMatched = true;
                    break;
                }
            }
        }
    }

    // 4. Check Course
    $courseMatched = false;
    if (!empty($courseUpper)) {
        if (strpos($docUpper, $courseUpper) !== false || strpos($docCompact, $courseUpper) !== false) {
            $courseMatched = true;
        } elseif (!empty($courseCode) && strlen($courseCode) >= 2 && (strpos($docUpper, $courseCode) !== false || strpos($docCompact, $courseCode) !== false)) {
            $courseMatched = true;
        }
    }

    // 5. Official PhilSCA enrollment keywords
    $keywords = ['CERTIFICATE', 'REGISTRATION', 'ENROLLMENT', 'ASSESSMENT', 'REGISTRAR', 'COLLEGE', 'PHILIPPINE', 'ACADEMIC', 'SEMESTER', 'CURRICULUM', 'STUDENT', 'UNITS', 'OFFICIALLY'];
    $keywordHits = 0;
    foreach ($keywords as $kw) {
        if (strpos($docUpper, $kw) !== false) $keywordHits++;
    }
    $isEnrollmentDoc = ($keywordHits >= 2);

    $score = 0;
    $details = [];

    if ($idMatched) {
        $score += 50;
        $details[] = "Student ID ({$studentId}) verified in document.";
    } else {
        $details[] = "Student ID ({$studentId}) not found in document.";
    }

    if ($lastMatched && $firstMatched) {
        $score += 35;
        $details[] = "Student Name ({$firstName} {$lastName}) verified in document.";
    } elseif ($lastMatched) {
        $score += 20;
        $details[] = "Student Surname ({$lastName}) found in document.";
    } else {
        $details[] = "Student Name ({$firstName} {$lastName}) not found in document.";
    }

    if ($courseMatched) {
        $score += 10;
        $details[] = "Course program ({$course}) verified in document.";
    }

    if ($isEnrollmentDoc) {
        $score += 5;
    } else {
        $details[] = "Document appears to be an unofficial or unrelated file.";
    }

    // Pass Criteria:
    // (1) Student ID matches AND (Name matched OR official enrollment doc)
    // OR (2) Full Name (First + Last) matches AND (Course matched OR official enrollment doc)
    $isValid = false;
    if ($idMatched && ($lastMatched || $firstMatched || $isEnrollmentDoc)) {
        $isValid = true;
        $score = max(95, $score);
    } elseif ($lastMatched && $firstMatched && ($courseMatched || $isEnrollmentDoc)) {
        $isValid = true;
        $score = max(90, $score);
    }

    $reason = $isValid ? "Certificate of Registration (COR) verified successfully." : "Pending Organization Manual Review";

    return [
        'is_valid'          => $isValid,
        'score'             => $score,
        'id_matched'        => $idMatched,
        'name_matched'      => ($lastMatched && $firstMatched),
        'course_matched'    => $courseMatched,
        'is_enrollment_doc' => $isEnrollmentDoc,
        'reason'            => $reason,
        'details'           => $details
    ];
}

// 1. Try Gemini Vision if an active API key is set
$apiKey = defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '';
$hasValidGeminiKey = !empty($apiKey) && $apiKey !== 'YOUR_GEMINI_API_KEY_HERE';

if ($hasValidGeminiKey) {
    $mimeType = mime_content_type($file['tmp_name']) ?: ($ext === 'pdf' ? 'application/pdf' : 'image/' . ($ext === 'jpg' ? 'jpeg' : $ext));
    $base64Data = base64_encode($fileData);

    $prompt = <<<PROMPT
Analyze this uploaded student document and compare with:
- Student ID: {$studentId}
- First Name: {$firstName}
- Last Name: {$lastName}
- Course: {$course}

Check if Student ID and/or Name appear in the document.
Respond ONLY with a JSON object:
{"is_valid": true|false, "confidence": "high"|"low", "detected_id": "...", "detected_name": "...", "reason": "..."}
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
                        'success'      => true,
                        'is_valid'     => true,
                        'needs_review' => false,
                        'score'        => 100,
                        'message'      => 'Certificate of Registration (COR) validated successfully.',
                        'details'      => $parsed
                    ]);
                    exit;
                } else {
                    $reason = !empty($parsed['reason']) ? $parsed['reason'] : "The uploaded COR does not match your inputted student ID ({$studentId}) or name.";
                    echo json_encode([
                        'success'      => true,
                        'is_valid'     => false,
                        'needs_review' => true,
                        'score'        => 25,
                        'message'      => $reason,
                        'details'      => $parsed
                    ]);
                    exit;
                }
            }
        }
    } catch (\Throwable $e) {
        error_log('COR AI Validation error: ' . $e->getMessage());
    }
}

// 2. Perform automated text extraction scan
$scan = scanDocumentContent($fileData, $ext, $studentId, $firstName, $lastName, $course);

if ($scan['is_valid'] === true) {
    echo json_encode([
        'success'      => true,
        'is_valid'     => true,
        'needs_review' => false,
        'score'        => $scan['score'],
        'message'      => $scan['reason'],
        'details'      => $scan['details']
    ]);
} else {
    // Document does not match! Flag for manual review & pending status
    echo json_encode([
        'success'      => true,
        'is_valid'     => false,
        'needs_review' => true,
        'score'        => $scan['score'],
        'message'      => $scan['reason'],
        'details'      => $scan['details']
    ]);
}
exit;
