#!/usr/bin/env node
/**
 * Brain Mesh CLI & Entry Point
 *
 * Connects brain-mesh orchestrator to kuhul-bridge for
 * monitored, self-healing KUHUL execution.
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 * @version 1.0.0
 */

'use strict';

const fs = require('fs');
const path = require('path');
const http = require('http');
const { BrainMeshOrchestrator, CM1 } = require('./orchestrator');

// Try to load kuhul-bridge if available
let KUHULBridge;
try {
  KUHULBridge = require('../kuhul-bridge').KUHULBridge;
} catch (e) {
  KUHULBridge = null;
}

/**
 * Integrated Brain-KUHUL Runtime
 *
 * Combines:
 * - BrainMeshOrchestrator (LLM routing, error handling)
 * - KUHULBridge (.khl execution, CM-1 tracking)
 */
class BrainKUHULRuntime {
  constructor(options = {}) {
    this.orchestrator = new BrainMeshOrchestrator(options);
    this.bridge = KUHULBridge ? new KUHULBridge({ cm1: true }) : null;

    // Wire up events
    this.wireEvents();

    this.log('Brain-KUHUL Runtime initialized');
  }

  log(message, level = 'info') {
    this.orchestrator.log(`[Runtime] ${message}`, level);
  }

  /**
   * Wire orchestrator events to bridge
   */
  wireEvents() {
    // CM-1 phase events
    this.orchestrator.on('cm1:phase', (event) => {
      if (this.bridge) {
        this.bridge.phaseHistory.push({
          ...event,
          source: 'orchestrator',
        });
      }
    });

    // Fix applied events
    this.orchestrator.on('fix:applied', (fix) => {
      this.log(`Fix applied: ${fix.diagnosis}`, 'success');
    });
  }

  /**
   * Execute KUHUL file with brain monitoring
   */
  async executeFile(filepath) {
    if (!this.bridge) {
      return { success: false, error: 'KUHULBridge not available' };
    }

    this.log(`Executing: ${filepath}`);

    // CM-1 phase: header begin
    this.orchestrator.onCM1PhaseTransition('null', 'header.begin', { file: filepath });

    try {
      const result = await this.bridge.executeFile(filepath);

      // CM-1 phase: transmission end
      this.orchestrator.onCM1PhaseTransition('body.end', 'transmission.end', { result });

      return {
        success: true,
        ...result,
        efficiency: this.orchestrator.getEfficiency(),
      };

    } catch (error) {
      this.log(`Execution failed: ${error.message}`, 'error');

      // Route to brain for diagnosis
      const diagnosis = await this.orchestrator.executeTask({
        domain: 'runtime_orchestration',
        operation: 'diagnose_failure',
        args: { file: filepath, error: error.message },
        context: { bridge_state: this.bridge?.getState() },
      });

      return {
        success: false,
        error: error.message,
        diagnosis: diagnosis.output,
        efficiency: this.orchestrator.getEfficiency(),
      };
    }
  }

  /**
   * Execute task via brain mesh
   */
  async executeTask(task) {
    return await this.orchestrator.executeTask(task);
  }

  /**
   * Get combined status
   */
  async getStatus() {
    const health = await this.orchestrator.healthCheck();

    return {
      ...health,
      bridge: this.bridge ? {
        available: true,
        cssAgents: this.bridge.cssAgents.size,
        scopeStack: this.bridge.scopeStack.length,
      } : { available: false },
    };
  }
}

/**
 * HTTP Server for Brain Mesh API
 */
