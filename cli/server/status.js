/**
 * MX2LM Server - Status & Health Check
 *
 * Queries running server for status information.
 */

const SERVER_URL = "http://127.0.0.1:4141";

/**
 * Get server status
 */
export async function status() {
  try {
    const res = await fetch(SERVER_URL + "/status");
    if (!res.ok) throw new Error("HTTP " + res.status);
    return await res.json();
  } catch (e) {
    return {
      error: "Server not reachable",
      message: e.message
    };
  }
}

/**
 * Get server health
 */
export async function health() {
  try {
    const res = await fetch(SERVER_URL + "/health");
    if (!res.ok) throw new Error("HTTP " + res.status);
    return await res.json();
  } catch (e) {
    return {
      healthy: false,
      error: e.message
    };
  }
}

/**
 * Get root info
 */
export async function info() {
  try {
    const res = await fetch(SERVER_URL + "/");
    if (!res.ok) throw new Error("HTTP " + res.status);
    return await res.json();
  } catch (e) {
    return {
      error: "Server not reachable",
      message: e.message
    };
  }
}

/**
 * Check if server is responding
 */
export async function ping() {
  try {
    const start = Date.now();
    const res = await fetch(SERVER_URL + "/health");
    const latency = Date.now() - start;
    return {
      reachable: res.ok,
      latency
    };
  } catch {
    return {
      reachable: false,
      latency: null
    };
  }
}
