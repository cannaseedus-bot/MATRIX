#!/usr/bin/env node
/**
 * KUHUL Bridge - Connects .khl kernels to kuhul-es runtime
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 * @version 1.0.0
 *
 * Transpiles KUHUL bracket syntax to kuhul-es generator syntax:
 *   [Sek operation] → yield* Sek('operation')
 *   [Pop name]      → yield* Pop('name')
 *   [Wo binding]    → yield* Wo('binding')
 *   [Ch'en view]    → yield* Chen('view')
 *   [Yax cond]      → yield* Yax('cond')
 *   [Xul]           → yield* Xul()
 */

'use strict';

const fs = require('fs');
const path = require('path');
const { KUHULRuntimeNode } = require('kuhul-es/runtime/src/node');

// CM-1 Control Characters
const CM1 = {
  NUL: '\u0000',   // Null - inert region
  SOH: '\u0001',   // Start of Header
  STX: '\u0002',   // Start of Text (body begin)
  ETX: '\u0003',   // End of Text (body end)
  EOT: '\u0004',   // End of Transmission
  SO:  '\u000E',   // Shift Out (scope push)
  SI:  '\u000F',   // Shift In (scope pop)
  DLE: '\u0010',   // Data Link Escape (literal)
  FS:  '\u001C',   // File Separator
  GS:  '\u001D',   // Group Separator
  RS:  '\u001E',   // Record Separator
  US:  '\u001F',   // Unit Separator
};

/**
 * KUHULBridge - Transpiles and executes .khl files
 */
class KUHULBridge {
  constructor(options = {}) {
    this.runtime = new KUHULRuntimeNode();
    this.cm1Enabled = options.cm1 !== false;
    this.scopeStack = [];
    this.phaseHistory = [];
    this.cssAgents = new Map();

    // Register CSS agents from quadrant
    this.registerCSSAgents();

    // Bind custom operations
    this.bindCustomOperations();
  }

  /**
   * Register CSS Agents from quadrant specification
   */
  registerCSSAgents() {
    const agents = [
      { id: 'CSS_ATOMIC_AGENT', domain: 'atomic.css', fold: 2 },
      { id: 'CSS_XCFE_AGENT', domain: 'xcfe.css', fold: 1 },
      { id: 'CSS_RUNTIME_AGENT', domain: 'runtime.css', fold: 1 },
      { id: 'CSS_SVG_3D_AGENT', domain: 'svg_3d.css', fold: 4 },
    ];

    agents.forEach(agent => {
      this.cssAgents.set(agent.id, {
        ...agent,
        status: 'idle',
        lastExecution: null,
      });
    });
  }

  /**
   * Bind custom KUHUL operations for CSS/CM-1 integration
   */
  bindCustomOperations() {
    const self = this;

    // Override Sek for CSS agent routing
    this.runtime.executeSek = async function(operation, ...args) {
      // CM-1 phase tracking
      if (self.cm1Enabled) {
        self.phaseHistory.push({
          phase: 'body',
          operation,
          timestamp: Date.now(),
        });
      }

      // Route to CSS agents
      if (operation.startsWith('css.')) {
        return await self.routeToCSSAgent(operation, args);
      }

      // CM-1 control operations
      if (operation.startsWith('cm1.')) {
        return await self.executeCM1Operation(operation, args);
      }

      // XCFE control operations
      if (operation.startsWith('xcfe.')) {
        return await self.executeXCFEOperation(operation, args);
      }

      // Default Sek behavior
      this.log(`[Sek] ${operation}: ${JSON.stringify(args)}`);
      return { operation, args };
    };
  }

  /**
   * Route operation to appropriate CSS agent
   */
  async routeToCSSAgent(operation, args) {
    const [, agentType, action] = operation.split('.');

    const agentMap = {
      'atomic': 'CSS_ATOMIC_AGENT',
      'xcfe': 'CSS_XCFE_AGENT',
      'runtime': 'CSS_RUNTIME_AGENT',
      'svg3d': 'CSS_SVG_3D_AGENT',
    };

    const agentId = agentMap[agentType];
    if (!agentId) {
      return { error: `Unknown CSS agent type: ${agentType}` };
    }

    const agent = this.cssAgents.get(agentId);
    agent.status = 'executing';
    agent.lastExecution = Date.now();

    this.runtime.log(`[CSS:${agentId}] ${action}: ${JSON.stringify(args)}`);

    // Simulate CSS agent execution
    const result = {
      agent: agentId,
      action,
      args,
      fold: agent.fold,
      timestamp: Date.now(),
    };

    agent.status = 'idle';
    return result;
  }

