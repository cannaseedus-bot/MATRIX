<?php
$host = getenv('DB_HOST') ?: 'localhost';
$db = getenv('DB_NAME') ?: 'matrix_broker';
$user = getenv('DB_USER') ?: 'matrix_user';
$pass = getenv('DB_PASS') ?: 'matrix_pass';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Database connection failed"]);
    exit;
}

function require_api_key() {
    $expected = getenv('API_KEY');
    if (!$expected) {
        return;
    }
    $provided = $_SERVER['HTTP_X_API_KEY'] ?? '';
    if (!$provided || !hash_equals($expected, $provided)) {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized"]);
        exit;
    }
}

header('Content-Type: application/json');
