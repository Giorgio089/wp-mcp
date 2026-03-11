/**
 * Config loader — reads ~/.mcp-bridge.json or a path from MCP_BRIDGE_CONFIG env var.
 */
import type { BridgeConfig, SiteConfig } from './types.js';
export declare function loadConfig(): BridgeConfig;
export declare function getSite(siteKey?: string): {
    key: string;
    config: SiteConfig;
};
export declare function listSites(): Array<{
    key: string;
    url: string;
    label?: string;
}>;
//# sourceMappingURL=config.d.ts.map