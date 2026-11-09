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

## To remember during the build

- Automatic admin session timeout. NFR-1.2 (p. 32)
- API key stored outside the public web root. §3.8 (p. 42)
- cURL timeouts so a slow feed or AI reply cannot hang the run. §1.6 (p. 5)
- Log per-source ingestion time as evidence for NFR-4.1 (p. 32).
- Report notes: PDO/MySQLi wording in NFR-1.1; the AI score is the model's own estimate, not a measured probability; recent events cannot be checked (no web access).
