# CONTROL-MICRONAUT-1 (CM-1)

**Invisible Control Alphabet for XCFE / DOM / CSS Safe Execution**

Status: Draft-Frozen v1

## Core Principle

CM-1 defines a non-rendering, non-executing control layer composed exclusively of Unicode C0 control characters (U+0000–U+001F) and U+0020 (SPACE).

**CM-1 never introduces behavior. It only constrains interpretation.**

## Execution Model

```
[CM-1 Control Stream]
        ↓
[XCFE Phase Resolution]
        ↓
[Parser / Renderer / DOM]
```

CM-1 **cannot**:
- Inject tokens
- Create nodes
- Alter values
- Execute logic

CM-1 **can**:
- Mark boundaries
- Declare phases
- Signal scope transitions
- Segment streams
- Annotate interpretation zones

## Phase Control (Primary)

| Code       | Name | XCFE Mapping                | Meaning               |
|------------|------|-----------------------------|-----------------------|
| **U+0000** | NUL  | `@control.null`             | Absolute inert region |
| **U+0001** | SOH  | `@control.header.begin`     | Metadata/header phase |
| **U+0002** | STX  | `@control.body.begin`       | Interpretable content |
| **U+0003** | ETX  | `@control.body.end`         | Content closure       |
| **U+0004** | EOT  | `@control.transmission.end` | Collapse / flush      |

## Scope & Context Stack

| Code       | Name | XCFE Mapping              | Meaning                     |
|------------|------|---------------------------|-----------------------------|
| **U+000E** | SO   | `@control.scope.push`     | Enter sub-context           |
| **U+000F** | SI   | `@control.scope.pop`      | Exit sub-context            |
| **U+001B** | ESC  | `@control.mode.switch`    | Grammar / parser mode shift |
| **U+0010** | DLE  | `@control.literal.escape` | Bypass interpretation       |

## Structural Segmentation

| Code       | Name | XCFE Mapping          | Meaning              |
|------------|------|-----------------------|----------------------|
| **U+001C** | FS   | `@control.file.sep`   | Major boundary       |
| **U+001D** | GS   | `@control.group.sep`  | Group boundary       |
| **U+001E** | RS   | `@control.record.sep` | Record boundary      |
| **U+001F** | US   | `@control.unit.sep`   | Atomic unit boundary |

## Safe Subset (CM-1-SAFE)

### Allowed (Guaranteed non-rendering)

```
U+0000  NUL    U+000E  SO     U+001C  FS
U+0001  SOH    U+000F  SI     U+001D  GS
U+0002  STX    U+0010  DLE    U+001E  RS
U+0003  ETX                   U+001F  US
U+0004  EOT                   U+0020  SPACE
```

### Forbidden (Hard Ban)

```
U+0008  BS     U+000C  FF
U+000B  VT     U+0018  CAN
               U+001A  SUB
```

## Invariants

### Structural

- Every `STX` must have a matching `ETX`
- Scope stack (`SO`/`SI`) must be balanced
- Separators may not nest illegally
- `ESC` cannot appear inside literal-escaped regions
- `NUL` regions are non-observable

### Projection Invariant

> **Removing all CM-1 characters must not change visible output.**

If removing CM-1 alters DOM structure, CSS layout, or text rendering, the stream is **invalid**.

## Usage Example

```javascript
// CM-1 annotated execution stream
const stream =
  '\u0001' + 'ps-envelope.v1' +     // SOH: header
  '\u001D' + 'action=process.list' + // GS: group separator
  '\u0002' + 'Get-Process' +         // STX: body begin
  '\u0003' +                         // ETX: body end
  '\u0004';                          // EOT: transmission end
```

## Why This Matters

- Zero execution authority
- Zero render authority
- Deterministic
- Compressible
- Replayable
- Auditable
- Invisible by design

> **CM-1 is not a language. It is not syntax. It is not data. It is phase geometry.**
