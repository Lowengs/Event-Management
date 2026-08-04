<?php
/**
 * Reset passwords for OSA, Admin, and Organization accounts to 'Naap@2025'
 */
require_once __DIR__ . '/db.php';

$hash = password_hash('Naap@2025', PASSWORD_DEFAULT);

// Reset OSA passwords
$conn->query("UPDATE osa SET PasswordHash = '$hash'");
$conn->query("UPDATE users SET PasswordHash = '$hash' WHERE LOWER(Role) = 'osa'");

// Reset Admin passwords
$conn->query("UPDATE admin SET PasswordHash = '$hash'");
$conn->query("UPDATE users SET PasswordHash = '$hash' WHERE LOWER(Role) = 'admin'");

// Reset Organization passwords
$conn->query("UPDATE organization SET PasswordHash = '$hash'");
$conn->query("UPDATE users SET PasswordHash = '$hash' WHERE LOWER(Role) = 'organization'");

echo json_encode([
    'success' => true,
    'message' => 'Passwords for OSA, Admin, and Organization accounts successfully reset to Naap@2025'
]);
