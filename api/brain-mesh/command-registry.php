<?php
/**
 * Command Registry - Allowlist for Terminal Projection
 *
 * Deny-by-default command validation for secure terminal execution
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 * @version 2.1.0
 */

/**
 * Command Registry Configuration
 *
 * Structure:
 * - namespace: Command prefix (e.g., 'brain-mesh', 'file', 'llm')
 * - commands: Array of allowed subcommands with metadata
 * - admin_only: Commands requiring elevated permissions
 * - forbidden: Explicitly blocked patterns
 */
$COMMAND_REGISTRY = [

    // ═══════════════════════════════════════════════════════════════════
    // BRAIN-MESH COMMANDS
    // ═══════════════════════════════════════════════════════════════════
    'brain-mesh' => [
        'description' => 'Brain Mesh orchestrator commands',
        'commands' => [
            'brains' => [
                'description' => 'List all registered brains',
                'usage' => 'brain-mesh brains',
                'handler' => 'handleBrains',
            ],
            'leaderboard' => [
                'description' => 'Show brain performance rankings',
                'usage' => 'brain-mesh leaderboard [--domain=<domain>]',
                'aliases' => ['lb'],
                'handler' => 'handleLeaderboard',
            ],
            'stats' => [
                'description' => 'Show adaptive routing statistics',
                'usage' => 'brain-mesh stats',
                'aliases' => ['adaptive'],
                'handler' => 'handleStats',
            ],
            'health' => [
                'description' => 'Health check',
                'usage' => 'brain-mesh health',
                'handler' => 'handleHealth',
            ],
            'select' => [
                'description' => 'Select best brain for domain',
                'usage' => 'brain-mesh select <domain>',
                'handler' => 'handleSelect',
            ],
            'best' => [
                'description' => 'Show best brain for domain',
                'usage' => 'brain-mesh best <domain>',
                'handler' => 'handleBest',
            ],
            'status' => [
                'description' => 'Show runtime status',
                'usage' => 'brain-mesh status',
                'handler' => 'handleStatus',
            ],
        ],
        'admin_only' => [
            'reset' => [
                'description' => 'Reset all brain scores',
                'usage' => 'brain-mesh reset',
                'handler' => 'handleReset',
                'dangerous' => true,
            ],
            'save' => [
                'description' => 'Save brain state to file',
                'usage' => 'brain-mesh save [filepath]',
                'handler' => 'handleSave',
            ],
            'load' => [
                'description' => 'Load brain state from file',
                'usage' => 'brain-mesh load [filepath]',
                'handler' => 'handleLoad',
            ],
        ],
    ],

    // ═══════════════════════════════════════════════════════════════════
    // LLM COMMANDS
    // ═══════════════════════════════════════════════════════════════════
    'llm' => [
        'description' => 'LLM provider management',
        'commands' => [
            'providers' => [
                'description' => 'List available LLM providers',
                'usage' => 'llm providers',
                'handler' => 'handleLLMProviders',
            ],
            'models' => [
                'description' => 'List available models',
                'usage' => 'llm models [--provider=<name>] [--tier=<tier>]',
                'handler' => 'handleLLMModels',
            ],
            'chat' => [
                'description' => 'Send chat completion request',
                'usage' => 'llm chat <model> "<message>"',
                'handler' => 'handleLLMChat',
            ],
            'test' => [
                'description' => 'Test model connectivity',
                'usage' => 'llm test <model>',
                'handler' => 'handleLLMTest',
            ],
        ],
        'admin_only' => [],
    ],

    // ═══════════════════════════════════════════════════════════════════
    // OLLAMA COMMANDS
    // ═══════════════════════════════════════════════════════════════════
    'ollama' => [
        'description' => 'Ollama local model management',
        'commands' => [
            'list' => [
                'description' => 'List pulled models',
                'usage' => 'ollama list',
                'handler' => 'handleOllamaList',
            ],
            'status' => [
                'description' => 'Check Ollama connection',
                'usage' => 'ollama status',
                'handler' => 'handleOllamaStatus',
            ],
        ],
        'admin_only' => [
            'pull' => [
                'description' => 'Pull a model',
                'usage' => 'ollama pull <model>',
                'handler' => 'handleOllamaPull',
            ],
            'delete' => [
                'description' => 'Delete a model',
                'usage' => 'ollama delete <model>',
                'handler' => 'handleOllamaDelete',
                'dangerous' => true,
            ],
        ],
    ],

    // ═══════════════════════════════════════════════════════════════════
    // FILE COMMANDS
    // ═══════════════════════════════════════════════════════════════════
    'file' => [
        'description' => 'File system operations (sandboxed)',
        'commands' => [
            'read' => [
                'description' => 'Read file contents',
                'usage' => 'file read <path>',
                'handler' => 'handleFileRead',
            ],
            'list' => [
                'description' => 'List directory contents',
                'usage' => 'file list <path>',
                'aliases' => ['ls'],
                'handler' => 'handleFileList',
            ],
            'info' => [
                'description' => 'Get file metadata',
                'usage' => 'file info <path>',
                'handler' => 'handleFileInfo',
            ],
            'search' => [
                'description' => 'Search files by pattern',
                'usage' => 'file search <pattern> [--path=<dir>]',
                'handler' => 'handleFileSearch',
            ],
            'tree' => [
                'description' => 'Show directory tree',
                'usage' => 'file tree [path] [--depth=<n>]',
                'handler' => 'handleFileTree',
            ],
        ],
        'admin_only' => [
            'write' => [
                'description' => 'Write file contents',
                'usage' => 'file write <path> <content>',
                'handler' => 'handleFileWrite',
            ],
            'create' => [
                'description' => 'Create new file',
                'usage' => 'file create <path>',
                'handler' => 'handleFileCreate',
            ],
            'mkdir' => [
                'description' => 'Create directory',
                'usage' => 'file mkdir <path>',
                'handler' => 'handleFileMkdir',
            ],
            'delete' => [
                'description' => 'Delete file',
                'usage' => 'file delete <path>',
                'handler' => 'handleFileDelete',
                'dangerous' => true,
            ],
            'move' => [
                'description' => 'Move/rename file',
                'usage' => 'file move <source> <dest>',
                'handler' => 'handleFileMove',
            ],
            'copy' => [
                'description' => 'Copy file',
                'usage' => 'file copy <source> <dest>',
                'handler' => 'handleFileCopy',
            ],
        ],
    ],

    // ═══════════════════════════════════════════════════════════════════
    // CM-1 COMMANDS
    // ═══════════════════════════════════════════════════════════════════
    'cm1' => [
        'description' => 'CM-1 phase control',
        'commands' => [
            'phase' => [
                'description' => 'Get current CM-1 phase',
                'usage' => 'cm1 phase',
                'handler' => 'handleCM1Phase',
            ],
            'history' => [
                'description' => 'Show phase transition history',
                'usage' => 'cm1 history [--limit=<n>]',
                'handler' => 'handleCM1History',
            ],
            'scope' => [
                'description' => 'Get current scope stack',
                'usage' => 'cm1 scope',
                'handler' => 'handleCM1Scope',
            ],
        ],
        'admin_only' => [
            'transition' => [
                'description' => 'Force phase transition',
                'usage' => 'cm1 transition <from> <to>',
                'handler' => 'handleCM1Transition',
            ],
            'push' => [
                'description' => 'Push scope',
                'usage' => 'cm1 push <scope_id>',
                'handler' => 'handleCM1Push',
            ],
            'pop' => [
                'description' => 'Pop scope',
                'usage' => 'cm1 pop',
                'handler' => 'handleCM1Pop',
            ],
        ],
    ],

    // ═══════════════════════════════════════════════════════════════════
    // HELP COMMANDS
    // ═══════════════════════════════════════════════════════════════════
    'help' => [
        'description' => 'Help and documentation',
        'commands' => [
            '' => [
                'description' => 'Show all commands',
                'usage' => 'help',
                'handler' => 'handleHelp',
            ],
            'search' => [
                'description' => 'Search commands',
                'usage' => 'help search <query>',
                'handler' => 'handleHelpSearch',
            ],
        ],
        'admin_only' => [],
    ],

    // ═══════════════════════════════════════════════════════════════════
    // SYSTEM COMMANDS
    // ═══════════════════════════════════════════════════════════════════
    'system' => [
        'description' => 'System information',
        'commands' => [
            'info' => [
                'description' => 'Show system information',
                'usage' => 'system info',
                'handler' => 'handleSystemInfo',
            ],
            'version' => [
                'description' => 'Show version',
                'usage' => 'system version',
                'handler' => 'handleSystemVersion',
            ],
        ],
        'admin_only' => [
            'config' => [
                'description' => 'Show config (redacted)',
                'usage' => 'system config',
                'handler' => 'handleSystemConfig',
            ],
        ],
    ],

    // ═══════════════════════════════════════════════════════════════════
    // TASK COMMANDS
    // ═══════════════════════════════════════════════════════════════════
    'task' => [
        'description' => 'Task queue management',
        'commands' => [
            'list' => [
                'description' => 'List tasks',
                'usage' => 'task list [--status=<status>] [--limit=<n>]',
                'handler' => 'handleTaskList',
            ],
            'get' => [
                'description' => 'Get task details',
                'usage' => 'task get <id>',
                'handler' => 'handleTaskGet',
            ],
            'status' => [
                'description' => 'Get task status',
                'usage' => 'task status <id>',
                'handler' => 'handleTaskStatus',
            ],
        ],
        'admin_only' => [
            'add' => [
                'description' => 'Add new task',
                'usage' => 'task add "<command>" [--agent=<agent>]',
                'handler' => 'handleTaskAdd',
            ],
            'cancel' => [
                'description' => 'Cancel task',
                'usage' => 'task cancel <id>',
                'handler' => 'handleTaskCancel',
            ],
        ],
    ],
];

