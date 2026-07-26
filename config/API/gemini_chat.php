<?php
/**
 * gemini_chat.php — Chat endpoint called by the event detail page Gemini AI box
 * Accepts: POST JSON { "prompt": "..." }
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../gemini.php';

$body   = file_get_contents('php://input');
$data   = json_decode($body, true);
$prompt = trim($data['prompt'] ?? '');

if (!$prompt) {
    echo json_encode(['text' => 'Please provide a question.']);
    exit;
}

$text = geminiAsk($prompt, 512);

if ($text) {
    echo json_encode(['text' => $text]);
} else {
    echo json_encode(['text' => 'Sorry, the AI could not generate a response right now. Please check your Gemini API key in config/gemini_key.php or try again later.']);
}
