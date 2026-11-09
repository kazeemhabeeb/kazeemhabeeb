# Final Test Results (Sprint 4 polish)

Evidence for Chapter 4. Tested on 9 November 2026 in Chromium, with the app served by PHP 8.3 and
MariaDB 10.11 on the test machine. Screenshots: `docs/screenshots/final/`.

## 1. Responsive design (NFR-3.1)

Every page was opened at phone (390 px), tablet (768 px) and desktop (1280 px) widths, and the browser was asked
whether the page was wider than the screen (which would force sideways scrolling).

| Page | Phone | Tablet | Desktop |
|---|---|---|---|
| News feed | ✓ | ✓ | ✓ |
| Article | ✓ | ✓ | ✓ |
| Admin login | ✓ | ✓ | ✓ |
| Dashboard | ✓ ¹ | ✓ | ✓ ¹ |
| RSS Sources | ✓ ¹ | ✓ | ✓ |
| Articles | ✓ ¹ | ✓ ¹ | ✓ |
| System Logs | ✓ ¹ | ✓ | ✓ |

¹ No page overflowed. Wide admin tables scroll sideways **inside their own box** (Bootstrap's
`.table-responsive`), which is the template's intended behaviour on small screens.

**Problem found and fixed:** on phones the admin top bar was cramped ("View site" wrapped onto two lines next
to the admin menu). On small screens these now show their icons only (`admin-topbar-phone-fixed.png`); the
words return from tablet width up.

## 2. Page speed (NFR-4.2: public feed under 2 seconds)

Median of 5 page loads (feed page until the first article card is drawn) and 10 requests for the others:

| Measurement | 152 articles (today's live data) | 10,152 articles (10,000 temporary test articles added) |
|---|---|---|
| Feed page shown | **112 ms** | **123 ms** |
| Feed data (`api/articles.php`, page 1) | 2 ms | 7 ms |
| Feed data, category "News", page 3 | 2 ms | 6 ms |
| Article page | 2 ms | 6 ms |
| Data downloaded on a first visit | 324 KB | 325 KB |

- With 66 times more articles the feed page was only about 11 ms slower. MySQL's plan (`EXPLAIN`) shows
  the feed queries read the `idx_articles_date` index and stop after 5 rows, rather than sorting the whole table.
- The page is light: 324 KB on a first visit, mostly the template's stylesheet and Bootstrap's script, which
  the browser then keeps; each page of articles after that is a few KB of JSON.
- These times were measured on the same machine as the server. On real hosting, the visitor's connection adds
  network time; at 324 KB, even a slow 4 Mbit/s connection adds well under a second, leaving a wide margin
  inside the 2-second target.
- The temporary 10,000 articles were deleted after the test.

NFR-4.1 (sub-second processing per source) was measured in Sprint 1: at most 0.023 s per source
(`ingestion-test-results.md`).

## 3. Security review (Sprint 4 "rigorous security audits")

A separate reviewer read every PHP and JavaScript file looking for exploitable problems. It found **no SQL
injection, cross-site scripting, CSRF, login-bypass or open-redirect bugs**, and confirmed: prepared statements
everywhere (including LIMIT/OFFSET), escaping of every printed value in PHP and JavaScript, the
strip-decode-strip sanitizer, CSRF checks on every change, session protections, CLI-only scripts, safe XML
parsing (no external entities), and the API key never leaving the server.

It reported hardening issues. Each was checked against the code, confirmed, and fixed:

| # | Issue | Fix | Proof |
|---|---|---|---|
| 1 | **Server-side request forgery:** a feed URL, or a redirect sent by a feed, could make the server fetch its own internal services (127.0.0.1, 10.x, 169.254.169.254…); the add-source error message could be used to probe ports | `check_public_url()` refuses local, private and reserved addresses and connects to the address it checked; redirects are followed by hand (max 3), each one checked; the admin sees a general error | `test_security.php`: 7 blocked addresses, redirect loop stopped, redirect to `file://` refused, live Punch redirect still works |
| 2 | No HTTPS enforcement or HSTS | `FORCE_HTTPS` setting: every page redirects http → https and sends `Strict-Transport-Security`; the session cookie is always `Secure` in production; `.htaccess` also moves static files to https; images are upgraded to https | With the setting on, all four page types answered 301 → https:// |
| 3 | A huge or "zip bomb" feed could exhaust memory and stop the hourly run | Replies over 5 MB (after unzipping) are refused while downloading | 6.4 MB reply and a 50 KB file that unzips to 50 MB both refused; memory stayed under 20 MB |
| 4 | Password guessing (parallel requests) and username discovery by timing | 5 wrong passwords from one IP in 15 minutes locks that IP out for 15 minutes; each failure is a "Login failed" log entry (shown and highlighted on the Logs page); `password_verify` always runs | After 5 wrong tries the correct password was refused with "Too many wrong passwords…"; known and unknown usernames answered in 1.076 s and 1.078 s |
| 5 | The hourly lock file was in the shared `/tmp` | Moved to the app's own folder; a failure to create it is logged | Hourly script runs normally |
| 6 | If the whole app were uploaded under the web root, `database/schema.sql` and folder listings could be exposed | `.htaccess` "deny all" in config, includes, database, cron, tools, tests; `public_html/.htaccess` turns off folder listings and refuses hidden, backup and .sql files | Files present in each folder |
| 7 | An article could steer the AI's reason to show a link or long message under "Why this score" | Web addresses are removed from the reason and it is capped at 300 characters | "Claim your prize at https://evil.example/win or www.evil.example" → links removed |
| — | Smaller notes | Admin sessions end after 8 hours even if active; `LIKE` searches on stored URLs no longer treat `%`/`_` as wildcards; a direct visit to an admin API without a session goes to the right login page | 9-hour-old session sent to login with "expired" |

**Remaining limitations (for the report):** view counts can be inflated by reloading an article (FR-2.4 counts
every view); an article written to fool the AI can still get a wrong score, as can any LLM-based check
(decision 29); a name that changes its address after being checked (DNS rebinding) is only partly covered, because
the check pins the address it looked up when the host can resolve names itself.

All earlier tests were run again after these changes and still pass: sanitizing (18), tier 1, AI checks (34),
login (11), articles page (11).
