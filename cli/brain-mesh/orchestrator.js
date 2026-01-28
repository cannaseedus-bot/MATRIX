/**
 * Brain Mesh Orchestrator
 *
 * Monitors KUHUL execution via CM-1 events, detects failures,
 * routes to appropriate LLM for diagnosis, and injects fixes.
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 * @version 1.0.0
 */

'use strict';

const fs = require('fs');
const path = require('path');
const http = require('http');
const { EventEmitter } = require('events');

// Load brain registry
const BRAINS_PATH = path.join(__dirname, 'brains.json');
const BRAINS_REGISTRY = JSON.parse(fs.readFileSync(BRAINS_PATH, 'utf-8'));

// CM-1 Control Characters
const CM1 = {
  NUL: '\u0000',
  SOH: '\u0001',
  STX: '\u0002',
  ETX: '\u0003',
  EOT: '\u0004',
  ENQ: '\u0005',
  ACK: '\u0006',
  NAK: '\u0015',
  SO:  '\u000E',
  SI:  '\u000F',
};

// Agent API configuration
const AGENT_CONFIG = {
  host: process.env.AGENT_HOST || '127.0.0.1',
  port: parseInt(process.env.AGENT_PORT || '5001', 10),
  token: process.env.LOCAL_API_TOKEN || '',
};

/**
 * Brain Mesh Orchestrator
 */
class BrainMeshOrchestrator extends EventEmitter {
  constructor(options = {}) {
    super();

    this.brains = new Map();
    this.activeBrains = new Set();
    this.executionLog = [];
    this.errorQueue = [];
    this.fixQueue = [];

    // Metrics
    this.metrics = {
      totalExecutions: 0,
      successfulExecutions: 0,
      failedExecutions: 0,
      interventions: 0,
      fixesApplied: 0,
      avgResponseTime: 0,
    };

    // Configuration
    this.config = {
      maxRetries: options.maxRetries || 3,
      retryDelay: options.retryDelay || 1000,
      interventionThreshold: options.interventionThreshold || 0.06, // 6%
      agentHost: options.agentHost || AGENT_CONFIG.host,
      agentPort: options.agentPort || AGENT_CONFIG.port,
      agentToken: options.agentToken || AGENT_CONFIG.token,
    };

    // Initialize brains from registry
    this.loadBrains();

    // CM-1 phase tracking
    this.cm1State = {
      currentPhase: 'null',
      scopeStack: [],
      phaseHistory: [],
    };
  }

  /**
   * Load brains from registry
   */
  loadBrains() {
    const registry = BRAINS_REGISTRY.brains;

    for (const [id, config] of Object.entries(registry)) {
      this.brains.set(id, {
        id,
        ...config,
        status: 'idle',
        lastExecution: null,
        errorCount: 0,
        successCount: 0,
      });
    }

    this.log(`Loaded ${this.brains.size} brains from registry`);
  }

  /**
   * Log with timestamp
   */
  log(message, level = 'info') {
    const timestamp = new Date().toISOString();
    const entry = { timestamp, level, message };
    this.executionLog.push(entry);

    const prefix = {
      info: '\x1b[36m[INFO]\x1b[0m',
      warn: '\x1b[33m[WARN]\x1b[0m',
      error: '\x1b[31m[ERROR]\x1b[0m',
      success: '\x1b[32m[OK]\x1b[0m',
    }[level] || '[LOG]';

    console.log(`${prefix} ${timestamp} ${message}`);
  }

  /**
   * Route task to appropriate brain based on domain
   */
  routeToBrain(domain) {
    const rules = BRAINS_REGISTRY.routing_rules.domain_to_brain;

    // Direct domain match
    if (rules[domain]) {
      return this.brains.get(rules[domain]);
    }

    // Pattern match (e.g., css.atomic.* → css_atomic)
    for (const [pattern, brainId] of Object.entries(rules)) {
      if (domain.startsWith(pattern.replace('*', ''))) {
        return this.brains.get(brainId);
      }
    }

    // Default to orchestrator
    return this.brains.get('mx2lm_prime');
  }

