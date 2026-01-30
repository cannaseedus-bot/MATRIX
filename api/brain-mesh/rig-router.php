<?php
/**
 * RIG Federation Router
 *
 * Routes LLM requests to federated rigs via DNS-based communication.
 * Enables local machines to share Ollama models without HTTP port forwarding.
 *
 * Endpoints:
 *   POST /brain-mesh/rig-router.php?action=register    - Register a rig
 *   POST /brain-mesh/rig-router.php?action=unregister  - Remove a rig
 *   POST /brain-mesh/rig-router.php?action=advertise   - Update models/status
 *   POST /brain-mesh/rig-router.php?action=poll        - Get pending requests
 *   POST /brain-mesh/rig-router.php?action=respond     - Submit response
 *   POST /brain-mesh/rig-router.php?action=request     - Queue inference request
 *   GET  /brain-mesh/rig-router.php?action=list        - List active rigs
 *   GET  /brain-mesh/rig-router.php?action=status      - Get rig status
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 * @version 2.1.0
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, X-Rig-ID, X-Session-ID");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require __DIR__ . '/db.php';

// Load config
$configFile = __DIR__ . '/config.php';
$config = file_exists($configFile) ? require $configFile : [];

// Get action
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Parse JSON input for POST requests
$input = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
}

// Get rig ID from header or input
$rigId = $_SERVER['HTTP_X_RIG_ID'] ?? $input['rigId'] ?? '';
$sessionId = $_SERVER['HTTP_X_SESSION_ID'] ?? $input['sessionId'] ?? '';

/**
 * Ensure rigs tables exist
 */
