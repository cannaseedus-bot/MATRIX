#!/usr/bin/env node

/**
 * MX2LM Server CLI
 *
 * Usage:
 *   mx2lm server start   - Launch server in new terminal
 *   mx2lm server stop    - Stop running server
 *   mx2lm server status  - Show server health
 *   mx2lm server ping    - Check if server is reachable
 */

import { start, stop, getLifecycleState } from "./lifecycle.js";
import { status, health, ping } from "./status.js";

const cmd = process.argv[2];

async function main() {
  switch (cmd) {
    case "start":
      start();
      break;

    case "stop":
      stop();
      break;

    case "status": {
      const lifecycle = getLifecycleState();
      const serverStatus = await status();

      console.log("=== MX2LM Server Status ===");
      console.log("Process running:", lifecycle.running);
      if (lifecycle.pid) console.log("PID:", lifecycle.pid);
      console.log("π support:", lifecycle.decay.piSupport.toFixed(2));
      console.log("Crash count:", lifecycle.decay.crashCount);
      console.log("Restart policy:", lifecycle.decay.restartPolicy);
      console.log("");

      if (serverStatus.error) {
        console.log("Server:", serverStatus.error);
      } else {
        console.log("Uptime:", serverStatus.uptime, "s");
        console.log("Requests:", serverStatus.requests);
        console.log("Healthy:", serverStatus.healthy);
      }
      break;
    }

    case "ping": {
      const result = await ping();
      console.log("Reachable:", result.reachable);
      if (result.latency !== null) {
        console.log("Latency:", result.latency, "ms");
      }
      break;
    }

    case "health": {
      const h = await health();
      console.log(JSON.stringify(h, null, 2));
      break;
    }

    default:
      console.log("MX2LM Server CLI");
      console.log("");
      console.log("Commands:");
      console.log("  start   - Launch server in new terminal");
      console.log("  stop    - Stop running server");
      console.log("  status  - Show server health and lifecycle");
      console.log("  ping    - Check if server is reachable");
      console.log("  health  - Get health endpoint response");
  }
}

main().catch(console.error);
