# MATRIX Language Pack for npm

## Package Structure

```json
{
  "name": "@matrixlang/core",
  "version": "1.0.0-alpha.1",
  "description": "Universal MATRIX runtime - The execution substrate for all languages",
  "keywords": ["matrix", "runtime", "universal", "ast", "execution", "compiler", "vm"],
  "homepage": "https://matrixlang.io",
  "repository": "github:matrixlang/matrix",
  "license": "MIT",
  "author": "MATRIX Team <team@matrixlang.io>",
  "type": "module",
  "exports": {
    ".": {
      "import": "./dist/index.js",
      "require": "./dist/index.cjs",
      "types": "./dist/index.d.ts"
    },
    "./runtime": {
      "import": "./dist/runtime.js",
      "require": "./dist/runtime.cjs"
    },
    "./compiler": {
      "import": "./dist/compiler.js",
      "require": "./dist/compiler.cjs"
    },
    "./cli": "./dist/cli.js",
    "./*": "./dist/*.js"
  },
  "main": "./dist/index.js",
  "types": "./dist/index.d.ts",
  "files": [
    "dist",
    "src",
    "README.md",
    "LICENSE"
  ],
  "bin": {
    "matrix": "./dist/cli.js",
    "mx": "./dist/cli.js"
  },
  "scripts": {
    "build": "node scripts/build.js",
    "dev": "node scripts/dev.js",
    "test": "node scripts/test.js",
    "test:watch": "node scripts/test.js --watch",
    "lint": "node scripts/lint.js",
    "format": "node scripts/format.js",
    "benchmark": "node scripts/benchmark.js",
    "publish:alpha": "npm publish --tag alpha",
    "publish:beta": "npm publish --tag beta",
    "publish:release": "npm publish --tag latest"
  },
  "dependencies": {
    "@matrixlang/parser": "^1.0.0",
    "@matrixlang/vm": "^1.0.0",
    "@matrixlang/stdlib": "^1.0.0",
    "uuid": "^9.0.0",
    "chalk": "^5.0.0",
    "commander": "^11.0.0",
    "fs-extra": "^11.0.0",
    "glob": "^10.0.0",
    "minimatch": "^9.0.0"
  },
  "devDependencies": {
    "@types/node": "^20.0.0",
    "typescript": "^5.0.0",
    "esbuild": "^0.19.0",
    "vitest": "^1.0.0",
    "@vitest/ui": "^1.0.0",
    "prettier": "^3.0.0",
    "eslint": "^8.0.0"
  },
  "engines": {
    "node": ">=18.0.0"
  },
  "publishConfig": {
    "access": "public",
    "registry": "https://registry.npmjs.org/"
  }
}
```

## Core Packages (Monorepo structure)

### 1. Main Package (`@matrixlang/core`)

```json
{
  "name": "@matrixlang/core",
  "version": "1.0.0",
  "description": "Core MATRIX runtime and compiler",
  "main": "dist/index.js",
  "exports": {
    ".": "./dist/index.js",
    "./runtime": "./dist/runtime.js",
    "./compiler": "./dist/compiler.js",
    "./cli": "./dist/cli.js"
  }
}
```

### 2. Parser Package (`@matrixlang/parser`)

```json
{
  "name": "@matrixlang/parser",
  "version": "1.0.0",
  "description": "MATRIX AST parser and syntax transformer",
  "main": "dist/index.js",
  "exports": {
    ".": "./dist/index.js",
    "./ast": "./dist/ast.js",
    "./syntax": "./dist/syntax.js"
  }
}
```

### 3. Virtual Machine (`@matrixlang/vm`)

```json
{
  "name": "@matrixlang/vm",
  "version": "1.0.0",
  "description": "MATRIX virtual machine and execution engine",
  "main": "dist/index.js",
  "exports": {
    ".": "./dist/index.js",
    "./executor": "./dist/executor.js",
    "./scheduler": "./dist/scheduler.js"
  }
}
```

### 4. Standard Library (`@matrixlang/stdlib`)

