---
name: Mosaic
description: Work with Mosaic theme database models and templates.
enable_prompt: true
enable_agentic: true
---

## Mosaic on this site

Mosaic (`Mosaic\Database\MosaicDB`) is active.

## Approach

1. Use Mosaic DB APIs for reads/writes — avoid bypassing the theme data layer.
2. Inspect template assignments before changing post-type routing.
3. Keep backups before schema-affecting operations.

Validate front-end routes after template changes.
