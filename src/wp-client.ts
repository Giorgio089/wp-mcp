/**
 * WordPress REST API HTTP client.
 * Translates MCP tool calls into authenticated HTTP POST requests.
 */

import type { SiteConfig, WpApiResponse } from './types.js';

export class WpClient {
  private readonly baseUrl: string;
  private readonly authHeader: string;

  constructor(site: SiteConfig) {
    // Normalise URL — strip trailing slash.
    this.baseUrl = site.url.replace(/\/$/, '');

    // WP Application Password: Basic Auth with "username:password"
    const credentials = `${site.username}:${site.applicationPassword}`;
    this.authHeader = `Basic ${Buffer.from(credentials).toString('base64')}`;
  }

  /**
   * Call a tool on the remote WordPress MCP Bridge plugin.
   */
  async callTool<T = unknown>(
    tool: string,
    params: Record<string, unknown> = {},
    requestId = crypto.randomUUID()
  ): Promise<T> {
    const endpoint = `${this.baseUrl}/wp-json/mcp-bridge/v1/execute`;

    const body = {
      tool,
      params,
      id: requestId,
    };

    let response: Response;
    try {
      response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: this.authHeader,
          'User-Agent': 'MCPBridgeServer/1.0',
        },
        body: JSON.stringify(body),
      });
    } catch (err) {
      throw new Error(
        `Network error connecting to ${this.baseUrl}: ${(err as Error).message}`
      );
    }

    if (!response.ok) {
      let detail = '';
      try {
        const errBody = await response.json() as { message?: string; code?: string };
        detail = errBody.message ?? errBody.code ?? '';
      } catch { /* ignore parse errors */ }

      throw new Error(
        `WordPress API error ${response.status}${detail ? `: ${detail}` : ''}`
      );
    }

    const data = await response.json() as WpApiResponse<T>;

    if (data.error) {
      throw new Error(`WP Bridge error [${data.error.code}]: ${data.error.message}`);
    }

    return data.result as T;
  }

  /**
   * Fetch the list of enabled tools from the remote site.
   */
  async getAvailableTools(): Promise<Array<{ name: string; description: string }>> {
    const endpoint = `${this.baseUrl}/wp-json/mcp-bridge/v1/tools`;

    const response = await fetch(endpoint, {
      headers: { Authorization: this.authHeader },
    });

    if (!response.ok) {
      throw new Error(`Failed to fetch tools: ${response.status}`);
    }

    const data = await response.json() as { tools: Array<{ name: string; description: string }> };
    return data.tools ?? [];
  }
}
