<?php
/**
 * gemini.php — Google Gemini AI helper
 * Set your API key in config/gemini_key.php (not committed to VCS)
 */

if (!defined('GEMINI_API_KEY')) {
    $configuredKey = getenv('GEMINI_API_KEY') ?: '';
    if ($configuredKey === '' && file_exists(__DIR__ . '/gemini_key.php')) {
        // Older key files define the constant themselves. Load it first, then
        // only define a fallback if it is still absent.
        $configuredKey = require __DIR__ . '/gemini_key.php';
    }
    if (!defined('GEMINI_API_KEY')) {
        define('GEMINI_API_KEY', is_string($configuredKey) && $configuredKey !== '' ? $configuredKey : 'YOUR_GEMINI_API_KEY_HERE');
    }
}

if (!defined('GEMINI_ENDPOINT')) {
    define('GEMINI_ENDPOINT',
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . GEMINI_API_KEY
    );
}

/**
 * Call Gemini and return the text response.
 * @param string $prompt
 * @param int    $maxTokens
 * @return string|null
 */
function geminiAsk(string $prompt, int $maxTokens = 1024): ?string {
    if (!defined('GEMINI_API_KEY') || empty(GEMINI_API_KEY) || GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE') {
        return null;
    }

    $payload = json_encode([
        'contents' => [['parts' => [['text' => $prompt]]]],
        'generationConfig' => [
            'maxOutputTokens' => $maxTokens,
            'temperature'     => 0.7,
        ]
    ]);

    $ch = curl_init(GEMINI_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false, // XAMPP local dev
    ]);

    $resp = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err || !$resp) return null;

    $data = json_decode($resp, true);
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
}

/**
 * Call Gemini with text and a base64 encoded file (image or pdf).
 */
