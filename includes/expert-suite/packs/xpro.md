---
name: XPRO Header and Footer
description: Design site header and footer with the XPRO plugin only — XPRO elements, library templates, global assignment. Use when building or editing headers, footers, or site-wide layout chrome.
enable_prompt: true
enable_agentic: true
---

# XPRO — header and footer

All site-wide header and footer work goes through **XPRO**, not Elementor page bodies.

## Hard rules

1. **XPRO only** for header and footer design.
2. **XPRO-provided elements only** inside header/footer templates — no Elementor Pro widgets, HTML widgets, or Atomic elements.
3. **Save to the XPRO library** after finalizing each template so future edits stay non-destructive.
4. **Assign globally** (site-wide or per XPRO display conditions) once templates are approved.

## Workflow

1. Inspect XPRO settings and existing templates via `agent2wp/execute-php` (options, custom post types, or plugin APIs XPRO registers).
2. Create or duplicate a header template in XPRO.
3. Build with XPRO elements only; match brand spacing, logo, and navigation from the primary WordPress menu.
4. Save template to **XPRO library**; note template name/ID.
5. Repeat for footer (columns, links, copyright, optional WPForms link — not the form itself).
6. Assign header + footer to display on all relevant pages.
7. Verify on **homepage** and at least one inner page.
8. Store template IDs/names in **Agent2Wp → Context**.

## Navigation

- Primary menu is managed in **Appearance → Menus** (WordPress), not hard-coded duplicate links in XPRO unless required for mobile markup.
- Menu assignment should match the site rulesbook primary menu.

## Do not

- Build header/footer inside regular Elementor pages.
- Skip library save — ad-hoc templates are harder to maintain.
- Mix Elementor page-builder widgets into XPRO template slots unless XPRO explicitly wraps Elementor (default: XPRO elements only).

## Related skills

- `site-rulesbook` — full site checklist
- `elementor` — page body content only
- `wpforms` — contact form (linked from footer optional; form lives on Contact page)
