<?php
require __DIR__ . '/db.php';
require_api_key();

$payload = json_decode(file_get_contents('php://input'), true);
$taskId = $payload['task_id'] ?? null;
$result = $payload['result'] ?? null;
$status = $payload['status'] ?? 'done';

if (!$taskId) {
    http_response_code(400);
    echo json_encode(["error" => "Missing task_id"]);
    exit;
}

$stmt = $pdo->prepare("UPDATE tasks SET status = ?, result = ? WHERE id = ?");
$stmt->execute([$status, $result, $taskId]);

echo json_encode(["status" => "Result stored"]);
