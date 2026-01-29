/**
 * Brain Mesh Orchestrator
 *
 * Monitors KUHUL execution via CM-1 events, detects failures,
 * routes to appropriate LLM for diagnosis, and injects fixes.
 *
 * Features:
 * - Adaptive brain scoring (multi-armed bandit)
 * - Automatic best-brain selection per domain
 * - Exploration/exploitation with epsilon-greedy
 * - Brain promotion/demotion based on performance
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 * @version 2.0.0
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

      // Adaptive scoring configuration
      adaptiveRouting: options.adaptiveRouting !== false, // Enable by default
      explorationRate: options.explorationRate || 0.1,    // 10% exploration
      scoreDecay: options.scoreDecay || 0.95,             // Score decay factor
      minSamples: options.minSamples || 3,                // Min samples before scoring
      rollingWindowSize: options.rollingWindowSize || 50, // Recent executions to consider

      // Score weights
      scoreWeights: {
        successRate: options.successWeight || 0.5,        // 50% weight
        speed: options.speedWeight || 0.3,                // 30% weight
        consistency: options.consistencyWeight || 0.2,    // 20% weight
      },

      // Promotion/demotion thresholds
      promotionThreshold: options.promotionThreshold || 0.85,  // Promote if score > 85%
      demotionThreshold: options.demotionThreshold || 0.4,     // Demote if score < 40%
    };

    // Domain-to-brain performance tracking
    this.domainBrainScores = new Map();

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

        // Adaptive scoring fields
        score: 0.5,                    // Initial neutral score
        tier: 'standard',              // standard, promoted, demoted
        recentExecutions: [],          // Rolling window of recent results
        avgResponseTime: 0,            // Average response time
        responseTimeVariance: 0,       // Consistency measure
        totalExecutions: 0,            // Total executions for this brain
        promotionCount: 0,             // Times promoted
        demotionCount: 0,              // Times demoted
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
   * Uses adaptive scoring when enabled
   */
  routeToBrain(domain) {
    // Get candidate brains for this domain
    const candidates = this.getCandidateBrains(domain);

    if (candidates.length === 0) {
      return this.brains.get('mx2lm_prime');
    }

    if (candidates.length === 1) {
      return candidates[0];
    }

    // Use adaptive routing if enabled
    if (this.config.adaptiveRouting) {
      return this.selectBestBrain(domain, candidates);
    }

    // Fallback to first candidate (static routing)
    return candidates[0];
  }

  /**
   * Get candidate brains for a domain
   */
  getCandidateBrains(domain) {
    const rules = BRAINS_REGISTRY.routing_rules.domain_to_brain;
    const candidates = [];

    // Direct domain match
    if (rules[domain]) {
      const brain = this.brains.get(rules[domain]);
      if (brain) candidates.push(brain);
    }

    // Pattern match (e.g., css.atomic.* → css_atomic)
    for (const [pattern, brainId] of Object.entries(rules)) {
      if (domain.startsWith(pattern.replace('*', ''))) {
        const brain = this.brains.get(brainId);
        if (brain && !candidates.includes(brain)) {
          candidates.push(brain);
        }
      }
    }

    // Add brains that list this domain in their capabilities
    for (const [id, brain] of this.brains) {
      const domains = brain['@domains'] || [];
      if (domains.some(d => domain.includes(d) || d.includes(domain))) {
        if (!candidates.includes(brain)) {
          candidates.push(brain);
        }
      }
    }

    // Exclude demoted brains unless no alternatives
    const nonDemoted = candidates.filter(b => b.tier !== 'demoted');
    return nonDemoted.length > 0 ? nonDemoted : candidates;
  }

  /**
   * Select best brain using adaptive scoring (epsilon-greedy)
   */
  selectBestBrain(domain, candidates) {
    // Exploration: randomly select with probability epsilon
    if (Math.random() < this.config.explorationRate) {
      const randomBrain = candidates[Math.floor(Math.random() * candidates.length)];
      this.log(`[Adaptive] Exploring: ${randomBrain.id} for ${domain}`, 'info');
      return randomBrain;
    }

    // Exploitation: select highest-scoring brain
    const domainKey = this.getDomainKey(domain);
    const scores = this.domainBrainScores.get(domainKey) || new Map();

    // Calculate effective scores for each candidate
    const scoredCandidates = candidates.map(brain => {
      const domainScore = scores.get(brain.id);
      const effectiveScore = this.calculateEffectiveScore(brain, domainScore);
      return { brain, effectiveScore };
    });

    // Sort by score (descending)
    scoredCandidates.sort((a, b) => b.effectiveScore - a.effectiveScore);

    const best = scoredCandidates[0];
    this.log(`[Adaptive] Selected: ${best.brain.id} (score: ${best.effectiveScore.toFixed(3)}) for ${domain}`, 'info');

    return best.brain;
  }

  /**
   * Normalize domain key for score tracking
   */
  getDomainKey(domain) {
    // Group similar domains (e.g., css.atomic.forge → css.atomic)
    const parts = domain.split('.');
    return parts.slice(0, 2).join('.');
  }

  /**
   * Calculate effective score for brain selection
   */
  calculateEffectiveScore(brain, domainScore) {
    // If not enough samples, use prior (0.5) with high uncertainty bonus
    if (brain.totalExecutions < this.config.minSamples) {
      // Upper Confidence Bound (UCB) style exploration bonus
      const uncertaintyBonus = Math.sqrt(2 * Math.log(this.metrics.totalExecutions + 1) / (brain.totalExecutions + 1));
      return 0.5 + uncertaintyBonus;
    }

    // Combine global brain score with domain-specific score
    const globalScore = brain.score;
    const specificScore = domainScore?.score || globalScore;

    // Weight domain-specific more heavily if available
    const combinedScore = domainScore
      ? (specificScore * 0.7) + (globalScore * 0.3)
      : globalScore;

    // Apply tier bonus/penalty
    const tierModifier = {
      'promoted': 0.05,
      'standard': 0,
      'demoted': -0.1,
    }[brain.tier] || 0;

    return Math.max(0, Math.min(1, combinedScore + tierModifier));
  }

  /**
   * Update brain score after execution
   */
  updateBrainScore(brain, domain, result) {
    const success = result.success;
    const responseTime = result.responseTime || 0;

    // Update execution counts
    brain.totalExecutions++;
    if (success) {
      brain.successCount++;
    } else {
      brain.errorCount++;
    }

    // Update rolling window
    brain.recentExecutions.push({
      success,
      responseTime,
      timestamp: Date.now(),
    });

    // Trim to window size
    if (brain.recentExecutions.length > this.config.rollingWindowSize) {
      brain.recentExecutions.shift();
    }

    // Calculate new scores from rolling window
    const recent = brain.recentExecutions;
    const successRate = recent.filter(e => e.success).length / recent.length;

    // Calculate response time stats
    const times = recent.map(e => e.responseTime).filter(t => t > 0);
    const avgTime = times.length > 0 ? times.reduce((a, b) => a + b, 0) / times.length : 0;
    const variance = times.length > 1
      ? times.reduce((sum, t) => sum + Math.pow(t - avgTime, 2), 0) / times.length
      : 0;

    brain.avgResponseTime = avgTime;
    brain.responseTimeVariance = variance;

    // Normalize speed score (faster = higher, cap at 10s)
    const speedScore = Math.max(0, 1 - (avgTime / 10000));

    // Normalize consistency score (lower variance = higher)
    const consistencyScore = Math.max(0, 1 - (Math.sqrt(variance) / 5000));

    // Calculate weighted score
    const weights = this.config.scoreWeights;
    const newScore =
      (successRate * weights.successRate) +
      (speedScore * weights.speed) +
      (consistencyScore * weights.consistency);

    // Apply decay and update
    brain.score = (brain.score * this.config.scoreDecay) + (newScore * (1 - this.config.scoreDecay));

    // Update domain-specific scores
    this.updateDomainScore(brain, domain, success, responseTime);

    // Check for promotion/demotion
    this.evaluateBrainTier(brain);

    this.emit('brain:scoreUpdate', {
      brainId: brain.id,
      score: brain.score,
      tier: brain.tier,
      successRate,
      avgResponseTime: avgTime,
    });
  }

  /**
   * Update domain-specific score
   */
  updateDomainScore(brain, domain, success, responseTime) {
    const domainKey = this.getDomainKey(domain);

    if (!this.domainBrainScores.has(domainKey)) {
      this.domainBrainScores.set(domainKey, new Map());
    }

    const domainScores = this.domainBrainScores.get(domainKey);

    if (!domainScores.has(brain.id)) {
      domainScores.set(brain.id, {
        score: 0.5,
        successCount: 0,
        errorCount: 0,
        totalTime: 0,
        executions: 0,
      });
    }

    const ds = domainScores.get(brain.id);
    ds.executions++;
    ds.totalTime += responseTime;

    if (success) {
      ds.successCount++;
    } else {
      ds.errorCount++;
    }

    // Calculate domain-specific score
    const successRate = ds.successCount / ds.executions;
    const avgTime = ds.totalTime / ds.executions;
    const speedScore = Math.max(0, 1 - (avgTime / 10000));

    ds.score = (successRate * 0.7) + (speedScore * 0.3);
  }

  /**
   * Evaluate brain tier (promotion/demotion)
   */
  evaluateBrainTier(brain) {
    // Need minimum samples before changing tier
    if (brain.totalExecutions < this.config.minSamples * 2) {
      return;
    }

    const previousTier = brain.tier;

    if (brain.score >= this.config.promotionThreshold && brain.tier !== 'promoted') {
      brain.tier = 'promoted';
      brain.promotionCount++;
      this.log(`[Adaptive] PROMOTED: ${brain.id} (score: ${brain.score.toFixed(3)})`, 'success');
      this.emit('brain:promoted', { brainId: brain.id, score: brain.score });

    } else if (brain.score <= this.config.demotionThreshold && brain.tier !== 'demoted') {
      brain.tier = 'demoted';
      brain.demotionCount++;
      this.log(`[Adaptive] DEMOTED: ${brain.id} (score: ${brain.score.toFixed(3)})`, 'warn');
      this.emit('brain:demoted', { brainId: brain.id, score: brain.score });

    } else if (brain.tier === 'demoted' && brain.score > this.config.demotionThreshold + 0.1) {
      // Recover from demotion if score improves
      brain.tier = 'standard';
      this.log(`[Adaptive] RECOVERED: ${brain.id} (score: ${brain.score.toFixed(3)})`, 'info');
      this.emit('brain:recovered', { brainId: brain.id, score: brain.score });

    } else if (brain.tier === 'promoted' && brain.score < this.config.promotionThreshold - 0.1) {
      // Lose promotion if score drops
      brain.tier = 'standard';
      this.log(`[Adaptive] STANDARD: ${brain.id} (score: ${brain.score.toFixed(3)})`, 'info');
    }
  }

  /**
   * Get brain leaderboard (sorted by score)
   */
  getBrainLeaderboard() {
    const leaderboard = [];

    for (const [id, brain] of this.brains) {
      leaderboard.push({
        rank: 0,
        id,
        label: brain['@label'],
        score: brain.score,
        tier: brain.tier,
        successRate: brain.totalExecutions > 0
          ? brain.successCount / brain.totalExecutions
          : 0,
        avgResponseTime: brain.avgResponseTime,
        totalExecutions: brain.totalExecutions,
        promotions: brain.promotionCount,
        demotions: brain.demotionCount,
      });
    }

    // Sort by score descending
    leaderboard.sort((a, b) => b.score - a.score);

    // Assign ranks
    leaderboard.forEach((entry, index) => {
      entry.rank = index + 1;
    });

    return leaderboard;
  }

  /**
   * Get best brain for a domain
   */
  getBestBrainForDomain(domain) {
    const candidates = this.getCandidateBrains(domain);
    if (candidates.length === 0) return null;

    return this.selectBestBrain(domain, candidates);
  }

  /**
   * Reset brain scores (for testing)
   */
  resetScores() {
    for (const [id, brain] of this.brains) {
      brain.score = 0.5;
      brain.tier = 'standard';
      brain.recentExecutions = [];
      brain.avgResponseTime = 0;
      brain.responseTimeVariance = 0;
      brain.totalExecutions = 0;
      brain.successCount = 0;
      brain.errorCount = 0;
      brain.promotionCount = 0;
      brain.demotionCount = 0;
    }
    this.domainBrainScores.clear();
    this.log('Brain scores reset', 'info');
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

      brain.lastExecution = Date.now();
      brain.status = 'idle';

      const execResult = {
        success: true,
        output: result.output,
        brain: brain.id,
        responseTime,
      };

      // Update adaptive score
      if (context?.domain) {
        this.updateBrainScore(brain, context.domain, execResult);
      } else {
        this.updateBrainScore(brain, 'general', execResult);
      }

      return execResult;

    } catch (error) {
      const responseTime = Date.now() - startTime;
      this.updateMetrics(false, responseTime);

      brain.status = 'error';

      const execResult = {
        success: false,
        error: error.message,
        brain: brain.id,
        responseTime,
      };

      // Update adaptive score for failure
      if (context?.domain) {
        this.updateBrainScore(brain, context.domain, execResult);
      } else {
        this.updateBrainScore(brain, 'general', execResult);
      }

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
   * Get brain status (with adaptive scoring)
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

        // Adaptive scoring fields
        score: brain.score,
        tier: brain.tier,
        totalExecutions: brain.totalExecutions,
        avgResponseTime: brain.avgResponseTime,
        successRate: brain.totalExecutions > 0
          ? (brain.successCount / brain.totalExecutions).toFixed(3)
          : 'N/A',
        promotions: brain.promotionCount,
        demotions: brain.demotionCount,
      };
    }

    return status;
  }

  /**
   * Get adaptive routing stats
   */
  getAdaptiveStats() {
    const leaderboard = this.getBrainLeaderboard();

    return {
      enabled: this.config.adaptiveRouting,
      explorationRate: this.config.explorationRate,
      scoreWeights: this.config.scoreWeights,
      thresholds: {
        promotion: this.config.promotionThreshold,
        demotion: this.config.demotionThreshold,
      },
      leaderboard: leaderboard.slice(0, 5), // Top 5
      tiers: {
        promoted: leaderboard.filter(b => b.tier === 'promoted').length,
        standard: leaderboard.filter(b => b.tier === 'standard').length,
        demoted: leaderboard.filter(b => b.tier === 'demoted').length,
      },
      domainScores: Object.fromEntries(
        Array.from(this.domainBrainScores.entries()).map(([domain, scores]) => [
          domain,
          Object.fromEntries(scores),
        ])
      ),
    };
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

      // Adaptive routing health
      adaptive: {
        enabled: this.config.adaptiveRouting,
        explorationRate: this.config.explorationRate,
        promotedBrains: Array.from(this.brains.values()).filter(b => b.tier === 'promoted').length,
        demotedBrains: Array.from(this.brains.values()).filter(b => b.tier === 'demoted').length,
        topBrain: this.getBrainLeaderboard()[0] || null,
      },
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

  /**
   * Serialize state for persistence
   */
  serializeState() {
    const brainStates = {};
    for (const [id, brain] of this.brains) {
      brainStates[id] = {
        score: brain.score,
        tier: brain.tier,
        totalExecutions: brain.totalExecutions,
        successCount: brain.successCount,
        errorCount: brain.errorCount,
        avgResponseTime: brain.avgResponseTime,
        promotionCount: brain.promotionCount,
        demotionCount: brain.demotionCount,
      };
    }

    return {
      version: '2.0.0',
      timestamp: Date.now(),
      brains: brainStates,
      domainScores: Object.fromEntries(
        Array.from(this.domainBrainScores.entries()).map(([domain, scores]) => [
          domain,
          Object.fromEntries(scores),
        ])
      ),
      metrics: this.metrics,
    };
  }

  /**
   * Restore state from persistence
   */
  restoreState(state) {
    if (!state || state.version !== '2.0.0') {
      this.log('Invalid or incompatible state version', 'warn');
      return false;
    }

    // Restore brain states
    for (const [id, savedState] of Object.entries(state.brains)) {
      const brain = this.brains.get(id);
      if (brain) {
        Object.assign(brain, savedState);
      }
    }

    // Restore domain scores
    for (const [domain, scores] of Object.entries(state.domainScores)) {
      this.domainBrainScores.set(domain, new Map(Object.entries(scores)));
    }

    // Restore metrics
    Object.assign(this.metrics, state.metrics);

    this.log(`Restored state from ${new Date(state.timestamp).toISOString()}`, 'success');
    return true;
  }

  /**
   * Save state to file
   */
  saveStateToFile(filepath) {
    const state = this.serializeState();
    fs.writeFileSync(filepath, JSON.stringify(state, null, 2));
    this.log(`State saved to ${filepath}`, 'success');
    return filepath;
  }

  /**
   * Load state from file
   */
  loadStateFromFile(filepath) {
    if (!fs.existsSync(filepath)) {
      this.log(`State file not found: ${filepath}`, 'warn');
      return false;
    }

    const state = JSON.parse(fs.readFileSync(filepath, 'utf-8'));
    return this.restoreState(state);
  }
}

module.exports = { BrainMeshOrchestrator, CM1, BRAINS_REGISTRY };
