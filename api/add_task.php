<?php
header("Content-Type: application/json");
require 'db.php';

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['command'])) {
    echo json_encode(["error" => "No command provided"]);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO tasks (command) VALUES (?)");
$stmt->execute([$data['command']]);
echo json_encode(["status" => "Task added"]);
?>
