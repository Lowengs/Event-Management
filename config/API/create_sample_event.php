<?php
require '../db.php';
$res = $conn->query("SELECT OrgId FROM organization WHERE OrgName LIKE '%ELITECH%'");
$org = $res->fetch_assoc();
if (!$org) {
    echo "ELITECH org not found\n";
    exit;
}
$orgId = $org['OrgId'];

$eventName = "Sample ELITECH Event with Tests";
$eventDesc = "This is a sample event to test pre-test and post-test functionalities.";
$eventDate = date('Y-m-d H:i:s', strtotime('+1 day'));
$eventLoc = "Main Auditorium";

$stmt = $conn->prepare("INSERT INTO event (OrgId, EventName, EventDescription, EventDateTime, EventLocation, EventStatus, EventMode) VALUES (?, ?, ?, ?, ?, 'upcoming', 'On-site')");
$stmt->bind_param("issss", $orgId, $eventName, $eventDesc, $eventDate, $eventLoc);
$stmt->execute();
$eventId = $stmt->insert_id;

echo "Created Event ID: $eventId\n";

// Now create Pre-test assessment
$stmt = $conn->prepare("INSERT INTO assessments (event_id, title, type, instructions, status, created_by) VALUES (?, 'Pre-Test', 'pretest', 'Please complete this pre-test before the event begins.', 'published', ?)");
$stmt->bind_param("ii", $eventId, $orgId);
$stmt->execute();
$preTestId = $stmt->insert_id;

$preTestQs = [
    [
        'question' => 'What is the primary goal of this event?',
        'options' => ['A' => 'Learning new skills', 'B' => 'Networking', 'C' => 'Both', 'D' => 'Neither'],
        'correct_answer' => 'C'
    ],
    [
        'question' => 'Are you familiar with the topics to be discussed?',
        'options' => ['A' => 'Yes, very familiar', 'B' => 'Somewhat familiar', 'C' => 'Not familiar at all', 'D' => 'Not sure'],
        'correct_answer' => 'B'
    ]
];

foreach ($preTestQs as $q) {
    $stmt = $conn->prepare("INSERT INTO assessment_questions (assessment_id, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $preTestId, $q['question'], $q['options']['A'], $q['options']['B'], $q['options']['C'], $q['options']['D'], $q['correct_answer']);
    $stmt->execute();
}

// Now create Post-test assessment
$stmt = $conn->prepare("INSERT INTO assessments (event_id, title, type, instructions, status, created_by) VALUES (?, 'Post-Test', 'posttest', 'Please complete this post-test after the event has concluded.', 'published', ?)");
$stmt->bind_param("ii", $eventId, $orgId);
$stmt->execute();
$postTestId = $stmt->insert_id;

$postTestQs = [
    [
        'question' => 'Did you find this event helpful?',
        'options' => ['A' => 'Extremely helpful', 'B' => 'Somewhat helpful', 'C' => 'Not very helpful', 'D' => 'Not helpful at all'],
        'correct_answer' => 'A'
    ],
    [
        'question' => 'Which topic was most interesting?',
        'options' => ['A' => 'Topic 1', 'B' => 'Topic 2', 'C' => 'Topic 3', 'D' => 'None of the above'],
        'correct_answer' => 'A'
    ]
];

foreach ($postTestQs as $q) {
    $stmt = $conn->prepare("INSERT INTO assessment_questions (assessment_id, question_text, option_a, option_b, option_c, option_d, correct_answer) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssss", $postTestId, $q['question'], $q['options']['A'], $q['options']['B'], $q['options']['C'], $q['options']['D'], $q['correct_answer']);
    $stmt->execute();
}

echo "Created Pre and Post tests for Event ID: $eventId\n";
