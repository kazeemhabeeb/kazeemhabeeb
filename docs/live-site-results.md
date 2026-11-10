# Live Site Results (trustfeed.habeeb.website)

Evidence for Chapter 4. Hosting: Hostinger (hPanel), hourly cron job, PHP 8.3, GPT-4o via RapidAPI Pro plan.

## First seven hourly runs (10 November 2026, 9 sources)

| Run | New articles | Duplicates skipped | Sent to GPT / verified / failed | Pending after |
|---|---|---|---|---|
| 01:00 (first run) | 151 (4 spam filtered) | 0 | 9 / 9 / 0 | 142 |
| 02:00 | 30 | 121 | 9 / 9 / 0 | 163 |
| 03:00 | 1 | 150 | 9 / 9 / 0 | 155 |
| 04:00 | 3 | 148 | 9 / 9 / 0 | 149 |
| 05:00 | 2 | 149 | 9 / 9 / 0 | 142 |
| 06:00 | 8 | 144 | 9 / 9 / 0 | 141 |
| 07:00 | 27 (1 flagged as clickbait) | 127 | 9 / 9 / 0 | 159 |

- Every run read all 9 sources with no failures and took 120–154 s, almost all of it waiting for GPT.
- Processing and saving took at most 0.026 s per source (NFR-4.1).
- 63 verified articles after 7 runs: 59 Real, 4 Misinformation (ratio 1 : 14.8).
- Tier 1 filtered 4 items the publishers had marked themselves: three Vanguard items filed under "Promoted"
  (one a football opinion piece) and one Daily Post item marked "Sponsored" and "Promoted".

## Arrival rate versus the GPT budget

Publishing rates measured from the live feeds at 08:00 on 10 November:

| Source | Articles per hour |
|---|---|
| Punch | 5.0 |
| Vanguard | 3.4 |
| Channels Television | 2.9 |
| BusinessDay (news API) | 2.6 |
| Daily Post | 2.5 |
| P.M. News | 2.2 |
| The Babylon Bee | 1.6 |
| Premium Times | 1.3 |
| The Onion | 0.1 |
| **All 9** | **≈ 22 per hour (≈ 500 a day)** |

The RapidAPI Pro plan allows about 216 GPT checks a day (100,000 credits ÷ 15 per call ÷ 30 days; 9 per hourly
run). With 9 sources most articles would stay Pending, and the backlog grew during busy hours. On 10 November the
admin **removed Punch, Vanguard and Daily Post**, the three busiest sources, leaving 6 sources at roughly
150–200 articles a day, within the budget. FR-1.1 is unchanged: every new article from every Active source is
still collected. This is the usage-cap limitation the proposal anticipates in §1.6 (p. 5).

## A false "Misinformation" on real news

*"I sold arms to anyone with money — Kalu"* (Punch, 10 November 2026) is real news, also reported by Daily Post.
GPT scored it **35%, Misinformation**: "The claim is extraordinary and unverified, and the headline uses
sensational clickbait wording."

Cause: the AI has no web access, so a surprising but genuine statement by a public figure looks like an invented
claim. The headline style "[quote] — Name" reports what someone said; it does not assert the statement is true.
This is the "cannot check recent events" limitation (decision 29 and §1.6), and it is why the admin can re-flag
an article by hand (FR-3.3).

### Follow-up tests on the Kalu article (10 November 2026)

**Prompt sentence for quote headlines.** A trial sentence was added to the prompt: *A headline written as
"<quoted words> — <Name>" reports what that named person said: judge whether the report of the statement is
credible, not whether the statement itself is surprising.*

| Article | Prompt | Score | Verdict |
|---|---|---|---|
| I sold arms to anyone with money — Kalu (real, Punch) | current (live site) | 35 | Misinformation |
| same | current | 20 | Misinformation |
| same | trial | 80 | Real |
| same | trial | 30 | Misinformation |
| I'm a godfather, but don't interfere in Abiodun's govt — Osoba (real, Punch) | trial | 85 | Real |
| *I will hand over to Peter Obi next week — Tinubu* (invented quote on a fake blog) | trial | 10 | Misinformation |
| Doctors HATE this one fruit that cures diabetes in 7 days!!! (fake) | trial | 10 | Misinformation |
| Gavin Newsom Commutes All California Death Sentences (The Onion) | trial | 5 | Misinformation |

