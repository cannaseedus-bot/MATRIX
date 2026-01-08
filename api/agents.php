<?php
header("Content-Type: application/json");
require 'guard.php';
require 'db.php';
require 'auth.php';

require_api_key();

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
$limit = max(1, min($limit, 100));
$stmt = $pdo->prepare("SELECT * FROM agents ORDER BY last_seen DESC LIMIT ?");
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->execute();
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
