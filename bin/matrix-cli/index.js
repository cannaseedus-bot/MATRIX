#!/usr/bin/env node
/**
 * Matrix CLI - Command-line interface with DNS-based tunneling
 *
 * Communicates with Brain-Mesh API using DNS TXT records
 * to avoid HTTP firewall issues. Uses TTL for polling and
 * TTP (Time-To-Propagate) for eventual consistency.
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 * @version 2.1.0
 */

const fs = require('fs');
const path = require('path');
const { DNSTunnel } = require('./dns-tunnel');

// ═══════════════════════════════════════════════════════════════════════════
// CONFIGURATION
// ═══════════════════════════════════════════════════════════════════════════

const CONFIG_FILE = path.join(process.env.HOME || process.env.USERPROFILE, '.matrix-cli.json');
const VERSION = '2.1.0';

const DEFAULT_CONFIG = {
    server: {
        host: 'localhost',
        port: 80,
        protocol: 'http',
        dnsServer: '8.8.8.8',
        dnsDomain: 'matrix.local',
    },
    tunnel: {
        enabled: false,
        ttl: 300,           // DNS TTL in seconds
        pollInterval: 5000, // Poll interval in ms
        ttpWindow: 10000,   // Time-to-propagate window
    },
    auth: {
        apiKey: null,
    },
    cm1: {
        phase: 'IDLE',
        scopeDepth: 0,
    }
};

// ═══════════════════════════════════════════════════════════════════════════
// CLI PARSER
// ═══════════════════════════════════════════════════════════════════════════

function parseArgs(args) {
    const result = {
        command: null,
        subcommand: null,
        positional: [],
        flags: {},
    };

    let i = 0;
    while (i < args.length) {
        const arg = args[i];

        if (arg.startsWith('--')) {
            const [key, value] = arg.slice(2).split('=');
            result.flags[key] = value ?? true;
        } else if (arg.startsWith('-')) {
            result.flags[arg.slice(1)] = args[++i] ?? true;
        } else if (!result.command) {
            result.command = arg;
        } else if (!result.subcommand) {
            result.subcommand = arg;
        } else {
            result.positional.push(arg);
        }
        i++;
    }

    return result;
}

// ═══════════════════════════════════════════════════════════════════════════
// COMMANDS
// ═══════════════════════════════════════════════════════════════════════════

