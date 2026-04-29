# Scaling to 50,000+ Concurrent Users

This document is the operational recipe for running the photo-gallery app at
**50k+ concurrent users**. The application code has already been prepared
(edge cache headers, queue jobs, cache invalidation, read-replica support);
this guide covers the **infrastructure** needed to actually reach that tier.

> **TL;DR** — A single PHP-FPM box caps out around 2k–5k concurrent requests.
> To handle 50k concurrent you must do both of these things:
>
> 1. **Serve 95%+ of public pages from a CDN edge** so PHP is not on the hot path.
> 2. **Horizontally scale** PHP, Redis, and MySQL so the remaining 5% doesn't pile up.

---

## Architecture Overview

```
           ┌──────────────┐
 Client ──▶│  Cloudflare  │───── static assets (CDN_BASE_URL)
           │     (CDN)    │───── cached HTML (EdgeCache middleware)
           └──────┬───────┘
                  │ cache miss or authenticated
                  ▼
           ┌──────────────┐
           │ Load Balancer│  (Cloudflare LB, HAProxy, or AWS ALB)
           └──────┬───────┘
                  │
        ┌─────────┼─────────┐
        ▼         ▼         ▼
     ┌─────┐  ┌─────┐   ┌─────┐
     │ App │  │ App │   │ App │   3–10 Laravel Octane workers
     └──┬──┘  └──┬──┘   └──┬──┘
        └────────┼─────────┘
                 ▼
        ┌────────────────┐
        │  Redis Cluster │  sessions, cache, queue, locks
        └────────────────┘
                 │
        ┌────────┴────────┐
        ▼                 ▼
  ┌──────────┐      ┌──────────┐
  │ MySQL W  │◀───▶ │ MySQL R  │  primary + 2-3 replicas
  └──────────┘      └──────────┘

  ┌────────────────────────────────────────┐
  │ Queue Workers (separate host pool)     │
  │  • downloads queue  (ZIP builds)       │
  │  • mail queue       (SMTP)             │
  │  • photos queue     (thumb/watermark)  │
  └────────────────────────────────────────┘
```

---

## 1. CDN Edge Cache (the biggest lever)

The `EdgeCache` middleware is already applied to every public GET route in
`routes/web.php`. It emits `Cache-Control: s-maxage=X, stale-while-revalidate=Y`
plus CDN-specific headers (`CDN-Cache-Control`, `Cloudflare-CDN-Cache-Control`,
`Surrogate-Control`).

### Cloudflare setup

1. **Add the site** and point DNS at Cloudflare (proxied / orange cloud).
2. **Caching → Configuration**:
   - Caching Level: *Standard*
   - Browser Cache TTL: *Respect Existing Headers* (required!)
3. **Cache Rules** (new): create one rule:
   - Match: `http.request.method eq "GET"`
   - Action: *Eligible for cache*
   - Edge TTL: *Use cache-control header from origin*
4. **Page Rules** (optional belt-and-braces): add a rule for `example.com/*`
   with `Cache Level: Cache Everything`.
5. **Cache → Cache Analytics**: watch *Cache Hit Ratio*. Target: **90%+**.

### CloudFront alternative

If you prefer AWS:
1. Create a distribution with the Laravel origin.
2. Use the managed `CachingOptimized` policy and the `CORS-S3Origin` origin
   request policy.
3. **Behaviors**:
   - Default (`/*`): cache based on headers `Accept-Encoding, Accept-Language`
     and cookie `remember_*` (allow-list, not forward-all).
   - `/assets/*`, `/build/*`: max TTL 1 year (already fingerprinted by Vite).

### Invalidating the edge cache

The `CacheInvalidationObserver` already forgets Laravel cache keys when a
model changes. To also purge the CDN edge, extend the observer with a
Cloudflare API call:

```php
// In CacheInvalidationObserver::invalidate() — add after Cache::forget loop:
if (config('services.cloudflare.zone_id')) {
    dispatch(new \App\Jobs\PurgeCloudflareCacheJob($urls))->onQueue('purge');
}
```

The Cloudflare purge-by-URL endpoint is rate-limited to 30 req/s — that's
why it belongs in a queued job.

---

## 2. Laravel Octane (persistent workers)

Stock PHP-FPM bootstraps the framework on every request (10–30ms overhead).
Octane keeps the app in memory and handles requests like Node — cuts median
latency roughly 4x.

```bash
composer require laravel/octane
php artisan octane:install --server=swoole   # or --server=frankenphp
```

### Run as a systemd service

`/etc/systemd/system/octane.service`:
```ini
[Unit]
Description=Laravel Octane (Swoole)
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/app
ExecStart=/usr/bin/php artisan octane:start \
  --server=swoole \
  --host=127.0.0.1 --port=8000 \
  --workers=4 --task-workers=2 \
  --max-requests=500
Restart=always
RestartSec=1

[Install]
WantedBy=multi-user.target
```

Tune `--workers` to roughly `CPU cores × 2`, `--max-requests=500` recycles
workers before memory fragmentation builds up.

