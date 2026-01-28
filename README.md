<img src="assets/logo.svg" width="200">

# MATRIX

**MATRIX** is a distributed AI code agent orchestration system. It bridges a PHP broker to AI coding assistants like Claude Code, Codex, Aider, and Ollama.

## Core Components

- **PHP Broker API** - REST API that queues and distributes tasks
- **Python Agent** - Bridges broker to AI code agents (Claude, Codex, Aider, Ollama)
- **PWA Frontend** - Web interface for managing tasks
- **MX2LM CLI** - KUHUL-controlled host orchestrator

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
├── api/                    # PHP Broker API
│   ├── agents.php          # Agent management
│   ├── tasks.php           # Task listing
│   ├── add_task.php        # Create tasks
│   ├── get_task.php        # Agent polling
│   ├── submit_result.php   # Result submission
│   └── schema.sql          # MySQL schema
│
├── agent/                  # Python Agent (AI Bridge)
│   ├── agent.py            # Main agent script
│   ├── agent_config.json   # Configuration
│   └── requirements.txt    # Python dependencies
│
├── cli/                    # MX2LM CLI
│   ├── server.khl          # Server runtime
│   ├── server/             # CLI module
│   └── ui/                 # CSS Micronaut UI
│
├── pwa/                    # Progressive Web App
│   ├── index.html          # Main interface
│   └── sw.js               # Service worker
│
├── docs/                   # Documentation
│   ├── ai-agents.md        # AI Agent integration
│   ├── mx2lm-cli.md        # CLI specification
│   └── mxs.md              # MXS specification
│
├── specs/                  # Language Specifications
│   ├── mxs/                # MXS stylesheets
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

## Architecture

```
PHP Broker API (task queue)
        ↓
Python Agent (bridge)
        ↓
AI Code Agent (Claude/Codex/Aider/Ollama)
        ↓
MX2LM CLI (host orchestrator)
        ↓
KUHUL π (intent physics)
```

| Layer          | Role              |
|----------------|-------------------|
| PHP Broker     | Task distribution |
| Python Agent   | AI bridge         |
| AI Agent       | Code execution    |
| MX2LM CLI      | Host control      |
| KUHUL          | Physics engine    |

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
