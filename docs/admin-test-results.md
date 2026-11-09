# Admin Panel Test Results (Sprint 4)

Evidence for Chapter 4. Screenshots: `docs/screenshots/sprint4/`.

## Login and session security (FR-3.1, NFR-1.2, §3.8)

The admin account was created with `php tools/create_admin.php` (decision 22). The password is stored only
as a bcrypt hash (`$2y$10$…`, 60 characters). The tool refused a password shorter than 10 characters.

Test script: `app/tests/test_login.sh` (11 checks, all passing on 9 November 2026).

| # | Check | Result |
|---|---|---|
| 1 | Open the dashboard without logging in | Sent to the login page (HTTP 302) |
| 2 | Wrong password | "Wrong username or password…", after a 1-second delay that slows password guessing |
| 3 | Right password | Logged in; session cookie is `HttpOnly; SameSite=Strict` (`Secure` is added automatically on HTTPS) |
| 3 | Session ID at login | Replaced with a new one (prevents session fixation) |
| 4 | Dashboard after login | Opens, showing the admin's name |
| 5 | Logout by a plain link (GET) | Refused, HTTP 403 |
| 6 | Logout with a forged CSRF token | Refused, HTTP 403 |
| 7 | Logout from the admin menu (real token) | Logged out, back to the login page |
| 8 | Dashboard after logout | Sent to the login page |
| 9 | 31 minutes without activity | Logged out automatically (NFR-1.2) |
| 10 | Message after timeout | "You were logged out after 30 minutes without activity. Please log in again." |
| 11 | Session ID visible in the page HTML | No (0 matches) |

Every admin page sends `Cache-Control: no-store`, so the browser does not keep copies of admin pages, and the
same Content-Security-Policy as the public site. Font Awesome icons and Bootstrap's script are served from the
site itself.

## Managing sources (FR-3.2)

Page: `app/public_html/admin/sources.php`. Every change is a POST form with a CSRF token; after saving, the page
redirects (so refreshing cannot submit twice) and shows a message. A new or changed address is read once,
the same way the hourly script reads it, and is only saved if it is a working RSS feed or WordPress news API.

Browser test: `app/tests/test_sources_browser.py` (9 November 2026).

| # | Action | Result |
|---|---|---|
| a | Add `ftp://example.com/feed` | Refused: must start with http:// or https:// |
| b | Add Punch's address again | Refused: "already in the list as Punch" (unique `rss_url`) |
| c | Add `https://www.channelstv.com/` (a web page) | Refused: "not an RSS feed or a WordPress news API" (`sources-not-a-feed-error.png`) |
| d | Add an address that cannot be reached | Refused: "Could not open that address…" |
| e | Add `https://babylonbee.com/feed` | Added |
| f | Rename "The Guardian Nigeria" (its feed blocks bots) without changing the address | Saved: the address is only re-checked when it changes |
| g | Disable The Babylon Bee | Status became Inactive (the hourly script skips it) |
| i | Delete "Test feed (made-up items)" | Pop-up: "This will also delete its 4 article(s)" (`sources-delete-confirmation.png`); after confirming, the source and all 4 articles were gone (ON DELETE CASCADE) |
| j | Add a source named `<script>alert("x")</script>Bee` | Stored as typed, displayed as plain text everywhere; no script ran |
| k | Send a delete request without the CSRF token (as another website would) | Refused, HTTP 403; the source was not deleted |
