<?php
/**
 * KUHUL GLYPH Studio - Terminal Projection PWA
 *
 * Single-file PHP that generates the complete KUHUL GLYPH Studio
 * Merges terminal projection with code editor and micronaut monitoring
 *
 * @law ASX = XCFE = XJSON = KUHUL = AST = CM-1
 * @version 2.1.0
 */

// Fetch initial brain health for micronaut panel
$brainHealth = 1.0;
$uptime = 0;
$traffic = 0;
$glow = 0;

// Try to get real stats from brain-mesh
$configFile = __DIR__ . '/../../api/brain-mesh/config.php';
if (file_exists($configFile)) {
    try {
        require_once __DIR__ . '/../../api/brain-mesh/db.php';
        require_once __DIR__ . '/../../api/brain-mesh/BrainMesh.php';
        $brainMesh = new BrainMesh($pdo ?? null, []);
        $stats = $brainMesh->getStats();
        $brainHealth = min(1, max(0, ($stats['success_rate'] ?? 100) / 100));
        $uptime = $stats['uptime_seconds'] ?? 0;
        $traffic = min(1, ($stats['requests_per_minute'] ?? 0) / 100);
    } catch (Exception $e) {
        // Use defaults
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="emerald">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0a0f0a">
    <meta name="description" content="KUHUL GLYPH Studio - Terminal Projection PWA">
    <title>KUHUL GLYPH Studio</title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⟁</text></svg>">

    <style>
/* ═══════════════════════════════════════════════════════════════════════════
   ATOMIC+GLYPH.css - CSS Framework for KUHUL GLYPH Studio
   Variables as state, atomic classes with glyph operators
   ═══════════════════════════════════════════════════════════════════════════ */

:root {
    /* ═══ Physics Variables ═══ */
    --entropy: 0;
    --compression: 1;
    --phase: 0;
    --drift: 0;

    /* ═══ Micronaut Variables (updated by JS) ═══ */
    --mx2lm-health: <?= $brainHealth ?>;
    --mx2lm-uptime: <?= $uptime ?>;
    --mx2lm-traffic: <?= $traffic ?>;
    --mx2lm-glow: <?= $glow ?>;
    --mx2lm-alert: 0;
    --mx2lm-drift: 0;

    /* ═══ Layout ═══ */
    --sidebar-width: 280px;
    --terminal-height: 50%;
    --panel-gap: 4px;

    /* ═══ Typography ═══ */
    --font-mono: ui-monospace, "SF Mono", "Cascadia Code", "Fira Code", monospace;
    --font-sans: system-ui, -apple-system, sans-serif;

    /* ═══ Timing ═══ */
    --transition-fast: 0.15s;
    --transition-medium: 0.3s;
}

/* ═══ Theme: Emerald Ghost (Default) ═══ */
[data-theme="emerald"] {
    --bg-primary: #0a0f0a;
    --bg-secondary: #0d140d;
    --bg-tertiary: #111a11;
    --bg-panel: #0b100b;
    --text-primary: #c8e6c8;
    --text-secondary: #7cb37c;
    --text-muted: #4a6b4a;
    --accent: #22c55e;
    --accent-glow: rgba(34, 197, 94, 0.4);
    --border: #1a2f1a;
    --scrollbar-track: #0d140d;
    --scrollbar-thumb: #2a4a2a;
}

/* ═══ Theme: Matrix ═══ */
[data-theme="matrix"] {
    --bg-primary: #000000;
    --bg-secondary: #001100;
    --bg-tertiary: #002200;
    --bg-panel: #000800;
    --text-primary: #00ff00;
    --text-secondary: #00cc00;
    --text-muted: #006600;
    --accent: #00ff00;
    --accent-glow: rgba(0, 255, 0, 0.5);
    --border: #003300;
    --scrollbar-track: #001100;
    --scrollbar-thumb: #004400;
}

/* ═══ Theme: Cyber ═══ */
[data-theme="cyber"] {
    --bg-primary: #0a0a1a;
    --bg-secondary: #0d0d24;
    --bg-tertiary: #12123a;
    --bg-panel: #0b0b20;
    --text-primary: #e0e0ff;
    --text-secondary: #a0a0ff;
    --text-muted: #6060a0;
    --accent: #8b5cf6;
    --accent-glow: rgba(139, 92, 246, 0.4);
    --border: #2a2a5a;
    --scrollbar-track: #0d0d24;
    --scrollbar-thumb: #3a3a6a;
}

/* ═══ Theme: Night ═══ */
[data-theme="night"] {
    --bg-primary: #0f172a;
    --bg-secondary: #1e293b;
    --bg-tertiary: #334155;
    --bg-panel: #1a2332;
    --text-primary: #e2e8f0;
    --text-secondary: #94a3b8;
    --text-muted: #64748b;
    --accent: #3b82f6;
    --accent-glow: rgba(59, 130, 246, 0.4);
    --border: #334155;
    --scrollbar-track: #1e293b;
    --scrollbar-thumb: #475569;
}

/* ═══ Base Reset ═══ */
*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

html, body {
    height: 100%;
    overflow: hidden;
}

body {
    font-family: var(--font-mono);
    font-size: 14px;
    line-height: 1.5;
    background: var(--bg-primary);
    color: var(--text-primary);
}

/* ═══ Scrollbar ═══ */
::-webkit-scrollbar {
    width: 8px;
    height: 8px;
}
::-webkit-scrollbar-track {
    background: var(--scrollbar-track);
}
::-webkit-scrollbar-thumb {
    background: var(--scrollbar-thumb);
    border-radius: 4px;
}
::-webkit-scrollbar-thumb:hover {
    background: var(--accent);
}

/* ═══════════════════════════════════════════════════════════════════════════
   LAYOUT - Split Pane Architecture
   ═══════════════════════════════════════════════════════════════════════════ */

.studio {
    display: grid;
    grid-template-columns: var(--sidebar-width) 1fr 1fr;
    grid-template-rows: auto 1fr auto;
    height: 100vh;
    gap: var(--panel-gap);
    background: var(--bg-primary);
}

.studio.sidebar-collapsed {
    grid-template-columns: 40px 1fr 1fr;
}

/* Header */
.studio-header {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 16px;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border);
}

.studio-logo {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: var(--accent);
}

.studio-logo .glyph {
    font-size: 1.5em;
}

.studio-controls {
    display: flex;
    align-items: center;
    gap: 12px;
}

/* Sidebar */
.sidebar {
    grid-row: 2;
    background: var(--bg-secondary);
    border-right: 1px solid var(--border);
    overflow-y: auto;
    transition: width var(--transition-medium);
}

.sidebar-header {
    padding: 12px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.sidebar-title {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-muted);
}

.glyph-list {
    padding: 8px;
}

.glyph-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    transition: background var(--transition-fast);
}

