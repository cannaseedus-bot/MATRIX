/**
 * KUHUL Glyph Runtime - Client-side Glyph Interpreter
 *
 * Parses K'UHUL glyph sequences and routes to terminal.php
 * Supports glyph autocomplete and CM-1 phase tracking
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 * @version 2.1.0
 */

const GlyphRuntime = {
    studio: null,

    // Glyph definitions
    glyphs: {
        '⟁Sek⟁': {
            name: 'Execute',
            description: 'Execute a command',
            pattern: /^⟁Sek⟁\s+(.+)$/,
            handler: 'execute'
        },
        '⟁Wo⟁': {
            name: 'Assign',
            description: 'Assign value to variable',
            pattern: /^⟁Wo⟁\s+(\w+)\s*=\s*(.+)$/,
            handler: 'assign'
        },
        '⟁K\'an⟁': {
            name: 'Transform',
            description: 'Transform/process data',
            pattern: /^⟁K'an⟁\s+(\w+):(\w+)\s+(.+)$/,
            handler: 'transform'
        },
        '⟁Ajaw⟁': {
            name: 'Lord',
            description: 'Admin/privileged command',
            pattern: /^⟁Ajaw⟁\s+(.+)$/,
            handler: 'admin'
        },
        '⟁Muwan⟁': {
            name: 'Owl',
            description: 'Async/background operation',
            pattern: /^⟁Muwan⟁\s+(.+)$/,
            handler: 'async'
        },
        '⟁K\'uhul⟁': {
            name: 'Divine',
            description: 'Sacred/critical operation',
            pattern: /^⟁K'uhul⟁\s+(.+)$/,
            handler: 'divine'
        }
    },

    // Variables storage
    variables: {},

    // CM-1 control codes
    CM1: {
        NUL: '\u0000',      // Null - inert region
        SOH: '\u0001',      // Start of header
        STX: '\u0002',      // Start of text (body begin)
        ETX: '\u0003',      // End of text (body end)
        EOT: '\u0004',      // End of transmission
        ENQ: '\u0005',      // Enquiry
        ACK: '\u0006',      // Acknowledge
        NAK: '\u0015',      // Negative acknowledge
        SO:  '\u000E',      // Scope push
        SI:  '\u000F',      // Scope pop
        ESC: '\u001B',      // Escape / mode switch
        FS:  '\u001C',      // File separator
        GS:  '\u001D',      // Group separator
        RS:  '\u001E',      // Record separator
        US:  '\u001F',      // Unit separator
    },

    // Initialize runtime
    init(studio) {
        this.studio = studio;
        this.setupAutocomplete();
        this.setupGlyphHighlighting();
        console.log('GlyphRuntime initialized');
    },

    // Parse input for glyphs
    parse(input) {
        // Check for glyph prefix
        for (const [glyph, def] of Object.entries(this.glyphs)) {
            if (input.startsWith(glyph)) {
                const match = input.match(def.pattern);
                if (match) {
                    return {
                        type: 'glyph',
                        glyph: glyph,
                        name: def.name,
                        handler: def.handler,
                        matches: match.slice(1),
                        raw: input
                    };
                }
            }
        }

        // Check for variable interpolation
        const interpolated = this.interpolateVariables(input);

        return {
            type: 'command',
            raw: input,
            interpolated: interpolated
        };
    },

    // Interpolate variables ($varname)
    interpolateVariables(text) {
        return text.replace(/\$(\w+)/g, (match, name) => {
            return this.variables[name] !== undefined ? this.variables[name] : match;
        });
    },

    // Handle glyph execution
    async execute(parsed) {
        switch (parsed.handler) {
            case 'execute':
                // ⟁Sek⟁ command - execute directly
                return this.interpolateVariables(parsed.matches[0]);

            case 'assign':
                // ⟁Wo⟁ var = value - assign variable
                const [varName, value] = parsed.matches;
                this.variables[varName] = this.interpolateVariables(value.replace(/^["']|["']$/g, ''));
                return `kuhul echo "Variable ${varName} set to: ${this.variables[varName]}"`;

            case 'transform':
                // ⟁K'an⟁ transform:type value
                const [transformType, transformOp, transformValue] = parsed.matches;
                const transformed = this.applyTransform(transformType, transformOp, transformValue);
                return `kuhul echo "${transformed}"`;

            case 'admin':
                // ⟁Ajaw⟁ - admin command
                return `kuhul admin ${parsed.matches[0]}`;

            case 'async':
                // ⟁Muwan⟁ - async operation
                return `kuhul async ${parsed.matches[0]}`;

            case 'divine':
                // ⟁K'uhul⟁ - critical operation
                return `kuhul divine ${parsed.matches[0]}`;

            default:
                return parsed.raw;
        }
    },

    // Apply transformation
    applyTransform(type, operation, value) {
        const val = this.interpolateVariables(value.replace(/^["']|["']$/g, ''));

        switch (type) {
            case 'transform':
                switch (operation) {
                    case 'uppercase':
                        return val.toUpperCase();
                    case 'lowercase':
                        return val.toLowerCase();
                    case 'reverse':
                        return val.split('').reverse().join('');
                    case 'length':
                        return String(val.length);
                    case 'words':
                        return String(val.split(/\s+/).length);
                    case 'base64':
                        return btoa(val);
                    case 'unbase64':
                        return atob(val);
                    case 'json':
                        try {
                            return JSON.stringify(JSON.parse(val), null, 2);
                        } catch {
                            return `Invalid JSON: ${val}`;
                        }
                    default:
                        return val;
                }

            case 'hash':
                // Simple hash for demo (not cryptographic)
                let hash = 0;
                for (let i = 0; i < val.length; i++) {
                    hash = ((hash << 5) - hash) + val.charCodeAt(i);
                    hash = hash & hash;
                }
                return hash.toString(16);

            case 'encode':
                switch (operation) {
                    case 'uri':
                        return encodeURIComponent(val);
                    case 'html':
                        return val.replace(/[&<>"']/g, c => ({
                            '&': '&amp;',
                            '<': '&lt;',
                            '>': '&gt;',
                            '"': '&quot;',
                            "'": '&#39;'
                        })[c]);
                    default:
                        return val;
                }

            default:
                return val;
        }
    },

    // Setup autocomplete for terminal input
    setupAutocomplete() {
        const input = document.getElementById('terminal-input');
        if (!input) return;

        // Create autocomplete dropdown
        const dropdown = document.createElement('div');
        dropdown.id = 'glyph-autocomplete';
        dropdown.style.cssText = `
            position: absolute;
            bottom: 100%;
            left: 0;
            right: 0;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 8px 8px 0 0;
            max-height: 200px;
            overflow-y: auto;
            display: none;
            z-index: 100;
        `;
        input.parentElement.style.position = 'relative';
        input.parentElement.appendChild(dropdown);

        // Autocomplete suggestions
        const suggestions = [
            { trigger: '⟁S', completion: '⟁Sek⟁ ', description: 'Execute command' },
            { trigger: '⟁W', completion: '⟁Wo⟁ ', description: 'Assign variable' },
            { trigger: '⟁K', completion: '⟁K\'an⟁ ', description: 'Transform' },
            { trigger: '⟁A', completion: '⟁Ajaw⟁ ', description: 'Admin command' },
            { trigger: '⟁M', completion: '⟁Muwan⟁ ', description: 'Async operation' },
            { trigger: 'bm', completion: 'brain-mesh ', description: 'Brain Mesh commands' },
            { trigger: 'llm', completion: 'llm ', description: 'LLM commands' },
            { trigger: 'help', completion: 'help', description: 'Show help' },
        ];

        input.addEventListener('input', () => {
            const value = input.value;
            const matches = suggestions.filter(s =>
                s.trigger.toLowerCase().startsWith(value.toLowerCase()) && value.length > 0
            );

            if (matches.length > 0 && value.length > 0) {
                dropdown.innerHTML = matches.map(m => `
                    <div class="autocomplete-item" data-completion="${m.completion}"
                         style="padding: 8px 12px; cursor: pointer; display: flex; justify-content: space-between;">
                        <span style="color: var(--accent);">${m.completion}</span>
                        <span style="color: var(--text-muted); font-size: 0.8em;">${m.description}</span>
                    </div>
                `).join('');
                dropdown.style.display = 'block';

                // Click to complete
                dropdown.querySelectorAll('.autocomplete-item').forEach(item => {
                    item.addEventListener('click', () => {
                        input.value = item.dataset.completion;
                        dropdown.style.display = 'none';
                        input.focus();
                    });
                });
            } else {
                dropdown.style.display = 'none';
            }
        });

        // Tab to complete
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Tab' && dropdown.style.display !== 'none') {
                e.preventDefault();
                const first = dropdown.querySelector('.autocomplete-item');
                if (first) {
                    input.value = first.dataset.completion;
                    dropdown.style.display = 'none';
                }
            }
        });

        // Hide on blur
        input.addEventListener('blur', () => {
            setTimeout(() => dropdown.style.display = 'none', 200);
        });
    },

    // Highlight glyphs in terminal output
    setupGlyphHighlighting() {
        // Observe terminal output for new content
        const output = document.getElementById('terminal-output');
        if (!output) return;

        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        this.highlightGlyphs(node);
                    }
                });
            });
        });

        observer.observe(output, { childList: true, subtree: true });
    },

    // Apply glyph highlighting to element
    highlightGlyphs(element) {
        const glyphPattern = /⟁\w+⟁/g;
        const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);

        const textNodes = [];
        while (walker.nextNode()) {
            textNodes.push(walker.currentNode);
        }

        textNodes.forEach(node => {
            if (glyphPattern.test(node.textContent)) {
                const span = document.createElement('span');
                span.innerHTML = node.textContent.replace(glyphPattern, match =>
                    `<span class="glyph-highlight">${match}</span>`
                );
                node.parentNode.replaceChild(span, node);
            }
        });
    },

    // Encode output with CM-1 markers
    encodeCM1(content, phase = 'body') {
        const { SOH, STX, ETX, EOT } = this.CM1;

        switch (phase) {
            case 'header':
                return SOH + content;
            case 'body':
                return STX + content + ETX;
            case 'end':
                return content + EOT;
            case 'full':
                return SOH + STX + content + ETX + EOT;
            default:
                return content;
        }
    },

    // Decode CM-1 markers from content
    decodeCM1(content) {
        const { SOH, STX, ETX, EOT, SO, SI } = this.CM1;

        // Extract phases
        const phases = {
            header: null,
            body: null,
            scopes: []
        };

        // Find header
        const sohIndex = content.indexOf(SOH);
        const stxIndex = content.indexOf(STX);
        if (sohIndex !== -1 && stxIndex !== -1 && sohIndex < stxIndex) {
            phases.header = content.substring(sohIndex + 1, stxIndex);
        }

        // Find body
        const etxIndex = content.indexOf(ETX);
        if (stxIndex !== -1 && etxIndex !== -1) {
            phases.body = content.substring(stxIndex + 1, etxIndex);
        }

        // Find scopes
        let depth = 0;
        let scopeStart = -1;
        for (let i = 0; i < content.length; i++) {
            if (content[i] === SO) {
                if (depth === 0) scopeStart = i;
                depth++;
            } else if (content[i] === SI) {
                depth--;
                if (depth === 0 && scopeStart !== -1) {
                    phases.scopes.push(content.substring(scopeStart + 1, i));
                    scopeStart = -1;
                }
            }
        }

        // Clean content (remove all CM-1 markers)
        const clean = content.replace(/[\x00-\x1F]/g, '');

        return {
            phases,
            clean,
            hasMarkers: content !== clean
        };
    },

    // SCXQ2 display encoding (visual obfuscation)
    encodeSCXQ2(text) {
        // Simple visual encoding for display
        const chars = text.split('');
        return chars.map((c, i) => {
            if (i % 3 === 0) return '█';
            if (i % 3 === 1) return '▓';
            return '░';
        }).join('');
    },

    // Get variable
    getVariable(name) {
        return this.variables[name];
    },

    // Set variable
    setVariable(name, value) {
        this.variables[name] = value;
    },

    // List all variables
    listVariables() {
        return { ...this.variables };
    },

    // Clear all variables
    clearVariables() {
        this.variables = {};
    }
};

// Export for use in Studio
if (typeof window !== 'undefined') {
    window.GlyphRuntime = GlyphRuntime;
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = GlyphRuntime;
}
