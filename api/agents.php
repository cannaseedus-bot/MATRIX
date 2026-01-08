<?php
require __DIR__ . '/db.php';
require_api_key();

$windowSeconds = intval($_GET['window'] ?? 300);

$stmt = $pdo->prepare(
    "SELECT id, name, last_seen
     FROM agents
     WHERE last_seen >= (NOW() - INTERVAL ? SECOND)
     ORDER BY last_seen DESC"
);
$stmt->execute([$windowSeconds]);
$agents = $stmt->fetchAll();

echo json_encode(["agents" => $agents]);
