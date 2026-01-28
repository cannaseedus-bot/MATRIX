<img src="assets/logo.svg" width="200">

# MATRIX

**MATRIX** is a distributed task execution system with multiple components:

- **PHP Broker API** - REST API that queues and distributes tasks to agents
- **Python Agent** - Background worker that polls the broker and executes commands
- **PWA Frontend** - Web interface for managing tasks and connecting to agents
- **MX2LM CLI** - KUHUL-controlled host orchestrator with π-decay lifecycle management
- **MATRIX Stylesheets (MXS)** - Declarative language specification for GPU execution
- **Inference Plains** - Math word problem solver using tokenization and matrix operations

## Project Structure

```
MATRIX/
├── api/                    # PHP Broker API
│   ├── agents.php          # Agent management endpoint
│   ├── tasks.php           # Task listing endpoint
│   ├── add_task.php        # Create new tasks
│   ├── get_task.php        # Agent task polling
│   ├── submit_result.php   # Task result submission
│   ├── auth.php            # Authentication helpers
│   ├── guard.php           # Request authorization
│   ├── db.php              # Database connection
│   └── schema.sql          # MySQL schema
│
├── agent/                  # Python Agent
│   ├── agent.py            # Main agent script
│   ├── agent_config.json   # Agent configuration
│   └── requirements.txt    # Python dependencies
│
├── cli/                    # MX2LM CLI
│   ├── server.khl          # Server runtime definition
│   ├── server.bat          # Windows launcher
│   ├── server/             # Server CLI module
│   │   ├── index.js        # CLI entry point
│   │   ├── lifecycle.js    # Start/stop/restart
│   │   ├── decay.js        # π-decay engine
│   │   ├── spawn.js        # Process spawning
│   │   └── status.js       # Health checks
│   └── ui/                 # CSS Micronaut UI
│       ├── micronaut.css   # Reactive CSS panel
│       └── ws-bind.js      # WebSocket binding
│
├── pwa/                    # Progressive Web App
│   ├── index.html          # Main PWA interface
│   ├── manifest.json       # PWA manifest
│   └── sw.js               # Service worker
│
├── inference/              # Inference Plains Implementation
│   └── asx_inference_cli.py # ASX tokenizer CLI
│
├── specs/                  # Language Specifications
│   ├── mxs/                # MXS stylesheet specs
│   │   ├── browser.mxs     # Browser target spec
│   │   └── server.mxs      # Server target spec
│   └── server/             # Server runtime specs
│       └── mx2lm.server.schema.xjson
│
├── docs/                   # Documentation
│   ├── mxs.md              # MXS specification guide
│   ├── mx2lm-cli.md        # MX2LM CLI specification
│   └── inference-plains.md # Inference Plains documentation
│
├── lib/                    # Shared PHP utilities
│   └── helpers.php         # HTML escape helpers
│
├── assets/                 # Branding assets
│   ├── logo.svg            # MATRIX logo
│   └── app.kuhul.svg       # Kuhul app icon
│
├── experimental/           # Experimental/POC code
│   └── kuhul/              # Kuhul symbolic computation POC
│
├── config.php              # PHP configuration template
├── index.php               # PWA entry point (PHP hosting)
└── package.json            # NPM package metadata
```

## Quick Start

### PHP Broker API

1. Upload `api/` folder and `config.php` to your web server
2. Edit `config.php` with your database credentials and optional API key
3. Import `api/schema.sql` into your MySQL database
4. Verify at `https://yourdomain.com/api/agents.php`

### PWA Frontend

1. Upload `index.php` (PHP hosting) or `pwa/index.html` (static hosting)
2. Configure `domain` and `api_base` in `config.php`
3. Enter your API key in the PWA if authentication is enabled

### Python Agent

1. Install dependencies:
   ```bash
   pip install -r agent/requirements.txt
   ```

2. Configure via environment variables or `agent/agent_config.json`:
   - `BROKER_URL` - API base URL (e.g., `https://yourdomain.com/api`)
   - `AGENT_NAME` - Agent identifier
   - `API_KEY` - API key (if enabled on broker)
   - `LOCAL_API_TOKEN` - Token for local PWA access
   - `LOCAL_ORIGIN` - CORS origin (default: `*`)

3. Run the agent:
   ```bash
   python agent/agent.py
   ```

4. Connect from the PWA to `http://127.0.0.1:5001`

### MX2LM CLI

The MX2LM CLI provides KUHUL-controlled server lifecycle management with π-decay restart logic.

**Commands:**

```bash
mx2lm server start    # Launch server in new terminal
mx2lm server stop     # Stop running server
mx2lm server status   # Show server health and π-support
mx2lm server ping     # Check if server is reachable
```

**Run directly:**

```bash
node cli/server/index.js start
node cli/server/index.js status
```

**Windows:**

```batch
cli\server.bat
```

See [docs/mx2lm-cli.md](docs/mx2lm-cli.md) for the full CLI specification.

## Components

### MX2LM CLI

A KUHUL-controlled host orchestrator that manages server lifecycle with π-decay stabilization:

- **π-decay engine** - Restart permission decays on crashes
- **WebSocket status** - Real-time server telemetry
- **CSS micronauts** - Projection-only reactive UI

| π Support | Restart Policy       |
|-----------|----------------------|
| `< 0.4`   | Suppress restart     |
| `0.4–0.7` | Restart once         |
| `> 0.7`   | Restart with backoff |
| `> 0.9`   | Immediate heal       |

### MXS (MATRIX Stylesheets)

A CSS-like declarative language for specifying GPU execution targets. See [docs/mxs.md](docs/mxs.md) for the full specification.

### Inference Plains

A math word problem solver that tokenizes natural language into matrix operations. See [docs/inference-plains.md](docs/inference-plains.md) for details.

### Experimental: Kuhul

The `experimental/kuhul/` directory contains a proof-of-concept for symbolic computation and pi solving. This code is experimental and not production-ready.

## Architecture

```
KUHUL π (intent physics)
    ↓
KUHUL Class (capability domain)
    ↓
XCFE (legality)
    ↓
Micronaut Cluster (aggregation)
    ↓
MX2LM CLI (host adapter)
    ↓
Host (PowerShell / OS)
```

| Layer          | Role           |
|----------------|----------------|
| MX2LM CLI      | The shell      |
| KUHUL          | The physics    |
| CSS micronauts | The nerves     |
| PowerShell     | Muscle         |
| XCFE           | Law            |

## License

UNLICENSED
