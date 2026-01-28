/**
 * PS-DSL Legality Verifier v1
 *
 * XCFE-grade verification for PowerShell intent legality.
 * Deny-by-default: only allowlisted actions can execute.
 *
 * XCFE Class: host.powershell.verifier
 */

import { PS_COMMAND_REGISTRY, isActionAllowed, isCmdletDenied, getActionSpec } from "./ps-command-registry.js";

// Illegal characters that could enable injection
const ILLEGAL_CHARS = /[|;`$(){}[\]\\<>]/;

// Error codes
export const ERROR_CODES = Object.freeze({
  BAD_DSL: "Invalid DSL version",
  BAD_ACTION: "Invalid action format",
  ACTION_NOT_ALLOWLISTED: "Action not in allowlist",
  PARAM_NOT_ALLOWED: "Parameter not allowed for this action",
  ILLEGAL_PARAM_CHARS: "Illegal characters in parameter value",
  CMDLET_DENIED: "Cmdlet is in deny list"
});

/**
 * Verify a PS-DSL intent is legal for execution
 *
 * @param {object} intent - The PS-DSL intent object
 * @returns {{ ok: boolean, lowered: string }} Verification result
 * @throws {Error} If intent is illegal
 */
export function verifyPSDSL(intent) {
  // Schema-level checks
  if (intent?.["@dsl"] !== "ps-dsl.v1") {
    throw createError("BAD_DSL", intent?.["@dsl"]);
  }

  if (typeof intent.action !== "string" || !/^[a-z]+\.[a-z]+$/.test(intent.action)) {
    throw createError("BAD_ACTION", intent.action);
  }

  // Deny-by-default: action must be in allowlist
  if (!isActionAllowed(intent.action)) {
    throw createError("ACTION_NOT_ALLOWLISTED", intent.action);
  }

  const spec = getActionSpec(intent.action);

  // Verify cmdlet is not in deny list (defense in depth)
  if (isCmdletDenied(spec.cmdlet)) {
    throw createError("CMDLET_DENIED", spec.cmdlet);
  }

  // Validate parameters
  const params = intent.params || {};
  for (const [key, value] of Object.entries(params)) {
    // Parameter must be in allowed list for this action
    if (!spec.params.includes(key)) {
      throw createError("PARAM_NOT_ALLOWED", `${key} (allowed: ${spec.params.join(", ")})`);
    }

    // Check for illegal characters in string values
    if (typeof value === "string" && ILLEGAL_CHARS.test(value)) {
      throw createError("ILLEGAL_PARAM_CHARS", `${key}=${value}`);
    }
  }

  // Deterministic lowering
  const lowered = lowerToPowerShell(spec, params);

  return {
    ok: true,
    lowered,
    cmdlet: spec.cmdlet,
    action: intent.action
  };
}

/**
 * Lower a verified action to PowerShell command string
 *
 * @param {object} spec - Action specification from registry
 * @param {object} params - Verified parameters
 * @returns {string} PowerShell command string
 */
function lowerToPowerShell(spec, params) {
  let cmd = spec.cmdlet;

  // Add prefix if specified (e.g., Env: for environment)
  if (spec.prefix) {
    cmd += ` ${spec.prefix}`;
  }

  // Add parameters
  for (const [key, value] of Object.entries(params)) {
    cmd += ` -${capitalize(key)} ${escapeLiteral(value)}`;
  }

  return cmd;
}

/**
 * Escape a value as a PowerShell literal
 *
 * @param {string|number|boolean} value
 * @returns {string} Escaped literal
 */
function escapeLiteral(value) {
  if (typeof value === "number" || typeof value === "boolean") {
    return String(value);
  }

  // Single-quoted literal with doubled single quotes inside
  return `'${String(value).replace(/'/g, "''")}'`;
}

/**
 * Capitalize first letter of a string
 */
function capitalize(str) {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

/**
 * Create a verification error
 */
function createError(code, detail) {
  const message = `${ERROR_CODES[code] || code}${detail ? `: ${detail}` : ""}`;
  const error = new Error(message);
  error.code = code;
  error.detail = detail;
  return error;
}

/**
 * Verify an XCFE envelope is legal
 *
 * @param {object} envelope - Full XCFE envelope
 * @returns {{ ok: boolean, lowered: string }}
 */
export function verifyEnvelope(envelope) {
  // Envelope structure checks
  if (envelope?.["@xcfe"] !== "ps-envelope.v1") {
    throw createError("BAD_ENVELOPE", envelope?.["@xcfe"]);
  }

  if (envelope?.["@control"]?.phase !== "delegate.external") {
    throw createError("BAD_PHASE", envelope?.["@control"]?.phase);
  }

  if (envelope?.["@control"]?.target !== "powershell") {
    throw createError("BAD_TARGET", envelope?.["@control"]?.target);
  }

  // Capability checks
  const cap = envelope?.["@capability"] || {};
  if (cap.interactive !== false) {
    throw createError("INTERACTIVE_NOT_ALLOWED");
  }
  if (cap.network !== false) {
    throw createError("NETWORK_NOT_ALLOWED");
  }

  // Verify the intent
  return verifyPSDSL(envelope["@intent"]);
}
