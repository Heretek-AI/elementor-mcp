---
name: "WooCommerce Automation"
description: "Driving the woo-read and woo-write dispatchers across ~120 store operations"
---
# WooCommerce MCP Automation

Interact with WooCommerce stores via `woo-read` and `woo-write`.

## Safety Rules
- All write operations modifying orders, prices, or inventory should be confirmed.
- Use `woo-read` to verify existing SKUs and categories before calling `woo-write`.
