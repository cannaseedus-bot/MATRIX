import json
import os
import shlex
import threading
import time
import subprocess
from pathlib import Path

import requests
from flask import Flask, jsonify, request

BROKER_URL = os.getenv("BROKER_URL", "https://yourdomain.com/api")
AGENT_NAME = os.getenv("AGENT_NAME", "Agent1")
API_KEY = os.getenv("API_KEY")
LOCAL_TOKEN = os.getenv("LOCAL_TOKEN")
POLL_INTERVAL = float(os.getenv("POLL_INTERVAL", "5"))
COMMAND_TIMEOUT = int(os.getenv("COMMAND_TIMEOUT", "60"))
AGENT_HOST = os.getenv("AGENT_HOST", "127.0.0.1")
LOCAL_ORIGIN = os.getenv("LOCAL_ORIGIN", "*")
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


BROKER_URL = _config_value("broker_url", "https://yourdomain.com/api")
AGENT_NAME = _config_value("agent_name", "Agent1")
API_KEY = _config_value("api_key", "")
LOCAL_API_TOKEN = _config_value("local_api_token", "")
POLL_INTERVAL = float(_config_value("poll_interval", "5"))
COMMAND_TIMEOUT = int(_config_value("command_timeout", "60"))
ALLOW_SHELL = str(_config_value("allow_shell", "false")).lower() in {"1", "true", "yes"}
ALLOWED_COMMANDS = _CONFIG.get("allowed_commands", [])
LISTEN_HOST = _config_value("listen_host", "0.0.0.0")
LISTEN_PORT = int(_config_value("listen_port", "5001"))

app = Flask(__name__)


def _corsify(response):
    response.headers["Access-Control-Allow-Origin"] = "*"
    response.headers["Access-Control-Allow-Headers"] = "Content-Type, X-LOCAL-TOKEN"
    response.headers["Access-Control-Allow-Methods"] = "GET, POST, OPTIONS"
    return response


@app.before_request
def handle_preflight():
    if request.method == "OPTIONS":
        return _corsify(app.response_class())
@app.after_request
def add_cors_headers(response):
    response.headers["Access-Control-Allow-Origin"] = "*"
@app.before_request
def handle_preflight():
    if request.method == "OPTIONS":
        return ("", 204)


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


def _authorized() -> bool:
    if not LOCAL_TOKEN:
        return True
    provided = request.headers.get("X-LOCAL-TOKEN")
    return provided == LOCAL_TOKEN
def _authorize_local() -> bool:
    if not LOCAL_API_TOKEN:
        return True
    provided = request.headers.get("X-LOCAL-TOKEN")
    return bool(provided and provided == LOCAL_API_TOKEN)


def _command_allowed(cmd: str) -> bool:
    if not ALLOWED_COMMANDS:
        return True
    try:
        parts = shlex.split(cmd)
    except ValueError:
        return False
    if not parts:
        return False
    return parts[0] in ALLOWED_COMMANDS


def run_local_command(cmd: str) -> tuple[str, str]:
    if not _command_allowed(cmd):
        return "", "Command not in allowlist"

    try:
        if ALLOW_SHELL:
            completed = subprocess.run(
                cmd,
                shell=True,
                check=False,
                capture_output=True,
                text=True,
                timeout=COMMAND_TIMEOUT,
            )
        else:
            completed = subprocess.run(
                shlex.split(cmd),
                shell=False,
                check=False,
                capture_output=True,
                text=True,
                timeout=COMMAND_TIMEOUT,
            )
    except subprocess.TimeoutExpired:
        return "", "Command timed out"
    except ValueError as exc:
        return "", f"Invalid command: {exc}"

    output = completed.stdout.strip()
    error = completed.stderr.strip()
    if completed.returncode != 0:
        return output, error or f"Command failed with code {completed.returncode}"

    return output, ""


@app.route("/run", methods=["POST", "OPTIONS"])
def run_command():
    if not _authorized():
@app.after_request
def add_cors_headers(response):
    response.headers["Access-Control-Allow-Origin"] = "*"
    response.headers["Access-Control-Allow-Headers"] = "Content-Type, X-LOCAL-TOKEN"
    response.headers["Access-Control-Allow-Methods"] = "GET, POST, OPTIONS"
    return response


@app.route("/status", methods=["GET"])
def status():
    if not _authorize_local():
        return jsonify({"error": "Unauthorized"}), 401
    return jsonify(
        {
            "agent_name": AGENT_NAME,
            "broker_url": BROKER_URL,
            "allow_shell": ALLOW_SHELL,
            "allowed_commands": ALLOWED_COMMANDS,
            "poll_interval": POLL_INTERVAL,
        }
    )


@app.route("/run", methods=["POST"])
def run_command():
    if not _authorized():
    if not _authorize_local():
        return jsonify({"error": "Unauthorized"}), 401
    data = request.get_json(silent=True) or {}
    cmd = data.get("cmd")
    if not cmd:
        return jsonify({"error": "Missing cmd"}), 400

    output, error = run_local_command(cmd)
    if error:
        return jsonify({"error": error, "output": output}), 400

    return jsonify({"output": output})


@app.route("/status", methods=["GET", "OPTIONS"])
@app.route("/status", methods=["GET"])
def status():
    if not _authorized():
        return jsonify({"error": "Unauthorized"}), 401
    return jsonify(
        {
            "agent_name": AGENT_NAME,
            "broker_url": BROKER_URL,
            "poll_interval": POLL_INTERVAL,
        }
    )


@app.after_request
def apply_cors(response):
    return _corsify(response)


def poll_broker():
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
                output, error = run_local_command(task["command"])
                status = "error" if error else "done"
                result = error if error else output
                requests.post(
                    f"{BROKER_URL}/submit_result.php",
                    json={"task_id": task["id"], "result": result, "status": status},
                    headers=_headers(),
                    timeout=15,
                )
        except requests.RequestException as exc:
            print("Error polling broker:", exc)
        time.sleep(POLL_INTERVAL)


if __name__ == "__main__":
    thread = threading.Thread(target=poll_broker, daemon=True)
    thread.start()
    app.run(host=AGENT_HOST, port=int(os.getenv("AGENT_PORT", "5001")))
    app.run(host=LISTEN_HOST, port=LISTEN_PORT)
