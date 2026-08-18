# Production Security Handover - Release 3.12.3

**Prepared:** 2026-08-17
**Target release:** EMCP Tools 3.12.3 / EMCP Tools Pro 3.12.3
**Status:** Remediation required before production release
**Primary owner:** Responsible implementation and release agent

## Agent objective

Remediate the confirmed security findings in this report, add focused regression
coverage, rebuild the production packages, and provide verification evidence.

Work in the editable source paths listed below. Do not patch files under
`releases/` directly; those are generated release mirrors and ZIP artifacts.
Preserve the existing nonce, capability, HMAC, OAuth/PKCE, path confinement,
secret encryption, and destructive-operation safeguards while making the fixes.

## Release decision

**Do not publish the current Pro package.** The standalone migration connector
contains a conditional unauthenticated site-takeover path when an administrator
opens its pairing window. This is a release blocker.

The free package had no release-blocking finding in this scoped review. The Pro
package also has three medium-severity issues. A low-severity OAuth registration
hardening issue affects the shared plugin code.

## Scope

The scan used the actual production artifacts:

- `releases/emcp-tools-3.12.3.zip`
- `releases/emcp-pro-3.12.3.zip`

Every ZIP entry was compared with its unpacked release mirror:

| Package | Files compared | Hash mismatches |
|---|---:|---:|
| Free | 770 | 0 |
| Pro | 928 | 0 |

The review excluded tests, documentation, repository metadata, development
scripts, build tooling, and other files that are not plugin runtime code. Shipped
third-party libraries were inventoried separately.

## Priority order

1. SEC-3123-01: authenticate connector pairing.
2. SEC-3123-02: make destructive AI approval metadata-driven.
3. SEC-3123-03: close the DNS-rebinding gap in web fetch.
4. SEC-3123-04: bound read-only database query work.
5. SEC-3123-05: bound public OAuth client registration.
6. Rebuild and verify both release ZIPs.

---

## SEC-3123-01 - Pairing-window hijack permits site takeover

**Severity:** Critical release blocker
**Affected artifact:** Pro standalone connector
**Exploit condition:** The connector is installed and its administrator has
opened the 5, 10, or 15 minute pairing window.

### Editable source

- `pro/connector/emcp-connector.php.txt:139-205`
- `pro/connector/emcp-connector.php.txt:584-681`
- `pro/connector/emcp-connector.php.txt:698-740`
- `pro/connector/emcp-connector.php.txt:772-834`
- `pro/connector/emcp-connector.php.txt:1010-1031`
- `pro/connector/emcp-connector.php.txt:1056-1105`

### Evidence

`perm_pair()` authorizes an unauthenticated `/pair` request solely because the
administrator-controlled timer is open. `route_pair()` then accepts the caller's
`live_url`, long-term HMAC `secret`, and expiry. No destination-generated pairing
credential or expected-source identity is required.

The first successful caller controls the stored HMAC secret and closes the
window. That caller can sign packet upload and restore requests. The restore
layer does not authenticate an independent package signer: it accepts the custom
`.emcp` structure, imports included SQL, and copies included files into the
WordPress installation. Winning the pairing race therefore permits database and
file replacement, including PHP placement and full site takeover.

### Required remediation

- Generate at least 256 bits of random pairing entropy when the admin arms the
  connector.
- Display a human-transferable one-time code to the administrator for entry on
  the source site. Do not expose the code through a public endpoint.
- Store only a cryptographic hash of the one-time code, plus its expiry and any
  required context.
- Require the code in `/pair` and compare it in constant time.
- Atomically consume the code and close the window so two concurrent requests
  cannot both succeed.
- Accept and store the source's long-term HMAC secret only after one-time-code
  authentication succeeds.
- Rate-limit failed pairing attempts. Return a generic error that does not reveal
  whether a code, source, or expiry check failed.
- Clear all pending pairing state on disarm, successful pairing, unpair, and
  expiry.
- Where practical, show the source URL/fingerprint to the destination admin and
  require confirmation before destructive restore begins.
- Add explicit upper bounds for packet size, declared total size, manifest size,
  entry size, and aggregate extracted size. These are secondary safeguards and
  do not replace authenticated pairing.

