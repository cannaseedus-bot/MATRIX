<?php
/**
 * Brain Mesh API - Error Queue Endpoint
 *
 * GET /brain-mesh/errors.php - List pending errors
 * POST /brain-mesh/errors.php?action=queue
 * Body: { error_type, error_message, brain_id?, task_id?, context? }
 *
 * POST /brain-mesh/errors.php?action=process
 * Body: { limit? }
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 */

header("Content-Type: application/json");
require __DIR__ . '/db.php';
require __DIR__ . '/BrainMesh.php';

$brainMesh = new BrainMesh($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // List pending errors
    $stmt = $pdo->prepare(
        "SELECT * FROM error_queue WHERE status IN ('pending', 'processing') ORDER BY created_at DESC LIMIT 50"
    );
    $stmt->execute();
    $errors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        '@context' => 'asx://brain-mesh/errors/v1',
        'errors' => $errors,
        'count' => count($errors)
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $_GET['action'] ?? null;
$data = json_decode(file_get_contents('php://input'), true) ?? [];

switch ($action) {
    case 'queue':
        if (!isset($data['error_type']) || !isset($data['error_message'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing error_type or error_message']);
            exit;
        }

        $errorId = $brainMesh->queueError(
            $data['error_type'],
            $data['error_message'],
            $data['brain_id'] ?? null,
            $data['task_id'] ?? null,
            $data['context'] ?? []
        );

        echo json_encode([
            '@context' => 'asx://brain-mesh/errors/v1',
            'queued' => true,
            'error_id' => $errorId
        ]);
        break;

    case 'process':
        $limit = $data['limit'] ?? 10;
        $results = $brainMesh->processErrorQueue($limit);

        echo json_encode([
            '@context' => 'asx://brain-mesh/errors/v1',
            'processed' => count($results),
            'results' => $results
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid action',
            'valid_actions' => ['queue', 'process']
        ]);
}