.glyph-item:hover {
    background: var(--bg-tertiary);
}

.glyph-item .symbol {
    font-size: 1.2em;
    width: 28px;
    text-align: center;
}

.glyph-item .name {
    font-size: 0.85rem;
    color: var(--text-secondary);
}

/* Main Panels */
.panel-terminal {
    grid-row: 2;
    display: flex;
    flex-direction: column;
    background: var(--bg-panel);
    border-radius: 8px;
    overflow: hidden;
}

.panel-editor {
    grid-row: 2;
    display: flex;
    flex-direction: column;
    background: var(--bg-panel);
    border-radius: 8px;
    overflow: hidden;
}

.panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    background: var(--bg-secondary);
    border-bottom: 1px solid var(--border);
}

.panel-title {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--text-secondary);
}

.panel-actions {
    display: flex;
    gap: 8px;
}

.panel-content {
    flex: 1;
    overflow: auto;
    position: relative;
}

/* Footer / Status Bar */
.studio-footer {
    grid-column: 1 / -1;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 4px 16px;
    background: var(--bg-secondary);
    border-top: 1px solid var(--border);
    font-size: 0.75rem;
    color: var(--text-muted);
}

/* ═══════════════════════════════════════════════════════════════════════════
   TERMINAL
   ═══════════════════════════════════════════════════════════════════════════ */

.terminal {
    height: 100%;
    display: flex;
    flex-direction: column;
    font-family: var(--font-mono);
}

.terminal-output {
    flex: 1;
    padding: 12px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-word;
}

.terminal-output .line {
    margin-bottom: 2px;
}

.terminal-output .prompt {
    color: var(--accent);
}

.terminal-output .command {
    color: var(--text-primary);
}

.terminal-output .output {
    color: var(--text-secondary);
}

.terminal-output .error {
    color: #ef4444;
}

.terminal-output .success {
    color: #22c55e;
}

.terminal-output .info {
    color: #3b82f6;
}

