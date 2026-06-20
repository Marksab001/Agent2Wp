---
name: Site Rulesbook
description: Master rules for building this WordPress site with MCP, Claude, Elementor, XPRO header/footer, and WPForms. Use when creating pages, menus, homepage, layouts, headers, footers, or contact forms.
enable_prompt: true
enable_agentic: true
---

# Site Rulesbook — MCP + Claude + WordPress

Follow this rulesbook on every site-building task. Load related Expert Suite skills (`elementor`, `xpro`, `wpforms`) when those plugins are active.

## 0. Agent2Wp (MCP bridge)

- Connect via **Agent2Wp → Configuration** with AI Abilities enabled.
- Use MCP tools (`agent2wp/execute-php`, content tools, `agent2wp/skill-get`) to inspect the live site before editing.
- Call `agent2wp/skill-get` with slug `elementor`, `xpro`, or `wpforms` before work in those areas.
- Persist confirmed site decisions (menu names, homepage ID, template slugs) in **Agent2Wp → Context**.

---

## 1. Site structure and configuration

### Navigation menu

1. Create **one primary menu** that includes all public pages the site needs.
2. Assign it to the theme’s primary/main location (inspect registered locations with `get_nav_menu_locations()`).
3. Add pages in logical order: Home, core pages, Contact last or as specified by the user.
4. After changes, verify the menu renders on the front end.

### Homepage

1. Create or designate the homepage page explicitly — do not leave `show_on_front` ambiguous.
2. Set **Settings → Reading**:
   - `show_on_front` = `page`
   - `page_on_front` = homepage page ID
3. Confirm with `get_option('show_on_front')` and `get_option('page_on_front')`.

### Environment safety — Atomic Editor

- **Disable the Atomic Editor** if it is enabled in Elementor or related settings.
- **Do not use Atomic elements** anywhere on the site.
- Before building, inspect active Elementor experiments/features and turn off Atomic-related options.
- If Atomic widgets appear in existing content, replace them with native Elementor equivalents.

---

## 2. Elementor and design standards

### Native components only

- Use **native free Elementor widgets** only.
- **Do not use** HTML widgets.
- **Do not use Elementor Pro widgets** (even if Pro is installed).
- Allowed examples: Heading, Text Editor, Image, Button, Icon, Spacer, Divider, Video, Icon Box, Image Box, Counter, Progress, Testimonial (free), Social Icons, Google Maps (free), Shortcode (only when required for WPForms embed — prefer WPForms Elementor widget if available).

### Layout structure

- Use **Containers** for all layout needs (Flexbox/Grid container system).
- **Do not use Inner Sections** — refactor any Inner Section layouts into nested Containers.
- Structure pattern: **Container → Container (columns) → Widgets**.
- Keep container hierarchy shallow; avoid unnecessary nesting.

### Page body workflow

1. Create page as draft.
2. Enable Elementor on the page.
3. Build with Containers + native widgets only.
4. Preview before publish.
5. Add the page to the primary menu.

---

## 3. Header and footer strategy (XPRO)

Headers and footers are **never** built inside regular page content. They are **XPRO templates only**.

### Design tool

- Use the **XPRO plugin** to design and assign the custom **header** and **footer**.
- Do not recreate header/footer markup in Elementor page bodies or theme template files unless the user explicitly overrides this rulesbook.

### XPRO components only

- In header and footer templates, use **only elements/widgets provided by XPRO**.
- Do not drop Elementor Pro widgets, HTML widgets, or Atomic elements into XPRO templates.

### Library and editability

1. Build header and footer in XPRO.
2. **Save both templates to the XPRO library** so they remain fully editable later.
3. Record template names/IDs in Agent2Wp Context after saving.
4. Assign header/footer to site-wide display (all pages or matching rules per XPRO settings).
5. Verify on homepage and an inner page.

---

## 4. Contact functionality

### WPForms

- Use **WPForms** as the **official contact form provider** for the Contact page.
- Create the form in WPForms admin first; note the form ID.
- Embed on the Contact page using the **WPForms widget/block** — not a raw HTML embed unless no widget exists.
- Style the surrounding page with Elementor (Containers + native widgets); keep the form itself managed by WPForms.
- Test submission on staging before go-live.

---

## 5. Pre-flight checklist (run before marking work complete)

| Check | Requirement |
|-------|-------------|
| Homepage | Set explicitly under Settings → Reading |
| Menu | Primary menu created and assigned |
| Atomic | Editor disabled; no Atomic elements in content |
| Elementor pages | Containers only; no Inner Sections; native widgets only |
| Header/Footer | Built in XPRO, saved to library, assigned globally |
| Contact | WPForms form live on Contact page |
| Context | Key IDs (homepage, menu, XPRO templates, form ID) saved in Agent2Wp Context |

---

## 6. What not to do

- Do not mix header/footer layout into page Elementor content.
- Do not use Elementor Pro or HTML widgets to shortcut layout.
- Do not skip saving XPRO templates to the library.
- Do not publish without verifying menu + homepage + contact form on the front end.
