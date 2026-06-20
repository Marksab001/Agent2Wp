---
name: Breakdance
description: Build and modify Breakdance elements, templates, and global settings.
enable_prompt: true
enable_agentic: true
---

## Breakdance on this site

Breakdance is active. Element trees live in Breakdance post meta and global options.

## Approach

1. Read existing element JSON via Breakdance data helpers before writing.
2. Use `\Breakdance\Data` APIs when available instead of hand-editing serialized trees.
3. Regenerate CSS if visual output drifts after structural edits.

Validate on a duplicate page before publishing layout changes.
