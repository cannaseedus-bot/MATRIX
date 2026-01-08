import os
import threading
import time
import subprocess

import requests
from flask import Flask, jsonify, request

BROKER_URL = os.getenv("BROKER_URL", "https://yourdomain.com/api")
AGENT_NAME = os.getenv("AGENT_NAME", "Agent1")
API_KEY = os.getenv("API_KEY")
LOCAL_TOKEN = os.getenv("LOCAL_TOKEN")
POLL_INTERVAL = float(os.getenv("POLL_INTERVAL", "5"))
COMMAND_TIMEOUT = int(os.getenv("COMMAND_TIMEOUT", "60"))
AGENT_HOST = os.getenv("AGENT_HOST", "127.0.0.1")

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


def _headers():
    if not API_KEY:
        return {}
    return {"X-API-KEY": API_KEY}


def _authorized() -> bool:
    if not LOCAL_TOKEN:
        return True
    provided = request.headers.get("X-LOCAL-TOKEN")
    return provided == LOCAL_TOKEN


def run_local_command(cmd: str) -> tuple[str, str]:
    try:
        completed = subprocess.run(
            cmd,
            shell=True,
            check=False,
            capture_output=True,
            text=True,
            timeout=COMMAND_TIMEOUT,
        )
    except subprocess.TimeoutExpired:
        return "", "Command timed out"

    output = completed.stdout.strip()
    error = completed.stderr.strip()
    if completed.returncode != 0:
        return output, error or f"Command failed with code {completed.returncode}"

    return output, ""


@app.route("/run", methods=["POST", "OPTIONS"])
def run_command():
    if not _authorized():
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
