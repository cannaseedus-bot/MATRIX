<?php
/**
 * Brain Mesh API - Record Execution Endpoint
 *
 * POST /brain-mesh/record.php
 * Body: { brain_id, domain, success, latency_ms, task_id?, error_message?, context? }
 *
 * Records execution result and updates brain scores
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 */

header("Content-Type: application/json");
require __DIR__ . '/../guard.php';
require __DIR__ . '/../db.php';
require __DIR__ . '/../auth.php';
require __DIR__ . '/BrainMesh.php';

require_api_key();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['brain_id', 'domain', 'success', 'latency_ms'];
foreach ($required as $field) {
    if (!isset($data[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

$brainMesh = new BrainMesh($pdo);

$result = $brainMesh->recordExecution(
    $data['brain_id'],
    $data['domain'],
    (bool)$data['success'],
    (int)$data['latency_ms'],
    $data['task_id'] ?? null,
    $data['error_message'] ?? null,
    $data['context'] ?? []
);

echo json_encode([
    '@context' => 'asx://brain-mesh/execution/v1',
    'recorded' => true,
    'result' => $result
]);
