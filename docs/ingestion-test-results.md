# Ingestion Test Results (Sprint 1)

Evidence for Chapter 4. Script: `app/cron/ingest.php`.

## 1. Saved test feeds (`app/tests/fixtures`)

Five sources: the RSS fixture, the WordPress API fixture, a site that is down, a page that is not a
feed, and an Inactive source.

| Run | Result |
|---|---|
| 1 | 4 active sources read (2 failed), 5 new articles saved (1 flagged as clickbait), 2 spam filtered |
| 2 | 0 new, 5 duplicate links skipped, 0 spam (already logged) |

Checks: the Inactive source was skipped; failures were logged as "RSS feed failed" and the run continued;
the XSS item was saved clean; "Doctors HATE this one fruit…!!!" got `is_clickbait = 1`; the sponsored and
untitled items were not saved; opening the script in a browser returned **403 Forbidden**.

## 2. Live news sources (9 November 2026)

| Source | Address type | Result |
|---|---|---|
| P.M. News | RSS | 30 articles |
| Premium Times | RSS | 15 |
| Channels Television | RSS | 10 |
| BusinessDay | News API (WordPress JSON) | 9 on run 1, 10 after the tier-1 fix below |
| Vanguard | RSS | 20 |
| Daily Post | RSS | 20 |
| The Onion | RSS | 12 |
| The Guardian Nigeria | RSS | **Failed: HTTP 403.** The site blocks automated requests. Logged as "RSS feed failed"; an example of the bot-blocking limitation in §1.6 (p. 5) |

| Run | Result |
|---|---|
| 1 | 8 active sources read (1 failed), 116 new articles saved, 1 spam filtered, 2.5 s in total. Slowest source: P.M. News, 0.020 s to process and save (+0.34 s download) |
| 2 | 0 new, 116 duplicate links skipped, 1.8 s. Slowest: P.M. News, 0.009 s (+0.17 s download) |

- **NFR-4.1** (sub-second per source): processing and saving took at most 0.020 s per source. Download time
  depends on the publisher's server and is reported separately.
- **Sanitization:** no saved title or summary contains an HTML tag or entity, and no "appeared first on"
  footer was left.
- **NFR-2.2:** the second run saved nothing twice.

### A tier-1 problem found and fixed

Run 1 rejected a real BusinessDay article ("Kaduna’s dairy revolution targets Nigeria’s $1.5bn milk import
market") as "not written in English": only 1 of its 31 words was on the common-English-word list. The list
was widened (words that are also French, such as "a" and "on", left out) and the threshold set to 3 in 100.
All 117 live articles then passed (lowest share 6.8%), while the French and Spanish test texts still
scored 0%. The article was saved on the next run. This case is now in `app/tests/test_tier1.php`.

### Other sources tried

- **Punch** redirects its feed to `rss.punchng.com`, a separate address this test environment does not allow.
  Not a problem on cPanel hosting.
- **The Babylon Bee** feed returned no items on the test day (the publisher's feed was empty).
