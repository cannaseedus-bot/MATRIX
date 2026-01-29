<?php
/**
 * Brain Mesh API - Adaptive Stats Endpoint
 *
 * GET /brain-mesh/stats.php - Get adaptive routing statistics
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 */

header("Content-Type: application/json");
require __DIR__ . '/db.php';
require __DIR__ . '/BrainMesh.php';

$brainMesh = new BrainMesh($pdo);

$stats = $brainMesh->getAdaptiveStats();

echo json_encode([
    '@context' => 'asx://brain-mesh/stats/v1',
    'stats' => $stats
]);
