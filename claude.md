# MATRIX - Project Context for Claude

## Overview

MATRIX is a distributed AI code agent orchestration system. It bridges a PHP broker to AI coding assistants (Claude Code, Codex, Aider, Ollama) with adaptive routing via Brain-Mesh.

## Key Concepts

### Brain-Mesh Orchestrator
Epsilon-greedy multi-armed bandit for LLM selection. Balances exploration (trying new models) with exploitation (using best performers). Located in `api/brain-mesh/`.

### RIG Federation
**Paradigm shift**: Local machines share Ollama models via DNS without HTTP port forwarding. The PHP server routes requests; rigs poll for work.

- `rig-manager.js` - Client-side rig lifecycle
- `rig-router.php` - Server-side request routing
- `model@rig-id` syntax for targeting specific rigs

### K'UHUL Glyphs
Domain-specific language for terminal commands:
- `⟁Sek⟁` - Execute command
- `⟁Wo⟁` - Assign variable
- `⟁K'an⟁` - Transform data
- `⟁Ajaw⟁` - Admin operation
- `⟁Muwan⟁` - Async operation
- `⟁K'uhul⟁` - Divine/critical operation

### CM-1 Protocol
Control characters for phase management:
- SOH (0x01) - Start of header / phase begin
- STX (0x02) - Start of text / execution
- ETX (0x03) - End of text / phase end
- EOT (0x04) - End of transmission

### MX2LM
Server runtime (Node.js) with KUHUL-controlled lifecycle and π-decay restart logic.

## Directory Structure

```
api/brain-mesh/     - PHP APIs (BrainMesh, LLMProvider, rig-router)
bin/matrix-cli/     - Node.js CLI with DNS tunneling + RIG management
pwa/studio/         - KUHUL GLYPH Studio PWA
cli/                - MX2LM server runtime
agent/              - Python AI bridge
specs/              - Language specifications (KUHUL, CM-1, MXS)
```

## Coding Conventions

### PHP
- PSR-4 autoloading
- Type declarations where applicable
- JSON responses with `@context` for ASX compatibility
- PDO for database (MySQL/MariaDB)

### JavaScript (Node.js)
- ES modules preferred
- Async/await for promises
- Config stored in `~/.matrix-*.json`

### CSS
- Micronaut CSS: CSS variables bound to health metrics
- ATOMIC+GLYPH framework for UI
- Themes via CSS custom properties

## Key Files

| File | Purpose |
|------|---------|
| `api/brain-mesh/BrainMesh.php` | Adaptive LLM selection engine |
| `api/brain-mesh/LLMProvider.php` | Multi-provider routing (cloud + local + rig) |
| `api/brain-mesh/rig-router.php` | RIG federation request handling |
| `bin/matrix-cli/index.js` | CLI entry point |
| `bin/matrix-cli/rig-manager.js` | RIG lifecycle management |
| `pwa/studio/index.php` | KUHUL Studio PWA shell |
| `pwa/studio/glyph-runtime.js` | Client-side glyph interpreter |

## Common Tasks

### Add a new LLM provider
1. Add provider config in `config.example.php`
2. Add resolution in `LLMProvider::resolveModel()`
3. Add chat method `chatProviderName()`
4. Update match expression in `chat()`

### Add a new CLI command
1. Add command object in `bin/matrix-cli/index.js` commands array
2. Include in help text
3. Add examples

### Add a new terminal command
1. Add to namespace in `command-registry.php`
2. Add handler in `terminal.php`

### Add a new glyph
1. Define in `glyph-runtime.js` GLYPHS object
2. Add handler in glyphHandlers
3. Document in README

## Database Tables

### brains / brain_scores
LLM models and their performance scores per domain.

### rigs / rig_requests
Federated rigs and their pending inference requests.

## API Patterns

### ASX Context
All JSON responses include `@context` for semantic typing:
```json
{
  "@context": "asx://brain-mesh/result",
  "success": true,
  "data": {}
}
```

### Error Handling
```json
{
  "@context": "asx://brain-mesh/error",
  "success": false,
  "error": "Error message"
}
```

## Testing

- PHP: Manual testing via curl/Postman
- CLI: `node bin/matrix-cli/index.js <command>`
- PWA: Open `/pwa/studio/` in browser

## Environment

- PHP 8.0+ with PDO MySQL
- Node.js 18+ (for fetch API)
- MySQL/MariaDB 5.7+
- Ollama (optional, for local models)

## Laws

The codebase references the law:
```
ASX = XCFE = XJSON = KUHUL = AST = CM-1
```

This establishes equivalence between:
- ASX (Agent Semantic eXchange)
- XCFE (eXecutable Code Flow Engine)
- XJSON (eXtended JSON)
- KUHUL (execution glyphs)
- AST (Abstract Syntax Tree)
- CM-1 (Control Micronaut protocol)
