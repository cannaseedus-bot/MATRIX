<?php
/**
 * Brain Mesh Orchestrator - PHP Implementation
 *
 * CM-1 Micronaut System for PHP Broker
 * Adaptive brain scoring with multi-armed bandit selection
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 * @version 2.0.0
 */

class BrainMesh {
    private PDO $pdo;

    // Adaptive routing config
    private float $explorationRate = 0.1;  // 10% exploration
    private array $scoreWeights = [
        'successRate' => 0.5,
        'speed' => 0.3,
        'consistency' => 0.2
    ];
    private float $promotionThreshold = 0.85;
    private float $demotionThreshold = 0.4;
    private int $rollingWindowSize = 50;
    private int $minExecutionsForTier = 10;

    // CM-1 state
    private array $cm1State = [
        'currentPhase' => 'null',
        'scopeStack' => [],
        'sessionId' => null
    ];

    public function __construct(PDO $pdo, array $options = []) {
        $this->pdo = $pdo;

        // Apply custom options
        if (isset($options['explorationRate'])) {
            $this->explorationRate = $options['explorationRate'];
        }
        if (isset($options['scoreWeights'])) {
            $this->scoreWeights = array_merge($this->scoreWeights, $options['scoreWeights']);
        }
        if (isset($options['promotionThreshold'])) {
            $this->promotionThreshold = $options['promotionThreshold'];
        }
        if (isset($options['demotionThreshold'])) {
            $this->demotionThreshold = $options['demotionThreshold'];
        }

        $this->cm1State['sessionId'] = bin2hex(random_bytes(16));
    }

    /**
     * Select best brain for domain using epsilon-greedy algorithm
     */
    public function selectBrain(string $domain): ?array {
        // Get candidates for domain
        $candidates = $this->getCandidatesForDomain($domain);

        if (empty($candidates)) {
            // Fallback to primary orchestrator
            return $this->getBrain('mx2lm_prime');
        }

        // Epsilon-greedy selection
        if (mt_rand() / mt_getrandmax() < $this->explorationRate) {
            // Explore: random selection
            return $candidates[array_rand($candidates)];
        }

        // Exploit: select best scoring brain
        return $this->selectBestBrain($domain, $candidates);
    }

    /**
     * Get candidate brains for a domain
     */
    private function getCandidatesForDomain(string $domain): array {
        // Check routing rules first
        $stmt = $this->pdo->prepare(
            "SELECT b.*, bs.score, bs.tier, bs.success_rate, bs.total_executions
             FROM brains b
             LEFT JOIN brain_scores bs ON b.id = bs.brain_id AND bs.domain = ?
             WHERE JSON_CONTAINS(b.domains, ?, '$')
                OR EXISTS (SELECT 1 FROM routing_rules r WHERE r.domain = ? AND r.brain_id = b.id)
             ORDER BY b.priority ASC"
        );
        $stmt->execute([
            $domain,
            json_encode($domain),
            $domain
        ]);

        $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // If no specific candidates, get all standard+ tier brains
        if (empty($candidates)) {
            $stmt = $this->pdo->prepare(
                "SELECT b.*, bs.score, bs.tier, bs.success_rate, bs.total_executions
                 FROM brains b
                 LEFT JOIN brain_scores bs ON b.id = bs.brain_id AND bs.domain = 'general'
                 WHERE bs.tier IS NULL OR bs.tier != 'demoted'
                 ORDER BY b.priority ASC"
            );
            $stmt->execute();
            $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $candidates;
    }

    /**
     * Select best brain using UCB-style scoring
     */
    private function selectBestBrain(string $domain, array $candidates): ?array {
        $bestBrain = null;
        $bestScore = -INF;

        $totalExecutions = array_sum(array_column($candidates, 'total_executions'));

        foreach ($candidates as $brain) {
            $score = $this->calculateEffectiveScore($brain, $totalExecutions);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestBrain = $brain;
            }
        }

        return $bestBrain;
    }

    /**
     * Calculate effective score with UCB exploration bonus
     */
    private function calculateEffectiveScore(array $brain, int $totalExecutions): float {
        $baseScore = $brain['score'] ?? 0.5;
        $executions = $brain['total_executions'] ?? 0;

        // UCB exploration bonus
        $explorationBonus = 0;
        if ($executions > 0 && $totalExecutions > 0) {
            $explorationBonus = sqrt(2 * log($totalExecutions) / $executions);
        } elseif ($executions == 0) {
            $explorationBonus = 1.0;  // High bonus for unexplored brains
        }

        // Tier modifier
        $tierModifier = match($brain['tier'] ?? 'standard') {
            'promoted' => 0.1,
            'demoted' => -0.2,
            default => 0
        };

        return $baseScore + ($explorationBonus * 0.1) + $tierModifier;
    }