### Acceptance criteria

- `/pair` without a code fails while the window is open.
- An invalid, expired, replayed, or already-consumed code fails.
- Exactly one request succeeds when two valid pairing requests race.
- A valid code succeeds once and establishes the expected long-term HMAC secret.
- A failed request cannot alter the existing pairing or close a valid window.
- Disarm and unpair invalidate all relevant pairing credentials immediately.
- Existing signed packet, status, cleanup, content, and restore flows continue to
  work after a legitimate pairing.
- Oversized packets/packages are rejected before unbounded disk or memory use.

### Required tests

- Unit tests for code generation, hashing, expiry, constant-time validation, and
  one-time consumption.
- REST tests for missing, invalid, expired, replayed, and valid pairing codes.
- A concurrency-oriented test or atomic-state test proving only one pairing wins.
- End-to-end connector test: arm, pair, transfer, restore, unpair.

---

## SEC-3123-02 - Destructive AI approval list is incomplete

**Severity:** Medium
**Affected artifact:** Pro AI Chat
**Threat:** Model error or prompt injection performs destructive work without the
human approval promised by the AI Chat setting.

### Editable source

- `pro/includes/ai-chat/class-ai-chat-controller.php:35-79`
- `pro/includes/ai-chat/class-ai-chat-controller.php:675-706`
- Ability registration files under `includes/abilities/` and
  `pro/includes/abilities/`

### Evidence

The controller uses a manually maintained `DESTRUCTIVE_TOOLS` list. The approval
check only runs when an ability name appears in that list, even though abilities
already publish destructive metadata.

Confirmed destructive abilities missing from the list include:

- `emcp-tools/delete-custom-block`
- `emcp-tools/delete-global-class`
- `emcp-tools/delete-redirect`
- `emcp-tools/delete-theme-php-template`
- `emcp-tools/delete-theme-template`
- `emcp-tools/migrate-site`
- `emcp-tools/remove-block`
- `emcp-tools/sync-content-item`
- `emcp-tools/sync-to-live`
- `emcp-tools/woo-write`

Some implementation callbacks also require `confirm:true`, but the model can
supply that argument. It is not a substitute for the separate human approval
gate.

### Required remediation

- Make the server approval decision from the registered ability's canonical
  destructive annotation instead of a second name list.
- Fail closed when approval is enabled and metadata is missing, malformed, or
  cannot be read.
- Keep the human approval signal outside model-authored ability arguments.
- Bind approval to the current user, ability name, and canonical argument hash.
  Prefer a short-lived, one-use server token rather than a reusable boolean.
- Ensure the advertised UI/tool state and server enforcement use the same
  metadata source.

### Acceptance criteria

- Every active ability marked destructive is rejected without human approval
  when approval is enabled.
- Every listed missing ability above is covered by the gate.
- Approval for one ability or argument set cannot authorize another.
- Approval cannot be replayed after use or expiry.
- Non-destructive abilities remain usable without unnecessary approval.
- Direct REST calls cannot bypass the same server-side rule.

### Required tests

- Enumerate all registered active abilities and assert that destructive metadata
  maps to approval enforcement.
- Add a regression test that fails whenever a newly added destructive ability is
  not gated.
- Test missing/malformed metadata, token replay, argument changes, and expiry.

---

## SEC-3123-03 - DNS rebinding in AI Chat web fetch

**Severity:** Medium
**Affected artifact:** Pro AI Chat
**Exploit condition:** An authenticated Pro AI Chat administrator fetches an
attacker-controlled hostname, directly or through model/tool behavior.

### Editable source

- `includes/class-url-guard.php:155-213`
- `pro/includes/ai-chat/class-ai-chat-web-fetch.php:108-179`

### Evidence

The URL guard resolves the hostname and rejects private, loopback, link-local,
and reserved addresses. The later `wp_remote_get()` call connects by hostname,
not by the address that passed validation. An attacker-controlled DNS server can
return a public address during validation and an internal address during the TCP
connection. The source comments correctly identify this TOCTOU limitation.

Manual redirect handling and short timeouts reduce exposure but do not close the
rebinding gap. Successful exploitation can reach cloud metadata or internal HTTP
services and return their response to AI Chat.

