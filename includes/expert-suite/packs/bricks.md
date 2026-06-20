---
name: Bricks
description: Edit Bricks builder templates and page structure on this site.
enable_prompt: true
enable_agentic: true
---

## Bricks on this site

Bricks is active. Page content is stored in post meta (typically `_bricks_page_content_2` or similar depending on version).

## Workflow

1. Read existing Bricks meta for the target post before editing.
2. Use Bricks PHP functions and filters when documented; avoid corrupting serialized JSON.
3. Duplicate pages by copying Bricks meta together with the post record.

Test layout changes in a draft post before replacing production templates.
