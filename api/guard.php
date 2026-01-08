<?php
require __DIR__ . '/../config.php';

if (!empty($api_key)) {
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    $provided = $headers['x-api-key'] ?? null;
    if ($provided !== $api_key) {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized"]);
        exit;
    }
}