### Required remediation

- Resolve once, require every returned address to be public, select a validated
  address, and pin the transport connection to it.
- Preserve the original hostname for HTTP Host and TLS SNI/certificate checks.
- Apply the same process independently to every redirect hop.
- Support both IPv4 and IPv6 and fail closed on ambiguous or failed resolution.
- Set WordPress `reject_unsafe_urls => true` as defense in depth.
- Do not silently fall back to an unpinned transport. If a WordPress HTTP
  transport cannot safely pin an address, reject the request or disable that
  transport for this feature.

### Acceptance criteria

- Literal and DNS-resolved private, loopback, link-local, multicast, and reserved
  addresses are rejected for IPv4 and IPv6.
- A host whose DNS answer changes between validation and connection cannot reach
  the changed address.
- Redirects to unsafe or rebound destinations are rejected.
- HTTPS verification still validates the original hostname.
- Normal public HTTP and HTTPS pages continue to work.

### Required tests

- Use injectable resolver/transport seams or a controlled test DNS server to
  simulate public-to-private rebinding.
- Cover mixed public/private answer sets, IPv6, redirects, failed resolution,
  and TLS hostname preservation.

---

## SEC-3123-04 - Read-only SQL work is not resource bounded

**Severity:** Medium
**Affected artifacts:** Free and Pro database ability surface
**Threat:** An authenticated MCP administrator or model-driven tool call can
exhaust database CPU, connection time, or PHP memory using a syntactically
read-only query.

### Editable source

- `includes/abilities/class-database-abilities.php:94-123`
- `includes/class-database-guard.php:80-126`

### Evidence

The guard blocks write/DDL keywords, multiple statements, file access, and raw
reads of protected user tables. However, the row limit is applied only after
`$wpdb->get_results()` executes and fetches the complete result. There is no
database-side execution timeout and delay/lock/resource functions such as
`SLEEP()`, `BENCHMARK()`, and `GET_LOCK()` are not rejected. Expensive recursive
CTEs and joins are also possible.

### Required remediation

- Reject known delay, lock, file, UDF execution, and side-effect functions using
  parsed/normalized SQL tokens, not raw substring checks.
- Enforce a database-side statement timeout compatible with supported MySQL and
  MariaDB versions. Restore any changed session setting in a `finally` block.
- Apply a database-side row bound for result-producing queries before execution.
- Avoid fetching an unbounded result and slicing it afterward.
- Return a generic bounded-query error without leaking database internals.

### Acceptance criteria

- `SELECT SLEEP(...)`, `BENCHMARK(...)`, lock acquisition, and equivalent unsafe
  functions are rejected.
- A query cannot run beyond the configured execution budget where the database
  supports statement timeouts.
- The database never returns more than the configured maximum rows to PHP.
- Existing safe `SELECT`, `SHOW`, `DESCRIBE`, and `EXPLAIN` workflows continue to
  function.
- Existing write, DDL, multi-statement, file-access, and protected-user-table
  blocks remain effective.

### Required tests

- Unit tests for delay/lock function variants, comments, case changes, and quoted
  literals.
- Integration tests proving server-side timeout and row limits.
- Regression tests for all previously blocked SQL classes.

---

## SEC-3123-05 - Public OAuth registration can grow the database

**Severity:** Low
**Affected artifacts:** Free and Pro OAuth server
**Threat:** Unauthenticated availability abuse of the Dynamic Client
Registration endpoint.

### Editable source

- `includes/oauth/class-oauth-clients.php:25-134`
- `includes/oauth/class-oauth-store.php:140-173`
- `includes/oauth/class-oauth-store.php:433-467`

### Evidence

`/register` is intentionally public, but registration has no application-level
rate limit and no explicit maximum count or length for `redirect_uris`. Distinct
names or URI sets create new database rows. Orphan cleanup runs later, so an
attacker can create rows and large JSON values faster than scheduled cleanup.

The redirect validator also accepts every syntactically valid non-HTTP scheme.
Private-use native-app schemes are legitimate, but dangerous generic schemes
such as `javascript`, `data`, `file`, and `vbscript` should never be accepted.

