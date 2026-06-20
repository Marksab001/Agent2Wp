![Agent2Wp — WordPress MCP plugin for AI agents, Claude, and Cursor](assets/banner.png)

# Agent2Wp

**WordPress MCP plugin** — connect **Claude**, **Cursor**, **Copilot**, and any **Model Context Protocol (MCP)** client to your WordPress site. AI agents execute PHP, manage files, run **WP-CLI**, edit posts, and use **Elementor / WooCommerce / Gutenberg** skills — authenticated on your server, no third-party proxy.

[![Latest release](https://img.shields.io/github/v/release/Taibur-Rahaman/Agent2Wp?label=download&sort=semver)](https://github.com/Taibur-Rahaman/Agent2Wp/releases/latest)
[![WordPress](https://img.shields.io/badge/WordPress-6.9%2B-blue?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MCP](https://img.shields.io/badge/Model%20Context%20Protocol-MCP-6366f1)](https://modelcontextprotocol.io/)

Developed by **[Taibur Rahaman](https://github.com/Taibur-Rahaman)**

## Table of contents

- [Download](#download-wordpress-plugin-zip)
- [What is Agent2Wp?](#what-is-agent2wp)
- [Features](#features)
- [Quick start](#quick-start)
- [Connect Claude or Cursor to WordPress](#connect-claude-or-cursor-to-wordpress)
- [Expert Suite](#expert-suite-built-in)
- [Requirements](#requirements)
- [FAQ](#faq)
- [Development](#development)
- [License](#license)

## Download (WordPress plugin ZIP)

**[⬇ Download latest release — `agent2wp-x.x.x.zip`](https://github.com/Taibur-Rahaman/Agent2Wp/releases/latest)**

Ready to upload in wp-admin. No Composer or terminal required.

1. Open **[Releases](https://github.com/Taibur-Rahaman/Agent2Wp/releases/latest)** and download the ZIP.
2. WordPress → **Plugins → Add New → Upload Plugin** → **Install Now** → **Activate**.
3. **Agent2Wp → Start** → **Start Agent2Wp** → **Copy for AI**.

> **Do not** use GitHub **Code → Download ZIP**. That source archive has no bundled `vendor/` folder. Always use the **Releases** ZIP.

## What is Agent2Wp?

Agent2Wp is an **open WordPress automation plugin** built on the **WordPress Abilities API** and **MCP Adapter**. It turns your site into a programmable environment for AI agents:

- **MCP server endpoint** at `/wp-json/mcp/agent2wp`
- **Application password** authentication (same as WordPress REST API)
- **Filesystem, PHP, WP-CLI, and content** abilities exposed as MCP tools
- **Expert Suite** — page-builder and commerce specializations included free

Use it for **staging and development**: let AI build pages, menus, Elementor layouts, forms, and custom functionality without manual copy-paste.

## Features

| Area | What agents can do |
|------|---------------------|
| **MCP bridge** | Native WordPress MCP server; works with Claude Desktop, Cursor, VS Code, Windsurf, and other MCP clients |
| **Code execution** | Run PHP in a controlled WordPress context |
| **Filesystem** | Read, write, edit, and delete files under your configured base directory |
| **WP-CLI** | Sync and async WP-CLI jobs from the agent |
| **Content** | Create, read, update, delete, and list posts and pages |
| **Gutenberg** | Block editor content tools + Block Editor Queue for native blocks |
| **Skills** | Built-in guides, user skills, and Expert Suite packs |
| **Context** | Persistent agent memory via **Agent2Wp → Context** |
| **Easy Mode** | One-click setup — enable abilities, create password, copy MCP config |

## Quick start

1. [Download and install](#download-wordpress-plugin-zip) the release ZIP.
2. Activate **Agent2Wp** — background setup can run automatically.
3. Open **Agent2Wp → Start** (or the Dashboard widget).
4. Click **Start Agent2Wp**, then **Copy for AI**.
5. Paste the config into Claude, Cursor, or your MCP client and reload the session.

Site rules (Elementor, XPRO, WPForms) can be seeded into agent context automatically.

## Connect Claude or Cursor to WordPress

After setup, Agent2Wp gives you a ready-made MCP configuration:

- **Endpoint:** `https://your-site.test/wp-json/mcp/agent2wp`
- **Auth:** WordPress application password (created in Easy Mode)
- **Client examples:** Claude Desktop, Cursor, VS Code, Codex, Zed, OpenCode

Agents discover tools via `discover-abilities`, then call `execute-php`, `read-file`, `create-post`, `skill-get`, and more.

## Expert Suite (built-in)

No license keys. No tier gating. All included in the base plugin.

| Pack | When loaded |
|------|-------------|
| Elementor, Bricks, Divi, Breakdance, WPBakery | Active page builder detected |
| WooCommerce, ACF, JetEngine, Pods, Meta Box | Active plugin detected |
| WPForms, XPRO, site rulesbook | Always / when plugin active |
| WordPress content tools, session memory | Always |

Check **Agent2Wp → Expert Suite** in wp-admin.

## Requirements

- **WordPress** 6.9+ (Abilities API)
- **PHP** 8.0+
- **Environment:** staging or development (not recommended for production)

## FAQ

### How do I connect Claude to WordPress?

Install Agent2Wp from [Releases](https://github.com/Taibur-Rahaman/Agent2Wp/releases/latest), run **Start Agent2Wp**, copy the MCP config, and add it to Claude Desktop (or your MCP client). Reload the session and list tools to verify the connection.

### How do I connect Cursor to WordPress?

Same flow: install the plugin, enable AI Abilities, copy the JSON config from **Agent2Wp → Start**, and add it to Cursor’s MCP settings (`.cursor/mcp.json` or project config).

### What is a WordPress MCP plugin?

An MCP plugin exposes WordPress operations as **Model Context Protocol tools** so AI assistants can act on your site through a standard protocol instead of custom scrapers or brittle browser automation.

### Does Agent2Wp work with Elementor?

Yes. Expert Suite includes an **Elementor** specialization (native widgets, containers, site rules). Agents can load it with `skill-get` when Elementor is active.

### Is Agent2Wp free?

The plugin and Expert Suite are included in the release download. See [LICENSE](LICENSE) and [COPYRIGHT.md](COPYRIGHT.md) for use restrictions.

### Why does the Code ZIP not work?

The repository source does not include the bundled `vendor/` directory. The **[Releases ZIP](https://github.com/Taibur-Rahaman/Agent2Wp/releases/latest)** is the WordPress-ready build with MCP Adapter dependencies included.

## Development

```sh
composer install
make mago-format
make mago-lint
make mago-analyze
./build/build 2.0.0   # WordPress install ZIP → /tmp/agent2wp-2.0.0.zip
```

## License

| Component | License |
|-----------|---------|
| Agent2Wp original code | [Agent2Wp Restricted Source License v1.0](LICENSE) |
| Novamira-derived portions | [AGPL-3.0-or-later](LICENSE-AGPL-3.0.txt) — see [NOTICE](NOTICE) |
| Bundled dependencies | See [NOTICE](NOTICE) |

> **Copyright:** Unauthorized copying, rebranding, or commercial redistribution is prohibited without permission. Read **[COPYRIGHT.md](COPYRIGHT.md)**. Licensing questions: [open an issue](https://github.com/Taibur-Rahaman/Agent2Wp/issues).
