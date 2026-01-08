# MATRIX - Distributed PWA + Python Agent + PHP Broker Starter Kit

This repository contains a deployable starter kit for a distributed task runner:

- **PHP broker API** on shared hosting (task queue + agent registry).
- **Local Python agent** that polls the broker and can execute commands locally.
- **PWA UI** that submits tasks to the broker.

## Directory Layout

- `api/` - PHP broker endpoints and MySQL schema.
- `agent/` - Python agent and requirements.
- `pwa/` - Simple HTML UI for submitting tasks.

## PHP Broker Setup

1. Create a MySQL database and user.
2. Run the schema in `api/schema.sql`.
3. Upload the contents of `api/` to your shared hosting (e.g., `/public_html/api`).
4. Configure environment variables for your hosting provider:

```
DB_HOST=localhost
DB_NAME=matrix_broker
DB_USER=matrix_user
DB_PASS=matrix_pass
API_KEY=optional_shared_key
```

If `API_KEY` is set, clients must send it in the `X-API-KEY` header.

### Endpoints

- `POST /add_task.php`
- `GET /get_task.php?agent=Agent1`
- `POST /submit_result.php`
- `GET /agents.php`

## Python Agent Setup

1. Create a virtual environment and install requirements:

```
python -m venv .venv
. .venv/bin/activate
pip install -r agent/requirements.txt
```

2. Run the agent:

```
export BROKER_URL="https://yourdomain.com/api"
export AGENT_NAME="Agent1"
export API_KEY="optional_shared_key"
python agent/agent.py
```

The agent exposes a local endpoint at `http://127.0.0.1:5001/run` for direct PWA calls.

## PWA

Open `pwa/index.html` in a browser (or host it alongside the API). Update the broker URL and optional API key, then submit tasks.

## Security Notes

- Use HTTPS everywhere.
- Keep API keys private.
- Restrict what commands your agent is allowed to execute.
