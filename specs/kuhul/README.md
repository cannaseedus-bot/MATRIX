# KUHUL Class System

KUHUL (π collapse intent physics) provides declarative capability classes that govern system behavior through mathematical constraints rather than imperative rules.

## Core Concepts

### Domains

| Domain   | Description                | Scopes              |
|----------|----------------------------|---------------------|
| `host`   | Local machine operations   | localhost, process  |
| `network`| LAN-scoped operations      | localhost, lan      |
| `cloud`  | Public-scoped operations   | public              |

### Authority Levels

| Authority    | Description                     |
|--------------|---------------------------------|
| `read-only`  | Can only read/query             |
| `read-write` | Can modify state                |
| `admin`      | Full control including process  |

## Defined Classes

### api.local.static

Static HTTP server with read-only constraints.

```
Domain: host
Scope: localhost
Authority: read-only
Methods: GET, OPTIONS
```

Used by: `cli/powershell/server.ps1`

### api.local.dynamic

Dynamic HTTP server with state modification.

```
Domain: host
Scope: localhost
Authority: read-write
Methods: GET, POST, PUT, DELETE, OPTIONS
```

### host.launcher

Process lifecycle manager with π-decay restart policy.

```
Domain: host
Scope: process
Authority: admin
```

Used by: `cli/server/lifecycle.js`

### agent.bridge

AI agent integration layer.

```
Domain: host
Scope: localhost
Authority: read-write
Agents: claude, codex, aider, ollama, ollama-cloud
```

Used by: `agent/agent.py`

### ui.projection

CSS Micronaut projection layer (no logic, display only).

```
Domain: host
Scope: localhost
Authority: read-only
Constraints: projection_only
```

Used by: `cli/ui/micronaut.css`

## π-Decay Engine

The π support value controls restart policy:

| π Support | Action              |
|-----------|---------------------|
| `< 0.4`   | Suppress restart    |
| `0.4–0.7` | Restart once        |
| `0.7–0.9` | Restart with backoff|
| `> 0.9`   | Immediate heal      |

Each crash decays π by factor of 0.7:

```
π_new = π_current × 0.7
```

## Usage

### PowerShell Server

```powershell
# KUHUL CLASS: api.local.static
# Domain: host | Scope: localhost | Authority: read-only

# Only GET allowed per class constraints
if ($request.HttpMethod -ne "GET") {
    Send-Response -StatusCode 405
}
```

### Headers

All responses include KUHUL class header:

```
X-KUHUL-Class: api.local.static
```

## Schema

See `classes.xjson` for full class definitions.
