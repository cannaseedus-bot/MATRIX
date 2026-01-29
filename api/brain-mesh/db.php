<?php
/**
 * Brain Mesh Database Connection
 *
 * Loads config and establishes PDO connection
 * Supports both standalone mode (own config.php) and integrated mode (parent db.php)
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 */

$configFile = __DIR__ . '/config.php';
$parentDb = __DIR__ . '/../db.php';

// Mode 1: Standalone with own config
if (file_exists($configFile)) {
    $config = require $configFile;

    try {
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=%s",
            $config['db']['host'],
            $config['db']['port'],
            $config['db']['name'],
            $config['db']['charset']
        );

        $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);

        // Export config for use in BrainMesh
        $brainMeshConfig = $config['adaptive'] ?? [];

    } catch (PDOException $e) {
        if (php_sapi_name() === 'cli') {
            echo "Database connection error: " . $e->getMessage() . "\n";
            exit(1);
        }

        header("Content-Type: application/json");
        http_response_code(500);
        echo json_encode([
            '@context' => 'asx://brain-mesh/error',
            'error' => 'Database connection failed',
            'message' => $e->getMessage()
        ]);
        exit;
    }
}
// Mode 2: Integrated with parent API db.php
elseif (file_exists($parentDb)) {
    require_once $parentDb;

    // Check if $pdo exists from parent
    if (!isset($pdo)) {
        if (php_sapi_name() === 'cli') {
            echo "Error: Parent db.php did not provide \$pdo connection.\n";
            exit(1);
        }

        header("Content-Type: application/json");
        http_response_code(500);
        echo json_encode([
            '@context' => 'asx://brain-mesh/error',
            'error' => 'Database not configured',
            'message' => 'Parent db.php did not provide $pdo connection'
        ]);
        exit;
    }

    // Default config when using parent db
    $brainMeshConfig = [
        'exploration_rate' => 0.1,
        'score_weights' => [
            'success_rate' => 0.5,
            'speed' => 0.3,
            'consistency' => 0.2
        ],
        'promotion_threshold' => 0.85,
        'demotion_threshold' => 0.4,
        'rolling_window_size' => 50,
        'min_executions_for_tier' => 10
    ];
}
// Mode 3: Not configured
else {
    if (php_sapi_name() === 'cli') {
        echo "Error: Brain Mesh not installed. Run install.php first.\n";
        exit(1);
    }

    header("Content-Type: application/json");
    http_response_code(500);
    echo json_encode([
        '@context' => 'asx://brain-mesh/error',
        'error' => 'Brain Mesh not installed',
        'message' => 'Run install.php to configure, or ensure parent db.php exists'
    ]);
    exit;
}
