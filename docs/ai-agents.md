# AI Agent Integration

MATRIX bridges the PHP broker to AI code agents like Claude Code, Codex, Aider, and Ollama. The Python agent receives tasks from the broker and delegates them to the configured AI agent.

## Architecture

```
PHP Broker API
     ↓
Python Agent (polls broker)
     ↓
AI Code Agent (Claude, Codex, Aider, Ollama)
     ↓
Codebase execution
```

## Supported Agents

| Agent         | Package                        | Type | Install Command                        |
|---------------|--------------------------------|------|----------------------------------------|
| Claude        | `@anthropic-ai/claude-code`    | CLI  | `npm i -g @anthropic-ai/claude-code`   |
| Codex         | `codex`                        | CLI  | `npm i -g codex`                       |
| Aider         | `aider-chat`                   | CLI  | `pip install aider-chat`               |
| Ollama        | `ollama`                       | API  | https://ollama.com/download            |
| Ollama Cloud  | (hosted)                       | API  | `ollama signin`                        |

## Setup

### Claude Code

```bash
npm install -g @anthropic-ai/claude-code
export ANTHROPIC_API_KEY="sk-ant-..."
```

### Codex

```bash
npm install -g codex
export OPENAI_API_KEY="sk-..."
```

### Aider

```bash
pip install aider-chat
```

### Ollama (Local)

```bash
# Install Ollama
curl -fsSL https://ollama.com/install.sh | sh

# Pull a model
ollama pull llama3.2

# Start the server
ollama serve
```

### Ollama Cloud

```bash
# Sign in to Ollama
ollama signin

# Or set API key directly
export OLLAMA_API_KEY="your_api_key"
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

| `ai_agent` Value | Description                          |
|------------------|--------------------------------------|
| `claude`         | Claude Code CLI                      |
| `codex`          | OpenAI Codex CLI                     |
| `aider`          | Aider CLI                            |
| `ollama`         | Ollama local API (localhost:11434)   |
| `ollama-cloud`   | Ollama cloud API (ollama.com)        |
| `custom`         | Custom agent (set `ai_agent_path`)   |

## Running the Agent

```bash
cd agent
pip install -r requirements.txt
python agent.py
```

Output:

```
MATRIX Agent: Agent1
AI Agent: ollama
Ollama Model: llama3.2
Ollama Host: http://localhost:11434
Working Dir: /home/user/project
Broker: https://yourdomain.com/api
Listening: http://127.0.0.1:5001
```

## API Endpoints

### POST /prompt

Send a prompt to the configured AI agent.

```bash
curl -X POST http://127.0.0.1:5001/prompt \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Explain quantum computing"}'
```

For Ollama, you can specify a model:

```bash
curl -X POST http://127.0.0.1:5001/prompt \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Write a haiku", "model": "llama3.2"}'
```

### POST /ollama

Direct Ollama endpoint (works regardless of configured agent):

```bash
# Local Ollama
curl -X POST http://127.0.0.1:5001/ollama \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Hello!", "model": "llama3.2"}'

# Ollama Cloud
curl -X POST http://127.0.0.1:5001/ollama \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Hello!", "model": "gpt-oss:120b", "cloud": true}'
```

### GET /ollama/models

List available Ollama models:

```bash
# Local models
curl http://127.0.0.1:5001/ollama/models

# Cloud models
curl "http://127.0.0.1:5001/ollama/models?cloud=true"
```

### POST /shell

Execute a shell command directly:

```bash
curl -X POST http://127.0.0.1:5001/shell \
  -H "Content-Type: application/json" \
  -d '{"cmd": "npm test"}'
```

### GET /agents

List all available AI agents:

```bash
curl http://127.0.0.1:5001/agents
```

Response:

```json
{
  "agents": [
    {"name": "claude", "type": "cli", "cmd": "npx", "active": false},
    {"name": "codex", "type": "cli", "cmd": "npx", "active": false},
    {"name": "aider", "type": "cli", "cmd": "aider", "active": false},
    {"name": "ollama", "type": "api", "host": "http://localhost:11434", "active": true},
    {"name": "ollama-cloud", "type": "api", "host": "https://ollama.com", "active": false}
  ],
  "current": "ollama"
}
```

### GET /status

Get agent status:

```bash
curl http://127.0.0.1:5001/status
```

## Ollama Models

### Popular Local Models

| Model         | Size   | Use Case                    |
|---------------|--------|-----------------------------|
| `llama3.2`    | 2B     | Fast, general purpose       |
| `llama3.2:3b` | 3B     | Better quality              |
| `codellama`   | 7B     | Code generation             |
| `mistral`     | 7B     | General purpose             |
| `mixtral`     | 47B    | High quality, needs GPU     |

### Cloud Models - Frontier / Ultra-Large

These models only run in the cloud due to size.

| Model                    | Size   | Description                        |
|--------------------------|--------|------------------------------------|
| `deepseek-v3.1:671b-cloud` | 671B | Frontier model                     |
| `gpt-oss:120b-cloud`     | 120B   | Open-source GPT-OSS variant        |
| `kimi-k2:1t-cloud`       | 1T     | Trillion-parameter class model     |
| `qwen3-coder:480b-cloud` | 480B   | Code-specialized model             |
| `qwen3-vl:235b-cloud`    | 235B   | Vision-language model              |

### Cloud Models - Large General-Purpose

| Model                     | Description                              |
|---------------------------|------------------------------------------|
| `glm-4.6:cloud`           | High-end GLM series model                |
| `glm-4.7`                 | Coding-optimized GLM variant             |
| `minimax-m2.1`            | Multilingual, strong for code + reasoning|
| `gemini-3-flash-preview`  | Fast, low-cost frontier intelligence     |

### Cloud Models - Agentic / Tool-Using

Optimized for tool use, code navigation, and agent workflows.

| Model               | Size | Description                                    |
|---------------------|------|------------------------------------------------|
| `nemotron-3-nano`   | 30B  | Efficient agentic model with tool-use          |
| `devstral-small-2`  | 24B  | Codebase exploration + multi-file editing      |
| `rnj-1`             | 8B   | STEM + code-optimized open-weight family       |

### Pull a Model

```bash
# Local models
ollama pull llama3.2
ollama pull codellama
ollama pull mistral

