#!/usr/bin/env node
/**
 * MCP Bridge Server — main entry point.
 * Connects AI agents to WordPress via the Model Context Protocol (stdio transport).
 */

import { McpServer } from '@modelcontextprotocol/sdk/server/mcp.js';
import { StdioServerTransport } from '@modelcontextprotocol/sdk/server/stdio.js';
import { z } from 'zod';
import { getSite, listSites } from './config.js';
import { WpClient } from './wp-client.js';

const server = new McpServer({
  name: 'mcp-server-wp',
  version: '1.0.0',
});

// ── Shared site parameter (present in all tools) ──────────────────────────────
const siteParam = z
  .string()
  .optional()
  .describe(
    'Site key from your ~/.mcp-bridge.json config. Omit to use the default site.'
  );

function client(siteKey?: string): WpClient {
  const { config } = getSite(siteKey);
  return new WpClient(config);
}

// ── Tool: list_sites ──────────────────────────────────────────────────────────
server.tool(
  'wp_list_sites',
  'List all WordPress sites configured in ~/.mcp-bridge.json',
  {},
  async () => {
    const sites = listSites();
    return {
      content: [
        {
          type: 'text',
          text: JSON.stringify({ sites }, null, 2),
        },
      ],
    };
  }
);

// ── Tool: wp_site_health ──────────────────────────────────────────────────────
server.tool(
  'wp_site_health',
  'Get a comprehensive health overview of a WordPress site: WP version, PHP info, DB stats, HTTP liveness check, and plugin update count.',
  { site: siteParam },
  async ({ site }) => {
    const result = await client(site).callTool('wp_site_health', {});
    return { content: [{ type: 'text', text: JSON.stringify(result, null, 2) }] };
  }
);

// ── Tool: wp_query_db ─────────────────────────────────────────────────────────
server.tool(
  'wp_query_db',
  'Execute SQL queries against the WordPress database. Returns up to 500 rows for SELECT. Write operations require explicit opt-in in plugin settings.',
  {
    site: siteParam,
    sql: z.string().describe('SQL query to execute. Use wp_ prefix for tables.'),
    limit: z
      .number()
      .int()
      .min(1)
      .max(500)
      .optional()
      .default(100)
      .describe('Max rows for SELECT. Default 100, max 500.'),
  },
  async ({ site, sql, limit }) => {
    const result = await client(site).callTool('wp_query_db', { sql, limit });
    return { content: [{ type: 'text', text: JSON.stringify(result, null, 2) }] };
  }
);

// ── Tool: wp_manage_plugins ───────────────────────────────────────────────────
server.tool(
  'wp_manage_plugins',
  'List, activate, deactivate, or update WordPress plugins. Use action=update with plugin="all" to update all plugins at once.',
  {
    site: siteParam,
    action: z
      .enum(['list', 'activate', 'deactivate', 'update'])
      .describe('Action to perform.'),
    plugin: z
      .string()
      .optional()
      .describe('Plugin file (e.g. "akismet/akismet.php"). Use "all" for update action.'),
    status: z
      .enum(['all', 'active', 'inactive'])
      .optional()
      .default('all')
      .describe('Filter for list action.'),
  },
  async ({ site, action, plugin, status }) => {
    const result = await client(site).callTool('wp_manage_plugins', {
      action,
      plugin,
      status,
    });
    return { content: [{ type: 'text', text: JSON.stringify(result, null, 2) }] };
  }
);

// ── Tool: wp_run_cli ──────────────────────────────────────────────────────────
server.tool(
  'wp_run_cli',
  'Execute WP-CLI commands on the remote WordPress server. Destructive commands require confirm=true. eval/shell commands are permanently blocked.',
  {
    site: siteParam,
    command: z
      .string()
      .describe(
        'WP-CLI command WITHOUT the leading "wp". Examples: "plugin list", "cache flush", "user list --format=json"'
      ),
    confirm: z
      .boolean()
      .optional()
      .default(false)
      .describe('Set to true to confirm destructive commands.'),
  },
  async ({ site, command, confirm }) => {
    const result = await client(site).callTool('wp_run_cli', { command, confirm });
    return { content: [{ type: 'text', text: JSON.stringify(result, null, 2) }] };
  }
);

// ── Tool: wp_file_system ──────────────────────────────────────────────────────
server.tool(
  'wp_file_system',
  'Read, write, or list files within WordPress themes, plugins, mu-plugins, and uploads directories. Write operations require confirm=true and auto-create a backup.',
  {
    site: siteParam,
    action: z.enum(['read', 'write', 'list']).describe('File system action.'),
    path: z
      .string()
      .describe(
        'Absolute or WP-content-relative path. E.g. "themes/my-theme/functions.php"'
      ),
    contents: z
      .string()
      .optional()
      .describe('New file contents (required for write action).'),
    confirm: z
      .boolean()
      .optional()
      .default(false)
      .describe('Must be true for write operations.'),
  },
  async ({ site, action, path, contents, confirm }) => {
    const result = await client(site).callTool('wp_file_system', {
      action,
      path,
      contents,
      confirm,
    });
    return { content: [{ type: 'text', text: JSON.stringify(result, null, 2) }] };
  }
);

// ── Start server ──────────────────────────────────────────────────────────────
async function main() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  // Log to stderr (not stdout) to avoid polluting the MCP stdio channel.
  process.stderr.write('[MCP Bridge] Server started. Waiting for connections via stdio.\n');
}

main().catch((err) => {
  process.stderr.write(`[MCP Bridge] Fatal error: ${err.message}\n`);
  process.exit(1);
});