### Required remediation

- Keep standards-required public registration; do not solve this by requiring a
  logged-in WordPress user.
- Enforce small explicit limits for request bytes, client-name length, redirect
  URI count, and individual/aggregate URI length before database work.
- Add rate limiting suitable for deployments behind trusted proxies.
- Allow HTTPS, HTTP loopback, and deliberately supported private-use app schemes.
  Explicitly deny browser-executable, local-file, and opaque dangerous schemes.
- Preserve exact redirect URI matching and PKCE requirements.

### Acceptance criteria

- Oversized names, URI arrays, individual URIs, aggregate metadata, and request
  rates receive bounded 4xx responses without database inserts.
- Dangerous schemes are rejected.
- Valid HTTPS, loopback-native, and supported private-use registrations continue
  to work.
- Repeated identical legitimate registration still reuses the existing client.
- Scheduled orphan cleanup remains functional.

### Required tests

- Boundary tests at and above every size/count limit.
- Rate-limit tests, including expiry/reset behavior.
- Redirect scheme allow/deny tests and exact-match authorization tests.

---

## Existing controls that must not regress

The scan verified the following positive controls. Keep them intact and add
regression coverage where touched:

- Admin mutation handlers generally require both a nonce and an appropriate
  capability.
- MCP abilities publish permission callbacks and the dispatcher validates input.
- OAuth uses PKCE S256, exact redirect matching, high-entropy tokens, and hashed
  token storage.
- AI provider secrets use authenticated AES-256-GCM encryption derived from the
  WordPress site secret.
- Filesystem tools confine paths under the WordPress installation and respect
  `DISALLOW_FILE_EDIT`.
- Structured database writes use `$wpdb` helpers, protect user tables, and require
  scoped conditions for update/delete operations.
- The SVG sanitizer copy corresponds to patched `enshrined/svg-sanitize` 0.22.0.
- No hardcoded first-party production credential was identified.

## Validation already completed

- 835 first-party production PHP files linted successfully with PHP 8.4.
- ZIP-to-release-mirror SHA-256 comparison completed with zero mismatches.
- A crafted ZIP `../` extraction check against the active PHP `ZipArchive`
  implementation remained inside the destination directory. Archive traversal
  was therefore not reported as a confirmed vulnerability.
- Bundled Freemius SDK version is 2.13.4.
- Bundled Composer packages identified:
  - `automattic/jetpack-autoloader` v5.0.20
  - `wordpress/mcp-adapter` v0.5.0
  - `wordpress/php-mcp-schema` v0.1.2
- The installed Composer executable does not provide `composer audit`; dependency
  advisory coverage is not complete until the agent runs the audit with Composer
  2 or an equivalent current advisory scanner.

## Final verification checklist

The remediation is complete only when all items below are satisfied:

- [ ] SEC-3123-01 through SEC-3123-05 acceptance criteria pass.
- [ ] New targeted security regression tests pass.
- [ ] Existing unit, integration, REST, and end-to-end suites pass.
- [ ] All first-party production PHP files pass syntax checks on the minimum and
      current supported PHP versions.
- [ ] Composer 2 `composer audit` or an equivalent advisory scan reports no known
      affected production dependency.
- [ ] Free and Pro packages are rebuilt from source using the project release
      process.
- [ ] Tests, development scripts, repository metadata, and unrelated files are
      excluded from the final plugin payloads.
- [ ] Every final ZIP entry matches its corresponding staged release file by
      SHA-256.
- [ ] The standalone connector in the final Pro ZIP contains the authenticated
      pairing implementation and its tests have exercised the installed form.
- [ ] Version/changelog/security documentation is updated for the patched
      release without publishing exploit details prematurely.

## Required agent response

When returning the work, provide:

1. A finding-by-finding summary of the implemented change.
2. Source files changed, with important line references.
3. Tests added and exact pass/fail results.
4. Dependency audit output and scanner/version used.
5. Final free/Pro ZIP names, file counts, and SHA-256 hashes.
6. Any acceptance criterion not met, with the specific blocker and residual risk.

Do not mark this handover complete solely because code was changed. Completion
requires rebuilt release artifacts and verification evidence.
