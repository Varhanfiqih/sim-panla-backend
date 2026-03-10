<?php
$secret = 'SIM_PANLA_DEPLOY_2026';
if (($_GET['secret'] ?? '') !== $secret) {
    http_response_code(403); die('Forbidden');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $target = __DIR__ . '/logo.png';
    if(move_uploaded_file($_FILES['file']['tmp_name'], $target)) {
        echo "UPLOAD SUCCESS: logo.png";
    } else {
        echo "UPLOAD FAILED.";
    }
    exit;
}
echo "Use POST with 'file' field.";
