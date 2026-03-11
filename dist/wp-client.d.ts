/**
 * WordPress REST API HTTP client.
 * Translates MCP tool calls into authenticated HTTP POST requests.
 */
import type { SiteConfig } from './types.js';
export declare class WpClient {
    private readonly baseUrl;
    private readonly authHeader;
    constructor(site: SiteConfig);
    /**
     * Call a tool on the remote WordPress MCP Bridge plugin.
     */
    callTool<T = unknown>(tool: string, params?: Record<string, unknown>, requestId?: `${string}-${string}-${string}-${string}-${string}`): Promise<T>;
    /**
     * Fetch the list of enabled tools from the remote site.
     */
    getAvailableTools(): Promise<Array<{
        name: string;
        description: string;
    }>>;
}
//# sourceMappingURL=wp-client.d.ts.map