<?php
/**
 * Brain Mesh API - State Export/Import Endpoint
 *
 * GET /brain-mesh/state.php - Export current state
 * POST /brain-mesh/state.php - Import state
 * POST /brain-mesh/state.php?action=reset - Reset all scores
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

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Export state
    $state = $brainMesh->exportState();
    echo json_encode($state);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $_GET['action'] ?? 'import';

if ($action === 'reset') {
    $brainMesh->resetScores();
    echo json_encode([
        '@context' => 'asx://brain-mesh/state/v1',
        'reset' => true,
        'message' => 'All brain scores have been reset'
    ]);
    exit;
}

// Import state
$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$success = $brainMesh->importState($data);

if ($success) {
    echo json_encode([
        '@context' => 'asx://brain-mesh/state/v1',
        'imported' => true,
        'message' => 'State imported successfully'
    ]);
} else {
    http_response_code(400);
    echo json_encode([
        'error' => 'Failed to import state',
        'imported' => false
    ]);
}
