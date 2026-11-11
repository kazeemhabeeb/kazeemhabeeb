# Requirements Traceability

Every objective and requirement in the approved proposal (§1.3 objectives, §3.2.1 functional requirements,
§3.2.2 non-functional requirements), where it is built, and the proof that it works.
Checked on 11 November 2026 against the live site (https://trustfeed.habeeb.website), the code on `main`
and a fresh run of the automated tests.

**Result: all 4 objectives, 13 functional and 10 non-functional requirements are met.** Two have notes on
wording (NFR-1.1, NFR-4.1), explained at the end.

Automated tests re-run on 11 November 2026 (no failures):

| Test | Checks | Result |
|---|---|---|
| `app/tests/test_sanitize.php` | 18 | All passed |
| `app/tests/test_tier1.php` | 7 feed items + 13 edge cases | All as expected |
| `app/tests/test_ai.php` | 45 | All passed |
| `app/tests/test_security.php` | 18 | All passed |
| `app/tests/test_second_opinion.php` | 14 | All passed |

## Objectives (§1.3)

| # | Objective | Built in | Proof |
|---|---|---|---|
| 1 | Automated script fetching articles from APIs and RSS feeds | `app/cron/ingest.php`, `app/includes/feed.php` (RSS 2.0 and WordPress REST API JSON) | Live: 7 sources read every hour (P.M. News, Premium Times = RSS; BusinessDay = news API); Execution logs every hour since 9 Nov; `ingestion-test-results.md` |
| 2 | Sanitization layer stripping XSS | `app/includes/sanitize.php` (`sanitize_item`, strip → decode → strip, http/https-only URLs) + `e()` on every output | `test_sanitize.php` 18/18 (a `<script>` headline and `javascript:` image removed); `test_security.php` 18/18; `screenshots/sprint3/article-xss-test.png` |
| 3 | NLP service that reads the text, checks clickbait/false claims, gives a credibility score | `app/includes/ai.php` (GPT-4o via RapidAPI) + tier-1 rules `app/includes/tier1.php` | Live: 316 Real / 88 Misinformation verdicts with scores and reasons (dashboard, 11 Nov); `ai-verification-test-results.md` |
| 4 | Public UI publishing the feed with visible AI verdicts | `app/public_html/index.php`, `article.php`, `js/feed.js` | Live site; `screenshots/sprint3/feed-desktop.png`, `article-real.png`, `article-misinformation.png` |

## Functional requirements (§3.2.1)

| ID | Requirement | Built in | Proof |
|---|---|---|---|
| FR-1.1 | Background script every 1 hour, all active feeds, new articles | `app/cron/ingest.php` (line 59: every Active source; line 17: command line only); Hostinger cron `0 * * * *` | System Logs: one Execution entry every hour (e.g. 01:01, 02:00, 03:00 … 07:02 on 11 Nov) |
| FR-1.2 | Clean HTML; JSON with title, body, source URL, timestamp | `sanitize.php` line 14 `sanitize_item()`; `ai.php` `build_prompt()` builds exactly `{title, body, source_url, publication_date}` | `test_ai.php`: "prompt has title, body, source_url, publication_date" passes |
| FR-1.3 | Send the payload by HTTP REST API to the classification service | `ai.php` line 107 `call_ai()` (cURL POST to RapidAPI) | Logs: "AI verification: 9 sent (limit 9), 8 verified" (07:02, 11 Nov); RapidAPI quota usage rising (7.22%) |
| FR-1.4 | Store verdict (Real / Misinformation) and % confidence | `ai.php` line 26 `classify_score()` (≥ 50 Real), line 246 saves score + verdict | Dashboard: Real 316, Fake 88; badges such as "Real – 95% Confidence" |
| FR-2.1 | Public feed, no login | `public_html/index.php` (no session check) | Live site opens without login; `public-site-test-results.md` |
| FR-2.2 | Colour-coded badge with label and % on each card | `public.php` line 47 `badge_html()`, `js/feed.js` | `screenshots/sprint3/feed-desktop.png` |
| FR-2.3 | AJAX/Fetch loading of news and categories, no page reload | `js/feed.js` line 98 `fetch()`, refresh every 5 minutes (line 9); `api/articles.php` | Category and page changes without reload (`public-site-test-results.md`) |
| FR-2.4 | View counter per article | `article.php` line 21 `view_count = view_count + 1` | Article #150: 0 → 1 view; "Top 10 most viewed" on the dashboard |
| FR-3.1 | Secure password-protected admin portal | `app/includes/auth.php` (`check_login`, `require_admin` on every admin page and AJAX endpoint) | `admin-test-results.md` (wrong password refused, lockout after 5, `screenshots/sprint4/login-wrong-password.png`); you log in at /admin/ |
| FR-3.2 | Add, edit, enable, disable, delete feed sources | `admin/sources.php` lines 85 (add), 90 (edit), 97 (enable/disable), 103 (delete) | You added, deleted and disabled sources live (Punch, Vanguard, Channels…); `screenshots/sprint4/sources-*.png` |
| FR-3.3 | Search, re-flag, delete articles | `admin/api/articles.php` line 25 (search), `admin/api/article_action.php` lines 38 (re-flag), 50 (delete) | `test_articles_browser.py`: 11 checks; `screenshots/sprint4/articles-reflag.png`, `articles-delete-confirmation.png` |
| FR-3.4 | Dashboard: total posts, fake vs real ratio, feed statuses, views per article | `admin/dashboard.php` (cards, ratio bar, feed status table with Last run OK/Failed, top 10 views) | Live dashboard 11 Nov: 413 posts, Real 78.2% / Fake 21.8%, ratio 1 : 3.6; `screenshots/sprint4/dashboard.png` |
| FR-3.5 | Execution logs, highlighting API timeouts and failed feeds | `ingest.php` / `ai.php` write logs; `admin/logs.php` (yellow = API timeout, red = API error / RSS feed failed, filters, 24 h banner) | Live logs: API timeout (yellow) for article #481, API errors (red) during the Search GPT outage; `screenshots/sprint4/logs.png` |

## Non-functional requirements (§3.2.2)

| ID | Requirement | Built in | Proof |
|---|---|---|---|
| NFR-1.1 | Prepared SQL statements and `htmlspecialchars` against SQLi and XSS | 31 `mysqli_prepare()` statements, no SQL built from input; `functions.php` line 7 `e()` = `htmlspecialchars` on every output | `test_security.php` 18/18 (SQL injection string stored as plain text); see note 1 |
| NFR-1.2 | Secure sessions, automatic timeout, bcrypt | `auth.php`: HttpOnly + Secure + SameSite cookies, `session_regenerate_id` at login, 30-minute idle timeout, 8-hour limit; `tools/create_admin.php` `password_hash(PASSWORD_BCRYPT)` | `admin-test-results.md` #9: logged out after 31 idle minutes; CSRF tokens refuse forged requests (HTTP 403) |
| NFR-2.1 | AI outage → site keeps working, articles show "Pending Verification" | `ai.php` `verify_pending()`: any failure leaves the article Pending, retried next hour; 3 failures in a row stop the run | Live: RapidAPI 502s and timeouts, and the 10-hour Search GPT outage on 10–11 Nov; the site stayed up and the articles showed yellow "Pending Verification"; `screenshots/sprint3/article-pending.png` |
| NFR-2.2 | Foreign keys and unique URLs, no duplicates | `schema.sql`: `UNIQUE KEY uq_articles_link (original_link)`, `fk_articles_source` foreign key | Every hourly log: "92–112 duplicate link(s) skipped"; second test run saved nothing twice (`ingestion-test-results.md`) |
| NFR-3.1 | Responsive on desktop, tablet, phone | Start Bootstrap Blog Home and SB Admin layouts (Bootstrap 5 grid) | `screenshots/final/phone-pages.png`, `tablet-feed.png`, `tablet-dashboard.png`, `sprint3/feed-mobile-menu.png` |
| NFR-3.2 | Green / Red / Yellow indicators | `public.php` `badge_html()`: `bg-success`, `bg-danger`, `bg-warning`; key in the sidebar | Screenshots above; live site |
| NFR-4.1 | Process, sanitize, save each source in under a second | `ingest.php` times each source and logs the slowest | Live logs: "Slowest source: P.M. News, 0.005–0.008 s to process and save"; see note 2 |
| NFR-4.2 | Public page under 2 seconds | Indexed queries (`idx_articles_date`), 5 articles per page, 324 KB first visit | `final-test-results.md`: feed shown in 112 ms (123 ms with 10,152 articles) |

## Notes

1. **NFR-1.1 names PDO; the app uses MySQLi prepared statements.** The rest of the proposal (§3.5 p. 38, §3.7
   p. 41, §3.8 p. 42, slide 7) names MySQLi, so MySQLi was used (decision 6). Both give the same protection:
   the SQL and the data are sent separately.
2. **NFR-4.1 counts processing, sanitizing and saving**, which take 0.005–0.023 s per source. Downloading a feed
   over the internet takes longer (0.05–3.7 s in the logs) and depends on the publisher's server, so it is logged
   separately.

Extra features beyond the proposal, each recorded with a reason in `build-decisions.md`: tier-1 PHP rules
(slide 8 "tiered"), the "Why this score" explanation and flags (§3.1.1), article images, login lockout, HTTPS and
security headers, automatic deployment from GitHub, and the web-search second opinion (decision 32; switched off).
