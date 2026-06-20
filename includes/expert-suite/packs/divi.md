---
name: Divi Builder
description: Edit Divi layouts, modules, and theme builder templates on this site.
enable_prompt: true
enable_agentic: true
---

## Divi on this site

Divi (`ET_BUILDER_VERSION`) is active. Layout data is stored in post meta and the Divi library.

## Approach

1. Inspect `_et_pb_use_builder`, `_et_pb_old_content`, and builder JSON before editing.
2. Prefer Divi APIs (`ET_Builder_Element`, `et_pb_*` helpers) over raw meta surgery.
3. Clear static CSS cache after module structure changes.

Work on drafts or duplicated layouts before touching live pages.
