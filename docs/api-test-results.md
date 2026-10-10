# AI Verification Service: Test Results

Record of the RapidAPI services tested for the verification step (FR-1.3, FR-1.4) and the
results, for use in Chapter 4 (testing) and Chapter 5 (limitations). The raw API responses are
saved in `tools/samples/`, and `php tools/score_llm.php <file>` reproduces each verdict.

## Services tested

| Service (RapidAPI) | What it does | Outcome |
|---|---|---|
| Fact Checker (letscrape) | Searches fact-checks that are already published (Google Fact Check Explorer data) | Works, but returns no score or verdict and finds nothing for ordinary news. Not suitable as the main classifier |
| Fake News Detection | Fake/real text classifier | HTTP 404: the provider's server is gone |
| Dawg fake news detector | Style-based fake/real classifier | HTTP 404: the provider's server is gone |
| **ChatGPT 4 by PR Labs (GPT-4o, `/gpt4`)** | LLM that follows our instructions and returns JSON | **Selected.** Returns a credibility score, clickbait, emotional manipulation, bias and a reason |

## Selected service

- Endpoint: `POST https://chatgpt-42.p.rapidapi.com/gpt4`
- Body: `{"messages":[{"role":"user","content":"<prompt>"}],"web_access":false,"max_tokens":512}`
- Plan: Pro ($5.99/month, 100,000 credits; a chat request costs 15 credits, so about 6,600 checks a month)
- Rule applied by our code (§3.4.2B step 6, §3.7): score ≥ 50 → Real, score < 50 → Misinformation,
  no usable score → Pending Verification (NFR-2.1)

## Final test set (final prompt, web_access off)

| # | Article (source) | Type | Score | Clickbait | Manipulation | Bias | Verdict | Expected |
|---|---|---|---|---|---|---|---|---|
| 1 | Macron to make state visit to Nigeria (Premium Times) | Real news | 82 | no | no | none | Real | Real ✅ |
| 2 | SHOCKING: Elon Musk warns a billion will DIE by 2030 (Viral Today) | Viral false claim, clickbait | 5 | yes | yes | high | Misinformation | Misinformation ✅ |
| 3 | FG pays eight months' SSANU arrears (Channels Television) | Real news | 80 | no | no | none | Real | Real ✅ |
| 4 | 5G masts are spreading coronavirus (Naija Truth Daily) | Debunked myth | 4 | yes | yes | high | Misinformation | Misinformation ✅ |
| 5 | This wicked government wants every Nigerian to starve (Lagos Wire) | Emotional, one-sided opinion | 5 | yes | yes | high | Misinformation | Misinformation ✅ |

Result: 5 out of 5 correct.

## Live RSS test (real feed item, built by the ingestion code)

The item was copied from the P.M. News RSS feed (`tools/samples/rss/pmnews-item.xml`), read with
SimpleXML, cleaned, and turned into the request body by the same functions the system uses.

| Article (source) | Score | Clickbait | Manipulation | Bias | Verdict | Expected |
|---|---|---|---|---|---|---|
| Stock Market ends seven-day losing streak, adds ₦209bn (P.M. News, 9 Oct 2026) | 86 | no | no | none | Real | Real ✅ |

Model's reason: "The reported gain is consistent with the stated market values, though the seven-day
losing streak is not substantiated in the text." The model checked the figures (₦161.260tn − ₦161.051tn = ₦209bn).

## Earlier tests (same service, earlier prompt wording)

| Article (source) | Score | Verdict | Expected | Note |
|---|---|---|---|---|
| Doctors HATE this one fruit that cures diabetes (Metro Pulse) | 2 | Misinformation | Misinformation ✅ | Clickbait and false health claim |
| Starbucks teen-free hours headline (The Onion) | 5 | Misinformation | Misinformation ✅ | Recognised as satire by source name |
| Same headline, source given as Lagos Wire | 15 | Misinformation | Misinformation ✅ | Satire recognised from the wording alone |
| Naira reaches two-year high after rate cut (BusinessDay) | 78 | Real | Real ✅ | |
| Fifty NYSC members abducted from Lagos camp (Punch), fabricated for the test | 72 | Real | Misinformation ❌ | Known limitation, see below |

## Limitations found

1. **Recent events cannot be confirmed.** A fabricated but plausibly written report scored 72 (Real).
   The model judges plausibility and wording and cannot check events after its training data.
2. **No live web search.** The listing's `web_access` option was tested (`tools/test_web_access.php`) on `/gpt4`, `/gpt4o`, `/gpt5`
   and `/conversationgpt4-2` on both the free and Pro plans; the model replied that it cannot access
   live web search. Retrieval of live news evidence (RAG, Chapter 2: Li et al., 2026; Ansari et al.,
   2026) is left as future work.
3. **The score is the model's own assessment**, not a measured probability, and may vary slightly
   between runs (Herrera-Poyatos et al., 2025). Each article is checked once and the result stored;
   the admin can re-flag any verdict (FR-3.3).
4. **Usage cap (§1.6).** The Pro plan allows about 9 checks per hourly run, so the ingestion script
   takes the newest article from each of up to 9 sources per run.