### Caveats

- Singletons live across requests — audit for leaks. `AppServiceProvider`
  already registers services as singletons, which is fine.
- `Auth::user()` must be cleared — Octane does this automatically but custom
  middleware using static state will leak. Grep for `static $` in middleware.

---

## 3. Redis Cluster (sessions, cache, queue, locks)

File-based sessions **will not work** on multiple app servers (sticky sessions
help but break when a pod dies). Single-node Redis caps around 30k ops/sec;
Redis Cluster is horizontally scalable.

### Managed options (recommended)

- **AWS ElastiCache** — 3-node cluster, `cache.r6g.large`.
- **DigitalOcean Managed Redis** — cheaper, same topology.
- **Upstash Redis** — per-request billing, zero ops.

### `.env` for cluster mode

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_CLUSTER=redis
REDIS_HOST=10.0.1.20,10.0.1.21,10.0.1.22
REDIS_PORT=6379
REDIS_PASSWORD=xxx
```

`config/database.php` already reads `REDIS_CLUSTER`; the `phpredis` client
handles slot routing transparently.

### Hot-key audit

Under load, a single Redis key hit 10k+ times per second (e.g. `app_settings_all`)
becomes a hot spot. Use `RedisReplicated` connection or add a small in-process
cache layer (`AppSetting::get()` already memoizes in-request — good).

---

## 4. MySQL Read Replicas

Reads outnumber writes ~50:1 in this app (browsing events >> placing orders).
The `mysql` connection in `config/database.php` is now **already configured**
to split when `DB_READ_HOST` is set.

### AWS RDS setup

1. Create an `r6g.xlarge` primary (writer).
2. Add 2–3 read replicas in the same region, different AZs.
3. Put them behind a single DNS name:
   - RDS Proxy (simpler, adds connection pooling), or
   - HAProxy for round-robin.
4. `.env`:
   ```env
   DB_HOST=primary.cluster-xxx.rds.amazonaws.com
   DB_READ_HOST=reader.cluster-ro-xxx.rds.amazonaws.com
   DB_READ_USERNAME=app_read      # optional, lower privileges
   ```

### Verify the split works

```php
// tinker
DB::statement('SELECT 1');
DB::connection()->getReadPdo();   // → read replica
DB::connection()->getPdo();       // → primary
```

Or enable query log briefly: writes should go to `DB_HOST`, reads (after a
non-sticky request) should land on `DB_READ_HOST`.

### Replication lag

`sticky=true` in `config/database.php` ensures read-your-own-writes within
a single request. But cross-request lag (AWS RDS typically <100ms) will
show up as "I placed an order and the receipt page says my order doesn't
exist yet." Mitigations:

- Use `DB::connection('mysql')->enableQueryLog()` to spot replica-lag bugs.
- For critical reads (order confirmation page), force the primary:
  ```php
  $order = Order::on('mysql')->usingConnection(function () { ... })->find($id);
  // or simpler: just use ->from(DB::raw('orders FORCE INDEX ...')) on writer
  ```

---

## 5. Queue Workers

Three queue jobs are already wired:
| Queue       | Job                      | What it does                    |
| ----------- | ------------------------ | ------------------------------- |
| `mail`      | `SendMailJob`            | All transactional email         |
| `downloads` | `BuildOrderZipJob`       | Build order ZIPs asynchronously |
| `photos`    | `ProcessUploadedPhotoJob`| Watermark, thumb, EXIF strip    |

### supervisord config

`/etc/supervisor/conf.d/laravel-workers.conf`:
```ini
[program:mail-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/app/artisan queue:work redis --queue=mail --tries=3 --timeout=60 --sleep=1
numprocs=4
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/laravel/mail.log
stopwaitsecs=60

[program:downloads-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/app/artisan queue:work redis --queue=downloads --tries=2 --timeout=1800 --sleep=3
numprocs=2
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/laravel/downloads.log
stopwaitsecs=1801

[program:photos-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/app/artisan queue:work redis --queue=photos --tries=3 --timeout=300 --sleep=1
numprocs=6
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/laravel/photos.log
stopwaitsecs=300
```

Tuning: `numprocs` scales with expected job rate. A ZIP job holds a worker
for minutes, so 2 workers = 2 concurrent ZIP builds; if you see the
`downloads` queue depth grow during peak, bump it up.

### Horizon (optional)

Laravel Horizon gives you a dashboard + auto-scaling based on queue length.
Worth installing once you have >10 worker processes.

```bash
composer require laravel/horizon
php artisan horizon:install
# route: /horizon (protect with admin middleware)
```

---

## 6. Load Balancer

- **Cloudflare Load Balancer** is the simplest — health-checked round-robin
  with automatic failover across regions. ~$5/mo per pool.
- **HAProxy** if you want to self-host. Stick-table for session affinity is
  only needed if you skip Redis sessions.
- **AWS ALB** — pairs well with CloudFront and RDS.

### Session affinity

With Redis sessions (recommended) you don't need sticky sessions — any pod
can handle any request. Omit affinity rules from your load balancer to
maximize fleet utilization.

---

## 7. Nginx tuning

Even with Octane, you still want Nginx in front for TLS + static files.

`/etc/nginx/nginx.conf` (excerpt):
```nginx
worker_processes auto;
worker_rlimit_nofile 65535;
events {
    worker_connections 16384;
    multi_accept on;
    use epoll;
}
http {
    keepalive_timeout 65;
    keepalive_requests 1000;
    client_max_body_size 100M;    # photo uploads

    # Let CDN do the gzip — Nginx only compresses for direct hits.
    gzip on;
    gzip_types text/plain text/css application/json application/javascript;

    # Rate-limit at Nginx layer as a second line of defense.
    limit_req_zone $binary_remote_addr zone=public:10m rate=30r/s;
    limit_req_zone $binary_remote_addr zone=api:10m    rate=10r/s;

    upstream octane {
        server 127.0.0.1:8000;
        keepalive 32;
    }
}
```

`/etc/nginx/sites-enabled/app`:
```nginx
server {
    listen 443 ssl http2;
    server_name example.com;

    # Static assets → served directly, bypass PHP entirely.
    location ~* \.(css|js|jpg|jpeg|png|webp|svg|woff2)$ {
        root /var/www/app/public;
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files $uri @octane;
    }

    location / {
        limit_req zone=public burst=20 nodelay;
        try_files $uri @octane;
    }

    location /api/ {
        limit_req zone=api burst=5 nodelay;
        try_files $uri @octane;
    }

    location @octane {
        proxy_pass http://octane;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $remote_addr;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_http_version 1.1;
        proxy_set_header Connection "";
    }
}
```

---

## 8. Observability

You cannot scale what you cannot measure.

- **Logs**: stream to CloudWatch / Datadog / Loki. `LOG_CHANNEL=stderr` when
  running under systemd + journald.
- **Metrics**: `laravel/pulse` package gives you a free-ish Grafana-lite.
- **APM**: NewRelic or Datadog APM — pin expensive queries and jobs.
- **Error tracking**: Sentry. `composer require sentry/sentry-laravel`.

Key dashboards to build first:
1. **Cache hit ratio** at the CDN (goal: 90%+).
2. **Queue depth** per queue (alert on `mail > 1000` or `downloads > 50`).
3. **Redis ops/sec + slowlog** (alert on any command >50ms).
4. **MySQL replica lag** (alert on > 5s).
5. **Nginx 5xx rate** (alert on >1% over 5 minutes).

---

## 9. Cost estimate (AWS, rough)

For a **steady-state 50k concurrent** deployment (~5k rps after CDN):

| Component                           | Cost / month |
|-------------------------------------|--------------|
| 4× `c6g.xlarge` EC2 (Octane)        | $420         |
| 2× `c6g.large` EC2 (queue workers)  | $90          |
| RDS `r6g.xlarge` + 2 read replicas  | $650         |
| ElastiCache 3-node `r6g.large`      | $540         |
| CloudFront (1TB egress)             | $85          |
| ALB + data transfer                 | $40          |
| S3 (photos) + R2 (ZIPs)             | $100         |
| **Total**                           | **~$1,925**  |

Cloudflare (no per-request charges) can cut the CloudFront line by ~70%.

---

## 10. Deployment checklist

Copy-paste this into your runbook before going live:

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `php artisan config:cache route:cache view:cache event:cache`
- [ ] `php artisan optimize`
- [ ] `php artisan storage:link`
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `npm run build` (Vite / assets)
- [ ] `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`
- [ ] `DB_READ_HOST` set, verified reads go to replica
- [ ] `CDN_BASE_URL` set, verified assets load from CDN
- [ ] Cloudflare cache rules active, hit ratio > 80% after 24h
- [ ] Queue workers running under supervisord, all 3 queues draining
- [ ] Octane running under systemd, `max-requests=500`
- [ ] Nginx rate limits in place
- [ ] Error tracking (Sentry) capturing a test exception
- [ ] Load test: `k6` or `wrk` hits 10k rps without 5xx

---

## Appendix: load testing

```bash
# Smoke test with k6 — adjust VUs upwards
cat > loadtest.js <<'EOF'
import http from 'k6/http';
import { sleep } from 'k6';

export const options = {
  stages: [
    { duration: '2m', target: 500 },    // ramp
    { duration: '5m', target: 5000 },   // steady
    { duration: '2m', target: 0 },      // cool
  ],
};

export default function () {
  const urls = ['/', '/events', '/events/summer-festival-2026', '/photographers/1'];
  http.get(`https://example.com${urls[Math.floor(Math.random()*urls.length)]}`);
  sleep(Math.random() * 3);
}
EOF

k6 run loadtest.js
```

Read the result:
- `http_req_duration{p(95)}` — should stay under 500ms even at peak.
- `http_req_failed` — should stay below 1%.
- If both hold, the Cloudflare cache is doing its job; PHP is barely touched.
