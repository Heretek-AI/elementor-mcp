<h1 align="center">
  <img src="assets/img/icon-sm.png" width="80" alt="Heretek Control Core logo"><br>
  Heretek Control Core
</h1>

<p align="center">
  <strong>Autonomous Model Context Protocol (MCP) Platform for Web &amp; Content Engineering</strong><br>
  <em>Maintained by <a href="https://github.com/Heretek-AI">Heretek AI</a> · 100% Open Source · All 269+ Pro Abilities Unlocked</em>
</p>

<p align="center">
  <em>Empowering autonomous AI agents (Claude, Cursor, ChatGPT, Antigravity) with native WordPress and Elementor page building capabilities.</em>
</p>

<div align="center">

[![Version](https://img.shields.io/github/v/release/Heretek-AI/heretek-control-core?label=version&color=dc2626)](https://github.com/Heretek-AI/heretek-control-core/releases)
[![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-8892BF.svg)](https://php.net)
[![WordPress](https://img.shields.io/badge/WordPress-%3E%3D6.9-21759B.svg)](https://wordpress.org)
[![Abilities](https://img.shields.io/badge/Abilities-269+_Unlocked-dc2626.svg)](#-all-269-abilities-unlocked)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)
[![GitHub Issues](https://img.shields.io/github/issues/Heretek-AI/heretek-control-core)](https://github.com/Heretek-AI/heretek-control-core/issues)
[![GitHub Stars](https://img.shields.io/github/stars/Heretek-AI/heretek-control-core?style=social)](https://github.com/Heretek-AI/heretek-control-core)

**[Quick Start](#-quick-start) · [Unlocked Abilities](#-all-269-abilities-unlocked) · [Connect AI Clients](#-connect-your-ai-client) · [Integrations](#-comprehensive-integrations-suite) · [Contributing](CONTRIBUTING.md)**

</div>

---

## ⚙️ About Heretek Control Core

**Heretek Control Core** is an unrestricted, telemetry-free **community edition** of the Model Context Protocol (MCP) server for WordPress and Elementor, originally authored by [Mian Shahzad Raza](https://github.com/msrbuilds) as `elementor-mcp`.

While the upstream free edition offers foundational builder endpoints, critical enterprise capabilities (in-editor in-editor AI chat, custom Gutenberg block generator, project memory, runtime agent skills, 8 form integrations, 6 SEO suites, unlimited themer quotas, and offline template bundles) were cordoned behind commercial paywalls and remote licensing servers.

**What Heretek Control Core delivers:**
- 🔓 **100% Pro Abilities Unlocked**: All commercial capabilities are compiled directly into the core. Zero license keys, zero subscriptions, and complete disarmament of Freemius telemetry.
- 🛡️ **Zero Telemetry & External Nags**: Stripped of phone-home analytics, account verification screens, and external licensing interceptors.
- 🔄 **Native In-Dashboard GitHub Updates**: The integrated updater checks [`Heretek-AI/heretek-control-core/releases`](https://github.com/Heretek-AI/heretek-control-core/releases) directly from your WordPress admin dashboard with one-click in-place updates.
- 📦 **Self-Contained Repository**: No private submodules or missing directories. Everything required to run, test, and forge ships directly in this repository.
- 🤝 **100% Protocol Compatible**: Preserves standard tool identifiers (`emcp-tools/*`), database formats, and REST routes. Existing agent configurations, Claude Desktop profiles, and client scripts work seamlessly without modification.

---

## ⚡ All 269+ Abilities Unlocked

| Feature Area | Upstream Free | Community Edition | Unlocked Capabilities |
|---|:---:|:---:|---|
| **Total MCP Tools** | ~188 | **269+** | Complete coverage of WordPress core, builders, forms, SEO, and commerce |
| **AI Assistant (AI Chat)** | ❌ Paid | ✅ **Included** | Floating AI assistant inside Elementor & Gutenberg; BYO LLM key or local Ollama |
| **Custom Block Builder** | ❌ Paid | ✅ **Included** | Custom Gutenberg block generator with sandbox compiler & live testing |
| **Elementor Widget Builder** | ❌ Paid | ✅ **Included** | Sandbox-backed custom Elementor widget generation & validation |
| **Theme Builder** | 1 per type | **Unlimited** | Headers, footers, single posts, and archives with exclude rules & dynamic tags |
| **Project Memory** | ❌ Paid | ✅ **Included** | Persistent memory CPT, admin approval workflow, and context injection |
| **Agent Skills** | ❌ Paid | ✅ **Included** | Runtime `SKILL.md` catalog with `list-skills` & `get-skill` discovery tools |
| **Deep Page Snapshot** | Basic | **Full Audits** | Automated WCAG AA color contrast math, heading hierarchy, and SEO audits |
| **Form Engines (8)** | ❌ Paid | ✅ **Included** | WPForms, Gravity Forms, Fluent, Ninja, Formidable, MetForm, SureForms, Forminator |
| **SEO Suites (6)** | ❌ Paid | ✅ **Included** | Yoast SEO, Rank Math, AIOSEO, SEOPress, The SEO Framework, SureRank |
| **Themes & Addons** | ❌ Paid | ✅ **Included** | GeneratePress, GenerateBlocks, Blocksy, BeTheme, Essential Addons, Premium, UAE |
| **WooCommerce Logistics** | ❌ Paid | ✅ **Included** | `woo-read`/`woo-write`: products, orders, customers, product CRUD + order updates (catalog widgets via `add-pro-widget`) |
| **Backup & Migrate** | ❌ Paid | ✅ **Included** | Portable `.emcp` site packages, database export/import, and deep URL search/replace |
| **Brand Kits & Templates** | 5 Prompts | **Full Library** | 50 Brand Kits, 60 Industry Landing Page Prompts, and Premium Templates |

---

## 🚀 Quick Start

### 1. Installation

1. Download the latest release `.zip` from **[Releases](https://github.com/Heretek-AI/heretek-control-core/releases)**.
2. In your WordPress admin: navigate to **Plugins → Add New → Upload Plugin**, select the `.zip`, and click **Install Now**.
3. Click **Activate Plugin**.
4. Access the **Elementor MCP** menu in your WordPress admin sidebar.
5. Notice the **PRO UNLOCKED** badge in the header — all capabilities are immediately active.

### 2. Native In-Dashboard Updates

This core features native in-dashboard update checking powered directly by GitHub Releases:
- Go to **Dashboard → Updates** or click the **Check for updates** button on the **Elementor MCP → Dashboard** screen.
- WordPress will notify you when a new release is available from `Heretek-AI/heretek-control-core` and allow one-click in-place updates without ever prompting for third-party licenses.

---

## 🔮 Connect Your AI Client

The plugin exposes an MCP server over stdio or HTTP streaming via the bundled [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter).

### Claude Desktop

Add this configuration to your `claude_desktop_config.json`:

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

*Tip: You can also generate a 1-click `.mcpb` bundle directly from the **Connect AI** tab in the plugin admin.*

### Claude Code

Connect Claude Code directly to your WordPress site:

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

When an integrated plugin is active on your WordPress site, its corresponding MCP tools automatically register:

### 1. E-Commerce (WooCommerce)
Two dispatcher tools register only when WooCommerce is active:
- **`emcp-tools/woo-read`**: `list-products`, `get-product`, `list-orders`, `get-order`, `list-customers`, `system-status`.
- **`emcp-tools/woo-write`**: `create-product`, `update-product`, `delete-product`, `update-order`.
Coupons, reviews, tax rates, shipping zones, and payment-gateway management are not yet exposed; WooCommerce widgets are placed through `add-pro-widget` (catalog tier `woo`).

### 2. Form Engines (8 Supported)
Inspect schemas, review field arrangements, and pull submissions:
- **WPForms** (`emcp-tools/wpforms-read`)
- **Gravity Forms** (`emcp-tools/gravityforms-read`)
- **Fluent Forms** (`emcp-tools/fluentforms-read`)
- **Ninja Forms** (`emcp-tools/ninjaforms-read`)
- **Formidable Forms** (`emcp-tools/formidable-read`)
- **MetForm** (`emcp-tools/metform-read`)
- **SureForms** (`emcp-tools/sureforms-read`)
- **Forminator** (`emcp-tools/forminator-read`)

### 3. SEO Suites (7 Supported: Slim SEO free + 6 Pro)
Full read & write control over meta titles, descriptions, OpenGraph/Twitter social cards, focus keywords, robots indexing, and canonical tags:
- **Yoast SEO** (`yoast-read`, `yoast-write`)
- **Rank Math SEO** (`rankmath-read`, `rankmath-write`)
- **All in One SEO (AIOSEO)** (`aioseo-read`, `aioseo-write`)
- **SEOPress** (`seopress-read`, `seopress-write`)
- **The SEO Framework** (`seoframework-read`, `seoframework-write`)
- **SureRank** (`surerank-read`, `surerank-write`)
- **Slim SEO** (`slimseo-read`, `slimseo-write`, free tier)

### 4. Themes & Custom Builders
- **GeneratePress**: Customizer colors, typography, layout presets, and elements (`generatepress-read`, `generatepress-write`).
- **GenerateBlocks**: Block catalog discovery + block defaults (`generateblocks-read`, `generateblocks-write`).
- **Blocksy**: Blocks catalog, extensions, and header/footer builder integration.
- **BeTheme & BeBuilder**: Read/write BeTheme options and construct BeBuilder content trees.
- **Elementor Addon Packs**: Essential Addons (`essential-addons-read`), Premium Addons (`premium-addons-read`), and Ultimate Addons for Elementor (`uae-read`, `uae-write`).

---

## 🛠️ Sandbox & Forge Superpowers

### Gutenberg Custom Block Builder
AI agents can autonomously craft native Gutenberg blocks within an isolated sandbox directory (`wp-content/emcp-sandbox/blocks/`):
- `emcp-tools/create-custom-block`: Generates valid `block.json` (apiVersion 3), `index.js`, and `render.php`.
- `emcp-tools/validate-block-spec`: Lints and validates block attributes and controls schema.
- `emcp-tools/set-block-status`: Safely activates or deactivates blocks on the site.

### Elementor Widget Builder
Construct production-ready custom Elementor widgets:
- Validated controls, responsive typography, icon selectors, and repeater structures.
- Isolated sandbox verification prior to live deployment.

### Backup, Restore & Migration
- **`create-backup` / `list-backups`**: Build a portable `.emcp` archive (full database dump + optional `include_files` bundle of uploads/plugins/themes) and list existing archives. Non-destructive, on by default.
- **Restore (admin-only)**: The Backup & Migrate tab verifies an archive's manifest hash, imports the database, rewrites the source URL to this site (serialized-safe, incl. `_elementor_data`), and places bundled files. Download + delete also live there.
- **`migrate-site`**: Push this site to a remote **EMCP Connector** (`connector/emcp-connector.php`, installed standalone on the destination) as HMAC-signed 2 MB packets; the connector restores it and reports a job id. Destructive on the destination — requires `confirm:true`. Disabled by default.
- **`sync-to-live`**: Run a serialized-safe URL search-and-replace across the whole database (options, posts, postmeta incl. `_elementor_data`, comments, term/usermeta, other prefixed tables). Requires `confirm:true`. Disabled by default.

---

## 🔒 Safety & Permission Protocols

Every tool strictly executes under native WordPress security standards:
1. **Capability Checks**: An agent can only execute operations permitted to the authenticated user account.
2. **Mutation Safeguards**: Destructive write, delete, and filesystem operations require an explicit `confirm: true` parameter.
3. **Opt-In Safety Defaults**: Write-capable and database-mutating abilities ship disabled-by-default on the **Tools & Abilities** screen for administrator review.
4. **Audit Trail**: Every change executed by an AI agent is recorded in the **Change History** with one-click rollback.

---

## 🤝 Contributing & Codex

We welcome community contributions, bug reports, templates, prompts, and new integrations! Please review our [Contribution Guidelines](CONTRIBUTING.md) to get started.

- **Found an anomaly or bug?** [Open an Issue](https://github.com/Heretek-AI/heretek-control-core/issues).
- **Want to share a template or prompt?** [Join the Discussions](https://github.com/Heretek-AI/heretek-control-core/discussions).

---

## 📄 License & Attribution

This project is licensed under the [GNU General Public License v2.0 or later](LICENSE).

- **Original Project**: [`msrbuilds/elementor-mcp`](https://github.com/msrbuilds/elementor-mcp) by [Mian Shahzad Raza](https://github.com/msrbuilds).
- **Unlocked Core**: Maintained by [Heretek AI](https://github.com/Heretek-AI).
