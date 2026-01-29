<?php
/**
 * Brain Mesh API - Brain Selection Endpoint
 *
 * GET /brain-mesh/select.php?domain=<domain> - Select best brain for domain
 *
 * Uses epsilon-greedy algorithm with UCB exploration bonus
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

$domain = $_GET['domain'] ?? 'general';

$brain = $brainMesh->selectBrain($domain);

if ($brain) {
    echo json_encode([
        '@context' => 'asx://brain-mesh/selection/v1',
        'domain' => $domain,
        'selected' => [
            'id' => $brain['id'],
            'label' => $brain['label'],
            'type' => $brain['type'],
            'score' => $brain['score'] ?? 0.5,
            'tier' => $brain['tier'] ?? 'standard',
            'llm_provider' => $brain['llm_provider'],
            'llm_model' => $brain['llm_model']
        ]
    ]);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'No brain available for domain']);
}
