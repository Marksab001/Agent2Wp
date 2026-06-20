---
name: ACPT
description: Manage ACPT custom post types, taxonomies, and meta on this site.
enable_prompt: true
enable_agentic: true
---

## ACPT on this site

AC Custom Post Types (`ACPT_PLUGIN_VERSION`) registers structured content models.

## Workflow

1. Discover registered entities via ACPT APIs before creating records.
2. Respect field types and validation rules defined in ACPT admin.
3. Store stable slugs in Agent2Wp Context for recurring tasks.

Do not rename machine keys on production without a migration plan.