# Cloud models (requires ollama signin)
ollama pull gpt-oss:120b-cloud
ollama pull deepseek-v3.1:671b-cloud
ollama pull qwen3-coder:480b-cloud
```

## Broker Task Format

The PHP broker can specify task type and Ollama options:

```json
{
  "id": 123,
  "command": "Explain this code",
  "type": "prompt",
  "working_dir": "/var/www/myproject"
}
```

For Ollama-specific tasks:

```json
{
  "id": 124,
  "command": "Generate a unit test",
  "type": "ollama",
  "model": "codellama",
  "cloud": false
}
```

| Type     | Behavior                           |
|----------|-----------------------------------|
| `prompt` | Send to configured AI agent       |
| `shell`  | Execute as shell command          |
| `ollama` | Direct Ollama API (specify model) |

## PowerShell Examples

```powershell
# Start agent
Start-Process powershell -ArgumentList "python agent/agent.py"

# Send prompt to Ollama
Invoke-RestMethod -Uri "http://127.0.0.1:5001/ollama" `
  -Method POST `
  -ContentType "application/json" `
  -Body '{"prompt": "Write a PowerShell script", "model": "codellama"}'

# List models
Invoke-RestMethod -Uri "http://127.0.0.1:5001/ollama/models"

# Check status
Invoke-RestMethod -Uri "http://127.0.0.1:5001/status"
```

## Python Examples

```python
from ollama import Client

# Using Ollama Python library directly
client = Client()
response = client.chat('llama3.2', messages=[
    {'role': 'user', 'content': 'Hello!'}
])
print(response['message']['content'])

# Using MATRIX agent
import requests
response = requests.post('http://127.0.0.1:5001/ollama', json={
    'prompt': 'Hello!',
    'model': 'llama3.2'
})
print(response.json()['output'])
```

## JavaScript Examples

```javascript
import { Ollama } from "ollama";

// Using Ollama JS library directly
const ollama = new Ollama();
const response = await ollama.chat({
  model: "llama3.2",
  messages: [{ role: "user", content: "Hello!" }],
});
console.log(response.message.content);

// Using MATRIX agent
const res = await fetch("http://127.0.0.1:5001/ollama", {
  method: "POST",
  headers: { "Content-Type": "application/json" },
  body: JSON.stringify({ prompt: "Hello!", model: "llama3.2" }),
});
const data = await res.json();
console.log(data.output);
```

## Docker

```dockerfile
FROM python:3.11-slim

# Install Node.js for CLI agents
RUN apt-get update && apt-get install -y nodejs npm curl

# Install Claude Code
RUN npm install -g @anthropic-ai/claude-code

# Install Ollama CLI (optional, for local inference in container)
RUN curl -fsSL https://ollama.com/install.sh | sh

WORKDIR /app
COPY agent/ .
RUN pip install -r requirements.txt

# Default to Ollama (can override with env)
ENV AI_AGENT=ollama
ENV OLLAMA_HOST=http://host.docker.internal:11434

CMD ["python", "agent.py"]
```

## Environment Variables

| Variable           | Description                    | Default                  |
|--------------------|--------------------------------|--------------------------|
| `AI_AGENT`         | Which agent to use             | `claude`                 |
| `ANTHROPIC_API_KEY`| Claude API key                 | (required for Claude)    |
| `OPENAI_API_KEY`   | OpenAI API key                 | (required for Codex)     |
| `OLLAMA_API_KEY`   | Ollama cloud API key           | (required for cloud)     |
| `OLLAMA_MODEL`     | Default Ollama model           | `llama3.2`               |
| `OLLAMA_HOST`      | Ollama server URL              | `http://localhost:11434` |
| `WORKING_DIR`      | Working directory for agents   | Current directory        |

## Switching Agents

Change agent at runtime via config or environment:

```bash
# Use Ollama local
AI_AGENT=ollama python agent.py

# Use Ollama cloud
AI_AGENT=ollama-cloud OLLAMA_API_KEY=xxx python agent.py

# Use Claude
AI_AGENT=claude ANTHROPIC_API_KEY=xxx python agent.py
```

Or edit `agent_config.json`:

```json
{
  "ai_agent": "ollama",
  "ollama_model": "codellama"
}
```
