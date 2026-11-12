# AI Classification Evaluation, v1 (12 November 2026)

Evidence for Objective 4 ("evaluate the effectiveness of the system") and Chapter 4.5.

## Method

- **Test set:** `app/tests/evaluation/set-v1.json`, 50 articles with known labels, collected on 12 November 2026:
  - 25 **Real**: the latest items from Premium Times (9), BusinessDay (8) and Punch (8), established
    Nigerian outlets with editorial teams (label assigned by source).
  - 18 **Misinformation (satire)**: The Onion (12) and The Babylon Bee (6), publications that state their
    content is fictional.
  - 7 **Misinformation (fabricated hoaxes)**: written for the test in the style of common Nigerian
    social-media hoaxes (celebrity and ex-president deaths, fake currency change, fake exam cancellation,
    fake free fuel, invented quote, miracle cure), attributed to an unknown blog.
  - Paid posts and items without a summary were excluded, as the live system filters them or would see less text.
- **Procedure:** `php app/tests/evaluate_ai.php` sends each article through the exact production code
  (`build_prompt` → `call_ai` → `parse_ai_reply` → `classify_score`, classification v1,
  `docs/classification-v1.md`), with one retry after a failure. Verdict: score ≥ 50 Real, below 50 Misinformation.
- **Metrics:** "positive" = Misinformation (the class the system detects).
  Precision = correct Misinformation verdicts ÷ all Misinformation verdicts;
  Recall = Misinformation articles caught ÷ all Misinformation articles.
- Raw results: `app/tests/evaluation/results-v1-20261112-0740.json`.

## Results

| Measure | Value |
|---|---|
| Articles answered | 49 of 50 (1 HTTP 502 from the service on both tries, left as "no verdict", as the live site would leave it Pending) |
| **Accuracy** | **95.9 %** (47 of 49) |
| **Precision** (Misinformation) | **92.3 %** (24 of 26) |
| **Recall** (Misinformation) | **100 %** (24 of 24) |
| **F1 score** | **0.96** |
| Specificity (Real kept Real) | 92.0 % (23 of 25) |
| Average time per article | 13.7 s (median 11.5 s, longest 65.4 s including the retry) |

Confusion matrix:

| | Verdict: Misinformation | Verdict: Real |
|---|---|---|
| **Actually Misinformation** | 24 (true positive) | 0 (false negative) |
| **Actually Real** | 2 (false positive) | 23 (true negative) |

By group:

| Group | Correct |
|---|---|
| Real news (established outlets) | 23 of 25 (92 %) |
| Satire publications | 18 of 18 (100 %) |
| Fabricated hoaxes | 6 of 6 answered (100 %) |

## Errors

Both errors are real articles marked Misinformation (false positives); no fake article was marked Real.

| # | Article | Score | AI reason | Why it went wrong |
|---|---|---|---|---|
| 9 | "University sacks another four staff members, gives reason" (Premium Times) | 30 | "The article lacks factual details and uses exaggerated wording." | Vague headline and a one-line teaser summary; the AI sees only the feed summary (decision 29). |
| 13 | "The Umrah and Absurdistanism; Normalising the abnormal" (BusinessDay) | 20 | "The article is from a known satire publication and contains fictional content." | An opinion column with ironic wording; the AI wrongly took the irony for satire. |

## Observations for the report

- The system **never let a fake or satirical article through as Real** (recall 100 %). Its mistakes are
  over-caution on real articles, which the administrator can correct by re-flagging (FR-3.3).
- A real recent death ("Wale Omole, former OAU Vice-Chancellor, dies at 84") scored 80 (Real), while a calm,
  invented death of a former president on a blog was not answered in this run because of the 502 (in earlier
  tests it scored 8–10). Earlier live cases (`live-site-results.md`) show that real events after the model's
  training cutoff can still be misjudged (Charlie Kirk article, 10).
- The score of the same article varies between runs (Kalu article: 35, 20, 80, 30, 45), so one run of 50 articles
  is an estimate. Larger or repeated runs would narrow it; the set and script are kept so v1 can be compared
  with any later version.
- Labels for real articles come from the source, not from checking each claim; this is the usual simplification
  and is stated as a limitation.
