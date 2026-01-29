# Changelog

All notable changes to the MATRIX project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.1.0] - 2026-01-29

### Added

#### Brain-Mesh Orchestrator
- Adaptive multi-LLM routing with epsilon-greedy selection
- `BrainMesh.php` - Core orchestration engine with scoring algorithm
- `LLMProvider.php` - Unified provider class supporting 9+ cloud providers
- `terminal.php` - Web-based terminal projection API
- `command-registry.php` - Deny-by-default command allowlist
- Support for Anthropic, OpenAI, Google, Mistral, Groq, Together, DeepSeek, xAI, Cohere
- Ollama integration with 40+ local models
- GGUF direct model support via llama.cpp

#### KUHUL GLYPH Studio
- `pwa/studio/index.php` - Single-file PWA shell
- `glyph-runtime.js` - Client-side K'UHUL glyph interpreter
- `sw.js` - Service worker with offline support
- `manifest.json` - PWA metadata with shortcuts
- Split-pane terminal + editor layout
- 4 themes: Emerald Ghost, Matrix, Cyber, Night
- Command palette (Ctrl+Shift+P)
- Glyph codex sidebar

#### Matrix CLI
- `bin/matrix-cli/` - Command-line interface
- DNS-based tunneling for firewall traversal
- TTP (Time-To-Propagate) protocol
- Commands: setup, connect, tunnel, brain, terminal, llm, kuhul

#### K'UHUL Namespace
- `kuhul exec` - Execute glyph code
- `kuhul parse` - Parse to AST
- `kuhul glyphs` - List available glyphs
- `kuhul status` - Kernel status
- `kuhul variables` - Session variables
- Admin commands: boot, admin, divine

#### Micronaut CSS
- CSS-variable-driven monitoring components
- Health indicator with gradient
- Traffic pulse animation
- Uptime counter
- Drift bar for latency

### Changed

- MX2LM CLI renamed to MX2LM Server (Node.js runtime)
- Updated architecture diagram in README
- Expanded project structure documentation

### Technical Details

#### Scoring Algorithm
- Success weight: 0.5
- Speed weight: 0.3
- Consistency weight: 0.2
- Exploration rate: 10%
- Rolling window: 50 executions

#### CM-1 Integration
- Phase tracking: SOH → STX → ETX → EOT
- Scope stack management
- Audit logging for phase transitions

---

## [2.0.0] - 2026-01-28

### Added

- Adaptive brain scoring in orchestrator
- MySQL installer for brain-mesh
- PHP brain-mesh micronaut system
- Standalone database support

### Changed

- Refactored orchestrator architecture
- Improved error handling

---

## [1.0.0] - 2026-01-15

### Added

- Initial release
- PHP Broker API
- Python Agent
- PWA Frontend
- MX2LM CLI
- PowerShell Server
- XCFE governed execution
- Support for Claude, Codex, Aider, Ollama

---

## Legend

- `Added` for new features
- `Changed` for changes in existing functionality
- `Deprecated` for soon-to-be removed features
- `Removed` for now removed features
- `Fixed` for any bug fixes
- `Security` for vulnerability fixes

---

@law ASX = XCFE = XJSON = KUHUL = AST = CM-1
