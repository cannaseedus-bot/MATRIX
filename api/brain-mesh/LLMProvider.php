<?php
/**
 * LLM Provider - Unified Model Routing
 *
 * Routes requests to appropriate LLM providers (Cloud + Ollama + GGUF)
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 * @version 2.1.0
 */

class LLMProvider {
    private array $config;
    private ?PDO $pdo;

    public function __construct(array $config, ?PDO $pdo = null) {
        $this->config = $config;
        $this->pdo = $pdo;
    }

    /**
     * Get all available providers
     */
    public function getProviders(): array {
        $providers = [];

        // Cloud providers
        foreach (['anthropic', 'openai', 'google', 'mistral', 'groq', 'together', 'deepseek', 'xai', 'cohere'] as $name) {
            if (isset($this->config['llm'][$name]) && $this->config['llm'][$name]['enabled']) {
                $providers[$name] = [
                    'type' => 'cloud',
                    'name' => $name,
                    'models' => array_keys($this->config['llm'][$name]['models']),
                    'default' => $this->config['llm'][$name]['default_model'],
                ];
            }
        }

        // Ollama
        if (isset($this->config['ollama']) && $this->config['ollama']['enabled']) {
            $providers['ollama'] = [
                'type' => 'local',
                'name' => 'ollama',
                'models' => array_keys($this->config['ollama']['models']),
                'default' => $this->config['ollama']['default_model'],
                'local_enabled' => $this->config['ollama']['local']['enabled'] ?? false,
                'cloud_enabled' => $this->config['ollama']['cloud']['enabled'] ?? false,
            ];
        }

        // GGUF
        if (isset($this->config['gguf']) && $this->config['gguf']['enabled']) {
            $providers['gguf'] = [
                'type' => 'local',
                'name' => 'gguf',
                'models' => array_keys($this->config['gguf']['models']),
                'default' => $this->config['gguf']['default_model'],
            ];
        }

        return $providers;
    }

    /**
     * Get all models across all providers
     */
    public function getAllModels(): array {
        $models = [];

        // Cloud models
        foreach (['anthropic', 'openai', 'google', 'mistral', 'groq', 'together', 'deepseek', 'xai', 'cohere'] as $provider) {
            if (isset($this->config['llm'][$provider]['models'])) {
                foreach ($this->config['llm'][$provider]['models'] as $id => $model) {
                    $models[] = [
                        'id' => $id,
                        'provider' => $provider,
                        'type' => 'cloud',
                        'label' => $model['label'],
                        'tier' => $model['tier'],
                        'context_window' => $model['context_window'],
                        'enabled' => $this->config['llm'][$provider]['enabled'] ?? false,
                    ];
                }
            }
        }

        // Ollama models
        if (isset($this->config['ollama']['models'])) {
            foreach ($this->config['ollama']['models'] as $id => $model) {
                $models[] = [
                    'id' => $id,
                    'provider' => 'ollama',
                    'type' => 'local',
                    'label' => $model['label'],
                    'tier' => $model['tier'],
                    'size' => $model['size'],
                    'context_window' => $model['context_window'],
                    'domains' => $model['domains'] ?? [],
                    'enabled' => $this->config['ollama']['enabled'] ?? false,
                ];
            }
        }

        // GGUF models
        if (isset($this->config['gguf']['models'])) {
            foreach ($this->config['gguf']['models'] as $id => $model) {
                $models[] = [
                    'id' => $id,
                    'provider' => 'gguf',
                    'type' => 'local',
                    'label' => $model['label'],
                    'tier' => $model['tier'],
                    'size' => $model['size'],
                    'file' => $model['file'],
                    'context_window' => $model['context_window'],
                    'enabled' => $this->config['gguf']['enabled'] ?? false,
                ];
            }
        }

        return $models;
    }