.terminal-output .glyph-highlight {
    color: var(--accent);
    text-shadow: 0 0 8px var(--accent-glow);
}

.terminal-input-line {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    background: var(--bg-tertiary);
    border-top: 1px solid var(--border);
}

.terminal-prompt {
    color: var(--accent);
    margin-right: 8px;
    user-select: none;
}

.terminal-input {
    flex: 1;
    background: transparent;
    border: none;
    outline: none;
    color: var(--text-primary);
    font-family: inherit;
    font-size: inherit;
    caret-color: var(--accent);
}

/* ═══════════════════════════════════════════════════════════════════════════
   EDITOR
   ═══════════════════════════════════════════════════════════════════════════ */

.editor-tabs {
    display: flex;
    gap: 2px;
    padding: 4px 8px;
    background: var(--bg-tertiary);
    overflow-x: auto;
}

.editor-tab {
    padding: 6px 12px;
    border-radius: 4px 4px 0 0;
    background: transparent;
    border: none;
    color: var(--text-muted);
    font-family: inherit;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.editor-tab:hover {
    background: var(--bg-secondary);
    color: var(--text-secondary);
}

.editor-tab.active {
    background: var(--bg-panel);
    color: var(--text-primary);
}

.editor-content {
    flex: 1;
    position: relative;
}

.editor-textarea {
    width: 100%;
    height: 100%;
    padding: 12px;
    background: transparent;
    border: none;
    outline: none;
    color: var(--text-primary);
    font-family: var(--font-mono);
    font-size: 14px;
    line-height: 1.6;
    resize: none;
    tab-size: 2;
}

/* ═══════════════════════════════════════════════════════════════════════════
   MICRONAUT PANEL (from cli/ui/micronaut.css)
   ═══════════════════════════════════════════════════════════════════════════ */

.mx2lm-panel {
    padding: 12px 16px;
    border-radius: 8px;
    background: var(--bg-tertiary);
    box-shadow: 0 0 calc(20px * var(--mx2lm-glow)) var(--accent-glow);
    transition: box-shadow var(--transition-medium), opacity var(--transition-medium);
    opacity: calc(0.6 + 0.4 * var(--mx2lm-health));
}

.mx2lm-indicator {
    height: 4px;
    border-radius: 4px;
    background: linear-gradient(90deg,
        var(--accent) calc(100% * var(--mx2lm-health)),
        var(--bg-secondary) 0);
    transition: background var(--transition-medium);
    margin-bottom: 8px;
}

.mx2lm-panel[data-alert="true"] {
    box-shadow: 0 0 calc(30px * var(--mx2lm-alert)) rgba(255, 100, 100, 0.5);
}

.mx2lm-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    color: var(--text-secondary);
}

.mx2lm-status::before {
    content: "";
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: color-mix(in oklab, var(--accent) calc(100% * var(--mx2lm-health)), #ef4444);
}

.mx2lm-traffic-pulse {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--accent);
    animation: pulse calc(2s - 1.5s * var(--mx2lm-traffic)) ease infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 0.4; transform: scale(0.9); }
    50% { opacity: 1; transform: scale(1.1); }
}

.mx2lm-uptime {
    font-size: 0.7rem;
    color: var(--text-muted);
}

