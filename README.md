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

```bash
php examples/stdio-echo-server.php | php examples/stdio-echo-client.php
```

## License
MIT


## Initialize as a Git repo & push

```bash
git init
git add .
git commit -m "chore: bootstrap PHP MCP monorepo"
# set your repo URL:
git remote add origin <YOUR_REPO_URL>
git branch -M main
git push -u origin main
```
