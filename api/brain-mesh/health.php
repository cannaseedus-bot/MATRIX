<?php
/**
 * Brain Mesh API - Health Check Endpoint
 *
 * GET /brain-mesh/health.php - Get orchestrator health status
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 */

header("Content-Type: application/json");
require __DIR__ . '/db.php';
require __DIR__ . '/BrainMesh.php';

// Health endpoint doesn't require auth (for monitoring)

$brainMesh = new BrainMesh($pdo);

$health = $brainMesh->healthCheck();

http_response_code($health['status'] === 'healthy' ? 200 : 503);

echo json_encode([
    '@context' => 'asx://brain-mesh/health/v1',
    'health' => $health
]);
