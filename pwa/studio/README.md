# KUHUL GLYPH Studio

Terminal projection PWA with integrated code editor and K'UHUL glyph execution.

## Overview

KUHUL GLYPH Studio is a Progressive Web App that provides:
- Web-based terminal connected to Brain-Mesh API
- Integrated code editor with K'UHUL syntax support
- Real-time health monitoring via Micronaut CSS
- Offline support via Service Worker
- Installable as native-like app

## Quick Start

### Access

Open in browser:
```
http://localhost/pwa/studio/
```

### Install as PWA

1. Open the studio URL in Chrome/Edge
2. Click "Install" in address bar
3. Or use menu: "Install KUHUL GLYPH Studio"

## Features

### Terminal

- Execute Brain-Mesh commands
- K'UHUL glyph execution
- Command history (Up/Down arrows)
- Autocomplete for glyphs and commands
- Real-time latency display

### Editor

- Multi-file tabs
- K'UHUL syntax highlighting
- Run code directly (Ctrl+Enter)
- Variable interpolation preview

### Micronaut Panel

- Real-time health indicator
- Traffic pulse animation
- Uptime counter
- Drift bar (latency indicator)

### Themes

- **Emerald Ghost** (default) - Green on dark
- **Matrix** - Classic green terminal
- **Cyber** - Purple/blue futuristic
- **Night** - Blue-gray slate

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+Shift+P` | Command palette |
| `Ctrl+Enter` | Run editor code |
| `Up/Down` | Navigate history |
| `Tab` | Autocomplete |
| `Escape` | Close palette |

## Glyph Reference

### Execution Glyphs

| Glyph | Name | Purpose | Example |
|-------|------|---------|---------|
| `⟁Sek⟁` | Execute | Run command | `⟁Sek⟁ brain-mesh health` |
| `⟁Wo⟁` | Assign | Set variable | `⟁Wo⟁ msg = "Hello"` |
| `⟁K'an⟁` | Transform | Process data | `⟁K'an⟁ transform:uppercase $msg` |

### Control Glyphs

| Glyph | Name | Purpose | Example |
|-------|------|---------|---------|
| `⟁Ajaw⟁` | Lord | Admin command | `⟁Ajaw⟁ reset-cache` |
| `⟁Muwan⟁` | Owl | Async operation | `⟁Muwan⟁ long-task` |
| `⟁K'uhul⟁` | Divine | Critical op | `⟁K'uhul⟁ kernel-init` |

### Transform Operations

```
⟁K'an⟁ transform:uppercase $var    # HELLO
⟁K'an⟁ transform:lowercase $var    # hello
⟁K'an⟁ transform:reverse $var      # olleh
⟁K'an⟁ transform:length $var       # 5
⟁K'an⟁ transform:base64 $var       # aGVsbG8=
⟁K'an⟁ encode:uri $var             # hello%20world
⟁K'an⟁ encode:html $var            # &lt;html&gt;
```

## Terminal Commands

### Brain-Mesh

```bash
brain-mesh brains         # List all brains
brain-mesh leaderboard    # Show rankings
brain-mesh stats          # Adaptive statistics
brain-mesh health         # Health check
```

### LLM

```bash
llm providers             # List providers
llm models               # List models
llm chat "Hello"         # Chat completion
```

### K'UHUL

```bash
kuhul glyphs             # List available glyphs
kuhul status             # Kernel status
kuhul variables          # Session variables
kuhul exec "⟁Sek⟁ help"  # Execute glyph code
```

### File Operations

```bash
file list /specs         # List directory
file read /specs/test.json  # Read file
file tree /api           # Directory tree
```

## Example Session

```
⟁ > brain-mesh health
Health: OK
CM-1 Phase: IDLE
Brains: 12 active

⟁ > ⟁Wo⟁ greeting = "Hello MATRIX"
Variable greeting = "Hello MATRIX"

⟁ > ⟁K'an⟁ transform:uppercase $greeting
HELLO MATRIX

⟁ > ⟁Sek⟁ llm chat "Explain recursion"
Recursion is a programming technique where...

⟁ > brain-mesh leaderboard --domain=code
BRAIN LEADERBOARD (code)
────────────────────────────
1. claude-3-5-sonnet  [premium]  0.892
2. gpt-4o             [premium]  0.871
3. codellama          [standard] 0.756
```

## Architecture

```
┌─────────────────────────────────────────┐
│          KUHUL GLYPH Studio             │
│                                         │
│  ┌─────────────┐  ┌─────────────────┐  │
│  │  Terminal   │  │  Code Editor    │  │
│  │  (xterm)    │  │  (textarea)     │  │
│  └──────┬──────┘  └────────┬────────┘  │
│         │                  │            │
│         └────────┬─────────┘            │
│                  ▼                      │
│  ┌─────────────────────────────────┐   │
│  │     Glyph Runtime (JS)          │   │
│  │  - Parse glyphs                 │   │
│  │  - Variable interpolation       │   │
│  │  - CM-1 phase tracking          │   │
│  └──────────────┬──────────────────┘   │
│                 ▼                       │
│  ┌─────────────────────────────────┐   │
│  │  terminal.php (Brain-Mesh API)  │   │
│  └─────────────────────────────────┘   │
└─────────────────────────────────────────┘
```

## Files

| File | Purpose |
|------|---------|
| `index.php` | Main PWA shell with embedded CSS/JS |
| `glyph-runtime.js` | Client-side glyph interpreter |
| `sw.js` | Service worker for offline support |
| `manifest.json` | PWA metadata and shortcuts |

## Offline Support

The Service Worker caches:
- Static assets (index.php, JS, CSS)
- Recent API responses
- Glyph codex data

Offline behavior:
- Terminal shows cached responses
- Editor remains fully functional
- Commands queue until reconnection

## Customization

### Add Custom Glyph

In `glyph-runtime.js`:

```javascript
glyphs['⟁Custom⟁'] = {
    name: 'Custom',
    pattern: /^⟁Custom⟁\s+(.+)$/,
    handler: 'custom'
};
```

### Add Theme

In `index.php` CSS:

```css
[data-theme="mytheme"] {
    --bg-primary: #1a1a2e;
    --text-primary: #eaeaea;
    --accent: #e94560;
    /* ... */
}
```

## Troubleshooting

### Terminal Not Connecting

1. Check API URL in browser console
2. Verify CORS headers in terminal.php
3. Test API directly: `curl /api/brain-mesh/health.php`

### PWA Not Installing

1. Requires HTTPS (or localhost)
2. Check manifest.json validity
3. Verify service worker registration

### Glyphs Not Executing

1. Check glyph syntax matches pattern
2. Verify command is in allowlist
3. Check terminal.php response in network tab

## License

UNLICENSED

---

@law ASX = XCFE = XJSON = KUHUL = AST = CM-1
