/**
 * MX2LM Server - Lifecycle Controller
 *
 * Manages server start/stop with π-decay governed restarts.
 */

import { spawnServer } from "./spawn.js";
import { onCrash, allowRestart, getState } from "./decay.js";

let proc = null;
let pid = null;

/**
 * Start the server in a new terminal
 */
export function start() {
  if (proc) {
    console.log("Server already running (PID: " + pid + ")");
    return false;
  }

  proc = spawnServer();
  pid = proc.pid;
  proc.unref();

  console.log("Server started (PID: " + pid + ")");
  return true;
}

/**
 * Stop the running server
 */
export function stop() {
  if (!proc) {
    console.log("No server running");
    return false;
  }

  try {
    process.kill(-proc.pid);
  } catch (e) {
    try {
      process.kill(proc.pid);
    } catch {
      // Already dead
    }
  }

  console.log("Server stopped (PID: " + pid + ")");
  proc = null;
  pid = null;
  return true;
}

/**
 * Handle server crash with π-decay restart logic
 */
export function crashed() {
  onCrash();
  const mode = allowRestart();
  const state = getState();

  console.log("Server crashed. π support: " + state.piSupport.toFixed(2));
  console.log("Restart policy: " + mode);

  proc = null;
  pid = null;

  switch (mode) {
    case "SUPPRESS":
      console.log("Restart suppressed (π too low)");
      return;
    case "ONCE":
      console.log("Attempting single restart...");
      start();
      return;
    case "BACKOFF":
      console.log("Restarting with 3s backoff...");
      setTimeout(start, 3000);
      return;
    case "IMMEDIATE":
      console.log("Immediate restart...");
      start();
      return;
  }
}

/**
 * Check if server is running
 */
export function isRunning() {
  return proc !== null;
}

/**
 * Get current lifecycle state
 */
export function getLifecycleState() {
  return {
    running: isRunning(),
    pid,
    decay: getState()
  };
}
