---
name: Session Memory
description: Persist site facts and preferences in Agent2Wp Context so the agent remembers them between sessions.
enable_prompt: true
enable_agentic: true
---

## Memory via Context

Agent2Wp Context (admin → Agent2Wp → Context) prepends persistent instructions to every MCP session.

Store durable facts here:

- Active theme, page builder, and key plugins (Elementor, XPRO, WPForms)
- Site rulesbook artifacts: primary menu ID, homepage page ID, XPRO header/footer template IDs, WPForms contact form ID
- Naming conventions for CPTs, fields, and CSS classes
- Staging vs production URLs
- User preferences (tone, layout patterns, brand colors)

## When to update Context

After learning something the user confirms should be remembered, suggest updating Context with a concise bullet list. Do not store secrets (passwords, API keys, license keys).

Use `agent2wp/skill-get` with slug `memory` only when the user asks about remembering things across sessions.
