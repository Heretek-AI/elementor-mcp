# Security Remediation Report: EMCP Tools 3.12.4 (round 5)

**Responds to:** `PRODUCTION-SECURITY-HANDOVER-3.12.3.md` (round 1), the cross-check of the 3.12.4 candidate (round 2), the parser review (round 3), the follow-up on backtick lexing and proxied fetches (round 4), and an independent adversarial re-audit (round 5).
**Date:** 2026-08-18
**Status:** committed locally, NOT pushed, NOT tagged, NOT released.

| Item | Value |
|---|---|
| Free repo HEAD | `3886dc3` security(3.12.4): read ANSI_QUOTES too, and refuse the server system schemas |
| Pro submodule HEAD | `6ca1d50` test(security): pin ANSI_QUOTES, system schemas, and parenthesized UNION |
| Free ZIP SHA-256 | `7b110ff15995e985120ea2ba3b7aeaf1a3f57687b9e618a24c018c8d9013846e` |
| Pro ZIP SHA-256 | `08041df401892df1663bdb35ce5d5ebf24f243a399efcb083d48cbf3e4f8c029` |

**Both review rounds were correct and are accepted.** Every finding across rounds 2 and 3 was reproduced in the code before being fixed; none were disputed. The round-3 SQL finding was not merely reproducible, it was **exploitable end to end**: a crafted statement returned a username from `wp_users` while the guard believed it was running `SELECT 1`. Artifacts have been rebuilt, so all hashes in earlier versions of this report are superseded.

---

## 0. Round-5 findings

An independent adversarial re-audit, run on a different model with instructions not to trust this report. It returned a NO-RELEASE verdict and two confirmed bypasses. Both were reproduced here before being fixed, and both were exploitable end to end.

This is the fourth time the SQL guard has been wrong, and the second time a fix in this report created the next hole. Section 6 item 7 already said the lexer should be treated as unfinished; that judgement was right.

### R5-1 (High): ANSI_QUOTES was the unread half of the dual-reading design

Round 3 added `normalize_variants()` to stop guessing the session's `sql_mode`. It varied exactly one switch, `NO_BACKSLASH_ESCAPES`. **`ANSI_QUOTES` is the twin switch and was not varied**: under it a double-quoted token is an IDENTIFIER, not a string. The lexer always treated it as a string and blanked it, so a protected table hid inside one.

Reproduced with the session in `ANSI_QUOTES`:

```
sql        : SELECT user_login FROM "wp_users" LIMIT 1
guard      : ALLOWED
keep-read  : SELECT user_login FROM '' LIMIT 1      <- the table was blanked
mysql      : {"user_login":"admin"}
```

The auditor's own reproduction went further and returned `user_pass`, a full password hash, through the real `query` executor.

`ANSI_QUOTES` is not the WordPress default, but any plugin, mu-plugin, or host that issues `SET SESSION sql_mode=...,ANSI_QUOTES` makes every later query on that connection run under it, including the MCP call.

**Fixed:** `normalize_sql()` now takes the quote convention as a fourth parameter, the identifier branch handles both delimiters (with doubled-delimiter escaping), and `normalize_variants()` returns the **cross product** of both switches, deduped. Verified refused, including the double-quoted alias variant, while a `"my label"` alias and a `"a quoted title"` literal still pass.

### R5-2 (Medium): the protected-table list did not cover other schemas

`query_touches_protected()` knew only the WordPress user tables, so a cross-schema reference walked straight past it, defeating the tool's own stated purpose. `mysql.user` holds server account password hashes and the metadata schemas expose the whole server.

```
SELECT user, host, authentication_string FROM mysql.user LIMIT 3   ->  ALLOWED
SELECT user FROM `mysql`.`user` LIMIT 2                            ->  ALLOWED
SELECT table_name FROM information_schema.tables LIMIT 2           ->  ALLOWED
```

