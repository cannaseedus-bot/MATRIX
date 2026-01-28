/**
 * MX2LM WebSocket → CSS Micronaut Binding
 *
 * Projection glue only - no logic decisions.
 * Receives server snapshots and updates CSS variables.
 */

const SERVER_WS = "ws://127.0.0.1:4141/ws/status";

let ws = null;
let reconnectAttempts = 0;
const MAX_RECONNECT = 5;
const RECONNECT_DELAY = 3000;

/**
 * Set a CSS variable on document root
 */
function setVar(name, value) {
  document.documentElement.style.setProperty("--mx2lm-" + name, value);
}

/**
 * Update CSS variables from server snapshot
 */
function applySnapshot(snapshot) {
  const health = snapshot.healthy ? 1 : 0.2;
  const traffic = Math.min(1, snapshot.requests / 100);
  const glow = Math.max(health, traffic);

  setVar("health", health);
  setVar("uptime", snapshot.uptime);
  setVar("traffic", traffic);
  setVar("glow", glow);

  // Calculate drift (time since last tick)
  if (snapshot.ts) {
    const drift = Math.min(1, (Date.now() - snapshot.ts) / 5000);
    setVar("drift", drift);
  }
}

/**
 * Connect to WebSocket server
 */
function connect() {
  if (ws && ws.readyState === WebSocket.OPEN) return;

  ws = new WebSocket(SERVER_WS);

  ws.onopen = () => {
    console.log("[MX2LM] WebSocket connected");
    reconnectAttempts = 0;
    setVar("alert", 0);
  };

  ws.onmessage = (event) => {
    try {
      const snapshot = JSON.parse(event.data);
      applySnapshot(snapshot);
    } catch (e) {
      console.error("[MX2LM] Invalid snapshot:", e);
    }
  };

  ws.onclose = () => {
    console.log("[MX2LM] WebSocket closed");
    setVar("health", 0.2);
    setVar("alert", 1);
    scheduleReconnect();
  };

  ws.onerror = (error) => {
    console.error("[MX2LM] WebSocket error:", error);
    setVar("alert", 1);
  };
}

/**
 * Schedule reconnection with backoff
 */
function scheduleReconnect() {
  if (reconnectAttempts >= MAX_RECONNECT) {
    console.log("[MX2LM] Max reconnect attempts reached");
    return;
  }

  reconnectAttempts++;
  const delay = RECONNECT_DELAY * reconnectAttempts;
  console.log("[MX2LM] Reconnecting in " + delay + "ms...");

  setTimeout(connect, delay);
}

/**
 * Disconnect WebSocket
 */
function disconnect() {
  if (ws) {
    ws.close();
    ws = null;
  }
}

/**
 * Initialize binding
 */
function init() {
  connect();

  // Expose for debugging
  window.mx2lmWs = {
    connect,
    disconnect,
    getSocket: () => ws
  };
}

// Auto-init when DOM ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", init);
} else {
  init();
}

export { connect, disconnect, applySnapshot };
