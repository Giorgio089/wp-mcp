# Changelog & Implementierungs-Dokumentation (11.03.2026)

## Erledigte Arbeitsschritte: "MCP Bridge" Projekt Setup

### 1. Architektur-Design & Analyse
*   **Analyse der MCP-Spezifikation:** Evaluierung des Model Context Protocol (MCP) und der stdio-Transportmechanismen.
*   **Architektur-Planung:** Entwurf einer zweiteiligen Architektur ("Lean Bridge") bestehend aus einem lokalen Node.js-Client und einem remote WordPress-Plugin zur Umgehung von serverseitigen Restriktionen (Firewalls, SSE-Blocker).
*   **Sicherheitskonzept:** Festlegung von WordPress Application Passwords (HTTP Basic Auth) für native, tokenlose Authentifizierung ohne Bloat.

### 2. WordPress Plugin Entwicklung (Host)
Das Plugin wurde vollständig im Ordner `wordpress-plugin` implementiert und bietet folgende Features:
*   **REST-API Endpunkt:** Registrierung von `/wp-json/mcp-bridge/v1/execute` als zentraler JSON-RPC Befehlsempfänger.
*   **Sicherheits-Infrastruktur:**
    *   `Authenticator`: Erzwingt HTTPS, validiert Application Passwords und prüft Admin-Rechte (`manage_options`).
    *   `Rate Limiter`: Schützt vor API-Abuse via WP-Transients (Standard: 60 Requests/Min).
    *   `IP-Whitelist`: Optionale Beschränkung der Zugriffe auf bekannte IPs.
*   **5 Kern-Tools (Actor Handlers):**
    *   `wp_site_health`: Liefert Systeminfos (WP/PHP Version), DB-Status, und HTTP-Liveness-Check.
    *   `wp_query_db`: Lesezugriff (sowie optionaler Schreibzugriff) auf die Datenbank via SQL. Mit hartem Limit (max 500 Rows) für Stabilität.
    *   `wp_manage_plugins`: Schnittstelle zum Auflisten, Aktivieren, Deaktivieren und Aktualisieren von Plugins.
    *   `wp_file_system`: Kontrollierter Zugriff (Read/Write/List) auf Theme- und Plugin-Dateien. *Sicherheitsfunktion: Automatisches Backup vor Schreibvorgängen; blockiert sicherheitskritische Dateien (z.B. wp-config.php).*
    *   `wp_run_cli`: Ausführen von WP-CLI Kommandos. *Sicherheitsfunktion: Befehle wie `eval` sind permanent blockiert; destruktive Commands (z.B. `db drop`) erfordern ein explizites `confirm: true` Flag.*
*   **Admin-Interface:** Erstellung einer Einstellungsseite (`Einstellungen > MCP Bridge`) zur flexiblen Konfiguration (Tool-Toggles, Security-Settings, Logging-Ansicht).
*   **Logging:** Proprietäre Datenbanktabelle `wp_mcp_bridge_log` zur Protokollierung aller Request/Response-Daten.

### 3. Lokaler MCP-Client Entwicklung (Node.js/TypeScript)
Der lokale Interpreter wurde im Root-Verzeichnis (`src/index.ts`) implementiert:
*   **Projekt-Setup:** Initialisierung mit `@modelcontextprotocol/sdk`, Typescript (`tsconfig.json`) und Zod für Schema-Validierung.
*   **Tool-Definitionen:** Alle 5 WP-Tools sowie das Hilfs-Tool `wp_list_sites` wurden als MCP-Tools registriert und mit den entsprechenden Parametern (Zod-Schemas) versehen.
*   **Config-Loader:** Implementierung eines Parsers für `~/.mcp-bridge.json` zur Verwaltung mehrerer WP-Instanzen (Fleet Management).
*   **HTTP-Client:** Ein spezieller `WpClient`, der die JSON-RPC Befehle kapselt, mit dem Application Password signiert und an das WordPress Plugin sendet.
*   **Kompilierung:** Erfolgreicher Build via `npm run build` nach `dist/index.js`.

---
**Status:** Repositorium bereit für Initital-Push zu GitHub. Live-Test in WordPress steht aus.
