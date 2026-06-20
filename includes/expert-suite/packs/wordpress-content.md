---
name: WordPress Content
description: Create, update, and delete WordPress posts and pages using Agent2Wp content abilities.
enable_prompt: true
enable_agentic: true
---

## Content abilities

Use these MCP tools when managing posts and pages:

- `agent2wp/create-post` — create a draft or published post/page
- `agent2wp/get-post` — fetch title, content, status, and permalink by ID
- `agent2wp/list-posts` — search and paginate posts/pages
- `agent2wp/update-post` — change title, content, status, or slug
- `agent2wp/delete-post` — trash or permanently delete

## Workflow

1. Prefer `draft` status until the user confirms publish.
2. After creating content, return the `edit_url` and `permalink` from the tool response.
3. For bulk edits, update one post at a time and verify each result.
