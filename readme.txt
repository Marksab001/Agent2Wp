=== Agent2Wp ===
Contributors: taiburrahaman
Tags: mcp, model-context-protocol, ai, ai-agents, agent, wordpress, wordpress-plugin, automation, cursor, claude, wp-cli, elementor, gutenberg, woocommerce, php, artificial-intelligence
Requires at least: 6.9
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 2.0.0
License: Agent2Wp-RSL-1.0
License URI: https://github.com/Taibur-Rahaman/Agent2Wp/blob/main/COPYRIGHT.md

WordPress MCP plugin — connect Claude, Cursor, and AI agents to your site. PHP execution, WP-CLI, filesystem, posts, Elementor skills. Expert Suite included.

== Description ==

**Agent2Wp** is a WordPress MCP (Model Context Protocol) plugin that lets AI assistants — Claude Desktop, Cursor, Copilot, VS Code, and other MCP clients — control your WordPress site securely via application passwords.

Install from [GitHub Releases](https://github.com/Taibur-Rahaman/Agent2Wp/releases/latest), activate, click **Start Agent2Wp**, and paste the MCP config into your AI client. No SaaS proxy; all requests hit your server.

**Expert Suite is included** — no license keys, no upsells, no tier gating.

= Capabilities =

* Execute PHP in a controlled WordPress context
* Read, write, edit, and delete files within the configured base directory
* Run WP-CLI commands
* Create, read, update, delete, and list posts and pages
* Gutenberg content tools with Block Editor Queue for native blocks
* Skills engine with built-in guides and Expert Suite specializations
* Persistent agent context via Agent2Wp → Context

= Requirements =

* WordPress 6.9 or later
* PHP 8.0 or later
* Staging or development environment (not recommended for production)

= Copyright =

DO NOT COPY without permission. Agent2Wp original code is under the Agent2Wp Restricted Source License v1.0. See COPYRIGHT.md in the plugin package. Commercial use requires a separate license.

= Attribution =

Portions of this software are derived from [Novamira](https://github.com/use-novamira/novamira) (Ovation S.r.l.), licensed under AGPL-3.0-or-later (LICENSE-AGPL-3.0.txt). See NOTICE in the plugin package.

== Installation ==

1. Download the latest **`agent2wp-x.x.x.zip`** from [GitHub Releases](https://github.com/Taibur-Rahaman/Agent2Wp/releases/latest) (not the repository "Code" ZIP).
2. In WordPress go to **Plugins → Add New → Upload Plugin**, choose the release ZIP, and activate **Agent2Wp**.
3. Open **Agent2Wp → Start** and click **Start Agent2Wp**.
4. Copy the generated MCP configuration into your AI client.

== Frequently Asked Questions ==

= Is this safe for production sites? =

No. Agent2Wp is intended for staging and development. When AI Abilities are enabled, authenticated agents can execute PHP and access the filesystem.

= Do I need a license key? =

No. Expert Suite capabilities are bundled and active by default.

= Which MCP endpoint should I use? =

`https://yoursite.test/wp-json/mcp/agent2wp`

== Screenshots ==

1. Easy Mode start screen — one-click setup
2. Configuration page with MCP client samples
3. Abilities Hub — enable or disable individual abilities
4. Expert Suite status page

== Changelog ==

= 2.0.0 =
* Agent2Wp rebrand with Expert Suite built in
* One-click Easy Mode setup
* Site rulesbook and specialization packs for Elementor, XPRO, WPForms, and more
* Full content lifecycle abilities for posts and pages

== Upgrade Notice ==

= 2.0.0 =
Major release with built-in Expert Suite and Easy Mode setup.
