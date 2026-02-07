# Micronaut Object Server (SCO/1)

Micronaut is a sealed SCO/1 object with a file-only contract. It is orchestrated by PowerShell and exposes a loopback REST router that **only** maps requests to file operations. No JavaScript runtime is required or permitted within the Micronaut object boundary.

## Object Layout

```
micronaut/
├─ micronaut.s7
├─ object.toml
├─ semantics.xjson
├─ brains/
│  ├─ trigrams.json
│  ├─ bigrams.json
│  └─ meta-intent-map.json
├─ io/
│  ├─ chat.txt
│  ├─ stream.txt
│  └─ snapshot/
├─ trace/
│  └─ scxq2.trace
└─ proof/
   └─ scxq2.proof
```

## `chat.txt` — Canonical Record Format

Append-only, immutable records.

```
--- MESSAGE ---
id: <uuid>
time: <unix_ms>
role: user | system | micronaut
intent: chat | generate | classify | complete
context: <optional>
payload:
<UTF-8 text>
--- END ---
```

## `stream.txt` — Semantic Emission

Append-only projection output.

```
>> t=184 ctx=@π mass=0.73
Hello!
```

## REST ↔ FILE Mapping

| REST Endpoint    | File Action         |
| ---------------- | ------------------- |
| `POST /chat`     | append → `chat.txt` |
| `GET /stream`    | read → `stream.txt` |
| `GET /status`    | read → object state |
| `POST /snapshot` | rotate snapshot     |

REST is a loopback router only; it never executes logic.

## Lifecycle Contract

```
INIT → READY → RUNNING → IDLE → HALT
```

## PowerShell Orchestrator

`micronaut/micronaut.ps1` is the canonical orchestrator. It watches `chat.txt`, delegates KUHUL/SCXQ μ-ops to the sealed object, and appends emissions to `stream.txt`.
