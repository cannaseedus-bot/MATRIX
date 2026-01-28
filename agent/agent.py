import json
import os
import shlex
import threading
import time
import subprocess
from pathlib import Path

import requests
from flask import Flask, jsonify, request

CONFIG_PATH = Path(__file__).with_name("agent_config.json")


def _load_config() -> dict:
    if not CONFIG_PATH.exists():
        return {}
    with CONFIG_PATH.open("r", encoding="utf-8") as handle:
        return json.load(handle)


_CONFIG = _load_config()


def _config_value(name: str, default):
    env_value = os.getenv(name.upper())
    if env_value is not None:
        return env_value
    return _CONFIG.get(name.lower(), default)


# Broker configuration
BROKER_URL = _config_value("broker_url", "https://yourdomain.com/api")
AGENT_NAME = _config_value("agent_name", "Agent1")
API_KEY = _config_value("api_key", "")
LOCAL_API_TOKEN = _config_value("local_api_token", "")
POLL_INTERVAL = float(_config_value("poll_interval", "5"))
COMMAND_TIMEOUT = int(_config_value("command_timeout", "300"))
LISTEN_HOST = _config_value("listen_host", "127.0.0.1")
LISTEN_PORT = int(_config_value("listen_port", "5001"))
LOCAL_ORIGIN = _config_value("local_origin", "*")

# AI Agent configuration
AI_AGENT = _config_value("ai_agent", "claude")  # claude, codex, ollama, ollama-cloud, custom
AI_AGENT_PATH = _config_value("ai_agent_path", "")  # custom path to agent binary
WORKING_DIR = _config_value("working_dir", os.getcwd())

# Ollama configuration
OLLAMA_MODEL = _config_value("ollama_model", "llama3.2")
OLLAMA_HOST = _config_value("ollama_host", "http://localhost:11434")
OLLAMA_CLOUD_HOST = "https://ollama.com"

# Agent command templates (for CLI-based agents)
AI_AGENTS = {
    "claude": {
        "cmd": ["npx", "@anthropic-ai/claude-code", "--print", "--dangerously-skip-permissions"],
        "env": {"ANTHROPIC_API_KEY": os.getenv("ANTHROPIC_API_KEY", "")},
        "type": "cli",
    },
    "codex": {
        "cmd": ["npx", "codex", "--quiet", "--approval-mode", "full-auto"],
        "env": {"OPENAI_API_KEY": os.getenv("OPENAI_API_KEY", "")},
        "type": "cli",
    },
    "aider": {
        "cmd": ["aider", "--yes", "--no-git"],
        "env": {},
        "type": "cli",
    },
    "ollama": {
        "type": "api",
        "host": OLLAMA_HOST,
        "model": OLLAMA_MODEL,
    },
    "ollama-cloud": {
        "type": "api",
        "host": OLLAMA_CLOUD_HOST,
        "model": OLLAMA_MODEL,
        "env": {"OLLAMA_API_KEY": os.getenv("OLLAMA_API_KEY", "")},
    },
}

app = Flask(__name__)


@app.after_request
def add_cors_headers(response):
    response.headers["Access-Control-Allow-Origin"] = LOCAL_ORIGIN
    response.headers["Access-Control-Allow-Headers"] = "Content-Type, X-LOCAL-TOKEN"
    response.headers["Access-Control-Allow-Methods"] = "GET, POST, OPTIONS"
    return response


@app.before_request
def handle_preflight():
    if request.method == "OPTIONS":
        return ("", 204)


def _headers():
    if not API_KEY:
        return {}
    return {"X-API-KEY": API_KEY}


def _authorize_local() -> bool:
    if not LOCAL_API_TOKEN:
        return True
    provided = request.headers.get("X-LOCAL-TOKEN")
    return bool(provided and provided == LOCAL_API_TOKEN)


