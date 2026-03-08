<?php
// Simple deployment webhook for cPanel shared hosting
// Keep this file SECRET - do not expose the secret key

$secret = 'SIM_PANLA_DEPLOY_2026'; // Ganti ini dengan secret kamu

// Verify secret
$provided = $_GET['secret'] ?? '';
if (!hash_equals($secret, $provided)) {
    http_response_code(403);
    die('Forbidden');
}

$output = [];
$repoPath = dirname(__DIR__);

// Pull latest code
exec("cd {$repoPath} && git pull origin main 2>&1", $output);

// Run artisan commands
exec("cd {$repoPath} && /usr/local/bin/php artisan migrate --force 2>&1", $output);
exec("cd {$repoPath} && /usr/local/bin/php artisan config:cache 2>&1", $output);
exec("cd {$repoPath} && /usr/local/bin/php artisan route:cache 2>&1", $output);
exec("cd {$repoPath} && /usr/local/bin/php artisan view:cache 2>&1", $output);

echo '<pre>' . implode("\n", $output) . '</pre>';
