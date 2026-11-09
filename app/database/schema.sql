-- TrustFeed database schema
-- Based on §3.6 of the proposal (Figure 3.3 and the table in §3.6.2).
-- Changes from the proposal are marked "Fix" or "Added" and explained in docs/database.md.
--
-- Import in phpMyAdmin (cPanel) or run: mysql -u USER -p DATABASE < schema.sql

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- admins: administrator login (FR-3.1, NFR-1.2)
-- ---------------------------------------------------------------------------
CREATE TABLE admins (
    admin_id      INT          NOT NULL AUTO_INCREMENT,
    username      VARCHAR(50)  NOT NULL,
    password_hash VARCHAR(255) NOT NULL,              -- bcrypt from password_hash()
    PRIMARY KEY (admin_id),
    UNIQUE KEY uq_admins_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- rss_sources: websites the ingestion script reads (FR-3.2)
-- rss_url holds an RSS feed or a news API address (Objective 1)
-- ---------------------------------------------------------------------------
CREATE TABLE rss_sources (
    source_id    INT          NOT NULL AUTO_INCREMENT,
    website_name VARCHAR(100) NOT NULL,
    rss_url      VARCHAR(500) NOT NULL,                -- Fix: TEXT in the PDF; MySQL cannot make TEXT unique
    status       ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    PRIMARY KEY (source_id),
    UNIQUE KEY uq_sources_url (rss_url)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- articles: every ingested and verified article
-- ---------------------------------------------------------------------------
CREATE TABLE articles (
    article_id             INT          NOT NULL AUTO_INCREMENT,
    source_id              INT          NOT NULL,
    title                  TEXT         NOT NULL,
    content_summary        TEXT         NULL,           -- sanitized summary from the feed
    original_link          VARCHAR(500) NOT NULL,       -- Fix: TEXT in the PDF; must be unique (NFR-2.2)
    publication_date       DATETIME     NULL,
    confidence_score       INT          NULL,           -- Fix: NULL = not verified yet (NFR-2.1)
    verification_status    VARCHAR(50)  NOT NULL DEFAULT 'Pending Verification', -- Real / Misinformation / Pending Verification
    view_count             INT          NOT NULL DEFAULT 0,
    category               VARCHAR(100) NULL,           -- Added: category filter (FR-2.3)
    image_url              VARCHAR(500) NULL,           -- Added: article image (http/https only)
    verification_reason    TEXT         NULL,           -- Added: "Why this score" (§3.1.1)
    is_clickbait           TINYINT(1)   NULL,           -- Added: tier 1 or AI flag (§3.1.1, §1.5, §2.5.1)
    emotional_manipulation TINYINT(1)   NULL,           -- Added: AI flag (§3.1.1, §1.5)
    bias_level             ENUM('none','low','high') NULL, -- Added: AI flag (§3.1.1, §1.5)
    PRIMARY KEY (article_id),
    UNIQUE KEY uq_articles_link (original_link),        -- stops duplicate ingestion (NFR-2.2)
    KEY idx_articles_date (publication_date),           -- newest-first feed (NFR-4.2)
    KEY idx_articles_category (category),               -- category filter (NFR-4.2)
    KEY idx_articles_status (verification_status),      -- Pending queue and dashboard counts
    CONSTRAINT fk_articles_source FOREIGN KEY (source_id)
        REFERENCES rss_sources (source_id)
        ON DELETE CASCADE,                              -- deleting a source deletes its articles
    CONSTRAINT chk_articles_score CHECK (confidence_score IS NULL OR confidence_score BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- logs: execution runs and errors shown in the admin panel (FR-3.5) -- Added table
-- log_type: Execution, API timeout, API error, RSS feed failed, Spam filtered, Login failed
-- ---------------------------------------------------------------------------
CREATE TABLE logs (
    log_id     INT         NOT NULL AUTO_INCREMENT,
    log_type   VARCHAR(50) NOT NULL,
    message    TEXT        NOT NULL,
    created_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    KEY idx_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
