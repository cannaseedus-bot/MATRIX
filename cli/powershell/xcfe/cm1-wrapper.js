/**
 * CM-1 Control Micronaut Wrapper v1
 *
 * Wraps PowerShell commands with CM-1 control characters
 * for auditability and phase geometry.
 *
 * XCFE Class: control.cm1.wrapper
 */

/**
 * CM-1 Control Character Constants
 */
export const CM1 = Object.freeze({
  // Phase control
  NUL: "\u0000",  // Absolute inert region
  SOH: "\u0001",  // Start of header
  STX: "\u0002",  // Start of text (body)
  ETX: "\u0003",  // End of text
  EOT: "\u0004",  // End of transmission

  // Scope control
  SO:  "\u000E",  // Shift out (push scope)
  SI:  "\u000F",  // Shift in (pop scope)
  DLE: "\u0010",  // Data link escape (literal)
  ESC: "\u001B",  // Escape (mode switch)

  // Segmentation
  FS:  "\u001C",  // File separator
  GS:  "\u001D",  // Group separator
  RS:  "\u001E",  // Record separator
  US:  "\u001F",  // Unit separator

  // Transport
  ENQ: "\u0005",  // Enquiry
  ACK: "\u0006",  // Acknowledge
  NAK: "\u0015",  // Negative acknowledge
  BEL: "\u0007",  // Bell/attention

  // Safe whitespace
  SP:  "\u0020"   // Space
});

/**
 * Wrap a command with CM-1 control characters for audit
 *
 * Format:
 * [SOH]envelope-version[GS]key=value[GS]...[STX]command[ETX][EOT]
 *
 * @param {string} command - The PowerShell command
 * @param {object} meta - Metadata to include in header
 * @returns {string} CM-1 annotated command
 */
export function wrapCommand(command, meta = {}) {
  // Build header segments
  const headerParts = ["ps-envelope.v1"];

  for (const [key, value] of Object.entries(meta)) {
    headerParts.push(`${key}=${value}`);
  }

  const header = headerParts.join(CM1.GS);

  // Compose full CM-1 stream
  return (
    CM1.SOH + header +
    CM1.STX + command +
    CM1.ETX + CM1.EOT
  );
}

/**
 * Unwrap a CM-1 annotated command
 *
 * @param {string} annotated - CM-1 annotated string
 * @returns {{ header: object, command: string }}
 */
export function unwrapCommand(annotated) {
  // Find boundaries
  const sohIdx = annotated.indexOf(CM1.SOH);
  const stxIdx = annotated.indexOf(CM1.STX);
  const etxIdx = annotated.indexOf(CM1.ETX);
  const eotIdx = annotated.indexOf(CM1.EOT);

  if (sohIdx === -1 || stxIdx === -1 || etxIdx === -1 || eotIdx === -1) {
    throw new Error("Invalid CM-1 structure: missing control characters");
  }

  if (!(sohIdx < stxIdx && stxIdx < etxIdx && etxIdx < eotIdx)) {
    throw new Error("Invalid CM-1 structure: incorrect order");
  }

  // Extract header
  const headerStr = annotated.slice(sohIdx + 1, stxIdx);
  const headerParts = headerStr.split(CM1.GS);

  const header = {
    version: headerParts[0]
  };

  for (let i = 1; i < headerParts.length; i++) {
    const [key, ...valueParts] = headerParts[i].split("=");
    header[key] = valueParts.join("=");
  }

  // Extract command
  const command = annotated.slice(stxIdx + 1, etxIdx);

  return { header, command };
}

/**
 * Strip all CM-1 control characters from a string
 * (Projection invariant: result should be identical to visible output)
 *
 * @param {string} str - Input string with CM-1 characters
 * @returns {string} String with CM-1 characters removed
 */
export function stripCM1(str) {
  // Remove all C0 control characters except tab, newline, carriage return
  return str.replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, "");
}

/**
 * Validate CM-1 structure invariants
 *
 * @param {string} str - CM-1 annotated string
 * @returns {{ valid: boolean, errors: string[] }}
 */
export function validateCM1(str) {
  const errors = [];

  // Check SOH/STX/ETX/EOT structure
  const hasSOH = str.includes(CM1.SOH);
  const hasSTX = str.includes(CM1.STX);
  const hasETX = str.includes(CM1.ETX);
  const hasEOT = str.includes(CM1.EOT);

  if (hasSTX && !hasETX) {
    errors.push("STX without matching ETX");
  }
  if (hasETX && !hasSTX) {
    errors.push("ETX without preceding STX");
  }

  // Check scope balance
  const soCount = (str.match(/\u000E/g) || []).length;
  const siCount = (str.match(/\u000F/g) || []).length;
  if (soCount !== siCount) {
    errors.push(`Unbalanced scope: ${soCount} SO vs ${siCount} SI`);
  }

  // Check order
  if (hasSOH && hasSTX) {
    if (str.indexOf(CM1.SOH) > str.indexOf(CM1.STX)) {
      errors.push("SOH must precede STX");
    }
  }
  if (hasSTX && hasETX) {
    if (str.indexOf(CM1.STX) > str.indexOf(CM1.ETX)) {
      errors.push("STX must precede ETX");
    }
  }
  if (hasETX && hasEOT) {
    if (str.indexOf(CM1.ETX) > str.indexOf(CM1.EOT)) {
      errors.push("ETX must precede EOT");
    }
  }

  return {
    valid: errors.length === 0,
    errors
  };
}

/**
 * Create an audit record for a CM-1 execution
 *
 * @param {string} annotated - CM-1 annotated command
 * @param {object} result - Execution result
 * @returns {object} Audit record
 */
export function createAuditRecord(annotated, result) {
  const { header, command } = unwrapCommand(annotated);

  return {
    "@audit": "ps-exec.v1",
    timestamp: Date.now(),
    action: header.action || "unknown",
    lowered: command,
    cm1_valid: validateCM1(annotated).valid,
    stdout_bytes: result.stdout?.length || 0,
    stderr: result.stderr || "",
    exit_code: result.exitCode ?? 0
  };
}
