---
name: WPBakery Page Builder
description: Edit WPBakery (Visual Composer) shortcodes and backend layouts.
enable_prompt: true
enable_agentic: true
---

## WPBakery on this site

WPBakery (`WPB_VC_VERSION`) stores layouts as shortcodes in post content.

## Approach

1. Parse existing `[vc_row]` / `[vc_column]` / `[vc_*]` shortcodes before editing.
2. Preserve attribute quoting and nested structure — malformed shortcodes break rendering.
3. Prefer `vc_map` element names when adding new modules.

Test rendered output after shortcode changes.
