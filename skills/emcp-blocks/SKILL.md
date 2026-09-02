---
name: "EMCP Custom Blocks"
description: "How to design, generate, and sandbox custom Gutenberg blocks from JSON specs"
---
# EMCP Custom Blocks Guide

Agents can design custom Gutenberg blocks by submitting a structured block specification to `create-block` or `generate-block`.

## Block Lifecycle
1. Propose and validate specification via `validate-block`.
2. Generate block package (`block.json` + `render.php`) using `generate-block`.
3. Test in sandbox before activating.
