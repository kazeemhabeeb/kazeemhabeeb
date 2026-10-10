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
| image_url | VARCHAR(500) | NULL allowed | Added: agreed image placeholders |

## logs (FR-3.5, §1.5)

| Column | Type | Constraints | Source |
|---|---|---|---|
| log_id | INT | Primary key, auto-increment | Added |
| log_type | VARCHAR(50) | Not null ('Execution', 'API timeout', 'API error', 'RSS feed failed') | Added |
| message | TEXT | Not null | Added |
| created_at | DATETIME | Default current time | Added |

## Decisions

- **Deleting a source deletes its articles** (`ON DELETE CASCADE`). The admin's delete
  confirmation warns: "This will also delete its articles." §3.6 does not specify this.