const commands = {
    // ─────────────────────────────────────────────────────────────────────────
    // SETUP & CONFIG
    // ─────────────────────────────────────────────────────────────────────────
    setup: {
        description: 'Configure Matrix CLI',
        async run(args, config) {
            console.log('Matrix CLI Setup');
            console.log('─'.repeat(40));

            // Interactive prompts would go here
            // For now, create default config

            const newConfig = { ...DEFAULT_CONFIG };

            if (args.flags.host) {
                newConfig.server.host = args.flags.host;
            }
            if (args.flags.dns) {
                newConfig.tunnel.enabled = true;
                newConfig.server.dnsServer = args.flags.dns === true ? '8.8.8.8' : args.flags.dns;
            }
            if (args.flags.key) {
                newConfig.auth.apiKey = args.flags.key;
            }

            fs.writeFileSync(CONFIG_FILE, JSON.stringify(newConfig, null, 2));
            console.log(`Configuration saved to ${CONFIG_FILE}`);
            console.log('\nSettings:');
            console.log(`  Host: ${newConfig.server.host}`);
            console.log(`  DNS Tunnel: ${newConfig.tunnel.enabled ? 'enabled' : 'disabled'}`);
            console.log(`  API Key: ${newConfig.auth.apiKey ? '***set***' : 'not set'}`);
        }
    },

    config: {
        description: 'Show or modify configuration',
        async run(args, config) {
            if (args.subcommand === 'show') {
                console.log(JSON.stringify(config, null, 2));
            } else if (args.subcommand === 'set') {
                const [key, value] = args.positional;
                if (!key || value === undefined) {
                    console.error('Usage: matrix-cli config set <key> <value>');
                    return;
                }
                setNestedValue(config, key, value);
                fs.writeFileSync(CONFIG_FILE, JSON.stringify(config, null, 2));
                console.log(`Set ${key} = ${value}`);
            } else {
                console.log('Usage: matrix-cli config <show|set>');
            }
        }
    },

    // ─────────────────────────────────────────────────────────────────────────
    // CONNECTION
    // ─────────────────────────────────────────────────────────────────────────
    connect: {
        description: 'Connect to Brain-Mesh server',
        async run(args, config) {
            const useDns = args.flags.dns || config.tunnel.enabled;

            console.log(`Connecting to ${config.server.host}...`);

            if (useDns) {
                console.log('Using DNS tunnel mode');
                const tunnel = new DNSTunnel(config);
                const result = await tunnel.query('ping');
                if (result.success) {
                    console.log('✓ DNS tunnel connected');
                    console.log(`  Server: ${config.server.dnsServer}`);
                    console.log(`  Domain: ${config.server.dnsDomain}`);
                } else {
                    console.error('✗ DNS tunnel failed:', result.error);
                }
            } else {
                console.log('Using HTTP mode');
                try {
                    const response = await httpRequest(config, '/api/brain-mesh/health.php');
                    console.log('✓ HTTP connected');
                    console.log(`  Health: ${response.status}`);
                } catch (error) {
                    console.error('✗ HTTP connection failed:', error.message);
                }
            }
        }
    },

    // ─────────────────────────────────────────────────────────────────────────
    // TUNNEL
    // ─────────────────────────────────────────────────────────────────────────
    tunnel: {
        description: 'Manage DNS tunnel',
        async run(args, config) {
            const tunnel = new DNSTunnel(config);

            switch (args.subcommand) {
                case 'start':
                    console.log('Starting DNS tunnel...');
                    const ttl = parseInt(args.flags.ttl) || config.tunnel.ttl;
                    await tunnel.start(ttl);
                    console.log(`DNS tunnel started (TTL: ${ttl}s)`);
                    break;

                case 'stop':
                    await tunnel.stop();
                    console.log('DNS tunnel stopped');
                    break;

                case 'status':
                    const status = await tunnel.status();
                    console.log('DNS Tunnel Status');
                    console.log('─'.repeat(40));
                    console.log(`  Active: ${status.active}`);
                    console.log(`  Server: ${status.server}`);
                    console.log(`  TTL: ${status.ttl}s`);
                    console.log(`  Last Query: ${status.lastQuery || 'never'}`);
                    console.log(`  Pending: ${status.pending} messages`);
                    break;

                case 'send':
                    const message = args.positional.join(' ');
                    if (!message) {
                        console.error('Usage: matrix-cli tunnel send <message>');
                        return;
                    }
                    const result = await tunnel.send(message);
                    console.log('Response:', result);
                    break;

                default:
                    console.log('Usage: matrix-cli tunnel <start|stop|status|send>');
            }
        }
    },

    // ─────────────────────────────────────────────────────────────────────────
    // BRAIN
    // ─────────────────────────────────────────────────────────────────────────
    brain: {
        description: 'Brain-Mesh management',
        async run(args, config) {
            const client = createClient(config);

            switch (args.subcommand) {
                case 'list':
                    const brains = await client.terminal('brain-mesh brains');
                    console.log(brains.output || JSON.stringify(brains.data, null, 2));
                    break;

                case 'leaderboard':
                case 'lb':
                    const domain = args.positional[0];
                    const cmd = domain ? `brain-mesh leaderboard --domain=${domain}` : 'brain-mesh leaderboard';
                    const lb = await client.terminal(cmd);
                    console.log(lb.output || JSON.stringify(lb.data, null, 2));
                    break;

                case 'stats':
                    const stats = await client.terminal('brain-mesh stats');
                    console.log(stats.output || JSON.stringify(stats.data, null, 2));
                    break;

                case 'health':
                    const health = await client.terminal('brain-mesh health');
                    console.log(health.output || JSON.stringify(health.data, null, 2));
                    break;

                case 'score':
                    const brainId = args.positional[0];
                    if (!brainId) {
                        console.error('Usage: matrix-cli brain score <brain-id>');
                        return;
                    }
                    const score = await client.terminal(`brain-mesh select ${brainId}`);
                    console.log(score.output || JSON.stringify(score.data, null, 2));
                    break;

                default:
                    console.log('Usage: matrix-cli brain <list|leaderboard|stats|health|score>');
            }
        }
    },

    // ─────────────────────────────────────────────────────────────────────────
    // TERMINAL
    // ─────────────────────────────────────────────────────────────────────────
    terminal: {
        description: 'Execute terminal command',
        aliases: ['t', 'term'],
        async run(args, config) {
            const command = args.subcommand
                ? [args.subcommand, ...args.positional].join(' ')
                : args.positional.join(' ');

            if (!command) {
                console.error('Usage: matrix-cli terminal <command>');
                return;
            }

            const client = createClient(config);
            const result = await client.terminal(command);

            if (result.success) {
                console.log(result.output || JSON.stringify(result.data, null, 2));
            } else {
                console.error('Error:', result.error);
                if (result.suggestion) {
                    console.log('Did you mean:', result.suggestion);
                }
            }
        }
    },

    // ─────────────────────────────────────────────────────────────────────────
    // LLM
    // ─────────────────────────────────────────────────────────────────────────
    llm: {
        description: 'LLM provider management',
        async run(args, config) {
            const client = createClient(config);

            switch (args.subcommand) {
                case 'providers':
                    const providers = await client.terminal('llm providers');
                    console.log(providers.output || JSON.stringify(providers.data, null, 2));
                    break;

                case 'models':
                    const provider = args.flags.provider ? `--provider=${args.flags.provider}` : '';
                    const models = await client.terminal(`llm models ${provider}`);
                    console.log(models.output || JSON.stringify(models.data, null, 2));
                    break;

                case 'chat':
                    const message = args.positional.join(' ');
                    if (!message) {
                        console.error('Usage: matrix-cli llm chat <message>');
                        return;
                    }
                    const model = args.flags.model || 'auto';
                    const chat = await client.terminal(`llm chat ${model} "${message}"`);
                    console.log(chat.output || JSON.stringify(chat.data, null, 2));
                    break;

                default:
                    console.log('Usage: matrix-cli llm <providers|models|chat>');
            }
        }
    },

    // ─────────────────────────────────────────────────────────────────────────
    // KUHUL
    // ─────────────────────────────────────────────────────────────────────────
    kuhul: {
        description: 'K\'UHUL glyph execution',
        async run(args, config) {
            const client = createClient(config);

            switch (args.subcommand) {
                case 'exec':
                    const code = args.positional.join(' ');
                    if (!code) {
                        console.error('Usage: matrix-cli kuhul exec <glyph_code>');
                        return;
                    }
                    const result = await client.terminal(`kuhul exec "${code}"`);
                    console.log(result.output || JSON.stringify(result.data, null, 2));
                    break;

                case 'glyphs':
                    const glyphs = await client.terminal('kuhul glyphs');
                    console.log(glyphs.output || JSON.stringify(glyphs.data, null, 2));
                    break;

                case 'status':
                    const status = await client.terminal('kuhul status');
                    console.log(status.output || JSON.stringify(status.data, null, 2));
                    break;

                default:
                    console.log('Usage: matrix-cli kuhul <exec|glyphs|status>');
            }
        }
    },

    // ─────────────────────────────────────────────────────────────────────────
    // RIG FEDERATION
    // ─────────────────────────────────────────────────────────────────────────
    rig: {
        description: 'RIG federation - share local models via DNS',
        async run(args, config) {
            const { RigManager } = require('./rig-manager');
            const rig = new RigManager(config);

            switch (args.subcommand) {
                case 'register':
                    const name = args.flags.name || `rig-${Date.now().toString(36)}`;
                    console.log(`Registering rig "${name}"...`);
                    const regResult = await rig.register(name);
                    if (regResult.success) {
                        console.log('✓ Rig registered');
                        console.log(`  ID: ${regResult.rigId}`);
                        console.log(`  Models: ${regResult.models?.join(', ') || 'none detected'}`);
                    } else {
                        console.error('✗ Registration failed:', regResult.error);
                    }
                    break;

                case 'unregister':
                    console.log('Unregistering rig...');
                    const unregResult = await rig.unregister();
                    if (unregResult.success) {
                        console.log('✓ Rig unregistered');
                    } else {
                        console.error('✗ Unregister failed:', unregResult.error);
                    }
                    break;

                case 'advertise':
                    console.log('Advertising models...');
                    const advResult = await rig.advertise();
                    if (advResult.success) {
                        console.log('✓ Models advertised');
                        console.log(`  Models: ${advResult.models?.join(', ') || 'none'}`);
                    } else {
                        console.error('✗ Advertise failed:', advResult.error);
                    }
                    break;

                case 'serve':
                    const interval = parseInt(args.flags.interval) || 5000;
                    console.log(`Starting rig server (poll interval: ${interval}ms)...`);
                    console.log('Press Ctrl+C to stop\n');
                    await rig.serve(interval);
                    break;

                case 'status':
                    const status = await rig.status();
                    console.log('RIG Status');
                    console.log('─'.repeat(40));
                    console.log(`  ID: ${status.rigId || 'not registered'}`);
                    console.log(`  Name: ${status.name || 'unknown'}`);
                    console.log(`  Registered: ${status.registered ? 'yes' : 'no'}`);
                    console.log(`  Models: ${status.models?.join(', ') || 'none'}`);
                    if (status.capabilities) {
                        console.log(`  Platform: ${status.capabilities.platform}`);
                        if (status.capabilities.gpu) {
                            console.log(`  GPU: ${status.capabilities.gpu}`);
                        }
                    }
                    break;

                case 'list':
                    console.log('Fetching federated rigs...');
                    const list = await httpRequest(config, '/api/brain-mesh/rig-router.php?action=list');
                    if (list.success) {
                        console.log(`\nFederated Rigs (${list.online}/${list.total} online)\n`);
                        console.log('─'.repeat(60));
                        for (const r of list.rigs) {
                            const statusIcon = r.status === 'online' ? '●' : '○';
                            console.log(`${statusIcon} ${r.name} (${r.id})`);
                            console.log(`    Models: ${r.models?.join(', ') || 'none'}`);
                            console.log(`    Score: ${(r.score * 100).toFixed(1)}% | Latency: ${r.avg_latency_ms}ms`);
                            console.log(`    Requests: ${r.successful_requests}/${r.total_requests}`);
                        }
                    } else {
                        console.error('✗ Failed to list rigs:', list.error);
                    }
                    break;

                case 'request':
                    const model = args.flags.model || args.positional[0];
                    const prompt = args.positional.slice(model === args.positional[0] ? 1 : 0).join(' ');
                    if (!model || !prompt) {
                        console.error('Usage: matrix-cli rig request --model=<model> <prompt>');
                        return;
                    }
                    console.log(`Queuing request to rig (model: ${model})...`);
                    const reqResult = await httpRequest(config, '/api/brain-mesh/rig-router.php?action=request', {
                        method: 'POST',
                        body: JSON.stringify({ model, prompt }),
                    });
                    if (reqResult.success) {
                        console.log(`✓ Request queued (ID: ${reqResult.requestId}, Rig: ${reqResult.rigId})`);
                        console.log('Waiting for response...');
                        const waitResult = await httpRequest(config, `/api/brain-mesh/rig-router.php?action=wait&requestId=${reqResult.requestId}&timeout=60`);
                        if (waitResult.success) {
                            console.log(`\n${waitResult.response}`);
                            console.log(`\n[Latency: ${waitResult.latency}ms]`);
                        } else {
                            console.error('✗ Request failed:', waitResult.error);
                        }
                    } else {
                        console.error('✗ Queue failed:', reqResult.error);
                    }
                    break;

                default:
                    console.log(`
RIG Federation - Share local Ollama models via DNS

USAGE:
  matrix-cli rig <command> [options]

COMMANDS:
  register      Register this machine as a rig
  unregister    Remove this rig from federation
  advertise     Update available models
  serve         Start serving requests (poll mode)
  status        Show local rig status
  list          List all federated rigs
  request       Send inference request to a rig

OPTIONS:
  --name=<name>       Rig name (for register)
  --interval=<ms>     Poll interval (for serve, default: 5000)
  --model=<model>     Model to use (for request)

EXAMPLES:
  matrix-cli rig register --name=home-gpu
  matrix-cli rig serve --interval=3000
  matrix-cli rig list
  matrix-cli rig request --model=llama3.2 "Hello world"

This enables local machines to share Ollama models with the
Brain-Mesh federation without HTTP port forwarding.
`);
            }
        }
    },

    // ─────────────────────────────────────────────────────────────────────────
    // HELP
    // ─────────────────────────────────────────────────────────────────────────
    help: {
        description: 'Show help',
        async run(args) {
            console.log(`
Matrix CLI v${VERSION}
Command-line interface with DNS-based tunneling

USAGE:
  matrix-cli <command> [subcommand] [options]

COMMANDS:
  setup              Configure Matrix CLI
  config             Show or modify configuration
  connect            Connect to Brain-Mesh server
  tunnel             Manage DNS tunnel
  brain              Brain-Mesh management
  terminal           Execute terminal command
  llm                LLM provider management
  kuhul              K'UHUL glyph execution
  rig                RIG federation (share local models)
  help               Show this help
  version            Show version

TUNNEL OPTIONS:
  --dns              Use DNS tunnel mode
  --ttl=<seconds>    Set DNS TTL (default: 300)

EXAMPLES:
  matrix-cli setup --host=myserver.com --dns
  matrix-cli connect --dns
  matrix-cli brain leaderboard
  matrix-cli terminal "brain-mesh health"
  matrix-cli tunnel start --ttl=60
  matrix-cli kuhul exec "⟁Sek⟁ brain-mesh stats"
  matrix-cli rig register --name=home-gpu
  matrix-cli rig serve

DNS-BASED COMMUNICATION:
  Matrix CLI can communicate via DNS TXT records, avoiding
  HTTP firewall restrictions. Use --dns flag to enable.

  Protocol: TTP (Time-To-Propagate)
  - Commands encoded in DNS queries
  - Responses in TXT records
  - TTL controls caching/polling

@law ASX = XCFE = XJSON = KUHUL = AST = CM-1
`);
        }
    },

    version: {
        description: 'Show version',
        async run() {
            console.log(`Matrix CLI v${VERSION}`);
            console.log('@law ASX = XCFE = XJSON = KUHUL = AST = CM-1');
        }
    }
};

