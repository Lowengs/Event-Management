<?php
require_once "C:/xampp/htdocs/Project/config/db.php";

$sql = "
CREATE TABLE IF NOT EXISTS `assessments` (
  `assessment_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` enum('pretest', 'posttest') NOT NULL,
  `instructions` text DEFAULT NULL,
  `status` enum('draft', 'published', 'closed') DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL, 
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`assessment_id`),
  KEY `event_id` (`event_id`),
  CONSTRAINT `assessments_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `event` (`EventId`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `assessment_questions` (
  `question_id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) NOT NULL,
  `option_d` varchar(255) NOT NULL,
  `correct_answer` enum('A', 'B', 'C', 'D') NOT NULL,
  `points` int(11) DEFAULT 1,
  PRIMARY KEY (`question_id`),
  KEY `assessment_id` (`assessment_id`),
  CONSTRAINT `assessment_questions_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`assessment_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `assessment_answers` (
  `answer_id` int(11) NOT NULL AUTO_INCREMENT,
  `assessment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL, 
  `question_id` int(11) NOT NULL,
  `selected_answer` enum('A', 'B', 'C', 'D') DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT 0,
  `score` int(11) DEFAULT 0,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`answer_id`),
  KEY `assessment_id` (`assessment_id`),
  KEY `student_id` (`student_id`),
  KEY `question_id` (`question_id`),
  CONSTRAINT `assessment_answers_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `assessments` (`assessment_id`) ON DELETE CASCADE,
  CONSTRAINT `assessment_answers_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `user` (`UserId`) ON DELETE CASCADE,
  CONSTRAINT `assessment_answers_ibfk_3` FOREIGN KEY (`question_id`) REFERENCES `assessment_questions` (`question_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

if ($conn->multi_query($sql)) {
    do { if ($res = $conn->store_result()) { $res->free(); } } while ($conn->more_results() && $conn->next_result());
    echo "Schema created.";
} else {
    echo "Error: " . $conn->error;
}
?>