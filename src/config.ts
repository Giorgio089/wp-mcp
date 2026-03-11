/**
 * Config loader — reads ~/.mcp-bridge.json or a path from MCP_BRIDGE_CONFIG env var.
 */

import { readFileSync, existsSync } from 'fs';
import { homedir } from 'os';
import { join } from 'path';
import type { BridgeConfig, SiteConfig } from './types.js';

const DEFAULT_CONFIG_PATH = join(homedir(), '.mcp-bridge.json');

let _config: BridgeConfig | null = null;

export function loadConfig(): BridgeConfig {
  if (_config) return _config;

  const configPath = process.env.MCP_BRIDGE_CONFIG ?? DEFAULT_CONFIG_PATH;

  if (!existsSync(configPath)) {
    throw new Error(
      `MCP Bridge config not found at ${configPath}.\n` +
      `Create it with your WordPress site credentials:\n\n` +
      `{\n` +
      `  "sites": {\n` +
      `    "my-site": {\n` +
      `      "url": "https://example.com",\n` +
      `      "username": "admin",\n` +
      `      "applicationPassword": "xxxx xxxx xxxx xxxx xxxx xxxx"\n` +
      `    }\n` +
      `  }\n` +
      `}`
    );
  }

  try {
    const raw = readFileSync(configPath, 'utf-8');
    _config = JSON.parse(raw) as BridgeConfig;

    if (!_config.sites || typeof _config.sites !== 'object') {
      throw new Error('Config must contain a "sites" object.');
    }

    return _config;
  } catch (err) {
    throw new Error(`Failed to parse config at ${configPath}: ${(err as Error).message}`);
  }
}

export function getSite(siteKey?: string): { key: string; config: SiteConfig } {
  const config = loadConfig();
  const key = siteKey ?? config.defaultSite ?? Object.keys(config.sites)[0];

  if (!key || !config.sites[key]) {
    const available = Object.keys(config.sites).join(', ');
    throw new Error(
      `Site "${key}" not found in config. Available sites: ${available || '(none)'}`
    );
  }

  return { key, config: config.sites[key] };
}

export function listSites(): Array<{ key: string; url: string; label?: string }> {
  const config = loadConfig();
  return Object.entries(config.sites).map(([key, s]) => ({
    key,
    url: s.url,
    label: s.label,
  }));
}
