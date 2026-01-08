<?php
require __DIR__ . '/db.php';
require_api_key();

$payload = json_decode(file_get_contents('php://input'), true);
$command = $payload['command'] ?? null;
$assignedAgent = $payload['assigned_agent'] ?? null;

if (!$command) {
    http_response_code(400);
    echo json_encode(["error" => "No command provided"]);
    exit;
}

$agentId = null;
if ($assignedAgent) {
    $stmt = $pdo->prepare("INSERT INTO agents (name) VALUES (?) ON DUPLICATE KEY UPDATE last_seen = CURRENT_TIMESTAMP");
    $stmt->execute([$assignedAgent]);
    $stmt = $pdo->prepare("SELECT id FROM agents WHERE name = ?");
    $stmt->execute([$assignedAgent]);
    $agent = $stmt->fetch();
    $agentId = $agent ? $agent['id'] : null;
}

$stmt = $pdo->prepare("INSERT INTO tasks (command, assigned_agent) VALUES (?, ?)");
$stmt->execute([$command, $agentId]);

echo json_encode(["status" => "Task added", "task_id" => $pdo->lastInsertId()]);
