# MATRIX Stylesheets (MXS) v1

MXS is a CSS-like declarative layer for MATRIX `@@gpu.execution` specs. It **does not add new structure** or new operations. Instead it applies **policy, tuning, and preferences** to existing backends, nodes, pipelines, and mesh compute elements.

## Mental model

- `@@gpu.execution` = DOM
- `@cuda`, `@webgpu`, `@inference.pipeline`, `@mesh.compute` = elements
- MXS = policy + tuning + preferences, **no imperative logic**

MXS never defines *what* exists, only *how it should behave* under conditions.

---

## Selector model

Selectors map directly to existing spec nodes:

```mxs
backend[cuda] {
  priority: 0;
  preferred-dtype: fp16;
  allow-quantization: true;
}

backend[capability~="webgpu"] {
  max-batch-size: 4;
  browser-min-performance: 0.6;
}

node[operation="attention"] {
  impl: flash_attention_v2;
  backend: auto;
}

node[runtime="cpu"][operation="tokenizer"] {
  threads: auto;
  pin-to-cores: performance;
}
```

**Selector mapping (v1):**

- `backend[cuda]` → `kind = "backend"`, `name = "cuda"`
- `backend[capability~="webgpu"]` → `kind = "backend"`, `attrs.capabilities` includes `"webgpu"`
- `node[operation="attention"]` → `kind = "node"`, `attrs.operation = "attention"`
- `pipeline[name="qwen…"]` → `kind = "pipeline"`, `name = "qwen2.5-7b-instruct"`
- `coordinator` → `kind = "coordinator"`

---

## Scopes and environment queries

MXS supports a media-query style `@env` wrapper:

```mxs
@env (device = "browser") {
  backend[webgpu] {
    priority: 1;
    shader-fusion: true;
    buffer-reuse: true;
  }

  backend[cuda] {
    disabled: true;
  }
}

@env (device = "server" && gpu = "nvidia") {
  backend[cuda] {
    priority: 1;
    flash-attention: true;
    cudagraphs: true;
  }

  backend[webgpu],
  backend[webgl] {
    disabled: true;
  }
}
```

This **does not change the graph**, only how it is realized.

---

## Pipeline targeting

MXS can style inference pipeline steps (purely declarative):

```mxs
pipeline[name="qwen2.5-7b-instruct"] {
  max-tokens: 4096;
  temperature: 0.6;
  top-p: 0.9;
}

pipeline[name="qwen2.5-7b-instruct"] step[tokenize] {
  runtime: cpu;
  threads: auto;
}

pipeline[name="qwen2.5-7b-instruct"] step[blocks] {
  runtime: gpu;
  backend-preference: "cuda, rocm, mps, webgpu, cpu";
}
```

---

## Mesh compute policy

Apply constraints and preferences to mesh compute:

```mxs
node[type="browser.node"] {
  min-memory-gb: 4;
  min-cores: 4;
  max-concurrent-tasks: 2;
}

node[type="browser.node"][capability~="webgpu"] {
  preferred-role: "attention, mlp";
}

node[type="browser.node"][capability~="webgl"] {
  preferred-role: "lightweight-ops";
}

coordinator {
  strategy: model-parallel;
  micro-batches: 4;
  gradient-accumulation: 8;
}
```

---

## Load order and linking

When `@@gpu.execution` is serialized to JSON or YAML, add an MXS link block:

```json
{
  "@links": {
    "styles": [
      { "href": "base.mxs", "sha256": "...optional...", "authority": "policy" },
      { "href": "browser.mxs" }
    ]
  }
}
```

**Load order:**

1. Base spec
2. `base.mxs`
3. `browser.mxs`
4. Later stylesheets

Later rules win; more specific selectors win.

---

## Grammar (frozen v1)

**Lexical:**

- **Identifier:** `[a-zA-Z_][a-zA-Z0-9_-]*`
- **String:** `"..."` or `'...'`
- **Number:** `123`, `3.14`
- **Boolean:** `true`, `false`

**EBNF:**

