# คู่มือ Deploy บน Laravel Cloud — แบบละเอียด สำหรับมือใหม่

> เอกสารนี้เขียนขึ้นใหม่ทั้งหมด สำหรับการ deploy โปรเจกต์นี้บน **Laravel Cloud** (https://cloud.laravel.com)
> ทุกขั้นตอนเขียนแบบมือใหม่ทำตามได้ ไม่ต้องมีพื้นฐาน DevOps มาก่อน
> เวลารวมที่ต้องใช้: ~30-60 นาที (ครั้งแรก)

## Laravel Cloud คืออะไร?

Laravel Cloud คือบริการ hosting ทางการของ Laravel เอง (เปิดตัวปี 2025) — deploy โดย push git commit เดียวจบ ไม่ต้องตั้งค่า VPS, Nginx, PHP-FPM, supervisor เอง

**สิ่งที่ Laravel Cloud จัดการให้อัตโนมัติ:**
- Web server (Nginx + PHP-FPM)
- Queue worker
- Scheduler (cron)
- SSL certificate
- Auto-scaling
- Zero-downtime deploy
- Logs streaming

**สิ่งที่ใช้คู่กัน (ตัวเลือก):**
- **Laravel Cloud Postgres** — managed PostgreSQL
- **Laravel Cloud Redis** (option) — managed cache/queue
- หรือใช้ external services (PlanetScale, Supabase, Upstash, etc.)

---

## สารบัญ

1. [เตรียมตัวก่อน deploy](#1-เตรียมตัวก่อน-deploy)
2. [ขั้นตอนที่ 1: สมัคร Laravel Cloud](#2-ขั้นตอนที่-1-สมัคร-laravel-cloud)
3. [ขั้นตอนที่ 2: Push โค้ดขึ้น GitHub](#3-ขั้นตอนที่-2-push-โค้ดขึ้น-github)
4. [ขั้นตอนที่ 3: สร้าง Application](#4-ขั้นตอนที่-3-สร้าง-application)
5. [ขั้นตอนที่ 4: ตั้งค่า Database (Postgres)](#5-ขั้นตอนที่-4-ตั้งค่า-database-postgres)
6. [ขั้นตอนที่ 5: ตั้งค่า Redis (Cache + Queue)](#6-ขั้นตอนที่-5-ตั้งค่า-redis-cache--queue)
7. [ขั้นตอนที่ 6: ตั้งค่า Storage (R2 / S3)](#7-ขั้นตอนที่-6-ตั้งค่า-storage-r2--s3)
8. [ขั้นตอนที่ 7: ตั้งค่า Environment Variables](#8-ขั้นตอนที่-7-ตั้งค่า-environment-variables)
9. [ขั้นตอนที่ 8: Deploy ครั้งแรก](#9-ขั้นตอนที่-8-deploy-ครั้งแรก)
10. [ขั้นตอนที่ 9: เปิด Queue Worker + Scheduler](#10-ขั้นตอนที่-9-เปิด-queue-worker--scheduler)
11. [ขั้นตอนที่ 10: ผูก Custom Domain + SSL](#11-ขั้นตอนที่-10-ผูก-custom-domain--ssl)
12. [ขั้นตอนที่ 11: ตั้งค่า Integration ภายนอก](#12-ขั้นตอนที่-11-ตั้งค่า-integration-ภายนอก)
13. [ขั้นตอนที่ 12: เปิดใช้งานจริง](#13-ขั้นตอนที่-12-เปิดใช้งานจริง)
14. [การ deploy ครั้งต่อๆ ไป](#14-การ-deploy-ครั้งต่อๆ-ไป)
15. [Troubleshooting](#15-troubleshooting)

---

## 1. เตรียมตัวก่อน deploy

### สิ่งที่ต้องมี (ติดตั้งใน machine ของคุณ)

```bash
git --version       # ต้องมี Git
node --version      # ต้องมี Node.js 18+ (สำหรับ build assets)
php --version       # ต้องมี PHP 8.2+ (สำหรับ test ในเครื่อง)
composer --version  # ต้องมี Composer 2.x
```

ถ้ายังไม่มี:
- Windows: ใช้ XAMPP + Composer + Node.js (สามตัวอยู่แล้วในเครื่องคุณตอนนี้ ✅)
- macOS: `brew install php composer node git`
- Linux: `apt install php8.2 composer nodejs git -y`

### สิ่งที่ต้องสมัคร (ถ้ายังไม่มี)

| บริการ | ใช้ทำอะไร | เริ่มต้นฟรี |
|---|---|---|
| **GitHub** | เก็บ source code | ✅ ฟรี |
| **Laravel Cloud** | hosting | ✅ มี free tier (Hobby plan) |
| **Cloudflare** | DNS + R2 storage | ✅ ฟรี (R2 ฟรี 10GB) |
| **LINE Developers** | LINE Messaging API | ✅ ฟรี |
| **Google Cloud** | OAuth + Drive | ✅ ฟรี |
| **SlipOK** | ตรวจสลิป PromptPay | มีแพ็คเกจฟรีจำกัด |
| **AWS** | Rekognition + S3 (option) | มี free tier |
| **Sentry** | error tracking (option) | ✅ ฟรี dev tier |

> 💡 **Tip:** เริ่มจากของฟรีก่อน เปิดเฉพาะ feature ที่ต้องใช้

---

## 2. ขั้นตอนที่ 1: สมัคร Laravel Cloud

1. ไปที่ https://cloud.laravel.com
2. กด **Sign up** → ใช้ GitHub login (แนะนำ — เพราะต้องเชื่อม GitHub อยู่แล้ว)
3. ยืนยัน email
4. เข้า dashboard ของ Laravel Cloud

### เลือก Plan

Laravel Cloud มี 3 plan หลัก:

| Plan | ราคา | เหมาะกับ |
|---|---|---|
| **Hobby** | ฟรี | ลองเล่น, MVP, traffic น้อย (sleep หลัง 30 นาทีไม่มีใครเข้า) |
| **Production** | $20+/mo | production จริง, no sleep, autoscale |
| **Enterprise** | สั่งราคา | scale ใหญ่ |

> **มือใหม่แนะนำ Hobby ก่อน** ถ้าใช้แล้ว traffic เริ่มเยอะ → upgrade เป็น Production

---

## 3. ขั้นตอนที่ 2: Push โค้ดขึ้น GitHub

ถ้ายังไม่ได้ push โปรเจกต์นี้ขึ้น GitHub:

### A. สร้าง repo ใหม่บน GitHub

1. ไปที่ https://github.com/new
2. **Repository name**: `photo-gallery` (หรือชื่อที่คุณชอบ)
3. **Visibility**: Private (แนะนำ — มี API keys)
4. ❌ ไม่ต้องติ๊ก Initialize with README
5. กด **Create repository**

### B. Push โค้ดจากเครื่อง

เปิด terminal ใน folder โปรเจกต์ (`C:\xampp\htdocs\photo-gallery-pgsql`):

```bash
# ถ้ายังไม่ได้ init git
git init
git branch -M main

# เพิ่ม .env เข้า .gitignore (สำคัญ! กันรั่ว secret)
echo ".env" >> .gitignore
echo "node_modules/" >> .gitignore
echo "vendor/" >> .gitignore
echo "storage/logs/*" >> .gitignore
echo "public/build/" >> .gitignore
echo "storage/framework/cache/data/*" >> .gitignore
echo "storage/framework/sessions/*" >> .gitignore
echo "storage/framework/views/*" >> .gitignore

# Add + commit ไฟล์ทั้งหมด
git add .
git commit -m "Initial commit"

# ผูกกับ GitHub repo
git remote add origin https://github.com/YOUR_USERNAME/photo-gallery.git

# Push
git push -u origin main
```

ถ้ามีไฟล์อื่นใหญ่ ๆ (เช่น `database/database.sqlite` ขนาดใหญ่) ให้ลบออกก่อน push:

```bash
git rm --cached database/database.sqlite 2>/dev/null
echo "database/*.sqlite" >> .gitignore
git add .gitignore
git commit -m "Exclude SQLite from git"
```

---

## 4. ขั้นตอนที่ 3: สร้าง Application

ใน Laravel Cloud dashboard:

1. กด **Create Application**
2. **Source**: เลือก GitHub → ให้สิทธิ์เข้าถึง repo `photo-gallery`
3. **Branch**: `main`
4. **Name**: `photo-gallery-prod` (ตั้งอะไรก็ได้)
5. **Region**: เลือกที่ใกล้ผู้ใช้
   - 🇹🇭 ลูกค้าในไทย → `ap-southeast-1` (Singapore)
   - 🇺🇸 ลูกค้า US → `us-east-1`
6. กด **Create**

Laravel Cloud จะ:
- Clone repo
- Detect ว่าเป็น Laravel project (เห็น `composer.json` + `bootstrap/app.php`)
- รัน `composer install`
- รัน `npm install && npm run build` (auto-detect Vite)
- ⚠️ **deploy ครั้งแรกจะล้มเหลว** เพราะยังไม่ได้ตั้ง env vars + DB → ปกติ

---

## 5. ขั้นตอนที่ 4: ตั้งค่า Database (Postgres)

ระบบนี้ใช้ **PostgreSQL 16** (ไม่ใช่ MySQL — ระบบมี migration ที่ใช้ Postgres-specific syntax เช่น `STRING_AGG`, `INTERVAL`, partial unique indexes)

### Option A: Laravel Cloud Postgres (แนะนำ)

ใน application dashboard:

1. ไปที่ tab **Resources**
2. กด **Add Database**
3. **Type**: PostgreSQL
4. **Version**: 16
5. **Plan**: เริ่มที่ Starter (1GB) → upgrade ภายหลังได้
6. **Region**: เดียวกับ application
7. กด **Create**

Laravel Cloud จะตั้ง env vars ให้อัตโนมัติ:
```
DB_CONNECTION=pgsql
DB_HOST=...
DB_PORT=5432
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
```

### Option B: External Postgres (Supabase / Neon / etc.)

ถ้าใช้ของนอก เช่น **Neon.tech** (Postgres serverless ฟรี):

1. สมัคร Neon → สร้าง project
2. เก็บ connection string
3. กลับไปที่ Laravel Cloud → ตั้ง env manually:

```env
DB_CONNECTION=pgsql
DB_HOST=ep-xxxx.us-east-2.aws.neon.tech
DB_PORT=5432
DB_DATABASE=neondb
DB_USERNAME=postgres
DB_PASSWORD=your-password
DB_SSLMODE=require
```

---

## 6. ขั้นตอนที่ 5: ตั้งค่า Redis (Cache + Queue)

> Redis ใช้สำหรับ cache + queue + session — ไม่บังคับ แต่แนะนำมากสำหรับ production

### Option A: Laravel Cloud Redis

1. ไปที่ tab **Resources** → **Add Redis**
2. **Plan**: Starter
3. กด **Create**

Env vars จะถูก inject อัตโนมัติ:
```
REDIS_HOST=...
REDIS_PASSWORD=...
REDIS_PORT=6379
```

### Option B: Upstash Redis (ฟรี 10K commands/วัน)

1. สมัคร https://upstash.com → Create Database (Region: Singapore)
2. เก็บ Endpoint + Password
3. ตั้งใน Laravel Cloud:

```env
REDIS_HOST=xxx.upstash.io
REDIS_PORT=6379
REDIS_PASSWORD=xxxx
```

### บังคับใช้ Redis สำหรับ cache + queue + session

ตั้งใน env (ถ้าไม่มี Redis ปล่อยเป็น `database` ก็ได้):

```env
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

---

## 7. ขั้นตอนที่ 6: ตั้งค่า Storage (R2 / S3)

ระบบนี้เก็บรูปทุกตัวบน object storage — ไม่ใช่ใน server (เพราะ photo file ใหญ่)

### แนะนำ: Cloudflare R2 (ถูกกว่า S3 ~10x — egress ฟรี)

1. สมัคร https://cloudflare.com (ฟรี)
2. Dashboard → R2 Object Storage → Create bucket
   - **Name**: `photo-gallery-media`
   - **Location**: ASIA (Asia-Pacific)
3. R2 → Manage API Tokens → Create API Token
   - **Permission**: Object Read & Write
   - **Specify bucket**: `photo-gallery-media`
4. เก็บ:
   - Access Key ID
   - Secret Access Key
   - Account ID (ดูที่ R2 Overview → Account ID)
5. (option) Public access:
   - Bucket settings → Public Access → Allow Access
   - ได้ public URL: `https://pub-xxxxx.r2.dev`

### ตั้งใน Laravel Cloud env:

```env
FILESYSTEM_DISK=r2

AWS_ACCESS_KEY_ID=your-r2-access-key
AWS_SECRET_ACCESS_KEY=your-r2-secret
AWS_DEFAULT_REGION=auto
AWS_BUCKET=photo-gallery-media
AWS_USE_PATH_STYLE_ENDPOINT=false
AWS_ENDPOINT=https://YOUR-ACCOUNT-ID.r2.cloudflarestorage.com
AWS_URL=https://pub-xxxxx.r2.dev
```

> ⚠️ R2 ใช้ region = `auto` (ไม่ใช่ region จริง)

### Option B: AWS S3

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=photo-gallery-prod
AWS_USE_PATH_STYLE_ENDPOINT=false
```

---

## 8. ขั้นตอนที่ 7: ตั้งค่า Environment Variables

ใน Laravel Cloud dashboard → application → tab **Environment**

### Env vars ที่ **บังคับ** ต้องตั้ง:

```env
# ── Core ──
APP_NAME="Photo Gallery"
APP_ENV=production
APP_KEY=               # จะ generate ด้านล่าง
APP_DEBUG=false
APP_URL=https://your-app.cloud.laravel.com   # หรือ custom domain
APP_LOCALE=th
APP_TIMEZONE=Asia/Bangkok
APP_FALLBACK_LOCALE=en

# ── Logging ──
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning   # production: warning ขึ้นไป (debug จะ log เยอะ)

# ── Session / Cache / Queue ──
SESSION_DRIVER=redis           # หรือ database ถ้าไม่มี Redis
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true     # บังคับ HTTPS

CACHE_STORE=redis              # หรือ database
QUEUE_CONNECTION=redis         # หรือ database

# ── Database (Laravel Cloud จะ auto-inject ถ้าใช้ Cloud Postgres) ──
DB_CONNECTION=pgsql
# DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD จะถูก auto-set

# ── Storage (จาก section 7) ──
FILESYSTEM_DISK=r2
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=auto
AWS_BUCKET=photo-gallery-media
AWS_ENDPOINT=https://YOUR-ACCOUNT.r2.cloudflarestorage.com
AWS_URL=https://pub-xxxxx.r2.dev
AWS_USE_PATH_STYLE_ENDPOINT=false
```

### Env vars **เลือกได้** (ใส่เฉพาะที่ใช้):

```env
# ── Mail (สำหรับส่ง notification) ──
MAIL_MAILER=ses            # หรือ smtp, postmark, resend
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Photo Gallery"
# ถ้าใช้ SES:
AWS_ACCESS_KEY_ID=          # ใช้ key เดียวกับ R2 ได้ (หรือคนละ key)
AWS_SES_REGION=ap-southeast-1

# ── LINE ──
LINE_CHANNEL_ACCESS_TOKEN=your-token
LINE_CHANNEL_SECRET=your-secret
# (อื่นๆ ตั้งใน /admin/settings/line ภายในระบบได้)

# ── Sentry ──
SENTRY_LARAVEL_DSN=https://xxx@sentry.io/yyy
SENTRY_ENVIRONMENT=production
SENTRY_TRACES_SAMPLE_RATE=0.1

# ── AI ──
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
```

> 💡 **Payment gateway keys (Stripe, Omise, SlipOK, etc.) — ตั้งใน `/admin/settings/payment-gateways` ใน UI หลัง deploy** ไม่ต้องตั้งใน env

### Generate APP_KEY

ทำในเครื่องของคุณก่อน push:

```bash
cd C:\xampp\htdocs\photo-gallery-pgsql
php artisan key:generate --show
# ได้ output แบบ: base64:xxxxx...
```

Copy ค่านั้นไปใส่ใน Laravel Cloud env → `APP_KEY=base64:xxxxx...`

> ⚠️ **APP_KEY ใหม่หลัง deploy = ทุก session/encrypted data ถูก decrypt ไม่ได้** — ตั้งครั้งเดียว แล้วอย่าเปลี่ยน

---

## 9. ขั้นตอนที่ 8: Deploy ครั้งแรก

ใน Laravel Cloud dashboard:

1. ไปที่ tab **Deployments**
2. กด **Trigger Deployment** (หรือรอ auto-trigger จาก git push)

Laravel Cloud จะรันอัตโนมัติ:
- `composer install --no-dev --optimize-autoloader`
- `npm ci && npm run build`
- `php artisan storage:link`
- `php artisan migrate --force`
- (ระหว่าง deploy เซิร์ฟเวอร์ใหม่ขึ้นมา → traffic ไม่ขาด)

### ดู logs

ระหว่าง deploy → ดูที่ tab **Logs** → realtime stream ของ build + runtime

ถ้า fail:
- เจอ error PHP version → ตรวจ `composer.json` (ต้อง `^8.2`)
- เจอ error Node version → ตั้ง env `NODE_VERSION=20`
- เจอ error migration → ดู section troubleshooting

### Seed ข้อมูลตั้งต้น (สำคัญมาก!)

หลัง deploy สำเร็จ ใน Laravel Cloud → tab **Console** (web SSH):

```bash
# ตั้ง admin account default
php artisan db:seed --class=DefaultAccountsSeeder

# ตั้ง app settings (mail toggle, credits OFF, chat OFF, etc.)
php artisan db:seed --class=AppSettingsSeeder

# ตั้ง alert rules 16 ตัว
php artisan db:seed --class=DefaultAlertRulesSeeder

# ตั้ง addon catalog
php artisan db:seed --class=AddonItemsSeeder

# ตั้ง subscription plans (ถ้ามี)
php artisan db:seed --class=PricingPackageSeeder

# ตั้ง event categories
php artisan db:seed --class=EventCategorySeeder

# ตั้ง Thai geography (จังหวัด/อำเภอ)
php artisan db:seed --class=ThaiGeographySeeder

# ตั้งบัญชี Thai banks
php artisan db:seed --class=ThaiBankSeeder

# (option) สำหรับทดสอบ — บัญชีช่างภาพ + อีเวนต์ตัวอย่าง
php artisan db:seed --class=TestPhotographersSeeder
php artisan db:seed --class=TestEventsSeeder
```

หรือรันทีเดียว:
```bash
php artisan db:seed   # รัน DatabaseSeeder.php (master)
```

### Login ครั้งแรก

ไปที่ `https://your-app.cloud.laravel.com/admin/login`

```
Email: admin@photogallery.com
Password: password123
```

⚠️ **เปลี่ยน password ทันที** ที่ `/admin/admins`

---

## 10. ขั้นตอนที่ 9: เปิด Queue Worker + Scheduler

Laravel Cloud จัดการให้ทั้ง 2 อย่างผ่าน UI

### Queue Worker

1. Application → tab **Workers**
2. กด **Add Worker**
3. ตั้งค่า:
   - **Connection**: `redis` (หรือ `database` ถ้าไม่มี Redis)
   - **Queue**: `default,notifications,payouts`
   - **Tries**: 3
   - **Timeout**: 600 (10 นาที — สำหรับ photo processing)
   - **Sleep**: 3
   - **Backoff**: 60
4. (option) ตั้ง **Workers count**: 2-4 (ขึ้นอยู่กับ traffic)
5. กด **Save**

### Scheduler

Laravel Cloud มี **Scheduler** built-in:

1. Application → tab **Scheduler**
2. กด **Enable Scheduler**
3. แค่นั้น — Laravel Cloud จะรัน `php artisan schedule:run` ทุกนาทีให้อัตโนมัติ

ระบบ scheduler จะรัน 30+ jobs (ดู `routes/console.php`) เช่น:
- Backup ตี 3 ทุกวัน
- Subscription renewal hourly
- LINE health check รายชั่วโมง
- Slip reverify ทุก 15 นาที
- ฯลฯ

### ตรวจว่า worker + scheduler ทำงานหรือยัง

ใน Laravel Cloud → tab **Logs** → ดู stream:

```
[2026-04-29 10:00:00] Scheduler running...
Running [presence:cleanup] -> stopped
Running [bookings:send-reminders] -> stopped

Worker [default] processing job: App\Jobs\ProcessPhotoCache
```

ใน admin → `/admin/diagnostics`:
- Queue heartbeat: should be < 5 min ago
- Scheduler last run: should be < 1 min ago

---

## 11. ขั้นตอนที่ 10: ผูก Custom Domain + SSL

Laravel Cloud ให้ subdomain ฟรี เช่น `your-app.cloud.laravel.com` (มี SSL อยู่แล้ว)

ถ้าต้องการ domain ตัวเอง (เช่น `photogallery.co.th`):

1. Application → tab **Domains**
2. กด **Add Domain** → ใส่ `photogallery.co.th`
3. Laravel Cloud จะแสดง DNS records ที่ต้องตั้ง

### ตั้งค่า DNS (ที่ provider เช่น Cloudflare)

ตัวอย่าง — Cloudflare DNS:

| Type | Name | Content | Proxy |
|---|---|---|---|
| CNAME | `@` (root) | `your-app.cloud.laravel.com` | DNS only (gray cloud) |
| CNAME | `www` | `your-app.cloud.laravel.com` | DNS only |

> ⚠️ ถ้าใช้ Cloudflare proxy (orange cloud) ต้องตั้ง SSL mode เป็น **Full (strict)** ที่ Cloudflare ก่อน

4. รอ ~5-15 นาที ให้ DNS propagate
5. กด **Verify** ใน Laravel Cloud → SSL จะถูก issue อัตโนมัติ (Let's Encrypt)

### บังคับ HTTPS

ตั้ง env:
```env
APP_URL=https://photogallery.co.th
SESSION_SECURE_COOKIE=true
```

---

## 12. ขั้นตอนที่ 11: ตั้งค่า Integration ภายนอก

ทุก integration ตั้งใน admin UI (ไม่ใช่ใน env) — ดูคู่มือใน `MANUAL_2026.md` section 7

ลำดับที่แนะนำ (ทำตามลำดับ):

### A. Mail (ส่ง email) — **ลำดับแรกสุด**

แอดมินไม่ได้รับ notification ถ้าไม่ตั้ง mail

1. ใช้ Amazon SES, Postmark, Resend, หรือ SMTP
2. ตั้ง env:
   ```env
   MAIL_MAILER=ses
   MAIL_FROM_ADDRESS=noreply@photogallery.co.th
   ```
3. ทดสอบใน Laravel Cloud Console:
   ```bash
   php artisan tinker
   > Mail::raw('test', fn($m) => $m->to('your@email.com')->subject('Test'));
   ```

### B. Payment Gateway — **ก่อนเปิดให้ลูกค้าซื้อ**

ที่ `/admin/settings/payment-gateways`

อย่างน้อยต้องเปิด **1 ตัว**:

**ขั้นต่ำสุด — แค่ PromptPay (ไม่ต้องสมัคร gateway):**
1. ตั้งบัญชีรับเงิน (PromptPay/bank) ที่ `/admin/settings/payment-gateways`
2. ระบบสร้าง QR ให้ลูกค้าเอง
3. ลูกค้าจ่ายเสร็จ → upload สลิป → admin approve manually
4. ใช้ได้ทันที ไม่ต้องสมัครอะไร ✅

**แนะนำ — เพิ่ม SlipOK (ตรวจสลิปอัตโนมัติ):**
1. สมัคร SlipOK ที่ https://slipok.com
2. กรอก API key + branch ID ใน `/admin/settings/payment-gateways`
3. เปิด `slipok_enabled` + ตั้ง `slip_verify_mode = auto`
4. SlipOK จะตรวจสลิปอัตโนมัติ (~3-5 วินาที) ลด workload admin

**Production (ถ้ามีลูกค้าเยอะ) — เพิ่ม Stripe / Omise:**
- Stripe: support card + Apple Pay + Google Pay
- Omise: support PromptPay QR + card + บัญชีไทยทุกแบงก์

### C. LINE Messaging API (ส่งรูปทาง LINE)

1. สมัคร LINE Official Account (ฟรี: 200 push/month — เพียงพอเริ่มต้น)
2. ที่ `/admin/settings/line`:
   - Channel Access Token (จาก LINE Developers Console)
   - Channel Secret
   - กดปุ่ม Test Push ทดสอบ
3. ใน LINE Developers → Messaging API → Webhook URL:
   `https://photogallery.co.th/api/webhooks/line`
4. Enable webhook + Use webhook + Disable greeting message
5. (option) เปิด Auto-reply เพื่อให้ระบบตอบ user ที่ทักหา OA ได้

### D. Google OAuth (ลูกค้า + ช่างภาพ login ด้วย Google)

1. https://console.cloud.google.com → New Project
2. APIs & Services → Credentials → Create OAuth Client ID
3. **Application type**: Web application
4. **Authorized redirect URIs**: `https://photogallery.co.th/auth/google/callback`
5. เก็บ Client ID + Client Secret
6. ที่ `/admin/settings/general` (หรือ /admin/settings/google) ใส่ credentials

### E. AWS Rekognition (AI Face Search) — **ถ้าต้องการ**

1. AWS IAM → Create User → attach `AmazonRekognitionFullAccess`
2. Create Access Key
3. ตั้ง env:
   ```env
   AWS_REKOGNITION_KEY=...
   AWS_REKOGNITION_SECRET=...
   AWS_DEFAULT_REGION=ap-southeast-1
   ```
4. ที่ `/admin/features` → เปิด toggle `face_search`
5. ทดสอบโดยอัปโหลดรูปงาน → ระบบจะ index ให้

> 💡 **Skip AI Face Search ก่อนได้** ถ้าต้องการลองระบบเฉย ๆ — feature flag ปิด default

---

## 13. ขั้นตอนที่ 12: เปิดใช้งานจริง

### Checklist ก่อนเปิดให้ลูกค้าใช้

- [ ] APP_DEBUG=false (ห้ามลืม)
- [ ] APP_KEY ตั้งแล้ว
- [ ] HTTPS ทำงาน + SESSION_SECURE_COOKIE=true
- [ ] Database migrate สำเร็จ
- [ ] Seeders run แล้ว (DefaultAccountsSeeder + AppSettingsSeeder + DefaultAlertRulesSeeder อย่างน้อย)
- [ ] Storage R2/S3 ทำงาน — ทดสอบโดย upload avatar ที่ `/profile`
- [ ] Mail ส่งได้ — ทดสอบจาก tinker
- [ ] Queue worker active (ดู `/admin/diagnostics`)
- [ ] Scheduler active
- [ ] อย่างน้อย 1 payment gateway ทำงาน
- [ ] เปลี่ยน admin password จาก `password123`
- [ ] ลบ test photographers/events (ถ้าไม่ต้องการ)
- [ ] ตั้ง custom domain + SSL ทำงาน

### ลบ test data

```bash
# ใน Laravel Cloud Console
php artisan tinker
> DB::table('event_events')->whereIn('slug', ['wedding-ton-anne-the-athenee-test', 'graduation-cmu-2569-morning-test', 'phuket-marathon-2026-test'])->delete();
> DB::table('auth_users')->where('email', 'like', '%@test.local')->delete();
> DB::table('photographer_profiles')->where('photographer_code', 'like', 'PH-T0%')->delete();
```

### เปิดใช้งานจริง

1. ตั้ง maintenance mode (option):
   ```bash
   php artisan up
   ```
2. แชร์ URL `https://photogallery.co.th` ให้ลูกค้า
3. ดูที่ `/admin` — เริ่มมีออเดอร์เข้า

---

## 14. การ deploy ครั้งต่อๆ ไป

หลังจาก setup ครั้งแรกแล้ว — deploy ใหม่ง่ายมาก

### Workflow ปกติ

```bash
# ใน machine ของคุณ
cd C:\xampp\htdocs\photo-gallery-pgsql

# แก้ code ที่ต้องการ
# ...

# Test ในเครื่อง
php artisan test

# Commit + push
git add .
git commit -m "feat: เพิ่มฟีเจอร์ XYZ"
git push

# Laravel Cloud จะ auto-deploy
```

### Deploy hooks (เพิ่ม custom commands)

ถ้าต้องการรัน command เพิ่มหลัง migrate:

Application → tab **Settings** → **Deploy Script**:

```bash
php artisan migrate --force
php artisan db:seed --class=AppSettingsSeeder
php artisan cache:clear
php artisan route:cache
php artisan view:cache
php artisan config:cache
php artisan event:cache
```

> ⚠️ `php artisan config:cache` หลัง deploy = boot เร็วขึ้น ~30%

### Rollback ถ้า deploy ล้มเหลว

Laravel Cloud → tab **Deployments** → หา deployment ก่อนหน้า → กด **Rollback**

โค้ด + DB migration จะ rollback (ถ้า migration มี `down()` method)

---

## 15. Troubleshooting

### Error: "SQLSTATE[08006]: could not connect to server"

**สาเหตุ:** Database ยังไม่ provision หรือ env vars ไม่ตรง

**แก้:**
1. ดู tab **Resources** → DB status = `running` ใช่ไหม
2. ตรวจ env: `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
3. ถ้าใช้ external DB → ตรวจ firewall whitelist Laravel Cloud IPs

### Error: "Disk quota exceeded" / R2 upload fail

**สาเหตุ:** R2 credentials ผิด หรือ bucket ไม่มี

**แก้:**
1. Test ใน Console:
   ```bash
   php artisan tinker
   > Storage::disk('r2')->put('test.txt', 'hello');
   > // ถ้า error → ดู error message
   ```
2. ตรวจ `AWS_ENDPOINT` URL — ต้องเป็น `https://YOUR-ACCOUNT-ID.r2.cloudflarestorage.com`
3. ตรวจ R2 token permission — ต้องมี Object Read & Write

### Error: "419 Page Expired" หลัง login

**สาเหตุ:** Session ทำงานข้าม domain ผิด หรือ HTTPS ไม่บังคับ

**แก้:**
```env
APP_URL=https://photogallery.co.th
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=.photogallery.co.th   # เริ่มด้วย . จะรองรับ subdomain
```

### Error: Image upload สำเร็จแต่ดูไม่ได้ (broken image)

**สาเหตุ:** `AWS_URL` ไม่ถูก หรือ R2 bucket ยังไม่ public

**แก้:**
1. R2 Dashboard → bucket → Settings → **Public Access** → Allow
2. Copy public URL: `https://pub-xxxxx.r2.dev`
3. ตั้ง env: `AWS_URL=https://pub-xxxxx.r2.dev`
4. Clear cache: `php artisan cache:clear`

### Queue worker ไม่ process job

**สาเหตุ:** Worker ไม่ start หรือ connection ผิด

**แก้:**
1. Application → Workers → ดู status (`Running`?)
2. Logs → กรอง `[queue]` → เห็น `Processing job`?
3. ตรวจ env `QUEUE_CONNECTION` ตรงกับ worker config
4. Restart worker (เปลี่ยน setting อะไรก็ได้แล้ว save)

### Scheduler ไม่รัน

**สาเหตุ:** ปิด toggle หรือ env ผิด

**แก้:**
1. Application → Scheduler → toggle = ON?
2. Console → `php artisan schedule:list` → เห็น job ทั้งหมด?
3. Console → `php artisan schedule:run` → รัน manually ดู error

### LINE webhook ไม่ทำงาน

**สาเหตุ:** Signature mismatch หรือ URL ผิด

**แก้:**
1. ตรวจ `line_channel_secret` ใน `/admin/settings/line` ตรงกับ LINE Developers
2. ใน LINE Developers → Webhook URL ต้องเป็น **HTTPS**
3. ดู `payment_audit_log` table → action='line_webhook_failed' → ดู error
4. ทดสอบโดย LINE Developers → Webhook → "Verify"

### Site ช้า / Time out

**แก้ตามลำดับ:**
1. **Cache config**: `php artisan config:cache && php artisan route:cache && php artisan view:cache`
2. **Upgrade plan**: Hobby → Production (ถ้ายังอยู่ Hobby)
3. **Add Redis** ถ้ายังใช้ database driver
4. **Add read replica** สำหรับ Postgres
5. **CDN** — เพิ่ม Cloudflare proxy (orange cloud)
6. **Profile**: Sentry → Performance → ดู slow query

---

## ภาคผนวก: เปรียบเทียบกับ deploy แบบ VPS

| สิ่งที่ต้องทำ | Laravel Cloud | VPS (DigitalOcean/Linode) |
|---|---|---|
| Setup server | ✅ คลิกเดียว | ❌ install nginx/php/postgres เอง |
| SSL certificate | ✅ auto | ❌ ตั้ง certbot เอง |
| Auto-scaling | ✅ มี | ❌ ไม่มี |
| Zero-downtime deploy | ✅ auto | ❌ ทำเอง (rolling deploy script) |
| Queue worker | ✅ UI toggle | ❌ ตั้ง supervisor เอง |
| Scheduler | ✅ UI toggle | ❌ ตั้ง crontab เอง |
| Logs | ✅ realtime stream | ❌ ssh เข้าไป tail logs |
| ราคาเริ่มต้น | $0 (Hobby) - $20/mo (Prod) | $5-10/mo |
| Maintenance | ✅ ไม่ต้อง | ❌ patch security เอง |

**Laravel Cloud คุ้มถ้า:**
- ไม่อยากจัดการ server เอง
- ต้องการ scale ได้เร็ว
- ทีมเล็ก ๆ (1-3 คน) ไม่มี DevOps

**VPS คุ้มถ้า:**
- มีคนทำ DevOps ในทีม
- ต้องการประหยัด max
- ต้องการ control เต็มรูปแบบ

---

## สิ่งที่ทำต่อหลัง deploy

1. **อ่าน `MANUAL_2026.md`** — คู่มือใช้งานทุก feature
2. **เปลี่ยน admin password** — `/admin/admins`
3. **ตั้ง alert rules** — `/admin/alerts/rules` (มี 16 rules default แล้ว แก้ threshold ตามต้องการ)
4. **เปิด feature ที่ต้องการ** — `/admin/features` (default chat = OFF, credits = OFF)
5. **ใส่ branding** — site logo, favicon ที่ `/admin/settings/general`
6. **ตั้ง legal pages** — privacy/terms/refund ที่ `/admin/legal-pages`
7. **เชิญทีมงาน** — `/admin/admins` (สร้าง admin เพิ่ม)
8. **Monitor 7 วันแรก** — ดู `/admin/diagnostics` ทุกวัน เพื่อจับปัญหาก่อนลูกค้าเจอ

---

**เวอร์ชันคู่มือ:** 2026.04.29
**ระบบที่อิงตาม:** Laravel 12 + PHP 8.2 + PostgreSQL 16 + Vite 7 + Tailwind 4
**ขอบเขต:** ระบบที่มีอยู่จริงในโค้ดเบสปัจจุบันเท่านั้น (verified จาก composer.json, routes/, database/, config/)

หากเจอปัญหาที่คู่มือไม่ครอบคลุม:
1. ดู logs ใน Laravel Cloud → tab Logs
2. ดู error ใน Sentry (ถ้าเปิด)
3. ดู `storage/logs/laravel.log` ผ่าน Console
4. ตรวจ `/admin/diagnostics` ในแอป