/**
 * Explicitly forbidden commands/patterns
 */
$FORBIDDEN_PATTERNS = [
    // Shell execution
    '/^\s*sh\s/',
    '/^\s*bash\s/',
    '/^\s*zsh\s/',
    '/^\s*exec\s/',
    '/^\s*eval\s/',

    // System commands
    '/^\s*rm\s+-rf/',
    '/^\s*sudo\s/',
    '/^\s*su\s/',
    '/^\s*chmod\s/',
    '/^\s*chown\s/',

    // Network
    '/^\s*curl\s/',
    '/^\s*wget\s/',
    '/^\s*nc\s/',
    '/^\s*netcat\s/',

    // Package managers
    '/^\s*apt\s/',
    '/^\s*yum\s/',
    '/^\s*npm\s/',
    '/^\s*pip\s/',
    '/^\s*composer\s/',

    // Database direct access
    '/^\s*mysql\s/',
    '/^\s*psql\s/',
    '/^\s*mongo\s/',

    // Dangerous PHP
    '/^\s*php\s+-r/',
    '/system\s*\(/',
    '/exec\s*\(/',
    '/passthru\s*\(/',
    '/shell_exec\s*\(/',
    '/`.*`/',  // Backtick execution
];

/**
 * Command Registry Class
 */