    /**
     * Resolve model ID to provider info
     * Supports model@rig-id syntax for rig routing
     */
    public function resolveModel(string $modelId): ?array {
        // Check for model@rig-id syntax
        if (str_contains($modelId, '@')) {
            return $this->resolveRigModel($modelId);
        }

        // Check cloud providers
        foreach (['anthropic', 'openai', 'google', 'mistral', 'groq', 'together', 'deepseek', 'xai', 'cohere'] as $provider) {
            if (isset($this->config['llm'][$provider]['models'][$modelId])) {
                return [
                    'provider' => $provider,
                    'type' => 'cloud',
                    'model' => $modelId,
                    'config' => $this->config['llm'][$provider]['models'][$modelId],
                    'base_url' => $this->config['llm'][$provider]['base_url'],
                    'api_key' => $this->config['llm'][$provider]['api_key'],
                ];
            }
        }

        // Check Ollama
        if (isset($this->config['ollama']['models'][$modelId])) {
            $baseUrl = $this->config['ollama']['local']['enabled']
                ? $this->config['ollama']['local']['base_url']
                : $this->config['ollama']['cloud']['base_url'];

            return [
                'provider' => 'ollama',
                'type' => 'local',
                'model' => $modelId,
                'config' => $this->config['ollama']['models'][$modelId],
                'base_url' => $baseUrl,
                'api_key' => $this->config['ollama']['cloud']['api_key'] ?? null,
            ];
        }

        // Check GGUF
        if (isset($this->config['gguf']['models'][$modelId])) {
            return [
                'provider' => 'gguf',
                'type' => 'local',
                'model' => $modelId,
                'config' => $this->config['gguf']['models'][$modelId],
                'base_url' => sprintf(
                    'http://%s:%d',
                    $this->config['gguf']['server']['host'],
                    $this->config['gguf']['server']['port']
                ),
            ];
        }

        // Check if model exists on any online rig
        return $this->resolveRigModelAuto($modelId);
    }

    /**
     * Resolve model@rig-id syntax
     */
    private function resolveRigModel(string $modelSpec): ?array {
        [$model, $rigId] = explode('@', $modelSpec, 2);

        // Normalize rig ID
        if (!str_starts_with($rigId, 'rig-')) {
            $rigId = 'rig-' . $rigId;
        }

        return [
            'provider' => 'rig',
            'type' => 'federated',
            'model' => $model,
            'rig_id' => $rigId,
            'config' => [
                'context_window' => 4096,
                'max_tokens' => 4096,
            ],
        ];
    }

    /**
     * Auto-resolve model to best available rig
     */
    private function resolveRigModelAuto(string $model): ?array {
        if (!$this->pdo) {
            return null;
        }

        try {
            // Find online rig with this model
            $stmt = $this->pdo->prepare(
                "SELECT id, name, score FROM rigs
                 WHERE status = 'online' AND JSON_CONTAINS(models, ?)
                 ORDER BY score DESC LIMIT 1"
            );
            $stmt->execute([json_encode($model)]);
            $rig = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($rig) {
                return [
                    'provider' => 'rig',
                    'type' => 'federated',
                    'model' => $model,
                    'rig_id' => $rig['id'],
                    'rig_name' => $rig['name'],
                    'config' => [
                        'context_window' => 4096,
                        'max_tokens' => 4096,
                    ],
                ];
            }
        } catch (PDOException $e) {
            // Rigs table might not exist
        }

        return null;
    }

