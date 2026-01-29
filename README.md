<img src="assets/logo.svg" width="200">

# MATRIX

**MATRIX** is a distributed AI code agent orchestration system. It bridges a PHP broker to AI coding assistants like Claude Code, Codex, Aider, and Ollama.

## Core Components

- **PHP Broker API** - REST API that queues and distributes tasks
- **Python Agent** - Bridges broker to AI code agents (Claude, Codex, Aider, Ollama)
- **PWA Frontend** - Web interface for managing tasks
- **MX2LM Server** - KUHUL-controlled server runtime (Node.js)
- **PowerShell Server** - Static file server with API endpoints (Windows)

## New Components (v2.1)

- **Brain-Mesh Orchestrator** - Adaptive multi-LLM routing with epsilon-greedy selection
- **KUHUL GLYPH Studio** - Terminal projection PWA with code editor
- **Matrix CLI** - Command-line interface with DNS-based tunneling
- **Micronaut CSS** - CSS-variable-driven UI monitoring system
- **CM-1 Protocol** - Control character layer for phase management

## Quick Start

### 1. Install an AI Code Agent

```bash
# Claude Code (recommended)
npm install -g @anthropic-ai/claude-code

# or Codex
npm install -g codex

# or Aider
pip install aider-chat

# or Ollama (local LLMs)
curl -fsSL https://ollama.com/install.sh | sh
ollama pull llama3.2
```

### 2. Set API Key

```bash
# For Claude
export ANTHROPIC_API_KEY="sk-ant-..."

# For Codex
export OPENAI_API_KEY="sk-..."

# For Ollama Cloud (optional)
export OLLAMA_API_KEY="your_api_key"
```

### 3. Configure & Run Agent

```bash
cd agent
pip install -r requirements.txt
python agent.py
```

### 4. Send a Prompt

```bash
# To configured agent
curl -X POST http://127.0.0.1:5001/prompt \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Add a hello world function to main.py"}'

# Direct to Ollama
curl -X POST http://127.0.0.1:5001/ollama \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Explain recursion", "model": "llama3.2"}'
```

## Supported AI Agents

| Agent         | Package                      | Type  | API Key Variable      |
|---------------|------------------------------|-------|-----------------------|
| Claude        | `@anthropic-ai/claude-code`  | CLI   | `ANTHROPIC_API_KEY`   |
| Codex         | `codex`                      | CLI   | `OPENAI_API_KEY`      |
| Aider         | `aider-chat`                 | CLI   | (various)             |
| Ollama        | `ollama`                     | API   | (none - local)        |
| Ollama Cloud  | (hosted)                     | API   | `OLLAMA_API_KEY`      |

## Ollama Cloud Models

### Frontier / Ultra-Large (Cloud-Only)

| Model                      | Size   | Description                    |
|----------------------------|--------|--------------------------------|
| `deepseek-v3.1:671b-cloud` | 671B   | Frontier model                 |
| `gpt-oss:120b-cloud`       | 120B   | Open-source GPT-OSS variant    |
| `kimi-k2:1t-cloud`         | 1T     | Trillion-parameter model       |
| `qwen3-coder:480b-cloud`   | 480B   | Code-specialized              |
| `qwen3-vl:235b-cloud`      | 235B   | Vision-language model          |

### Large General-Purpose

| Model                    | Description                              |
|--------------------------|------------------------------------------|
| `glm-4.6:cloud`          | High-end GLM series                      |
| `glm-4.7`                | Coding-optimized GLM                     |
| `minimax-m2.1`           | Multilingual, code + reasoning           |
| `gemini-3-flash-preview` | Fast, low-cost frontier                  |

### Agentic / Tool-Using

| Model             | Size | Description                         |
|-------------------|------|-------------------------------------|
| `nemotron-3-nano` | 30B  | Tool-use capabilities               |
| `devstral-small-2`| 24B  | Codebase exploration, multi-file    |
| `rnj-1`           | 8B   | STEM + code optimized               |

## Project Structure

