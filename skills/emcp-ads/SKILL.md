---
name: "Ad Monetization & ExoClick Manager"
description: "Manage WP Quads ad slots, dynamic ads.txt, ExoClick REST API zones, reporting, responsive ad styling, and cache invalidation"
---
# Ad Monetization & ExoClick Manager

Teach AI agents how to manage, audit, and optimize website advertising inventory, WP Quads ad units, dynamic `/ads.txt` records, and ExoClick REST API integrations across WordPress.

## Architecture & Data Flow

### 1. WP Quads Dual-Write Synchronization
WP Quads operates with a split data architecture:
- **Custom Post Type**: `quads-ads` posts store `code`, `position`, `dimensions`, and `quads_ad_old_id` (e.g. `ad1`, `ad2`) in `wp_postmeta`.
- **Serialized Settings**: `quads_settings` option in `wp_options` stores an `ads` associative array keyed by slot ID (`ad1`, `ad2`, ...).
- **Critical Rule**: Updating only one side leads to cache desynchronization, stale banner codes, or blank slots on frontend pages. Always use `emcp-tools/ads-write` (`update-ad` / `create-ad`), which enforces simultaneous dual-writes to both postmeta and serialized options.

### 2. Standard Responsive Placements & Dimensions
When placing ads in comic or blog layouts, maintain strict layout integrity:
- **Above Comic**: `728x90` Leaderboard. Wrap in responsive container centered above the primary canvas.
- **Under Comic**: `300x250` Medium Rectangle. Placed immediately after navigation or author notes.
- **Sidebars (Left & Right)**: `160x600` Wide Skyscrapers. Anchored in layout rails without overlapping main comic panels.
- **Sticky Mobile Footer**: Anchored floating banner (`eas6a97888e10` or `300x100`/`300x50`) hidden on desktop breakpoints.
- **Popunder (ExoClick PopMagic)**: `idzone: 6020536`. Always enforce frequency capping (minimum 24-hour cookie cooldown) to preserve user retention.

### 3. IAB ads.txt Management
- **Syntax**: `<domain>, <publisher_id>, <relationship>, <certification_authority_id>`
  - Example: `exoclick.com, 1111220, DIRECT, f6e6255c27770857`
  - Relationship must strictly be `DIRECT` or `RESELLER`.
- **Dual Persistence**: The plugin writes to `ABSPATH . 'ads.txt'` if filesystem permissions permit, and always synchronizes with the WordPress database option `emcp_tools_ads_txt` to ensure uninterrupted serving across all hosting configurations.

### 4. Cache Purging Protocol
Whenever ad tags, zone IDs, or `/ads.txt` are modified:
1. Purge LiteSpeed page cache: `do_action('litespeed_purge_all')`
2. Flush WordPress object cache: `wp_cache_flush()`
3. Flush transient API tokens: `purge-ad-cache`

---

## MCP Tools Reference

### `emcp-tools/ads-read`
Call with no `operation` to inspect full operation schemas and argument hints.

- `list-ads`: List all registered ad units from WP Quads, including detected ad networks (`ExoClick`, `JuicyAds`, `TrafficStars`), slot keys, dimensions, and active statuses.
  ```json
  { "operation": "list-ads", "arguments": { "status": "all" } }
  ```
- `get-ad`: Get complete configuration, dimensions, code, and placement for a specific ad slot by Post ID or Quads key.
  ```json
  { "operation": "get-ad", "arguments": { "id": "ad1" } }
  ```
- `get-ads-txt`: Parse `/ads.txt` records, validate IAB formatting, and inspect authorized digital sellers.
  ```json
  { "operation": "get-ads-txt", "arguments": { "validate_syntax": true } }
  ```
- `audit-monetization`: Diagnostic scan checking ads.txt health, active unit count, multi-network script conflicts, and responsive styling.
  ```json
  { "operation": "audit-monetization", "arguments": {} }
  ```
- `exoclick-list-zones`: Query ExoClick REST API for registered zones, IDs, types, and statuses.
  ```json
  { "operation": "exoclick-list-zones", "arguments": { "idsite": 1111220 } }
  ```
- `exoclick-get-stats`: Query ExoClick reporting API for impressions, clicks, CTR, eCPM, and revenue metrics.
  ```json
  { "operation": "exoclick-get-stats", "arguments": { "date_from": "2026-09-01", "date_to": "2026-09-05" } }
  ```

---

### `emcp-tools/ads-write`
Call with no `operation` to inspect full schemas.

- `update-ad`: Dual-write code, title, or position to WP Quads postmeta and `quads_settings`, automatically purging page cache.
  ```json
  {
    "operation": "update-ad",
    "arguments": {
      "id": "ad1",
      "code": "<script async src=\"https://a.magsrv.com/ad-provider.js\"></script><ins class=\"eas6a97888e2\" data-zoneid=\"6020542\"></ins><script>(AdProvider = window.AdProvider || []).push({\"serve\": {}});</script>",
      "title": "Above Comic 728x90",
      "purge_cache": true
    }
  }
  ```
- `create-ad`: Create a new ad unit across both posts and serialized settings.
  ```json
  {
    "operation": "create-ad",
    "arguments": {
      "title": "Sticky Mobile Footer",
      "code": "<script async src=\"https://a.magsrv.com/ad-provider.js\"></script><ins class=\"eas6a97888e10\" data-zoneid=\"6020534\"></ins><script>(AdProvider = window.AdProvider || []).push({\"serve\": {}});</script>",
      "slot_key": "ad11",
      "position": "custom"
    }
  }
  ```
- `delete-ad`: Delete ad unit from WP Quads and settings (`confirm: true` required for safety).
  ```json
  { "operation": "delete-ad", "arguments": { "id": "ad11", "confirm": true } }
  ```
- `set-ads-txt`: Replace or append lines to `/ads.txt` with IAB syntax pre-validation.
  ```json
  {
    "operation": "set-ads-txt",
    "arguments": {
      "append_records": [
        "exoclick.com, 1111220, DIRECT, f6e6255c27770857"
      ]
    }
  }
  ```
- `purge-ad-cache`: Clear LiteSpeed page cache, WP object cache, and temporary transients.
  ```json
  { "operation": "purge-ad-cache", "arguments": { "all": true } }
  ```
- `exoclick-create-zone`: Create a new ad zone via ExoClick API and optionally wire it into a WP Quads slot.
  ```json
  {
    "operation": "exoclick-create-zone",
    "arguments": {
      "name": "Under Comic 300x250",
      "dimensions": "300x250",
      "install_to_slot": "ad2"
    }
  }
  ```
- `exoclick-verify-site`: Verify site ownership with ExoClick via API verification check.
  ```json
  { "operation": "exoclick-verify-site", "arguments": { "idsite": 1111220 } }
  ```
