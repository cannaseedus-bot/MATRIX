# AI Agent Integration

MATRIX bridges the PHP broker to AI code agents like Claude Code, Codex, and Aider. The Python agent receives tasks from the broker and delegates them to the configured AI agent.

## Architecture

```
PHP Broker API
     ↓
Python Agent (polls broker)
     ↓
AI Code Agent (Claude, Codex, Aider)
     ↓
Codebase execution
```

## Supported Agents

| Agent   | Package                        | Install Command                        |
|---------|--------------------------------|----------------------------------------|
| Claude  | `@anthropic-ai/claude-code`    | `npm i -g @anthropic-ai/claude-code`   |
| Codex   | `codex`                        | `npm i -g codex`                       |
| Aider   | `aider-chat`                   | `pip install aider-chat`               |

## Setup

### 1. Install the AI Agent

**Claude Code (recommended):**

```bash
npm install -g @anthropic-ai/claude-code
```

**Codex:**

```bash
npm install -g codex
```

**Aider:**

```bash
pip install aider-chat
```

### 2. Set API Keys

**Claude:**

```bash
export ANTHROPIC_API_KEY="sk-ant-..."
```

**Codex:**

```bash
export OPENAI_API_KEY="sk-..."
```

### 3. Configure the Agent

Edit `agent/agent_config.json`:

```json
{
  "broker_url": "https://yourdomain.com/api",
  "agent_name": "Agent1",
  "ai_agent": "claude",
  "working_dir": "/path/to/your/project"
}
```

### 4. Run the Agent

```bash
cd agent
pip install -r requirements.txt
python agent.py
```

## Configuration Options

| Option           | Description                              | Default                  |
|------------------|------------------------------------------|--------------------------|
| `ai_agent`       | Which AI agent to use                    | `claude`                 |
| `ai_agent_path`  | Custom path to agent binary              | (uses npx)               |
| `working_dir`    | Directory for AI agent to work in        | Current directory        |
| `command_timeout`| Max seconds for AI agent execution       | `300`                    |

## API Endpoints

### POST /prompt

Send a prompt to the AI code agent.

```bash
curl -X POST http://127.0.0.1:5001/prompt \
  -H "Content-Type: application/json" \
  -d '{"prompt": "Add a hello world function to main.py"}'
```

**Request:**

```json
{
  "prompt": "Add a hello world function to main.py",
  "working_dir": "/path/to/project"
}
```

**Response:**

```json
{
  "output": "I've added a hello_world function to main.py..."
}
```

### POST /shell

Execute a shell command directly (bypasses AI agent).

```bash
curl -X POST http://127.0.0.1:5001/shell \
  -H "Content-Type: application/json" \
  -d '{"cmd": "npm test"}'
```

### GET /agents

List available AI agents.

```bash
curl http://127.0.0.1:5001/agents
```

**Response:**

```json
{
  "agents": [
    {"name": "claude", "cmd": "npx", "active": true},
    {"name": "codex", "cmd": "npx", "active": false},
    {"name": "aider", "cmd": "aider", "active": false}
  ],
  "current": "claude"
}
```

### GET /status

Get agent status.

```bash
curl http://127.0.0.1:5001/status
```

## Broker Task Format

When the PHP broker sends tasks, it can specify the type:

```json
{
  "id": 123,
  "command": "Fix the bug in auth.py",
  "type": "prompt",
  "working_dir": "/var/www/myproject"
}
```

| Type     | Behavior                           |
|----------|-----------------------------------|
| `prompt` | Send to AI agent (default)        |
| `shell`  | Execute as shell command          |

## PowerShell / Windows

On Windows, use PowerShell to manage the agent:

**Start agent:**

```powershell
cd agent
python agent.py
```

**Start in new window:**

```powershell
Start-Process powershell -ArgumentList "python agent.py"
```

**As background job:**

```powershell
Start-Job { python agent.py }
```

## Docker

```dockerfile
FROM python:3.11-slim

RUN apt-get update && apt-get install -y nodejs npm
RUN npm install -g @anthropic-ai/claude-code

WORKDIR /app
COPY agent/ .
RUN pip install -r requirements.txt

ENV ANTHROPIC_API_KEY=""
ENV AI_AGENT=claude

CMD ["python", "agent.py"]
```

## Custom AI Agent

To use a custom AI agent:

1. Set `ai_agent` to `custom`
2. Set `ai_agent_path` to your agent binary

```json
{
  "ai_agent": "custom",
  "ai_agent_path": "/usr/local/bin/my-agent"
}
```

The agent will be called with the prompt as the first argument:

```bash
/usr/local/bin/my-agent "Your prompt here"
```
