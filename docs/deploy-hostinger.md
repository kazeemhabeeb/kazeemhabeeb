# Deploying TrustFeed to Hostinger

Live address: **https://trustfeed.habeeb.website** (Hostinger Enterprise Cloud, hPanel).

The proposal names cPanel (§3.5, p. 38) for its cron scheduler. Hostinger's hPanel provides the same
functions (file manager, MySQL, phpMyAdmin, cron jobs, SSL), so the design is unchanged.

## Server facts (checked 9 November 2026)

| | |
|---|---|
| SSH | `ssh -i ~/.ssh/trustfeed_deploy -p 65002 u633588168@82.112.249.140` |
| Home folder | `/home/u633588168` |
| App folder (private, outside every website) | `/home/u633588168/trustfeed_app` |
| Subdomain web folder | `/home/u633588168/domains/habeeb.website/public_html/trustfeed` → symlink to `trustfeed_app/public_html` |
| Website PHP | 8.3 (hPanel → Advanced → PHP Configuration), with `nd_mysqli`, `mysqlnd`, `curl`, `mbstring`, `simplexml` |
| Command-line PHP for cron | `/opt/alt/php83/usr/bin/php` (PHP 8.3.36). The plain `php` command is 7.4, so do not use it |
| rsync | `/usr/bin/rsync` (used by the deployment) |

## Folder layout on the server

```
/home/u633588168/trustfeed_app/          private (never reachable from a browser, §3.8)
├── config/config.php                    created once on the server; never in Git, never overwritten
├── includes/  cron/  tools/  database/
├── ingest.lock                          created by the hourly script
└── public_html/                         the website
/home/u633588168/domains/habeeb.website/public_html/trustfeed  ->  /home/u633588168/trustfeed_app/public_html
```

The subdomain lives inside habeeb.website's web folder, so the private folders cannot sit beside it; the
symlink keeps them outside every public folder. PHP resolves the symlink, so `__DIR__ . '/../includes'` finds
`trustfeed_app/includes`.

## One-time setup

### 1. SSH key for GitHub (done)
`ssh-keygen -t ed25519 -f ~/.ssh/trustfeed_deploy -N ""` on the Mac; public key added in hPanel → SSH Access →
SSH keys; logging in with it works without a password.

### 2. GitHub Secrets
GitHub → repository → Settings → Secrets and variables → Actions:
`SSH_HOST` = `82.112.249.140`, `SSH_PORT` = `65002`, `SSH_USER` = `u633588168`,
`DEPLOY_PATH` = `/home/u633588168/trustfeed_app`, `SSH_PRIVATE_KEY` = contents of `~/.ssh/trustfeed_deploy`.

### 3. Database
hPanel → Databases → Management → create a database and a user (hPanel adds the `u633588168_` prefix).
Keep the password in a password manager; it only goes into `config.php` on the server.

### 4. First deployment
Merge the work into `main`. GitHub Actions (`.github/workflows/deploy.yml`) checks every PHP file and uploads
the app to `trustfeed_app` with rsync. Progress: GitHub → Actions → "Deploy to Hostinger".

### 5. On the server (SSH), once
```bash
cd ~/trustfeed_app

# Tables
mysql -u DB_USER -p DB_NAME < database/schema.sql

# Settings: copy the template, then fill in DB_NAME, DB_USER, DB_PASS and RAPIDAPI_KEY.
# Keep FORCE_HTTPS true and ALLOW_PRIVATE_FEED_ADDRESSES false.
cp config/config.example.php config/config.php
nano config/config.php
chmod 600 config/config.php

# Admin account (asks for a username and a password of 10+ characters, typed hidden)
/opt/alt/php83/usr/bin/php tools/create_admin.php

# Point the subdomain at the app (replaces Hostinger's placeholder folder with a symlink)
rm ~/domains/habeeb.website/public_html/trustfeed/default.php
rmdir ~/domains/habeeb.website/public_html/trustfeed
ln -s ~/trustfeed_app/public_html ~/domains/habeeb.website/public_html/trustfeed
```

### 6. Hourly cron job (FR-1.1)
hPanel → Advanced → Cron Jobs → Custom, every hour (`0 * * * *`):
```
/opt/alt/php83/usr/bin/php /home/u633588168/trustfeed_app/cron/ingest.php > /dev/null 2>&1
```

### 7. Check
- `https://trustfeed.habeeb.website` opens the feed, with SSL.
- `https://trustfeed.habeeb.website/admin/login.php` accepts the new admin.
- Add the sources (RSS Sources page), then run the script once by hand:
  `/opt/alt/php83/usr/bin/php ~/trustfeed_app/cron/ingest.php`
- The dashboard and System Logs show the run.
- `https://trustfeed.habeeb.website/../config/config.php` and similar paths are not reachable.

## After that

Every change merged into `main` deploys automatically. Two things stay manual:
- **Database changes:** run the provided SQL file once (phpMyAdmin or `mysql` over SSH).
- **config.php changes:** edit on the server; deployments never touch it.

## Web-search second opinion (decision 32)

Add to `config/config.php` on the server. Until `SEARCH_API_URL` is filled in, the second opinion stays off and the site works as before. The same
`RAPIDAPI_KEY` is used.
```php
define('SEARCH_API_HOST', 'search-gpt.p.rapidapi.com');
define('SEARCH_API_URL', 'https://search-gpt.p.rapidapi.com/');
define('SEARCH_API_MODEL', 'GPT-5.6-Terra');
define('SEARCH_API_TIMEOUT_SECONDS', 90);
define('SECOND_OPINION_MAX_PER_RUN', 2);
define('SECOND_OPINION_MAX_WAITING', 10);
```
No database change is needed. Live check (4 sample articles, nothing saved):
`/opt/alt/php83/usr/bin/php ~/trustfeed_app/tools/test_web_check.php`