class CommandRegistry {
    private array $registry;
    private array $forbidden;
    private bool $isAdmin;

    public function __construct(array $registry, array $forbidden, bool $isAdmin = false) {
        $this->registry = $registry;
        $this->forbidden = $forbidden;
        $this->isAdmin = $isAdmin;
    }

    /**
     * Parse command string into namespace, command, and args
     */
    public function parse(string $input): array {
        $input = trim($input);

        // Check forbidden patterns
        foreach ($this->forbidden as $pattern) {
            if (preg_match($pattern, $input)) {
                return [
                    'valid' => false,
                    'error' => 'Command pattern is forbidden',
                    'matched_pattern' => $pattern,
                ];
            }
        }

        // Split command
        $parts = preg_split('/\s+/', $input, 3);
        $namespace = $parts[0] ?? '';
        $subcommand = $parts[1] ?? '';
        $args = $parts[2] ?? '';

        // Check if namespace exists
        if (!isset($this->registry[$namespace])) {
            return [
                'valid' => false,
                'error' => 'Unknown command namespace: ' . $namespace,
                'suggestion' => $this->suggestNamespace($namespace),
            ];
        }

        $ns = $this->registry[$namespace];

        // Check allowed commands
        if (isset($ns['commands'][$subcommand])) {
            return [
                'valid' => true,
                'namespace' => $namespace,
                'command' => $subcommand,
                'args' => $this->parseArgs($args),
                'handler' => $ns['commands'][$subcommand]['handler'],
                'admin_required' => false,
            ];
        }

        // Check aliases
        foreach ($ns['commands'] as $cmd => $meta) {
            if (isset($meta['aliases']) && in_array($subcommand, $meta['aliases'])) {
                return [
                    'valid' => true,
                    'namespace' => $namespace,
                    'command' => $cmd,
                    'args' => $this->parseArgs($args),
                    'handler' => $meta['handler'],
                    'admin_required' => false,
                ];
            }
        }

        // Check admin commands
        if (isset($ns['admin_only'][$subcommand])) {
            if (!$this->isAdmin) {
                return [
                    'valid' => false,
                    'error' => 'Admin permission required for: ' . $namespace . ' ' . $subcommand,
                    'admin_required' => true,
                ];
            }

            return [
                'valid' => true,
                'namespace' => $namespace,
                'command' => $subcommand,
                'args' => $this->parseArgs($args),
                'handler' => $ns['admin_only'][$subcommand]['handler'],
                'admin_required' => true,
                'dangerous' => $ns['admin_only'][$subcommand]['dangerous'] ?? false,
            ];
        }

        // Handle namespace-only command (e.g., "help")
        if (empty($subcommand) && isset($ns['commands'][''])) {
            return [
                'valid' => true,
                'namespace' => $namespace,
                'command' => '',
                'args' => [],
                'handler' => $ns['commands']['']['handler'],
                'admin_required' => false,
            ];
        }

        return [
            'valid' => false,
            'error' => 'Unknown command: ' . $namespace . ' ' . $subcommand,
            'suggestion' => $this->suggestCommand($namespace, $subcommand),
        ];
    }

