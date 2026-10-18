# How to Connect Your WordPress Site to Claude Code (and other AI tools)

**Using MCP Bridge for WordPress by [LeanPlugins](https://leanplugins.dev)**

---

This guide walks you through connecting your WordPress site to AI coding tools like Claude Code, Cursor, or Windsurf — so you can manage your site with natural language.

**What you'll be able to do after setup:**
- *"List all active plugins on my site"*
- *"Deactivate Hello Dolly and activate WooCommerce"*
- *"Show me the site health status"*
- *"Flush the WordPress cache"*
- *"Which plugins have pending updates?"*

**Time to complete:** ~10 minutes

---

## What You Need

Before you start, make sure you have:

- ✅ A WordPress site (self-hosted, version 6.0 or higher)
- ✅ Admin access to your WordPress backend
- ✅ [Node.js](https://nodejs.org) installed on your local machine (version 18 or higher)
- ✅ An AI tool that supports MCP: [Claude Code](https://claude.ai/code), [Cursor](https://cursor.sh), or [Windsurf](https://windsurf.ai)

---

## Step 1 — Install the WordPress Plugin

1. Download the **MCP Bridge Plugin** from [leanplugins.dev/wp-mcp-bridge](https://leanplugins.dev/wp-mcp-bridge)
2. In your WordPress backend, go to **Plugins → Add New Plugin → Upload Plugin**
3. Upload the downloaded `.zip` file and click **Install Now**
4. Click **Activate Plugin**

You should now see **MCP Bridge** in your WordPress menu under **Settings → MCP Bridge**.

> 💡 **Verify your endpoint is live** — open your browser and visit:
> `https://your-site.com/wp-json/mcp-bridge/v1/tools`
> You should see a JSON response. If so, the plugin is working correctly.

---

## Step 2 — Enable the Tools You Need

1. Go to **Settings → MCP Bridge** in your WordPress admin
2. Under **Enabled Tools**, check the tools you want to use:

| Tool | What it does | Risk Level |
|------|-------------|------------|
| **Site Health** | WP version, PHP info, DB stats | ✅ Safe |
| **Query DB** | Run SQL queries | ⚠️ Medium |
| **Manage Plugins** | List, activate, deactivate, update plugins | ⚠️ Medium |
| **Run WP-CLI** | Execute WP-CLI commands on your server | 🔴 High Risk |
| **File System** | Read/write theme & plugin files | 🔴 High Risk |

3. Click **Save Settings**

> ⚠️ **Recommendation for most users:** Start with **Site Health** and **Manage Plugins** only. Enable WP-CLI and File System only if you know what you're doing.

---

## Step 3 — Create a WordPress Application Password

This is how the MCP server authenticates with your WordPress site. It's a WordPress core feature — no third-party authentication involved.

1. In your WordPress admin, go to **Users → Your Profile**
2. Scroll all the way down to **Application Passwords**
3. Enter a name — for example: `Claude Code`
4. Click **Add New Application Password**
5. **Copy the generated password immediately** — it looks like this:

```
AbCd EfGh IjKl MnOp QrSt UvWx
```

> ⚠️ **Important:** This password is only shown once. Copy it now and keep it somewhere safe.

---

## Step 4 — Create Your Local Config File

On your local machine, create a file called `.mcp-bridge.json` in your home directory:

**macOS / Linux:**
```bash
nano ~/.mcp-bridge.json
```

**Windows:**
```
C:\Users\YourName\.mcp-bridge.json
```

Paste in the following and fill in your details:

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

| Field | What to enter |
|-------|--------------|
| `url` | Your WordPress site URL (with `https://`) |
| `username` | Your WordPress admin username |
| `applicationPassword` | The password you copied in Step 3 |
| `label` | A friendly name — anything you like |

Save the file.

> 💡 **Multiple sites?** Just add more entries to the `sites` object — see the [multi-site example](#managing-multiple-sites) at the bottom of this guide.

---

## Step 5 — Connect to Your AI Tool

Choose your AI tool below:

---

### Claude Code

Open your terminal and run:

```bash
claude mcp add wordpress -- npx -y mcp-server-wp
```

Restart Claude Code. Done! ✅

---

### Cursor

Add the following to `~/.cursor/mcp.json`:

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

Restart Cursor. Done! ✅

---

### Windsurf

Add the following to `~/.windsurf/mcp.json`:

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

Restart Windsurf. Done! ✅

---

### Claude Desktop

Open the config file:
- **macOS:** `~/Library/Application Support/Claude/claude_desktop_config.json`
- **Windows:** `%APPDATA%\Claude\claude_desktop_config.json`

Add:

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

Restart Claude Desktop. Done! ✅

---

## Step 6 — Test the Connection

Open your AI tool and try one of these prompts:

```
Show me the health status of my WordPress site
List all active plugins
Which plugins have pending updates?
```

If you get a response with your site's data — everything is working! 🎉

---

## Troubleshooting

**"Tool is not enabled or does not exist"**
→ Go to **Settings → MCP Bridge** in your WP Admin and make sure the tool is checked and saved.

**"Authentication required"**
→ Double-check your `username` and `applicationPassword` in `~/.mcp-bridge.json`. Make sure you're using your WP username, not your email address.

**"MCP Bridge requires HTTPS"**
→ Your site must run on HTTPS. If you're testing locally, you can temporarily disable the HTTPS requirement under **Settings → MCP Bridge → Security**.

**"Site not found in config"**
→ Check that the `defaultSite` key in your `~/.mcp-bridge.json` matches exactly one of the keys in `sites`.

**The `/tools` endpoint returns a 404**
→ Go to **Settings → Permalinks** in your WP Admin and click **Save Changes** to flush the rewrite rules.

---

## Managing Multiple Sites

You can manage multiple WordPress sites from a single config file:

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

Then in your AI tool, just specify the site:
```
List all plugins on my production site
Flush the cache on staging
Show site health for production
```

---

## Security Recommendations

- **Never share** your Application Password or your `~/.mcp-bridge.json` file
- Use a **dedicated Application Password** for each AI tool (so you can revoke them individually)
- Keep **WP-CLI** and **File System** tools **disabled** unless actively needed
- Enable the **IP Whitelist** in plugin settings if your IP address is static
- Always keep the **Require HTTPS** option enabled on production sites

---

## Need Help?

- 📖 **Documentation:** [leanplugins.dev/wp-mcp-bridge](https://leanplugins.dev/wp-mcp-bridge)
- 🐛 **Report a bug:** [github.com/leanplugins/mcp-server-wp/issues](https://github.com/leanplugins/mcp-server-wp/issues)
- 💬 **Support:** [hello@leanplugins.dev](mailto:hello@leanplugins.dev)
