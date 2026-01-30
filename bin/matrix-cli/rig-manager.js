/**
 * Rig Manager - Federated Local Model Sharing via DNS
 *
 * Allows local rigs to offer their Ollama models to the Brain-Mesh
 * network without HTTP port forwarding. Uses DNS TXT records for
 * communication through firewalls.
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 * @version 2.1.0
 */

const { DNSTunnel } = require('./dns-tunnel');
const crypto = require('crypto');
const { execSync, spawn } = require('child_process');

// ═══════════════════════════════════════════════════════════════════════════
// RIG MANAGER CLASS
// ═══════════════════════════════════════════════════════════════════════════

class RigManager {
    constructor(config) {
        this.config = config;
        this.tunnel = new DNSTunnel(config);
        this.rigId = null;
        this.rigName = null;
        this.models = [];
        this.capabilities = {};
        this.running = false;
        this.pollInterval = config.rig?.pollInterval || 5000;
        this.ollamaHost = config.ollama?.host || 'http://localhost:11434';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // REGISTRATION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate unique rig ID
     */
    generateRigId() {
        const hostname = require('os').hostname();
        const hash = crypto.createHash('sha256')
            .update(hostname + Date.now())
            .digest('hex')
            .slice(0, 16);
        return `rig-${hash}`;
    }

    /**
     * Register this rig with Brain-Mesh
     */
    async register(name, options = {}) {
        this.rigId = this.generateRigId();
        this.rigName = name;

        // Auto-detect models if requested
        if (options.models === 'auto') {
            this.models = await this.detectOllamaModels();
        } else if (options.models) {
            this.models = options.models.split(',').map(m => m.trim());
        }

        // Detect capabilities
        this.capabilities = await this.detectCapabilities();

        // Send registration via DNS
        const registration = {
            type: 'rig.register',
            rigId: this.rigId,
            name: this.rigName,
            models: this.models,
            capabilities: this.capabilities,
            timestamp: Date.now(),
        };

        console.log('Registering rig...');
        console.log(`  ID: ${this.rigId}`);
        console.log(`  Name: ${this.rigName}`);
        console.log(`  Models: ${this.models.join(', ') || 'none'}`);

        const result = await this.tunnel.query(`register:${JSON.stringify(registration)}`);

        if (result.success || result.simulated) {
            // Save rig config locally
            this.saveRigConfig();
            console.log('✓ Rig registered successfully');
            return { success: true, rigId: this.rigId };
        } else {
            console.error('✗ Registration failed:', result.error);
            return { success: false, error: result.error };
        }
    }

    /**
     * Unregister this rig
     */
    async unregister() {
        if (!this.rigId) {
            this.loadRigConfig();
        }

        if (!this.rigId) {
            return { success: false, error: 'No rig registered' };
        }

        const result = await this.tunnel.query(`unregister:${this.rigId}`);

        // Clear local config
        this.clearRigConfig();
        console.log('✓ Rig unregistered');

        return { success: true };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // MODEL DETECTION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Detect available Ollama models
     */
    async detectOllamaModels() {
        try {
            const response = await fetch(`${this.ollamaHost}/api/tags`);
            const data = await response.json();
            return data.models?.map(m => m.name) || [];
        } catch (e) {
            console.warn('Could not detect Ollama models:', e.message);
            return [];
        }
    }

    /**
     * Detect system capabilities
     */
    async detectCapabilities() {
        const caps = {
            platform: process.platform,
            arch: process.arch,
            cpus: require('os').cpus().length,
            memory: Math.round(require('os').totalmem() / (1024 * 1024 * 1024)) + 'GB',
        };

        // Try to detect GPU (basic)
        try {
            if (process.platform === 'linux') {
                const nvidia = execSync('nvidia-smi --query-gpu=name,memory.total --format=csv,noheader 2>/dev/null', { encoding: 'utf8' });
                if (nvidia) {
                    const [name, vram] = nvidia.trim().split(', ');
                    caps.gpu = name;
                    caps.vram = vram;
                }
            } else if (process.platform === 'win32') {
                const nvidia = execSync('nvidia-smi --query-gpu=name,memory.total --format=csv,noheader 2>nul', { encoding: 'utf8' });
                if (nvidia) {
                    const [name, vram] = nvidia.trim().split(', ');
                    caps.gpu = name;
                    caps.vram = vram;
                }
            }
        } catch (e) {
            // No NVIDIA GPU or nvidia-smi not available
        }

        return caps;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ADVERTISEMENT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Advertise current models and status
     */
    async advertise(options = {}) {
        if (!this.rigId) {
            this.loadRigConfig();
        }

        if (!this.rigId) {
            return { success: false, error: 'No rig registered. Run: matrix-cli rig register' };
        }

        // Refresh model list
        this.models = await this.detectOllamaModels();

        // Update capabilities if requested
        if (options.capabilities) {
            Object.assign(this.capabilities, options.capabilities);
        }

        const advertisement = {
            type: 'rig.advertise',
            rigId: this.rigId,
            name: this.rigName,
            models: this.models,
            capabilities: this.capabilities,
            status: 'online',
            timestamp: Date.now(),
        };

        console.log('Advertising rig...');
        console.log(`  Models: ${this.models.join(', ') || 'none'}`);

        const result = await this.tunnel.query(`advertise:${JSON.stringify(advertisement)}`);

        if (result.success || result.simulated) {
            console.log('✓ Advertisement sent');
            return { success: true, models: this.models };
        } else {
            console.error('✗ Advertisement failed:', result.error);
            return { success: false, error: result.error };
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SERVING
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Start serving requests
     */
    async serve(options = {}) {
        if (!this.rigId) {
            this.loadRigConfig();
        }

        if (!this.rigId) {
            console.error('No rig registered. Run: matrix-cli rig register');
            return { success: false, error: 'No rig registered' };
        }

        this.pollInterval = options.poll ? parseInt(options.poll) * 1000 : this.pollInterval;
        this.running = true;

        console.log('Starting rig server...');
        console.log(`  Rig ID: ${this.rigId}`);
        console.log(`  Poll interval: ${this.pollInterval / 1000}s`);
        console.log(`  Ollama host: ${this.ollamaHost}`);
        console.log('');
        console.log('Waiting for requests... (Ctrl+C to stop)');
        console.log('─'.repeat(50));

        // Initial advertisement
        await this.advertise();

        // Start polling loop
        while (this.running) {
            await this.pollAndProcess();
            await this.sleep(this.pollInterval);
        }

        return { success: true };
    }

    /**
     * Poll for pending requests and process them
     */
    async pollAndProcess() {
        try {
            const result = await this.tunnel.query(`poll:${this.rigId}`);

            if (result.requests && result.requests.length > 0) {
                for (const req of result.requests) {
                    await this.processRequest(req);
                }
            }
        } catch (e) {
            // Silent fail on poll errors
        }
    }

    /**
     * Process a single inference request
     */
    async processRequest(request) {
        console.log(`\n[${new Date().toISOString()}] Request: ${request.id}`);
        console.log(`  Model: ${request.model}`);
        console.log(`  Prompt: ${(request.prompt || '').slice(0, 50)}...`);

        const startTime = Date.now();

        try {
            // Execute on local Ollama
            const response = await this.executeOllama(request);
            const latency = Date.now() - startTime;

            console.log(`  ✓ Completed in ${latency}ms`);

            // Send response via DNS
            await this.tunnel.query(`response:${JSON.stringify({
                type: 'rig.response',
                rigId: this.rigId,
                requestId: request.id,
                sessionId: request.sessionId,
                success: true,
                response: response,
                latency,
                timestamp: Date.now(),
            })}`);

        } catch (error) {
            console.log(`  ✗ Error: ${error.message}`);

            // Send error response
            await this.tunnel.query(`response:${JSON.stringify({
                type: 'rig.response',
                rigId: this.rigId,
                requestId: request.id,
                sessionId: request.sessionId,
                success: false,
                error: error.message,
                timestamp: Date.now(),
            })}`);
        }
    }

    /**
     * Execute request on local Ollama
     */
    async executeOllama(request) {
        const endpoint = request.type === 'embedding'
            ? `${this.ollamaHost}/api/embeddings`
            : `${this.ollamaHost}/api/generate`;

        const body = {
            model: request.model,
            prompt: request.prompt,
            stream: false,
        };

        if (request.options) {
            Object.assign(body, request.options);
        }

        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });

        if (!response.ok) {
            throw new Error(`Ollama error: ${response.status}`);
        }

        const data = await response.json();
        return data.response || data.embedding || data;
    }

    /**
     * Stop serving
     */
    stop() {
        this.running = false;
        console.log('\nStopping rig server...');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // STATUS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Get rig status
     */
    async status() {
        if (!this.rigId) {
            this.loadRigConfig();
        }

        const ollamaStatus = await this.checkOllamaStatus();
        const models = await this.detectOllamaModels();

        const status = {
            registered: !!this.rigId,
            rigId: this.rigId,
            rigName: this.rigName,
            models: models,
            capabilities: this.capabilities,
            ollama: ollamaStatus,
            serving: this.running,
        };

        return status;
    }

    /**
     * Check Ollama connectivity
     */
    async checkOllamaStatus() {
        try {
            const response = await fetch(`${this.ollamaHost}/api/tags`);
            return response.ok ? 'connected' : 'error';
        } catch (e) {
            return 'disconnected';
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CONFIG PERSISTENCE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Save rig config to disk
     */
    saveRigConfig() {
        const fs = require('fs');
        const path = require('path');
        const configPath = path.join(process.env.HOME || process.env.USERPROFILE, '.matrix-rig.json');

        const config = {
            rigId: this.rigId,
            rigName: this.rigName,
            models: this.models,
            capabilities: this.capabilities,
            registered: Date.now(),
        };

        fs.writeFileSync(configPath, JSON.stringify(config, null, 2));
    }

    /**
     * Load rig config from disk
     */
    loadRigConfig() {
        const fs = require('fs');
        const path = require('path');
        const configPath = path.join(process.env.HOME || process.env.USERPROFILE, '.matrix-rig.json');

        try {
            if (fs.existsSync(configPath)) {
                const config = JSON.parse(fs.readFileSync(configPath, 'utf8'));
                this.rigId = config.rigId;
                this.rigName = config.rigName;
                this.models = config.models || [];
                this.capabilities = config.capabilities || {};
                return true;
            }
        } catch (e) {
            // Config not found or invalid
        }
        return false;
    }

    /**
     * Clear rig config
     */
    clearRigConfig() {
        const fs = require('fs');
        const path = require('path');
        const configPath = path.join(process.env.HOME || process.env.USERPROFILE, '.matrix-rig.json');

        try {
            fs.unlinkSync(configPath);
        } catch (e) {
            // Already gone
        }

        this.rigId = null;
        this.rigName = null;
        this.models = [];
        this.capabilities = {};
    }

    // ─────────────────────────────────────────────────────────────────────────
    // UTILITIES
    // ─────────────────────────────────────────────────────────────────────────

    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// EXPORTS
// ═══════════════════════════════════════════════════════════════════════════

module.exports = { RigManager };
