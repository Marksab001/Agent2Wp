---
name: JetEngine
description: Manage JetEngine custom post types, meta boxes, relations, and listings.
enable_prompt: true
enable_agentic: true
---

## JetEngine on this site

JetEngine is active via Crocoblock.

## Key APIs

- `jet_engine()` for service access
- Custom post types and taxonomies registered through JetEngine UI
- Meta fields via JetEngine field API

## Workflow

1. Discover registered CPTs and meta keys with `agent2wp/execute-php` before writing.
2. Use JetEngine listing/query APIs for front-end data, not raw SQL.
3. Relation fields require both sides of the relation to exist.

Document new CPT slugs in Agent2Wp Context for future sessions.
