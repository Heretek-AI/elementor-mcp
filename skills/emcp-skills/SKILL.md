---
name: "Elementor Page Building Master Skill"
description: "Professional playbook for building responsive, accessible, high-converting Elementor pages using MCP tools"
---
# Elementor Page Building Master Skill

A complete workflow guide for AI agents designing and developing pages with Elementor and EMCP tools.

## 1. Discovery & Inspection Phase
Before modifying any layout or injecting elements:
1. **Analyze Site Context**:
   - Call `core-get-site-info` to identify WordPress version, active theme, and URL structure.
   - Inspect active plugins with `emcp-tools/list-plugins`.
2. **Inspect Existing Page Structure**:
   - Call `emcp-tools/get-page-structure` with `post_id` to inspect existing container IDs, nested elements, and widgets.
3. **Inspect Registered Widgets**:
   - Call `emcp-tools/list-widgets` to see all available core and addon widgets.

## 2. Kit-First Design System
Always anchor layouts to the site's design kit rather than hardcoding ad-hoc hex values:
- Use Elementor Global Colors: Primary (`__globals__?id=globals/colors?key=primary`), Secondary, Text, Accent.
- Use Global Typography presets (Primary, Secondary, Text, Accent).
- Maintain uniform spacing tokens (e.g. 16px, 24px, 48px, 80px).

## 3. Flexbox Container Rules
Elementor uses modern CSS Flexbox containers (`e-con`):
- **Structure**: Root Container (Row/Column) -> Inner Content Containers -> Widgets.
- **Avoid Over-Nesting**: Never nest containers more than 3 levels deep unless strictly required for multi-column responsive alignment.
- **Direction**:
  - Hero / Feature sections: Column on mobile, Row on desktop.
  - Card grids: Row with `flex-wrap: wrap` and percentage or fixed-basis widths.
- **Justify & Align**:
  - Center hero content: `justify_content: center`, `align_items: center`.
  - Space-between header/nav rows: `justify_content: space-between`.

## 4. Responsive Breakpoints
- Test and style Desktop (default), Tablet (`1024px`), and Mobile (`767px`).
- Invert container direction for mobile when visual media should appear above copy.
- Adjust typography sizes responsively: Headings scale down proportionally on mobile (e.g. 48px desktop -> 32px mobile).

## 5. Review & Verification Loop
After applying updates:
1. Fetch page snapshot using `emcp-tools/get-page-snapshot` to verify DOM structure, contrast, and SEO metadata.
2. Preview rendered output in browser to confirm zero overlapping absolute positions or clipped overflow.
3. Check accessibility with `emcp-tools/audit-page-a11y`.

## Industry Skill Packs
When building for a specific business vertical, read the dedicated vertical pack in `verticals/` for tailored layouts, conversion CTAs, and schema patterns.