Exploitability depends on the database grant: it fails on least-privilege managed hosting and succeeds where WordPress runs with a broad or root DB account, which is common on self-hosted and VPS installs.

**Fixed:** `references_system_schema()` refuses `mysql`, `information_schema`, `performance_schema`, and `sys`, wired into the query executor beside the protected-table check. Matched with identifiers KEPT so the backticked and double-quoted forms are caught too, and it does not overreach: a literal `'mysql.user'` and a table named `my_mysql.thing` still pass.

### Also fixed: a false positive the same review found

The first-keyword test ran on the raw normalized string, so a leading paren made it fail and a parenthesized UNION branch was refused as not-read-only. Leading parens are now skipped, and a write behind a paren is still refused.

### Round-5 items accepted but NOT fixed

- **Multi-byte lead-byte masking.** The auditor could not reproduce it and neither can I: this connection is `utf8mb4`, whose continuation bytes (0x80 to 0xBF) can never collide with a quote, backtick, or backslash, so byte-level scanning is safe here. On a legacy connection charset such as GBK or SJIS it is a real hazard. Unproven, and left open in section 6.
- **AI Chat approval is a reusable boolean, not a one-use token.** SEC-3123-02's suggested remediation asked for a per-user, per-argument-hash single-use token. What shipped is a fail-closed boolean set by the browser after a human click. That closes the bypass but is not the stronger design, and this report has never claimed otherwise.

---

## 0b. Round-4 findings

Both were correct. The SQL one was again **exploitable end to end**, and it was in code written during round 3, which is the strongest argument in this report for not trusting a lexer fix without re-probing it.

### R4-1 (High): stripping backticks before lexing re-lexed identifier contents

`query_touches_tables()` ran `str_replace` on every backtick before calling the lexer. That turned the CONTENTS of a quoted identifier into bare SQL text, so an alias containing a hash, a quote, or a dash-dash-space opened a comment or a string and hid everything after it.

Proven on the dev database before fixing. All three returned the username while the guard said ALLOWED:

```
SELECT 1 AS `x#`,   (SELECT user_login FROM wp_users LIMIT 1)  ->  ALLOWED, leaked "admin"
SELECT 1 AS `x'`,   (SELECT user_login FROM wp_users LIMIT 1)  ->  ALLOWED, leaked "admin"
SELECT 1 AS `x-- `, (SELECT user_login FROM wp_users LIMIT 1)  ->  ALLOWED, leaked "admin"
```

The pre-strip was not arbitrary: `normalize_sql()` emptied backtick spans, so without it a legitimately quoted table name disappeared from the scan. Both needs are now met **inside** the lexer. A backtick span is consumed as a single token, and a `keep_identifiers` flag decides what is emitted: the name (so the table scan sees the table) or a blank (so a column named `delete` is not read as a write keyword). Doubled backticks are treated as a literal backtick rather than ending the identifier early.

Verified after the fix: all five alias shapes are refused, a backticked protected table is still refused, and `delete` / `update` / `insert` as quoted column names are still allowed.

### R4-2 (Medium): pinning does not survive an outbound HTTP proxy

Correct. `CURLOPT_RESOLVE` only governs names libcurl resolves itself. Through a proxy the hostname is handed over verbatim (in the CONNECT line for TLS) and the **proxy** resolves it, so the address we validated is not the address connected, while `curl_setopt` still succeeds and the pin flag still becomes true.

Pinning cannot be enforced on that path, so `fetch()` now refuses it: `proxied()` asks `WP_HTTP_Proxy::send_through_proxy()` per URL, on the first hop and on every redirect, because a bypass list can cover some hosts and not others and a redirect can cross that boundary.

Verified live by configuring a proxy:

| Condition | Result |
|---|---|
| no proxy | fetch ok |
| `WP_PROXY_HOST` set, host proxied | **REFUSED** (`fetch_proxied`) |
| proxy set, host on `WP_PROXY_BYPASS_HOSTS` | fetch ok (resolved locally, so the pin holds) |

### Also fixed: a pre-existing flaky test

`CloudStoreTest` asserted that a random base64 ciphertext does not contain the two-character token `AT`. That collides by chance often enough to fail roughly **one run in five**, and it reads as a plaintext leak when it is not one. It now uses long distinctive canaries and checks the refresh token too. Confirmed stable across 8 consecutive full runs.

---

## 0b. Round-3 findings

### R3-1 (High): the SQL lexer disagreed with MySQL in two ways

**Confirmed, and demonstrated as a working exfiltration** before any fix was written.

**(a) `--` was always treated as a comment.** MySQL only begins a comment when the second dash is followed by whitespace or a control character; `1--1` is `1 - (-1)`. Everything after a bare `--` was therefore invisible to the read-only check, the protected-table check, and the file-access check, while MySQL executed it in full.

Run against the dev database, before the fix:

```
sql        : SELECT 1--1, (SELECT user_login FROM wp_users LIMIT 1)
normalized : SELECT 1
guard says : ALLOWED
returned   : {"a":"2","leaked":"admin"}
```

`SELECT 1--1 INTO OUTFILE '/tmp/x'` normalized to `SELECT 1` and passed the file-access check by the same route.

**(b) The string scanner assumed backslash escaping.** Under `NO_BACKSLASH_ESCAPES` a backslash is an ordinary character, so a literal ends earlier than the scanner believed and real SQL hid inside what it took for a string. `SELECT '\' FROM wp_users WHERE 1` normalized to `SELECT ''` and the protected table vanished.

