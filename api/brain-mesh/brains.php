<?php
/**
 * Brain Mesh API - Brains Endpoint
 *
 * GET /brain-mesh/brains.php - List all brains with scores
 * GET /brain-mesh/brains.php?id=<brain_id> - Get specific brain
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

$id = $_GET['id'] ?? null;

if ($id) {
    $brain = $brainMesh->getBrain($id);
    if ($brain) {
        echo json_encode($brain);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Brain not found']);
    }
} else {
    $brains = $brainMesh->getAllBrains();
    echo json_encode([
        '@context' => 'asx://brain-mesh/brains/v1',
        'brains' => $brains,
        'count' => count($brains)
    ]);
}
