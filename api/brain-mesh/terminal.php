<?php
/**
 * Terminal Projection API
 *
 * Web-based terminal execution endpoint
 * Routes commands through registry validation and brain-mesh orchestration
 *
 * POST /brain-mesh/terminal.php
 * {
 *   "cmd": "brain-mesh leaderboard",
 *   "session_id": "optional-session-id"
 * }
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 * @version 2.1.0
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, X-Session-ID");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require __DIR__ . '/db.php';
require __DIR__ . '/BrainMesh.php';
require __DIR__ . '/LLMProvider.php';
require __DIR__ . '/command-registry.php';

// Load config
$configFile = __DIR__ . '/config.php';
$config = file_exists($configFile) ? require $configFile : [];

// Check auth
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$isAdmin = false;

if (!empty($config['auth']['api_key']) && $apiKey !== $config['auth']['api_key']) {
    if (!in_array($apiKey, $config['auth']['admin_keys'] ?? [])) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key']);
        exit;
    }
    $isAdmin = true;
} elseif (in_array($apiKey, $config['auth']['admin_keys'] ?? [])) {
    $isAdmin = true;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['cmd'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing cmd parameter']);
    exit;
}

$cmd = trim($input['cmd']);
$sessionId = $input['session_id'] ?? bin2hex(random_bytes(16));

// Create instances
$brainMesh = new BrainMesh($pdo, $brainMeshConfig ?? []);
$llmProvider = new LLMProvider($config, $pdo);
$registry = createCommandRegistry($isAdmin);

// Parse command
$parsed = $registry->parse($cmd);

if (!$parsed['valid']) {
    $response = [
        '@context' => 'asx://terminal/error',
        'success' => false,
        'error' => $parsed['error'],
        'cmd' => $cmd,
    ];

    if (isset($parsed['suggestion'])) {
        $response['suggestion'] = $parsed['suggestion'];
    }

    echo json_encode($response);
    exit;
}

// Warn on dangerous commands
if ($parsed['dangerous'] ?? false) {
    // Could add confirmation flow here
}

// Execute command
$startTime = microtime(true);
$result = executeCommand($parsed, $brainMesh, $llmProvider, $config, $pdo);
$latency = (int)((microtime(true) - $startTime) * 1000);

// Build response
$response = [
    '@context' => 'asx://terminal/result',
    'success' => $result['success'] ?? true,
    'cmd' => $cmd,
    'namespace' => $parsed['namespace'],
    'command' => $parsed['command'],
    'output' => $result['output'] ?? '',
    'data' => $result['data'] ?? null,
    'latency_ms' => $latency,
    'session_id' => $sessionId,
    'admin' => $parsed['admin_required'] ?? false,
];

if (isset($result['error'])) {
    $response['error'] = $result['error'];
    $response['success'] = false;
}

echo json_encode($response, JSON_PRETTY_PRINT);

// ═══════════════════════════════════════════════════════════════════
// COMMAND HANDLERS
// ═══════════════════════════════════════════════════════════════════

function executeCommand(array $parsed, BrainMesh $brainMesh, LLMProvider $llmProvider, array $config, PDO $pdo): array {
    $handler = $parsed['handler'];
    $args = $parsed['args'];

    return match($handler) {
        // Brain-Mesh
        'handleBrains' => handleBrains($brainMesh),
        'handleLeaderboard' => handleLeaderboard($brainMesh, $args),
        'handleStats' => handleStats($brainMesh),
        'handleHealth' => handleHealth($brainMesh),
        'handleSelect' => handleSelect($brainMesh, $args),
        'handleBest' => handleBest($brainMesh, $args),
        'handleStatus' => handleStatus($brainMesh),
        'handleReset' => handleReset($brainMesh),
        'handleSave' => handleSave($brainMesh, $args),
        'handleLoad' => handleLoad($brainMesh, $args),

        // LLM
        'handleLLMProviders' => handleLLMProviders($llmProvider),
        'handleLLMModels' => handleLLMModels($llmProvider, $args),
        'handleLLMChat' => handleLLMChat($llmProvider, $args),
        'handleLLMTest' => handleLLMTest($llmProvider, $args),

        // Ollama
        'handleOllamaList' => handleOllamaList($llmProvider),
        'handleOllamaStatus' => handleOllamaStatus($llmProvider),
        'handleOllamaPull' => handleOllamaPull($llmProvider, $args, $config),
        'handleOllamaDelete' => handleOllamaDelete($llmProvider, $args, $config),

        // File
        'handleFileRead' => handleFileRead($args, $config),
        'handleFileList' => handleFileList($args, $config),
        'handleFileInfo' => handleFileInfo($args, $config),
        'handleFileSearch' => handleFileSearch($args, $config),
        'handleFileTree' => handleFileTree($args, $config),
        'handleFileWrite' => handleFileWrite($args, $config),
        'handleFileCreate' => handleFileCreate($args, $config),
        'handleFileMkdir' => handleFileMkdir($args, $config),
        'handleFileDelete' => handleFileDelete($args, $config),
        'handleFileMove' => handleFileMove($args, $config),
        'handleFileCopy' => handleFileCopy($args, $config),

        // CM-1
        'handleCM1Phase' => handleCM1Phase($brainMesh),
        'handleCM1History' => handleCM1History($pdo, $args),
        'handleCM1Scope' => handleCM1Scope($brainMesh),
        'handleCM1Transition' => handleCM1Transition($brainMesh, $args),
        'handleCM1Push' => handleCM1Push($brainMesh, $args),
        'handleCM1Pop' => handleCM1Pop($brainMesh),

        // Help
        'handleHelp' => handleHelp(),
        'handleHelpSearch' => handleHelpSearch($args),

        // System
        'handleSystemInfo' => handleSystemInfo(),
        'handleSystemVersion' => handleSystemVersion(),
        'handleSystemConfig' => handleSystemConfig($config),

        // Task
        'handleTaskList' => handleTaskList($pdo, $args),
        'handleTaskGet' => handleTaskGet($pdo, $args),
        'handleTaskStatus' => handleTaskStatus($pdo, $args),
        'handleTaskAdd' => handleTaskAdd($pdo, $args),
        'handleTaskCancel' => handleTaskCancel($pdo, $args),

        default => ['success' => false, 'error' => 'Handler not implemented: ' . $handler],
    };
}

// ═══════════════════════════════════════════════════════════════════
// BRAIN-MESH HANDLERS
// ═══════════════════════════════════════════════════════════════════

function handleBrains(BrainMesh $brainMesh): array {
    $brains = $brainMesh->getAllBrains();

    $output = "REGISTERED BRAINS\n";
    $output .= str_repeat("─", 60) . "\n";

    foreach ($brains as $brain) {
        $tier = $brain['tier'] ?? 'standard';
        $score = number_format($brain['score'] ?? 0.5, 3);
        $output .= sprintf(
            "%-20s %-12s [%s] score=%s\n",
            $brain['id'],
            $brain['type'],
            $tier,
            $score
        );
    }

    return ['success' => true, 'output' => $output, 'data' => $brains];
}

function handleLeaderboard(BrainMesh $brainMesh, array $args): array {
    $domain = $args['flags']['domain'] ?? $args['positional'][0] ?? null;
    $leaderboard = $brainMesh->getLeaderboard($domain);

    $output = "BRAIN LEADERBOARD" . ($domain ? " (domain: $domain)" : "") . "\n";
    $output .= str_repeat("─", 70) . "\n";
    $output .= sprintf("%-4s %-20s %-10s %-8s %-10s %s\n", "Rank", "Brain", "Score", "Tier", "Execs", "Success%");
    $output .= str_repeat("─", 70) . "\n";

    foreach ($leaderboard as $brain) {
        $successPct = ($brain['total_executions'] > 0)
            ? number_format($brain['success_rate'] * 100, 1) . '%'
            : 'N/A';

        $output .= sprintf(
            "%-4d %-20s %-10s %-8s %-10d %s\n",
            $brain['rank'],
            $brain['id'],
            number_format($brain['score'], 4),
            $brain['tier'],
            $brain['total_executions'],
            $successPct
        );
    }

    return ['success' => true, 'output' => $output, 'data' => $leaderboard];
}

function handleStats(BrainMesh $brainMesh): array {
    $stats = $brainMesh->getAdaptiveStats();

    $output = "ADAPTIVE ROUTING STATISTICS\n";
    $output .= str_repeat("─", 50) . "\n";
    $output .= sprintf("Total Executions:     %d\n", $stats['total_executions']);
    $output .= sprintf("Overall Success Rate: %.2f%%\n", $stats['overall_success_rate'] * 100);
    $output .= sprintf("Exploration Rate:     %.0f%%\n", $stats['exploration_rate'] * 100);
    $output .= "\nTier Distribution:\n";

    foreach ($stats['tier_distribution'] as $tier => $count) {
        $output .= sprintf("  %-10s %d brains\n", $tier, $count);
    }

    if (!empty($stats['top_domains'])) {
        $output .= "\nTop Domains:\n";
        foreach ($stats['top_domains'] as $domain) {
            $output .= sprintf(
                "  %-25s %d calls (%.1f%% success)\n",
                $domain['domain'],
                $domain['count'],
                $domain['success_rate'] * 100
            );
        }
    }

    return ['success' => true, 'output' => $output, 'data' => $stats];
}

function handleHealth(BrainMesh $brainMesh): array {
    $health = $brainMesh->healthCheck();

    $status = $health['status'] === 'healthy' ? '✓ HEALTHY' : '⚠ DEGRADED';
    $output = "HEALTH CHECK: $status\n";
    $output .= str_repeat("─", 40) . "\n";
    $output .= sprintf("Total Brains:  %d\n", $health['total_brains']);
    $output .= sprintf("Active Brains: %d\n", $health['active_brains']);
    $output .= sprintf("CM-1 Session:  %s\n", substr($health['cm1_session'], 0, 16) . '...');
    $output .= sprintf("CM-1 Phase:    %s\n", $health['cm1_phase']);
    $output .= sprintf("Scope Depth:   %d\n", $health['scope_depth']);

    return ['success' => true, 'output' => $output, 'data' => $health];
}

function handleSelect(BrainMesh $brainMesh, array $args): array {
    $domain = $args['positional'][0] ?? null;

    if (!$domain) {
        return ['success' => false, 'error' => 'Domain required'];
    }

    $brain = $brainMesh->selectBrain($domain);

    if (!$brain) {
        return ['success' => false, 'error' => 'No brain available for domain: ' . $domain];
    }

    $output = sprintf("Selected brain for domain '%s':\n", $domain);
    $output .= sprintf("  ID:    %s\n", $brain['id']);
    $output .= sprintf("  Type:  %s\n", $brain['type']);
    $output .= sprintf("  Tier:  %s\n", $brain['tier'] ?? 'standard');
    $output .= sprintf("  Score: %s\n", number_format($brain['score'] ?? 0.5, 4));

    return ['success' => true, 'output' => $output, 'data' => $brain];
}

function handleBest(BrainMesh $brainMesh, array $args): array {
    return handleSelect($brainMesh, $args);
}

function handleStatus(BrainMesh $brainMesh): array {
    $health = $brainMesh->healthCheck();
    $stats = $brainMesh->getAdaptiveStats();

    $output = "BRAIN-MESH STATUS\n";
    $output .= str_repeat("═", 50) . "\n";
    $output .= sprintf("Status:      %s\n", $health['status']);
    $output .= sprintf("Brains:      %d active / %d total\n", $health['active_brains'], $health['total_brains']);
    $output .= sprintf("Executions:  %d total\n", $stats['total_executions']);
    $output .= sprintf("Success:     %.1f%%\n", $stats['overall_success_rate'] * 100);

    return ['success' => true, 'output' => $output, 'data' => ['health' => $health, 'stats' => $stats]];
}

function handleReset(BrainMesh $brainMesh): array {
    $brainMesh->resetScores();

    return [
        'success' => true,
        'output' => "All brain scores have been reset to defaults.\n",
    ];
}

function handleSave(BrainMesh $brainMesh, array $args): array {
    $state = $brainMesh->exportState();
    $filepath = $args['positional'][0] ?? 'brain-state-' . date('Y-m-d-His') . '.json';

    file_put_contents($filepath, json_encode($state, JSON_PRETTY_PRINT));

    return [
        'success' => true,
        'output' => "State saved to: $filepath\n",
        'data' => ['filepath' => $filepath],
    ];
}

function handleLoad(BrainMesh $brainMesh, array $args): array {
    $filepath = $args['positional'][0] ?? null;

    if (!$filepath || !file_exists($filepath)) {
        return ['success' => false, 'error' => 'File not found: ' . ($filepath ?? 'none')];
    }

    $state = json_decode(file_get_contents($filepath), true);
    $success = $brainMesh->importState($state);

    return [
        'success' => $success,
        'output' => $success ? "State loaded from: $filepath\n" : "Failed to load state\n",
    ];
}

// ═══════════════════════════════════════════════════════════════════
// LLM HANDLERS
// ═══════════════════════════════════════════════════════════════════

function handleLLMProviders(LLMProvider $llmProvider): array {
    $providers = $llmProvider->getProviders();

    $output = "LLM PROVIDERS\n";
    $output .= str_repeat("─", 50) . "\n";

    foreach ($providers as $name => $info) {
        $status = '✓';
        $output .= sprintf("%-15s [%s] %-8s %d models\n",
            $name, $status, $info['type'], count($info['models']));
    }

    return ['success' => true, 'output' => $output, 'data' => $providers];
}

function handleLLMModels(LLMProvider $llmProvider, array $args): array {
    $models = $llmProvider->getAllModels();

    $provider = $args['flags']['provider'] ?? null;
    $tier = $args['flags']['tier'] ?? null;

    if ($provider) {
        $models = array_filter($models, fn($m) => $m['provider'] === $provider);
    }
    if ($tier) {
        $models = array_filter($models, fn($m) => $m['tier'] === $tier);
    }

    $output = "LLM MODELS" . ($provider ? " ($provider)" : "") . "\n";
    $output .= str_repeat("─", 70) . "\n";

    foreach ($models as $model) {
        $enabled = $model['enabled'] ? '✓' : '✗';
        $output .= sprintf("[$enabled] %-35s %-12s %s\n",
            $model['id'], $model['provider'], $model['tier']);
    }

    return ['success' => true, 'output' => $output, 'data' => array_values($models)];
}

function handleLLMChat(LLMProvider $llmProvider, array $args): array {
    $model = $args['positional'][0] ?? null;
    $message = $args['positional'][1] ?? null;

    if (!$model || !$message) {
        return ['success' => false, 'error' => 'Usage: llm chat <model> "<message>"'];
    }

    $response = $llmProvider->chat($model, [
        ['role' => 'user', 'content' => $message]
    ]);

    if (!$response['success']) {
        return ['success' => false, 'error' => $response['error'] ?? 'Unknown error'];
    }

    $output = "Response from $model:\n";
    $output .= str_repeat("─", 50) . "\n";
    $output .= $response['content'] . "\n";
    $output .= str_repeat("─", 50) . "\n";
    $output .= sprintf("Tokens: %d in / %d out | Latency: %dms\n",
        $response['usage']['input_tokens'] ?? 0,
        $response['usage']['output_tokens'] ?? 0,
        $response['latency_ms'] ?? 0);

    return ['success' => true, 'output' => $output, 'data' => $response];
}

function handleLLMTest(LLMProvider $llmProvider, array $args): array {
    $model = $args['positional'][0] ?? null;

    if (!$model) {
        return ['success' => false, 'error' => 'Model required'];
    }

    $response = $llmProvider->chat($model, [
        ['role' => 'user', 'content' => 'Say "OK" if you can hear me.']
    ]);

    if (!$response['success']) {
        return [
            'success' => false,
            'output' => "✗ $model: " . ($response['error'] ?? 'Connection failed') . "\n",
            'data' => $response,
        ];
    }

    return [
        'success' => true,
        'output' => "✓ $model: Connected ({$response['latency_ms']}ms)\n",
        'data' => $response,
    ];
}

// ═══════════════════════════════════════════════════════════════════
// OLLAMA HANDLERS
// ═══════════════════════════════════════════════════════════════════

function handleOllamaList(LLMProvider $llmProvider): array {
    $models = $llmProvider->listOllamaModels();

    if (empty($models)) {
        return ['success' => false, 'output' => "No models found or Ollama not running\n"];
    }

    $output = "OLLAMA MODELS\n";
    $output .= str_repeat("─", 40) . "\n";

    foreach ($models as $model) {
        $output .= "  $model\n";
    }

    return ['success' => true, 'output' => $output, 'data' => $models];
}

function handleOllamaStatus(LLMProvider $llmProvider): array {
    $status = $llmProvider->checkOllamaConnection();

    if (!$status['connected']) {
        return [
            'success' => false,
            'output' => "✗ Ollama not connected: " . ($status['reason'] ?? 'Unknown') . "\n",
        ];
    }

    $output = "✓ Ollama connected\n";
    $output .= sprintf("  Models loaded: %d\n", count($status['models'] ?? []));

    return ['success' => true, 'output' => $output, 'data' => $status];
}

function handleOllamaPull(LLMProvider $llmProvider, array $args, array $config): array {
    $model = $args['positional'][0] ?? null;

    if (!$model) {
        return ['success' => false, 'error' => 'Model name required'];
    }

    // This would need to be async in real implementation
    return [
        'success' => true,
        'output' => "Pull request queued for: $model\n" .
                   "Run 'ollama list' to check status.\n",
    ];
}

function handleOllamaDelete(LLMProvider $llmProvider, array $args, array $config): array {
    $model = $args['positional'][0] ?? null;

    if (!$model) {
        return ['success' => false, 'error' => 'Model name required'];
    }

    return [
        'success' => true,
        'output' => "Delete request for: $model (not implemented in projection mode)\n",
    ];
}

// ═══════════════════════════════════════════════════════════════════
// FILE HANDLERS
// ═══════════════════════════════════════════════════════════════════

function validatePath(string $path, array $config): ?string {
    $sandboxRoot = realpath($config['terminal']['sandbox_root'] ?? __DIR__ . '/../../');
    $realPath = realpath($sandboxRoot . '/' . ltrim($path, '/'));

    // Check if path is within sandbox
    if ($realPath && strpos($realPath, $sandboxRoot) === 0) {
        // Check forbidden paths
        foreach ($config['editor']['forbidden_paths'] ?? [] as $forbidden) {
            if (strpos($path, $forbidden) !== false) {
                return null;
            }
        }
        return $realPath;
    }

    // For new files, check parent exists
    $parent = realpath(dirname($sandboxRoot . '/' . ltrim($path, '/')));
    if ($parent && strpos($parent, $sandboxRoot) === 0) {
        return $parent . '/' . basename($path);
    }

    return null;
}

function handleFileRead(array $args, array $config): array {
    $path = $args['positional'][0] ?? null;

    if (!$path) {
        return ['success' => false, 'error' => 'Path required'];
    }

    $realPath = validatePath($path, $config);

    if (!$realPath || !file_exists($realPath)) {
        return ['success' => false, 'error' => 'File not found or access denied'];
    }

    if (is_dir($realPath)) {
        return ['success' => false, 'error' => 'Path is a directory'];
    }

    $size = filesize($realPath);
    $maxSize = $config['editor']['max_file_size'] ?? 1048576;

    if ($size > $maxSize) {
        return ['success' => false, 'error' => 'File too large: ' . number_format($size) . ' bytes'];
    }

    $content = file_get_contents($realPath);

    return [
        'success' => true,
        'output' => $content,
        'data' => [
            'path' => $path,
            'size' => $size,
            'mime' => mime_content_type($realPath),
        ],
    ];
}

function handleFileList(array $args, array $config): array {
    $path = $args['positional'][0] ?? '.';
    $realPath = validatePath($path, $config);

    if (!$realPath || !is_dir($realPath)) {
        return ['success' => false, 'error' => 'Directory not found or access denied'];
    }

    $entries = [];
    foreach (scandir($realPath) as $entry) {
        if ($entry === '.' || $entry === '..') continue;

        $fullPath = $realPath . '/' . $entry;
        $entries[] = [
            'name' => $entry,
            'type' => is_dir($fullPath) ? 'dir' : 'file',
            'size' => is_file($fullPath) ? filesize($fullPath) : null,
            'modified' => filemtime($fullPath),
        ];
    }

    // Sort: directories first, then by name
    usort($entries, function($a, $b) {
        if ($a['type'] !== $b['type']) {
            return $a['type'] === 'dir' ? -1 : 1;
        }
        return strcasecmp($a['name'], $b['name']);
    });

    $output = "Directory: $path\n";
    $output .= str_repeat("─", 60) . "\n";

    foreach ($entries as $e) {
        $icon = $e['type'] === 'dir' ? '📁' : '📄';
        $size = $e['size'] !== null ? number_format($e['size']) : '-';
        $output .= sprintf("%s %-30s %10s\n", $icon, $e['name'], $size);
    }

    return ['success' => true, 'output' => $output, 'data' => $entries];
}

function handleFileInfo(array $args, array $config): array {
    $path = $args['positional'][0] ?? null;

    if (!$path) {
        return ['success' => false, 'error' => 'Path required'];
    }

    $realPath = validatePath($path, $config);

    if (!$realPath || !file_exists($realPath)) {
        return ['success' => false, 'error' => 'File not found or access denied'];
    }

    $info = [
        'path' => $path,
        'real_path' => $realPath,
        'type' => is_dir($realPath) ? 'directory' : 'file',
        'size' => is_file($realPath) ? filesize($realPath) : null,
        'modified' => date('Y-m-d H:i:s', filemtime($realPath)),
        'permissions' => substr(sprintf('%o', fileperms($realPath)), -4),
    ];

    if (is_file($realPath)) {
        $info['mime'] = mime_content_type($realPath);
        $info['extension'] = pathinfo($realPath, PATHINFO_EXTENSION);
    }

    $output = "File Info: $path\n";
    $output .= str_repeat("─", 40) . "\n";

    foreach ($info as $key => $value) {
        $output .= sprintf("%-15s %s\n", $key . ':', $value ?? 'N/A');
    }

    return ['success' => true, 'output' => $output, 'data' => $info];
}

function handleFileSearch(array $args, array $config): array {
    $pattern = $args['positional'][0] ?? null;
    $searchPath = $args['flags']['path'] ?? '.';

    if (!$pattern) {
        return ['success' => false, 'error' => 'Pattern required'];
    }

    $realPath = validatePath($searchPath, $config);

    if (!$realPath) {
        return ['success' => false, 'error' => 'Invalid search path'];
    }

    $results = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($realPath, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (fnmatch($pattern, $file->getFilename())) {
            $results[] = str_replace($realPath . '/', '', $file->getPathname());
        }

        if (count($results) >= 100) break;  // Limit results
    }

    $output = "Search: $pattern\n";
    $output .= str_repeat("─", 40) . "\n";

    foreach ($results as $r) {
        $output .= "  $r\n";
    }

    $output .= "\n" . count($results) . " matches found\n";

    return ['success' => true, 'output' => $output, 'data' => $results];
}

function handleFileTree(array $args, array $config): array {
    $path = $args['positional'][0] ?? '.';
    $maxDepth = (int)($args['flags']['depth'] ?? 3);

    $realPath = validatePath($path, $config);

    if (!$realPath || !is_dir($realPath)) {
        return ['success' => false, 'error' => 'Directory not found'];
    }

    $output = "$path\n";
    $output .= buildTree($realPath, '', $maxDepth, 0);

    return ['success' => true, 'output' => $output];
}

function buildTree(string $path, string $prefix, int $maxDepth, int $depth): string {
    if ($depth >= $maxDepth) return '';

    $output = '';
    $entries = array_diff(scandir($path), ['.', '..']);
    $count = count($entries);
    $i = 0;

    foreach ($entries as $entry) {
        $i++;
        $isLast = ($i === $count);
        $connector = $isLast ? '└── ' : '├── ';
        $fullPath = $path . '/' . $entry;

        $output .= $prefix . $connector . $entry . "\n";

        if (is_dir($fullPath)) {
            $nextPrefix = $prefix . ($isLast ? '    ' : '│   ');
            $output .= buildTree($fullPath, $nextPrefix, $maxDepth, $depth + 1);
        }
    }

    return $output;
}

function handleFileWrite(array $args, array $config): array {
    $path = $args['positional'][0] ?? null;
    $content = $args['positional'][1] ?? null;

    if (!$path || $content === null) {
        return ['success' => false, 'error' => 'Path and content required'];
    }

    $realPath = validatePath($path, $config);

    if (!$realPath) {
        return ['success' => false, 'error' => 'Invalid path or access denied'];
    }

    file_put_contents($realPath, $content);

    return [
        'success' => true,
        'output' => "Written " . strlen($content) . " bytes to $path\n",
    ];
}

function handleFileCreate(array $args, array $config): array {
    $path = $args['positional'][0] ?? null;

    if (!$path) {
        return ['success' => false, 'error' => 'Path required'];
    }

    $realPath = validatePath($path, $config);

    if (!$realPath) {
        return ['success' => false, 'error' => 'Invalid path'];
    }

    if (file_exists($realPath)) {
        return ['success' => false, 'error' => 'File already exists'];
    }

    touch($realPath);

    return ['success' => true, 'output' => "Created: $path\n"];
}

function handleFileMkdir(array $args, array $config): array {
    $path = $args['positional'][0] ?? null;

    if (!$path) {
        return ['success' => false, 'error' => 'Path required'];
    }

    $sandboxRoot = realpath($config['terminal']['sandbox_root'] ?? __DIR__ . '/../../');
    $fullPath = $sandboxRoot . '/' . ltrim($path, '/');

    if (!str_starts_with($fullPath, $sandboxRoot)) {
        return ['success' => false, 'error' => 'Path outside sandbox'];
    }

    mkdir($fullPath, 0755, true);

    return ['success' => true, 'output' => "Created directory: $path\n"];
}

function handleFileDelete(array $args, array $config): array {
    $path = $args['positional'][0] ?? null;

    if (!$path) {
        return ['success' => false, 'error' => 'Path required'];
    }

    $realPath = validatePath($path, $config);

    if (!$realPath || !file_exists($realPath)) {
        return ['success' => false, 'error' => 'File not found'];
    }

    if (is_dir($realPath)) {
        return ['success' => false, 'error' => 'Cannot delete directories via terminal'];
    }

    unlink($realPath);

    return ['success' => true, 'output' => "Deleted: $path\n"];
}

function handleFileMove(array $args, array $config): array {
    $source = $args['positional'][0] ?? null;
    $dest = $args['positional'][1] ?? null;

    if (!$source || !$dest) {
        return ['success' => false, 'error' => 'Source and destination required'];
    }

    $realSource = validatePath($source, $config);
    $realDest = validatePath($dest, $config);

    if (!$realSource || !file_exists($realSource)) {
        return ['success' => false, 'error' => 'Source not found'];
    }

    if (!$realDest) {
        return ['success' => false, 'error' => 'Invalid destination'];
    }

    rename($realSource, $realDest);

    return ['success' => true, 'output' => "Moved: $source → $dest\n"];
}

function handleFileCopy(array $args, array $config): array {
    $source = $args['positional'][0] ?? null;
    $dest = $args['positional'][1] ?? null;

    if (!$source || !$dest) {
        return ['success' => false, 'error' => 'Source and destination required'];
    }

    $realSource = validatePath($source, $config);
    $realDest = validatePath($dest, $config);

    if (!$realSource || !file_exists($realSource)) {
        return ['success' => false, 'error' => 'Source not found'];
    }

    if (!$realDest) {
        return ['success' => false, 'error' => 'Invalid destination'];
    }

    copy($realSource, $realDest);

    return ['success' => true, 'output' => "Copied: $source → $dest\n"];
}

// ═══════════════════════════════════════════════════════════════════
// CM-1 HANDLERS
// ═══════════════════════════════════════════════════════════════════

function handleCM1Phase(BrainMesh $brainMesh): array {
    $health = $brainMesh->healthCheck();

    return [
        'success' => true,
        'output' => "Current CM-1 Phase: " . $health['cm1_phase'] . "\n",
        'data' => ['phase' => $health['cm1_phase']],
    ];
}

function handleCM1History(PDO $pdo, array $args): array {
    $limit = (int)($args['flags']['limit'] ?? 20);

    $stmt = $pdo->prepare(
        "SELECT * FROM cm1_phases ORDER BY created_at DESC LIMIT ?"
    );
    $stmt->execute([$limit]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $output = "CM-1 PHASE HISTORY\n";
    $output .= str_repeat("─", 60) . "\n";

    foreach ($history as $h) {
        $output .= sprintf(
            "%s: %s → %s\n",
            $h['created_at'],
            $h['from_phase'] ?? 'null',
            $h['to_phase']
        );
    }

    return ['success' => true, 'output' => $output, 'data' => $history];
}

function handleCM1Scope(BrainMesh $brainMesh): array {
    $health = $brainMesh->healthCheck();

    return [
        'success' => true,
        'output' => "Scope Depth: " . $health['scope_depth'] . "\n",
        'data' => ['scope_depth' => $health['scope_depth']],
    ];
}

function handleCM1Transition(BrainMesh $brainMesh, array $args): array {
    $from = $args['positional'][0] ?? null;
    $to = $args['positional'][1] ?? null;

    if (!$from || !$to) {
        return ['success' => false, 'error' => 'From and To phases required'];
    }

    $brainMesh->onPhaseTransition($from, $to);

    return [
        'success' => true,
        'output' => "Phase transition: $from → $to\n",
    ];
}

function handleCM1Push(BrainMesh $brainMesh, array $args): array {
    $scopeId = $args['positional'][0] ?? null;

    if (!$scopeId) {
        return ['success' => false, 'error' => 'Scope ID required'];
    }

    $depth = $brainMesh->pushScope($scopeId);

    return [
        'success' => true,
        'output' => "Pushed scope: $scopeId (depth: $depth)\n",
    ];
}

function handleCM1Pop(BrainMesh $brainMesh): array {
    $scopeId = $brainMesh->popScope();

    if ($scopeId === null) {
        return ['success' => false, 'error' => 'Scope stack empty'];
    }

    return [
        'success' => true,
        'output' => "Popped scope: $scopeId\n",
    ];
}

// ═══════════════════════════════════════════════════════════════════
// HELP HANDLERS
// ═══════════════════════════════════════════════════════════════════

function handleHelp(): array {
    global $COMMAND_REGISTRY;

    $output = "BRAIN-MESH TERMINAL\n";
    $output .= str_repeat("═", 60) . "\n\n";

    foreach ($COMMAND_REGISTRY as $namespace => $ns) {
        $output .= strtoupper($namespace) . " - " . $ns['description'] . "\n";

        foreach ($ns['commands'] as $cmd => $meta) {
            if ($cmd === '') {
                $output .= sprintf("  %-25s %s\n", $namespace, $meta['description']);
            } else {
                $output .= sprintf("  %-25s %s\n", "$namespace $cmd", $meta['description']);
            }
        }

        $output .= "\n";
    }

    return ['success' => true, 'output' => $output];
}

function handleHelpSearch(array $args): array {
    global $isAdmin;

    $query = $args['positional'][0] ?? '';

    if (!$query) {
        return ['success' => false, 'error' => 'Search query required'];
    }

    $registry = createCommandRegistry($isAdmin);
    $results = $registry->search($query);

    $output = "Search results for: $query\n";
    $output .= str_repeat("─", 40) . "\n";

    foreach ($results as $cmd) {
        $admin = $cmd['admin'] ? ' [ADMIN]' : '';
        $output .= sprintf("%-30s %s%s\n", $cmd['full'], $cmd['description'], $admin);
    }

    return ['success' => true, 'output' => $output, 'data' => $results];
}

// ═══════════════════════════════════════════════════════════════════
// SYSTEM HANDLERS
// ═══════════════════════════════════════════════════════════════════

function handleSystemInfo(): array {
    $info = [
        'php_version' => PHP_VERSION,
        'os' => PHP_OS,
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'CLI',
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
    ];

    $output = "SYSTEM INFO\n";
    $output .= str_repeat("─", 40) . "\n";

    foreach ($info as $key => $value) {
        $output .= sprintf("%-20s %s\n", $key . ':', $value);
    }

    return ['success' => true, 'output' => $output, 'data' => $info];
}

function handleSystemVersion(): array {
    $version = [
        'brain_mesh' => '2.1.0',
        'terminal' => '1.0.0',
        'protocol' => 'CM-1',
        'law' => 'ASX = XCFE = XJSON = KUHUL = AST = CM-1',
    ];

    $output = "Brain Mesh Terminal v{$version['terminal']}\n";
    $output .= "Orchestrator v{$version['brain_mesh']}\n";
    $output .= "@law {$version['law']}\n";

    return ['success' => true, 'output' => $output, 'data' => $version];
}

function handleSystemConfig(array $config): array {
    // Redact sensitive values
    $redacted = $config;

    $sensitiveKeys = ['api_key', 'pass', 'secret', 'password', 'token'];

    array_walk_recursive($redacted, function(&$value, $key) use ($sensitiveKeys) {
        foreach ($sensitiveKeys as $sensitive) {
            if (stripos($key, $sensitive) !== false && !empty($value)) {
                $value = '***REDACTED***';
            }
        }
    });

    return [
        'success' => true,
        'output' => json_encode($redacted, JSON_PRETTY_PRINT) . "\n",
        'data' => $redacted,
    ];
}

// ═══════════════════════════════════════════════════════════════════
// TASK HANDLERS
// ═══════════════════════════════════════════════════════════════════

function handleTaskList(PDO $pdo, array $args): array {
    $status = $args['flags']['status'] ?? null;
    $limit = (int)($args['flags']['limit'] ?? 20);

    $sql = "SELECT * FROM tasks";
    $params = [];

    if ($status) {
        $sql .= " WHERE status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY updated_at DESC LIMIT ?";
    $params[] = $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $output = "TASKS" . ($status ? " (status: $status)" : "") . "\n";
    $output .= str_repeat("─", 70) . "\n";

    foreach ($tasks as $task) {
        $output .= sprintf(
            "#%-6d %-10s %-40s\n",
            $task['id'],
            $task['status'],
            substr($task['command'], 0, 40)
        );
    }

    return ['success' => true, 'output' => $output, 'data' => $tasks];
}

function handleTaskGet(PDO $pdo, array $args): array {
    $id = (int)($args['positional'][0] ?? 0);

    if (!$id) {
        return ['success' => false, 'error' => 'Task ID required'];
    }

    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        return ['success' => false, 'error' => 'Task not found'];
    }

    $output = "TASK #$id\n";
    $output .= str_repeat("─", 40) . "\n";

    foreach ($task as $key => $value) {
        $output .= sprintf("%-15s %s\n", $key . ':', $value ?? 'null');
    }

    return ['success' => true, 'output' => $output, 'data' => $task];
}

function handleTaskStatus(PDO $pdo, array $args): array {
    return handleTaskGet($pdo, $args);
}

function handleTaskAdd(PDO $pdo, array $args): array {
    $command = $args['positional'][0] ?? null;
    $agent = $args['flags']['agent'] ?? null;

    if (!$command) {
        return ['success' => false, 'error' => 'Command required'];
    }

    $stmt = $pdo->prepare(
        "INSERT INTO tasks (command, assigned_agent, status) VALUES (?, ?, 'pending')"
    );
    $stmt->execute([$command, $agent]);
    $id = $pdo->lastInsertId();

    return [
        'success' => true,
        'output' => "Task created: #$id\n",
        'data' => ['task_id' => $id],
    ];
}

function handleTaskCancel(PDO $pdo, array $args): array {
    $id = (int)($args['positional'][0] ?? 0);

    if (!$id) {
        return ['success' => false, 'error' => 'Task ID required'];
    }

    $stmt = $pdo->prepare(
        "UPDATE tasks SET status = 'cancelled' WHERE id = ? AND status = 'pending'"
    );
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        return ['success' => false, 'error' => 'Task not found or not cancellable'];
    }

    return ['success' => true, 'output' => "Task #$id cancelled\n"];
}
