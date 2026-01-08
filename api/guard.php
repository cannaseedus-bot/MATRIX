<?php
require __DIR__ . '/../config.php';

function request_headers_lowercase(): array
{
    if (function_exists('getallheaders')) {
        return array_change_key_case(getallheaders(), CASE_LOWER);
    }

    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (strncmp($key, 'HTTP_', 5) === 0) {
            $name = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$name] = $value;
        }
    }

    return $headers;
}

if (!empty($api_key)) {
    $headers = request_headers_lowercase();
    $provided = $headers['x-api-key'] ?? null;
    if ($provided !== $api_key) {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized"]);
        exit;
    }
}
