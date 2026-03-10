<?php
$secret = 'SIM_PANLA_DEPLOY_2026';
if (($_GET['secret'] ?? '') !== $secret) {
    http_response_code(403); die('Forbidden');
}
$pngUrl = "https://raw.githubusercontent.com/varhan1/sim_spanla_backend/main/public/logo.png";
$icoUrl = "https://raw.githubusercontent.com/varhan1/sim_spanla_backend/main/public/logo.ico";

$contentPng = file_get_contents($pngUrl);
if ($contentPng) {
    file_put_contents(__DIR__ . '/logo.png', $contentPng);
    echo "logo.png downloaded successfully.<br>";
} else {
    echo "logo.png download failed.<br>";
}

$contentIco = file_get_contents($icoUrl);
if ($contentIco) {
    file_put_contents(__DIR__ . '/logo.ico', $contentIco);
    echo "logo.ico downloaded successfully.<br>";
} else {
    echo "logo.ico download failed.<br>";
}
echo "DONE.";