```ebnf
stylesheet      ::= (env_block | rule)* ;

env_block       ::= "@env" "(" condition ")" "{" rule* "}" ;

condition       ::= expr (("&&" | "||") expr)* ;
expr            ::= IDENT "=" STRING ;

rule            ::= selector "{" declaration* "}" ;

selector        ::= simple_selector (compound_selector)* ;

simple_selector ::= IDENT ;

compound_selector
                ::= "[" attr_selector "]"
                 | " " IDENT
                 | ":" IDENT ;

attr_selector   ::= IDENT
                 | IDENT "=" STRING
                 | IDENT "~=" STRING ;

declaration     ::= IDENT ":" value ";" ;

value           ::= STRING
                 | NUMBER
                 | BOOLEAN ;
```

No nested rules, no functions, no expressions in values—just literals.

---

## Reference parser (TypeScript, minimal)

```ts
type MxsValue = string | number | boolean;

interface MxsDeclaration {
  name: string;
  value: MxsValue;
}

interface MxsSelectorPart {
  type: "tag" | "attr" | "pseudo";
  name: string;
  op?: "=" | "~=";
  value?: string;
}

interface MxsRule {
  env?: { condition: string }; // raw for now
  selectors: MxsSelectorPart[][];
  declarations: MxsDeclaration[];
}

interface MxsStylesheet {
  rules: MxsRule[];
}

export function parseMxs(src: string): MxsStylesheet {
  let i = 0;
  const len = src.length;

  const peek = () => src[i] || "";
  const next = () => src[i++] || "";
  const isWs = (c: string) => /\s/.test(c);
  const isIdentStart = (c: string) => /[a-zA-Z_]/.test(c);
  const isIdent = (c: string) => /[a-zA-Z0-9_-]/.test(c);

  const skipWsAndComments = () => {
    while (i < len) {
      if (isWs(peek())) { i++; continue; }
      if (peek() === "/" && src[i + 1] === "*") {
        i += 2;
        while (i < len && !(src[i] === "*" && src[i + 1] === "/")) i++;
        i += 2;
        continue;
      }
      break;
    }
  };

  const ident = (): string => {
    skipWsAndComments();
    let s = "";
    if (!isIdentStart(peek())) throw new Error("Expected identifier");
    while (isIdent(peek())) s += next();
    return s;
  };

  const stringLit = (): string => {
    skipWsAndComments();
    const q = peek();
    if (q !== `"` && q !== `'`) throw new Error("Expected string");
    next();
    let s = "";
    while (i < len && peek() !== q) s += next();
    if (peek() !== q) throw new Error("Unterminated string");
    next();
    return s;
  };

  const numberLit = (): number => {
    skipWsAndComments();
    let s = "";
    while (/[0-9.]/.test(peek())) s += next();
    return parseFloat(s);
  };

  const value = (): MxsValue => {
    skipWsAndComments();
    const c = peek();
    if (c === `"` || c === `'`) return stringLit();
    if (/[0-9]/.test(c)) return numberLit();
    const id = ident();
    if (id === "true") return true;
    if (id === "false") return false;
    return id; // treat bare ident as string
  };

  const selector = (): MxsSelectorPart[][] => {
    skipWsAndComments();
    const groups: MxsSelectorPart[][] = [];
    let current: MxsSelectorPart[] = [];

    const pushTag = (name: string) =>
      current.push({ type: "tag", name });

    while (i < len) {
      skipWsAndComments();
      const c = peek();
      if (c === "{") break;
      if (c === ",") {
        next();
        groups.push(current);
        current = [];
        continue;
      }
      if (isIdentStart(c)) {
        const name = ident();
        pushTag(name);
        continue;
      }
      if (c === "[") {
        next();
        skipWsAndComments();
        const attrName = ident();
        skipWsAndComments();
        let op: "=" | "~=" | undefined;
        let val: string | undefined;
        if (peek() === "=" || (peek() === "~" && src[i + 1] === "=")) {
          op = peek() === "~" ? "~=" : "=";
          if (op === "~=") i += 2; else i++;
          val = stringLit();
        }
        skipWsAndComments();
        if (peek() !== "]") throw new Error("Expected ']' ");
        next();
        current.push({ type: "attr", name: attrName, op, value: val });
        continue;
      }
      if (c === ":") {
        next();
        const pseudo = ident();
        current.push({ type: "pseudo", name: pseudo });
        continue;
      }
      if (isWs(c)) { next(); continue; }
      break;
    }
    if (current.length) groups.push(current);
    return groups;
  };

  const declaration = (): MxsDeclaration | null => {
    skipWsAndComments();
    if (peek() === "}") return null;
    const name = ident();
    skipWsAndComments();
    if (next() !== ":") throw new Error("Expected ':'");
    const val = value();
    skipWsAndComments();
    if (next() !== ";") throw new Error("Expected ';'");
    return { name, value: val };
  };

  const rules: MxsRule[] = [];

  while (i < len) {
    skipWsAndComments();
    if (i >= len) break;

    let env: { condition: string } | undefined;

    if (src.startsWith("@env", i)) {
      i += 4;
      skipWsAndComments();
      if (next() !== "(") throw new Error("Expected '(' after @env");
      let cond = "";
      while (i < len && peek() !== ")") cond += next();
      if (next() !== ")") throw new Error("Expected ')'");
      env = { condition: cond.trim() };
      skipWsAndComments();
      if (next() !== "{") throw new Error("Expected '{' after @env");
      while (true) {
        skipWsAndComments();
        if (peek() === "}") { next(); break; }
        const sels = selector();
        skipWsAndComments();
        if (next() !== "{") throw new Error("Expected '{'");
        const decls: MxsDeclaration[] = [];
        while (true) {
          skipWsAndComments();
          if (peek() === "}") { next(); break; }
          const d = declaration();
          if (d) decls.push(d);
        }
        rules.push({ env, selectors: sels, declarations: decls });
      }
      continue;
    }

    const sels = selector();
    skipWsAndComments();
    if (next() !== "{") throw new Error("Expected '{'");
    const decls: MxsDeclaration[] = [];
    while (true) {
      skipWsAndComments();
      if (peek() === "}") { next(); break; }
      const d = declaration();
      if (d) decls.push(d);
    }
    rules.push({ selectors: sels, declarations: decls });
  }

  return { rules };
}
```

---

## Selector engine sketch

Assume `@@gpu.execution` is normalized into a JSON tree:

```ts
interface MatrixNode {
  kind: string; // backend, node, pipeline, coordinator, ...
  name?: string;
  attrs: Record<string, unknown>;
  children: MatrixNode[];
}
```

Matcher skeleton:

```ts
function matchesSelectorPart(node: MatrixNode, part: MxsSelectorPart): boolean {
  if (part.type === "tag") {
    return node.kind === part.name;
  }
  if (part.type === "attr") {
    const v = node.attrs[part.name];
    if (v == null) return false;
    if (!part.op) return true;
    if (part.op === "=") return String(v) === part.value;
    if (part.op === "~=") {
      const arr = Array.isArray(v) ? v : String(v).split(/\s*,\s*/);
      return arr.includes(part.value!);
    }
  }
  return false;
}

function matchesSelector(node: MatrixNode, selector: MxsSelectorPart[]): boolean {
  return selector.every((p) => matchesSelectorPart(node, p));
}

function applyStyles(
  root: MatrixNode,
  stylesheet: MxsStylesheet,
  env: Record<string, string | number | boolean>
) {
  const allNodes: MatrixNode[] = [];
  (function walk(n: MatrixNode) {
    allNodes.push(n);
    n.children.forEach(walk);
  })(root);

  for (const rule of stylesheet.rules) {
    for (const selectorGroup of rule.selectors) {
      for (const node of allNodes) {
        if (matchesSelector(node, selectorGroup)) {
          for (const decl of rule.declarations) {
            if (!node.attrs.style) node.attrs.style = {};
            node.attrs.style[decl.name] = decl.value;
          }
        }
      }
    }
  }
}
```

---

## Constraints (v1)

MXS **cannot**:

- Add new backends
- Add new ops
- Mutate contracts
- Introduce side effects

It can only **tune, constrain, and prioritize** existing structures.