  /**
   * Execute CM-1 control operation
   */
  async executeCM1Operation(operation, args) {
    const [, action] = operation.split('.');

    switch (action) {
      case 'scope_push':
        const scopeId = `scope_${Date.now()}_${Math.random().toString(36).slice(2, 8)}`;
        this.scopeStack.push(scopeId);
        return { action: 'scope_push', scopeId, depth: this.scopeStack.length };

      case 'scope_pop':
        const popped = this.scopeStack.pop();
        return { action: 'scope_pop', scopeId: popped, depth: this.scopeStack.length };

      case 'phase_transition':
        const [fromPhase, toPhase] = args;
        this.phaseHistory.push({ from: fromPhase, to: toPhase, timestamp: Date.now() });
        return { action: 'phase_transition', from: fromPhase, to: toPhase };

      case 'separator':
        const [sepType] = args;
        const sepMap = { file: CM1.FS, group: CM1.GS, record: CM1.RS, unit: CM1.US };
        return { action: 'separator', type: sepType, char: sepMap[sepType] };

      default:
        return { action, args, error: 'unknown_cm1_action' };
    }
  }

  /**
   * Execute XCFE control operation
   */
  async executeXCFEOperation(operation, args) {
    const [, control] = operation.split('.');

    const validControls = [
      'if', 'then', 'else', 'loop', 'break', 'continue',
      'dispatch', 'route', 'return',
      'perception', 'representation', 'reasoning',
      'decision', 'action', 'reflection',
    ];

    if (!validControls.includes(control)) {
      return { error: `Invalid XCFE control: ${control}` };
    }

    return {
      control,
      args,
      cssClass: `.xcfe-${control}`,
      timestamp: Date.now(),
    };
  }

  /**
   * Transpile .khl bracket syntax to kuhul-es generator syntax
   */
  transpile(khlSource) {
    let output = khlSource;

    // Remove block comments
    output = output.replace(/\/\*[\s\S]*?\*\//g, '');

    // Extract function blocks [Pop name] ... [Xul]
    const functionPattern = /\[Pop\s+(\w+)\]([\s\S]*?)\[Xul\]/g;
    const functions = [];
    let match;

    while ((match = functionPattern.exec(output)) !== null) {
      functions.push({
        name: match[1],
        body: match[2],
      });
    }

    // Transpile each glyph call
    const glyphPatterns = [
      { pattern: /\[Sek\s+([^\]]+)\]/g, replacement: "yield* Sek('$1')" },
      { pattern: /\[Pop\s+(\w+)\]/g, replacement: "yield* Pop('$1')" },
      { pattern: /\[Wo\s+([^\]]+)\]/g, replacement: "yield* Wo('$1')" },
      { pattern: /\[Ch'en\s+([^\]]+)\]/g, replacement: "yield* Chen('$1')" },
      { pattern: /\[Yax\s+([^\]]+)\]/g, replacement: "yield* Yax('$1')" },
      { pattern: /\[Xul\]/g, replacement: "yield* Xul()" },
    ];

    glyphPatterns.forEach(({ pattern, replacement }) => {
      output = output.replace(pattern, replacement);
    });

    // Transpile variable assignments [Wo name := value]
    output = output.replace(
      /yield\* Wo\('(\w+)\s*:=\s*([^']+)'\)/g,
      "τ $1 = $2"
    );