    /**
     * Parse argument string into structured array
     */
    private function parseArgs(string $args): array {
        $result = [
            'positional' => [],
            'flags' => [],
        ];

        if (empty($args)) {
            return $result;
        }

        // Match quoted strings and flags
        preg_match_all('/--(\w+)=(?:"([^"]*)"|(\S+))|--(\w+)|"([^"]*)"|(\S+)/', $args, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (!empty($match[1])) {
                // --flag=value
                $result['flags'][$match[1]] = $match[2] ?: $match[3];
            } elseif (!empty($match[4])) {
                // --flag (boolean)
                $result['flags'][$match[4]] = true;
            } elseif (!empty($match[5])) {
                // "quoted string"
                $result['positional'][] = $match[5];
            } elseif (!empty($match[6])) {
                // unquoted word
                $result['positional'][] = $match[6];
            }
        }

        return $result;
    }

    /**
     * Suggest similar namespace
     */
    private function suggestNamespace(string $input): ?string {
        $namespaces = array_keys($this->registry);
        return $this->findClosest($input, $namespaces);
    }

    /**
     * Suggest similar command
     */
    private function suggestCommand(string $namespace, string $input): ?string {
        $commands = array_merge(
            array_keys($this->registry[$namespace]['commands'] ?? []),
            array_keys($this->registry[$namespace]['admin_only'] ?? [])
        );
        return $this->findClosest($input, $commands);
    }

    /**
     * Find closest match using Levenshtein distance
     */
    private function findClosest(string $input, array $options): ?string {
        $closest = null;
        $minDist = PHP_INT_MAX;

        foreach ($options as $option) {
            $dist = levenshtein($input, $option);
            if ($dist < $minDist && $dist <= 3) {  // Max 3 edits
                $minDist = $dist;
                $closest = $option;
            }
        }

        return $closest;
    }

    /**
     * Get all available commands
     */
    public function getAllCommands(): array {
        $commands = [];

        foreach ($this->registry as $namespace => $ns) {
            foreach ($ns['commands'] as $cmd => $meta) {
                $commands[] = [
                    'namespace' => $namespace,
                    'command' => $cmd,
                    'full' => trim($namespace . ' ' . $cmd),
                    'description' => $meta['description'],
                    'usage' => $meta['usage'],
                    'admin' => false,
                ];
            }

            if ($this->isAdmin) {
                foreach ($ns['admin_only'] as $cmd => $meta) {
                    $commands[] = [
                        'namespace' => $namespace,
                        'command' => $cmd,
                        'full' => trim($namespace . ' ' . $cmd),
                        'description' => $meta['description'],
                        'usage' => $meta['usage'],
                        'admin' => true,
                        'dangerous' => $meta['dangerous'] ?? false,
                    ];
                }
            }
        }

        return $commands;
    }

    /**
     * Search commands
     */
    public function search(string $query): array {
        $results = [];
        $query = strtolower($query);

        foreach ($this->getAllCommands() as $cmd) {
            if (
                stripos($cmd['full'], $query) !== false ||
                stripos($cmd['description'], $query) !== false
            ) {
                $results[] = $cmd;
            }
        }

        return $results;
    }

    /**
     * Get help for namespace
     */
    public function getNamespaceHelp(string $namespace): ?array {
        if (!isset($this->registry[$namespace])) {
            return null;
        }

        $ns = $this->registry[$namespace];

        return [
            'namespace' => $namespace,
            'description' => $ns['description'],
            'commands' => array_map(fn($cmd, $meta) => [
                'command' => $cmd,
                'description' => $meta['description'],
                'usage' => $meta['usage'],
            ], array_keys($ns['commands']), $ns['commands']),
            'admin_commands' => $this->isAdmin ? array_map(fn($cmd, $meta) => [
                'command' => $cmd,
                'description' => $meta['description'],
                'usage' => $meta['usage'],
                'dangerous' => $meta['dangerous'] ?? false,
            ], array_keys($ns['admin_only']), $ns['admin_only']) : [],
        ];
    }
}

/**
 * Factory function
 */
function createCommandRegistry(bool $isAdmin = false): CommandRegistry {
    global $COMMAND_REGISTRY, $FORBIDDEN_PATTERNS;
    return new CommandRegistry($COMMAND_REGISTRY, $FORBIDDEN_PATTERNS, $isAdmin);
}
