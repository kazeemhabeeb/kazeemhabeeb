# Public Site Test Results (Sprint 3)

Evidence for Chapter 4. Pages: `app/public_html/index.php`, `article.php`, `api/articles.php`,
`js/feed.js`, `js/article.js`. Tested on 9 November 2026 with the 150 live articles from Sprints 1–2,
in Chromium at desktop (1280 px) and phone (390 px) widths. Screenshots: `docs/screenshots/sprint3/`.

| Requirement | What was checked | Result |
|---|---|---|
| FR-2.1 public feed, no login | `index.php` opens without any login | ✓ |
| FR-2.2 badge with label and % on every card | Real 95%, Real 50%, Misinformation 15%, Pending (no %) | ✓ `feed-desktop.png` |
| FR-2.3 AJAX/Fetch, no page reload | Articles and categories come from `api/articles.php`; clicking "Business" changed the list and the address to `?category=Business` without reloading; page 2 loaded the same way; a quiet refresh runs every 5 minutes | ✓ |
| FR-2.4 view counter | Opening article #150 raised its `view_count` from 0 to 1 | ✓ |
| §3.1.1 alert indicators | "⚠ Clickbait" on the flagged card; full flags under "Why this score" | ✓ `article-misinformation.png` |
| Use case "Read Article Summary" | Summary and "Read the full story on <source>" link | ✓ `article-real.png` |
| NFR-2.1 Pending shown | Yellow badge and "This article has not been verified yet" | ✓ `article-pending.png` |
| NFR-3.1 responsive | Phone width: one column, collapsible menu | ✓ `feed-mobile-menu.png` |
| NFR-3.2 colours | Green / red / yellow, with a key in the sidebar | ✓ |
| Missing article | `article.php?id=999999` shows "Article not found" with HTTP 404 | ✓ |

## Second layer of XSS protection

To prove the pages are safe even if something got past sanitizing, an article was written **straight into
the database**, skipping `sanitize.php`, with attacks in every field:

- title: `XSS test <script>alert("title")</script><img src=x onerror=alert(1)>`
- summary: `<script>document.title="HACKED"</script>` and `<b onmouseover=alert(2)>`
- image address: `https://example.com/x.jpg" onerror="alert(3)`
- AI reason: `<script>alert(4)</script>`

Result (`article-xss-test.png`): every attack is shown as harmless text, **no alert ran**, the tab title was
not changed, and the bad image fell back to the placeholder. Two protections did this:

1. Every value is escaped with `e()` / `esc()` before it is printed (NFR-1.1).
2. A Content-Security-Policy header tells the browser to run only script files from this site, never
   inline scripts (§3.8). Bootstrap's script is served from the site itself (the official 5.2.3 file; its
   SHA-384 checksum matches Bootstrap's published one).