**Fixed** in `includes/class-database-guard.php`:
- `starts_comment()` implements MySQL's actual rule, so `--` only opens a comment before whitespace or a control character.
- `normalize_sql()` takes the escaping convention as a parameter, and `normalize_variants()` returns **both** readings.
- `is_read_only_sql()` now runs its checks per reading (extracted into `read_only_check()`) and refuses if **any** reading is unsafe. `query_touches_tables()` scans every reading. `trailing_limit()` reports no bound when any reading lacks a top-level LIMIT, so the caller appends one.

Guessing the session's `sql_mode` was rejected as an approach: this code cannot see it reliably, and a wrong guess reintroduces the same class of bug. Checking both readings is safe without being noisy, because the alternative reading of an innocent literal is still innocent.

**Verified live after the fix.** All four attacks refused (`protected_read`, `file_access_blocked`), and none of the legitimate cases became collateral damage:

| Query | Verdict |
|---|---|
| `SELECT 1--1, (SELECT user_login FROM wp_users LIMIT 1)` | **REFUSED** (`protected_read`) |
| `SELECT 1--1 INTO OUTFILE '/tmp/x'` | **REFUSED** (`file_access_blocked`) |
| `SELECT '\' FROM wp_users WHERE user_login LIKE '%'` | **REFUSED** (`protected_read`) |
| `SELECT ID FROM wp_posts WHERE post_title = 'it\'s'` | allowed |
| `SELECT ID FROM wp_posts WHERE post_content = 'C:\\tmp'` | allowed |
| `SELECT ID FROM wp_posts -- a note` | allowed |
| `SELECT 5--2 AS n` | allowed, and returns **7** through the bounding path |
| `SELECT ID FROM wp_posts WHERE post_title = 'wp_users'` | allowed (a literal is not a reference) |

### R3-2 (Medium): F7 was not strictly fail-closed

**Confirmed on both counts.** The response was rejected only after `wp_remote_get()` had already performed the request, so a stream fallback could still cause blind side effects; and `$pin_applied` was set on entry to `pin_curl_address()`, before the pin was validated or applied.

