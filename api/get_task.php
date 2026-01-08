<?php
require __DIR__ . '/db.php';
require_api_key();

$agentName = $_GET['agent'] ?? null;
if (!$agentName) {
    http_response_code(400);
    echo json_encode(["error" => "Missing agent name"]);
    exit;
}

$pdo->beginTransaction();

$stmt = $pdo->prepare("INSERT INTO agents (name) VALUES (?) ON DUPLICATE KEY UPDATE last_seen = CURRENT_TIMESTAMP");
$stmt->execute([$agentName]);

$stmt = $pdo->prepare("SELECT id FROM agents WHERE name = ?");
$stmt->execute([$agentName]);
$agent = $stmt->fetch();
$agentId = $agent['id'];

$stmt = $pdo->prepare(
    "SELECT id, command FROM tasks
     WHERE status = 'pending' AND (assigned_agent IS NULL OR assigned_agent = ?)
     ORDER BY created_at ASC
     LIMIT 1
     FOR UPDATE"
);
$stmt->execute([$agentId]);
$task = $stmt->fetch();

if ($task) {
    $update = $pdo->prepare("UPDATE tasks SET status = 'running', assigned_agent = ? WHERE id = ?");
    $update->execute([$agentId, $task['id']]);
    $pdo->commit();
    echo json_encode(["id" => $task['id'], "command" => $task['command']]);
    exit;
}

$pdo->commit();
echo json_encode([]);
