/**
 * MX2LM Server - Process Spawn
 *
 * Launches server in a new terminal window.
 * Platform-specific implementations.
 */

import { spawn } from "node:child_process";
import { platform } from "node:os";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const SERVER_PATH = join(__dirname, "..", "server.khl");

/**
 * Spawn server in new terminal window (Windows)
 */
function spawnWindows() {
  return spawn("cmd.exe", ["/c", "start", "MX2LM Server", "node", SERVER_PATH], {
    detached: true,
    stdio: "ignore"
  });
}

/**
 * Spawn server in new terminal window (macOS)
 */
function spawnMac() {
  return spawn("open", ["-a", "Terminal", SERVER_PATH], {
    detached: true,
    stdio: "ignore"
  });
}

/**
 * Spawn server in new terminal window (Linux)
 */
function spawnLinux() {
  // Try common terminal emulators
  const terminals = [
    ["x-terminal-emulator", "-e"],
    ["gnome-terminal", "--"],
    ["konsole", "-e"],
    ["xterm", "-e"]
  ];

  for (const [term, flag] of terminals) {
    try {
      return spawn(term, [flag, "node", SERVER_PATH], {
        detached: true,
        stdio: "ignore"
      });
    } catch {
      continue;
    }
  }

  // Fallback: run in background without terminal
  return spawn("node", [SERVER_PATH], {
    detached: true,
    stdio: "ignore"
  });
}

/**
 * Spawn server in new terminal (cross-platform)
 */
export function spawnServer() {
  const os = platform();

  if (os === "win32") return spawnWindows();
  if (os === "darwin") return spawnMac();
  return spawnLinux();
}

/**
 * Spawn server using batch file (Windows only)
 */
export function spawnViaBat() {
  const batPath = join(__dirname, "..", "server.bat");
  return spawn("cmd.exe", ["/c", "start", batPath], {
    detached: true,
    stdio: "ignore"
  });
}