```
MATRIX/
├── api/                    # PHP APIs
│   ├── brain-mesh/         # Brain-Mesh Orchestrator (NEW)
│   │   ├── BrainMesh.php    # Adaptive routing engine
│   │   ├── LLMProvider.php  # Multi-LLM provider class
│   │   ├── terminal.php     # Terminal projection API
│   │   ├── command-registry.php # Command allowlist
│   │   ├── config.example.php # Configuration template
│   │   └── db.php           # Database connection
│   ├── agents.php          # Agent management
│   ├── tasks.php           # Task listing
│   └── schema.sql          # MySQL schema
│
├── bin/                    # CLI Tools (NEW)
│   └── matrix-cli/         # Matrix CLI
│       ├── index.js         # Main entry point
│       ├── dns-tunnel.js    # DNS-based communication
│       ├── commands/        # CLI command modules
│       └── package.json     # Dependencies
│
├── agent/                  # Python Agent (AI Bridge)
│   ├── agent.py            # Main agent script
│   ├── agent_config.json   # Configuration
│   └── requirements.txt    # Python dependencies
│
├── cli/                    # MX2LM Server Runtime
│   ├── server.khl          # Server runtime config
│   ├── server/             # Node.js server module
│   ├── powershell/         # PowerShell static server
│   │   ├── server.ps1       # Main server script
│   │   ├── start-server.bat # Windows launcher
│   │   └── xcfe/            # XCFE governed execution
│   └── ui/                 # Micronaut CSS (NEW)
│       ├── micronaut.css    # CSS variable bindings
│       ├── emerald_ghost_atomic.css # Atomic framework
│       └── demo.html        # Interactive demo
│
├── pwa/                    # Progressive Web Apps
│   ├── studio/             # KUHUL GLYPH Studio (NEW)
│   │   ├── index.php        # Main PWA shell
│   │   ├── glyph-runtime.js # Client glyph parser
│   │   ├── sw.js            # Service worker
│   │   └── manifest.json    # PWA metadata
│   ├── index.html          # Task runner interface
│   └── sw.js               # Service worker
│
├── docs/                   # Documentation
│   ├── ai-agents.md        # AI Agent integration
│   ├── mx2lm-cli.md        # Server specification
│   └── mxs.md              # MXS specification
│
├── specs/                  # Language Specifications
│   ├── mxs/                # MXS stylesheets
│   ├── kuhul/              # KUHUL class definitions
│   ├── cm1/                # Control Micronaut-1 spec
│   │   ├── control-micronaut.xjson # CM-1 protocol
│   │   └── control-micronaut.schema.xjson
│   └── server/             # Server schemas
│
├── inference/              # Inference Plains
├── experimental/           # POC code (Kuhul)
└── assets/                 # Logos
```

## Configuration

Edit `agent/agent_config.json`:

```json
{
  "broker_url": "https://yourdomain.com/api",
  "agent_name": "Agent1",
  "ai_agent": "claude",
  "working_dir": "/path/to/project",

  "ollama_model": "llama3.2",
  "ollama_host": "http://localhost:11434"
}
```

### Agent Types

| `ai_agent` Value | Description                    |
|------------------|--------------------------------|
| `claude`         | Claude Code CLI                |
| `codex`          | OpenAI Codex CLI               |
| `aider`          | Aider CLI                      |
| `ollama`         | Ollama local (localhost:11434) |
| `ollama-cloud`   | Ollama cloud (ollama.com)      |
| `custom`         | Custom agent binary            |

## API Endpoints

| Endpoint         | Method | Description                    |
|------------------|--------|--------------------------------|
| `/prompt`        | POST   | Send prompt to AI agent        |
| `/ollama`        | POST   | Direct Ollama API              |
| `/ollama/models` | GET    | List Ollama models             |
| `/shell`         | POST   | Execute shell command          |
| `/status`        | GET    | Get agent status               |
| `/agents`        | GET    | List available AI agents       |

See [docs/ai-agents.md](docs/ai-agents.md) for full documentation.

## PHP Broker API

Deploy to any PHP hosting:

1. Upload `api/` folder and `config.php`
2. Import `api/schema.sql` into MySQL
3. Configure database credentials in `config.php`

## MX2LM CLI

KUHUL-controlled server lifecycle with π-decay restart logic:

```bash
node cli/server/index.js start
node cli/server/index.js status
```

| π Support | Restart Policy       |
|-----------|----------------------|
| `< 0.4`   | Suppress restart     |
| `0.4–0.7` | Restart once         |
| `> 0.7`   | Restart with backoff |
| `> 0.9`   | Immediate heal       |

See [docs/mx2lm-cli.md](docs/mx2lm-cli.md) for full specification.

## PowerShell Static Server

KUHUL-governed static file server for Windows:

```powershell
# Start server on port 8080
.\cli\powershell\server.ps1 -Port 8080 -Root "./public"

# Or use the batch launcher
.\cli\powershell\start-server.bat -port 8080
```

| Endpoint         | Description              |
|------------------|--------------------------|
| `/`              | Server status (JSON)     |
| `/status`        | Server status            |
| `/health`        | Health check             |
| `/api/agents`    | AI agents list           |
| `/api/models`    | Ollama models            |
| `/*`             | Static files from root   |