function geminiAnalyzeBase64Image(string $prompt, string $base64Data, string $mimeType, int $maxTokens = 1024): ?string {
    $payload = json_encode([
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt],
                    [
                        'inlineData' => [
                            'mimeType' => $mimeType,
                            'data' => $base64Data
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'maxOutputTokens' => $maxTokens,
            'temperature'     => 0.2, // lower temperature for validation
        ]
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

    if ($err) return "CURL_ERROR: $err";
    if (!$resp) return "EMPTY_RESPONSE";

    $data = json_decode($resp, true);
    if (isset($data['error'])) {
        return "API_ERROR: " . ($data['error']['message'] ?? json_encode($data['error']));
    }
    
    return $data['candidates'][0]['content']['parts'][0]['text'] ?? "NO_CONTENT: " . $resp;
}

/**
 * Generate multiple-choice quiz questions for an event using Gemini.
 * Returns an array of ['q' => '...', 'opts' => [...], 'answer' => '...']
 */
function generateQuizQuestions(string $eventName, string $eventDetails = '', int $count = 10): array {
    $context = $eventDetails ? "Event: $eventName\nDetails: $eventDetails" : "Event: $eventName";

    $prompt = <<<PROMPT
You are an educational quiz generator for a student organization event.
Generate exactly {$count} multiple-choice questions based on this event context:

{$context}

Rules:
- Each question must have exactly 4 answer options (A, B, C, D)
- Mark the correct answer
- Questions should test knowledge relevant to the event topic
- Mix difficulty levels (easy, medium, hard)

Respond ONLY with valid JSON in this exact format (no markdown, no explanation):
[
  {
    "q": "Question text here?",
    "opts": ["Option A", "Option B", "Option C", "Option D"],
    "answer": "Option A"
  }
]
PROMPT;

    $raw = geminiAsk($prompt, 2048);
    if (!$raw) return fallbackQuestions($eventName, $count);

    // Strip markdown code fences if present
    $raw = preg_replace('/^```(?:json)?\s*/m', '', trim($raw));
    $raw = preg_replace('/```\s*$/m', '', $raw);

    $parsed = json_decode(trim($raw), true);
    if (!is_array($parsed) || empty($parsed)) return fallbackQuestions($eventName, $count);

    return array_slice($parsed, 0, $count);
}

/**
 * Generate AI analysis feedback based on quiz result.
 */
function generateAIFeedback(string $eventName, int $score, int $total, array $wrongTopics = [], string $type = 'pre'): string {
    $pct = $total > 0 ? round(($score / $total) * 100) : 0;
    $testType = $type === 'pre' ? 'pre-test' : 'post-test';
    $wrongs = !empty($wrongTopics) ? implode(', ', $wrongTopics) : 'various topics';

    $prompt = <<<PROMPT
A student completed a {$testType} for the event "{$eventName}".
Score: {$score}/{$total} ({$pct}%)
Topics they got wrong: {$wrongs}

Write a brief, encouraging 2-3 sentence AI analysis and suggestion.
Be specific to the event topic. Keep it positive and actionable.
Do NOT use bullet points or markdown. Just plain text.
PROMPT;

    $feedback = geminiAsk($prompt, 256);
    if (!$feedback) {
        return $pct >= 70
            ? "Great performance! You demonstrated strong knowledge in this area. Keep building on this foundation during the event."
            : "This assessment gives you a baseline for growth. Focus on the areas covered during the event and don't hesitate to ask questions.";
    }
    return trim($feedback);
}

/**
 * Generate a comparison-focused AI insight using both pre-test and post-test scores.
 */
function generateComparisonInsight(string $eventName, ?int $preScore, ?int $postScore, int $total): string {
    $total = max(1, $total);
    $preScore = max(0, (int)($preScore ?? 0));
    $postScore = max(0, (int)($postScore ?? 0));
    $prePct = round(($preScore / $total) * 100);
    $postPct = round(($postScore / $total) * 100);
    $delta = $postScore - $preScore;
    $trend = $delta > 0 ? 'improved' : ($delta < 0 ? 'declined' : 'stayed the same');

    $prompt = <<<PROMPT
A student completed a pre-test and a post-test for the event "{$eventName}".
Pre-test: {$preScore}/{$total} ({$prePct}%)
Post-test: {$postScore}/{$total} ({$postPct}%)
Change: {$delta} points and the result has {$trend}.

Write a brief, encouraging 2-3 sentence AI insight that explains what the student is lacking and what they should improve next.
Focus on likely learning gaps such as understanding, recall, consistency, confidence, and attention to detail.
Do NOT use bullet points or markdown. Just plain text.
PROMPT;

    $feedback = geminiAsk($prompt, 256);
    if ($feedback) {
        return trim($feedback);
    }

    if ($delta > 0) {
        return "You improved from the pre-test, which means your review is working. Keep strengthening the topics you still miss and focus on consistency, recall, and careful reading.";
    }

    if ($delta < 0) {
        return "Your post-test score dropped, so the main gaps are likely retention, confidence, and careful reading. Review the missed topics and practice applying the concepts again.";
    }

    return "Your scores stayed about the same, so you likely need more practice with core concepts and confidence under test conditions. Review the topic areas again and focus on accuracy.";
}

/**
 * Fallback static questions if AI fails.
 */
function fallbackQuestions(string $eventName, int $count = 10): array {
    return array_slice([
        ['q' => "What is the main purpose of the event \"{$eventName}\"?", 'opts' => ['Knowledge sharing','Entertainment','Fundraising','Competition'], 'answer' => 'Knowledge sharing'],
        ['q' => 'Which skill is most important for a student leader?', 'opts' => ['Communication','Speed reading','Memorization','Multi-tasking'], 'answer' => 'Communication'],
        ['q' => 'What does teamwork primarily require?', 'opts' => ['Collaboration','Individual effort','Competition','Silence'], 'answer' => 'Collaboration'],
        ['q' => 'What is the primary benefit of student organizations?', 'opts' => ['Peer networking','Free meals','Extra credits','Avoiding classes'], 'answer' => 'Peer networking'],
        ['q' => 'How should you handle disagreements in a team?', 'opts' => ['Open dialogue','Ignoring the issue','Escalating immediately','Walking away'], 'answer' => 'Open dialogue'],
        ['q' => 'What is a key element of effective event planning?', 'opts' => ['Clear objectives','Last-minute preparation','Solo decision-making','Skipping budgeting'], 'answer' => 'Clear objectives'],
        ['q' => 'Which of the following promotes inclusive participation?', 'opts' => ['Welcoming all voices','Excluding quiet members','Favoring senior students','Limiting discussion time'], 'answer' => 'Welcoming all voices'],
        ['q' => 'What best describes a mentor-mentee relationship?', 'opts' => ['Guidance and support','Strict supervision','Financial exchange','Social media following'], 'answer' => 'Guidance and support'],
        ['q' => 'Why is attendance tracking important at events?', 'opts' => ['Accountability and data','Punishment','Decoration','Registration fees'], 'answer' => 'Accountability and data'],
        ['q' => 'What should follow every major event?', 'opts' => ['Evaluation and reporting','Immediate next event','Long break','Social media posts only'], 'answer' => 'Evaluation and reporting'],
    ], 0, $count);
}