    // Transpile if/else blocks
    output = output.replace(
      /yield\* Wo\('if\s+([^']+)'\)/g,
      "if ($1)"
    );

    // Generate output with CM-1 markers
    const transpiled = `
// Transpiled from .khl by kuhul-bridge
// CM-1 Phase: ${CM1.SOH}HEADER${CM1.STX}

${functions.map(fn => `
function* ${fn.name}() {
  ${this.transpileFunctionBody(fn.body)}
}
`).join('\n')}

// CM-1 Phase: ${CM1.ETX}END${CM1.EOT}
`.trim();

    return transpiled;
  }

  /**
   * Transpile function body with proper indentation
   */
  transpileFunctionBody(body) {
    let output = body;

    // Transpile nested glyph calls
    output = output.replace(/\[Sek\s+([^\]]+)\]/g, "yield* Sek('$1');");
    output = output.replace(/\[Wo\s+([^\]]+)\]/g, "yield* Wo('$1');");
    output = output.replace(/\[Ch'en\s+([^\]]+)\]/g, "yield* Chen('$1');");
    output = output.replace(/\[Yax\s+([^\]]+)\]/g, "yield* Yax('$1');");

    // Clean up whitespace
    output = output
      .split('\n')
      .map(line => line.trim())
      .filter(line => line.length > 0)
      .join('\n  ');

    return output;
  }

  /**
   * Load and execute a .khl file
   */
  async executeFile(filepath) {
    const absolutePath = path.resolve(filepath);

    if (!fs.existsSync(absolutePath)) {
      throw new Error(`File not found: ${absolutePath}`);
    }

    const khlSource = fs.readFileSync(absolutePath, 'utf-8');

    // CM-1 Phase: Header Begin
    if (this.cm1Enabled) {
      this.phaseHistory.push({ phase: 'header.begin', timestamp: Date.now() });
    }

    // Transpile to kuhul-es format
    const transpiledSource = this.transpile(khlSource);

    // CM-1 Phase: Body Begin
    if (this.cm1Enabled) {
      this.phaseHistory.push({ phase: 'body.begin', timestamp: Date.now() });
    }

    // Execute via kuhul-es runtime
    await this.runtime.execute(transpiledSource);

    // CM-1 Phase: Transmission End
    if (this.cm1Enabled) {
      this.phaseHistory.push({ phase: 'transmission.end', timestamp: Date.now() });
    }

    return {
      file: absolutePath,
      πBindings: this.runtime.π.size,
      τBindings: this.runtime.τ.size,
      frames: this.runtime.frame,
      hashChain: this.runtime.hashChain.length,
      cm1Phases: this.phaseHistory.length,
      cssAgents: Object.fromEntries(this.cssAgents),
    };
  }

  /**
   * Execute raw KUHUL source
   */
  async execute(khlSource) {
    const transpiledSource = this.transpile(khlSource);
    await this.runtime.execute(transpiledSource);

    return {
      πBindings: this.runtime.π.size,
      τBindings: this.runtime.τ.size,
      frames: this.runtime.frame,
    };
  }

  /**
   * Get current state for debugging/replay
   */
  getState() {
    return {
      π: Object.fromEntries(this.runtime.π),
      τ: Object.fromEntries(this.runtime.τ),
      frame: this.runtime.frame,
      hashChain: this.runtime.hashChain,
      scopeStack: this.scopeStack,
      phaseHistory: this.phaseHistory,
      cssAgents: Object.fromEntries(this.cssAgents),
    };
  }

  /**
   * Save state for replay
   */
  saveState(filepath) {
    const state = this.getState();
    fs.writeFileSync(filepath, JSON.stringify(state, null, 2));
    return filepath;
  }
}

// CLI Interface
if (require.main === module) {
  const args = process.argv.slice(2);

  console.log(`
╔══════════════════════════════════════╗
║        KUHUL Bridge v1.0.0           ║
║   .khl → kuhul-es transpiler         ║
╚══════════════════════════════════════╝
`);

  if (args.length === 0) {
    console.log('Usage:');
    console.log('  node kuhul-bridge.js <file.khl>     Execute a .khl file');
    console.log('  node kuhul-bridge.js --transpile <file.khl>  Transpile only');
    console.log('  node kuhul-bridge.js --state <file.khl>      Show state after execution');
    process.exit(0);
  }

  const bridge = new KUHULBridge({ cm1: true });

  if (args[0] === '--transpile') {
    const source = fs.readFileSync(args[1], 'utf-8');
    console.log(bridge.transpile(source));
  } else if (args[0] === '--state') {
    bridge.executeFile(args[1]).then(result => {
      console.log('\nExecution Result:');
      console.log(JSON.stringify(result, null, 2));
      console.log('\nFull State:');
      console.log(JSON.stringify(bridge.getState(), null, 2));
    });
  } else {
    bridge.executeFile(args[0]).then(result => {
      console.log('\nExecution Complete:');
      console.log(JSON.stringify(result, null, 2));
    }).catch(err => {
      console.error('Execution failed:', err.message);
      process.exit(1);
    });
  }
}

module.exports = { KUHULBridge, CM1 };