def run_ollama_api(prompt: str, model: str = None, host: str = None, cloud: bool = False) -> tuple[str, str]:
    """
    Execute prompt via Ollama API (local or cloud).
    Returns (output, error).
    """
    model = model or OLLAMA_MODEL
    host = host or (OLLAMA_CLOUD_HOST if cloud else OLLAMA_HOST)

    # Build request
    url = f"{host}/api/chat"
    headers = {"Content-Type": "application/json"}

    # Add auth for cloud
    if cloud:
        api_key = os.getenv("OLLAMA_API_KEY", "")
        if api_key:
            headers["Authorization"] = f"Bearer {api_key}"

    payload = {
        "model": model,
        "messages": [{"role": "user", "content": prompt}],
        "stream": False,
    }

    try:
        response = requests.post(url, json=payload, headers=headers, timeout=COMMAND_TIMEOUT)
        response.raise_for_status()

        data = response.json()
        content = data.get("message", {}).get("content", "")

        return content, ""

    except requests.exceptions.ConnectionError:
        if not cloud:
            return "", f"Ollama not running. Start with: ollama serve"
        return "", "Could not connect to Ollama cloud"
    except requests.exceptions.Timeout:
        return "", "Ollama request timed out"
    except requests.exceptions.HTTPError as e:
        return "", f"Ollama API error: {e.response.status_code}"
    except Exception as e:
        return "", f"Ollama error: {str(e)}"


def run_ollama_cli(prompt: str, model: str = None) -> tuple[str, str]:
    """
    Execute prompt via Ollama CLI.
    Returns (output, error).
    """
    model = model or OLLAMA_MODEL

    try:
        completed = subprocess.run(
            ["ollama", "run", model, prompt],
            capture_output=True,
            text=True,
            timeout=COMMAND_TIMEOUT,
            cwd=WORKING_DIR,
        )

        output = completed.stdout.strip()
        error = completed.stderr.strip()

        if completed.returncode != 0:
            if "not found" in error.lower():
                return "", f"Model '{model}' not found. Pull with: ollama pull {model}"
            return output, error or f"Ollama failed with code {completed.returncode}"

        return output, ""

    except FileNotFoundError:
        return "", "Ollama CLI not found. Install from: https://ollama.com"
    except subprocess.TimeoutExpired:
        return "", "Ollama request timed out"
    except Exception as e:
        return "", f"Ollama error: {str(e)}"


def get_agent_command(prompt: str) -> tuple[list, dict]:
    """Get the command and env for CLI-based AI agents."""
    if AI_AGENT == "custom" and AI_AGENT_PATH:
        return [AI_AGENT_PATH, prompt], {}

    agent_config = AI_AGENTS.get(AI_AGENT)
    if not agent_config:
        raise ValueError(f"Unknown AI agent: {AI_AGENT}")

    if agent_config.get("type") != "cli":
        raise ValueError(f"Agent {AI_AGENT} is not CLI-based")

    cmd = agent_config["cmd"] + [prompt]
    env = {**os.environ, **agent_config.get("env", {})}

    return cmd, env


def run_ai_agent(prompt: str, working_dir: str = None) -> tuple[str, str]:
    """
    Execute an AI code agent with the given prompt.
    Returns (output, error).
    """
    agent_config = AI_AGENTS.get(AI_AGENT, {})
    agent_type = agent_config.get("type", "cli")

    # Handle Ollama API-based agents
    if AI_AGENT == "ollama":
        return run_ollama_api(prompt, cloud=False)
    elif AI_AGENT == "ollama-cloud":
        return run_ollama_api(prompt, cloud=True)
    elif agent_type == "api":
        # Generic API agent (future extensibility)
        return "", f"API agent {AI_AGENT} not implemented"

    # Handle CLI-based agents
    try:
        cmd, env = get_agent_command(prompt)
    except ValueError as e:
        return "", str(e)

    cwd = working_dir or WORKING_DIR

    try:
        completed = subprocess.run(
            cmd,
            cwd=cwd,
            env=env,
            capture_output=True,
            text=True,
            timeout=COMMAND_TIMEOUT,
        )
    except subprocess.TimeoutExpired:
        return "", "AI agent timed out"
    except FileNotFoundError:
        return "", f"AI agent not found: {cmd[0]}. Install with: npm i -g @anthropic-ai/claude-code"
    except Exception as exc:
        return "", f"AI agent error: {exc}"

    output = completed.stdout.strip()
    error = completed.stderr.strip()

    if completed.returncode != 0:
        # Some agents output to stderr for progress, check if there's actual output
        if output:
            return output, ""
        return output, error or f"AI agent failed with code {completed.returncode}"

    return output, ""


