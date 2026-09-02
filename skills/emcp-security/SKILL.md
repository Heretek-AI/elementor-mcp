---
name: "Security & Malware Scanner"
description: "Scan core integrity, detect backdoors and malicious payloads, and pair scanner findings with filesystem inspection"
---
# Security & Malware Scanner

This skill teaches AI agents how to audit WordPress security, detect unauthorized changes or malware, and safely confirm findings using the EMCP tools.

## Core Tool: `emcp-tools/scan-security`

`emcp-tools/scan-security` executes an automated security audit:
- **Core File Integrity Check**: Compares WordPress core files against official WordPress.org checksums.
- **Vulnerability & Heuristic Scan**:
  - Detects obfuscated PHP functions (`base64_decode`, `eval`, `gzinflate`, `str_rot13`).
  - Scans `wp-content/uploads/` for executable `.php`, `.phtml`, or hidden executable scripts.
  - Flags modified core files (e.g. `index.php`, `wp-config.php`, `.htaccess`).
- **User & Privileges Audit**:
  - Enumerates users with the `administrator` role.
  - Identifies recently created admin accounts or unverified emails.

## Pairing Scanner with Filesystem Tools

When `scan-security` reports a warning on a suspicious file:
1. **Never delete immediately without inspecting**:
   Use `emcp-tools/read-file` to view the suspicious file's contents and context.
2. **Search for Related Injections**:
   Use `emcp-tools/search-files` across `wp-content/plugins/` or `wp-content/themes/` using targeted regex patterns identified in the payload:
   ```json
   {
     "query": "eval\\s*\\(\\s*base64_decode",
     "path": "wp-content/plugins"
   }
   ```
3. **Database Audit**:
   Query `wp_posts` and `wp_options` via `emcp-tools/query` for injected `<script>` tags, hidden iframes, or rogue rewrite rules.

## Hardening Recommendations
- Disable file editing in dashboard (`DISALLOW_FILE_EDIT = true` in `wp-config.php`).
- Ensure file permissions: directories `0755`, files `0644`.
- Disable XML-RPC if mobile app is not in active use.
- Keep WordPress core, active theme, and all active plugins updated.
