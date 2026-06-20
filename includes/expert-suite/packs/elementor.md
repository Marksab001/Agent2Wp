---
name: Elementor
description: Build and edit Elementor pages using native widgets and Containers only — no Pro, HTML, Atomic, or Inner Sections. Use for page layout and body content (not header/footer; those use XPRO).
enable_prompt: true
enable_agentic: true
---

# Elementor — site body content

Headers and footers belong in **XPRO** (see `xpro` skill). This skill covers **page body** content only.

## Hard rules

| Rule | Action |
|------|--------|
| Native widgets only | Free Elementor widgets; no Elementor Pro widgets |
| No HTML widget | Never use the HTML widget |
| Containers only | All layout via Containers; **no Inner Sections** |
| No Atomic | Disable Atomic Editor; do not use Atomic elements |
| Agent2Wp first | Inspect `_elementor_data` via MCP before editing |

## Layout pattern

```
Container (full width or boxed)
  └─ Container (direction: row, columns)
       ├─ Widget(s) column 1
       └─ Widget(s) column 2
```

Convert any legacy Inner Section to nested Containers before adding new content.

## Allowed native widgets (examples)

Heading, Text Editor, Image, Button, Icon, Spacer, Divider, Video, Icon Box, Image Box, Counter, Progress, Social Icons, Google Maps, Shortcode (WPForms embed only when WPForms widget unavailable).

## Workflow

1. `agent2wp/execute-php` — read `_elementor_data`, `_elementor_edit_mode`, post status.
2. Build or edit on a **draft** page.
3. Use Elementor PHP API when available; avoid corrupting JSON manually.
4. Clear Elementor CSS cache if styles look stale (`\Elementor\Plugin::$instance->files_manager->clear_cache()` when appropriate).
5. Add finished page to the primary menu; confirm homepage is set separately.

## Atomic Editor

Before building, verify Atomic Editor is off and scan existing pages for Atomic widget types. Replace with native widgets.

## Do not

- Build site header or footer here — use XPRO.
- Use Elementor Theme Builder for header/footer when this ruleset applies.
- Publish without front-end preview.
