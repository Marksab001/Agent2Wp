---
name: WPForms Contact
description: Create and embed WPForms contact forms on the Contact page — official form provider for this site. Use when building contact pages, forms, or form submissions.
enable_prompt: true
enable_agentic: true
---

# WPForms — contact page

WPForms is the **official contact form provider** for this site.

## Hard rules

1. All contact functionality uses **WPForms** — not Contact Form 7, Gravity Forms, or custom HTML forms unless the user explicitly overrides.
2. Form is created in **WPForms admin** first; embed by ID on the Contact page.
3. Surrounding page layout uses **Elementor** (Containers + native widgets); form fields stay inside WPForms.

## Workflow

1. Inspect existing forms: `agent2wp/execute-php` with WPForms APIs or post type queries if available.
2. Create form in WPForms (fields: Name, Email, Message minimum; add phone/consent if user requests).
3. Configure notifications (admin email) and confirmation message.
4. Create or open **Contact** page; set Elementor layout with Containers only.
5. Embed form:
   - Prefer **WPForms Elementor widget** or Gutenberg WPForms block.
   - Fallback: `[wpforms id="FORM_ID"]` shortcode via Shortcode widget only if no widget exists.
6. Add Contact page to primary menu.
7. Test submission on staging; confirm email/confirmation behavior.
8. Save form ID and page ID in **Agent2Wp → Context**.

## Page structure (example)

```
Container (hero — Heading + Text)
Container (form column — WPForms widget)
Container (optional — address/map with native Google Maps widget)
```

## Do not

- Hard-code `<form>` HTML in Elementor HTML widgets.
- Place the only contact form in the footer unless user requests it — primary placement is Contact page.

## Related skills

- `site-rulesbook` — menu, homepage, checklist
- `elementor` — page layout around the form
