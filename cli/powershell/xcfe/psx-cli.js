#!/usr/bin/env node
/**
 * PSX CLI v1 - Governed PowerShell Execution
 *
 * XCFE-lawful PowerShell execution via PS-DSL intent files.
 * Deny-by-default: only allowlisted actions can execute.
 *
 * Usage:
 *   node psx-cli.js <intent.json>
 *   node psx-cli.js --list-actions
 *   node psx-cli.js --verify <intent.json>
 *
 * XCFE Class: host.powershell.cli
 */

import fs from "node:fs";
import { spawn } from "node:child_process";
import { verifyPSDSL, verifyEnvelope, ERROR_CODES } from "./ps-dsl-verifier.js";
import { wrapCommand, createAuditRecord, CM1 } from "./cm1-wrapper.js";
import { PS_COMMAND_REGISTRY } from "./ps-command-registry.js";

const USAGE = `
PSX CLI - Governed PowerShell Execution

Usage:
  node psx-cli.js <intent.json>      Execute a PS-DSL intent
  node psx-cli.js --list-actions     List allowed actions
  node psx-cli.js --verify <file>    Verify intent without executing
  node psx-cli.js --help             Show this help

Examples:
  node psx-cli.js process-list.json
  echo '{"@dsl":"ps-dsl.v1","action":"process.list"}' | node psx-cli.js -

XCFE Class: host.powershell.cli
`;

/**
 * Read JSON from file or stdin
 */
function readJSON(pathOrStdin) {
  if (pathOrStdin === "-") {
    return JSON.parse(fs.readFileSync(0, "utf8"));
  }
  return JSON.parse(fs.readFileSync(pathOrStdin, "utf8"));
}

/**
 * Encode command as Base64 UTF-16LE for PowerShell -EncodedCommand
 */
function encodeCommand(command) {
  const buffer = Buffer.from(command, "utf16le");
  return buffer.toString("base64");
}

/**
 * Execute PowerShell with encoded command
 */
function executePowerShell(command) {
  return new Promise((resolve, reject) => {
    const encoded = encodeCommand(command);

    const args = [
      "-NoProfile",
      "-NonInteractive",
      "-ExecutionPolicy", "Bypass",
      "-EncodedCommand", encoded
    ];

    const ps = spawn("powershell.exe", args, {
      stdio: ["pipe", "pipe", "pipe"]
    });

    let stdout = "";
    let stderr = "";

    ps.stdout.on("data", (data) => {
      stdout += data.toString();
    });

    ps.stderr.on("data", (data) => {
      stderr += data.toString();
    });

    ps.on("error", (err) => {
      reject(new Error(`Failed to start PowerShell: ${err.message}`));
    });

    ps.on("close", (code) => {
      resolve({
        stdout: stdout.trim(),
        stderr: stderr.trim(),
        exitCode: code
      });
    });
  });
}

/**
 * List all allowed actions
 */
function listActions() {
  console.log("Allowed PS-DSL Actions:\n");

  for (const [action, spec] of Object.entries(PS_COMMAND_REGISTRY.allow)) {
    console.log(`  ${action}`);
    console.log(`    Cmdlet: ${spec.cmdlet}`);
    console.log(`    Params: ${spec.params.length ? spec.params.join(", ") : "(none)"}`);
    console.log(`    ${spec.description}`);
    console.log();
  }

  console.log(`Total: ${Object.keys(PS_COMMAND_REGISTRY.allow).length} actions`);
}

/**
 * Verify an intent file
 */
function verifyFile(path) {
  try {
    const data = readJSON(path);

    // Detect envelope vs raw intent
    let result;
    if (data["@xcfe"]) {
      result = verifyEnvelope(data);
    } else {
      result = verifyPSDSL(data);
    }

    console.log("VALID");
    console.log(`  Action: ${result.action}`);
    console.log(`  Cmdlet: ${result.cmdlet}`);
    console.log(`  Lowered: ${result.lowered}`);
    return true;

  } catch (err) {
    console.error("INVALID:", err.code || err.message);
    if (err.detail) {
      console.error(`  Detail: ${err.detail}`);
    }
    return false;
  }
}

/**
 * Execute an intent file
 */
async function executeFile(path) {
  let data;
  try {
    data = readJSON(path);
  } catch (err) {
    console.error(`Error reading file: ${err.message}`);
    process.exit(1);
  }

  // Verify
  let verification;
  try {
    if (data["@xcfe"]) {
      verification = verifyEnvelope(data);
    } else {
      verification = verifyPSDSL(data);
    }
  } catch (err) {
    console.error("ILLEGAL:", err.code || err.message);
    if (err.detail) {
      console.error(`  Detail: ${err.detail}`);
    }
    process.exit(2);
  }

  // Wrap with CM-1 for audit
  const annotated = wrapCommand(verification.lowered, {
    action: verification.action
  });

  // Execute
  try {
    const result = await executePowerShell(verification.lowered);

    // Output result
    process.stdout.write(result.stdout);
    if (result.stdout && !result.stdout.endsWith("\n")) {
      process.stdout.write("\n");
    }

    if (result.stderr) {
      process.stderr.write(result.stderr);
      if (!result.stderr.endsWith("\n")) {
        process.stderr.write("\n");
      }
    }

    // Create audit record (could be logged/stored)
    const audit = createAuditRecord(annotated, result);

    // Exit with PowerShell exit code
    process.exit(result.exitCode);

  } catch (err) {
    console.error("Execution error:", err.message);
    process.exit(3);
  }
}

/**
 * Main entry point
 */
async function main() {
  const args = process.argv.slice(2);

  if (args.length === 0 || args.includes("--help") || args.includes("-h")) {
    console.log(USAGE);
    process.exit(0);
  }

  if (args.includes("--list-actions")) {
    listActions();
    process.exit(0);
  }

  if (args.includes("--verify")) {
    const idx = args.indexOf("--verify");
    const file = args[idx + 1];
    if (!file) {
      console.error("Error: --verify requires a file path");
      process.exit(1);
    }
    const valid = verifyFile(file);
    process.exit(valid ? 0 : 1);
  }

  // Default: execute the file
  await executeFile(args[0]);
}

main().catch((err) => {
  console.error("Fatal:", err.message);
  process.exit(1);
});
