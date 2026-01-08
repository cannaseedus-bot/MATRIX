<?php
header("Content-Type: application/json");
require 'db.php';

$stmt = $pdo->query("SELECT * FROM agents ORDER BY last_seen DESC");
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
