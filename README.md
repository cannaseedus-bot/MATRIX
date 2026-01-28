<img src="assets/logo.svg" width="200">

# MATRIX

**MATRIX** is a distributed task execution system with multiple components:

- **PHP Broker API** - REST API that queues and distributes tasks to agents
- **Python Agent** - Background worker that polls the broker and executes commands
- **PWA Frontend** - Web interface for managing tasks and connecting to agents
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
├── pwa/                    # Progressive Web App
│   ├── index.html          # Main PWA interface
│   ├── manifest.json       # PWA manifest
│   └── sw.js               # Service worker
│
├── inference/              # Inference Plains Implementation
│   └── asx_inference_cli.py # ASX tokenizer CLI
│
├── specs/                  # Language Specifications
│   └── mxs/                # MXS stylesheet specs
│       ├── browser.mxs     # Browser target spec
│       └── server.mxs      # Server target spec
│
├── docs/                   # Documentation
│   ├── mxs.md              # MXS specification guide
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

## Components

### MXS (MATRIX Stylesheets)

A CSS-like declarative language for specifying GPU execution targets. See [docs/mxs.md](docs/mxs.md) for the full specification.

### Inference Plains

A math word problem solver that tokenizes natural language into matrix operations. See [docs/inference-plains.md](docs/inference-plains.md) for details.

### Experimental: Kuhul

The `experimental/kuhul/` directory contains a proof-of-concept for symbolic computation and pi solving. This code is experimental and not production-ready.

## License

UNLICENSED