```json
{
  "name": "@matrixlang/stdlib",
  "version": "1.0.0",
  "description": "MATRIX standard library - built-in modules and functions",
  "main": "dist/index.js",
  "exports": {
    ".": "./dist/index.js",
    "./io": "./dist/io.js",
    "./net": "./dist/net.js",
    "./fs": "./dist/fs.js",
    "./math": "./dist/math.js"
  }
}
```

### 5. Language Integrations

```json
{
  "name": "@matrixlang/javascript",
  "version": "1.0.0",
  "description": "JavaScript to MATRIX compiler",
  "main": "dist/index.js",
  "peerDependencies": {
    "@matrixlang/core": "^1.0.0"
  }
}
```

```json
{
  "name": "@matrixlang/python",
  "version": "1.0.0",
  "description": "Python to MATRIX compiler",
  "main": "dist/index.js"
}
```

```json
{
  "name": "@matrixlang/php",
  "version": "1.0.0",
  "description": "PHP to MATRIX compiler",
  "main": "dist/index.js"
}
```

## CLI Tool (`@matrixlang/cli`)

```json
{
  "name": "@matrixlang/cli",
  "version": "1.0.0",
  "description": "MATRIX command-line interface",
  "bin": {
    "matrix": "./dist/cli.js",
    "mx": "./dist/cli.js"
  },
  "main": "./dist/cli.js",
  "dependencies": {
    "@matrixlang/core": "^1.0.0",
    "commander": "^11.0.0",
    "chalk": "^5.0.0",
    "inquirer": "^9.0.0",
    "figlet": "^1.6.0"
  }
}
```

## VS Code Extension (`@matrixlang/vscode`)

```json
{
  "name": "@matrixlang/vscode",
  "version": "1.0.0",
  "description": "VS Code extension for MATRIX language",
  "main": "./dist/extension.js",
  "engines": {
    "vscode": "^1.80.0"
  },
  "contributes": {
    "languages": [
      {
        "id": "matrix",
        "aliases": ["MATRIX", "matrix"],
        "extensions": [".matrix", ".mx", ".mxt", ".mxc", ".mxw"],
        "configuration": "./language-configuration.json"
      }
    ],
    "grammars": [
      {
        "language": "matrix",
        "scopeName": "source.matrix",
        "path": "./syntaxes/matrix.tmLanguage.json"
      }
    ]
  }
}
```

## Quick Start Package (`create-matrix-app`)

```json
{
  "name": "create-matrix-app",
  "version": "1.0.0",
  "description": "Scaffold a new MATRIX application",
  "bin": {
    "create-matrix-app": "./dist/index.js"
  },
  "dependencies": {
    "degit": "^2.8.4",
    "prompts": "^2.4.2",
    "kolorist": "^1.8.0"
  }
}
```

## Sample Usage in package.json

```json
{
  "name": "my-matrix-app",
  "version": "1.0.0",
  "type": "module",
  "scripts": {
    "start": "matrix run src/app.matrix",
    "build": "matrix compile src/app.matrix --target node",
    "dev": "matrix watch src/ --target browser",
    "test": "matrix test src/**/*.test.matrix",
    "deploy": "matrix deploy --platform vercel"
  },
  "dependencies": {
    "@matrixlang/core": "^1.0.0",
    "@matrixlang/stdlib": "^1.0.0"
  },
  "devDependencies": {
    "@matrixlang/cli": "^1.0.0"
  },
  "matrix": {
    "entry": "src/app.matrix",
    "output": "dist/bundle.mx",
    "target": "universal",
    "modules": {
      "http": true,
      "fs": true,
      "database": true
    }
  }
}
```

## Installation Instructions

```bash
# Install globally for CLI usage
npm install -g @matrixlang/cli

# Or install locally in your project
npm install @matrixlang/core @matrixlang/stdlib

# Create a new MATRIX project
npx create-matrix-app my-app
cd my-app
npm install

# Run MATRIX file
matrix run app.matrix

# Compile to JavaScript
matrix compile app.matrix --target js

# Start development server
matrix serve --watch
```

## Workspace Configuration (package.json for monorepo)

