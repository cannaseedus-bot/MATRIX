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
AI_AGENT = _config_value("ai_agent", "claude")  # claude, codex, custom
AI_AGENT_PATH = _config_value("ai_agent_path", "")  # custom path to agent binary
WORKING_DIR = _config_value("working_dir", os.getcwd())

# Agent command templates
AI_AGENTS = {
    "claude": {
        "cmd": ["npx", "@anthropic-ai/claude-code", "--print", "--dangerously-skip-permissions"],
        "env": {"ANTHROPIC_API_KEY": os.getenv("ANTHROPIC_API_KEY", "")},
    },
    "codex": {
        "cmd": ["npx", "codex", "--quiet", "--approval-mode", "full-auto"],
        "env": {"OPENAI_API_KEY": os.getenv("OPENAI_API_KEY", "")},
    },
    "aider": {
        "cmd": ["aider", "--yes", "--no-git"],
        "env": {},
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


def get_agent_command(prompt: str) -> tuple[list, dict]:
    """Get the command and env for the configured AI agent."""
    if AI_AGENT == "custom" and AI_AGENT_PATH:
        return [AI_AGENT_PATH, prompt], {}

    agent_config = AI_AGENTS.get(AI_AGENT)
    if not agent_config:
        raise ValueError(f"Unknown AI agent: {AI_AGENT}")

    cmd = agent_config["cmd"] + [prompt]
    env = {**os.environ, **agent_config["env"]}

    return cmd, env


def run_ai_agent(prompt: str, working_dir: str = None) -> tuple[str, str]:
    """
    Execute an AI code agent with the given prompt.
    Returns (output, error).
    """
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
    return jsonify({
        "agent_name": AGENT_NAME,
        "broker_url": BROKER_URL,
        "ai_agent": AI_AGENT,
        "working_dir": WORKING_DIR,
        "poll_interval": POLL_INTERVAL,
    })


@app.route("/prompt", methods=["POST", "OPTIONS"])
def prompt():
    """Send a prompt to the AI code agent."""
    if not _authorize_local():
        return jsonify({"error": "Unauthorized"}), 401

    data = request.get_json(silent=True) or {}
    prompt_text = data.get("prompt")
    working_dir = data.get("working_dir", WORKING_DIR)

    if not prompt_text:
        return jsonify({"error": "Missing prompt"}), 400

    output, error = run_ai_agent(prompt_text, working_dir)

    if error:
        return jsonify({"error": error, "output": output}), 400

    return jsonify({"output": output})


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
        agents.append({
            "name": name,
            "cmd": config["cmd"][0],
            "active": name == AI_AGENT,
        })

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
                else:
                    # Default: send to AI agent
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
    print(f"Working Dir: {WORKING_DIR}")
    print(f"Broker: {BROKER_URL}")
    print(f"Listening: http://{LISTEN_HOST}:{LISTEN_PORT}")
    print()

    thread = threading.Thread(target=poll_broker, daemon=True)
    thread.start()
    app.run(host=LISTEN_HOST, port=LISTEN_PORT)
