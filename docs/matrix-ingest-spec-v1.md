# MATRIX Ingest Spec v1

Version: v1.0
Status: Draft

## Scope

This document defines the binary-first ingest pipeline for MATRIX, including:

- SVG-Tensor packing (geometry-first layout instead of flat arrays)
- GGUF embedding ingest unification
- ATOM framing and file layout

## Goals

- Eliminate text parsing in hot loops (offline normalization only).
- Provide deterministic, fixed-geometry binary atoms.
- Support mmap/streaming access with predictable alignment.
- Enable cross-compatibility with GGUF embeddings and future geometry engines.

## Terminology

- **Atom**: Fixed-size block of tokens or geometry records.
- **SVG-Tensor**: A geometry-centric tensor layout where segments and attributes are stored in aligned, fixed-width tables rather than flat token arrays.
- **Ingest**: Offline conversion from text formats (HTML/JSON/MD/SVG) to binary atoms.

## Canonical Pipeline

```
[ HTML | JSON | MD | SVG ]
        ↓ (one-time)
   CLEAN + NORMALIZE
        ↓
     TOKENIZE / SYMBOL MAP
        ↓
   PACK → BINARY ATOMS
        ↓
  mmap / seek / stream
        ↓
   π-LM / Embedding / Geometry
```

## File Container

All ingest outputs are stored in a single container file with a fixed header and aligned sections.

```
+-------------------+
| MATRIX Header     |
+-------------------+
| Section Table     |
+-------------------+
| Token Atoms       |
+-------------------+
| SVG-Tensor Atoms  |
+-------------------+
| GGUF Embeddings   |
+-------------------+
```

### Header (64 bytes)

| Offset | Size | Type     | Field            | Description                          |
|--------|------|----------|------------------|--------------------------------------|
| 0      | 8    | char[8]  | magic            | "MATRIXv1"                           |
| 8      | 4    | uint32   | version          | 1                                    |
| 12     | 4    | uint32   | flags            | reserved                             |
| 16     | 8    | uint64   | section_count    | number of sections                   |
| 24     | 8    | uint64   | atom_size        | tokens or records per atom           |
| 32     | 8    | uint64   | alignment        | byte alignment (default 32)          |
| 40     | 8    | uint64   | vocab_size       | vocabulary size for token atoms      |
| 48     | 16   | byte[16] | file_hash        | xxh3_128 of all sections (optional)  |

### Section Table Entry (32 bytes)

| Offset | Size | Type     | Field            | Description                          |
|--------|------|----------|------------------|--------------------------------------|
| 0      | 8    | char[8]  | section_id       | e.g. "TOKENS\0\0"                   |
| 8      | 8    | uint64   | offset           | absolute file offset                  |
| 16     | 8    | uint64   | length           | byte length                           |
| 24     | 8    | uint64   | atom_count       | number of atoms in section           |

## Token Atoms

Token atoms store fixed-width token IDs (uint16 or uint32). They are packed as sequential atoms with no per-atom headers.

- **dtype**: uint16 if vocab <= 65,535 else uint32
- **atom_size**: default 256 tokens
- **layout**: contiguous array, aligned to `alignment`

## SVG-Tensor Atoms

SVG-Tensor atoms store vector geometry in fixed-width records, enabling deterministic geometry processing without parsing SVG text at runtime.

Each atom contains one or more **Geometry Tables** with aligned columns. The minimal table set is:

1. **Segment Table** (fixed-width rows)
2. **Attribute Table** (fixed-width rows)
3. **Index Table** (uint32 offsets)

### Segment Table (row = 32 bytes)

| Field        | Type    | Description                            |
|--------------|---------|----------------------------------------|
| opcode       | uint8   | 0=M,1=L,2=C,3=Q,4=Z, etc.              |
| flags        | uint8   | bitflags (relative, smooth, etc.)      |
| reserved     | uint16  | padding                                |
| x0, y0       | int32   | start point (fixed-point 16.16)        |
| x1, y1       | int32   | end point (fixed-point 16.16)          |
| c0x, c0y     | int32   | control point 0 (if applicable)        |
| c1x, c1y     | int32   | control point 1 (if applicable)        |

### Attribute Table (row = 16 bytes)

| Field        | Type    | Description                            |
|--------------|---------|----------------------------------------|
| fill_rgba    | uint32  | packed RGBA                            |
| stroke_rgba  | uint32  | packed RGBA                            |
| stroke_w     | uint16  | stroke width (fixed-point 8.8)         |
| opacity      | uint16  | 0-65535                                |
| flags        | uint32  | fill/stroke rule, caps, joins          |

### Index Table

Maps segment ranges to attributes and paths.

| Field        | Type    | Description                            |
|--------------|---------|----------------------------------------|
| seg_start    | uint32  | segment start index                    |
| seg_count    | uint32  | segment count                          |
| attr_index   | uint32  | attribute table index                  |
| path_id      | uint32  | user-defined path ID                   |

### SVG-Tensor Atom Layout

```
[Segment Table][Attribute Table][Index Table]
```

All tables are aligned to `alignment`, and counts are stored in the section table for the atom range.

## GGUF Embedding Ingest Unification

GGUF embeddings are stored in a dedicated section that mirrors GGUF tensor metadata but is flattened into fixed-width records for streaming.

### GGUF Embedding Section Layout

```
[GGUF Tensor Header][Embedding Records...]
```

#### GGUF Tensor Header (48 bytes)

| Offset | Size | Type   | Field         | Description                          |
|--------|------|--------|---------------|--------------------------------------|
| 0      | 8    | char[8]| magic         | "GGUFEMB"                           |
| 8      | 4    | uint32 | version       | 1                                    |
| 12     | 4    | uint32 | dtype         | 0=F16,1=F32,2=I8,3=I16               |
| 16     | 8    | uint64 | vector_size   | dimension size                       |
| 24     | 8    | uint64 | record_count  | number of vectors                    |
| 32     | 8    | uint64 | stride_bytes  | bytes per vector                     |
| 40     | 8    | uint64 | reserved      | future                               |

#### Embedding Record

```
[vector_id:uint64][vector:stride_bytes]
```

- **vector_id**: external ID mapping (e.g., doc ID)
- **vector**: contiguous values (dtype), aligned to `alignment`

## Validation Rules

- Header `magic` and section IDs must match known constants.
- All section offsets must be aligned to `alignment`.
- `atom_count * atom_size` must match section length (for token atoms).
- SVG-Tensor tables must be aligned and use fixed-point formats.
- GGUF embedding vectors must match `stride_bytes`.

## Compatibility Notes

- All sections are optional; consumers should ignore unknown sections.
- Token atom dtype is chosen based on vocab size at ingest time.
- SVG-Tensor provides deterministic geometry for GPU-friendly rendering.

## Example Section IDs

- `TOKENS\0\0` for token atoms
- `SVGTENS` for SVG-Tensor atoms
- `GGUFEMB` for GGUF embeddings

## Future Extensions

- Per-atom hashes for provenance.
- Compression blocks (LZ4/Zstd) with fixed window sizes.
- Multi-layer geometry (filters, gradients, masks).