.mx2lm-drift-bar {
    height: 2px;
    background: linear-gradient(90deg,
        var(--accent) calc(100% * (1 - var(--mx2lm-drift))),
        #f59e0b 0);
    border-radius: 1px;
    margin-top: 4px;
}

/* ═══════════════════════════════════════════════════════════════════════════
   BUTTONS & CONTROLS
   ═══════════════════════════════════════════════════════════════════════════ */

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 6px;
    border: 1px solid var(--border);
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    font-family: inherit;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.btn:hover {
    background: var(--bg-secondary);
    color: var(--text-primary);
    border-color: var(--accent);
}

.btn-icon {
    padding: 6px;
    min-width: 32px;
}

.btn-accent {
    background: var(--accent);
    color: var(--bg-primary);
    border-color: var(--accent);
}

.btn-accent:hover {
    filter: brightness(1.1);
}

/* Theme Selector */
.theme-select {
    padding: 4px 8px;
    border-radius: 4px;
    border: 1px solid var(--border);
    background: var(--bg-tertiary);
    color: var(--text-secondary);
    font-family: inherit;
    font-size: 0.8rem;
    cursor: pointer;
}

/* ═══════════════════════════════════════════════════════════════════════════
   COMMAND PALETTE
   ═══════════════════════════════════════════════════════════════════════════ */

.command-palette {
    position: fixed;
    top: 20%;
    left: 50%;
    transform: translateX(-50%);
    width: 500px;
    max-width: 90vw;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    z-index: 1000;
    display: none;
}

.command-palette.open {
    display: block;
}

.command-palette-input {
    width: 100%;
    padding: 16px;
    border: none;
    border-bottom: 1px solid var(--border);
    background: transparent;
    color: var(--text-primary);
    font-family: var(--font-mono);
    font-size: 1rem;
    outline: none;
}

.command-palette-results {
    max-height: 300px;
    overflow-y: auto;
}

.command-palette-item {
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    transition: background var(--transition-fast);
}

.command-palette-item:hover,
.command-palette-item.selected {
    background: var(--bg-tertiary);
}

.command-palette-item .icon {
    width: 24px;
    text-align: center;
    color: var(--accent);
}

.command-palette-item .label {
    flex: 1;
}

.command-palette-item .shortcut {
    font-size: 0.75rem;
    color: var(--text-muted);
    padding: 2px 6px;
    background: var(--bg-primary);
    border-radius: 4px;
}

.command-palette-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
    display: none;
}

.command-palette-overlay.open {
    display: block;
}

/* ═══════════════════════════════════════════════════════════════════════════
   RESPONSIVE
   ═══════════════════════════════════════════════════════════════════════════ */

@media (max-width: 1024px) {
    .studio {
        grid-template-columns: 1fr;
        grid-template-rows: auto 1fr 1fr auto;
    }

    .sidebar {
        display: none;
    }

    .panel-terminal,
    .panel-editor {
        grid-column: 1;
    }
}
    </style>
</head>
<body>
    <div class="studio">
        <!-- Header -->
        <header class="studio-header">
            <div class="studio-logo">
                <span class="glyph">⟁</span>
                <span>KUHUL GLYPH Studio</span>
            </div>
            <div class="studio-controls">
                <div class="mx2lm-panel" id="micronaut-panel">
                    <div class="mx2lm-indicator"></div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span class="mx2lm-status">Brain Mesh</span>
                        <span class="mx2lm-traffic-pulse"></span>
                        <span class="mx2lm-uptime" id="uptime-display">0s</span>
                    </div>
                </div>
                <select class="theme-select" id="theme-select">
                    <option value="emerald">Emerald Ghost</option>
                    <option value="matrix">Matrix</option>
                    <option value="cyber">Cyber</option>
                    <option value="night">Night</option>
                </select>
                <button class="btn btn-icon" onclick="toggleCommandPalette()" title="Command Palette (Ctrl+Shift+P)">
                    ⌘
                </button>
            </div>
        </header>

        <!-- Sidebar: Glyph Codex -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <span class="sidebar-title">Glyph Codex</span>
                <button class="btn btn-icon" onclick="toggleSidebar()">◀</button>
            </div>
            <div class="glyph-list" id="glyph-list">
                <!-- Populated by JS -->
            </div>
        </aside>

        <!-- Terminal Panel -->
        <section class="panel-terminal">
            <div class="panel-header">
                <span class="panel-title">Terminal</span>
                <div class="panel-actions">
                    <button class="btn btn-icon" onclick="clearTerminal()" title="Clear">⌫</button>
                </div>
            </div>
            <div class="panel-content">
                <div class="terminal">
                    <div class="terminal-output" id="terminal-output">
                        <div class="line"><span class="info">KUHUL GLYPH Studio v2.1.0</span></div>
                        <div class="line"><span class="info">Type 'help' for available commands</span></div>
                        <div class="line"><span class="info">Use ⟁Sek⟁ prefix for K'UHUL glyph execution</span></div>
                        <div class="line"></div>
                    </div>
                    <div class="terminal-input-line">
                        <span class="terminal-prompt">⟁ &gt;</span>
                        <input type="text" class="terminal-input" id="terminal-input"
                               placeholder="Enter command..."
                               autocomplete="off"
                               spellcheck="false">
                    </div>
                </div>
            </div>
        </section>

        <!-- Editor Panel -->
        <section class="panel-editor">
            <div class="panel-header">
                <span class="panel-title">Editor</span>
                <div class="panel-actions">
                    <button class="btn" onclick="newFile()">+ New</button>
                    <button class="btn btn-accent" onclick="runCode()">▶ Run</button>
                </div>
            </div>
            <div class="editor-tabs" id="editor-tabs">
                <button class="editor-tab active" data-file="scratch.khl">scratch.khl</button>
            </div>
            <div class="panel-content editor-content">
                <textarea class="editor-textarea" id="editor-textarea"
                          placeholder="// Write K'UHUL code here...
