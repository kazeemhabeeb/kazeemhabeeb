# Database Design

Based on §3.6 of the proposal (Figure 3.3 and the schema table in §3.6.2), with the agreed
fixes and additions marked.

Legend: **PDF** = as in §3.6.2 · **Fix** = same column, adjusted to work in MySQL or to support a
requirement · **Added** = needed for an agreed feature the schema does not cover.

## admins (FR-3.1, NFR-1.2)

| Column | Type | Constraints | Source |
|---|---|---|---|
| admin_id | INT | Primary key, auto-increment | PDF |
| username | VARCHAR(50) | Unique, not null | PDF |
| password_hash | VARCHAR(255) | Not null (bcrypt via `password_hash()`) | PDF |

## rss_sources (FR-3.2)

| Column | Type | Constraints | Source |
|---|---|---|---|
| source_id | INT | Primary key, auto-increment | PDF |
| website_name | VARCHAR(100) | Not null | PDF |
| rss_url | VARCHAR(500) | Not null, unique | Fix: PDF says TEXT, which MySQL cannot index as UNIQUE |
| status | ENUM('Active','Inactive') | Default 'Active' | PDF |

## articles

| Column | Type | Constraints | Source |
|---|---|---|---|
| article_id | INT | Primary key, auto-increment | PDF |
| source_id | INT | Foreign key → rss_sources.source_id, ON DELETE CASCADE | PDF (cascade: agreed decision) |
| title | TEXT | Not null | PDF |
| content_summary | TEXT | | PDF |
| original_link | VARCHAR(500) | Not null, unique (NFR-2.2) | Fix: PDF says TEXT |
| publication_date | DATETIME | | PDF |
| confidence_score | INT | NULL allowed, 0–100 | Fix: NULL = not yet verified (NFR-2.1) |
| verification_status | VARCHAR(50) | Default 'Pending Verification' | PDF |
| view_count | INT | Default 0 | PDF |
| category | VARCHAR(100) | | Added: category filtering (FR-2.3, §2.2) |
| image_url | VARCHAR(500) | NULL allowed | Added: design choice for a more readable feed (Usability, NFR-3.x). Taken from the feed's `<media:content>`, `<enclosure>` or first `<img>`; only http/https URLs are stored and they are escaped on output, which addresses the image-tag XSS risk in §1.2 (NFR-1.1, §3.8). NULL when the feed has no image |
| verification_reason | TEXT | NULL allowed | Added: the AI's one-sentence reason, shown as "Why this score" on the article page (§3.1.1 explainable verification flags, §3.1.2 AI explainability metrics) |
| is_clickbait | TINYINT(1) | NULL allowed | Added: 1 if tier 1 **or** the AI flags clickbait (§3.1.1 flags, §1.5, §2.5.1) |
| emotional_manipulation | TINYINT(1) | NULL allowed | Added: the AI's emotional manipulation flag (§3.1.1, §1.5) |
| bias_level | ENUM('none','low','high') | NULL allowed | Added: the AI's bias flag (§3.1.1, §1.5) |

The flag columns are NULL until the AI has verified the article, except `is_clickbait`, which tier 1 sets to 1
when a headline matches a clickbait rule; those articles go to the AI first (§2.5.1). The SQL is in `app/database/schema.sql`.

## logs (FR-3.5, §1.5)

| Column | Type | Constraints | Source |
|---|---|---|---|
| log_id | INT | Primary key, auto-increment | Added |
| log_type | VARCHAR(50) | Not null ('Execution', 'API timeout', 'API error', 'RSS feed failed', 'Spam filtered', 'Login failed', 'Second opinion') | Added |
| message | TEXT | Not null | Added |
| created_at | DATETIME | Default current time | Added |

## Decisions

- **Manual re-flag (FR-3.3) clears the AI result.** When the admin changes the label, `confidence_score`,
  `verification_reason` and the three flag columns are set to NULL, so the badge shows the label without a percentage and the
  article page says "This label was set by an administrator." No extra column is needed.

- **Sources can be RSS feeds or news APIs** (WordPress REST API JSON). Both go in `rss_url`; the ingestion
  script detects XML or JSON from the reply, so no extra column is needed (Objective 1).

- **Deleting a source deletes its articles** (`ON DELETE CASCADE`). The admin's delete
  confirmation warns: "This will also delete its articles." §3.6 does not specify this.
