<?php
header("Content-Type: application/json");
require 'guard.php';
require 'db.php';
require 'auth.php';

require_api_key();

$data = json_decode(file_get_contents("php://input"), true);
if (!isset($data['task_id']) || !isset($data['result'])) {
    echo json_encode(["error" => "Missing task_id or result"]);
    exit;
}

$status = $data['status'] ?? 'done';
if (!in_array($status, ['done', 'error'], true)) {
    $status = 'done';
}

$stmt = $pdo->prepare("UPDATE tasks SET status=?, result=? WHERE id=?");
$stmt->execute([$status, $data['result'], $data['task_id']]);
echo json_encode(["status" => "Result stored"]);
$stmt = $pdo->prepare("UPDATE tasks SET status=?, result=?, updated_at=NOW() WHERE id=?");
$stmt->execute([$status, $data['result'], $data['task_id']]);
echo json_encode(["status" => "Result stored", "task_id" => $data['task_id']]);
?>
