---
name: Pods
description: Work with Pods custom content types, fields, and relationships.
enable_prompt: true
enable_agentic: true
---

## Pods on this site

Pods (`PODS_VERSION`) manages custom content types beyond core post types.

## Workflow

1. Load a pod with `pods( 'pod_name', $id )` for reads.
2. Use `$pod->save()` for updates with field slugs from the pod config.
3. Relationship fields need valid target IDs.

Inspect pod configuration before bulk imports or migrations.