**Fixed** in `pro/includes/ai-chat/class-ai-chat-web-fetch.php`:
- `curl_transport_available()` asks the same question WordPress asks when selecting a transport (`WpOrg\Requests\Transport\Curl::test( array( 'ssl' => true ) )` ), and `fetch()` refuses **before issuing any request** when the answer is no. Probing for the extension was never sufficient: cURL can be present but built without SSL or missing `curl_exec`, in which case Requests silently uses the socket transport and `http_api_curl` never fires.
- `$pin_applied` is now set only after `curl_setopt( CURLOPT_RESOLVE )` returns true. A null or malformed pin, or a `curl_setopt` that does not take, leaves it false and the response is discarded.

The post-request assertion is retained as defense in depth. Verified live: a normal fetch succeeds, and a request whose handle nothing pins is refused with `fetch_unpinned`.

---

## 1. Round-2 findings

### F1 (High): existing vulnerable pairings survived the upgrade

**Confirmed.** `pairing()` read `OPT_PAIRING` with no version gate, and the connector had no upgrade routine at all (`grep` for `version_compare|upgrade|migrat` returned only unrelated comments). A pairing hijacked under 1.0.0 kept full signed restore access.

**Fixed** in `pro/connector/emcp-connector.php.txt` (now **1.2.0**):
- `SCHEMA = 2`, `OPT_SCHEMA`, `OPT_REVOKED`.
- `pairing()` **returns null while the stored schema is stale**, so a stale pairing is unusable *before* any migration runs. This is the important half: it does not depend on the upgrade hook having fired.
- `maybe_upgrade()` on `init` priority 1 (ahead of `rest_api_init`, which fires during `parse_request`) deletes the pairing, sets a revocation flag, and clears pair state.
- `revoked_notice()` tells the admin to pair again.

**Additionally fixed, beyond the finding:** the reviewer's deployment concern in round 1 (a current source could still pair with an outdated connector) is now closed. The connector reports `schema` and `connector` on a successful pair, and the source refuses the relationship when `schema < 2`. A pre-1.1 connector is detected **by the absence of the field**, not by trusting a version string it supplies.

### F2 (Medium): the SQL row limit remained bypassable

**Confirmed.** `can_append_limit()` returned false whenever `\blimit\b` appeared anywhere, so both of the reviewer's examples fetched unbounded.

**Fixed** in `includes/class-database-guard.php`:
- `can_append_limit()` is now a **shape check only** (SELECT/WITH).
- `trailing_limit()` reads the row count of a **top-level trailing** LIMIT across all three syntaxes (`LIMIT n`, `LIMIT skip, n`, `LIMIT n OFFSET skip`), anchored at end so a subquery LIMIT returns null.
- `bound_sql()` appends when there is no top-level limit, returns unchanged when the caller's limit is at or under the cap, and **refuses** (`limit_too_large`) when it is above. Rewriting someone else's LIMIT inside raw SQL is not safe around string literals, so it is refused rather than silently clamped.

**A third hole was found while fixing this, not in the report:** the old code appended `' LIMIT n'` with a leading space, so `SELECT 1 -- note` became `SELECT 1 -- note LIMIT 101` and the clause was swallowed by the comment, running unbounded. The clause is now appended after a newline.

### F3 (Medium): OAuth throttle trivially bypassable

**Confirmed.** `HTTP_CF_CONNECTING_IP` and `HTTP_X_FORWARDED_FOR` were read before `REMOTE_ADDR` with no trusted-proxy check.

**Fixed** in `includes/oauth/class-oauth-clients.php`:
- New `client_ip()` uses `REMOTE_ADDR` and honours forwarded headers **only** when the peer is listed by the `emcp_tools_trusted_proxies` filter (empty by default). Forwarded values must also pass `FILTER_VALIDATE_IP`.
- New `unauthorized_at_capacity()` (`MAX_UNAUTHORIZED = 500`, filterable) refuses registration with 503 once that many rows have never completed authorization. **This, not the per-IP throttle, is the control that actually bounds table growth**, because it cannot be sidestepped by rotating address or header.

