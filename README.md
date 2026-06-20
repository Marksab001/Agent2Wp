# Agent2Wp

> ### ⚠️ DO NOT COPY
>
> **This repository is protected by copyright.** Unauthorized copying, redistribution, rebranding, commercial use, or SaaS deployment is **prohibited** without written permission.
>
> Read **[COPYRIGHT.md](COPYRIGHT.md)** before cloning or forking. Commercial licensing: [open an issue](https://github.com/Taibur-Rahaman/Agent2Wp/issues).

**Expert MCP bridge for WordPress** — give AI agents programmatic control over your site through the Model Context Protocol.

Developed by **[Taibur Rahaman](https://github.com/Taibur-Rahaman)**

## Download (WordPress install)

**[⬇ Download latest release ZIP](https://github.com/Taibur-Rahaman/Agent2Wp/releases/latest)** — ready to upload in wp-admin. No Composer or build step required.

1. Open **[Releases](https://github.com/Taibur-Rahaman/Agent2Wp/releases/latest)** and download `agent2wp-x.x.x.zip`.
2. In WordPress: **Plugins → Add New → Upload Plugin** → choose the ZIP → **Install Now** → **Activate**.
3. Open **Agent2Wp → Start** and click **Start Agent2Wp**.

> **Important:** Do **not** use GitHub’s **Code → Download ZIP** on the repository page. That source archive has no `vendor/` folder and the plugin will not work. Always use the **Releases** ZIP (built automatically with all dependencies).

Each push to `main` rebuilds the release ZIP for the version in `agent2wp.php`.

## Why Agent2Wp

Agent2Wp connects Cursor, Claude, Copilot, or any MCP client directly to WordPress. Agents can execute PHP, edit files, run WP-CLI, manage posts, and load specialization skills — all authenticated on your server. Nothing is proxied through a third-party SaaS.

**Expert Suite is included.** No license keys, no upsells, no tier gating.

## Expert Suite (built-in)

| Capability | What you get |
|------------|--------------|
| **Specializations** | Skills for Elementor, Bricks, Divi, WooCommerce, ACF, JetEngine, and 10+ more — shown only when the plugin is active |
| **Content tools** | Create, read, update, delete, and list posts/pages via MCP |
| **Session memory** | Persistent agent context via **Agent2Wp → Context** |
| **Skills engine** | User skills, built-in guides, and Expert packs in one catalog |

Check status anytime under **Agent2Wp → Expert Suite**.

## Quick start (2 clicks)

1. Activate **Agent2Wp** — setup can run automatically in the background
2. Open **Agent2Wp → Start** (or use the Dashboard widget)
3. **Start Agent2Wp** — animated progress, everything turns on automatically
4. **Copy for AI** — auto-copies on success; paste into Claude or Cursor

Site rules (Elementor, XPRO, WPForms) are loaded into agent memory automatically.

## Requirements

- WordPress 6.9+
- PHP 8.0+
- Staging or development environment (not recommended for production)

## License

| Component | License |
|-----------|---------|
| Agent2Wp original code | [Agent2Wp Restricted Source License v1.0](LICENSE) — **do not copy without permission** |
| Novamira-derived portions | [AGPL-3.0-or-later](LICENSE-AGPL-3.0.txt) |
| Bundled dependencies | See [NOTICE](NOTICE) |

Full copyright warning: **[COPYRIGHT.md](COPYRIGHT.md)**

## Development

```sh
composer install
make mago-format
make mago-lint
make mago-analyze
```