// Example:
⟁Wo⟁ greeting = &quot;Hello from KUHUL&quot;
⟁Sek⟁ echo $greeting
⟁K'an⟁ transform:uppercase $greeting"></textarea>
            </div>
        </section>

        <!-- Footer -->
        <footer class="studio-footer">
            <div>
                <span id="cm1-phase">Phase: IDLE</span>
            </div>
            <div>
                <span id="latency-display">Latency: --ms</span>
                <span style="margin-left: 16px;">Session: <span id="session-id">--</span></span>
            </div>
        </footer>
    </div>

    <!-- Command Palette -->
    <div class="command-palette-overlay" id="palette-overlay" onclick="closeCommandPalette()"></div>
    <div class="command-palette" id="command-palette">
        <input type="text" class="command-palette-input" id="palette-input"
               placeholder="Type a command..." autocomplete="off">
        <div class="command-palette-results" id="palette-results">
            <!-- Populated by JS -->
        </div>
    </div>

    <!-- Load Glyph Runtime -->
    <script src="glyph-runtime.js"></script>

    <script>
// ═══════════════════════════════════════════════════════════════════════════
// KUHUL GLYPH Studio - Main Application
// ═══════════════════════════════════════════════════════════════════════════

const Studio = {
    sessionId: null,
    history: [],
    historyIndex: -1,
    startTime: Date.now(),

    // Initialize
    init() {
        this.sessionId = this.generateSessionId();
        document.getElementById('session-id').textContent = this.sessionId.slice(0, 8);

        this.setupTerminal();
        this.setupEditor();
        this.setupTheme();
        this.setupCommandPalette();
        this.setupGlyphCodex();
        this.startUptimeCounter();

        // Initialize glyph runtime
        if (typeof GlyphRuntime !== 'undefined') {
            GlyphRuntime.init(this);
        }
    },

    generateSessionId() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
            const r = Math.random() * 16 | 0;
            return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
        });
    },

    // ═══ Terminal ═══
    setupTerminal() {
        const input = document.getElementById('terminal-input');
        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.executeCommand(input.value);
                input.value = '';
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this.navigateHistory(-1);
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                this.navigateHistory(1);
            }
        });
        input.focus();
    },

    navigateHistory(direction) {
        const input = document.getElementById('terminal-input');
        this.historyIndex += direction;
        this.historyIndex = Math.max(-1, Math.min(this.historyIndex, this.history.length - 1));

        if (this.historyIndex >= 0) {
            input.value = this.history[this.history.length - 1 - this.historyIndex];
        } else {
            input.value = '';
        }
    },

    async executeCommand(cmd) {
        if (!cmd.trim()) return;

        // Add to history
        this.history.push(cmd);
        this.historyIndex = -1;

        // Display command
        this.appendOutput(`<span class="prompt">⟁ &gt;</span> <span class="command">${this.escapeHtml(cmd)}</span>`);

        // Update CM-1 phase
        this.setCM1Phase('STX');

        const startTime = performance.now();

        try {
            // Check for local commands first
            if (cmd === 'clear' || cmd === 'cls') {
                clearTerminal();
                return;
            }

            if (cmd === 'help') {
                this.showHelp();
                return;
            }

            // Route to backend
            const response = await fetch('/api/brain-mesh/terminal.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Session-ID': this.sessionId
                },
                body: JSON.stringify({
                    cmd: cmd,
                    session_id: this.sessionId
                })
            });

            const data = await response.json();
            const latency = Math.round(performance.now() - startTime);

            // Update latency display
            document.getElementById('latency-display').textContent = `Latency: ${latency}ms`;

            // Update micronaut panel
            this.updateMicronaut(data);

            // Display result
            if (data.success) {
                if (data.output) {
                    this.appendOutput(`<span class="output">${this.escapeHtml(data.output)}</span>`);
                }
                if (data.data) {
                    this.appendOutput(`<span class="output">${JSON.stringify(data.data, null, 2)}</span>`);
                }
            } else {
                this.appendOutput(`<span class="error">Error: ${this.escapeHtml(data.error || 'Unknown error')}</span>`);
                if (data.suggestion) {
                    this.appendOutput(`<span class="info">Did you mean: ${this.escapeHtml(data.suggestion)}?</span>`);
                }
            }

        } catch (error) {
            this.appendOutput(`<span class="error">Network error: ${error.message}</span>`);
        }

        // Update CM-1 phase
        this.setCM1Phase('EOT');
        setTimeout(() => this.setCM1Phase('IDLE'), 500);
    },

    appendOutput(html) {
        const output = document.getElementById('terminal-output');
        const line = document.createElement('div');
        line.className = 'line';
        line.innerHTML = html;
        output.appendChild(line);
        output.scrollTop = output.scrollHeight;
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    showHelp() {
        const help = `
<span class="info">═══ KUHUL GLYPH Studio Commands ═══</span>

<span class="glyph-highlight">Glyph Prefixes:</span>
  ⟁Sek⟁   Execute command
  ⟁Wo⟁    Variable assignment
  ⟁K'an⟁  Transform/process

<span class="glyph-highlight">Namespaces:</span>
  brain-mesh   Brain Mesh orchestrator
  llm          LLM provider commands
  ollama       Ollama local models
  file         File operations
  help         Show help

<span class="glyph-highlight">Examples:</span>
  brain-mesh leaderboard
  brain-mesh health
  llm providers
  llm chat "Hello world"

<span class="glyph-highlight">Shortcuts:</span>
  Ctrl+Shift+P   Command palette
  Ctrl+Enter     Run editor code
  Up/Down        Navigate history
  clear          Clear terminal
`;
        this.appendOutput(help);
    },

    setCM1Phase(phase) {
        document.getElementById('cm1-phase').textContent = `Phase: ${phase}`;
    },

    updateMicronaut(data) {
        // Update CSS variables based on response
        const root = document.documentElement;

        if (data.latency_ms) {
            const drift = Math.min(1, data.latency_ms / 2000);
            root.style.setProperty('--mx2lm-drift', drift);
        }

        // Increment traffic
        const currentTraffic = parseFloat(getComputedStyle(root).getPropertyValue('--mx2lm-traffic')) || 0;
        root.style.setProperty('--mx2lm-traffic', Math.min(1, currentTraffic + 0.1));

        // Glow on activity
        root.style.setProperty('--mx2lm-glow', '0.8');
        setTimeout(() => root.style.setProperty('--mx2lm-glow', '0.2'), 1000);
    },

    // ═══ Editor ═══
    setupEditor() {
        const textarea = document.getElementById('editor-textarea');
        textarea.addEventListener('keydown', (e) => {
            // Ctrl+Enter to run
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                runCode();
            }

            // Tab handling
            if (e.key === 'Tab') {
                e.preventDefault();
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                textarea.value = textarea.value.substring(0, start) + '  ' + textarea.value.substring(end);
                textarea.selectionStart = textarea.selectionEnd = start + 2;
            }
        });
    },

    // ═══ Theme ═══
    setupTheme() {
        const select = document.getElementById('theme-select');
        const saved = localStorage.getItem('kuhul-theme');
        if (saved) {
            select.value = saved;
            document.documentElement.setAttribute('data-theme', saved);
        }

        select.addEventListener('change', (e) => {
            const theme = e.target.value;
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem('kuhul-theme', theme);
        });
    },

    // ═══ Command Palette ═══
    setupCommandPalette() {
        const commands = [
            { icon: '▶', label: 'Run Code', shortcut: 'Ctrl+Enter', action: () => runCode() },
            { icon: '⌫', label: 'Clear Terminal', shortcut: '', action: () => clearTerminal() },
            { icon: '📋', label: 'Brain Mesh Leaderboard', shortcut: '', action: () => this.executeCommand('brain-mesh leaderboard') },
            { icon: '💚', label: 'Brain Mesh Health', shortcut: '', action: () => this.executeCommand('brain-mesh health') },
            { icon: '🤖', label: 'List LLM Providers', shortcut: '', action: () => this.executeCommand('llm providers') },
            { icon: '📊', label: 'Show Stats', shortcut: '', action: () => this.executeCommand('brain-mesh stats') },
            { icon: '❓', label: 'Help', shortcut: '', action: () => this.executeCommand('help') },
        ];

        this.commands = commands;
        this.renderPaletteResults(commands);

        // Keyboard shortcut
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey && e.shiftKey && e.key === 'P') {
                e.preventDefault();
                toggleCommandPalette();
            }
            if (e.key === 'Escape') {
                closeCommandPalette();
            }
        });

        // Filter on input
        document.getElementById('palette-input').addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            const filtered = commands.filter(c => c.label.toLowerCase().includes(query));
            this.renderPaletteResults(filtered);
        });
    },

    renderPaletteResults(commands) {
        const container = document.getElementById('palette-results');
        container.innerHTML = commands.map((cmd, i) => `
            <div class="command-palette-item ${i === 0 ? 'selected' : ''}" onclick="Studio.commands[${i}].action(); closeCommandPalette();">
                <span class="icon">${cmd.icon}</span>
                <span class="label">${cmd.label}</span>
                ${cmd.shortcut ? `<span class="shortcut">${cmd.shortcut}</span>` : ''}
            </div>
        `).join('');
    },

    // ═══ Glyph Codex ═══
    setupGlyphCodex() {
        const glyphs = [
            { symbol: '⟁Sek⟁', name: 'Execute', description: 'Run command' },
            { symbol: '⟁Wo⟁', name: 'Assign', description: 'Variable assignment' },
            { symbol: '⟁K\'an⟁', name: 'Transform', description: 'Process/transform' },
            { symbol: '⟁Ajaw⟁', name: 'Lord', description: 'Admin command' },
            { symbol: '⟁Muwan⟁', name: 'Owl', description: 'Night/async operation' },
            { symbol: '⟁K\'uhul⟁', name: 'Divine', description: 'Sacred/critical op' },
            { symbol: '🔒', name: 'SCXQ2', description: 'Encryption' },
            { symbol: '🧠', name: 'Neural', description: 'Brain operation' },
        ];

        const container = document.getElementById('glyph-list');
        container.innerHTML = glyphs.map(g => `
            <div class="glyph-item" onclick="insertGlyph('${g.symbol}')" title="${g.description}">
                <span class="symbol">${g.symbol}</span>
                <span class="name">${g.name}</span>
            </div>
        `).join('');
    },

    // ═══ Uptime Counter ═══
    startUptimeCounter() {
        const updateUptime = () => {
            const seconds = Math.floor((Date.now() - this.startTime) / 1000);
            document.getElementById('uptime-display').textContent = `${seconds}s`;
            document.documentElement.style.setProperty('--mx2lm-uptime', seconds);
        };

        setInterval(updateUptime, 1000);
        updateUptime();
    }
};

