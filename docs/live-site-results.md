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