function ensureRigTables(PDO $pdo): void {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rigs (
            id VARCHAR(64) PRIMARY KEY,
            name VARCHAR(128) NOT NULL,
            models JSON,
            capabilities JSON,
            status ENUM('online', 'offline', 'busy') DEFAULT 'offline',
            last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            score DECIMAL(5,3) DEFAULT 0.500,
            total_requests INT DEFAULT 0,
            successful_requests INT DEFAULT 0,
            avg_latency_ms INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_score (score DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rig_requests (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            rig_id VARCHAR(64) NOT NULL,
            session_id VARCHAR(64),
            model VARCHAR(128) NOT NULL,
            prompt TEXT NOT NULL,
            options JSON,
            response TEXT,
            status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
            latency_ms INT,
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            started_at TIMESTAMP NULL,
            completed_at TIMESTAMP NULL,
            INDEX idx_rig_status (rig_id, status),
            INDEX idx_created (created_at),
            FOREIGN KEY (rig_id) REFERENCES rigs(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// Ensure tables exist
try {
    ensureRigTables($pdo);
} catch (PDOException $e) {
    // Tables might already exist, continue
}

/**
 * Register a new rig
 */
function registerRig(PDO $pdo, array $data): array {
    $rigId = $data['rigId'] ?? '';
    $name = $data['name'] ?? 'unnamed-rig';
    $models = $data['models'] ?? [];
    $capabilities = $data['capabilities'] ?? [];

    if (empty($rigId)) {
        return ['success' => false, 'error' => 'Missing rigId'];
    }

    $stmt = $pdo->prepare("
        INSERT INTO rigs (id, name, models, capabilities, status, last_seen)
        VALUES (?, ?, ?, ?, 'online', NOW())
        ON DUPLICATE KEY UPDATE
            name = VALUES(name),
            models = VALUES(models),
            capabilities = VALUES(capabilities),
            status = 'online',
            last_seen = NOW()
    ");

    $stmt->execute([
        $rigId,
        $name,
        json_encode($models),
        json_encode($capabilities)
    ]);

    return [
        '@context' => 'asx://rig/registered',
        'success' => true,
        'rigId' => $rigId,
        'name' => $name,
        'models' => $models,
        'timestamp' => time()
    ];
}

/**
 * Unregister a rig
 */
function unregisterRig(PDO $pdo, string $rigId): array {
    if (empty($rigId)) {
        return ['success' => false, 'error' => 'Missing rigId'];
    }

    // Mark as offline rather than delete (preserve history)
    $stmt = $pdo->prepare("UPDATE rigs SET status = 'offline' WHERE id = ?");
    $stmt->execute([$rigId]);

    return [
        '@context' => 'asx://rig/unregistered',
        'success' => true,
        'rigId' => $rigId,
        'timestamp' => time()
    ];
}

/**
 * Advertise rig capabilities/models
 */
function advertiseRig(PDO $pdo, array $data): array {
    $rigId = $data['rigId'] ?? '';
    $models = $data['models'] ?? null;
    $capabilities = $data['capabilities'] ?? null;
    $status = $data['status'] ?? 'online';

    if (empty($rigId)) {
        return ['success' => false, 'error' => 'Missing rigId'];
    }

    $updates = ['status = ?', 'last_seen = NOW()'];
    $params = [$status];

    if ($models !== null) {
        $updates[] = 'models = ?';
        $params[] = json_encode($models);
    }

    if ($capabilities !== null) {
        $updates[] = 'capabilities = ?';
        $params[] = json_encode($capabilities);
    }

    $params[] = $rigId;

    $stmt = $pdo->prepare("UPDATE rigs SET " . implode(', ', $updates) . " WHERE id = ?");
    $stmt->execute($params);

    return [
        '@context' => 'asx://rig/advertised',
        'success' => true,
        'rigId' => $rigId,
        'status' => $status,
        'timestamp' => time()
    ];
}

/**
 * Poll for pending requests (called by rig)
 */
function pollRequests(PDO $pdo, string $rigId): array {
    if (empty($rigId)) {
        return ['success' => false, 'error' => 'Missing rigId'];
    }

    // Update last seen
    $stmt = $pdo->prepare("UPDATE rigs SET last_seen = NOW(), status = 'online' WHERE id = ?");
    $stmt->execute([$rigId]);

    // Get pending requests for this rig
    $stmt = $pdo->prepare("
        SELECT id, session_id, model, prompt, options, created_at
        FROM rig_requests
        WHERE rig_id = ? AND status = 'pending'
        ORDER BY created_at ASC
        LIMIT 5
    ");
    $stmt->execute([$rigId]);
    $requests = $stmt->fetchAll();

    // Mark as processing
    if (!empty($requests)) {
        $ids = array_column($requests, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("
            UPDATE rig_requests
            SET status = 'processing', started_at = NOW()
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($ids);
    }

    // Decode JSON options
    foreach ($requests as &$req) {
        $req['options'] = json_decode($req['options'], true) ?? [];
    }

    return [
        '@context' => 'asx://rig/poll',
        'success' => true,
        'rigId' => $rigId,
        'requests' => $requests,
        'count' => count($requests),
        'timestamp' => time()
    ];
}

/**
 * Submit response from rig
 */
function submitResponse(PDO $pdo, array $data): array {
    $rigId = $data['rigId'] ?? '';
    $requestId = $data['requestId'] ?? 0;
    $response = $data['response'] ?? '';
    $success = $data['success'] ?? true;
    $latency = $data['latency'] ?? 0;
    $error = $data['error'] ?? null;

    if (empty($rigId) || empty($requestId)) {
        return ['success' => false, 'error' => 'Missing rigId or requestId'];
    }

    $status = $success ? 'completed' : 'failed';

    $stmt = $pdo->prepare("
        UPDATE rig_requests
        SET status = ?, response = ?, latency_ms = ?, error_message = ?, completed_at = NOW()
        WHERE id = ? AND rig_id = ?
    ");
    $stmt->execute([$status, $response, $latency, $error, $requestId, $rigId]);

    // Update rig stats
    if ($success) {
        $stmt = $pdo->prepare("
            UPDATE rigs SET
                total_requests = total_requests + 1,
                successful_requests = successful_requests + 1,
                avg_latency_ms = (avg_latency_ms * total_requests + ?) / (total_requests + 1),
                score = LEAST(1.0, score + 0.01)
            WHERE id = ?
        ");
        $stmt->execute([$latency, $rigId]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE rigs SET
                total_requests = total_requests + 1,
                score = GREATEST(0.0, score - 0.05)
            WHERE id = ?
        ");
        $stmt->execute([$rigId]);
    }

    return [
        '@context' => 'asx://rig/response',
        'success' => true,
        'rigId' => $rigId,
        'requestId' => $requestId,
        'status' => $status,
        'timestamp' => time()
    ];
}

/**
 * Queue an inference request for a rig
 */
function queueRequest(PDO $pdo, array $data): array {
    $model = $data['model'] ?? '';
    $prompt = $data['prompt'] ?? '';
    $options = $data['options'] ?? [];
    $sessionId = $data['sessionId'] ?? '';
    $preferredRig = $data['rigId'] ?? null;

    if (empty($model) || empty($prompt)) {
        return ['success' => false, 'error' => 'Missing model or prompt'];
    }

    // Find available rig
    $rigId = null;

    if ($preferredRig) {
        // Check if preferred rig is available and has the model
        $stmt = $pdo->prepare("
            SELECT id FROM rigs
            WHERE id = ? AND status = 'online' AND JSON_CONTAINS(models, ?)
        ");
        $stmt->execute([$preferredRig, json_encode($model)]);
        $rig = $stmt->fetch();
        if ($rig) {
            $rigId = $rig['id'];
        }
    }

    if (!$rigId) {
        // Find best available rig with the model (epsilon-greedy selection)
        $explore = (mt_rand(1, 100) <= 10); // 10% exploration

        if ($explore) {
            // Random selection from available rigs
            $stmt = $pdo->prepare("
                SELECT id FROM rigs
                WHERE status = 'online' AND JSON_CONTAINS(models, ?)
                ORDER BY RAND()
                LIMIT 1
            ");
        } else {
            // Select by score
            $stmt = $pdo->prepare("
                SELECT id FROM rigs
                WHERE status = 'online' AND JSON_CONTAINS(models, ?)
                ORDER BY score DESC, avg_latency_ms ASC
                LIMIT 1
            ");
        }
        $stmt->execute([json_encode($model)]);
        $rig = $stmt->fetch();

        if ($rig) {
            $rigId = $rig['id'];
        }
    }

    if (!$rigId) {
        return [
            '@context' => 'asx://rig/error',
            'success' => false,
            'error' => 'No available rig found for model: ' . $model,
            'model' => $model
        ];
    }

    // Queue the request
    $stmt = $pdo->prepare("
        INSERT INTO rig_requests (rig_id, session_id, model, prompt, options, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([$rigId, $sessionId, $model, $prompt, json_encode($options)]);

    $requestId = $pdo->lastInsertId();

    return [
        '@context' => 'asx://rig/queued',
        'success' => true,
        'requestId' => $requestId,
        'rigId' => $rigId,
        'model' => $model,
        'timestamp' => time()
    ];
}

/**
 * Wait for request completion (long-poll)
 */
function waitForResponse(PDO $pdo, int $requestId, int $timeout = 30): array {
    $start = time();

    while (time() - $start < $timeout) {
        $stmt = $pdo->prepare("
            SELECT status, response, latency_ms, error_message
            FROM rig_requests WHERE id = ?
        ");
        $stmt->execute([$requestId]);
        $req = $stmt->fetch();

        if (!$req) {
            return ['success' => false, 'error' => 'Request not found'];
        }

        if ($req['status'] === 'completed') {
            return [
                '@context' => 'asx://rig/result',
                'success' => true,
                'requestId' => $requestId,
                'response' => $req['response'],
                'latency' => $req['latency_ms']
            ];
        }

        if ($req['status'] === 'failed') {
            return [
                '@context' => 'asx://rig/error',
                'success' => false,
                'requestId' => $requestId,
                'error' => $req['error_message'] ?? 'Request failed'
            ];
        }

        usleep(500000); // 500ms
    }

    return [
        '@context' => 'asx://rig/timeout',
        'success' => false,
        'requestId' => $requestId,
        'error' => 'Request timed out'
    ];
}

/**
 * List active rigs
 */
function listRigs(PDO $pdo): array {
    // Mark stale rigs as offline (no heartbeat for 2 minutes)
    $pdo->exec("
        UPDATE rigs SET status = 'offline'
        WHERE status = 'online' AND last_seen < DATE_SUB(NOW(), INTERVAL 2 MINUTE)
    ");

    $stmt = $pdo->query("
        SELECT id, name, models, capabilities, status, score,
               total_requests, successful_requests, avg_latency_ms,
               last_seen, created_at
        FROM rigs
        ORDER BY status DESC, score DESC
    ");
    $rigs = $stmt->fetchAll();

    foreach ($rigs as &$rig) {
        $rig['models'] = json_decode($rig['models'], true) ?? [];
        $rig['capabilities'] = json_decode($rig['capabilities'], true) ?? [];
        $rig['score'] = (float) $rig['score'];
    }

    $online = array_filter($rigs, fn($r) => $r['status'] === 'online');

    return [
        '@context' => 'asx://rig/list',
        'success' => true,
        'rigs' => $rigs,
        'total' => count($rigs),
        'online' => count($online),
        'timestamp' => time()
    ];
}

/**
 * Get specific rig status
 */
function getRigStatus(PDO $pdo, string $rigId): array {
    if (empty($rigId)) {
        return ['success' => false, 'error' => 'Missing rigId'];
    }

    $stmt = $pdo->prepare("
        SELECT id, name, models, capabilities, status, score,
               total_requests, successful_requests, avg_latency_ms,
               last_seen, created_at
        FROM rigs WHERE id = ?
    ");
    $stmt->execute([$rigId]);
    $rig = $stmt->fetch();

    if (!$rig) {
        return ['success' => false, 'error' => 'Rig not found'];
    }

    $rig['models'] = json_decode($rig['models'], true) ?? [];
    $rig['capabilities'] = json_decode($rig['capabilities'], true) ?? [];
    $rig['score'] = (float) $rig['score'];

    // Get pending request count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as pending FROM rig_requests
        WHERE rig_id = ? AND status IN ('pending', 'processing')
    ");
    $stmt->execute([$rigId]);
    $rig['pendingRequests'] = (int) $stmt->fetchColumn();

    return [
        '@context' => 'asx://rig/status',
        'success' => true,
        'rig' => $rig,
        'timestamp' => time()
    ];
}

// Route action
try {
    switch ($action) {
        case 'register':
            $result = registerRig($pdo, $input);
            break;

        case 'unregister':
            $result = unregisterRig($pdo, $rigId ?: ($input['rigId'] ?? ''));
            break;

        case 'advertise':
            $result = advertiseRig($pdo, $input);
            break;

        case 'poll':
            $result = pollRequests($pdo, $rigId ?: ($input['rigId'] ?? ''));
            break;

        case 'respond':
            $result = submitResponse($pdo, $input);
            break;

        case 'request':
            $result = queueRequest($pdo, $input);
            break;

        case 'wait':
            $requestId = $input['requestId'] ?? $_GET['requestId'] ?? 0;
            $timeout = $input['timeout'] ?? $_GET['timeout'] ?? 30;
            $result = waitForResponse($pdo, (int) $requestId, min(60, (int) $timeout));
            break;

        case 'list':
            $result = listRigs($pdo);
            break;

        case 'status':
            $result = getRigStatus($pdo, $rigId ?: ($_GET['rigId'] ?? ''));
            break;

        default:
            http_response_code(400);
            $result = [
                '@context' => 'asx://rig/error',
                'success' => false,
                'error' => 'Invalid action',
                'validActions' => ['register', 'unregister', 'advertise', 'poll', 'respond', 'request', 'wait', 'list', 'status']
            ];
    }
} catch (PDOException $e) {
    http_response_code(500);
    $result = [
        '@context' => 'asx://rig/error',
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage()
    ];
} catch (Exception $e) {
    http_response_code(500);
    $result = [
        '@context' => 'asx://rig/error',
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($result, JSON_PRETTY_PRINT);
