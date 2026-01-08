<?php
header("Content-Type: application/json");
require 'db.php';
require 'auth.php';

require_api_key();

$status = $_GET['status'] ?? null;
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
$limit = max(1, min($limit, 100));

if ($status) {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE status = ? ORDER BY updated_at DESC LIMIT ?");
    $stmt->bindValue(1, $status);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
} else {
    $stmt = $pdo->prepare("SELECT * FROM tasks ORDER BY updated_at DESC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
}

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
