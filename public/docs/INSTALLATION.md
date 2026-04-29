# คู่มือการติดตั้งโปรเจค Photo Gallery (Laravel 12 + Tailwind v4)

คู่มือนี้ครอบคลุมตั้งแต่การติดตั้งบนเครื่อง Local (Windows/XAMPP) จนถึงการ deploy ขึ้น Production Server และเปิดใช้งานจริงบนอินเทอร์เน็ต

---

## สารบัญ

1. [ภาพรวมระบบและ Stack](#1-ภาพรวมระบบและ-stack)
2. [ความต้องการของระบบ (Requirements)](#2-ความต้องการของระบบ-requirements)
3. [ติดตั้งบน Windows + XAMPP (Local Dev)](#3-ติดตั้งบน-windows--xampp-local-dev)
4. [ตั้งค่า .env แบบละเอียด](#4-ตั้งค่า-env-แบบละเอียด)
5. [ตั้งค่า Database และ Migration](#5-ตั้งค่า-database-และ-migration)
6. [ตั้งค่า Storage, Symlink และ Permissions](#6-ตั้งค่า-storage-symlink-และ-permissions)
7. [ตั้งค่า OAuth (Google, Facebook, LINE)](#7-ตั้งค่า-oauth-google-facebook-line)
8. [ตั้งค่า Payment Gateway (Stripe, Omise, PromptPay)](#8-ตั้งค่า-payment-gateway-stripe-omise-promptpay)
9. [ตั้งค่า AWS S3 + CloudFront (CDN รูปภาพ)](#9-ตั้งค่า-aws-s3--cloudfront-cdn-รูปภาพ)
10. [ตั้งค่า Mail + LINE Notify](#10-ตั้งค่า-mail--line-notify)
11. [สร้างบัญชี Admin แรก](#11-สร้างบัญชี-admin-แรก)
12. [Build Assets (Tailwind + Vite)](#12-build-assets-tailwind--vite)
13. [ทดสอบระบบด้วย Smoke Test](#13-ทดสอบระบบด้วย-smoke-test)
14. [Deploy ขึ้น Production Server (Linux/Ubuntu)](#14-deploy-ขึ้น-production-server-linuxubuntu)
15. [ตั้งค่า Nginx + SSL (Let's Encrypt)](#15-ตั้งค่า-nginx--ssl-lets-encrypt)
16. [ตั้งค่า Queue Worker + Scheduler (Supervisor + Cron)](#16-ตั้งค่า-queue-worker--scheduler-supervisor--cron)
17. [Checklist ก่อนเปิดใช้งานจริง](#17-checklist-ก่อนเปิดใช้งานจริง)
18. [Troubleshooting](#18-troubleshooting)

---

## 1. ภาพรวมระบบและ Stack

- **Framework**: Laravel 12
- **PHP**: 8.2 ขึ้นไป
- **Frontend**: Tailwind CSS v4 + Alpine.js + Bootstrap Icons + Vite
- **Database**: MySQL 8.0 (แนะนำ) หรือ MariaDB 10.6+
- **Storage**: Local + AWS S3 (optional) + CloudFront (optional)
- **Queue/Cache/Session**: Database driver (default)
- **Mail**: SMTP / Mailgun / SES (เลือกได้)
- **OAuth**: Laravel Socialite (Google / Facebook / LINE)
- **Payment**: Stripe / Omise / PromptPay (bank-transfer + slip verification)

### โมดูลหลักของระบบ

| โมดูล | คำอธิบาย |
|------|---------|
| **Public Gallery** | เว็บหน้าบ้าน ดูรูป/อีเวนต์/ช่างภาพ |
| **Events & Photos** | จัดการอีเวนต์ + อัพโหลดรูป |
| **Photographer Panel** | ช่างภาพล็อกอินอัพโหลดรูป/ดูยอดขาย |
| **Digital Products** | สินค้าดิจิทัล (พรีเซ็ต/ฟิลเตอร์/คอร์ส) พร้อมระบบดาวน์โหลด |
| **Payments** | รับชำระผ่าน Stripe, Omise, PromptPay + SlipOK verification |
| **Admin Panel** | Dashboard, จัดการ Users/Photographers/Orders/Payments |
| **Notifications** | In-app bell (realtime polling) + LINE + Email |

---

## 2. ความต้องการของระบบ (Requirements)

### Software

- **PHP** ≥ 8.2 พร้อม extensions:
  `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd` (หรือ `imagick`), `json`,
  `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`, `exif`,
  `intl`, `fpm` (production)
- **Composer** ≥ 2.5
- **Node.js** ≥ 20.x + **npm** ≥ 10.x
- **MySQL** ≥ 8.0 หรือ **MariaDB** ≥ 10.6
- **Git**
- **XAMPP** 8.2+ (สำหรับ Windows Dev)

### Hardware (แนะนำ Production)

- CPU 2 vCPU, RAM 2 GB ขึ้นไป
- Disk 20 GB ขึ้นไป (ถ้าเก็บรูปใน local)
- ถ้ามีรูปเยอะ ใช้ S3 จะประหยัดกว่า

---

## 3. ติดตั้งบน Windows + XAMPP (Local Dev)

### 3.1 ติดตั้ง Tools

1. ติดตั้ง **XAMPP** → https://www.apachefriends.org/
2. ติดตั้ง **Composer** → https://getcomposer.org/download/
3. ติดตั้ง **Node.js LTS** → https://nodejs.org/
4. ติดตั้ง **Git** → https://git-scm.com/

### 3.2 เปิด PHP Extensions (`php.ini`)

เปิดไฟล์ `C:\xampp\php\php.ini` แล้ว uncomment (ลบ `;` หน้าบรรทัด):

```ini
extension=bcmath
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=exif
extension=zip
```

### 3.3 Clone โปรเจค

```bash
cd C:\xampp\htdocs
git clone <repo-url> photo-gallery-tailwind
cd photo-gallery-tailwind
```

### 3.4 ติดตั้ง Dependencies

```bash
composer install
npm install
```

### 3.5 สร้างไฟล์ `.env`

```bash
copy .env.example .env
php artisan key:generate
```

### 3.6 สร้าง Database

เปิด phpMyAdmin (http://localhost/phpmyadmin) แล้วสร้าง database ชื่อ `photo_gallery` (charset `utf8mb4`, collation `utf8mb4_unicode_ci`)

### 3.7 แก้ `.env` ให้ใช้ MySQL

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=photo_gallery
DB_USERNAME=root
DB_PASSWORD=
```

### 3.8 Migrate + สร้าง Admin

```bash
php artisan migrate --seed
php artisan storage:link
```

### 3.9 รัน Dev Server

```bash
composer dev
```

หรือแยกเทอร์มินัล:

```bash
php artisan serve --port=8001
npm run dev
php artisan queue:listen
```

เปิดเบราว์เซอร์ที่ http://127.0.0.1:8001

---

## 4. ตั้งค่า `.env` แบบละเอียด

### 4.1 App

```env
APP_NAME="Photo Gallery"
APP_ENV=local                 # production = "production"
APP_KEY=                      # ใช้ php artisan key:generate
APP_DEBUG=true                # production = false
APP_URL=http://127.0.0.1:8001 # production = https://yourdomain.com

APP_LOCALE=th
APP_FALLBACK_LOCALE=en
APP_TIMEZONE=Asia/Bangkok
```

### 4.2 Database

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=photo_gallery
DB_USERNAME=root
DB_PASSWORD=
```

### 4.3 Session / Cache / Queue

```env
SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public        # ใช้ s3 ถ้าต่อ S3
```

### 4.4 การเข้ารหัสรหัสผ่าน

```env
BCRYPT_ROUNDS=12              # Dev=10, Production=12
```

---

## 5. ตั้งค่า Database และ Migration

### 5.1 โครงสร้างตารางหลัก

โปรเจคนี้มี migration ~38 ไฟล์ ครอบคลุม:

- `auth_users`, `auth_admins`, `social_logins`, `user_sessions`
- `photographer_profiles`, `commission_logs`, `photographer_payouts`
- `event_events`, `event_photos`, `event_photos_cache`
- `orders`, `order_items`, `digital_orders`, `digital_download_tokens`
- `payments`, `coupons`, `coupon_usage`
- `chat_conversations`, `chat_messages`
- `admin_notifications`, `user_notifications`
- `app_settings`, `security_rate_limits`

### 5.2 คำสั่ง

```bash
# รัน migration ทั้งหมด
php artisan migrate

# Reset + re-run (ลบข้อมูลทั้งหมด!)
php artisan migrate:fresh --seed

# ดูสถานะ
php artisan migrate:status
```

### 5.3 Seed ข้อมูลตัวอย่าง

```bash
php artisan db:seed --class=AdminSeeder
php artisan db:seed --class=AppSettingsSeeder
```

---

## 6. ตั้งค่า Storage, Symlink และ Permissions

### 6.1 Symlink Storage

```bash
php artisan storage:link
```

สร้าง symlink `public/storage` → `storage/app/public` สำหรับให้รูปที่อัปโหลดเข้าถึงได้จากเว็บ

### 6.2 Permissions (Linux Production)

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 6.3 โฟลเดอร์ที่ต้องมี

```
storage/app/public/
  ├── photos/           # รูปต้นฉบับ
  ├── photos/thumbs/    # thumbnail
  ├── photos/watermark/ # preview watermark
  ├── slips/            # สลิปโอนเงิน
  ├── avatars/          # รูปโปรไฟล์
  └── digital/          # ไฟล์สินค้าดิจิทัล
```

---

## 7. ตั้งค่า OAuth (Google, Facebook, LINE, Apple)

> **🆕 สถาปัตยกรรมใหม่ (v1.1)** — ตั้งแต่เมษายน 2026 เป็นต้นมา credentials ของทุก provider ถูกย้ายออกจาก `.env` ไปจัดการผ่านหน้า Admin แทน (เก็บใน `app_settings` table แบบเข้ารหัส) เพื่อให้เปลี่ยนค่าได้ทันทีโดยไม่ต้อง redeploy
>
> - **หน้าหลัก**: `/admin/settings/social-auth` — Client ID + Secret ของทุก provider
> - **Google Drive** (ใช้ key ร่วมกับ Google OAuth): `/admin/settings/google-drive`
> - **LINE Messaging API / Rich Menu** (ใช้ channel ร่วมกับ LINE Login): `/admin/settings/line`
>
> `.env` จะเก็บไว้เฉพาะเป็น **fallback** (อ่านเมื่อ DB ยังว่าง) และเฉพาะ Facebook เท่านั้นที่ยังรองรับ fallback อัตโนมัติ

### 7.1 ภาพรวม: หน้าตั้งค่า Social Auth

1. ล็อกอิน Admin → เมนู **Settings → Social Auth** หรือเข้า `/admin/settings/social-auth`
2. ในหน้านี้จะมี 4 การ์ดสำหรับ 4 provider:
   - Google / LINE / Facebook / Apple
3. แต่ละการ์ดจะแสดง **Redirect URI** ของตัวเองพร้อมปุ่ม copy — นำไปใส่ที่ฝั่ง provider
4. กรอก Client ID + Client Secret แล้วกด **บันทึก**
5. สถานะ pill ด้านบนจะเปลี่ยนเป็น "พร้อมใช้งาน" เมื่อกรอกครบ

> **ข้อควรรู้** — รหัสลับ (Client Secret / Private Key) จะเก็บแบบเข้ารหัสใน DB ช่องกรอกจะว่างเสมอหลังบันทึก (security feature) ถ้าอยากคงค่าเดิมไว้ก็ปล่อยว่างไว้ ระบบจะไม่ทับค่าเดิม

---

### 7.2 Google OAuth + Google Drive

1. ไป https://console.cloud.google.com/
2. สร้าง Project → APIs & Services → Credentials
3. OAuth Consent Screen: ตั้งชื่อแอป, email, domain
4. Create OAuth Client ID → Web Application
5. **Authorized redirect URIs** (copy จากหน้า admin):
   - `http://127.0.0.1:8001/auth/google/callback` (dev)
   - `https://yourdomain.com/auth/google/callback` (prod)
6. Copy Client ID + Secret
7. **ใส่ที่**: `/admin/settings/social-auth` → การ์ด Google

> 💡 Google Client ID/Secret ใช้ร่วมกันระหว่าง **OAuth Login** และ **Google Drive Storage** — แก้ที่ใดที่หนึ่ง อีกหน้าจะได้ค่าเดียวกันโดยอัตโนมัติ

### 7.3 Facebook Login

1. ไป https://developers.facebook.com/
2. Create App → Type: Consumer → Add **Facebook Login**
3. Settings → Basic: ตั้ง App Domain + Privacy Policy URL
4. Facebook Login → Settings → **Valid OAuth Redirect URIs**:
   - `https://yourdomain.com/auth/facebook/callback`
5. **ใส่ที่**: `/admin/settings/social-auth` → การ์ด Facebook
   - App ID → Client ID
   - App Secret → Client Secret

> 🔁 **Fallback แบบพิเศษ** — Facebook เป็น provider เดียวที่ยังอ่าน `.env` ได้ (ใช้สำหรับ fresh install) โดยระบบจะลำดับ: DB → `FB_APP_ID` / `FB_APP_SECRET` → ถ้าไม่มีทั้งคู่ ปุ่ม Facebook login จะถูกซ่อน

### 7.4 LINE Login

1. ไป https://developers.line.biz/console/
2. Create Provider → Create Channel → เลือก **LINE Login Channel** ⚠️
   - ห้ามใช้ Messaging API Channel สำหรับ OAuth — มันจะ reject redirect
3. ไปที่แท็บ **LINE Login** → กรอก Callback URL:
   - ต้องตรงกับ URI ที่แสดงในหน้า admin แบบ **ตรงทุกตัวอักษร** (scheme + host + port + path)
4. **ใส่ที่**: `/admin/settings/social-auth` → การ์ด LINE
   - Channel ID → Client ID
   - Channel Secret → Client Secret

> 💡 LINE Channel ID/Secret ใช้ร่วมกันระหว่าง **LINE Login** (OAuth) และ **LINE Messaging API** (ส่งข้อความ/Rich Menu) — แก้ที่ `/admin/settings/line` จะอัปเดตทั้งคู่

### 7.5 Apple Sign-In (Optional)

Apple ต้องใช้ credentials 4 อย่าง:

1. ไป https://developer.apple.com/ (ต้องเป็น Apple Developer Program สมาชิก)
2. Identifiers → สร้าง **Services ID** (เช่น `com.yourdomain.auth`)
3. Configure → Sign in with Apple → เพิ่ม Return URL:
   - `https://yourdomain.com/auth/apple/callback`
4. Keys → Create Key → Enable "Sign in with Apple" → ดาวน์โหลดไฟล์ `.p8`
5. **ใส่ที่**: `/admin/settings/social-auth` → การ์ด Apple
   - **Service ID**: เช่น `com.yourdomain.auth` (Client ID)
   - **Team ID**: 10 หลักจากหน้า Membership
   - **Key ID**: 10 หลักจาก Key ที่สร้าง
   - **Private Key**: วาง content ของไฟล์ `.p8` ทั้งไฟล์ (รวม `-----BEGIN PRIVATE KEY-----`)

> ⚠️ Apple Sign-In ต้องใช้ **HTTPS เท่านั้น** และ domain ต้อง verify แล้วใน Apple Developer

---

### 7.6 ทดสอบ Local Dev

- **Google**: ใช้ `http://127.0.0.1:8001` ได้ตรง ๆ (Google อนุญาต localhost สำหรับ OAuth testing)
- **LINE**: ใช้ `http://127.0.0.1:8001` ได้ แต่ `APP_URL` ต้องตรงกับ host ที่ใช้ browse (ดู §18 Troubleshooting)
- **Facebook / Apple**: ต้องใช้ HTTPS — ใช้ `ngrok` หรือ `cloudflared tunnel` สำหรับทดสอบ local

```bash
# ตัวอย่างใช้ ngrok
ngrok http 8001
# จะได้ https://xxxx.ngrok-free.app
# แล้วตั้ง APP_URL=https://xxxx.ngrok-free.app ใน .env ชั่วคราว
# อย่าลืม php artisan config:clear หลังเปลี่ยน
```

---

## 8. ตั้งค่า Payment Gateway (Stripe, Omise, PromptPay)

### 8.1 Stripe (บัตรเครดิตต่างประเทศ)

1. สมัคร https://dashboard.stripe.com/
2. Developers → API Keys → Copy
3. Webhooks → Add endpoint: `https://yourdomain.com/webhook/stripe`
   - Events: `payment_intent.succeeded`, `payment_intent.payment_failed`
4. `.env`:

```env
STRIPE_PUBLIC_KEY=pk_live_xxx        # pk_test สำหรับทดสอบ
STRIPE_SECRET_KEY=sk_live_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
```

### 8.2 Omise (บัตรเครดิตในไทย + Internet Banking)

1. สมัคร https://dashboard.omise.co/
2. Keys → Copy Public + Secret
3. `.env`:

```env
OMISE_PUBLIC_KEY=pkey_xxx
OMISE_SECRET_KEY=skey_xxx
```

### 8.3 PromptPay (QR โอนเงิน + แนบสลิป)

```env
PROMPTPAY_NUMBER=0812345678      # เบอร์ หรือเลขประจำตัวผู้เสียภาษี
PROMPTPAY_NAME="ชื่อร้าน จำกัด"
```

### 8.4 SlipOK (ตรวจสลิปอัตโนมัติ — Optional)

ตั้งค่าในหน้า Admin → Settings:
- **Slip Verify Mode**: `manual` / `auto` / `hybrid`
- **Auto-approve Threshold**: `80` (คะแนน 0-100)
- **SlipOK API Key**: ใส่ใน Admin Settings (เก็บใน `app_settings` table)

---

## 9. ตั้งค่า AWS S3 + CloudFront (CDN รูปภาพ)

### 9.1 S3 Bucket

1. สร้าง Bucket ที่ AWS Console → Region `ap-southeast-1` (สิงคโปร์)
2. ปิด **Block all public access** (ถ้าต้องการแสดงรูปสาธารณะ)
3. Bucket Policy:

```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Principal": "*",
    "Action": "s3:GetObject",
    "Resource": "arn:aws:s3:::YOUR-BUCKET/*"
  }]
}
```

### 9.2 IAM User

1. IAM → Create User → Programmatic Access
2. Attach Policy: `AmazonS3FullAccess` (หรือ inline policy จำกัดเฉพาะ bucket)
3. Save Access Key + Secret

### 9.3 `.env`

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=AKIAxxxxxx
AWS_SECRET_ACCESS_KEY=xxxxxxxxxxxxxxxxxx
AWS_DEFAULT_REGION=ap-southeast-1
AWS_BUCKET=your-bucket-name
AWS_URL=https://your-bucket-name.s3.ap-southeast-1.amazonaws.com
AWS_USE_PATH_STYLE_ENDPOINT=false
```

### 9.4 CloudFront CDN (Optional แต่แนะนำ)

1. Create Distribution → Origin = S3 Bucket
2. Viewer Protocol: Redirect HTTP → HTTPS
3. Copy Domain name (เช่น `d1234.cloudfront.net`)

```env
AWS_CLOUDFRONT_DOMAIN=d1234.cloudfront.net
AWS_CLOUDFRONT_DISTRIBUTION_ID=E1XXXXXX
AWS_CLOUDFRONT_KEY_PAIR_ID=              # ถ้าใช้ signed URL
```

---

## 10. ตั้งค่า Mail + LINE + Photo Delivery

### 10.1 SMTP (Gmail SMTP หรือ Mailgun / SES)

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=yourname@gmail.com
MAIL_PASSWORD=xxxx-xxxx-xxxx-xxxx   # App Password (ไม่ใช่รหัส Gmail)
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@yourdomain.com
MAIL_FROM_NAME="${APP_NAME}"
```

> สร้าง App Password: https://myaccount.google.com/apppasswords (ต้องเปิด 2FA ก่อน)

### 10.2 LINE Messaging API (แจ้งเตือน + Rich Menu)

หลัง LINE Notify ปิดตัว ระบบใช้ **LINE Messaging API** แทน — ตั้งค่าที่ `/admin/settings/line`:

- **Channel Access Token** (long-lived หรือ stateless)
- **Channel Secret** (สำหรับ webhook signature)
- **Webhook URL** (ถ้าต้องการรับข้อความ/events จากผู้ใช้)

> 💡 ถ้าเปิดใช้ **LINE Login** ไว้แล้วในหน้า Social Auth ช่อง Channel ID/Secret ตรงนี้จะอ่านค่าเดียวกันโดยอัตโนมัติ (ไม่ต้องกรอกซ้ำ)

### 10.3 Photo Delivery Service — เลือกช่องทางส่งรูปให้ลูกค้า

หน้า `/admin/settings/delivery` ใช้กำหนดว่า **หลังชำระเงินสำเร็จ** ระบบจะส่งรูป/ลิงก์ดาวน์โหลดให้ลูกค้าผ่านช่องทางไหน:

| ช่องทาง | คำอธิบาย | ต้องตั้งอะไร |
|---------|----------|-----------|
| **Web** | สร้างลิงก์ดาวน์โหลด + แสดงในหน้า `/profile/downloads` | ไม่ต้อง — ทำงาน default |
| **Email** | ส่งอีเมลแนบลิงก์/ZIP | §10.1 SMTP |
| **LINE** | ส่ง Flex Message + Carousel รูปไปในแชท LINE | §10.2 + ลูกค้าเชื่อม LINE Login |

Admin สามารถเลือกได้ 1 หรือหลายช่องทางพร้อมกัน ค่า default คือ **Web + Email**

### 10.4 Queue สำหรับส่ง Notification

การส่งเมล / LINE message เป็นงาน async — ใช้ queue worker:

```bash
# Local dev
php artisan queue:listen

# Production (ผ่าน Supervisor, ดู §16)
php artisan queue:work database --tries=3
```

---

## 11. สร้างบัญชี Admin แรก

### 11.1 ผ่าน Seeder (ถ้ามี)

```bash
php artisan db:seed --class=AdminSeeder
```

Default: `admin@example.com` / `password`

### 11.2 ผ่าน Tinker (แบบ manual)

```bash
php artisan tinker
```

```php
\App\Models\Admin::create([
    'username' => 'admin',
    'email' => 'admin@yourdomain.com',
    'password_hash' => \Hash::make('รหัสที่คุณต้องการ'),
    'first_name' => 'Super',
    'last_name' => 'Admin',
    'role' => 'superadmin',
    'status' => 'active',
]);
```

> **เปลี่ยนรหัสทันทีหลังเข้า production**

---

## 12. Build Assets (Tailwind + Vite)

### 12.1 Development

```bash
npm run dev
```

### 12.2 Production Build

```bash
npm run build
```

สร้างไฟล์ `public/build/` (manifest + compiled CSS/JS)

---

## 13. ทดสอบระบบด้วย Smoke Test

โปรเจคนี้มี smoke test command สำหรับตรวจทุกระบบอัตโนมัติ:

```bash
# รันทั้งหมด 95 assertions
php artisan app:smoke-test

# รันเฉพาะกลุ่ม
php artisan app:smoke-test --group=schema
php artisan app:smoke-test --group=routes
php artisan app:smoke-test --group=digital
php artisan app:smoke-test --group=notifications

# เก็บข้อมูลทดสอบไว้ (ไม่ลบ)
php artisan app:smoke-test --keep
```

ควรขึ้น **100% pass** ก่อน deploy

---

## 14. Deploy ขึ้น Production Server (Linux/Ubuntu)

### 14.1 ติดตั้ง Software บน Server

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server git unzip curl \
    php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml \
    php8.2-curl php8.2-gd php8.2-bcmath php8.2-zip \
    php8.2-intl php8.2-exif php8.2-fileinfo

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js 20
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

### 14.2 Clone + Setup

```bash
cd /var/www
sudo git clone <repo-url> photo-gallery
sudo chown -R $USER:www-data photo-gallery
cd photo-gallery

composer install --no-dev --optimize-autoloader
cp .env.example .env
# แก้ .env ให้เป็น production
php artisan key:generate
php artisan migrate --force
php artisan storage:link

npm install
npm run build

# ตั้ง permission
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 14.3 Optimize Laravel

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

## 15. ตั้งค่า Nginx + SSL (Let's Encrypt)

### 15.1 Nginx Config: `/etc/nginx/sites-available/photo-gallery`

```nginx
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    root /var/www/photo-gallery/public;

    client_max_body_size 50M;

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }

    gzip on;
    gzip_types text/css application/javascript application/json image/svg+xml;
}
```

### 15.2 เปิดใช้งาน

```bash
sudo ln -s /etc/nginx/sites-available/photo-gallery /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 15.3 SSL ด้วย Let's Encrypt

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Auto renewal
sudo systemctl enable certbot.timer
```

---

## 16. ตั้งค่า Queue Worker + Scheduler (Supervisor + Cron)

### 16.1 Supervisor (Queue Worker)

ติดตั้ง:

```bash
sudo apt install -y supervisor
```

สร้าง `/etc/supervisor/conf.d/photo-gallery-worker.conf`:

```ini
[program:photo-gallery-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/photo-gallery/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/photo-gallery-worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start photo-gallery-worker:*
```

### 16.2 Cron (Laravel Scheduler)

```bash
sudo crontab -u www-data -e
```

เพิ่มบรรทัด:

```cron
* * * * * cd /var/www/photo-gallery && php artisan schedule:run >> /dev/null 2>&1
```

---

## 17. Checklist ก่อนเปิดใช้งานจริง

### 🔐 Security

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] `APP_KEY` ถูก generate แล้ว
- [ ] DB username/password ไม่ใช้ root
- [ ] เปลี่ยนรหัส admin default แล้ว
- [ ] ไฟร์วอลล์เปิดเฉพาะ 22, 80, 443
- [ ] `chmod 600 .env`
- [ ] SSL ใช้งานได้ (https://)
- [ ] Rate limiting เปิดในหน้า login + payment

### 💳 Payments

- [ ] Stripe/Omise ใช้ **live key** ไม่ใช่ test key
- [ ] Webhook URL ถูกต้องและรับ event ได้
- [ ] PromptPay number ตรวจถูกต้องแล้ว
- [ ] SlipOK key ใส่ใน admin settings

### 🔗 OAuth (`/admin/settings/social-auth`)

- [ ] Google Client ID/Secret บันทึกใน admin settings แล้ว (ไม่ใช่ใน .env)
- [ ] Google redirect URI ตรงกับ production domain (scheme + host แบบ exact match)
- [ ] Facebook app อยู่ใน **Live Mode** + Client ID/Secret อยู่ใน admin settings
- [ ] LINE ใช้ **LINE Login Channel** (ไม่ใช่ Messaging API Channel) สำหรับ OAuth
- [ ] LINE callback URL เป็น HTTPS + ตรงกับ `APP_URL`
- [ ] Apple (ถ้าใช้) — Service ID, Team ID, Key ID, .p8 private key ใส่ครบ

### 📧 Communication

- [ ] SMTP ส่งเมลทดสอบได้จริง
- [ ] LINE Messaging API ส่งข้อความได้ (`/admin/settings/line`)
- [ ] Photo Delivery channels ตั้งค่าแล้ว (`/admin/settings/delivery`)
- [ ] email templates แสดงผลถูกต้อง

### 🗄️ Data

- [ ] Backup DB ตั้งเวลาอัตโนมัติ (cron + mysqldump)
- [ ] Backup storage/app/public
- [ ] migrate ผ่าน 100%
- [ ] smoke test ผ่าน 100%

### ⚡ Performance

- [ ] `npm run build` แล้ว
- [ ] `config:cache`, `route:cache`, `view:cache` แล้ว
- [ ] Supervisor queue worker รันอยู่
- [ ] Cron scheduler รันอยู่
- [ ] CloudFront/CDN active (ถ้าใช้)

### 📊 Monitoring

- [ ] Laravel log rotation (`logrotate`)
- [ ] Error tracking (Sentry / Bugsnag — optional)
- [ ] Google Analytics 4 (`GA4_MEASUREMENT_ID`)
- [ ] Uptime monitoring (UptimeRobot / Better Stack)

---

## 18. Troubleshooting

### 🔴 500 Server Error หลัง deploy

```bash
tail -f storage/logs/laravel.log
# ตรวจ permission
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 🔴 CSS/JS ไม่โหลด

```bash
npm run build
php artisan view:clear
```

ตรวจว่า `APP_URL` ตรงกับโดเมนจริง

### 🔴 "SQLSTATE[42S02]: ... table doesn't exist"

```bash
php artisan migrate --force
# ถ้ายังขาดตารางเฉพาะ:
php artisan migrate:status
php artisan migrate --path=database/migrations/xxxx.php
```

### 🔴 ส่งเมลไม่ได้

- ลอง `php artisan tinker` → `Mail::raw('test', fn($m) => $m->to('you@x.com')->subject('t'));`
- Gmail: ต้องใช้ App Password ไม่ใช่รหัสจริง
- ตรวจ `MAIL_ENCRYPTION=tls` + port 587

### 🔴 Webhook Stripe ไม่ทำงาน

- ตรวจ `STRIPE_WEBHOOK_SECRET` ตรงกับ dashboard
- route `/webhook/stripe` ต้องไม่ผ่าน CSRF middleware
- ตรวจ `storage/logs/laravel.log` ดู payload ที่เข้ามา

### 🔴 Upload รูปแล้วไม่แสดง

```bash
php artisan storage:link
ls -la public/storage       # ต้องเห็น symlink
```

### 🔴 Queue ไม่ process

```bash
sudo supervisorctl status
sudo supervisorctl restart photo-gallery-worker:*
tail -f /var/log/photo-gallery-worker.log
```

### 🔴 "security_rate_limits doesn't exist"

```bash
php artisan migrate --path=database/migrations/2026_04_17_120000_create_security_rate_limits_table.php --force
```

### 🔴 Factory Reset ไม่ทำงาน / ขาดตาราง

ระบบใช้ `Schema::hasTable()` ป้องกันอยู่แล้ว ถ้ามีปัญหาลอง:

```bash
php artisan migrate:fresh --force
php artisan db:seed --class=AdminSeeder
php artisan db:seed --class=AppSettingsSeeder
```

### 🔴 LINE OAuth: "400 Bad Request — Invalid redirect_uri value"

**สาเหตุหลัก**: `APP_URL` ใน `.env` ไม่ตรงกับ host ที่ browse จริง — LINE ตรวจ redirect_uri แบบ exact string match ทุกตัวอักษร

**ตรวจ 3 จุดให้ตรงกัน**:

| จุด | ค่าที่ต้องตั้ง |
|----|-------------|
| `.env` → `APP_URL` | เช่น `http://127.0.0.1:8001` |
| Browser address bar | ต้องตรงกับ `APP_URL` (ห้ามใช้ `localhost` ถ้าตั้ง `127.0.0.1`) |
| LINE Developers Console → Callback URL | ต้องตรงกับ URI ที่แสดงในหน้า `/admin/settings/social-auth` |

**ขั้นตอนแก้ไข**:

```bash
# 1. เปิด .env แก้ APP_URL ให้ตรงกับที่ browse
#    APP_URL=http://127.0.0.1:8001   (ไม่ใช่ http://localhost:8001)

# 2. Clear config cache
php artisan config:clear
php artisan cache:clear

# 3. เข้า /admin/settings/social-auth → การ์ด LINE → copy Redirect URI

# 4. ไป https://developers.line.biz/console/
#    Channel → LINE Login → Callback URL → paste URI → Update
```

> ⚠️ **ห้ามผสม** `localhost` กับ `127.0.0.1` — สำหรับ LINE แล้วมันเป็นคนละ URI กันโดยสิ้นเชิง
>
> ⚠️ **ตรวจประเภท Channel** — ถ้าใช้ Messaging API Channel สำหรับ OAuth จะเจอ 400 เสมอ ต้องใช้ **LINE Login Channel** เท่านั้น

### 🔴 "การตั้งค่า OAuth ไม่ทำงานทั้ง ๆ ที่ใส่ .env แล้ว"

ตั้งแต่ v1.1 เป็นต้นมา credentials ของ Google/LINE/Apple ถูกย้ายจาก `.env` ไปยัง `app_settings` table แล้ว

**ต้องตั้งที่**: `/admin/settings/social-auth` (ไม่ใช่ `.env` อีกต่อไป)

มีเพียง **Facebook** เท่านั้นที่ยังอ่าน `.env` แบบ fallback ได้ (เพื่อรองรับการ migrate ทีละขั้น)

ถ้าเพิ่งอัปเกรดจาก v1.0:

```bash
# ดึงค่าจาก .env มาใส่ app_settings ได้แบบ manual ผ่าน tinker:
php artisan tinker
```

```php
\App\Models\AppSetting::set('social_auth', 'google_client_id', env('GOOGLE_CLIENT_ID'));
\App\Models\AppSetting::set('social_auth', 'google_client_secret', env('GOOGLE_CLIENT_SECRET'));
\App\Models\AppSetting::set('social_auth', 'line_channel_id', env('LINE_CHANNEL_ID'));
\App\Models\AppSetting::set('social_auth', 'line_channel_secret', env('LINE_CHANNEL_SECRET'));
```

---

## 📚 คำสั่งที่ใช้บ่อย

```bash
# Clear ทุก cache
php artisan optimize:clear

# Re-cache หลัง deploy
php artisan optimize

# ดู routes
php artisan route:list

# Tinker (REPL)
php artisan tinker

# สร้าง user ใหม่
php artisan tinker
# แล้วรัน PHP code ด้านใน

# Backup DB
mysqldump -u root -p photo_gallery > backup_$(date +%F).sql

# Restore DB
mysql -u root -p photo_gallery < backup_2026-04-17.sql
```

---

## 🎯 ลำดับความสำคัญในการ Deploy ครั้งแรก

```
1. ติดตั้ง server + PHP + MySQL + Node            ⏱️ 30 นาที
2. Clone + composer install + npm install         ⏱️ 10 นาที
3. ตั้งค่า .env + migrate + storage:link           ⏱️ 20 นาที
4. ตั้งค่า OAuth (อย่างน้อย Google)                ⏱️ 15 นาที
5. ตั้งค่า Payment (อย่างน้อย PromptPay)           ⏱️ 10 นาที
6. ตั้งค่า Mail (SMTP)                             ⏱️ 10 นาที
7. Nginx + SSL                                     ⏱️ 20 นาที
8. Supervisor + Cron                               ⏱️ 10 นาที
9. สร้าง admin + seed settings                     ⏱️ 5 นาที
10. ทดสอบด้วย smoke test                           ⏱️ 10 นาที

รวมเวลา: ~2-3 ชั่วโมง (ถ้าไม่ติดปัญหา)
```

---

**อัปเดตล่าสุด**: 2026-04-19
**เวอร์ชัน**: 1.1
**ผู้ดูแล**: ทีมพัฒนา Photo Gallery

### บันทึกการเปลี่ยนแปลง (Changelog)

**v1.1 (2026-04-19)**
- 🆕 OAuth credentials ย้ายจาก `.env` ไปจัดการที่ `/admin/settings/social-auth` (Google / LINE / Facebook / Apple)
- 🆕 เพิ่มการรองรับ **Apple Sign-In** (Service ID / Team ID / Key ID / .p8 private key)
- 🆕 เพิ่มหน้า Photo Delivery `/admin/settings/delivery` เลือกช่องทางส่งรูป (Web / Email / LINE)
- 🆕 เปลี่ยน LINE Notify → **LINE Messaging API** (LINE Notify ปิดตัวเดือน 3/2025)
- 🔁 Google Client ID/Secret: ใช้ร่วมกันระหว่าง OAuth Login และ Google Drive storage
- 🔁 LINE Channel ID/Secret: ใช้ร่วมกันระหว่าง LINE Login และ LINE Messaging API
- 📝 เพิ่ม Troubleshooting: LINE "Invalid redirect_uri" (APP_URL mismatch) + LINE Login vs Messaging API Channel

**v1.0 (2026-04-17)**
- 📘 Initial installation guide
