<?php
/**
 * Organization API: GET Assessments
 * Endpoint: /config/API/endpoints/index.php?action=get_assessments
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../../db.php';

$isDirectApiCall = (defined('IS_API_ENDPOINT') && IS_API_ENDPOINT || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php' || basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__));
if ($isDirectApiCall) {
    header('Content-Type: application/json');
}

if (empty($_SESSION['org_id'])) {
    echo json_encode(['success' => false, 'message' => 'Organization login required']);
if ($isDirectApiCall) exit;
    return;
}

$orgId = (int)$_SESSION['org_id'];

// Self-healing schema check
$conn->query("CREATE TABLE IF NOT EXISTS assessments (
    assessment_id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'pretest',
    test_type VARCHAR(50) DEFAULT 'pretest',
    instructions TEXT,
    status VARCHAR(50) NOT NULL DEFAULT 'draft',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB");
$timeLimitColumn = $conn->query("SHOW COLUMNS FROM assessments LIKE 'time_limit'");
if (!$timeLimitColumn || $timeLimitColumn->num_rows === 0) $conn->query('ALTER TABLE assessments ADD COLUMN time_limit INT NOT NULL DEFAULT 30');

$conn->query("CREATE TABLE IF NOT EXISTS assessment_questions (
    question_id INT AUTO_INCREMENT PRIMARY KEY,
    assessment_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255),
    option_b VARCHAR(255),
    option_c VARCHAR(255),
    option_d VARCHAR(255),
    correct_answer VARCHAR(10) NOT NULL,
    points INT DEFAULT 1
) ENGINE=InnoDB");

// Deduplicate questions
$conn->query("
    DELETE q1 FROM assessment_questions q1
    INNER JOIN assessment_questions q2 
    WHERE q1.question_id > q2.question_id 
      AND q1.assessment_id = q2.assessment_id 
      AND q1.question_text = q2.question_text
");

$events = [];
$evQueryList = $conn->query("SELECT EventId, EventName, EventDateTime FROM event WHERE OrgId=$orgId ORDER BY EventDateTime DESC");
if ($evQueryList) {
    while ($row = $evQueryList->fetch_assoc()) {
        $events[] = $row;
    }
}

$groupedEvents = [];
foreach ($events as $row) {
    $row['pretest'] = null;
    $row['posttest'] = null;
    $groupedEvents[$row['EventId']] = $row;
}

$qT = "
    SELECT a.*,
           (SELECT COUNT(*) FROM assessment_questions aq WHERE aq.assessment_id = a.assessment_id) as q_count
    FROM assessments a
    JOIN event e ON a.event_id = e.EventId
    WHERE e.OrgId = $orgId
";
$resT = $conn->query($qT);
if ($resT) {
    while ($row = $resT->fetch_assoc()) {
        $eid = $row['event_id'];
        $type = strtolower($row['type']);
        if (isset($groupedEvents[$eid]) && ($type === 'pretest' || $type === 'posttest')) {
            $groupedEvents[$eid][$type] = $row;
        }
    }
}

$questionsData = [];
$qQ = "
    SELECT aq.* 
    FROM assessment_questions aq
    JOIN assessments a ON aq.assessment_id = a.assessment_id
    JOIN event e ON a.event_id = e.EventId
    WHERE e.OrgId = $orgId
    ORDER BY aq.question_id ASC
";
$resQ = $conn->query($qQ);
if ($resQ) {
    while ($row = $resQ->fetch_assoc()) {
        $questionsData[$row['assessment_id']][] = $row;
    }
}

echo json_encode([
        'success'        => true,
        'events'         => $events,
        'grouped_events' => array_values($groupedEvents),
        'questions_data' => $questionsData
    ]);
if ($isDirectApiCall) exit;
?>

