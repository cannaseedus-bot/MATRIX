<?php
header("Content-Type: application/json");
require 'guard.php';
require 'db.php';

$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 20;
$limit = max(1, min($limit, 200));

$stmt = $pdo->prepare("SELECT * FROM tasks ORDER BY id DESC LIMIT ?");
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->execute();
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
