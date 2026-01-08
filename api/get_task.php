<?php
header("Content-Type: application/json");
require 'db.php';
require 'auth.php';

require_api_key();

$agent = $_GET['agent'] ?? null;
if (!$agent) {
    echo json_encode(["error" => "No agent name provided"]);
    exit;
}

// Register/update agent
$stmt = $pdo->prepare("INSERT INTO agents (name, last_seen) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE last_seen = NOW()");
$stmt->execute([$agent]);

// Fetch pending task
$stmt = $pdo->prepare(
    "SELECT * FROM tasks
     WHERE status='pending'
       AND (assigned_agent IS NULL OR assigned_agent = '' OR assigned_agent = ?)
     ORDER BY (assigned_agent = ?) DESC, id ASC
     LIMIT 1"
);
$stmt->execute([$agent, $agent]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if ($task) {
    $pdo->prepare("UPDATE tasks SET status='running', assigned_agent=?, updated_at=NOW() WHERE id=?")
        ->execute([$agent, $task['id']]);
    echo json_encode($task);
} else {
    echo json_encode([]);
}
?>
