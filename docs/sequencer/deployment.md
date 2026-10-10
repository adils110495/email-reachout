# Production deployment

Target stack: Nginx → PHP-FPM 8.2/8.3 → Laravel, MySQL 8, Redis 7, Supervisor (queue workers) and cron (scheduler).

Ready-made files are in [`deploy/`](../../deploy):

| File | Install to |
|---|---|
| `deploy/nginx/sequencer.conf` | `/etc/nginx/sites-available/` |
| `deploy/php/php-fpm-pool.conf` | `/etc/php/8.3/fpm/pool.d/sequencer.conf` |
| `deploy/supervisor/sequencer-worker.conf` | `/etc/supervisor/conf.d/` |
| `deploy/cron/sequencer` | `/etc/cron.d/sequencer` |

## 1. Server packages

```bash
apt install nginx mysql-server redis-server supervisor \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-bcmath php8.3-curl \
  php8.3-zip php8.3-intl php8.3-imap php8.3-redis
```

`php-imap` is required for reply/bounce detection. On PHP 8.4+ use `pecl install imap`.

`php-redis` is optional: the app ships with Predis (`REDIS_CLIENT=predis`). With phpredis installed, set `REDIS_CLIENT=phpredis`.

## 2. Database & Redis

```sql
CREATE DATABASE cold_email_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cold_email'@'localhost' IDENTIFIED BY '<strong password>';
GRANT ALL ON cold_email_db.* TO 'cold_email'@'localhost';
```

