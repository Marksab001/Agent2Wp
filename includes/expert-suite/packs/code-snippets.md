---
name: Code Snippets
description: Manage Code Snippets plugin entries safely on this site.
enable_prompt: true
enable_agentic: true
---

## Code Snippets on this site

The Code Snippets plugin is active.

## Safety

1. Prefer `functions` snippets over `content` snippets when possible.
2. Never activate a snippet without reviewing scope (global vs admin vs front-end).
3. Use the plugin API or `$wpdb` against `{prefix}snippets` only when necessary.

Keep snippets idempotent and avoid duplicate hook registrations.
