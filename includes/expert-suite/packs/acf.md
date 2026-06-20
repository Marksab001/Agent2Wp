---
name: Advanced Custom Fields
description: Read and write ACF field groups and values for posts, terms, and options on this site.
enable_prompt: true
enable_agentic: true
---

## ACF on this site

Advanced Custom Fields is active.

## Reading fields

- Post fields: `get_field( 'field_name', $post_id )`
- Option fields: `get_field( 'field_name', 'option' )`
- Field keys vs names: prefer field names in templates; use keys when updating programmatically.

## Writing fields

- `update_field( 'field_name', $value, $post_id )`
- For repeaters/flexible content, match the array structure ACF expects.

## Field groups

- Inspect registered groups with `acf_get_field_groups()` and `acf_get_fields( $group_key )`.
- Do not rename field keys on live data without a migration plan.

Validate field values against field type (image ID vs URL, relationship post IDs, etc.).
