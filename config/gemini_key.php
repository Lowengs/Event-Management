<?php
/**
 * Gemini API Key Configuration
 */
$geminiApiKey = getenv('GEMINI_API_KEY') ?: '';
if (!defined('GEMINI_API_KEY')) {
    define('GEMINI_API_KEY', $geminiApiKey);
}