    /**
     * Select best model for domain (integrates with brain scoring)
     */
    public function selectModelForDomain(string $domain): ?array {
        // Get models matching domain
        $candidates = [];

        // Ollama models have domain tags
        if (isset($this->config['ollama']['models'])) {
            foreach ($this->config['ollama']['models'] as $id => $model) {
                if (isset($model['domains']) && in_array($domain, $model['domains'])) {
                    $candidates[] = [
                        'id' => $id,
                        'provider' => 'ollama',
                        'tier' => $model['tier'],
                        'score' => $this->getModelScore($id, $domain),
                    ];
                }
            }
        }

        // Cloud models by tier
        $domainTierMap = [
            'code' => ['code', 'standard'],
            'reasoning' => ['reasoning', 'premium'],
            'chat' => ['economy', 'standard'],
            'embedding' => ['embedding'],
            'vision' => ['vision', 'vision_premium'],
        ];

        $targetTiers = $domainTierMap[$domain] ?? ['standard'];

        foreach (['anthropic', 'openai', 'mistral', 'groq'] as $provider) {
            if (isset($this->config['llm'][$provider]['models'])) {
                foreach ($this->config['llm'][$provider]['models'] as $id => $model) {
                    if (in_array($model['tier'], $targetTiers)) {
                        $candidates[] = [
                            'id' => $id,
                            'provider' => $provider,
                            'tier' => $model['tier'],
                            'score' => $this->getModelScore($id, $domain),
                        ];
                    }
                }
            }
        }

        if (empty($candidates)) {
            return null;
        }

        // Sort by score descending
        usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);

