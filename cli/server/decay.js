/**
 * MX2LM Server - π Decay Engine
 *
 * π support represents existence permission.
 * Decays on crashes, suppresses restart naturally.
 */

export let piSupport = 1.0;
export let crashCount = 0;

/**
 * Called when server crashes.
 * Reduces π support by decay factor.
 */
export function onCrash() {
  crashCount++;
  piSupport *= 0.6;
}

/**
 * Reset decay state (e.g., after manual intervention)
 */
export function reset() {
  piSupport = 1.0;
  crashCount = 0;
}

/**
 * Determine restart policy based on π support level.
 *
 * @returns {"SUPPRESS" | "ONCE" | "BACKOFF" | "IMMEDIATE"}
 */
export function allowRestart() {
  if (piSupport < 0.4) return "SUPPRESS";
  if (piSupport < 0.7) return "ONCE";
  if (piSupport < 0.9) return "BACKOFF";
  return "IMMEDIATE";
}

/**
 * Get current decay state for diagnostics
 */
export function getState() {
  return {
    piSupport,
    crashCount,
    restartPolicy: allowRestart()
  };
}