// ═══════════════════════════════════════════════════════════════════════════
// UTILITIES
// ═══════════════════════════════════════════════════════════════════════════

function loadConfig() {
    try {
        if (fs.existsSync(CONFIG_FILE)) {
            const data = fs.readFileSync(CONFIG_FILE, 'utf8');
            return { ...DEFAULT_CONFIG, ...JSON.parse(data) };
        }
    } catch (e) {
        // Use defaults
    }
    return { ...DEFAULT_CONFIG };
}

function setNestedValue(obj, path, value) {
    const keys = path.split('.');
    let current = obj;
    for (let i = 0; i < keys.length - 1; i++) {
        if (!current[keys[i]]) current[keys[i]] = {};
        current = current[keys[i]];
    }
    current[keys[keys.length - 1]] = value;
}

function createClient(config) {
    const useDns = config.tunnel.enabled;

    return {
        async terminal(cmd) {
            if (useDns) {
                const tunnel = new DNSTunnel(config);
                return tunnel.query(`terminal:${cmd}`);
            } else {
                return httpRequest(config, '/api/brain-mesh/terminal.php', {
                    method: 'POST',
                    body: JSON.stringify({ cmd }),
                });
            }
        }
    };
}

async function httpRequest(config, endpoint, options = {}) {
    const url = `${config.server.protocol}://${config.server.host}:${config.server.port}${endpoint}`;

    const headers = {
        'Content-Type': 'application/json',
    };

    if (config.auth.apiKey) {
        headers['X-API-KEY'] = config.auth.apiKey;
    }

    // Use native fetch (Node 18+)
    const response = await fetch(url, {
        method: options.method || 'GET',
        headers,
        body: options.body,
    });

    return response.json();
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN
// ═══════════════════════════════════════════════════════════════════════════

async function main() {
    const args = parseArgs(process.argv.slice(2));
    const config = loadConfig();

    // Find command (check aliases too)
    let cmd = commands[args.command];
    if (!cmd) {
        for (const [name, command] of Object.entries(commands)) {
            if (command.aliases?.includes(args.command)) {
                cmd = command;
                break;
            }
        }
    }

    if (!cmd || args.command === 'help' || args.flags.help || args.flags.h) {
        await commands.help.run(args);
        return;
    }

    try {
        await cmd.run(args, config);
    } catch (error) {
        console.error('Error:', error.message);
        if (args.flags.verbose || args.flags.v) {
            console.error(error.stack);
        }
        process.exit(1);
    }
}

main();