  /**
   * Call agent API for LLM inference
   */
  async callAgentAPI(endpoint, payload) {
    return new Promise((resolve, reject) => {
      const data = JSON.stringify(payload);

      const options = {
        hostname: this.config.agentHost,
        port: this.config.agentPort,
        path: endpoint,
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Content-Length': Buffer.byteLength(data),
          ...(this.config.agentToken && { 'X-LOCAL-TOKEN': this.config.agentToken }),
        },
      };

      const req = http.request(options, (res) => {
        let body = '';
        res.on('data', chunk => body += chunk);
        res.on('end', () => {
          try {
            const result = JSON.parse(body);
            if (res.statusCode >= 400) {
              reject(new Error(result.error || `HTTP ${res.statusCode}`));
            } else {
              resolve(result);
            }
          } catch (e) {
            reject(new Error(`Invalid JSON response: ${body}`));
          }
        });
      });

      req.on('error', reject);
      req.setTimeout(60000, () => {
        req.destroy();
        reject(new Error('Request timeout'));
      });

      req.write(data);
      req.end();
    });
  }

  /**
   * Send prompt to LLM via agent API
   */
  async promptLLM(brain, prompt, context = {}) {
    const binding = brain['@llm_binding'];

    this.log(`Routing to ${brain['@label']} (${binding['@provider']}:${binding['@model']})`);

    const startTime = Date.now();

    try {
      let result;

      if (binding['@provider'] === 'ollama') {
        // Direct Ollama endpoint
        result = await this.callAgentAPI('/ollama', {
          prompt,
          model: binding['@model'],
        });
      } else {
        // Generic prompt endpoint (uses configured agent)
        result = await this.callAgentAPI('/prompt', {
          prompt,
          model: binding['@model'],
        });
      }

      const responseTime = Date.now() - startTime;
      this.updateMetrics(true, responseTime);

      brain.successCount++;
      brain.lastExecution = Date.now();
      brain.status = 'idle';

      return {
        success: true,
        output: result.output,
        brain: brain.id,
        responseTime,
      };

    } catch (error) {
      const responseTime = Date.now() - startTime;
      this.updateMetrics(false, responseTime);

      brain.errorCount++;
      brain.status = 'error';

      this.log(`Brain ${brain.id} failed: ${error.message}`, 'error');

      // Try fallback
      if (binding['@fallback']) {
        this.log(`Attempting fallback: ${binding['@fallback']}`, 'warn');
        return await this.tryFallback(brain, binding['@fallback'], prompt);
      }

      return {
        success: false,
        error: error.message,
        brain: brain.id,
        responseTime,
      };
    }
  }

  /**
   * Try fallback LLM provider
   */
  async tryFallback(originalBrain, fallbackSpec, prompt) {
    const [provider, model] = fallbackSpec.includes(':')
      ? fallbackSpec.split(':')
      : [fallbackSpec, null];

    try {
      let result;

      if (provider === 'ollama') {
        result = await this.callAgentAPI('/ollama', {
          prompt,
          model: model || 'llama3.2',
        });
      } else {
        result = await this.callAgentAPI('/prompt', { prompt });
      }

      this.log(`Fallback successful via ${provider}`, 'success');

      return {
        success: true,
        output: result.output,
        brain: originalBrain.id,
        fallback: fallbackSpec,
      };

    } catch (error) {
      this.log(`Fallback failed: ${error.message}`, 'error');

      return {
        success: false,
        error: error.message,
        brain: originalBrain.id,
        fallback: fallbackSpec,
      };
    }
  }

  /**
   * Update metrics
   */
  updateMetrics(success, responseTime) {
    this.metrics.totalExecutions++;

    if (success) {
      this.metrics.successfulExecutions++;
    } else {
      this.metrics.failedExecutions++;
    }

    // Rolling average response time
    const n = this.metrics.totalExecutions;
    this.metrics.avgResponseTime =
      ((this.metrics.avgResponseTime * (n - 1)) + responseTime) / n;
  }

  /**
   * Monitor CM-1 phase transition
   */
  onCM1PhaseTransition(fromPhase, toPhase, context = {}) {
    this.cm1State.currentPhase = toPhase;
    this.cm1State.phaseHistory.push({
      from: fromPhase,
      to: toPhase,
      timestamp: Date.now(),
      context,
    });

    this.emit('cm1:phase', { from: fromPhase, to: toPhase, context });

    // Check for anomalies
    if (this.detectPhaseAnomaly(fromPhase, toPhase)) {
      this.handlePhaseAnomaly(fromPhase, toPhase, context);
    }
  }

  /**
   * Detect phase anomaly
   */
  detectPhaseAnomaly(fromPhase, toPhase) {
    const validTransitions = {
      'null': ['header.begin'],
      'header.begin': ['body.begin'],
      'body.begin': ['body.end'],
      'body.end': ['transmission.end', 'header.begin'],
      'transmission.end': ['null', 'header.begin'],
    };

    const valid = validTransitions[fromPhase];
    return !valid || !valid.includes(toPhase);
  }

  /**
   * Handle phase anomaly
   */
  async handlePhaseAnomaly(fromPhase, toPhase, context) {
    this.log(`CM-1 phase anomaly: ${fromPhase} → ${toPhase}`, 'warn');
    this.metrics.interventions++;

    const brain = this.brains.get('kuhul_runtime');

    const prompt = `
CM-1 Phase Anomaly Detected:
- From: ${fromPhase}
- To: ${toPhase}
- Context: ${JSON.stringify(context)}

Valid transitions from "${fromPhase}" are: ${JSON.stringify(this.getValidTransitions(fromPhase))}

Diagnose the issue and provide a fix in JSON format:
{
  "diagnosis": "explanation of what went wrong",
  "fix": "corrective action to take",
  "inject_phase": "correct phase to inject"
}
`.trim();

    const result = await this.promptLLM(brain, prompt, context);

    if (result.success) {
      try {
        const fix = JSON.parse(result.output);
        this.applyFix(fix, context);
      } catch (e) {
        this.log(`Could not parse fix: ${e.message}`, 'error');
      }
    }
  }

  /**
   * Get valid transitions for a phase
   */
  getValidTransitions(phase) {
    const transitions = {
      'null': ['header.begin'],
      'header.begin': ['body.begin'],
      'body.begin': ['body.end'],
      'body.end': ['transmission.end', 'header.begin'],
      'transmission.end': ['null', 'header.begin'],
    };
    return transitions[phase] || [];
  }

  /**
   * Monitor scope push/pop
   */
  onCM1ScopeChange(action, scopeId) {
    if (action === 'push') {
      this.cm1State.scopeStack.push(scopeId);
    } else if (action === 'pop') {
      const expected = this.cm1State.scopeStack.pop();
      if (expected !== scopeId) {
        this.log(`Scope mismatch: expected ${expected}, got ${scopeId}`, 'warn');
        this.handleScopeMismatch(expected, scopeId);
      }
    }

    this.emit('cm1:scope', { action, scopeId, depth: this.cm1State.scopeStack.length });
  }

  /**
   * Handle scope mismatch
   */
  async handleScopeMismatch(expected, actual) {
    this.metrics.interventions++;

    const brain = this.brains.get('kuhul_runtime');

    const prompt = `
CM-1 Scope Mismatch:
- Expected scope ID: ${expected}
- Actual scope ID: ${actual}
- Current stack depth: ${this.cm1State.scopeStack.length}

Diagnose and provide corrective action.
`.trim();

    await this.promptLLM(brain, prompt);
  }

  /**
   * Execute task with brain routing
   */
  async executeTask(task) {
    const { domain, operation, args, context } = task;

    this.log(`Executing: ${domain}.${operation}`);

    // Route to brain
    const brain = this.routeToBrain(domain);
    if (!brain) {
      return { success: false, error: 'No brain found for domain' };
    }

    brain.status = 'executing';
    this.activeBrains.add(brain.id);

    // CM-1 phase: body begin
    this.onCM1PhaseTransition(this.cm1State.currentPhase, 'body.begin', { task });

    try {
      // Build prompt for LLM
      const prompt = this.buildTaskPrompt(brain, task);

      // Execute via LLM
      const result = await this.promptLLM(brain, prompt, context);

      // CM-1 phase: body end
      this.onCM1PhaseTransition('body.begin', 'body.end', { result });

      this.activeBrains.delete(brain.id);
      brain.status = 'idle';

      return result;

    } catch (error) {
      this.activeBrains.delete(brain.id);
      brain.status = 'error';

      this.log(`Task execution failed: ${error.message}`, 'error');

      // Queue for intervention
      this.errorQueue.push({ task, error, brain: brain.id, timestamp: Date.now() });

      return { success: false, error: error.message };
    }
  }

  /**
   * Build task prompt for brain
   */
  buildTaskPrompt(brain, task) {
    const capabilities = brain['@capabilities'].join(', ');
    const domains = brain['@domains'].join(', ');

    return `
You are ${brain['@label']} (${brain['@description']}).

Capabilities: ${capabilities}
Domains: ${domains}

Task:
- Domain: ${task.domain}
- Operation: ${task.operation}
- Arguments: ${JSON.stringify(task.args || {})}
- Context: ${JSON.stringify(task.context || {})}

Execute this task and return the result as JSON.
`.trim();
  }

  /**
   * Apply fix from LLM
   */
  applyFix(fix, context) {
    this.log(`Applying fix: ${fix.diagnosis}`, 'info');
    this.metrics.fixesApplied++;

    this.fixQueue.push({
      fix,
      context,
      timestamp: Date.now(),
      applied: true,
    });

    this.emit('fix:applied', fix);

    // Inject corrected phase if specified
    if (fix.inject_phase) {
      this.cm1State.currentPhase = fix.inject_phase;
      this.log(`Injected phase: ${fix.inject_phase}`, 'success');
    }
  }

  /**
   * Process error queue (batch intervention)
   */
  async processErrorQueue() {
    if (this.errorQueue.length === 0) return;

    const errors = this.errorQueue.splice(0, 10); // Process up to 10 at a time

    this.log(`Processing ${errors.length} queued errors`, 'info');

    const brain = this.brains.get('mx2lm_prime');

    const prompt = `
Multiple execution errors occurred. Analyze and provide batch fixes:

Errors:
${errors.map((e, i) => `
${i + 1}. Task: ${e.task.domain}.${e.task.operation}
   Error: ${e.error.message || e.error}
   Brain: ${e.brain}
`).join('\n')}

Provide fixes as JSON array:
[
  { "index": 1, "diagnosis": "...", "fix": "...", "retry": true/false },
  ...
]
`.trim();

    const result = await this.promptLLM(brain, prompt);

    if (result.success) {
      try {
        const fixes = JSON.parse(result.output);
        fixes.forEach(fix => {
          this.applyFix(fix, errors[fix.index - 1]);
        });
      } catch (e) {
        this.log(`Could not parse batch fixes: ${e.message}`, 'error');
      }
    }
  }

  /**
   * Get current efficiency metrics
   */
  getEfficiency() {
    const total = this.metrics.totalExecutions;
    if (total === 0) return { efficiency: 1.0, interventionRate: 0 };

    const efficiency = this.metrics.successfulExecutions / total;
    const interventionRate = this.metrics.interventions / total;

    return {
      efficiency,
      interventionRate,
      successRate: efficiency,
      failureRate: 1 - efficiency,
      avgResponseTime: this.metrics.avgResponseTime,
      totalExecutions: total,
      fixesApplied: this.metrics.fixesApplied,
    };
  }

  /**
   * Get brain status
   */
  getBrainStatus() {
    const status = {};

    for (const [id, brain] of this.brains) {
      status[id] = {
        label: brain['@label'],
        status: brain.status,
        fold: brain['@horizontal_fold'],
        successCount: brain.successCount,
        errorCount: brain.errorCount,
        lastExecution: brain.lastExecution,
      };
    }

    return status;
  }

  /**
   * Health check
   */
  async healthCheck() {
    const results = {
      orchestrator: 'ok',
      brains: this.brains.size,
      activeBrains: this.activeBrains.size,
      errorQueueSize: this.errorQueue.length,
      cm1Phase: this.cm1State.currentPhase,
      scopeDepth: this.cm1State.scopeStack.length,
      efficiency: this.getEfficiency(),
      agentAPI: 'unknown',
    };

    // Check agent API
    try {
      const response = await this.callAgentAPI('/status', {});
      results.agentAPI = 'ok';
      results.agentConfig = response;
    } catch (e) {
      results.agentAPI = `error: ${e.message}`;
    }

    return results;
  }
}

module.exports = { BrainMeshOrchestrator, CM1, BRAINS_REGISTRY };
