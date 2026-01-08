<?php
header("Content-Type: application/json");
require 'db.php';

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['task_id']) || !isset($data['result'])) {
    echo json_encode(["error" => "Missing task_id or result"]);
    exit;
}

$stmt = $pdo->prepare("UPDATE tasks SET status='done', result=? WHERE id=?");
$stmt->execute([$data['result'], $data['task_id']]);
echo json_encode(["status" => "Result stored"]);
?>