        return $this->resolveModel($candidates[0]['id']);
    }

    /**
     * Get model score from database (if available)
     */
    private function getModelScore(string $modelId, string $domain): float {
        if (!$this->pdo) {
            return 0.5;  // Default score
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT score FROM brain_scores WHERE brain_id = ? AND domain = ?"
            );
            $stmt->execute([$modelId, $domain]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result ? (float)$result['score'] : 0.5;
        } catch (Exception $e) {
            return 0.5;
        }
    }

    /**
     * Send chat completion request
     */
    public function chat(string $modelId, array $messages, array $options = []): array {
        $resolved = $this->resolveModel($modelId);

        if (!$resolved) {
            return [
                'error' => 'Model not found: ' . $modelId,
                'success' => false,
            ];
        }

        $startTime = microtime(true);

        try {
            $response = match($resolved['provider']) {
                'anthropic' => $this->chatAnthropic($resolved, $messages, $options),
                'openai', 'groq', 'together', 'deepseek', 'xai', 'mistral' => $this->chatOpenAICompatible($resolved, $messages, $options),
                'google' => $this->chatGoogle($resolved, $messages, $options),
                'cohere' => $this->chatCohere($resolved, $messages, $options),
                'ollama' => $this->chatOllama($resolved, $messages, $options),
                'gguf' => $this->chatGGUF($resolved, $messages, $options),
                'rig' => $this->chatRig($resolved, $messages, $options),
                default => ['error' => 'Unknown provider: ' . $resolved['provider'], 'success' => false],
            };

            $response['latency_ms'] = (int)((microtime(true) - $startTime) * 1000);
            $response['model'] = $modelId;
            $response['provider'] = $resolved['provider'];

            return $response;

        } catch (Exception $e) {
            return [
                'error' => $e->getMessage(),
                'success' => false,
                'latency_ms' => (int)((microtime(true) - $startTime) * 1000),
                'model' => $modelId,
                'provider' => $resolved['provider'],
            ];
        }
    }

    /**
     * Anthropic API (Claude)
     */
    private function chatAnthropic(array $resolved, array $messages, array $options): array {
        $systemMessage = '';
        $filteredMessages = [];

        foreach ($messages as $msg) {
            if ($msg['role'] === 'system') {
                $systemMessage .= $msg['content'] . "\n";
            } else {
                $filteredMessages[] = $msg;
            }
        }

        $payload = [
            'model' => $resolved['model'],
            'messages' => $filteredMessages,
            'max_tokens' => $options['max_tokens'] ?? $resolved['config']['max_tokens'] ?? 4096,
        ];

        if ($systemMessage) {
            $payload['system'] = trim($systemMessage);
        }

        $response = $this->httpPost(
            $resolved['base_url'] . '/messages',
            $payload,
            [
                'x-api-key: ' . $resolved['api_key'],
                'anthropic-version: 2023-06-01',
                'Content-Type: application/json',
            ]
        );

        if (isset($response['error'])) {
            return ['error' => $response['error']['message'] ?? 'Unknown error', 'success' => false];
        }

        return [
            'success' => true,
            'content' => $response['content'][0]['text'] ?? '',
            'usage' => [
                'input_tokens' => $response['usage']['input_tokens'] ?? 0,
                'output_tokens' => $response['usage']['output_tokens'] ?? 0,
            ],
            'stop_reason' => $response['stop_reason'] ?? null,
        ];
    }

    /**
     * OpenAI-compatible API (OpenAI, Groq, Together, DeepSeek, xAI, Mistral)
     */
    private function chatOpenAICompatible(array $resolved, array $messages, array $options): array {
        $payload = [
            'model' => $resolved['model'],
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? $resolved['config']['max_tokens'] ?? 4096,
        ];

        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }

        $headers = [
            'Authorization: Bearer ' . $resolved['api_key'],
            'Content-Type: application/json',
        ];

        $response = $this->httpPost(
            $resolved['base_url'] . '/chat/completions',
            $payload,
            $headers
        );

        if (isset($response['error'])) {
            return ['error' => $response['error']['message'] ?? 'Unknown error', 'success' => false];
        }

        return [
            'success' => true,
            'content' => $response['choices'][0]['message']['content'] ?? '',
            'usage' => [
                'input_tokens' => $response['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $response['usage']['completion_tokens'] ?? 0,
            ],
            'stop_reason' => $response['choices'][0]['finish_reason'] ?? null,
        ];
    }

    /**
     * Google Gemini API
     */
    private function chatGoogle(array $resolved, array $messages, array $options): array {
        // Convert to Gemini format
        $contents = [];
        foreach ($messages as $msg) {
            $role = $msg['role'] === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $msg['content']]],
            ];
        }

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'maxOutputTokens' => $options['max_tokens'] ?? $resolved['config']['max_tokens'] ?? 4096,
            ],
        ];

        $url = sprintf(
            '%s/models/%s:generateContent?key=%s',
            $resolved['base_url'],
            $resolved['model'],
            $resolved['api_key']
        );

        $response = $this->httpPost($url, $payload, ['Content-Type: application/json']);

        if (isset($response['error'])) {
            return ['error' => $response['error']['message'] ?? 'Unknown error', 'success' => false];
        }

        return [
            'success' => true,
            'content' => $response['candidates'][0]['content']['parts'][0]['text'] ?? '',
            'usage' => [
                'input_tokens' => $response['usageMetadata']['promptTokenCount'] ?? 0,
                'output_tokens' => $response['usageMetadata']['candidatesTokenCount'] ?? 0,
            ],
            'stop_reason' => $response['candidates'][0]['finishReason'] ?? null,
        ];
    }

    /**
     * Cohere API
     */
    private function chatCohere(array $resolved, array $messages, array $options): array {
        // Convert to Cohere format
        $chatHistory = [];
        $lastMessage = '';

        foreach ($messages as $i => $msg) {
            if ($i === count($messages) - 1 && $msg['role'] === 'user') {
                $lastMessage = $msg['content'];
            } else {
                $chatHistory[] = [
                    'role' => $msg['role'] === 'assistant' ? 'CHATBOT' : 'USER',
                    'message' => $msg['content'],
                ];
            }
        }

        $payload = [
            'model' => $resolved['model'],
            'message' => $lastMessage,
            'chat_history' => $chatHistory,
        ];

        $response = $this->httpPost(
            $resolved['base_url'] . '/chat',
            $payload,
            [
                'Authorization: Bearer ' . $resolved['api_key'],
                'Content-Type: application/json',
            ]
        );

        if (isset($response['error'])) {
            return ['error' => $response['message'] ?? 'Unknown error', 'success' => false];
        }

        return [
            'success' => true,
            'content' => $response['text'] ?? '',
            'usage' => [
                'input_tokens' => $response['meta']['billed_units']['input_tokens'] ?? 0,
                'output_tokens' => $response['meta']['billed_units']['output_tokens'] ?? 0,
            ],
            'stop_reason' => $response['finish_reason'] ?? null,
        ];
    }

    /**
     * Ollama API
     */
    private function chatOllama(array $resolved, array $messages, array $options): array {
        $payload = [
            'model' => $resolved['model'],
            'messages' => $messages,
            'stream' => false,
            'options' => [
                'num_ctx' => $options['context_length'] ?? $resolved['config']['context_window'] ?? 4096,
            ],
        ];

        $headers = ['Content-Type: application/json'];
        if (!empty($resolved['api_key'])) {
            $headers[] = 'Authorization: Bearer ' . $resolved['api_key'];
        }

        $response = $this->httpPost(
            $resolved['base_url'] . '/api/chat',
            $payload,
            $headers,
            $this->config['ollama']['local']['timeout'] ?? 120
        );

        if (isset($response['error'])) {
            return ['error' => $response['error'], 'success' => false];
        }

        return [
            'success' => true,
            'content' => $response['message']['content'] ?? '',
            'usage' => [
                'input_tokens' => $response['prompt_eval_count'] ?? 0,
                'output_tokens' => $response['eval_count'] ?? 0,
            ],
            'stop_reason' => $response['done_reason'] ?? 'stop',
        ];
    }

    /**
     * GGUF via llama.cpp server
     */
    private function chatGGUF(array $resolved, array $messages, array $options): array {
        // llama.cpp server uses OpenAI-compatible endpoint
        $payload = [
            'messages' => $messages,
            'max_tokens' => $options['max_tokens'] ?? $resolved['config']['context_window'] ?? 4096,
        ];

        $response = $this->httpPost(
            $resolved['base_url'] . '/v1/chat/completions',
            $payload,
            ['Content-Type: application/json']
        );

        if (isset($response['error'])) {
            return ['error' => $response['error']['message'] ?? 'Unknown error', 'success' => false];
        }

        return [
            'success' => true,
            'content' => $response['choices'][0]['message']['content'] ?? '',
            'usage' => [
                'input_tokens' => $response['usage']['prompt_tokens'] ?? 0,
                'output_tokens' => $response['usage']['completion_tokens'] ?? 0,
            ],
            'stop_reason' => $response['choices'][0]['finish_reason'] ?? null,
        ];
    }

    /**
     * Federated RIG via rig-router.php
     * Routes request to a remote rig running Ollama
     */
    private function chatRig(array $resolved, array $messages, array $options): array {
        $rigId = $resolved['rig_id'];
        $model = $resolved['model'];

        // Build prompt from messages
        $prompt = '';
        foreach ($messages as $msg) {
            $role = $msg['role'];
            $content = $msg['content'];
            if ($role === 'system') {
                $prompt .= "[System]\n$content\n\n";
            } elseif ($role === 'user') {
                $prompt .= "[User]\n$content\n\n";
            } elseif ($role === 'assistant') {
                $prompt .= "[Assistant]\n$content\n\n";
            }
        }

        // Queue request via rig-router
        $rigRouterUrl = $this->getRigRouterUrl();

        $queueResponse = $this->httpPost(
            $rigRouterUrl . '?action=request',
            [
                'model' => $model,
                'prompt' => trim($prompt),
                'rigId' => $rigId,
                'options' => $options,
            ],
            ['Content-Type: application/json']
        );

        if (!($queueResponse['success'] ?? false)) {
            return [
                'error' => $queueResponse['error'] ?? 'Failed to queue rig request',
                'success' => false,
            ];
        }

        $requestId = $queueResponse['requestId'];
        $actualRigId = $queueResponse['rigId'];

        // Wait for response (long-poll)
        $timeout = $options['timeout'] ?? 60;
        $waitResponse = $this->httpPost(
            $rigRouterUrl . "?action=wait&requestId=$requestId&timeout=$timeout",
            [],
            ['Content-Type: application/json'],
            $timeout + 5
        );

        if (!($waitResponse['success'] ?? false)) {
            return [
                'error' => $waitResponse['error'] ?? 'Rig request timed out',
                'success' => false,
                'rig_id' => $actualRigId,
                'request_id' => $requestId,
            ];
        }

        return [
            'success' => true,
            'content' => $waitResponse['response'] ?? '',
            'usage' => [
                'input_tokens' => 0, // Rigs don't report token usage yet
                'output_tokens' => 0,
            ],
            'stop_reason' => 'stop',
            'rig_id' => $actualRigId,
            'rig_latency' => $waitResponse['latency'] ?? 0,
        ];
    }

    /**
     * Get rig router URL
     */
    private function getRigRouterUrl(): string {
        // Try config first
        if (isset($this->config['rig']['router_url'])) {
            return $this->config['rig']['router_url'];
        }

        // Default to same server
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return "$protocol://$host/api/brain-mesh/rig-router.php";
    }

    /**
     * HTTP POST helper
     */
    private function httpPost(string $url, array $data, array $headers = [], int $timeout = 60): array {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['error' => $error];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            return ['error' => $decoded ?? ['message' => 'HTTP ' . $httpCode]];
        }

        return $decoded ?? [];
    }

    /**
     * Check Ollama connection
     */
    public function checkOllamaConnection(): array {
        if (!$this->config['ollama']['enabled']) {
            return ['connected' => false, 'reason' => 'Ollama disabled in config'];
        }

        $baseUrl = $this->config['ollama']['local']['enabled']
            ? $this->config['ollama']['local']['base_url']
            : $this->config['ollama']['cloud']['base_url'];

        $ch = curl_init($baseUrl . '/api/tags');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return ['connected' => false, 'reason' => 'HTTP ' . $httpCode];
        }

        $data = json_decode($response, true);

        return [
            'connected' => true,
            'models' => array_column($data['models'] ?? [], 'name'),
        ];
    }

    /**
     * List pulled Ollama models
     */
    public function listOllamaModels(): array {
        $check = $this->checkOllamaConnection();

        if (!$check['connected']) {
            return [];
        }

        return $check['models'] ?? [];
    }

    /**
     * Generate embeddings
     */
    public function embed(string $modelId, array $texts): array {
        $resolved = $this->resolveModel($modelId);

        if (!$resolved) {
            return ['error' => 'Model not found: ' . $modelId, 'success' => false];
        }

        if ($resolved['provider'] === 'ollama') {
            return $this->embedOllama($resolved, $texts);
        }

        if ($resolved['provider'] === 'openai') {
            return $this->embedOpenAI($resolved, $texts);
        }

        return ['error' => 'Embeddings not supported for provider: ' . $resolved['provider'], 'success' => false];
    }

    private function embedOllama(array $resolved, array $texts): array {
        $embeddings = [];

        foreach ($texts as $text) {
            $response = $this->httpPost(
                $resolved['base_url'] . '/api/embeddings',
                ['model' => $resolved['model'], 'prompt' => $text],
                ['Content-Type: application/json']
            );

            if (isset($response['embedding'])) {
                $embeddings[] = $response['embedding'];
            }
        }

        return [
            'success' => true,
            'embeddings' => $embeddings,
            'model' => $resolved['model'],
        ];
    }

    private function embedOpenAI(array $resolved, array $texts): array {
        $response = $this->httpPost(
            $resolved['base_url'] . '/embeddings',
            ['model' => $resolved['model'], 'input' => $texts],
            [
                'Authorization: Bearer ' . $resolved['api_key'],
                'Content-Type: application/json',
            ]
        );

        if (isset($response['error'])) {
            return ['error' => $response['error']['message'] ?? 'Unknown error', 'success' => false];
        }

        return [
            'success' => true,
            'embeddings' => array_column($response['data'] ?? [], 'embedding'),
            'model' => $resolved['model'],
        ];
    }
}