The reviewer's note that the transient counter is non-atomic is accepted and **not** fixed. It is documented in the code as best-effort, with the capacity ceiling as the hard bound.

### F4 (Medium regression): server gate metadata-driven, UI gate not

**Confirmed**, and this was a regression I introduced: the server correctly refused 13 destructive tools the browser could not present for approval, so those calls were simply unusable.

**Fixed:** new `EMCP_Tools_AI_Chat_Controller::destructive_tool_names()` derives the list from ability metadata (union with the static constant as a floor), and `class-ai-chat-page.php` feeds the client from it. Server and client now read the same source.

### F5 (Low): plaintext pairing code persisted

**Confirmed, and the round-1 report's claim was wrong.** `set_transient()` writes to `wp_options` without a persistent object cache, and `clear_pair_state()` did not remove it.

**Partially fixed:** the transient is now deleted by `clear_pair_state()` and its TTL drops from 5 minutes to 60 seconds. **It is still written to `wp_options` for that window.** See section 4.

### F6 (Low): pairing rate limit was a DoS

**Confirmed.** The counter was global.

**Fixed:** `pair_fail_key()` keys the counter by `md5(REMOTE_ADDR)`. Forwarded headers are deliberately **not** consulted here, since this route is unauthenticated. Verified: an attacker exhausting its budget does not rate-limit the legitimate source.

### F7 (Low): pinning not strictly fail-closed

**Confirmed.** Checking `curl_init()` up front cannot see that WordPress selected the stream transport.

**Fixed** with a per-request assertion rather than a capability probe: `$pin_applied` is reset before each hop, set inside `pin_curl_address()`, and checked after the response. If our pin never reached a handle, the response is **discarded** with `fetch_unpinned`. This catches any transport fallback, not only the ones anticipated.

### F8 (Low): denylist-based redirect URI validation

**Confirmed.** `https:relative`, `ftp://…`, and `mailto:…` were all accepted.

**Fixed:** `https` now requires a host; a new `RESERVED_SCHEMES` list refuses IANA transport and messaging schemes; and `emcp_tools_oauth_redirect_scheme_allowlist` lets a deployment pin an exact allowlist.

This is **not** a pure allowlist by default, deliberately. Native MCP clients register single-word private-use schemes (`vscode://`, `cursor://`) that cannot be enumerated in advance, so defaulting to strict allowlisting would break real clients. The filter exists for deployments that know their client set.

---

## 2. Corrections to the round-1 report

Three claims were wrong. Stating them plainly:

1. **"the plaintext is never persisted"** (SEC-01). False. The one-view display copy goes through `set_transient()`, which lands in `wp_options` absent a persistent object cache. Reduced and cleaned up, not eliminated.
2. **"381 first-party files linted."** Undercounted. `git ls-files` from the repo root does not descend into the `pro/` submodule, so only the free tree was measured. The real figure is **825** (380 free + 444 pro + 1 connector).
3. **"all 5 findings are remediated."** Four of the five were incomplete, as round 2 established.

The round-1 SEC-01 harness was also unreproducible because it lived in a scratch directory. It is now committed at **`pro/tests/connector/connector-security-check.php`** and extended to cover F1, F5, and F6.

---

## 3. Verification

| Suite | Result |
|---|---|
| Pro PHPUnit | **2148 tests, 7184 assertions, 2 skipped, OK** (was 2112: +36), stable over repeated runs |
| Public PHPUnit | **155 tests, 382 assertions, OK** |
| `Release3124HardeningTest` (new) | 19 tests |
| `SqlLexerTest` (rounds 3 to 5) | 17 tests, 53 assertions |
| `pro/tests/connector/connector-security-check.php` | **25 passed, 0 failed** |
| PHP lint, PHP 8.4.15 | **826 files, 0 errors** (free + pro + connector) |

`Release3123HardeningTest` line 61 was updated: `can_append_limit()` is now a shape check, so an existing LIMIT no longer makes it return false.