function createServer(runtime, port = 5002) {
  const server = http.createServer(async (req, res) => {
    // CORS headers
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

    if (req.method === 'OPTIONS') {
      res.writeHead(204);
      res.end();
      return;
    }

    const url = new URL(req.url, `http://${req.headers.host}`);
    const pathname = url.pathname;

    // JSON response helper
    const json = (data, status = 200) => {
      res.writeHead(status, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify(data, null, 2));
    };

    // Parse body for POST requests
    const parseBody = () => new Promise((resolve) => {
      let body = '';
      req.on('data', chunk => body += chunk);
      req.on('end', () => {
        try {
          resolve(JSON.parse(body || '{}'));
        } catch {
          resolve({});
        }
      });
    });

    try {
      // Routes
      if (pathname === '/status' && req.method === 'GET') {
        const status = await runtime.getStatus();
        json(status);

      } else if (pathname === '/brains' && req.method === 'GET') {
        const brains = runtime.orchestrator.getBrainStatus();
        json(brains);

      } else if (pathname === '/efficiency' && req.method === 'GET') {
        const efficiency = runtime.orchestrator.getEfficiency();
        json(efficiency);

      } else if (pathname === '/execute' && req.method === 'POST') {
        const body = await parseBody();

        if (body.file) {
          const result = await runtime.executeFile(body.file);
          json(result);
        } else if (body.task) {
          const result = await runtime.executeTask(body.task);
          json(result);
        } else {
          json({ error: 'Missing file or task' }, 400);
        }

      } else if (pathname === '/task' && req.method === 'POST') {
        const task = await parseBody();
        const result = await runtime.executeTask(task);
        json(result);

      } else if (pathname === '/cm1/phase' && req.method === 'POST') {
        const body = await parseBody();
        runtime.orchestrator.onCM1PhaseTransition(
          body.from || 'null',
          body.to,
          body.context || {}
        );
        json({ success: true, phase: body.to });

      } else if (pathname === '/cm1/scope' && req.method === 'POST') {
        const body = await parseBody();
        runtime.orchestrator.onCM1ScopeChange(body.action, body.scopeId);
        json({ success: true, depth: runtime.orchestrator.cm1State.scopeStack.length });

      } else if (pathname === '/errors/process' && req.method === 'POST') {
        await runtime.orchestrator.processErrorQueue();
        json({ success: true, remaining: runtime.orchestrator.errorQueue.length });

      } else {
        json({ error: 'Not found', endpoints: [
          'GET /status',
          'GET /brains',
          'GET /efficiency',
          'POST /execute { file | task }',
          'POST /task { domain, operation, args }',
          'POST /cm1/phase { from, to, context }',
          'POST /cm1/scope { action, scopeId }',
          'POST /errors/process',
        ]}, 404);
      }

    } catch (error) {
      json({ error: error.message }, 500);
    }
  });

  server.listen(port, () => {
    console.log(`Brain Mesh API listening on http://127.0.0.1:${port}`);
  });

  return server;
}

/**
 * CLI Interface
 */
async function cli() {
  const args = process.argv.slice(2);
  const command = args[0];

  console.log(`
╔══════════════════════════════════════════╗
║         BRAIN MESH ORCHESTRATOR          ║
║   LLM-powered self-healing KUHUL runtime ║
╚══════════════════════════════════════════╝
`);

  const runtime = new BrainKUHULRuntime();

  switch (command) {
    case 'serve':
    case 'server': {
      const port = parseInt(args[1] || '5002', 10);
      createServer(runtime, port);
      break;
    }

    case 'execute':
    case 'run': {
      const file = args[1];
      if (!file) {
        console.error('Usage: brain-mesh execute <file.khl>');
        process.exit(1);
      }
      const result = await runtime.executeFile(file);
      console.log('\nResult:');
      console.log(JSON.stringify(result, null, 2));
      break;
    }

    case 'task': {
      const taskJson = args[1];
      if (!taskJson) {
        console.error('Usage: brain-mesh task \'{"domain":"...", "operation":"..."}\'');
        process.exit(1);
      }
      const task = JSON.parse(taskJson);
      const result = await runtime.executeTask(task);
      console.log('\nResult:');
      console.log(JSON.stringify(result, null, 2));
      break;
    }

    case 'status': {
      const status = await runtime.getStatus();
      console.log('\nStatus:');
      console.log(JSON.stringify(status, null, 2));
      break;
    }

    case 'brains': {
      const brains = runtime.orchestrator.getBrainStatus();
      console.log('\nBrains:');
      console.log(JSON.stringify(brains, null, 2));
      break;
    }

    case 'efficiency': {
      const efficiency = runtime.orchestrator.getEfficiency();
      console.log('\nEfficiency Metrics:');
      console.log(JSON.stringify(efficiency, null, 2));
      break;
    }

    case 'health': {
      const health = await runtime.orchestrator.healthCheck();
      console.log('\nHealth Check:');
      console.log(JSON.stringify(health, null, 2));
      break;
    }

    default:
      console.log(`Usage: brain-mesh <command> [args]

Commands:
  serve [port]           Start HTTP API server (default: 5002)
  execute <file.khl>     Execute KUHUL file with brain monitoring
  task '<json>'          Execute task via brain mesh
  status                 Show runtime status
  brains                 Show brain status
  efficiency             Show efficiency metrics
  health                 Run health check

Examples:
  node cli/brain-mesh serve 5002
  node cli/brain-mesh execute cli/kuhul_runtime_kernel.khl
  node cli/brain-mesh task '{"domain":"ui_design_emerald","operation":"generate_css"}'
`);
      break;
  }
}

// Run CLI if executed directly
if (require.main === module) {
  cli().catch(err => {
    console.error('Error:', err.message);
    process.exit(1);
  });
}

module.exports = {
  BrainKUHULRuntime,
  BrainMeshOrchestrator,
  createServer,
};
