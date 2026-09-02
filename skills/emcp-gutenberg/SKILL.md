---
name: "Gutenberg Block Editor Integration"
description: "Build, parse, and synchronize WordPress block editor content and patterns using EMCP tools"
---
# Gutenberg Block Editor Integration

Guide for AI agents interacting with the WordPress block editor (Gutenberg) through EMCP tools.

## Key Abilities
- `get-post-blocks`: Inspect parsed blocks hierarchy (blockName, attrs, innerHTML, innerBlocks).
- `update-block`: Update specific block attributes or inner HTML.
- `add-block`: Insert core or custom blocks into posts and pages.
- `remove-block`: Delete specified block from content.
- `list-patterns`: Discover bundled block patterns and insert with `insert-pattern`.

## Block Markup Standards
Always ensure proper block delimiters:
```html
<!-- wp:paragraph {"fontSize":"medium"} -->
<p class="has-medium-font-size">Block text content here.</p>
<!-- /wp:paragraph -->
```
