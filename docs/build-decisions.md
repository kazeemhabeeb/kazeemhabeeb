# Build Decisions

Rule for the build: follow the approved proposal and slides. Where the proposal answers a question,
we follow it and cite the page. We decide ourselves only where the proposal contradicts itself or
says nothing.

## Agreed

| # | Decision | Proposal reference |
|---|---|---|
| 1 | Ingest **every** newly published article from **all active** sources each hour. No per-source limit. | FR-1.1 (p. 30) |
| 2 | Articles the AI service could not verify stay **Pending Verification** and are retried on the next run. | NFR-2.1 (p. 32) "temporarily display"; §3.3.2 Sprint 2 (p. 33) |
| 3 | Add a **tier-1 deterministic filter** in PHP before the AI service. | §2.5.1 (p. 28); §2.3 (p. 16); slide 6 (gap); slide 8 ("tiered") |
| 4 | Send the AI service a JSON payload with exactly **title, body text, source URL, publication timestamp**. | FR-1.2 (p. 30) |
| 5 | **Store** the AI's explainable flags (clickbait, emotional manipulation, bias) and show them as alert indicators. | §3.1.1 (p. 29); §1.5 (p. 4) |
| 6 | Use **MySQLi** prepared statements. NFR-1.1 says PDO; every other section says MySQLi. | §3.5 (p. 38), §3.7 (p. 41), §3.8 (p. 42), slide 7 |
| 7 | AI service: GPT-4o via the PR Labs "ChatGPT 4" API on **RapidAPI**. The proposal names a RapidAPI service but no product; the Fact Checker API was tested and rejected (see `api-test-results.md`). | §3.5 (p. 38) |
| 8 | Sources can be an **RSS feed or a news API** (WordPress REST API JSON). Both use the same `rss_url` column, so the schema does not change. The script detects XML or JSON from the reply. | Objective 1 (p. 3); slide 3; §2.5.1 (p. 28) "stable API and RSS endpoints" |
| 9 | **Keep article images** (`image_url`). Not required by the proposal; kept for usability. Only http/https URLs are stored and they are escaped on output. | Not in the proposal. Relates to the image-tag XSS risk in §1.2 (p. 3) |
| 10 | Verdict rule in PHP: score ≥ 50 → Real, < 50 → Misinformation, no usable reply → Pending Verification. | §3.4.2B step 6 (p. 36); §3.7 (p. 41) |
| 11 | Deleting a source deletes its articles (`ON DELETE CASCADE`). Needed because the foreign key would otherwise block the delete. | FR-3.2 (p. 31); NFR-2.2 (p. 32) |
| 12 | Tier-1 rules. **Spam** (not saved, logged): no title or no valid link; mostly non-English text; title or category marked Sponsored, Advertorial or Promoted. **Clickbait pattern** (flagged, still sent to the AI): phrases such as "you won't believe", "what happened next", "will shock you", "this is why"; two or more exclamation marks; three or more ALL-CAPS words in the headline. | §2.5.1 (p. 28); §2.3 (p. 16); §1.5 (p. 4) English only |
| 13 | Flags stored in three columns on `articles`: `is_clickbait` (yes/no, set by tier 1 **or** the AI), `emotional_manipulation` (yes/no), `bias_level` (none/low/high). | §3.1.1 (p. 29); §1.5 (p. 4); §3.6.1 (p. 39) "highly normalized" |
| 14 | At most **9 AI calls per hourly run** (a setting), flagged articles first, then newest. Ingestion is not capped: every new article is still saved each hour. Articles over the cap stay Pending and are verified in a later hourly run. 9 per hour × 24 = 216 calls a day, inside the Pro plan's ~222 (100,000 credits ÷ 15 per call ÷ 30 days). | FR-1.1 (p. 30) hourly run; §1.6 (p. 5) usage caps; NFR-2.1 (p. 32); §2.5.1 (p. 28) flagged content first |
| 15 | **No search on the public site.** Public use cases are only view the feed, read a summary and see the score; search is an admin feature. The Blog Home sidebar keeps the Categories box. | Use cases §3.4.2A (p. 35); FR-3.3 (p. 31) |
| 16 | **Re-flagged articles show the label without a percentage** (e.g. "Real – set by admin"). Re-flagging clears the AI score, reason and flags, since the AI result no longer applies. | FR-3.3 (p. 31); FR-2.2 (p. 31), explained in the report |
| 17 | The public feed **re-checks for new articles every 5 minutes** by AJAX, without a page reload. | FR-2.3 (p. 31) "load and refresh" |
| 18 | **CSRF tokens** on every admin form and admin AJAX action. Not visible on screen. | §3.8 (p. 42) "data tampering"; NFR-1.2 (p. 32) |
| 19 | **Alert indicators:** full flags (clickbait, emotional manipulation, bias) on the article page under "Why this score"; a small ⚠ label on the feed card only when a flag is set. | §3.1.1 (p. 29) "alert indicators" |
| 20 | Dashboard also shows the **Pending Verification** count. | FR-3.4 (p. 31) |
| 21 | Logs add a **"Spam filtered"** type for tier-1 results, alongside Execution, API timeout, API error and RSS feed failed. | FR-3.5 (p. 31); §2.5.1 (p. 28) |
| 22 | **One admin account**, created by a setup script. No sign-up page, no "Run ingestion now" button: only the cron job starts ingestion. | FR-3.1 (p. 31); use cases (p. 35) |
| 23 | Sessions: `password_hash()` (bcrypt), new session ID at login, cookies HttpOnly + Secure + SameSite, logout after 30 minutes idle; every admin page **and** admin AJAX endpoint checks the session. | NFR-1.2 (p. 32); §3.8 (p. 42) |
| 24 | The ingestion script runs only from the command line (cron) and refuses browser requests. | §3.8 (p. 42) "unauthorized access" |
| 25 | Security test evidence: a test feed with `<script>` in a headline and an SQL injection string in a summary; screenshots go in Chapter 4. | §3.3.2 Sprint 4 (p. 34) |
| 26 | **Pending Verification articles are shown on the public feed** with the yellow badge. NFR-2.1 and NFR-3.2 require it; §1.2 (p. 3) and §3.1.2 (p. 29) say verdicts come before the reader sees content, so the report explains that a Pending article is never shown unlabelled. | NFR-2.1 (p. 32); NFR-3.2 (p. 32); §3.3.2 Sprint 2 (p. 33) |
| 27 | **No "the AI can be wrong" line in the interface.** It is not in the proposal and conflicts with §1.4 (p. 4); the AI's fallibility goes in the report's Limitations instead. | §1.4 (p. 4); §1.6 (p. 5) |
| 28 | Tier-1 rule details, so each rule stays fair: "Sponsored/Advertorial/Promoted" counts only at the **start of the title** or as the **whole category** (so "Officers promoted…" is news); known abbreviations (NNPC, CBN, EFCC, INEC…) do **not** count as ALL-CAPS words; "not English" means mostly non-Latin letters, or a 15+ word text where fewer than 3 words in 100 are common English words (headlines alone are too short to judge). The first version (1 in 20, shorter word list) wrongly rejected a real BusinessDay article in the 9 November 2026 live test; widened and re-tested on all 117 live articles. | §2.5.1 (p. 28); §1.5 (p. 4) |
| 29 | **The AI only sees the feed's short summary**, not the full article. When a publisher's summary is a quote or teaser, GPT can misjudge a real story (P.M. News "Who is Alice Huang…?" scored 15). Recorded as a limitation in the report; the admin can correct such cases by re-flagging (FR-3.3). No page scraping. | §2.5.1 (p. 28); FR-3.3 (p. 31); `docs/ai-verification-test-results.md` |
| 30 | **Security hardening from the final review:** feeds on local or private network addresses are refused and every redirect is checked; feed replies over 5 MB are refused; HTTPS is enforced with HSTS; failed logins are logged and an IP is locked out for 15 minutes after 5; admin sessions end after 8 hours; links are removed from the AI's reason (max 300 characters); `.htaccess` files block non-public folders. | §3.8 (p. 42); NFR-1.2 (p. 32); FR-3.5 (p. 31); `docs/final-test-results.md` |

## To remember during the build

- Automatic admin session timeout. NFR-1.2 (p. 32)
- API key stored outside the public web root. §3.8 (p. 42)
- cURL timeouts so a slow feed or AI reply cannot hang the run. §1.6 (p. 5)
- Log per-source ingestion time as evidence for NFR-4.1 (p. 32).
- Report notes: PDO/MySQLi wording in NFR-1.1; the AI score is the model's own estimate, not a measured probability; recent events cannot be checked (no web access); the AI can be wrong (hallucination risk, slide 6 and §2.3), stated in Limitations, not on screen; the §1.2/§3.1.2 vs NFR-2.1 wording on Pending articles; summary-only input can mislead the AI (decision 29).
