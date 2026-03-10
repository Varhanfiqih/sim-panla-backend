<?php
$secret = 'SIM_PANLA_DEPLOY_2026'; // Ganti ini dengan secret kamu
if (($_GET['secret'] ?? '') !== $secret) {
    http_response_code(403);
    die('Forbidden');
}

$logPath = dirname(__DIR__) . '/storage/logs/laravel.log';
if (!file_exists($logPath)) {
    die('No log file at ' . $logPath);
}

echo "<pre>";
$lines = file($logPath);
$lastLines = array_slice($lines, -100);
echo htmlspecialchars(implode("", $lastLines));
echo "</pre>";