### Live verification against a real database and HTTP stack

Run on the dev site (`wp_posts` holds **218** rows), against `EMCP_Tools_Database_Guard::bound_sql()` and the real `$wpdb`:

| Case | Result |
|---|---|
| `SELECT * FROM wp_posts LIMIT 1000000000` | **REFUSED** (`limit_too_large`) |
| `SELECT p.* FROM wp_posts p JOIN (SELECT 1 LIMIT 1) x ON 1=1` | **101 rows** (bounded, not 218) |
| `LIMIT 5, 1000000` | **REFUSED** |
| `LIMIT 1000000 OFFSET 5` | **REFUSED** |
| `SELECT ID FROM wp_posts -- note` | **101 rows** (comment did not swallow the clause) |
| `SELECT ID FROM wp_posts LIMIT 3` | 3 rows (caller's limit preserved) |

| Check | Result |
|---|---|
| normal fetch | ok |
| fetch with nothing pinning the handle | **REFUSED** (`fetch_unpinned`) |
| `client_ip()` under three rotated forwarded headers | bucket stable at the peer address |
| `https:relative` / `ftp://` / `mailto:` | refused |
| `https://app.example.com/cb` / `vscode://` / `http://127.0.0.1:5173/cb` | accepted |

Connector check highlights: a stale pairing yields `null` and an empty HMAC secret **before** the upgrade runs; the upgrade deletes it and flags re-pairing; the upgrade is idempotent; a current-schema pairing is untouched; an attacker exhausting the failure budget does not lock out the legitimate source.

### Reproduction

```bash
php vendor/phpunit/phpunit/phpunit                              # 2148 OK, 2 skipped
php vendor/phpunit/phpunit/phpunit -c tests/phpunit.xml          # 155 OK
php vendor/phpunit/phpunit/phpunit --filter Release3124Hardening # 19 OK
php vendor/phpunit/phpunit/phpunit --filter SqlLexer             # 17 OK
php pro/tests/connector/connector-security-check.php             # 25 passed, 0 failed

# against the source as actually shipped:
unzip -p releases/emcp-pro-3.12.4.zip '*/connector/emcp-connector.php.txt' > /tmp/shipped.php.txt
php pro/tests/connector/connector-security-check.php /tmp/shipped.php.txt
```

**On the Composer audit:** round 1 used Composer 2.10.2 downloaded to a scratch directory, which is why it was unreproducible. `audit` requires Composer 2.4+. To reproduce: `curl -sSL -o composer.phar https://getcomposer.org/download/2.10.2/composer.phar && php composer.phar audit`. Last result: *No security vulnerability advisories found* across `automattic/jetpack-autoloader` 5.0.20, `wordpress/mcp-adapter` 0.5.0, `wordpress/php-mcp-schema` 0.1.2.

---

## 4. Still open, stated plainly

1. **The plaintext pairing code still reaches `wp_options` for up to 60 seconds** (F5). Eliminating it means rendering the arm result inline instead of redirecting, which risks breaking the pairing screen on destination sites I cannot live-test. Judged not worth that risk for a value whose disclosure requires database read access, which already exposes the pairing secret itself. The false claim has been corrected rather than papered over.
2. **The registration throttle is still non-atomic** (F3). A simultaneous burst can slip a few past the per-IP cap. `unauthorized_at_capacity()` is the hard bound.
3. **Redirect scheme validation is not a pure allowlist by default** (F8), for client-compatibility reasons given above. The filter makes strict mode available.
4. **Connector packet and archive size bounds are still deferred** (round 1, SEC-01). Now that pairing is authenticated *and* stale pairings are revoked *and* the source refuses outdated connectors, the remaining exposure is an authenticated paired source sending an oversized archive, which is a party that can already replace the destination's database and files by design.
5. **PHP 8.1 (the declared floor) is still unlinted.** Only 8.4.15 / 8.5.0 / 8.5.1 exist on this machine. Linting on a newer runtime does not prove 8.1 parse compatibility. Manual review found no post-8.1 syntax in the changed code, but that is not a machine check.
6. **`tools/verify-release-zip.sh` reports a false failure on the Pro ZIP.** It looks for an `emcp-tools/` path prefix in an archive rooted at `emcp-pro/`. `emcp-pro/emcp-tools.php` and `emcp-pro/bin/mcp-proxy.mjs` are both present; verified with `unzip -l` and `unzip -t`. The script needs fixing, separately from this release.

---

## 5. Artifacts

| Artifact | Entries | SHA-256 |
|---|---|---|
| `emcp-tools-3.12.4.zip` (free, GitHub) | 927 | `7b110ff15995e985120ea2ba3b7aeaf1a3f57687b9e618a24c018c8d9013846e` |
| `emcp-pro-3.12.4.zip` (premium, Freemius) | 1115 | `08041df401892df1663bdb35ce5d5ebf24f243a399efcb083d48cbf3e4f8c029` |

Packaging checks: 0 Pro-manifest leaks in the free ZIP; connector present **only** in the Pro ZIP at version **1.2.0**; 0 test files in either ZIP; `.emcp-pro` marker absent from free and present in premium; both archives pass `unzip -t`.

Extracted trees for review: `releases/emcp-tools/` and `releases/emcp-pro/`. A plain `diff` will flag every file in the free tree because `git archive` emits CRLF on this Windows host while the working tree is LF: use `diff --strip-trailing-cr`, or compare against `git show HEAD:<path>`.

---

## 6. What to challenge next

1. **`bound_sql()` refusing rather than clamping.** It changes behaviour for anyone whose saved query carries a large LIMIT. I judged an explicit error safer than rewriting raw SQL, but it is a UX regression worth a second opinion.
2. **`trailing_limit()` is regex over normalized SQL.** Comments and string literals are already stripped by `normalize_sql()`, but please probe for a construction where a top-level LIMIT escapes all three patterns and lands in the append branch, producing two LIMIT clauses and a syntax error (fails closed, but noisily).
3. **The `pin_applied` assertion** assumes `http_api_curl` fires for every pinned request in the cURL path. If any code path could set the flag without the pin actually being applied, the assertion is weaker than it looks.
4. **`schema < 2` refusal on the source** relies on an old connector omitting the field rather than on a version comparison. Confirm no pre-1.1 connector emits a `schema` key.
5. **`MAX_UNAUTHORIZED = 500`** as a default: high enough not to lock out a busy legitimate site, low enough to bound growth?
6. **The normalizer is still a hand-written lexer, not MySQL's parser.** Two divergences were found; assume there are more. Specific things worth probing: `/*` inside a string literal, backtick-quoted identifiers containing a quote (``` `a``b` ```), `$$`-style or other dialect quoting, multi-byte input where a lead byte could mask a delimiter, and `#` inside a string. The dual-reading design bounds the damage from an escaping mistake but not from a delimiter the lexer does not model at all.
7. **The lexer has now been wrong four times** (the comment rule, backslash escaping, the backtick pre-strip, and ANSI_QUOTES). Three of those exfiltrated real data, and two were introduced by an earlier fix in this same report. The structural alternative is now the recommendation rather than a suggestion: refuse raw SQL whose token stream the lexer cannot fully account for, instead of chasing MySQL parity by hand. The one concrete gap still unprobed is a legacy connection charset (GBK, SJIS, BIG5) where a multi-byte lead byte can mask a delimiter, which a utf8mb4 dev box cannot exercise.
8. **`Curl::test()` as the transport oracle.** It matches Requests' current selection logic, but a plugin that registers its own transport via `Requests::add_transport()` could still displace cURL. The post-request assertion catches that, but only after the request. Is there a pre-request way to see the final transport?
