/**
 * DNS Tunnel - DNS-based communication for firewall traversal
 *
 * Uses DNS TXT records to communicate with Brain-Mesh API
 * without requiring HTTP port forwarding.
 *
 * Protocol: TTP (Time-To-Propagate)
 * - Commands encoded as DNS subdomain queries
 * - Responses returned in TXT records
 * - TTL controls cache/polling behavior
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 * @version 2.1.0
 */

const dgram = require('dgram');
const dns = require('dns');
const crypto = require('crypto');

// ═══════════════════════════════════════════════════════════════════════════
// DNS TUNNEL CLASS
// ═══════════════════════════════════════════════════════════════════════════

class DNSTunnel {
    constructor(config) {
        this.config = config;
        this.dnsServer = config.server.dnsServer || '8.8.8.8';
        this.dnsDomain = config.server.dnsDomain || 'matrix.local';
        this.ttl = config.tunnel.ttl || 300;
        this.pollInterval = config.tunnel.pollInterval || 5000;
        this.ttpWindow = config.tunnel.ttpWindow || 10000;

        this.active = false;
        this.lastQuery = null;
        this.pending = [];
        this.responses = new Map();
        this.pollTimer = null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ENCODING
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Encode command for DNS query
     * Format: <session>.<encoded-cmd>.<domain>
     */
    encodeCommand(cmd) {
        const sessionId = this.generateSessionId();
        const encoded = this.base32Encode(cmd);

        // Split into DNS-safe labels (max 63 chars each)
        const labels = [];
        for (let i = 0; i < encoded.length; i += 60) {
            labels.push(encoded.slice(i, i + 60));
        }

        return {
            sessionId,
            query: `${sessionId}.${labels.join('.')}.${this.dnsDomain}`,
            labels,
        };
    }

    /**
     * Decode TXT record response
     */
    decodeResponse(txtRecords) {
        if (!txtRecords || txtRecords.length === 0) {
            return { success: false, error: 'No response' };
        }

        try {
            // TXT records may be split
            const combined = txtRecords.flat().join('');
            const decoded = this.base32Decode(combined);
            return JSON.parse(decoded);
        } catch (e) {
            return { success: false, error: 'Decode error', raw: txtRecords };
        }
    }

    /**
     * Generate session ID
     */
    generateSessionId() {
        return crypto.randomBytes(4).toString('hex');
    }

    /**
     * Base32 encode (DNS-safe)
     */
    base32Encode(str) {
        const alphabet = 'abcdefghijklmnopqrstuvwxyz234567';
        const bytes = Buffer.from(str, 'utf8');
        let result = '';
        let bits = 0;
        let value = 0;

        for (const byte of bytes) {
            value = (value << 8) | byte;
            bits += 8;

            while (bits >= 5) {
                result += alphabet[(value >>> (bits - 5)) & 31];
                bits -= 5;
            }
        }

        if (bits > 0) {
            result += alphabet[(value << (5 - bits)) & 31];
        }

        return result;
    }

    /**
     * Base32 decode
     */
    base32Decode(str) {
        const alphabet = 'abcdefghijklmnopqrstuvwxyz234567';
        let bits = 0;
        let value = 0;
        const bytes = [];

        for (const char of str.toLowerCase()) {
            const index = alphabet.indexOf(char);
            if (index === -1) continue;

            value = (value << 5) | index;
            bits += 5;

            if (bits >= 8) {
                bytes.push((value >>> (bits - 8)) & 255);
                bits -= 8;
            }
        }

        return Buffer.from(bytes).toString('utf8');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // QUERY
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Send DNS query and await response
     */
    async query(cmd) {
        const encoded = this.encodeCommand(cmd);
        this.lastQuery = new Date().toISOString();

        return new Promise((resolve, reject) => {
            // Use node's dns module with custom resolver
            const resolver = new dns.Resolver();
            resolver.setServers([this.dnsServer]);

            // Try TXT record lookup
            resolver.resolveTxt(encoded.query, (err, records) => {
                if (err) {
                    // Fallback: Try direct UDP query
                    this.directQuery(encoded.query)
                        .then(resolve)
                        .catch(() => {
                            // Final fallback: Simulate response for local testing
                            resolve(this.simulateResponse(cmd));
                        });
                } else {
                    resolve(this.decodeResponse(records));
                }
            });

            // Timeout after TTP window
            setTimeout(() => {
                resolve({ success: false, error: 'Timeout', ttpWindow: this.ttpWindow });
            }, this.ttpWindow);
        });
    }

    /**
     * Direct UDP DNS query (for custom DNS servers)
     */
    async directQuery(hostname) {
        return new Promise((resolve, reject) => {
            const socket = dgram.createSocket('udp4');
            const query = this.buildDNSQuery(hostname, 'TXT');

            socket.on('message', (msg) => {
                const response = this.parseDNSResponse(msg);
                socket.close();
                resolve(response);
            });

            socket.on('error', (err) => {
                socket.close();
                reject(err);
            });

            socket.send(query, 53, this.dnsServer, (err) => {
                if (err) {
                    socket.close();
                    reject(err);
                }
            });

            // Timeout
            setTimeout(() => {
                socket.close();
                reject(new Error('DNS query timeout'));
            }, 5000);
        });
    }

    /**
     * Build raw DNS query packet
     */
    buildDNSQuery(hostname, type = 'TXT') {
        const id = crypto.randomBytes(2);
        const flags = Buffer.from([0x01, 0x00]); // Standard query
        const qdcount = Buffer.from([0x00, 0x01]); // 1 question
        const ancount = Buffer.from([0x00, 0x00]); // 0 answers
        const nscount = Buffer.from([0x00, 0x00]); // 0 authority
        const arcount = Buffer.from([0x00, 0x00]); // 0 additional

        // Encode hostname
        const labels = hostname.split('.');
        const qname = Buffer.concat([
            ...labels.map(label => {
                const len = Buffer.from([label.length]);
                const data = Buffer.from(label, 'ascii');
                return Buffer.concat([len, data]);
            }),
            Buffer.from([0x00]) // Null terminator
        ]);

        const qtype = Buffer.from([0x00, type === 'TXT' ? 0x10 : 0x01]); // TXT = 16, A = 1
        const qclass = Buffer.from([0x00, 0x01]); // IN class

        return Buffer.concat([
            id, flags, qdcount, ancount, nscount, arcount,
            qname, qtype, qclass
        ]);
    }

    /**
     * Parse DNS response packet
     */
    parseDNSResponse(msg) {
        try {
            // Skip header (12 bytes) and question section
            let offset = 12;

            // Skip question section
            while (msg[offset] !== 0) {
                offset += msg[offset] + 1;
            }
            offset += 5; // null + qtype + qclass

            // Parse answer
            const answers = [];
            const ancount = msg.readUInt16BE(6);

            for (let i = 0; i < ancount; i++) {
                // Skip name (compressed or full)
                if ((msg[offset] & 0xc0) === 0xc0) {
                    offset += 2; // Pointer
                } else {
                    while (msg[offset] !== 0) {
                        offset += msg[offset] + 1;
                    }
                    offset += 1;
                }

                const type = msg.readUInt16BE(offset);
                offset += 2;
                offset += 2; // class
                offset += 4; // ttl
                const rdlength = msg.readUInt16BE(offset);
                offset += 2;

                if (type === 16) { // TXT
                    const txtlen = msg[offset];
                    const txt = msg.slice(offset + 1, offset + 1 + txtlen).toString('utf8');
                    answers.push(txt);
                }

                offset += rdlength;
            }

            return this.decodeResponse(answers);
        } catch (e) {
            return { success: false, error: 'Parse error', message: e.message };
        }
    }

    /**
     * Simulate response for local testing
     */
    simulateResponse(cmd) {
        // Parse command type
        if (cmd === 'ping') {
            return { success: true, pong: true, timestamp: Date.now() };
        }

        if (cmd.startsWith('terminal:')) {
            const termCmd = cmd.slice(9);
            return {
                success: true,
                output: `[DNS-SIMULATED] Command: ${termCmd}\nNote: Connect to a real DNS server for actual execution.`,
                data: { simulated: true, command: termCmd },
                tunnel: 'dns',
            };
        }

        return { success: true, data: { raw: cmd }, tunnel: 'dns-simulated' };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // TUNNEL MANAGEMENT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Start persistent tunnel with polling
     */
    async start(ttl = null) {
        if (ttl) this.ttl = ttl;
        this.active = true;

        // Initial connection test
        const ping = await this.query('ping');
        if (!ping.success && !ping.pong) {
            console.warn('Warning: DNS tunnel may not be fully functional');
        }

        // Start polling for pending messages
        this.pollTimer = setInterval(async () => {
            await this.poll();
        }, this.pollInterval);

        return { active: true, ttl: this.ttl };
    }

    /**
     * Stop tunnel
     */
    async stop() {
        this.active = false;
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
        return { active: false };
    }

    /**
     * Poll for pending messages
     */
    async poll() {
        if (!this.active) return;

        try {
            const result = await this.query('poll');
            if (result.messages) {
                for (const msg of result.messages) {
                    this.pending.push(msg);
                }
            }
        } catch (e) {
            // Silently fail polling
        }
    }

    /**
     * Send message through tunnel
     */
    async send(message) {
        return this.query(`send:${message}`);
    }

    /**
     * Get tunnel status
     */
    async status() {
        return {
            active: this.active,
            server: this.dnsServer,
            domain: this.dnsDomain,
            ttl: this.ttl,
            lastQuery: this.lastQuery,
            pending: this.pending.length,
        };
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// TTP PROTOCOL HELPERS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * TTP - Time-To-Propagate Protocol
 *
 * Handles eventual consistency in DNS-based communication:
 * 1. Commands are sent as DNS queries
 * 2. Server processes and stores response in TXT record
 * 3. TTL determines how long response is cached
 * 4. TTP window allows for propagation delay
 *
 * Message format:
 * Query:  <session>.<encoded-cmd>.<domain>
 * Response: TXT record with base32-encoded JSON
 */

const TTP = {
    /**
     * Calculate optimal TTL based on command type
     */
    calculateTTL(cmd) {
        // Shorter TTL for real-time commands
        if (cmd.includes('health') || cmd.includes('status')) {
            return 30; // 30 seconds
        }
        // Longer TTL for static data
        if (cmd.includes('leaderboard') || cmd.includes('providers')) {
            return 300; // 5 minutes
        }
        // Default
        return 60; // 1 minute
    },

    /**
     * Generate TTP envelope
     */
    envelope(sessionId, payload) {
        return {
            ttp: '1.0',
            session: sessionId,
            timestamp: Date.now(),
            payload,
        };
    },

    /**
     * Validate TTP envelope
     */
    validate(envelope) {
        if (!envelope.ttp || !envelope.session || !envelope.timestamp) {
            return { valid: false, error: 'Missing TTP fields' };
        }
        // Check if within TTP window (not stale)
        const age = Date.now() - envelope.timestamp;
        if (age > 60000) { // 60 seconds
            return { valid: false, error: 'TTP envelope expired', age };
        }
        return { valid: true };
    }
};

// ═══════════════════════════════════════════════════════════════════════════
// EXPORTS
// ═══════════════════════════════════════════════════════════════════════════

module.exports = {
    DNSTunnel,
    TTP,
};
