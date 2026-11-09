# AI Verification Test Results (Sprint 2)

Evidence for Chapter 4. Code: `app/includes/ai.php`, called from `app/cron/ingest.php`.

## 1. Offline tests (no credits): `php app/tests/test_ai.php`

34 checks, all passing. They use the six real GPT replies saved in `tools/samples/final` and a fake AI
service (`app/tests/mock_ai_server.php`) that imitates each failure.

| Area | What is checked |
|---|---|
| Real replies | All six saved GPT replies are accepted and classified (82 Real, 5, 80 Real, 4, 5, 86 Real) |
| The 50% rule | 50 → Real, 49 → Misinformation, 0 and 100 at the ends |
| Untidy but valid replies | Extra words around the JSON, ```json fences, the score as text "85", "Low" in capitals: accepted |
| Invalid replies | Score 150, -5, "85%", 72.5 or missing; bias "extreme"; clickbait "maybe"; no reason; a reply cut off half-way; a plain-text refusal: all rejected, so the article stays Pending |
| Security | HTML in GPT's reason is removed before it is saved |
| Service failures | Slow reply → "API timeout"; rate limit (429), credits used up (429), bad key (403), service down (503), changed reply format, unreachable service → "API error" |
| FR-1.2 | The prompt contains exactly title, body, source_url and publication_date |

## 2. Live runs (9 November 2026, real GPT-4o through RapidAPI)

### Run 1: first version of the prompt, 30-second time limit

9 sent, 7 verified, 2 timed out. Two problems:

- **GPT treated today's date as "the future"** (its training data ends earlier) and marked down real news:
  "Nvidia probes CEO over Chinese spy link allegations" (Punch) scored **15, Misinformation**, with the reason
  "Future publication date, implausible specific allegations…".
- **Replies took about 18 seconds**, so 2 of 9 calls hit the 30-second limit.

**Fixes:** the prompt now states today's date and says not to lower the score because the date is later than
the model's training data; the time limit was raised to 60 seconds.

### Run 2: same articles reset to Pending, fixed prompt

9 sent, **8 verified**, 1 failed (HTTP 502 "upstream request failed" from the service; logged as API error,
left Pending, retried next hour). Total run time 156 s.

| Article (source) | Run 1 | Run 2 | Reason given in run 2 |
|---|---|---|---|
| Nvidia probes CEO over Chinese spy link allegations (Punch) | 15 Misinformation | **50 Real** | Relies on anonymous US officials and lacks corroborating details |
| Trump rules out fresh war with Iran (Punch) | 75 Real | **90 Real** | Straightforward report from a reputable source |
| Next four years will consolidate Abia reforms — Otti (Punch) | timed out | **85 Real** | Straightforward political statement, positive framing |
| Gospel singer Toluwanisings for burial today (Punch) | timed out | **95 Real** | Factual report, neutral language |
| Police Arrest Eight Suspects Over Alleged Vandalism (Channels TV) | 85 Real | 85 Real | Routine, non-sensational police statement |
| Ex-Kwara ADC spokesperson defects to APC (Punch) | 85 Real | 95 Real | Straightforward political reporting |
| Doctors HATE this one fruit that cures diabetes in 7 days!!! (test item, tier-1 flagged, verified first) | 10 Misinformation | **5 Misinformation** | Implausible medical claim, sensational clickbait language |
| Who is Alice Huang, the woman accused of spying for China against US tech giant Nvidia? (P.M. News) | — | **15 Misinformation** | Body is unrelated to the headline |

Checks: the tier-1 flagged item was sent first; verdicts follow the 50% rule; `is_clickbait` stays 1 for the
tier-1 item; the service error was logged and the article left Pending; the hourly run finished in under 3
minutes, far inside the hour.

## 3. Limitation found: the AI only sees the feed's short summary

P.M. News's feed gives "Who is Alice Huang…?" the summary *"I was unfaithful to my wife… I am deeply sorry,"
he said.* That is exactly what the publisher sent: a quote from the story, not a summary of it. GPT only sees
the headline and that quote, judges them unrelated, and scores the real article **15, Misinformation**. A
retry with a prompt line saying the body may be a quote or teaser still gave 15, so that line was removed.

This is a limitation of summary-only input (RSS feeds rarely include the full article, and the system does not
scrape pages: §2.5.1). It belongs in the report's Limitations alongside "cannot check recent events".
