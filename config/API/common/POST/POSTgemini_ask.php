<?php
/**
 * Common API: Gemini AI Ask
 * Endpoint: /config/API/endpoints/index.php?action=POSTgemini_ask
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../gemini.php';

header('Content-Type: application/json');

$prompt = $_POST['prompt'] ?? $_POST['question'] ?? '';
if (!$prompt) {
    $input = json_decode(file_get_contents('php://input'), true);
    $prompt = $input['prompt'] ?? $input['question'] ?? '';
}

if (!$prompt) {
    echo json_encode(['success' => false, 'message' => 'Prompt is required']);
    exit;
}

$response = geminiAsk($prompt);
if ($response === null) {
    echo json_encode(['success' => false, 'message' => 'Failed to reach Gemini AI service']);
    exit;
}

echo json_encode(['success' => true, 'answer' => $response, 'response' => $response]);
?>
