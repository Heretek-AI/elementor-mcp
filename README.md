<h1 align="center">
  <img src="assets/img/icon-sm.png" width="72" alt="Heretek AI Elementor MCP logo"><br>
  Heretek MCP Tools (Unlocked)
</h1>

<p align="center">
  <strong>The Unlocked, Enterprise-Grade Model Context Protocol (MCP) Server for WordPress &amp; Elementor</strong><br>
  <em>Maintained by <a href="https://github.com/Heretek-AI">Heretek AI</a> · 100% Open Source · All 269 Pro Tools Included Free</em>
</p>

<div align="center">

[![Version](https://img.shields.io/github/v/release/Heretek-AI/elementor-mcp?label=version&color=dc2626)](https://github.com/Heretek-AI/elementor-mcp/releases)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-8892BF.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D6.9-21759B.svg)](https://wordpress.org)
[![MCP Tools](https://img.shields.io/badge/MCP_Tools-269_Unlocked-dc2626.svg)](#-all-269-mcp-tools-unlocked)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)
[![GitHub Issues](https://img.shields.io/github/issues/Heretek-AI/elementor-mcp)](https://github.com/Heretek-AI/elementor-mcp/issues)
[![GitHub Stars](https://img.shields.io/github/stars/Heretek-AI/elementor-mcp?style=social)](https://github.com/Heretek-AI/elementor-mcp)

**[Quick Start](#-quick-start) · [Unlocked Features](#-all-269-mcp-tools-unlocked) · [Client Setup](#-connect-your-ai-client) · [Integrations](#-comprehensive-integrations-suite) · [Contributing](CONTRIBUTING.md)**

</div>

---

## 📖 About This Fork

This repository is an unlocked, telemetry-free **soft fork** of [`msrbuilds/elementor-mcp`](https://github.com/msrbuilds/elementor-mcp), originally authored by [Mian Shahzad Raza](https://github.com/msrbuilds). 

While the upstream free version provides fundamental Elementor tools, many advanced capabilities (AI Chat assistant, custom Gutenberg block builder, project memory, agent runtime skills, 8 form integrations, 6 SEO plugin integrations, unlimited themer quotas, and offline bundles) were locked behind commercial Freemius paywalls and a private submodule (`msrbuilds/emcp-pro`).

**What this Heretek AI fork delivers:**
- 🔓 **100% Pro Features Unlocked**: All commercial capabilities are compiled directly into the codebase. No license keys, no subscriptions, and zero Freemius upsells.
- 🛡️ **Zero Telemetry & Tracking**: Stripped of phone-home analytics, account verification screens, and external paywall iframes.
- 🔄 **Native In-Dashboard Updates**: The built-in GitHub updater checks [`Heretek-AI/elementor-mcp/releases`](https://github.com/Heretek-AI/elementor-mcp/releases) directly from your WordPress admin dashboard with one-click in-place updates.
- 📦 **Self-Contained Repository**: No private submodules or missing directories. Everything required to run, test, and develop ships in this single repo.
- 🤝 **100% Upstream Compatible**: Preserves standard tool slugs (`emcp-tools/*`), database formats, and hooks. Existing agent configurations and client scripts work out of the box.

---

## ✨ All 269 MCP Tools Unlocked

| Feature Domain | Upstream Free | Heretek AI Unlocked | What It Unlocks |
|---|:---:|:---:|---|
| **Total MCP Tools** | ~188 | **269+** | Full coverage of WordPress core, page builders, forms, SEO, and commerce |
| **In-Editor AI Chat** | ❌ Paid | ✅ **Included** | Floating AI assistant in Elementor & Gutenberg; BYO LLM key or local Ollama |
| **Gutenberg Block Builder** | ❌ Paid | ✅ **Included** | Custom Gutenberg block generator with sandbox compiler & live testing |
| **Elementor Widget Builder** | ❌ Paid | ✅ **Included** | Sandbox-backed custom Elementor widget generation & validation |
| **Themer / Theme Builder** | 1 per type | **Unlimited** | Headers, footers, single post, and archives with exclude rules & dynamic tags |
| **Project Memory** | ❌ Paid | ✅ **Included** | Persistent memory CPT, admin approval workflow, and context injection |
| **Agent Skills Runtime** | ❌ Paid | ✅ **Included** | Runtime `SKILL.md` catalog with `list-skills` & `get-skill` discovery tools |
| **Deep Page Snapshot** | Basic | **Full Audits** | Automated WCAG AA color contrast math, heading hierarchy, and SEO audits |
| **Form Builders (8)** | ❌ Paid | ✅ **Included** | WPForms, Gravity Forms, Fluent, Ninja, Formidable, MetForm, SureForms, Forminator |
| **SEO Plugins (6)** | ❌ Paid | ✅ **Included** | Yoast SEO, Rank Math, AIOSEO, SEOPress, The SEO Framework, SureRank |
| **Themes & Addons** | ❌ Paid | ✅ **Included** | GeneratePress, GenerateBlocks, Blocksy, BeTheme, Essential Addons, Premium, UAE |
| **WooCommerce** | ❌ Paid | ✅ **Included** | ~120 store operations: products, orders, coupons, reviews, categories, inventory |
| **Backup, Sync & Migrate** | ❌ Paid | ✅ **Included** | Portable `.emcp` site packages, database export/import, and deep URL search/replace |
| **Offline Content Bundles** | 5 Prompts | **Full Library** | 50 Brand Kits, 60 Industry Landing Page Prompts, and Premium Templates |

---

## 🚀 Quick Start

### 1. Installation

1. Download the latest release `.zip` from **[Releases](https://github.com/Heretek-AI/elementor-mcp/releases)**.
2. In your WordPress admin: go to **Plugins → Add New → Upload Plugin**, select the `.zip`, and click **Install Now**.
3. Click **Activate Plugin**.
4. Open the **EMCP Tools** menu in the WordPress admin sidebar.
5. Notice the **PRO UNLOCKED** badge in the header — all features are instantly active!

### 2. Automatic Updates

This fork includes native in-dashboard update checking powered by GitHub releases:
- Go to **Dashboard → Updates** or click the **Check for updates** button on the **EMCP Tools → Dashboard** tab.
- WordPress will notify you when a new release is available from `Heretek-AI/elementor-mcp` and allow one-click updates.

---

## 🤖 Connect Your AI Client

The plugin exposes an MCP server over stdio or HTTP streaming via the bundled [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter).

### Claude Desktop

Add this to your `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": ["-y", "@msrbuilds/emcp-proxy@latest"],
      "env": {
        "WP_URL": "https://your-site.com",
        "WP_USER": "your_username",
        "WP_APP_PASSWORD": "xxxx xxxx xxxx xxxx"
      }
    }
  }
}
```

*Tip: You can also generate a 1-click `.mcpb` bundle directly from the **Connection** tab in the plugin admin.*

### Claude Code

```bash
claude mcp add wordpress -- npx -y @msrbuilds/emcp-proxy@latest
```

### Cursor

In your workspace root, create or edit `.cursor/mcp.json`:

```json
{
  "mcpServers": {
    "wordpress": {
      "command": "npx",
      "args": ["-y", "@msrbuilds/emcp-proxy@latest"],
      "env": {
        "WP_URL": "https://your-site.com",
        "WP_USER": "your_username",
        "WP_APP_PASSWORD": "xxxx xxxx xxxx xxxx"
      }
    }
  }
}
```

### Direct HTTP Streaming (OAuth / App Password)

```json
{
  "mcpServers": {
    "wordpress": {
      "url": "https://your-site.com/wp-json/mcp/emcp-tools-server",
      "headers": {
        "Authorization": "Basic <base64(username:app_password)>"
      }
    }
  }
}
```

---

## 🧩 Comprehensive Integrations Suite

When an integrated plugin is installed and activated on your WordPress site, its corresponding MCP tools automatically register:

### 1. E-Commerce (WooCommerce)
- **`emcp-tools/woo-read`**: Query products, variations, orders, customers, reviews, coupons, tax rates, shipping zones, payment gateways, and system reports.
- **`emcp-tools/woo-write`**: Create and update products, bulk adjust stock/prices, fulfill orders, issue coupon codes, manage refunds, and configure store settings.

### 2. Form Builders (8 Supported)
Read form structures, inspect fields, and fetch submissions:
- **WPForms** (`emcp-tools/wpforms-read`)
- **Gravity Forms** (`emcp-tools/gravityforms-read`)
- **Fluent Forms** (`emcp-tools/fluentforms-read`)
- **Ninja Forms** (`emcp-tools/ninjaforms-read`)
- **Formidable Forms** (`emcp-tools/formidable-read`)
- **MetForm** (`emcp-tools/metform-read`)
- **SureForms** (`emcp-tools/sureforms-read`)
- **Forminator** (`emcp-tools/forminator-read`)

### 3. SEO Suites (6 Supported)
Full read & write control over meta titles, descriptions, social OpenGraph/Twitter tags, focus keywords, robots indexing, and canonical URLs:
- **Yoast SEO** (`yoast-read`, `yoast-write`)
- **Rank Math SEO** (`rankmath-read`, `rankmath-write`)
- **All in One SEO (AIOSEO)** (`aioseo-read`, `aioseo-write`)
- **SEOPress** (`seopress-read`, `seopress-write`)
- **The SEO Framework** (`seoframework-read`, `seoframework-write`)
- **SureRank** (`surerank-read`, `surerank-write`)

### 4. Themes & Custom Builders
- **GeneratePress**: Customizer colors, typography, layout presets, and elements (`generatepress-read`, `generatepress-write`).
- **GenerateBlocks**: Discovery, block patterns, and layout schemas (`generateblocks-catalog`).
- **Blocksy**: Blocks catalog, extensions, and header/footer builder integration.
- **BeTheme & BeBuilder**: Read/write BeTheme options and build BeBuilder content trees.
- **Elementor Addon Packs**: Essential Addons (`ea-read`), Premium Addons (`pa-read`), and Ultimate Addons for Elementor (`uae-read`).

---

## 🛠️ Sandbox & Builder Superpowers

### Gutenberg Custom Block Builder
AI agents can autonomously build native Gutenberg blocks directly inside an isolated sandbox directory (`wp-content/emcp-sandbox/blocks/`):
- `emcp-tools/create-custom-block`: Generates valid `block.json` (apiVersion 3), `index.js`, and `render.php`.
- `emcp-tools/validate-block-spec`: Lints and validates the block attributes and controls schema.
- `emcp-tools/set-block-status`: Safely activate or deactivate blocks on the site.

### Elementor Widget Builder
Generate production-ready custom Elementor widgets:
- Validated controls, responsive typography, icon selectors, and repeater structures.
- Isolated sandbox testing before deploying to production.

### Backup, Sync & Migration
- **`create-backup`**: Packages the database, theme settings, and active plugins into a portable `.emcp` archive.
- **`migrate-site`**: Headless migration with serialized URL search-and-replace for domain switching.
- **Connector Plugin**: Lightweight standalone bridge (`connector/emcp-connector.php`) for push/pull sync between staging and production sites.

---

## 🔒 Safe by Default & Permission Model

Every tool call strictly executes under native WordPress security standards:
1. **Capability Checks**: An agent can only execute operations permitted to the authenticated user.
2. **Mutation Safeguards**: Destructive write, delete, and filesystem operations require an explicit `confirm: true` parameter.
3. **Opt-In Safety Defaults**: Write-capable and database-mutating tools ship disabled-by-default on the **Tools** screen for administrator review.
4. **Audit Trail**: Every change made by an AI agent is logged with one-click rollback available on the **History** tab.

---

## 🤝 Contributing

We welcome community contributions, bug reports, prompts, and new integrations! Please read our [Contribution Guidelines](CONTRIBUTING.md) to get started.

- **Found a bug or have a suggestion?** [Open an Issue](https://github.com/Heretek-AI/elementor-mcp/issues).
- **Want to share a build or prompt?** [Join the Discussions](https://github.com/Heretek-AI/elementor-mcp/discussions).

---

## 📄 License & Attribution

This project is licensed under the [GNU General Public License v2.0 or later](LICENSE).

- **Original Project**: [`msrbuilds/elementor-mcp`](https://github.com/msrbuilds/elementor-mcp) by [Mian Shahzad Raza](https://github.com/msrbuilds).
- **Unlocked Fork**: Maintained by [Heretek AI](https://github.com/Heretek-AI).
