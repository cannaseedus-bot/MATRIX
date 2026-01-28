# MX2LM CLI Specification

**MX2LM CLI** is a KUHUL-controlled host orchestrator — a projection and delegation layer that reacts to KUHUL collapse values.

## Architecture

```
KUHUL π (intent physics)
    ↓
KUHUL Class (capability domain)
    ↓
XCFE (legality)
    ↓
Micronaut Cluster (aggregation)
    ↓
MX2LM CLI (host adapter)
```

The CLI never decides policy. It **observes collapse and applies mappings**.

## Core Runtime Zones

| Zone              | Role                      |
|-------------------|---------------------------|
| `π-engine`        | Evaluates KUHUL π actions |
| `class-router`    | Activates KUHUL classes   |
| `xcfe-gate`       | Enforces legality         |
| `micronaut-cluster` | Aggregates signals      |
| `host-adapters`   | Delegates to OS / SDKs    |
| `projection`      | UI / CSS / pages          |

---

## KUHUL Classes → CLI Modules

### `css.micronaut` — CLI UI Runtime

Writes CSS variables, adjusts UI glow/motion/alert states, drives CLI animations.

```css
--mx2lm-glow: 0.72;
--mx2lm-drift: 0.08;
--mx2lm-alert: 0.15;
```

### `css.cluster` — UI Signal Aggregator

Collects many π actions, collapses into CSS variables, prevents UI spam.

### `net.cors` — API Request Gate

Biases whether API calls are allowed:
- Low signal → block request silently
- Medium → local/static fallback
- High → remote API allowed

### `net.dns` — Endpoint Bias

Chooses between localhost, LAN, or WAN. Prefers cached/static endpoints under low energy.

### `api.static` — Read-Only API Loader

Fetches schemas, loads manifests, pulls documentation pages.

### `api.local.static` — Local Dashboard Server

Launches or connects to `http://localhost:mx2lm` for status pages, logs, and inspectors.

### `host.launcher` — PowerShell / OS Adapter

Executes only XCFE-verified, allowlisted commands:
- `Get-Process`
- `Get-Service`

PowerShell is observation-only, read-only, gated by π collapse.

### `domain.handler` — CLI URI Router

Handles URIs like:
- `mx2lm://dashboard`
- `kuhul://inspect/css`
- `asx://manifest`

---

## CLI Command Reference

| Command                | KUHUL Classes Involved            |
|------------------------|-----------------------------------|
| `mx2lm status`         | css.cluster + api.local.static   |
| `mx2lm models list`    | net.cors + api.static            |
| `mx2lm inspect ui`     | css.micronaut                    |
| `mx2lm diagnose host`  | host.launcher + PS-DSL           |
| `mx2lm open dashboard` | domain.handler + api.local.static|
| `mx2lm config show`    | api.static                       |
| `mx2lm server start`   | api.local.server                 |
| `mx2lm server stop`    | api.local.server                 |
| `mx2lm server status`  | api.local.server                 |

---

## Micronaut Clusters

Micronaut clusters are **decision surfaces**, not features.

Example cluster:

```kuhul
⟁π.action⟁ system_pressure
⟁kuhul.class⟁ host.launcher
```

Collapse result:

| Value  | Action                  |
|--------|-------------------------|
| 0.18   | do nothing              |
| 0.55   | show warning            |
| 0.92   | allow diagnostic probe  |

---

## Server Runtime

### π-Driven Restart Policy

| π Support | CLI Action           |
|-----------|----------------------|
| `< 0.4`   | do not restart       |
| `0.4–0.7` | restart once         |
| `> 0.7`   | restart with backoff |
| `> 0.9`   | immediate heal       |

### Lifecycle Commands

```bash
mx2lm server start    # Launch server in new terminal
mx2lm server stop     # Stop running server
mx2lm server status   # Show server health
```

---

## The Invariant

> **MX2LM CLI never executes intent. It only responds to collapsed reality.**

No command:
- bypasses π
- bypasses XCFE
- bypasses class constraints

---

## System Summary

| Layer       | Role           |
|-------------|----------------|
| MX2LM CLI   | the shell      |
| KUHUL       | the physics    |
| CSS micronauts | the nerves  |
| PowerShell  | muscle         |
| XCFE        | law            |
