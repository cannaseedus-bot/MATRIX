<?php
require __DIR__ . '/../config.php';

function _header_value(array $headers, string $name): ?string
{
    foreach ($headers as $key => $value) {
        if (strcasecmp($key, $name) === 0) {
            return $value;
        }
    }
    return null;
}

function require_api_key(): void
{
    global $api_key;
    if (!$api_key) {
        return;
    }

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $provided = _header_value($headers, 'X-API-KEY');
    if (!$provided || !hash_equals($api_key, $provided)) {
        http_response_code(401);
        echo json_encode(["error" => "Invalid API key"]);
        exit;
    }
}