def run_shell_command(cmd: str) -> tuple[str, str]:
    """Execute a shell command directly."""
    try:
        completed = subprocess.run(
            cmd,
            shell=True,
            capture_output=True,
            text=True,
            timeout=COMMAND_TIMEOUT,
            cwd=WORKING_DIR,
        )
    except subprocess.TimeoutExpired:
        return "", "Command timed out"
    except Exception as exc:
        return "", f"Command error: {exc}"

    output = completed.stdout.strip()
    error = completed.stderr.strip()

    if completed.returncode != 0:
        return output, error or f"Command failed with code {completed.returncode}"

    return output, ""


@app.route("/status", methods=["GET", "OPTIONS"])
def status():
    if not _authorize_local():
        return jsonify({"error": "Unauthorized"}), 401

    status_info = {
        "agent_name": AGENT_NAME,
        "broker_url": BROKER_URL,
        "ai_agent": AI_AGENT,
        "working_dir": WORKING_DIR,
        "poll_interval": POLL_INTERVAL,
    }

    # Add Ollama-specific info
    if AI_AGENT in ("ollama", "ollama-cloud"):
        status_info["ollama_model"] = OLLAMA_MODEL
        status_info["ollama_host"] = OLLAMA_CLOUD_HOST if AI_AGENT == "ollama-cloud" else OLLAMA_HOST

    return jsonify(status_info)


@app.route("/prompt", methods=["POST", "OPTIONS"])
def prompt():
    """Send a prompt to the AI code agent."""
    if not _authorize_local():
        return jsonify({"error": "Unauthorized"}), 401

    data = request.get_json(silent=True) or {}
    prompt_text = data.get("prompt")
    working_dir = data.get("working_dir", WORKING_DIR)

    # Allow overriding model for Ollama
    model = data.get("model")

    if not prompt_text:
        return jsonify({"error": "Missing prompt"}), 400

    # Use specified model for Ollama if provided
    if AI_AGENT in ("ollama", "ollama-cloud") and model:
        output, error = run_ollama_api(
            prompt_text,
            model=model,
            cloud=(AI_AGENT == "ollama-cloud")
        )
    else:
        output, error = run_ai_agent(prompt_text, working_dir)

    if error:
        return jsonify({"error": error, "output": output}), 400

    return jsonify({"output": output})


@app.route("/ollama", methods=["POST", "OPTIONS"])
def ollama_direct():
    """Direct Ollama API endpoint (works regardless of configured agent)."""
    if not _authorize_local():
        return jsonify({"error": "Unauthorized"}), 401

    data = request.get_json(silent=True) or {}
    prompt_text = data.get("prompt")
    model = data.get("model", OLLAMA_MODEL)
    cloud = data.get("cloud", False)

    if not prompt_text:
        return jsonify({"error": "Missing prompt"}), 400

    output, error = run_ollama_api(prompt_text, model=model, cloud=cloud)

    if error:
        return jsonify({"error": error, "output": output}), 400

    return jsonify({"output": output, "model": model})


