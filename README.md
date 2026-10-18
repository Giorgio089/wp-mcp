# MCP Bridge for WordPress

> Control your WordPress site with natural language — directly from Claude Code, Cursor, Codex and other AI tools.

[![npm version](https://img.shields.io/npm/v/mcp-server-wp.svg)](https://www.npmjs.com/package/mcp-server-wp)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)

---

## What is this?

**MCP Bridge** consists of two parts that work together:

| Part | What it does |
|------|-------------|
| 🔌 **WordPress Plugin** ([leanplugins.dev](https://leanplugins.dev/wp-mcp-bridge)) | Exposes a secure REST API on your WordPress site |
| ⚡ **This npm package** | Local MCP server that connects your AI tool to the REST API |

Once set up, you can tell your AI agent things like:

- *"List all active plugins on my site"*
- *"Deactivate the Hello Dolly plugin"*
- *"Show me the site health status"*
- *"Run `wp cache flush` on my server"*
- *"What plugins have pending updates?"*

---

## Requirements

- WordPress 6.0+ with the [MCP Bridge Plugin](https://leanplugins.dev/wp-mcp-bridge) installed & activated
- Node.js 18+
- An AI tool that supports MCP (Claude Code, Cursor, Windsurf, etc.)

---

## Setup (3 Steps)

### Step 1 — Install & activate the WordPress plugin

Download the **MCP Bridge Plugin** from [leanplugins.dev](https://leanplugins.dev/wp-mcp-bridge) and install it in your WordPress backend.

After activation you'll see a new menu item under **Settings → MCP Bridge**.

---

### Step 2 — Create a WordPress Application Password

In your WordPress admin:

```
WP Admin → Users → Your Profile → scroll to "Application Passwords"
→ Enter a name e.g. "Claude Code"
→ Click "Add New Application Password"
→ Copy the generated password (shown only once!)
   Example: "AbCd EfGh IjKl MnOp QrSt UvWx"
```

---

### Step 3 — Create your config file

Create `~/.mcp-bridge.json` on your local machine:

```json
{
  "sites": {
    "my-site": {
      "url": "https://your-wordpress-site.com",
      "username": "your-wp-username",
      "applicationPassword": "AbCd EfGh IjKl MnOp QrSt UvWx",
      "label": "My WordPress Site"
    }
  },
  "defaultSite": "my-site"
}
```

> **Multiple sites?** Just add more entries to the `sites` object and reference them with the `site` parameter in your prompts.

---

## Connecting to your AI tool

### Claude Code

```bash
claude mcp add wordpress -- npx -y mcp-server-wp
```

Then restart Claude Code. That's it! 🎉

---

### Cursor / Windsurf

Add to your `~/.cursor/mcp.json` (or `~/.windsurf/mcp.json`):

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": ["-y", "mcp-server-wp"]
    }
  }
}
```

---

### Claude Desktop

Add to `~/Library/Application Support/Claude/claude_desktop_config.json` (macOS):

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": ["-y", "mcp-server-wp"]
    }
  }
}
```

---

## Available Tools

Once connected, your AI agent has access to these tools:

| Tool | Description | Risk |
|------|-------------|------|
| `wp_site_health` | WP version, PHP info, DB stats, HTTP status, plugin updates | ✅ Safe |
| `wp_query_db` | Execute SQL queries (SELECT by default, writes opt-in) | ⚠️ Medium |
| `wp_manage_plugins` | List, activate, deactivate, update plugins | ⚠️ Medium |
| `wp_run_cli` | Execute WP-CLI commands on your server | 🔴 High Risk |
| `wp_file_system` | Read/write theme & plugin files | 🔴 High Risk |

> High-risk tools are **disabled by default**. Enable them explicitly in **Settings → MCP Bridge** in your WP Admin.

---

## Example Prompts

```
"Show me the health status of my WordPress site"
"Which plugins are inactive?"
"Update all plugins on my site"
"Flush the WordPress cache"
"Read the functions.php of my active theme"
"Run wp user list on my site"
```

---

## Security

- Authentication via **WordPress Application Passwords** (WP core feature, no third-party auth)
- All requests require `manage_options` capability (admin only)
- **Rate limiting**, **IP whitelist** and **HTTPS enforcement** configurable in plugin settings
- High-risk tools (WP-CLI, File System) are **off by default**
- Full **request logging** in the WP database

---

## Multi-Site Example

```json
{
  "sites": {
    "production": {
      "url": "https://my-shop.com",
      "username": "admin",
      "applicationPassword": "xxxx xxxx xxxx xxxx xxxx xxxx",
      "label": "Production Shop"
    },
    "staging": {
      "url": "https://staging.my-shop.com",
      "username": "admin",
      "applicationPassword": "yyyy yyyy yyyy yyyy yyyy yyyy",
      "label": "Staging"
    }
  },
  "defaultSite": "staging"
}
```

Then in your AI tool:
```
"List plugins on my production site"
"Run cache flush on staging"
```

---

## Links

- 🔌 **WordPress Plugin**: [leanplugins.dev/wp-mcp-bridge](https://leanplugins.dev/wp-mcp-bridge)
- 📦 **npm Package**: [npmjs.com/package/mcp-server-wp](https://www.npmjs.com/package/mcp-server-wp)
- 🐛 **Issues**: [github.com/leanplugins/mcp-server-wp](https://github.com/leanplugins/mcp-server-wp/issues)

---

## License

MIT © [LeanPlugins](https://leanplugins.dev)