KUHUL Class: `api.local.static` (read-only, localhost only)

## XCFE Governed Execution (PSX)

Deny-by-default PowerShell execution via PS-DSL:

```bash
# List allowed actions
node cli/powershell/xcfe/psx-cli.js --list-actions

# Execute an intent
node cli/powershell/xcfe/psx-cli.js examples/process-list.json
```

Intent format (PS-DSL):

```json
{
  "@dsl": "ps-dsl.v1",
  "action": "process.list",
  "params": {}
}
```

Features:
- **Deny-by-default**: Only allowlisted actions can execute
- **No arbitrary text**: PowerShell never receives raw user input
- **CM-1 auditable**: All executions carry phase geometry
- **Deterministic lowering**: DSL intents lower to single cmdlets

See [cli/powershell/xcfe/README.md](cli/powershell/xcfe/README.md) for full specification.

## Brain-Mesh API

Adaptive LLM orchestrator with multi-armed bandit routing.

### Quick Start

```bash
# Health check
curl http://localhost/api/brain-mesh/health.php

# Execute terminal command
curl -X POST http://localhost/api/brain-mesh/terminal.php \
  -H "Content-Type: application/json" \
  -d '{"cmd": "brain-mesh leaderboard"}'

# List LLM providers
curl -X POST http://localhost/api/brain-mesh/terminal.php \
  -d '{"cmd": "llm providers"}'
```

### Configuration

Copy `api/brain-mesh/config.example.php` to `config.php` and configure:

- Database credentials (MySQL/MariaDB)
- LLM provider API keys (Anthropic, OpenAI, etc.)
- Ollama host for local models
- Adaptive scoring tuning

### Supported LLM Providers

| Provider | Models | API Key Variable |
|----------|--------|------------------|
| Anthropic | Claude 3.5/4 | `ANTHROPIC_API_KEY` |
| OpenAI | GPT-4o/o1 | `OPENAI_API_KEY` |
| Google | Gemini 2.0 | `GOOGLE_API_KEY` |
| Mistral | Mistral Large | `MISTRAL_API_KEY` |
| Groq | Llama 3.3 70B | `GROQ_API_KEY` |
| Ollama | 40+ local | (none) |
| GGUF | llama.cpp | (local) |

## KUHUL GLYPH Studio

Terminal projection PWA with integrated code editor.

### Access

Open `/pwa/studio/` in browser and install as PWA.

### Features

- Split-pane terminal + code editor
- K'UHUL glyph execution (⟁Sek⟁, ⟁Wo⟁, ⟁K'an⟁)
- Micronaut health monitoring
- 4 themes (Emerald Ghost, Matrix, Cyber, Night)
- Offline support via Service Worker
- Command palette (Ctrl+Shift+P)

### Glyph Syntax

| Glyph | Name | Purpose | Example |
|-------|------|---------|---------|
| ⟁Sek⟁ | Execute | Run command | `⟁Sek⟁ brain-mesh health` |
| ⟁Wo⟁ | Assign | Set variable | `⟁Wo⟁ msg = "Hello"` |
| ⟁K'an⟁ | Transform | Process data | `⟁K'an⟁ transform:uppercase $msg` |
| ⟁Ajaw⟁ | Lord | Admin command | `⟁Ajaw⟁ reset-cache` |
| ⟁Muwan⟁ | Owl | Async operation | `⟁Muwan⟁ long-task` |
| ⟁K'uhul⟁ | Divine | Critical op | `⟁K'uhul⟁ kernel-init` |

### Terminal Commands

| Namespace | Commands |
|-----------|----------|
| brain-mesh | brains, leaderboard, stats, health, select, best |
| llm | providers, models, chat, test |
| kuhul | exec, parse, glyphs, status, echo, variables |
| file | read, list, info, search, tree |
| help | (shows all commands) |

## Matrix CLI

Command-line interface with DNS-based tunneling for firewall traversal.

### Installation

```bash
# Global install
npm install -g ./bin/matrix-cli

# Or run directly
node bin/matrix-cli/index.js
```

### Usage

```bash
# Setup and connect
matrix-cli setup
matrix-cli connect --dns

# Brain management
matrix-cli brain list
matrix-cli brain score claude-sonnet
matrix-cli brain leaderboard

# Terminal commands
matrix-cli terminal "brain-mesh health"
matrix-cli terminal "llm providers"

# DNS tunnel (no HTTP required)
matrix-cli tunnel start --ttl 300
matrix-cli tunnel status
```

### DNS-Based Communication

