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
