/**
 * Shared TypeScript types for the MCP Bridge server.
 */

export interface SiteConfig {
  url: string;
  username: string;
  applicationPassword: string;
  /** Optional label for display. Defaults to the key name. */
  label?: string;
}

export interface BridgeConfig {
  sites: Record<string, SiteConfig>;
  /** Default site key to use when no --site flag is provided. */
  defaultSite?: string;
}

export interface WpApiResponse<T = unknown> {
  jsonrpc: '2.0';
  id: string;
  result?: T;
  error?: {
    code: string;
    message: string;
    data?: Record<string, unknown>;
  };
}

// ---- Tool parameter shapes ----

export interface SiteHealthParams {
  site?: string;
}

export interface QueryDbParams {
  site?: string;
  sql: string;
  limit?: number;
}

export interface ManagePluginsParams {
  site?: string;
  action: 'list' | 'activate' | 'deactivate' | 'update';
  plugin?: string;
  status?: 'all' | 'active' | 'inactive';
}

export interface RunCliParams {
  site?: string;
  command: string;
  confirm?: boolean;
}

export interface FileSystemParams {
  site?: string;
  action: 'read' | 'write' | 'list';
  path: string;
  contents?: string;
  confirm?: boolean;
}
