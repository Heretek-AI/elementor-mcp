---
name: "EMCP Themer"
description: "Full guide for building theme templates (Headers, Footers, Singles, Archives) and dynamic data"
---
# EMCP Themer Guide

EMCP Themer allows programmatically building full-site theme templates without relying on third-party theme builders.

## Template Types
- `header`: Site-wide or conditional header
- `footer`: Site-wide or conditional footer
- `single`: Singular post/page/CPT layout
- `archive`: Blog/taxonomy/author loop layout
- `search`: Search results template
- `404`: 404 Error page

## Display Conditions & Priorities
Rules use `include` or `exclude`. Specific rules have higher specificity than broad ones.
