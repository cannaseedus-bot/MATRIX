# Brain-Mesh Orchestrator

Adaptive multi-LLM routing with epsilon-greedy selection for optimal model performance.

## Overview

Brain-Mesh is an intelligent orchestration layer that:
- Routes requests to the best-performing LLM for each domain
- Uses multi-armed bandit (epsilon-greedy) selection
- Tracks performance metrics and adapts in real-time
- Supports 9+ cloud providers + local Ollama models

## Quick Start

### 1. Install Dependencies

```bash
# Database
mysql -u root -p < schema.sql

# Copy config
cp config.example.php config.php
```

### 2. Configure

Edit `config.php`:

```php
return [
    'database' => [
        'host' => 'localhost',
        'name' => 'brain_mesh',
        'user' => 'your_user',
        'pass' => 'your_password',
    ],
    'providers' => [
        'anthropic' => [
            'api_key' => 'sk-ant-...',
            'models' => ['claude-3-5-sonnet', 'claude-3-opus'],
        ],
        // ... more providers
    ],
];
```

### 3. Test

```bash
# Health check
curl http://localhost/api/brain-mesh/health.php

# Execute command
curl -X POST http://localhost/api/brain-mesh/terminal.php \
  -H "Content-Type: application/json" \
  -d '{"cmd": "brain-mesh leaderboard"}'
```

## API Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/health.php` | GET | No | Health check |
| `/terminal.php` | POST | Yes | Execute terminal command |
| `/leaderboard.php` | GET | Yes | Brain rankings |
| `/install.php` | POST | Yes | Database setup |

## Terminal Commands

### Brain-Mesh Namespace

```bash
# List all brains
brain-mesh brains

# Show leaderboard
brain-mesh leaderboard
brain-mesh leaderboard --domain=code

# Statistics
brain-mesh stats

# Health check
brain-mesh health

# Select best brain for domain
brain-mesh select code
brain-mesh best reasoning

# Runtime status
brain-mesh status
```

### LLM Namespace

```bash
# List providers
llm providers

# List models
llm models
llm models --provider=anthropic

# Chat completion
llm chat claude-3-5-sonnet "Hello world"
llm test gpt-4o
```

### K'UHUL Namespace

```bash
# Execute glyph
kuhul exec "⟁Sek⟁ brain-mesh health"

# Parse code
kuhul parse "⟁Wo⟁ x = 42"

# List glyphs
kuhul glyphs

# Kernel status
kuhul status
```

## Scoring Algorithm

Brain-Mesh uses epsilon-greedy multi-armed bandit selection:

### Score Components

| Component | Weight | Description |
|-----------|--------|-------------|
| Success Rate | 0.5 | % of successful completions |
| Speed | 0.3 | Normalized latency (lower = better) |
| Consistency | 0.2 | Variance in response quality |

### Selection

```
P(explore) = ε (default: 0.1)
P(exploit) = 1 - ε

If explore:
  Select random brain
Else:
  Select brain with highest score for domain
```

### Tier Promotion

| Tier | Score Range | Behavior |
|------|-------------|----------|
| premium | > 0.85 | Preferred for important tasks |
| standard | 0.4 - 0.85 | Normal selection pool |
| fallback | < 0.4 | Only used when others fail |

## Configuration Reference

### Database

```php
'database' => [
    'host' => 'localhost',
    'port' => 3306,
    'name' => 'brain_mesh',
    'user' => 'user',
    'pass' => 'password',
    'charset' => 'utf8mb4',
]
```

### LLM Providers

```php
'providers' => [
    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'base_url' => 'https://api.anthropic.com/v1',
        'models' => [
            'claude-3-5-sonnet' => [
                'context' => 200000,
                'max_tokens' => 8192,
                'cost_per_1k' => 0.003,
            ],
        ],
    ],
    'ollama' => [
        'host' => 'http://localhost:11434',
        'models' => ['llama3.2', 'mixtral', 'codellama'],
    ],
]
```

### Adaptive Scoring

```php
'adaptive' => [
    'exploration_rate' => 0.1,      // 10% exploration
    'rolling_window' => 50,         // Last 50 executions
    'score_weights' => [
        'success' => 0.5,
        'speed' => 0.3,
        'consistency' => 0.2,
    ],
    'tier_thresholds' => [
        'promotion' => 0.85,
        'demotion' => 0.4,
    ],
    'min_executions' => 10,
]
```

### CM-1 Control

```php
'cm1' => [
    'enabled' => true,
    'audit_logging' => true,
    'phase_tracking' => true,
]
```

## Database Schema

```sql
-- Brains table
CREATE TABLE brains (
    id VARCHAR(64) PRIMARY KEY,
    type VARCHAR(32),
    tier ENUM('premium', 'standard', 'fallback'),
    score DECIMAL(5,3) DEFAULT 0.500,
    executions INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Executions history
CREATE TABLE executions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    brain_id VARCHAR(64),
    domain VARCHAR(32),
    success BOOLEAN,
    latency_ms INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- CM-1 phases
CREATE TABLE cm1_phases (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    from_phase VARCHAR(16),
    to_phase VARCHAR(16),
    context JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Files

| File | Purpose |
|------|---------|
| `BrainMesh.php` | Core orchestration engine |
| `LLMProvider.php` | Multi-provider LLM class |
| `terminal.php` | Terminal projection API |
| `command-registry.php` | Command allowlist |
| `config.example.php` | Configuration template |
| `db.php` | Database connection |
| `health.php` | Health endpoint |
| `install.php` | Database setup |

## Security

### Authentication

```bash
# API key in header
curl -H "X-API-KEY: your-key" http://localhost/api/brain-mesh/terminal.php
```

### Admin Commands

Some commands require admin API key:
- `brain-mesh reset`
- `brain-mesh save/load`
- `file write/delete`

### Rate Limiting

Default: 60 requests/minute, burst limit 10

## Troubleshooting

### Connection Issues

```bash
# Check health
curl http://localhost/api/brain-mesh/health.php

# Verify database
brain-mesh status
```

### Low Scores

1. Check provider connectivity: `llm test <model>`
2. Review execution history in database
3. Adjust scoring weights in config

### Missing Models

```bash
# For Ollama
ollama pull llama3.2

# Check available
llm models --provider=ollama
```

## License

UNLICENSED

---

@law ASX = XCFE = XJSON = KUHUL = AST = CM-1