```json
{
  "name": "@matrixlang/monorepo",
  "version": "1.0.0",
  "private": true,
  "workspaces": [
    "packages/*",
    "packages/compilers/*",
    "packages/runtimes/*",
    "packages/tools/*"
  ],
  "scripts": {
    "build": "turbo run build",
    "dev": "turbo run dev --parallel",
    "test": "turbo run test",
    "lint": "turbo run lint",
    "publish": "changeset publish"
  },
  "devDependencies": {
    "turbo": "^1.10.0",
    "@changesets/cli": "^2.26.0",
    "typescript": "^5.0.0"
  }
}
```

## Browser Package (`@matrixlang/browser`)

```json
{
  "name": "@matrixlang/browser",
  "version": "1.0.0",
  "description": "MATRIX runtime for browser environments",
  "main": "dist/matrix.min.js",
  "unpkg": "dist/matrix.min.js",
  "jsdelivr": "dist/matrix.min.js",
  "module": "dist/matrix.esm.js",
  "types": "dist/index.d.ts",
  "files": [
    "dist",
    "src"
  ],
  "scripts": {
    "build": "rollup -c",
    "dev": "rollup -c -w"
  }
}
```

## NPM Publish Workflow

```bash
# 1. Login to npm
npm login

# 2. Build all packages
npm run build

# 3. Version bump
npm run version

# 4. Publish packages
npm run publish:all

# 5. Tag releases
git tag v1.0.0
git push --tags
```

## Package Features Matrix

| Package | Version | Description | Size |
| --- | --- | --- | --- |
| `@matrixlang/core` | 1.0.0 | Core runtime & compiler | ~250KB |
| `@matrixlang/cli` | 1.0.0 | Command-line interface | ~5MB |
| `@matrixlang/browser` | 1.0.0 | Browser runtime | ~150KB |
| `@matrixlang/vscode` | 1.0.0 | VS Code extension | ~2MB |
| `create-matrix-app` | 1.0.0 | Project scaffolding | ~1MB |

This npm package structure provides:
1. **Modular design** - Separate packages for different concerns
2. **Tree-shakeable** - ES modules for optimal bundling
3. **Universal runtime** - Works in Node.js, browser, edge
4. **TypeScript support** - Full type definitions
5. **CLI tools** - Developer-friendly commands
6. **Extensible** - Plugin system for language integrations

Ready to publish and revolutionize runtime execution! 🚀
# MATRIX - Distributed PWA + Python Agent + PHP Broker Starter Kit

This repository contains a deployable starter kit for a distributed task runner:

- **PHP broker API** on shared hosting (task queue + agent registry).
- **Local Python agent** that polls the broker and can execute commands locally.
- **PWA UI** that submits tasks to the broker.

## Directory Layout

- `api/` - PHP broker endpoints and MySQL schema.
- `agent/` - Python agent and requirements.
- `pwa/` - Simple HTML UI for submitting tasks.

## PHP Broker Setup

1. Create a MySQL database and user.
2. Run the schema in `api/schema.sql`.
3. Upload the contents of `api/` to your shared hosting (e.g., `/public_html/api`).
4. Configure environment variables for your hosting provider:

```
DB_HOST=localhost
DB_NAME=matrix_broker
DB_USER=matrix_user
DB_PASS=matrix_pass
API_KEY=optional_shared_key
```

If `API_KEY` is set, clients must send it in the `X-API-KEY` header.

### Endpoints

- `POST /add_task.php`
- `GET /get_task.php?agent=Agent1`
- `POST /submit_result.php`
- `GET /agents.php`

## Python Agent Setup

1. Create a virtual environment and install requirements:

```
python -m venv .venv
. .venv/bin/activate
pip install -r agent/requirements.txt
```

2. Run the agent:

```
export BROKER_URL="https://yourdomain.com/api"
export AGENT_NAME="Agent1"
export API_KEY="optional_shared_key"
python agent/agent.py
```

The agent exposes a local endpoint at `http://127.0.0.1:5001/run` for direct PWA calls.

## PWA

Open `pwa/index.html` in a browser (or host it alongside the API). Update the broker URL and optional API key, then submit tasks.

## Security Notes

- Use HTTPS everywhere.
- Keep API keys private.
- Restrict what commands your agent is allowed to execute.