    /**
     * Record execution result and update scores
     */
    public function recordExecution(
        string $brainId,
        string $domain,
        bool $success,
        int $latencyMs,
        ?int $taskId = null,
        ?string $errorMessage = null,
        array $context = []
    ): array {
        // Insert execution record
        $stmt = $this->pdo->prepare(
            "INSERT INTO brain_executions (brain_id, domain, task_id, success, latency_ms, error_message, context)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $brainId,
            $domain,
            $taskId,
            $success ? 1 : 0,
            $latencyMs,
            $errorMessage,
            json_encode($context)
        ]);

        // Update scores
        $this->updateBrainScore($brainId, $domain, $success, $latencyMs);

        // Evaluate tier
        $newTier = $this->evaluateBrainTier($brainId, $domain);

        return [
            'brain_id' => $brainId,
            'domain' => $domain,
            'success' => $success,
            'new_tier' => $newTier
        ];
    }

    /**
     * Update brain score based on execution result
     */
    private function updateBrainScore(string $brainId, string $domain, bool $success, int $latencyMs): void {
        // Get recent executions for rolling window
        $stmt = $this->pdo->prepare(
            "SELECT success, latency_ms FROM brain_executions
             WHERE brain_id = ? AND domain = ?
             ORDER BY created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$brainId, $domain, $this->rollingWindowSize]);
        $recentExecutions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($recentExecutions)) {
            return;
        }

        // Calculate metrics
        $successCount = array_sum(array_column($recentExecutions, 'success'));
        $totalCount = count($recentExecutions);
        $successRate = $successCount / $totalCount;

        $latencies = array_column($recentExecutions, 'latency_ms');
        $avgLatency = array_sum($latencies) / count($latencies);

        // Calculate consistency (inverse of variance)
        $variance = 0;
        foreach ($latencies as $lat) {
            $variance += pow($lat - $avgLatency, 2);
        }
        $variance /= count($latencies);
        $consistency = 1 / (1 + sqrt($variance) / 1000);

        // Speed score (inverse latency, normalized)
        $speedScore = 1 / (1 + $avgLatency / 1000);

        // Weighted composite score
        $compositeScore =
            ($this->scoreWeights['successRate'] * $successRate) +
            ($this->scoreWeights['speed'] * $speedScore) +
            ($this->scoreWeights['consistency'] * $consistency);

        // Upsert score record
        $stmt = $this->pdo->prepare(
            "INSERT INTO brain_scores (brain_id, domain, score, success_rate, avg_latency_ms, total_executions, total_successes, total_failures)
             VALUES (?, ?, ?, ?, ?, 1, ?, ?)
             ON DUPLICATE KEY UPDATE
                score = ?,
                success_rate = ?,
                avg_latency_ms = ?,
                total_executions = total_executions + 1,
                total_successes = total_successes + ?,
                total_failures = total_failures + ?,
                updated_at = NOW()"
        );
        $stmt->execute([
            $brainId,
            $domain,
            $compositeScore,
            $successRate,
            (int)$avgLatency,
            $success ? 1 : 0,
            $success ? 0 : 1,
            $compositeScore,
            $successRate,
            (int)$avgLatency,
            $success ? 1 : 0,
            $success ? 0 : 1
        ]);
    }

    /**
     * Evaluate and update brain tier
     */
    private function evaluateBrainTier(string $brainId, string $domain): string {
        $stmt = $this->pdo->prepare(
            "SELECT score, total_executions, tier FROM brain_scores
             WHERE brain_id = ? AND domain = ?"
        );
        $stmt->execute([$brainId, $domain]);
        $scoreData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$scoreData || $scoreData['total_executions'] < $this->minExecutionsForTier) {
            return 'standard';
        }

        $score = (float)$scoreData['score'];
        $currentTier = $scoreData['tier'] ?? 'standard';
        $newTier = $currentTier;

        if ($score >= $this->promotionThreshold && $currentTier !== 'promoted') {
            $newTier = 'promoted';
        } elseif ($score <= $this->demotionThreshold && $currentTier !== 'demoted') {
            $newTier = 'demoted';
        } elseif ($score > $this->demotionThreshold && $score < $this->promotionThreshold) {
            $newTier = 'standard';
        }

        if ($newTier !== $currentTier) {
            $stmt = $this->pdo->prepare(
                "UPDATE brain_scores SET tier = ? WHERE brain_id = ? AND domain = ?"
            );
            $stmt->execute([$newTier, $brainId, $domain]);
        }

        return $newTier;
    }

    /**
     * Get brain leaderboard (unified: brains + rigs)
     */
    public function getLeaderboard(?string $domain = null, bool $includeRigs = true): array {
        $sql = "SELECT
                    b.id, b.label, b.type,
                    COALESCE(bs.score, 0.5) as score,
                    COALESCE(bs.success_rate, 0.5) as success_rate,
                    COALESCE(bs.tier, 'standard') as tier,
                    COALESCE(bs.total_executions, 0) as total_executions,
                    COALESCE(bs.avg_latency_ms, 0) as avg_latency_ms,
                    0 as is_rig
                FROM brains b
                LEFT JOIN brain_scores bs ON b.id = bs.brain_id";

        $params = [];
        if ($domain) {
            $sql .= " AND bs.domain = ?";
            $params[] = $domain;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Include rigs in leaderboard
        if ($includeRigs) {
            try {
                $rigSql = "SELECT
                            id, name as label, 'rig' as type,
                            score,
                            CASE WHEN total_requests > 0
                                THEN successful_requests / total_requests
                                ELSE 0.5 END as success_rate,
                            CASE
                                WHEN score >= ? THEN 'promoted'
                                WHEN score <= ? THEN 'demoted'
                                ELSE 'standard' END as tier,
                            total_requests as total_executions,
                            avg_latency_ms,
                            1 as is_rig,
                            status,
                            models
                        FROM rigs
                        WHERE status != 'offline' OR last_seen > DATE_SUB(NOW(), INTERVAL 1 HOUR)";

                $rigStmt = $this->pdo->prepare($rigSql);
                $rigStmt->execute([$this->promotionThreshold, $this->demotionThreshold]);
                $rigs = $rigStmt->fetchAll(PDO::FETCH_ASSOC);

                // Decode models JSON for rigs
                foreach ($rigs as &$rig) {
                    $rig['models'] = json_decode($rig['models'], true) ?? [];
                    $rig['score'] = (float) $rig['score'];
                }

                $entries = array_merge($entries, $rigs);
            } catch (PDOException $e) {
                // Rigs table might not exist yet, continue without
            }
        }

        // Sort by score descending
        usort($entries, fn($a, $b) => (float)$b['score'] <=> (float)$a['score']);

        // Add rank
        $rank = 1;
        foreach ($entries as &$entry) {
            $entry['rank'] = $rank++;
            $entry['is_rig'] = (bool) ($entry['is_rig'] ?? false);
        }

        return $entries;
    }

    /**
     * Get brain by ID (includes rigs with rig- prefix)
     */
    public function getBrain(string $id): ?array {
        // Check if it's a rig
        if (str_starts_with($id, 'rig-') || str_starts_with($id, 'rig:')) {
            return $this->getRig($id);
        }

        $stmt = $this->pdo->prepare("SELECT * FROM brains WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get rig by ID
     */
    public function getRig(string $rigId): ?array {
        // Normalize ID (rig:xxx -> rig-xxx)
        $rigId = str_replace('rig:', 'rig-', $rigId);

        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, name, models, capabilities, status, score,
                        total_requests, successful_requests, avg_latency_ms,
                        last_seen, created_at
                 FROM rigs WHERE id = ?"
            );
            $stmt->execute([$rigId]);
            $rig = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$rig) {
                return null;
            }

            // Format as brain-compatible structure
            return [
                'id' => $rig['id'],
                'label' => $rig['name'],
                'type' => 'rig',
                'models' => json_decode($rig['models'], true) ?? [],
                'capabilities' => json_decode($rig['capabilities'], true) ?? [],
                'status' => $rig['status'],
                'score' => (float) $rig['score'],
                'tier' => $this->rigScoreToTier((float) $rig['score']),
                'total_executions' => $rig['total_requests'],
                'success_rate' => $rig['total_requests'] > 0
                    ? $rig['successful_requests'] / $rig['total_requests']
                    : 0.5,
                'avg_latency_ms' => $rig['avg_latency_ms'],
                'last_seen' => $rig['last_seen'],
                'is_rig' => true,
            ];
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Convert rig score to tier
     */
    private function rigScoreToTier(float $score): string {
        if ($score >= $this->promotionThreshold) {
            return 'promoted';
        } elseif ($score <= $this->demotionThreshold) {
            return 'demoted';
        }
        return 'standard';
    }

    /**
     * Get all online rigs
     */
    public function getOnlineRigs(): array {
        try {
            // Mark stale rigs as offline
            $this->pdo->exec(
                "UPDATE rigs SET status = 'offline'
                 WHERE status = 'online' AND last_seen < DATE_SUB(NOW(), INTERVAL 2 MINUTE)"
            );

            $stmt = $this->pdo->query(
                "SELECT id, name, models, capabilities, status, score,
                        total_requests, successful_requests, avg_latency_ms,
                        last_seen
                 FROM rigs
                 WHERE status = 'online'
                 ORDER BY score DESC"
            );

            $rigs = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $rig) {
                $rigs[] = [
                    'id' => $rig['id'],
                    'label' => $rig['name'],
                    'type' => 'rig',
                    'models' => json_decode($rig['models'], true) ?? [],
                    'capabilities' => json_decode($rig['capabilities'], true) ?? [],
                    'status' => $rig['status'],
                    'score' => (float) $rig['score'],
                    'tier' => $this->rigScoreToTier((float) $rig['score']),
                    'total_executions' => $rig['total_requests'],
                    'success_rate' => $rig['total_requests'] > 0
                        ? $rig['successful_requests'] / $rig['total_requests']
                        : 0.5,
                    'avg_latency_ms' => $rig['avg_latency_ms'],
                    'is_rig' => true,
                ];
            }

            return $rigs;
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Find rigs that have a specific model
     */
    public function findRigsWithModel(string $model): array {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, name, models, score, status, avg_latency_ms
                 FROM rigs
                 WHERE status = 'online' AND JSON_CONTAINS(models, ?)
                 ORDER BY score DESC"
            );
            $stmt->execute([json_encode($model)]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Select best rig for a model (epsilon-greedy)
     */
    public function selectRigForModel(string $model): ?array {
        $rigs = $this->findRigsWithModel($model);

        if (empty($rigs)) {
            return null;
        }

        // Epsilon-greedy selection
        if (mt_rand() / mt_getrandmax() < $this->explorationRate) {
            // Explore: random selection
            $rig = $rigs[array_rand($rigs)];
        } else {
            // Exploit: select best scoring rig
            $rig = $rigs[0]; // Already sorted by score DESC
        }

        return $this->getRig($rig['id']);
    }

    /**
     * Get all brains with scores
     */
    public function getAllBrains(): array {
        $stmt = $this->pdo->query(
            "SELECT b.*, bs.score, bs.tier, bs.success_rate, bs.total_executions
             FROM brains b
             LEFT JOIN brain_scores bs ON b.id = bs.brain_id AND bs.domain = 'general'
             ORDER BY b.priority ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get adaptive routing statistics
     */
    public function getAdaptiveStats(): array {
        // Total executions
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM brain_executions");
        $totalExecutions = (int)$stmt->fetchColumn();

        // Success rate
        $stmt = $this->pdo->query("SELECT AVG(success) FROM brain_executions");
        $overallSuccessRate = (float)$stmt->fetchColumn();

        // Tier counts
        $stmt = $this->pdo->query(
            "SELECT tier, COUNT(*) as count FROM brain_scores GROUP BY tier"
        );
        $tierCounts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $tierCounts[$row['tier']] = (int)$row['count'];
        }

        // Top domains
        $stmt = $this->pdo->query(
            "SELECT domain, COUNT(*) as count, AVG(success) as success_rate
             FROM brain_executions
             GROUP BY domain
             ORDER BY count DESC
             LIMIT 10"
        );
        $topDomains = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'total_executions' => $totalExecutions,
            'overall_success_rate' => round($overallSuccessRate, 4),
            'exploration_rate' => $this->explorationRate,
            'tier_distribution' => $tierCounts,
            'top_domains' => $topDomains,
            'config' => [
                'promotion_threshold' => $this->promotionThreshold,
                'demotion_threshold' => $this->demotionThreshold,
                'rolling_window_size' => $this->rollingWindowSize,
                'min_executions_for_tier' => $this->minExecutionsForTier
            ]
        ];
    }

    /**
     * CM-1 phase transition
     */
    public function onPhaseTransition(string $from, string $to, array $context = []): void {
        $this->cm1State['currentPhase'] = $to;

        $stmt = $this->pdo->prepare(
            "INSERT INTO cm1_phases (session_id, from_phase, to_phase, context)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([
            $this->cm1State['sessionId'],
            $from,
            $to,
            json_encode($context)
        ]);
    }

    /**
     * CM-1 scope management
     */
    public function pushScope(string $scopeId): int {
        $this->cm1State['scopeStack'][] = $scopeId;
        return count($this->cm1State['scopeStack']);
    }

    public function popScope(): ?string {
        return array_pop($this->cm1State['scopeStack']);
    }

    public function getScopeDepth(): int {
        return count($this->cm1State['scopeStack']);
    }

    /**
     * Queue error for processing
     */
    public function queueError(
        string $errorType,
        string $errorMessage,
        ?string $brainId = null,
        ?int $taskId = null,
        array $context = []
    ): int {
        $stmt = $this->pdo->prepare(
            "INSERT INTO error_queue (brain_id, task_id, error_type, error_message, context)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $brainId,
            $taskId,
            $errorType,
            $errorMessage,
            json_encode($context)
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Process error queue
     */
    public function processErrorQueue(int $limit = 10): array {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM error_queue
             WHERE status = 'pending' AND retry_count < max_retries
             ORDER BY created_at ASC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        $errors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];

        foreach ($errors as $error) {
            // Mark as processing
            $this->pdo->prepare(
                "UPDATE error_queue SET status = 'processing', retry_count = retry_count + 1 WHERE id = ?"
            )->execute([$error['id']]);

            // Try to route to appropriate brain
            $brain = $this->selectBrain('runtime_orchestration');

            if ($brain) {
                $results[] = [
                    'error_id' => $error['id'],
                    'routed_to' => $brain['id'],
                    'error_type' => $error['error_type']
                ];

                // Mark for escalation if max retries reached
                if ($error['retry_count'] + 1 >= $error['max_retries']) {
                    $this->pdo->prepare(
                        "UPDATE error_queue SET status = 'escalated' WHERE id = ?"
                    )->execute([$error['id']]);
                }
            }
        }

        return $results;
    }

    /**
     * Reset all brain scores
     */
    public function resetScores(): void {
        $this->pdo->exec("UPDATE brain_scores SET score = 0.5, success_rate = 0.5, tier = 'standard', total_executions = 0, total_successes = 0, total_failures = 0");
        $this->pdo->exec("TRUNCATE TABLE brain_executions");
    }

    /**
     * Get health status
     */
    public function healthCheck(): array {
        $brains = $this->getAllBrains();
        $activeBrains = count(array_filter($brains, fn($b) => ($b['tier'] ?? 'standard') !== 'demoted'));

        return [
            'status' => $activeBrains > 0 ? 'healthy' : 'degraded',
            'total_brains' => count($brains),
            'active_brains' => $activeBrains,
            'cm1_session' => $this->cm1State['sessionId'],
            'cm1_phase' => $this->cm1State['currentPhase'],
            'scope_depth' => $this->getScopeDepth()
        ];
    }

    /**
     * Export state for persistence
     */
    public function exportState(): array {
        $stmt = $this->pdo->query(
            "SELECT * FROM brain_scores"
        );
        $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            '@context' => 'asx://brain-mesh/state/v2',
            '@version' => '2.0.0',
            '@exported_at' => date('c'),
            'scores' => $scores,
            'config' => [
                'explorationRate' => $this->explorationRate,
                'scoreWeights' => $this->scoreWeights,
                'promotionThreshold' => $this->promotionThreshold,
                'demotionThreshold' => $this->demotionThreshold
            ]
        ];
    }

    /**
     * Import state from backup
     */
    public function importState(array $state): bool {
        if (!isset($state['scores']) || !is_array($state['scores'])) {
            return false;
        }

        $this->pdo->beginTransaction();

        try {
            foreach ($state['scores'] as $score) {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO brain_scores (brain_id, domain, score, success_rate, avg_latency_ms, total_executions, total_successes, total_failures, tier)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE
                        score = VALUES(score),
                        success_rate = VALUES(success_rate),
                        avg_latency_ms = VALUES(avg_latency_ms),
                        total_executions = VALUES(total_executions),
                        total_successes = VALUES(total_successes),
                        total_failures = VALUES(total_failures),
                        tier = VALUES(tier)"
                );
                $stmt->execute([
                    $score['brain_id'],
                    $score['domain'],
                    $score['score'],
                    $score['success_rate'],
                    $score['avg_latency_ms'],
                    $score['total_executions'],
                    $score['total_successes'],
                    $score['total_failures'],
                    $score['tier']
                ]);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }
}
