<?php
header("Content-Type: application/json");
require 'db.php';

$agent = $_GET['agent'] ?? null;
if (!$agent) {
    echo json_encode(["error" => "No agent name provided"]);
    exit;
}

// Register/update agent
$stmt = $pdo->prepare("INSERT INTO agents (name) VALUES (?) ON DUPLICATE KEY UPDATE last_seen = NOW()");
$stmt->execute([$agent]);

// Fetch pending task
$stmt = $pdo->query("SELECT * FROM tasks WHERE status='pending' ORDER BY id ASC LIMIT 1");
$task = $stmt->fetch(PDO::FETCH_ASSOC);

if ($task) {
    $pdo->prepare("UPDATE tasks SET status='running', assigned_agent=? WHERE id=?")
        ->execute([$agent, $task['id']]);
    echo json_encode($task);
} else {
    echo json_encode([]);
}
?>
