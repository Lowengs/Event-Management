<?php
session_start();
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../audit.php';

$id = $_SESSION['student_id'] ?? null;

if ($id) {
    logAudit($conn, 'Student Logout', 'student', (int)$id, 'success', ['email' => $_SESSION['student_email'] ?? '']);
}

session_unset();
session_destroy();
setcookie('student_remember', '', time() - 3600, '/');

header('Location: ../../app/index.php');
exit;