// ═══ Global Functions ═══

function clearTerminal() {
    const output = document.getElementById('terminal-output');
    output.innerHTML = '<div class="line"><span class="info">Terminal cleared</span></div>';
}

function toggleCommandPalette() {
    const palette = document.getElementById('command-palette');
    const overlay = document.getElementById('palette-overlay');
    const isOpen = palette.classList.contains('open');

    if (isOpen) {
        closeCommandPalette();
    } else {
        palette.classList.add('open');
        overlay.classList.add('open');
        document.getElementById('palette-input').focus();
    }
}

function closeCommandPalette() {
    document.getElementById('command-palette').classList.remove('open');
    document.getElementById('palette-overlay').classList.remove('open');
    document.getElementById('palette-input').value = '';
    document.getElementById('terminal-input').focus();
}

function toggleSidebar() {
    document.querySelector('.studio').classList.toggle('sidebar-collapsed');
}

function insertGlyph(glyph) {
    const input = document.getElementById('terminal-input');
    input.value = glyph + ' ' + input.value;
    input.focus();
}

function newFile() {
    const name = prompt('File name:', 'new.khl');
    if (name) {
        const tabs = document.getElementById('editor-tabs');
        const tab = document.createElement('button');
        tab.className = 'editor-tab';
        tab.setAttribute('data-file', name);
        tab.textContent = name;
        tabs.appendChild(tab);
    }
}

function runCode() {
    const code = document.getElementById('editor-textarea').value;
    const lines = code.split('\n').filter(l => l.trim() && !l.trim().startsWith('//'));

    lines.forEach(line => {
        Studio.executeCommand(line.trim());
    });
}

// ═══ Initialize ═══
document.addEventListener('DOMContentLoaded', () => Studio.init());

// ═══ Service Worker Registration ═══
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js')
            .then(reg => console.log('SW registered:', reg.scope))
            .catch(err => console.log('SW registration failed:', err));
    });
}
    </script>
</body>
</html>
