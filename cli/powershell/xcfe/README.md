# XCFE-PS-ENVELOPE-1

**Governed PowerShell Delegation via XCFE + CM-1**

Status: Draft-Frozen v1

## Overview

This module provides a lawful, auditable PowerShell execution layer that:

- **Deny-by-default**: Only allowlisted actions can execute
- **No arbitrary text**: PowerShell never receives raw user input
- **Deterministic lowering**: DSL intents lower to single cmdlets
- **CM-1 auditable**: All executions carry phase geometry

## Architecture

```
[PS-DSL Intent JSON]
        ↓
[XCFE Legality Verifier]
        ↓
[Deterministic Lowering]
        ↓
[CM-1 Annotated Stream]
        ↓
[PowerShell -EncodedCommand]
        ↓
[Host PowerShell Runtime]
        ↓
[Observation + Audit Record]
```

## Quick Start

```bash
# List allowed actions
node psx-cli.js --list-actions

# Verify an intent (no execution)
node psx-cli.js --verify examples/process-list.json

# Execute an intent
node psx-cli.js examples/process-list.json
```

## PS-DSL Intent Format

```json
{
  "@dsl": "ps-dsl.v1",
  "action": "process.list",
  "params": {}
}
```

### With Parameters

```json
{
  "@dsl": "ps-dsl.v1",
  "action": "service.query",
  "params": {
    "status": "running"
  }
}
```

## Allowed Actions

| Action              | Cmdlet                  | Params                    |
|---------------------|-------------------------|---------------------------|
| `process.list`      | Get-Process             | (none)                    |
| `process.query`     | Get-Process             | name, id                  |
| `service.list`      | Get-Service             | (none)                    |
| `service.query`     | Get-Service             | name, status              |
| `eventlog.list`     | Get-EventLog            | logname                   |
| `eventlog.query`    | Get-EventLog            | logname, newest, entrytype|
| `computer.info`     | Get-ComputerInfo        | (none)                    |
| `host.info`         | Get-Host                | (none)                    |
| `disk.list`         | Get-PSDrive             | (none)                    |
| `network.adapters`  | Get-NetAdapter          | (none)                    |
| `network.connections`| Get-NetTCPConnection   | state                     |
| `date.get`          | Get-Date                | format                    |
| `env.list`          | Get-ChildItem Env:      | (none)                    |

## Denied Cmdlets (Hard Ban)

These are **never** allowed, even if somehow referenced:

- Execution: `Invoke-Expression`, `iex`, `Start-Process`
- Code loading: `Add-Type`, `New-Object`, `Import-Module`
- File mutation: `Set-Content`, `Remove-Item`, `New-Item`
- Network: `Invoke-WebRequest`, `curl`, `wget`
- System mutation: `Stop-Process`, `Stop-Service`, `Restart-Computer`

## XCFE Envelope Format

For full legality envelope with capability constraints:

```json
{
  "@xcfe": "ps-envelope.v1",
  "@control": {
    "phase": "delegate.external",
    "target": "powershell",
    "audit": true
  },
  "@capability": {
    "powershell": true,
    "interactive": false,
    "network": false,
    "filesystem": "read-only"
  },
  "@intent": {
    "@dsl": "ps-dsl.v1",
    "action": "process.list",
    "params": {}
  }
}
```

## CM-1 Audit Trail

Every execution is wrapped with CM-1 control characters:

```
[SOH]ps-envelope.v1[GS]action=process.list[STX]Get-Process[ETX][EOT]
```

This provides:
- Full provenance
- Replayability
- Audit verification
- Zero rendering impact

## Security Properties

- **No arbitrary text execution**
- **No expression evaluation**
- **No privilege escalation**
- **No runtime branching**
- **No environment mutation**
- **No persistence**
- **No network access**

This is **delegated observation**, not command & control.

## Files

| File                    | Purpose                           |
|-------------------------|-----------------------------------|
| `psx-cli.js`            | CLI entry point                   |
| `ps-dsl-verifier.js`    | Legality verification             |
| `ps-command-registry.js`| Allowlist/denylist registry       |
| `cm1-wrapper.js`        | CM-1 audit binding                |
| `ps-dsl.schema.xjson`   | DSL schema                        |
| `ps-envelope.schema.xjson`| Envelope schema                 |

## KUHUL Class

```
XCFE Class: host.powershell.executor
Domain: host
Scope: process
Authority: read-only (observation)
```
