<?php
/**
 * Brain Mesh API - Leaderboard Endpoint
 *
 * GET /brain-mesh/leaderboard.php - Get brain leaderboard
 * GET /brain-mesh/leaderboard.php?domain=<domain> - Leaderboard for specific domain
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 */

header("Content-Type: application/json");
require __DIR__ . '/../guard.php';
require __DIR__ . '/../db.php';
require __DIR__ . '/../auth.php';
require __DIR__ . '/BrainMesh.php';

require_api_key();

$brainMesh = new BrainMesh($pdo);

$domain = $_GET['domain'] ?? null;

$leaderboard = $brainMesh->getLeaderboard($domain);

echo json_encode([
    '@context' => 'asx://brain-mesh/leaderboard/v1',
    'domain' => $domain ?? 'all',
    'leaderboard' => $leaderboard,
    'count' => count($leaderboard)
]);
