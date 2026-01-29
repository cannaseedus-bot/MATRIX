<?php
/**
 * Brain Mesh Configuration
 *
 * Copy this file to config.php and fill in your credentials
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 * @version 2.1.0
 */

return [
    // ═══════════════════════════════════════════════════════════════════
    // DATABASE CONFIGURATION
    // ═══════════════════════════════════════════════════════════════════
    'db' => [
        'host' => 'localhost',
        'port' => '3306',
        'name' => 'brain_mesh',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4'
    ],

    // ═══════════════════════════════════════════════════════════════════
    // AUTHENTICATION
    // ═══════════════════════════════════════════════════════════════════
    'auth' => [
        // API key for programmatic access (X-API-KEY header)
        'api_key' => '',

        // Session secret for CSRF tokens
        'session_secret' => '',

        // JWT secret for token-based auth (optional)
        'jwt_secret' => '',

        // Admin keys (elevated permissions)
        'admin_keys' => [
            // 'admin-key-1',
            // 'admin-key-2',
        ],
    ],

    // ═══════════════════════════════════════════════════════════════════
    // LLM PROVIDERS - CLOUD SERVICES
    // ═══════════════════════════════════════════════════════════════════
    'llm' => [
        // ─────────────────────────────────────────────────────────────────
        // ANTHROPIC (Claude)
        // ─────────────────────────────────────────────────────────────────
        'anthropic' => [
            'enabled' => true,
            'api_key' => '',
            'base_url' => 'https://api.anthropic.com/v1',
            'models' => [
                'claude-opus-4-20250514' => [
                    'label' => 'Claude Opus 4',
                    'context_window' => 200000,
                    'max_tokens' => 32000,
                    'cost_per_1k_input' => 0.015,
                    'cost_per_1k_output' => 0.075,
                    'tier' => 'premium',
                ],
                'claude-sonnet-4-20250514' => [
                    'label' => 'Claude Sonnet 4',
                    'context_window' => 200000,
                    'max_tokens' => 64000,
                    'cost_per_1k_input' => 0.003,
                    'cost_per_1k_output' => 0.015,
                    'tier' => 'standard',
                ],
                'claude-haiku-4-20250514' => [
                    'label' => 'Claude Haiku 4',
                    'context_window' => 200000,
                    'max_tokens' => 8192,
                    'cost_per_1k_input' => 0.00025,
                    'cost_per_1k_output' => 0.00125,
                    'tier' => 'economy',
                ],
            ],
            'default_model' => 'claude-sonnet-4-20250514',
        ],

        // ─────────────────────────────────────────────────────────────────
        // OPENAI
        // ─────────────────────────────────────────────────────────────────
        'openai' => [
            'enabled' => false,
            'api_key' => '',
            'base_url' => 'https://api.openai.com/v1',
            'organization' => '',
            'models' => [
                'gpt-4o' => [
                    'label' => 'GPT-4o',
                    'context_window' => 128000,
                    'max_tokens' => 16384,
                    'cost_per_1k_input' => 0.005,
                    'cost_per_1k_output' => 0.015,
                    'tier' => 'premium',
                ],
                'gpt-4o-mini' => [
                    'label' => 'GPT-4o Mini',
                    'context_window' => 128000,
                    'max_tokens' => 16384,
                    'cost_per_1k_input' => 0.00015,
                    'cost_per_1k_output' => 0.0006,
                    'tier' => 'economy',
                ],
                'gpt-4-turbo' => [
                    'label' => 'GPT-4 Turbo',
                    'context_window' => 128000,
                    'max_tokens' => 4096,
                    'cost_per_1k_input' => 0.01,
                    'cost_per_1k_output' => 0.03,
                    'tier' => 'standard',
                ],
                'o1-preview' => [
                    'label' => 'o1 Preview',
                    'context_window' => 128000,
                    'max_tokens' => 32768,
                    'cost_per_1k_input' => 0.015,
                    'cost_per_1k_output' => 0.06,
                    'tier' => 'reasoning',
                ],
                'o1-mini' => [
                    'label' => 'o1 Mini',
                    'context_window' => 128000,
                    'max_tokens' => 65536,
                    'cost_per_1k_input' => 0.003,
                    'cost_per_1k_output' => 0.012,
                    'tier' => 'reasoning',
                ],
            ],
            'default_model' => 'gpt-4o',
        ],

        // ─────────────────────────────────────────────────────────────────
        // GOOGLE (Gemini)
        // ─────────────────────────────────────────────────────────────────
        'google' => [
            'enabled' => false,
            'api_key' => '',
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
            'models' => [
                'gemini-2.0-flash' => [
                    'label' => 'Gemini 2.0 Flash',
                    'context_window' => 1000000,
                    'max_tokens' => 8192,
                    'cost_per_1k_input' => 0.000075,
                    'cost_per_1k_output' => 0.0003,
                    'tier' => 'economy',
                ],
                'gemini-1.5-pro' => [
                    'label' => 'Gemini 1.5 Pro',
                    'context_window' => 2000000,
                    'max_tokens' => 8192,
                    'cost_per_1k_input' => 0.00125,
                    'cost_per_1k_output' => 0.005,
                    'tier' => 'standard',
                ],
                'gemini-1.5-flash' => [
                    'label' => 'Gemini 1.5 Flash',
                    'context_window' => 1000000,
                    'max_tokens' => 8192,
                    'cost_per_1k_input' => 0.000075,
                    'cost_per_1k_output' => 0.0003,
                    'tier' => 'economy',
                ],
            ],
            'default_model' => 'gemini-2.0-flash',
        ],

        // ─────────────────────────────────────────────────────────────────
        // MISTRAL AI
        // ─────────────────────────────────────────────────────────────────
        'mistral' => [
            'enabled' => false,
            'api_key' => '',
            'base_url' => 'https://api.mistral.ai/v1',
            'models' => [
                'mistral-large-latest' => [
                    'label' => 'Mistral Large',
                    'context_window' => 128000,
                    'max_tokens' => 8192,
                    'cost_per_1k_input' => 0.002,
                    'cost_per_1k_output' => 0.006,
                    'tier' => 'premium',
                ],
                'mistral-medium-latest' => [
                    'label' => 'Mistral Medium',
                    'context_window' => 32000,
                    'max_tokens' => 8192,
                    'cost_per_1k_input' => 0.0027,
                    'cost_per_1k_output' => 0.0081,
                    'tier' => 'standard',
                ],
                'mistral-small-latest' => [
                    'label' => 'Mistral Small',
                    'context_window' => 32000,
                    'max_tokens' => 8192,
                    'cost_per_1k_input' => 0.001,
                    'cost_per_1k_output' => 0.003,
                    'tier' => 'economy',
                ],
                'codestral-latest' => [
                    'label' => 'Codestral',
                    'context_window' => 32000,
                    'max_tokens' => 8192,
                    'cost_per_1k_input' => 0.001,
                    'cost_per_1k_output' => 0.003,
                    'tier' => 'code',
                ],
                'open-mixtral-8x22b' => [
                    'label' => 'Mixtral 8x22B',
                    'context_window' => 64000,
                    'max_tokens' => 8192,
                    'cost_per_1k_input' => 0.002,
                    'cost_per_1k_output' => 0.006,
                    'tier' => 'standard',
                ],
            ],
            'default_model' => 'mistral-large-latest',
        ],

        // ─────────────────────────────────────────────────────────────────
        // GROQ (Fast Inference)
        // ─────────────────────────────────────────────────────────────────
        'groq' => [
            'enabled' => false,
            'api_key' => '',
            'base_url' => 'https://api.groq.com/openai/v1',
            'models' => [
                'llama-3.3-70b-versatile' => [
                    'label' => 'Llama 3.3 70B',
                    'context_window' => 128000,
                    'max_tokens' => 32768,
                    'cost_per_1k_input' => 0.00059,
                    'cost_per_1k_output' => 0.00079,
                    'tier' => 'standard',
                ],
                'llama-3.1-8b-instant' => [
                    'label' => 'Llama 3.1 8B',
                    'context_window' => 128000,
                    'max_tokens' => 8192,
                    'cost_per_1k_input' => 0.00005,
                    'cost_per_1k_output' => 0.00008,
                    'tier' => 'economy',
                ],
                'mixtral-8x7b-32768' => [
                    'label' => 'Mixtral 8x7B',
                    'context_window' => 32768,
                    'max_tokens' => 8192,
                    'cost_per_1k_input' => 0.00024,
                    'cost_per_1k_output' => 0.00024,
                    'tier' => 'economy',
                ],
                'gemma2-9b-it' => [
                    'label' => 'Gemma 2 9B',
                    'context_window' => 8192,
                    'max_tokens' => 8192,
                    'cost_per_1k_input' => 0.0002,
                    'cost_per_1k_output' => 0.0002,
                    'tier' => 'economy',
                ],
            ],
            'default_model' => 'llama-3.3-70b-versatile',
        ],

        // ─────────────────────────────────────────────────────────────────
        // TOGETHER AI
        // ─────────────────────────────────────────────────────────────────
        'together' => [
            'enabled' => false,
            'api_key' => '',
            'base_url' => 'https://api.together.xyz/v1',
            'models' => [
                'meta-llama/Meta-Llama-3.1-405B-Instruct-Turbo' => [
                    'label' => 'Llama 3.1 405B',
                    'context_window' => 130000,
                    'max_tokens' => 4096,
                    'cost_per_1k_input' => 0.005,
                    'cost_per_1k_output' => 0.015,
                    'tier' => 'premium',
                ],
                'meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo' => [
                    'label' => 'Llama 3.1 70B',
                    'context_window' => 130000,
                    'max_tokens' => 4096,
                    'cost_per_1k_input' => 0.00088,
                    'cost_per_1k_output' => 0.00088,
                    'tier' => 'standard',
                ],
                'Qwen/Qwen2.5-72B-Instruct-Turbo' => [
                    'label' => 'Qwen 2.5 72B',
                    'context_window' => 32768,
                    'max_tokens' => 4096,
                    'cost_per_1k_input' => 0.0012,
                    'cost_per_1k_output' => 0.0012,
                    'tier' => 'standard',
                ],
                'deepseek-ai/DeepSeek-V3' => [
                    'label' => 'DeepSeek V3',
                    'context_window' => 64000,
                    'max_tokens' => 8192,
                    'cost_per_1k_input' => 0.0014,
                    'cost_per_1k_output' => 0.0028,
                    'tier' => 'standard',
                ],
            ],
            'default_model' => 'meta-llama/Meta-Llama-3.1-70B-Instruct-Turbo',
        ],

        // ─────────────────────────────────────────────────────────────────
        // DEEPSEEK
        // ─────────────────────────────────────────────────────────────────
        'deepseek' => [
            'enabled' => false,
            'api_key' => '',
            'base_url' => 'https://api.deepseek.com/v1',
            'models' => [
                'deepseek-chat' => [
                    'label' => 'DeepSeek Chat',
                    'context_window' => 64000,
                    'max_tokens' => 8192,
                    'cost_per_1k_input' => 0.00014,
                    'cost_per_1k_output' => 0.00028,
                    'tier' => 'economy',
                ],
                'deepseek-reasoner' => [
                    'label' => 'DeepSeek Reasoner',
                    'context_window' => 64000,
                    'max_tokens' => 8192,
                    'cost_per_1k_input' => 0.00055,
                    'cost_per_1k_output' => 0.00219,
                    'tier' => 'reasoning',
                ],
            ],
            'default_model' => 'deepseek-chat',
        ],

        // ─────────────────────────────────────────────────────────────────
        // XAI (Grok)
        // ─────────────────────────────────────────────────────────────────
        'xai' => [
            'enabled' => false,
            'api_key' => '',
            'base_url' => 'https://api.x.ai/v1',
            'models' => [
                'grok-2-latest' => [
                    'label' => 'Grok 2',
                    'context_window' => 131072,
                    'max_tokens' => 8192,
                    'cost_per_1k_input' => 0.002,
                    'cost_per_1k_output' => 0.01,
                    'tier' => 'standard',
                ],
                'grok-2-vision-latest' => [
                    'label' => 'Grok 2 Vision',
                    'context_window' => 32768,
                    'max_tokens' => 8192,
                    'cost_per_1k_input' => 0.002,
                    'cost_per_1k_output' => 0.01,
                    'tier' => 'vision',
                ],
            ],
            'default_model' => 'grok-2-latest',
        ],

        // ─────────────────────────────────────────────────────────────────
        // COHERE
        // ─────────────────────────────────────────────────────────────────
        'cohere' => [
            'enabled' => false,
            'api_key' => '',
            'base_url' => 'https://api.cohere.ai/v1',
            'models' => [
                'command-r-plus' => [
                    'label' => 'Command R+',
                    'context_window' => 128000,
                    'max_tokens' => 4096,
                    'cost_per_1k_input' => 0.003,
                    'cost_per_1k_output' => 0.015,
                    'tier' => 'premium',
                ],
                'command-r' => [
                    'label' => 'Command R',
                    'context_window' => 128000,
                    'max_tokens' => 4096,
                    'cost_per_1k_input' => 0.0005,
                    'cost_per_1k_output' => 0.0015,
                    'tier' => 'standard',
                ],
            ],
            'default_model' => 'command-r-plus',
        ],
    ],

    // ═══════════════════════════════════════════════════════════════════
    // OLLAMA - LOCAL & CLOUD
    // ═══════════════════════════════════════════════════════════════════
    'ollama' => [
        'enabled' => true,

        // Local Ollama instance
        'local' => [
            'enabled' => true,
            'base_url' => 'http://localhost:11434',
            'timeout' => 120,
        ],

        // Ollama Cloud (if using hosted service)
        'cloud' => [
            'enabled' => false,
            'base_url' => 'https://ollama.yourdomain.com',
            'api_key' => '',
        ],

        // Available models (pulled via `ollama pull <model>`)
        'models' => [
            // ─────────────────────────────────────────────────────────────
            // LLAMA FAMILY
            // ─────────────────────────────────────────────────────────────
            'llama3.2' => [
                'label' => 'Llama 3.2 3B',
                'size' => '2.0GB',
                'context_window' => 128000,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_light',
                'domains' => ['general', 'chat'],
            ],
            'llama3.2:1b' => [
                'label' => 'Llama 3.2 1B',
                'size' => '1.3GB',
                'context_window' => 128000,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_tiny',
                'domains' => ['general', 'chat'],
            ],
            'llama3.1' => [
                'label' => 'Llama 3.1 8B',
                'size' => '4.7GB',
                'context_window' => 128000,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_standard',
                'domains' => ['general', 'chat', 'reasoning'],
            ],
            'llama3.1:70b' => [
                'label' => 'Llama 3.1 70B',
                'size' => '40GB',
                'context_window' => 128000,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_premium',
                'domains' => ['general', 'reasoning', 'code'],
            ],

            // ─────────────────────────────────────────────────────────────
            // CODE MODELS
            // ─────────────────────────────────────────────────────────────
            'codellama' => [
                'label' => 'Code Llama 7B',
                'size' => '3.8GB',
                'context_window' => 16384,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_code',
                'domains' => ['code', 'programming'],
            ],
            'codellama:13b' => [
                'label' => 'Code Llama 13B',
                'size' => '7.4GB',
                'context_window' => 16384,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_code',
                'domains' => ['code', 'programming'],
            ],
            'codellama:34b' => [
                'label' => 'Code Llama 34B',
                'size' => '19GB',
                'context_window' => 16384,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_code_premium',
                'domains' => ['code', 'programming'],
            ],
            'deepseek-coder-v2' => [
                'label' => 'DeepSeek Coder V2',
                'size' => '8.9GB',
                'context_window' => 128000,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_code',
                'domains' => ['code', 'programming'],
            ],
            'qwen2.5-coder' => [
                'label' => 'Qwen 2.5 Coder 7B',
                'size' => '4.7GB',
                'context_window' => 32768,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_code',
                'domains' => ['code', 'programming'],
            ],
            'qwen2.5-coder:32b' => [
                'label' => 'Qwen 2.5 Coder 32B',
                'size' => '19GB',
                'context_window' => 32768,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_code_premium',
                'domains' => ['code', 'programming'],
            ],

            // ─────────────────────────────────────────────────────────────
            // MIXTRAL / MOE MODELS
            // ─────────────────────────────────────────────────────────────
            'mixtral' => [
                'label' => 'Mixtral 8x7B',
                'size' => '26GB',
                'context_window' => 32768,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_moe',
                'domains' => ['general', 'reasoning', 'code'],
            ],
            'mixtral:8x22b' => [
                'label' => 'Mixtral 8x22B',
                'size' => '80GB',
                'context_window' => 64000,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_moe_premium',
                'domains' => ['general', 'reasoning', 'code'],
            ],

            // ─────────────────────────────────────────────────────────────
            // PHI FAMILY (Microsoft)
            // ─────────────────────────────────────────────────────────────
            'phi3' => [
                'label' => 'Phi-3 Mini 3.8B',
                'size' => '2.3GB',
                'context_window' => 128000,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_light',
                'domains' => ['general', 'reasoning'],
            ],
            'phi3:medium' => [
                'label' => 'Phi-3 Medium 14B',
                'size' => '7.9GB',
                'context_window' => 128000,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_standard',
                'domains' => ['general', 'reasoning', 'code'],
            ],
            'phi3.5' => [
                'label' => 'Phi-3.5 Mini',
                'size' => '2.2GB',
                'context_window' => 128000,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_light',
                'domains' => ['general', 'reasoning'],
            ],
            'phi4' => [
                'label' => 'Phi-4 14B',
                'size' => '9.1GB',
                'context_window' => 16384,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_standard',
                'domains' => ['general', 'reasoning', 'math'],
            ],

            // ─────────────────────────────────────────────────────────────
            // QWEN FAMILY
            // ─────────────────────────────────────────────────────────────
            'qwen2.5' => [
                'label' => 'Qwen 2.5 7B',
                'size' => '4.7GB',
                'context_window' => 32768,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_standard',
                'domains' => ['general', 'multilingual'],
            ],
            'qwen2.5:14b' => [
                'label' => 'Qwen 2.5 14B',
                'size' => '9.0GB',
                'context_window' => 32768,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_standard',
                'domains' => ['general', 'multilingual'],
            ],
            'qwen2.5:32b' => [
                'label' => 'Qwen 2.5 32B',
                'size' => '20GB',
                'context_window' => 32768,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_premium',
                'domains' => ['general', 'multilingual', 'reasoning'],
            ],
            'qwen2.5:72b' => [
                'label' => 'Qwen 2.5 72B',
                'size' => '47GB',
                'context_window' => 32768,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_premium',
                'domains' => ['general', 'multilingual', 'reasoning'],
            ],

            // ─────────────────────────────────────────────────────────────
            // GEMMA FAMILY (Google)
            // ─────────────────────────────────────────────────────────────
            'gemma2' => [
                'label' => 'Gemma 2 9B',
                'size' => '5.4GB',
                'context_window' => 8192,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_standard',
                'domains' => ['general', 'chat'],
            ],
            'gemma2:27b' => [
                'label' => 'Gemma 2 27B',
                'size' => '16GB',
                'context_window' => 8192,
                'quantization' => 'Q4_K_M',
                'tier' => 'local_premium',
                'domains' => ['general', 'reasoning'],
            ],

            // ─────────────────────────────────────────────────────────────
            // SPECIALIZED MODELS
            // ─────────────────────────────────────────────────────────────
            'nomic-embed-text' => [
                'label' => 'Nomic Embed',
                'size' => '274MB',
                'context_window' => 8192,
                'quantization' => 'F16',
                'tier' => 'embedding',
                'domains' => ['embedding', 'search'],
            ],
            'mxbai-embed-large' => [
                'label' => 'MixedBread Embed',
                'size' => '670MB',
                'context_window' => 512,
                'quantization' => 'F16',
                'tier' => 'embedding',
                'domains' => ['embedding', 'search'],
            ],
            'llava' => [
                'label' => 'LLaVA 7B',
                'size' => '4.5GB',
                'context_window' => 4096,
                'quantization' => 'Q4_K_M',
                'tier' => 'vision',
                'domains' => ['vision', 'multimodal'],
            ],
            'llava:34b' => [
                'label' => 'LLaVA 34B',
                'size' => '20GB',
                'context_window' => 4096,
                'quantization' => 'Q4_K_M',
                'tier' => 'vision_premium',
                'domains' => ['vision', 'multimodal'],
            ],
        ],

        // Default model for local inference
        'default_model' => 'llama3.2',
    ],

    // ═══════════════════════════════════════════════════════════════════
    // LOCAL GGUF MODELS (Direct llama.cpp / llamafile)
    // ═══════════════════════════════════════════════════════════════════
    'gguf' => [
        'enabled' => false,

        // Path to GGUF model files
        'models_path' => '/models/gguf',

        // llama.cpp server settings
        'server' => [
            'host' => '127.0.0.1',
            'port' => 8080,
            'threads' => 4,
            'gpu_layers' => 0,  // 0 = CPU only, -1 = all layers on GPU
            'context_size' => 4096,
            'batch_size' => 512,
        ],

        // Registered GGUF models
        'models' => [
            'phi-3-mini-4k-instruct' => [
                'label' => 'Phi-3 Mini 4K GGUF',
                'file' => 'Phi-3-mini-4k-instruct-Q4_K_M.gguf',
                'size' => '2.4GB',
                'quantization' => 'Q4_K_M',
                'context_window' => 4096,
                'tier' => 'gguf_light',
            ],
            'phi-3-mini-128k-instruct' => [
                'label' => 'Phi-3 Mini 128K GGUF',
                'file' => 'Phi-3-mini-128k-instruct-Q4_K_M.gguf',
                'size' => '2.4GB',
                'quantization' => 'Q4_K_M',
                'context_window' => 128000,
                'tier' => 'gguf_light',
            ],
            'phi-3-medium-4k-instruct' => [
                'label' => 'Phi-3 Medium 4K GGUF',
                'file' => 'Phi-3-medium-4k-instruct-Q4_K_M.gguf',
                'size' => '8.6GB',
                'quantization' => 'Q4_K_M',
                'context_window' => 4096,
                'tier' => 'gguf_standard',
            ],
            'phi-3-medium-128k-instruct' => [
                'label' => 'Phi-3 Medium 128K GGUF',
                'file' => 'Phi-3-medium-128k-instruct-Q4_K_M.gguf',
                'size' => '8.6GB',
                'quantization' => 'Q4_K_M',
                'context_window' => 128000,
                'tier' => 'gguf_standard',
            ],
            'llama-3.2-3b-instruct' => [
                'label' => 'Llama 3.2 3B GGUF',
                'file' => 'Llama-3.2-3B-Instruct-Q4_K_M.gguf',
                'size' => '2.0GB',
                'quantization' => 'Q4_K_M',
                'context_window' => 128000,
                'tier' => 'gguf_light',
            ],
            'llama-3.2-1b-instruct' => [
                'label' => 'Llama 3.2 1B GGUF',
                'file' => 'Llama-3.2-1B-Instruct-Q8_0.gguf',
                'size' => '1.3GB',
                'quantization' => 'Q8_0',
                'context_window' => 128000,
                'tier' => 'gguf_tiny',
            ],
        ],

        'default_model' => 'phi-3-mini-4k-instruct',
    ],

    // ═══════════════════════════════════════════════════════════════════
    // ADAPTIVE BRAIN SCORING
    // ═══════════════════════════════════════════════════════════════════
    'adaptive' => [
        'exploration_rate' => 0.1,  // 10% exploration
        'score_weights' => [
            'success_rate' => 0.5,
            'speed' => 0.3,
            'consistency' => 0.2
        ],
        'promotion_threshold' => 0.85,
        'demotion_threshold' => 0.4,
        'rolling_window_size' => 50,
        'min_executions_for_tier' => 10,
    ],

    // ═══════════════════════════════════════════════════════════════════
    // CM-1 CONTROL SETTINGS
    // ═══════════════════════════════════════════════════════════════════
    'cm1' => [
        'enabled' => true,
        'phase_tracking' => true,
        'scope_management' => true,
        'audit_logging' => true,
    ],

    // ═══════════════════════════════════════════════════════════════════
    // TERMINAL PROJECTION SETTINGS
    // ═══════════════════════════════════════════════════════════════════
    'terminal' => [
        'enabled' => true,
        'max_output_length' => 65536,  // 64KB max output
        'command_timeout' => 30,        // seconds
        'history_size' => 100,          // commands per session
        'sandbox_root' => __DIR__ . '/../../',  // Project root
    ],

    // ═══════════════════════════════════════════════════════════════════
    // FILE EDITOR SETTINGS
    // ═══════════════════════════════════════════════════════════════════
    'editor' => [
        'enabled' => true,
        'allowed_extensions' => [
            'php', 'js', 'ts', 'json', 'xjson', 'khl',
            'html', 'css', 'scss', 'sql', 'md', 'txt',
            'yaml', 'yml', 'xml', 'svg', 'sh', 'bash'
        ],
        'forbidden_paths' => [
            'config.php',
            '.env',
            '.htaccess',
            'credentials',
        ],
        'max_file_size' => 1048576,  // 1MB
    ],

    // ═══════════════════════════════════════════════════════════════════
    // RATE LIMITING
    // ═══════════════════════════════════════════════════════════════════
    'rate_limit' => [
        'enabled' => true,
        'requests_per_minute' => 60,
        'burst_limit' => 10,
    ],

    // ═══════════════════════════════════════════════════════════════════
    // LOGGING
    // ═══════════════════════════════════════════════════════════════════
    'logging' => [
        'enabled' => true,
        'level' => 'info',  // debug, info, warning, error
        'file' => __DIR__ . '/logs/brain-mesh.log',
    ],

    // ═══════════════════════════════════════════════════════════════════
    // METADATA
    // ═══════════════════════════════════════════════════════════════════
    '@context' => 'asx://brain-mesh/config/v2.1',
    '@law' => 'ASX = XCFE = XJSON = KUHUL = AST = CM-1',
    'installed_at' => null,  // Set by installer
];
