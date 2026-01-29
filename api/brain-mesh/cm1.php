<?php
/**
 * Brain Mesh API - CM-1 Control Endpoint
 *
 * POST /brain-mesh/cm1.php?action=phase
 * Body: { from, to, context? }
 *
 * POST /brain-mesh/cm1.php?action=push_scope
 * Body: { scope_id }
 *
 * POST /brain-mesh/cm1.php?action=pop_scope
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 */

header("Content-Type: application/json");
require __DIR__ . '/db.php';
require __DIR__ . '/BrainMesh.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $_GET['action'] ?? null;
$data = json_decode(file_get_contents('php://input'), true) ?? [];

$brainMesh = new BrainMesh($pdo);

switch ($action) {
    case 'phase':
        if (!isset($data['to'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing "to" phase']);
            exit;
        }
        $brainMesh->onPhaseTransition(
            $data['from'] ?? 'null',
            $data['to'],
            $data['context'] ?? []
        );
        echo json_encode([
            '@context' => 'asx://brain-mesh/cm1/v1',
            'action' => 'phase_transition',
            'phase' => $data['to']
        ]);
        break;

    case 'push_scope':
        if (!isset($data['scope_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing scope_id']);
            exit;
        }
        $depth = $brainMesh->pushScope($data['scope_id']);
        echo json_encode([
            '@context' => 'asx://brain-mesh/cm1/v1',
            'action' => 'push_scope',
            'scope_id' => $data['scope_id'],
            'depth' => $depth
        ]);
        break;

    case 'pop_scope':
        $scopeId = $brainMesh->popScope();
        echo json_encode([
            '@context' => 'asx://brain-mesh/cm1/v1',
            'action' => 'pop_scope',
            'popped' => $scopeId,
            'depth' => $brainMesh->getScopeDepth()
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid action',
            'valid_actions' => ['phase', 'push_scope', 'pop_scope']
        ]);
}
