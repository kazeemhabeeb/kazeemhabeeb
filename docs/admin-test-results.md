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
