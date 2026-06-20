---
name: Meta Box
description: Read and write Meta Box custom fields and field groups.
enable_prompt: true
enable_agentic: true
---

## Meta Box on this site

Meta Box (`RWMB_VER`) registers field groups attached to posts, terms, or settings pages.

## Reading and writing

- `rwmb_meta( 'field_id', [], $post_id )` for values
- `rwmb_set_meta( $post_id, 'field_id', $value )` when available
- Inspect field definitions with `rwmb_get_registry( 'meta_box' )`

Match field types (file, image, group, clone) when constructing update arrays.