The trial sentence did not weaken detection (the invented quote, the fake cure and the satire were all still
caught), but it did not reliably fix the Kalu article either: the same prompt gave 80 and then 30. **The prompt
was left unchanged**; the result is evidence of LLM variability (§2.3: inconsistent classifications for the same
input). 5 of the 10 calls in this test returned HTTP 502 "upstream request failed" from the service.

**Web access re-check.** In the RapidAPI playground with `"web_access": true`:
- Asked directly about the Kalu story (only answerable by searching the web), the service replied
  **"NO WEB ACCESS"**.
- The full TrustFeed prompt for the Kalu article scored **45, Misinformation** ("minimal context or verification"),
  with no sign of a web search.

So `"web_access": false` stays in `ai.php`: it states what the service actually does. Across five runs on
two endpoints, the same real article scored 35, 20, 80, 30 and 45. Replies took 17–28 seconds.

### Charlie Kirk article and the web-search second opinion (10 November 2026)

A real P.M. News story, *Erika Kirk condemns jokes on Charlie's assassination*, was scored **10, Misinformation**:
"Article makes implausible claim about assassination of living public figure without credible sourcing". The
model's knowledge ends before Charlie Kirk's death and it cannot search the web, so a real later event looked
invented. This is more serious than the Kalu case: any real event after the training cutoff that contradicts
what the model knew (deaths, appointments, elections) tends to be marked false.

**Prompt sentence about the knowledge cutoff** (*"Your knowledge has a cutoff date; deaths, appointments,
elections and other events after it are normal, so do not treat an article as false only because it contradicts
what you knew before your cutoff."*):

| Article | Prompt | Scores |
|---|---|---|
| Erika Kirk condemns jokes on Charlie's assassination (real) | current | 5, 85 |
| same | with the cutoff sentence | 15, 70, 85, 15 |
| I sold arms to anyone with money — Kalu (real) | with the cutoff sentence | 75 |
| BREAKING: Davido is dead! … (invented death, blog) | with the cutoff sentence | 5 |
| Former President Olusegun Obasanjo dies at 89 (invented, calm wording, blog) | with the cutoff sentence | 10 |
| I will hand over to Peter Obi next week — Tinubu (invented quote, blog) | with the cutoff sentence | 5 |
| Gavin Newsom Commutes All California Death Sentences (The Onion) | with the cutoff sentence | 0 |

The sentence did not let fakes through, but it did not fix the real article either (2 of 4 runs still below
50). Not adopted: the model cannot know facts it was never given.

**Search GPT (RapidAPI, model GPT-5.6-Terra, `web_search` tool).** The same TrustFeed prompt for the Kirk article
scored **88, Real**: "Independent reporting supports the events, though Erika Kirk did not name Dave Chappelle in
her statement", citing a CNN story from that day. 9.5 s, 13,653 tokens. A general news question used 35,015
tokens. Plans are by tokens (Basic $1 = 1M, Pro $5 = 5M, Ultra $25 = 25M, Mega $75 = 100M a month, hard limits),
so web-checking all ~216 articles a day is not affordable; the second opinion checks only Misinformation
verdicts (decision 32).

**New `satire` field, live check on GPT-4o:** The Onion 0 (satire true), The Babylon Bee 0 (satire true), the
Kirk article 10 (satire false, so it goes to the web check), an invented Davido death 10 (satire false, web
check). Automated test: `app/tests/test_second_opinion.php` (14 checks, mock services).

**Live check on the Hostinger server** (`tools/test_web_check.php`, web-check prompt from `ai.php`, two runs):

| Article | Truth | Run 1 | Run 2 |
|---|---|---|---|
| Erika Kirk condemns jokes on Charlie's assassination (P.M. News) | Real | 401 | **88 Real** (22,511 tokens, 18.9 s) |
| I sold arms to anyone with money — Kalu (Punch) | Real | 401 | **93 Real** (20,154 tokens, 12.5 s) |
| Former President Olusegun Obasanjo dies at 89 (invented, blog) | Fake | 401 | **8 Misinformation** (15,969 tokens, 10.0 s) |
| I will hand over to Peter Obi next week — Tinubu (invented, blog) | Fake | **2 Misinformation** (18,204 tokens, 31.5 s) | 401 |

Every answer was correct, and the reasons cite the facts found ("Nigeria's constitution says the vice
president—not Peter Obi—would succeed a resigning president"). 4 of 8 calls failed with HTTP 401 "Client
Error, Invalid request or property", on different articles each run with the same key, so the failures are
random on the service's side; failed calls use no tokens. `call_web_ai()` now retries a 401 or 5xx once after
3 seconds; anything still failing stays Pending and is retried the next hour (NFR-2.1).
