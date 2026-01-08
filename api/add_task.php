<?php
header("Content-Type: application/json");
require 'guard.php';
require 'db.php';
require 'auth.php';

require_api_key();

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['command'])) {
    echo json_encode(["error" => "No command provided"]);
    exit;
}

$assignedAgent = $data['assigned_agent'] ?? null;
$stmt = $pdo->prepare("INSERT INTO tasks (command, assigned_agent) VALUES (?, ?)");
$stmt->execute([$data['command'], $assignedAgent]);
echo json_encode(["status" => "Task added"]);
$stmt = $pdo->prepare("INSERT INTO tasks (command, assigned_agent, status) VALUES (?, ?, 'pending')");
$stmt->execute([$data['command'], $assignedAgent]);
echo json_encode(["status" => "Task added", "task_id" => $pdo->lastInsertId()]);
?>