Matrix CLI uses DNS TXT records for communication, avoiding HTTP firewall issues:

```
┌─────────────┐     DNS Query      ┌─────────────┐
│  Matrix CLI │ ─────────────────▶ │  DNS Server │
│  (mx2lm)    │                    │  (PHP/bind) │
│             │ ◀───────────────── │             │
└─────────────┘     TXT Response   └─────────────┘
```

- **No HTTP ports needed** - Uses standard DNS (port 53)
- **TTL-based polling** - Configurable refresh rate
- **TTP Protocol** - Time-To-Propagate for eventual consistency
- **Encrypted payloads** - Base64 + SCXQ2 encoding

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      MATRIX v2.1                             │
│                                                              │
│  ┌──────────────────┐    ┌──────────────────────────────┐  │
│  │  KUHUL GLYPH     │    │     Brain-Mesh Orchestrator   │  │
│  │  Studio PWA      │───▶│  (Epsilon-Greedy Selection)   │  │
│  │  (Terminal +     │    │                               │  │
│  │   Editor)        │    │  ┌─────┐ ┌─────┐ ┌─────────┐ │  │
│  └──────────────────┘    │  │Claude│ │GPT-4│ │ Ollama  │ │  │
│                          │  └──┬──┘ └──┬──┘ └────┬────┘ │  │
│  ┌──────────────────┐    │     └───────┼─────────┘      │  │
│  │  Matrix CLI      │    │             ▼                │  │
│  │  (DNS Tunnel)    │───▶│     LLMProvider.php         │  │
│  └──────────────────┘    └──────────────────────────────┘  │
│           │                            │                    │
│           ▼                            ▼                    │
│  ┌──────────────────┐    ┌──────────────────────────────┐  │
│  │ MX2LM Server     │    │ Python Agent                 │  │
│  │ (Node.js)        │    │ (AI Bridge)                  │  │
│  └──────────────────┘    └──────────────────────────────┘  │
│           │                            │                    │
│           └────────────┬───────────────┘                    │
│                        ▼                                    │
│           ┌──────────────────────────────┐                 │
│           │ CM-1 Protocol                │                 │
│           │ (Phase Control)              │                 │
│           └──────────────────────────────┘                 │
└─────────────────────────────────────────────────────────────┘
```

| Layer          | Role                    |
|----------------|-------------------------|
| KUHUL Studio   | Web terminal + editor   |
| Matrix CLI     | DNS-based CLI access    |
| Brain-Mesh     | Adaptive LLM routing    |
| MX2LM Server   | Node.js runtime         |
| Python Agent   | AI bridge               |
| CM-1 Protocol  | Phase control           |

## Examples

### PowerShell

```powershell
# Start agent in new window
Start-Process powershell -ArgumentList "python agent/agent.py"

# Send prompt to Claude
Invoke-RestMethod -Uri "http://127.0.0.1:5001/prompt" `
  -Method POST `
  -ContentType "application/json" `
  -Body '{"prompt": "Fix the bug in auth.py"}'

# Send prompt to Ollama
Invoke-RestMethod -Uri "http://127.0.0.1:5001/ollama" `
  -Method POST `
  -ContentType "application/json" `
  -Body '{"prompt": "Write a unit test", "model": "codellama"}'

# List Ollama models
Invoke-RestMethod -Uri "http://127.0.0.1:5001/ollama/models"

# Check status
Invoke-RestMethod -Uri "http://127.0.0.1:5001/status"
```

### Bash

```bash
# Use Ollama local
AI_AGENT=ollama python agent/agent.py

# Use Ollama cloud
AI_AGENT=ollama-cloud OLLAMA_API_KEY=xxx python agent/agent.py

# Use Claude
AI_AGENT=claude ANTHROPIC_API_KEY=xxx python agent/agent.py
```

### Python

```python
from ollama import Client

# Using Ollama directly
client = Client()
response = client.chat('llama3.2', messages=[
    {'role': 'user', 'content': 'Hello!'}
])

# Using MATRIX agent
import requests
response = requests.post('http://127.0.0.1:5001/ollama', json={
    'prompt': 'Hello!',
    'model': 'llama3.2'
})
```

### JavaScript

```javascript
import { Ollama } from "ollama";

// Using Ollama directly
const ollama = new Ollama();
const response = await ollama.chat({
  model: "llama3.2",
  messages: [{ role: "user", content: "Hello!" }],
});

// Using MATRIX agent
const res = await fetch("http://127.0.0.1:5001/ollama", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({ prompt: "Hello!", model: "llama3.2" }),
});
```

## License

UNLICENSED
