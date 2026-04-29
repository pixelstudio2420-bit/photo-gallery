# Face-Search Production Deploy Runbook

Follow these steps in order before enabling face-search for real users.

Total time: ~30 min (mostly AWS Console clicks).

---

## 1. Create IAM User + Policy in AWS

1. AWS Console → **IAM** → Users → **Create user**
2. Name: `photo-gallery-rekognition` (anything, just be consistent)
3. **Do NOT** give Console access — programmatic (access key) only
4. Attach policy → **Create policy** → JSON tab → paste:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "PhotoGalleryRekognition",
      "Effect": "Allow",
      "Action": [
        "rekognition:CreateCollection",
        "rekognition:DeleteCollection",
        "rekognition:ListCollections",
        "rekognition:DescribeCollection",
        "rekognition:IndexFaces",
        "rekognition:ListFaces",
        "rekognition:SearchFacesByImage",
        "rekognition:DetectFaces",
        "rekognition:DeleteFaces",
        "rekognition:CompareFaces"
      ],
      "Resource": "*"
    }
  ]
}
```

5. Save policy as `PhotoGalleryRekognitionPolicy`, attach to the user
6. After user is created → **Security credentials** → **Create access key** → type "Application running outside AWS"
7. Copy **Access key ID** (AKIA…) and **Secret access key** — you won't see the secret again

---

## 2. Set credentials in the app's Admin Settings

Login as admin → **Settings** → AWS / Storage tab, fill in:

| Key | Value |
|---|---|
| `aws_key` | the AKIA… access key |
| `aws_secret` | the wJa… secret access key |
| `aws_region` | `ap-southeast-1` (Singapore — closest low-latency region for TH users) |

Save. The service reads from `app_settings`, so **no .env change + no redeploy needed**.

Sanity-check:

```bash
php artisan tinker --execute="echo app(\App\Services\FaceSearchService::class)->isConfigured() ? 'OK' : 'NOT OK';"
```

Should print `OK`.

---

## 3. Run the migration on production

```bash
cd /var/www/photo-gallery-tailwind
php artisan migrate --force
```

Verify:

```bash
php artisan migrate:status | grep -i rekognition
php artisan migrate:status | grep -i face_search_enabled
```

Both should show `Ran`.

---

## 4. Verify the queue worker is running

Auto-indexing runs via `ProcessUploadedPhotoJob`, which is dispatched to the queue.
**If the queue isn't running, uploaded photos won't get indexed.**

Check with systemd:
```bash
systemctl status laravel-queue
```

Or supervisor:
```bash
supervisorctl status
```

Or ad-hoc for testing:
```bash
php artisan queue:work --tries=3 --timeout=120
```

If not running, set up supervisor — minimum config (edit `/etc/supervisor/conf.d/photo-gallery.conf`):

```ini
[program:photo-gallery-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/photo-gallery-tailwind/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/photo-gallery-queue.log
stopwaitsecs=3600
```

Then:
```bash
supervisorctl reread
supervisorctl update
supervisorctl start photo-gallery-queue:*
```

---

## 5. Backfill existing events

For events that had photos uploaded BEFORE auto-indexing was wired in:

```bash
# Dry-run first — see what would be processed
php artisan rekognition:reindex-event --all --dry-run

# Real run — all events
php artisan rekognition:reindex-event --all

# Or one event at a time
php artisan rekognition:reindex-event 42
```

Cost estimate: **$0.001 per photo** (IndexFaces).
1,000 photos ≈ $1.00. 10,000 photos ≈ $10.00.

---

## 6. Set a Budget Alert

AWS Console → **Billing and Cost Management** → Budgets → **Create budget**:

- Type: *Customized (advanced)*
- Period: Monthly
- Budgeted amount: **$30** (adjust — see cost notes below)
- Threshold alerts:
  - 50% → warning email
  - 80% → urgent email
  - 100% → page-you email

**Cost back-of-envelope:**
- Indexing (one-time per photo): $0.001
- Search queries: $0.001 per call
- Storage of face metadata: free (included)
- For 5,000 monthly searches + 2,000 new photo indexes: ~$7/month
- Set budget to 3–4× your expected baseline so alerts fire before disasters

---

## 7. Verify everything in browser

1. Go to Admin → Events → any event with photos
2. Look for the **face-coverage widget** under the stat cards — should show X% indexed
3. If the widget says "AWS not configured", re-check step 2
4. Go to the public event page → click **"ค้นหาด้วยใบหน้า"** → ensure the form loads
5. Tick the PDPA consent box + upload a test selfie → check the response returns matches

---

## 8. Schedule the cleanup cron

The `rekognition:cleanup-orphans` command is already registered to run daily at 04:00 — no manual step needed, provided `schedule:run` is in your system cron:

```cron
* * * * * cd /var/www/photo-gallery-tailwind && php artisan schedule:run >> /dev/null 2>&1
```

Confirm it:
```bash
crontab -l -u www-data | grep schedule:run
```

---

## 9. Pre-launch smoke test

A 2-minute sanity check before telling users about the feature:

```bash
# 1. AWS creds detected?
php artisan tinker --execute="dump(app(\App\Services\FaceSearchService::class)->isConfigured());"

# 2. Queue running?
ps aux | grep queue:work | grep -v grep

# 3. Face column exists?
php artisan db:table event_photos | grep rekognition_face_id

# 4. Indexed photo count > 0?
php artisan tinker --execute="echo \App\Models\EventPhoto::whereNotNull('rekognition_face_id')->count();"
```

If all 4 are green — ship it.

---

## Rollback

If something goes wrong after launch:

1. **Disable feature globally**: Admin Settings → clear `aws_key` → face-search returns "not configured" gracefully
2. **Disable per-event**: Admin Events → uncheck "เปิดค้นหาด้วยใบหน้า"
3. **Nuclear option**: `php artisan migrate:rollback --step=2` (rolls back face column + face_search_enabled)

The feature is designed to fail soft — no migration rollback is needed in practice because turning off `aws_key` is instant.
