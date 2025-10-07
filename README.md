# PHP MCP Monorepo

Framework-agnostic **Model Context Protocol** client and server for PHP.

## Packages
- `mcp-core` — protocol types, JSON-RPC framing, errors, capabilities.
- `mcp-client` — high-level client SDK.
- `mcp-server` — minimal server runtime and router.
- `mcp-transport-stdio` — stdio transport (duplex).
- `mcp-transport-streamhttp` — streamable HTTP transport.
- `mcp-codec-json` — JSON codec and DTO mapping.

## Quick Start (local dev)

```bash
docker run --rm \
  -u $(id -u):$(id -g) \
  -v $PWD:/app \
  -v $HOME/.composer:/tmp/composer-cache \
  -w /app \
  composer:2 \
  composer install --no-interaction --prefer-dist --optimize-autoloader
```

Stdio run example
```bash
docker run --rm -i \                                                                                  127 ↵
  -v "$PWD":/work -w /work \
  php:8.4-cli php examples/stdio-echo-server.php
```

## License
MIT

## MCP Inspector

```bash
docker run --rm \
  -e HOST=0.0.0.0 -e DANGEROUSLY_OMIT_AUTH=true \
  -p 6274:6274 -p 6277:6277 \
  ghcr.io/modelcontextprotocol/inspector:latest
```
