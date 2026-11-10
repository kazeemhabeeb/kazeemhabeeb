<?php
/*
 * TrustFeed configuration.
 *
 * Copy this file to config.php in the same folder and fill in your values.
 * config.php is never committed to Git, and this folder sits outside public_html,
 * so the database password and API key cannot be opened from a browser (§3.8).
 */

// ---------------------------------------------------------------------------
// Database (MySQLi, §3.5)
// ---------------------------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'trustfeed');
define('DB_USER', 'trustfeed_user');
define('DB_PASS', 'change-me');

// ---------------------------------------------------------------------------
// AI verification service: GPT-4o through RapidAPI (§3.5, decision 7)
// The key is read from the RAPIDAPI_KEY environment variable when it is set;
// otherwise paste it between the quotes in config.php (never in this example file).
// ---------------------------------------------------------------------------
define('RAPIDAPI_KEY', getenv('RAPIDAPI_KEY') ?: '');
define('RAPIDAPI_HOST', 'chatgpt-42.p.rapidapi.com');
define('RAPIDAPI_URL', 'https://chatgpt-42.p.rapidapi.com/gpt4');

// ---------------------------------------------------------------------------
// Second opinion: Search GPT on RapidAPI, which can search the web (decision 32).
// A Misinformation verdict from GPT-4o on a non-satire article is checked again before readers see it.
// Uses the same RAPIDAPI_KEY. Leave SEARCH_API_URL empty to switch the second opinion off.
// Host and URL: RapidAPI -> Search GPT -> Code snippet tab.
// ---------------------------------------------------------------------------
define('SEARCH_API_HOST', '');
define('SEARCH_API_URL', '');
define('SEARCH_API_MODEL', 'GPT-5.6-Terra');
define('SEARCH_API_TIMEOUT_SECONDS', 90);   // live test: 9.5-25 s per reply
define('SECOND_OPINION_MAX_PER_RUN', 2);    // token plan: ~14,000-35,000 tokens per check
define('SECOND_OPINION_MAX_WAITING', 10);   // older waiting articles get the first verdict (no pile-up)

// ---------------------------------------------------------------------------
// Ingestion and verification settings
// ---------------------------------------------------------------------------
define('VERDICT_THRESHOLD', 50);      // score >= 50 is Real, below is Misinformation (p. 36, p. 41)
define('AI_MAX_CALLS_PER_RUN', 9);    // Pro plan budget: 9 per hourly run (decision 14, §1.6)
define('AI_CALL_DELAY_SECONDS', 1);   // Pro plan allows 1 request per second
define('AI_TIMEOUT_SECONDS', 60);     // give up on a slow AI reply; article stays Pending (NFR-2.1).
                                      // Live replies took ~18 s; 30 s timed out 2 of 9 (9 Nov 2026 test).
define('FEED_TIMEOUT_SECONDS', 15);   // give up on a slow feed; logged as RSS feed failed (FR-3.5)

// ---------------------------------------------------------------------------
// Site settings
// ---------------------------------------------------------------------------
define('SITE_NAME', 'TrustFeed');
define('SESSION_TIMEOUT_MINUTES', 30); // admin is logged out after 30 idle minutes (NFR-1.2)
define('TIMEZONE', 'Africa/Lagos');
define('FORCE_HTTPS', true);              // send every visitor to https:// and add HSTS (§3.8). false only for local testing
define('MAX_LOGIN_ATTEMPTS', 5);        // failed logins allowed per IP address in 15 minutes
define('SESSION_MAX_HOURS', 8);         // admin must log in again after 8 hours, even if active

// Feeds on local or private network addresses are refused (protects the server's internal services).
// Only the automated tests, which serve feeds from 127.0.0.1, set this to true.
define('ALLOW_PRIVATE_FEED_ADDRESSES', false);