Upgrading an existing install: **back up the database first**, then `php artisan migrate --force`.
The sequencer migrations expand the existing tables and convert existing data (the separate SMTP and
IMAP rows in Mail Settings become one default account; every lead gets an unsubscribe token; each
lead's category is copied into the new many-to-many table). No data is deleted.

MySQL must use InnoDB (the default). Duplicate protection relies on row locks and unique indexes.

For Redis, bind it to localhost or a private network and set `requirepass`. Use `appendonly yes`
so queued jobs survive a restart.

## 3. Application

```bash
cd /var/www/html
git pull
composer install --no-dev --optimize-autoloader
cp .env.example .env    # first deploy only, then edit (see §4)
php artisan key:generate   # first deploy only - NEVER rotate later: it decrypts stored SMTP/IMAP passwords
php artisan migrate --force
php artisan optimize        # config/route/view cache
php artisan queue:restart   # workers pick up the new code after their current job
chown -R www-data:www-data storage bootstrap/cache
```

## 4. Environment variables

| Variable | Example | Purpose |
|---|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | **Never** debug in production |
| `APP_KEY` | generated | Encrypts SMTP/IMAP passwords - back it up, never change it |
| `APP_URL` | `https://app.example.com` | |
| `APP_TIMEZONE` | `Asia/Kolkata` | The one timezone for the whole app (IST). The MySQL session follows it automatically (`DB_TIMEZONE` overrides, e.g. `+05:30`) |
| `TRUSTED_PROXIES` | `*` or `10.0.0.1` | When behind a load balancer / Cloudflare |
| `DB_*` | | MySQL |
| `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, `REDIS_DB`, `REDIS_CACHE_DB` | `predis`, `127.0.0.1`, `6379` | |
| `QUEUE_CONNECTION` | `redis` | The app's queue: Leads sends, sequences, IMAP polling, Bulks |
| `QUEUE_RETRY_AFTER` | `180` | Must exceed the longest job timeout (120s) |
| `CACHE_STORE` | `redis` | Rate limiter, unique-job and `withoutOverlapping` locks. **Must** be shared by all workers |
| `IMAP_VALIDATE_CERT` | `true` | Validate IMAP TLS certificates; only `false` for self-signed dev servers |
| `SESSION_DRIVER` | `redis` or `database` | |
| `SEQUENCER_PUBLIC_URL` | `https://track.example.com` | Base of tracking/unsubscribe links in emails (defaults to `APP_URL`) |
| `SEQUENCER_FOOTER_ADDRESS` | `Acme Ltd, 1 Main St, London` | Postal address in every footer (CAN-SPAM) |
| `SEQUENCER_DEFAULT_DAILY_LIMIT` | `100` | Default for new sequences |
| `SEQUENCER_DEFAULT_RATE_PER_MINUTE` | `10` | Default for new accounts |
| `SEQUENCER_SMTP_TIMEOUT` | `30` | Seconds |
| `SEQUENCER_IMAP_LOOKBACK_DAYS` | `14` | First poll of a mailbox |
| `SEQUENCER_IMAP_MAX_PER_RUN` | `200` | |
| `SEQUENCER_ALLOW_PRIVATE_HOSTS` | `false` | Keep false in production (SSRF guard) |
| `MAIL_*` | | Only for password-reset emails. Sequence mail uses each account's own SMTP |

Outreach email (sequences and the Leads compose window) is sent through the SMTP settings of each
account in **Settings > Mail Settings**, not `MAIL_*`. IMAP settings per account enable reply and
bounce detection and the Sent-folder copy.

## 5. Workers (Supervisor)

`deploy/supervisor/sequencer-worker.conf` defines one program, `cold-email-worker`: 4 processes running
`queue:work redis --queue=emails,default`. Add processes for throughput; duplicate sequence sends are
impossible by design. In Docker, the `queue` service in `docker-compose.yml` runs the same command.

```bash
supervisorctl reread && supervisorctl update
supervisorctl status
```

`stopwaitsecs` is longer than the job timeout, so a deploy never kills a worker mid-SMTP.
Run `php artisan queue:restart` on every deploy. (This relies on Supervisor's `autorestart`. In the local
Docker setup restart is disabled, so use `docker compose restart queue` there instead.)

## 6. Scheduler (cron)

```
* * * * * www-data cd /var/www/html && php artisan schedule:run >> /dev/null 2>&1
```

Run it on one host. With several app hosts, set `CACHE_STORE=redis` so the overlap locks are shared.
The scheduler only queues work; it never sends.

## 7. Nginx

See `deploy/nginx/sequencer.conf`. It sets HTTPS, security headers, a 25 MB upload limit (CSV),
`no-store` on `/track/*`, denies dotfiles, and adds login rate limiting.

The tracking and unsubscribe URLs must be publicly reachable at `SEQUENCER_PUBLIC_URL`.

## 8. Email deliverability checklist

- SPF, DKIM and DMARC for every sending domain. Each account sends through its own SMTP server.
- Keep the account rate limit conservative (10-30/min) and warm up new mailboxes with a low daily cap.
- `SEQUENCER_FOOTER_ADDRESS` set; the unsubscribe footer and `List-Unsubscribe` headers are automatic.

## 9. Monitoring & recovery

- `storage/logs/laravel.log`: send retries (warning), permanent failures (error), IMAP failures (warning).
  SMTP transcripts and passwords are never logged.
- `php artisan queue:failed`: only infrastructure failures (DB down, worker killed) land here. Business
  failures are recorded on the email log / enrollment instead.
- **Settings > Mail Settings** shows SMTP/IMAP health per account (`smtp_ok`, `imap_ok`, last IMAP error).
- Enrollments that end `failed` (`send_failed` or `delivery_uncertain`) can be retried from the
  sequence's Leads tab after checking the account.
- The **Bulks** page shows progress and a downloadable per-row report for imports and bulk sequence actions.

| Failure | Behaviour |
|---|---|
| SMTP down / 4xx / timeout | retried with backoff 30s, 2m, 10m, 30m (5 attempts), then `failed` |
| SMTP 5xx | not retried |
| IMAP down | recorded on the account, retried on the next poll (every 2 min) |
| Redis down | sends defer (rate limiter fails closed); queue resumes when Redis returns; quotas live in MySQL |
| MySQL blip / deadlock | transactions retried (5x); jobs retried by the queue (5 tries) |
| Worker killed mid-send | at-most-once: the step is marked `delivery_uncertain`, never re-sent automatically |

## 10. Backups

Back up MySQL nightly and `APP_KEY`. Without the key, stored SMTP/IMAP passwords cannot be decrypted.
Redis holds only the transient queue, locks and per-minute counters.