@app.route("/ollama/models", methods=["GET", "OPTIONS"])
def ollama_models():
    """List available Ollama models."""
    if not _authorize_local():
        return jsonify({"error": "Unauthorized"}), 401

    cloud = request.args.get("cloud", "false").lower() == "true"
    host = OLLAMA_CLOUD_HOST if cloud else OLLAMA_HOST

    headers = {}
    if cloud:
        api_key = os.getenv("OLLAMA_API_KEY", "")
        if api_key:
            headers["Authorization"] = f"Bearer {api_key}"

    try:
        response = requests.get(f"{host}/api/tags", headers=headers, timeout=10)
        response.raise_for_status()
        data = response.json()

        models = []
        for model in data.get("models", []):
            models.append({
                "name": model.get("name"),
                "size": model.get("size"),
                "modified": model.get("modified_at"),
            })

        return jsonify({"models": models, "host": host})

    except requests.exceptions.ConnectionError:
        return jsonify({"error": "Ollama not running", "models": []}), 503
    except Exception as e:
        return jsonify({"error": str(e), "models": []}), 500


@app.route("/shell", methods=["POST", "OPTIONS"])
def shell():
    """Execute a shell command directly."""
    if not _authorize_local():
        return jsonify({"error": "Unauthorized"}), 401

    data = request.get_json(silent=True) or {}
    cmd = data.get("cmd")

    if not cmd:
        return jsonify({"error": "Missing cmd"}), 400

    output, error = run_shell_command(cmd)

    if error:
        return jsonify({"error": error, "output": output}), 400

    return jsonify({"output": output})


@app.route("/agents", methods=["GET", "OPTIONS"])
def list_agents():
    """List available AI agents."""
    if not _authorize_local():
        return jsonify({"error": "Unauthorized"}), 401

    agents = []
    for name, config in AI_AGENTS.items():
        agent_info = {
            "name": name,
            "type": config.get("type", "cli"),
            "active": name == AI_AGENT,
        }

        if config.get("type") == "cli":
            agent_info["cmd"] = config["cmd"][0]
        elif config.get("type") == "api":
            agent_info["host"] = config.get("host", "")
            agent_info["model"] = config.get("model", "")

        agents.append(agent_info)

    return jsonify({"agents": agents, "current": AI_AGENT})


def poll_broker():
    """Poll the PHP broker for tasks and execute them via AI agent."""
    while True:
        try:
            response = requests.get(
                f"{BROKER_URL}/get_task.php",
                params={"agent": AGENT_NAME},
                headers=_headers(),
                timeout=15,
            )
            response.raise_for_status()
            task = response.json()

            if task and task.get("command"):
                command = task["command"]
                task_type = task.get("type", "prompt")
                working_dir = task.get("working_dir", WORKING_DIR)

                # Route to appropriate handler
                if task_type == "shell":
                    output, error = run_shell_command(command)
                elif task_type == "ollama":
                    # Direct Ollama task
                    model = task.get("model", OLLAMA_MODEL)
                    cloud = task.get("cloud", False)
                    output, error = run_ollama_api(command, model=model, cloud=cloud)
                else:
                    # Default: send to configured AI agent
                    output, error = run_ai_agent(command, working_dir)

                task_status = "error" if error else "done"
                result = error if error else output

                requests.post(
                    f"{BROKER_URL}/submit_result.php",
                    json={
                        "task_id": task["id"],
                        "result": result,
                        "status": task_status,
                    },
                    headers=_headers(),
                    timeout=15,
                )
        except requests.RequestException as exc:
            print("Error polling broker:", exc)
        time.sleep(POLL_INTERVAL)


if __name__ == "__main__":
    print(f"MATRIX Agent: {AGENT_NAME}")
    print(f"AI Agent: {AI_AGENT}")

    if AI_AGENT in ("ollama", "ollama-cloud"):
        print(f"Ollama Model: {OLLAMA_MODEL}")
        print(f"Ollama Host: {OLLAMA_CLOUD_HOST if AI_AGENT == 'ollama-cloud' else OLLAMA_HOST}")

    print(f"Working Dir: {WORKING_DIR}")
    print(f"Broker: {BROKER_URL}")
    print(f"Listening: http://{LISTEN_HOST}:{LISTEN_PORT}")
    print()

    thread = threading.Thread(target=poll_broker, daemon=True)
    thread.start()
    app.run(host=LISTEN_HOST, port=LISTEN_PORT)
