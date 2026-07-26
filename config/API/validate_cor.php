<?php
require_once '../gemini.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

if (!isset($_FILES['cor'])) {
    echo json_encode(['success' => false, 'message' => 'No COR file uploaded']);
    exit;
}

$file = $_FILES['cor'];
$firstName = $_POST['first_name'] ?? '';
$lastName = $_POST['last_name'] ?? '';
$middleName = $_POST['middle_name'] ?? '';
$studentId = $_POST['student_id'] ?? '';
$course = $_POST['course'] ?? '';
$yearLevel = $_POST['year_level'] ?? '';
$section = $_POST['section'] ?? '';

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload error']);
    exit;
}

$mimeType = mime_content_type($file['tmp_name']);
$allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];

if (!in_array($mimeType, $allowedMimes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file format. Only JPG, PNG, and PDF are allowed.']);
    exit;
}

$base64Data = base64_encode(file_get_contents($file['tmp_name']));

function norm_text(string $value): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/\s+/', ' ', $value);
    return $value;
}

function norm_id(string $value): string {
    return strtoupper(preg_replace('/[^A-Z0-9]/i', '', trim($value)));
}

function norm_course(string $value): string {
    return strtoupper(preg_replace('/\s+/', '', trim($value)));
}

function norm_section(string $value): string {
    return preg_replace('/\D+/', '', trim($value));
}

function norm_year(string $value): string {
    $value = strtolower(trim($value));
    $map = [
        '1st year' => '1', 'first year' => '1', '1' => '1',
        '2nd year' => '2', 'second year' => '2', '2' => '2',
        '3rd year' => '3', 'third year' => '3', '3' => '3',
        '4th year' => '4', 'fourth year' => '4', '4' => '4',
    ];
    $compact = preg_replace('/\s+/', ' ', $value);
    return $map[$compact] ?? preg_replace('/\D+/', '', $compact);
}

$prompt = <<<PROMPT
You are a validation assistant that reads a Certificate of Registration (COR).
Extract the student details and return ONLY a JSON object with these keys:
{
  "StudentId": "",
  "FirstName": "",
  "MiddleName": "",
  "LastName": "",
  "Course": "",
  "YearLevel": "",
  "Section": "",
  "Confidence": "high|medium|low"
}

Use empty strings if a field is unclear.
PROMPT;

if (empty(GEMINI_API_KEY) || GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE') {
    // If no API key is provided, bypass AI validation for testing purposes
    echo json_encode(['success' => true, 'message' => 'AI Validation bypassed (No API Key).']);
    exit;
}

$response = geminiAnalyzeBase64Image($prompt, $base64Data, $mimeType);

if (!$response || strpos($response, 'API_ERROR:') === 0 || strpos($response, 'CURL_ERROR:') === 0 || strpos($response, 'NO_CONTENT:') === 0 || strpos($response, 'EMPTY_RESPONSE') === 0) {
    echo json_encode(['success' => false, 'message' => 'AI Service Down: ' . $response]);
    exit;
}

$response = trim($response);
$clean = preg_replace('/^```(?:json)?\s*/m', '', $response);
$clean = preg_replace('/```\s*$/m', '', $clean);
$extracted = json_decode(trim($clean), true);

if (!is_array($extracted)) {
    echo json_encode(['success' => false, 'message' => 'AI response could not be parsed.']);
    exit;
}

$mismatches = [];

if (norm_id($studentId) !== '' && norm_id((string)($extracted['StudentId'] ?? '')) !== norm_id($studentId)) {
    $mismatches[] = 'Student ID';
}

$expectedFirst = norm_text($firstName);
$expectedLast = norm_text($lastName);
$expectedMiddle = norm_text($middleName);
$corFirst = norm_text((string)($extracted['FirstName'] ?? ''));
$corLast = norm_text((string)($extracted['LastName'] ?? ''));
$corMiddle = norm_text((string)($extracted['MiddleName'] ?? ''));

if ($expectedFirst !== '' && $corFirst !== '' && $expectedFirst !== $corFirst) {
    $mismatches[] = 'First name';
}
if ($expectedLast !== '' && $corLast !== '' && $expectedLast !== $corLast) {
    $mismatches[] = 'Last name';
}
if ($expectedMiddle !== '' && $corMiddle !== '' && $expectedMiddle !== $corMiddle) {
    $mismatches[] = 'Middle name';
}

if (norm_course($course) !== '' && norm_course((string)($extracted['Course'] ?? '')) !== norm_course($course)) {
    $mismatches[] = 'Course';
}

if (norm_year($yearLevel) !== '' && norm_year((string)($extracted['YearLevel'] ?? '')) !== norm_year($yearLevel)) {
    $mismatches[] = 'Year level';
}

$expectedSection = norm_section($section);
$corSection = norm_section((string)($extracted['Section'] ?? ''));
if ($expectedSection !== '' && $corSection !== '' && $expectedSection !== $corSection) {
    $mismatches[] = 'Section';
}

if (!empty($mismatches)) {
    echo json_encode([
        'success' => false,
        'message' => 'COR mismatch: ' . implode(', ', $mismatches) . ' does not match the uploaded COR.',
    ]);
    exit;
}

echo json_encode(['success' => true]);
?>