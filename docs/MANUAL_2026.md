# คู่มือระบบ Photo Gallery — ฉบับสมบูรณ์ 2026

> เอกสารนี้เขียนขึ้นใหม่ทั้งหมด อ้างอิงเฉพาะระบบที่มีอยู่จริงในโค้ดเบสปัจจุบัน
> หากต้องการคู่มือ deploy บน Laravel Cloud โดยตรง ดูที่ `DEPLOY_LARAVEL_CLOUD.md`

---

## สารบัญ

1. [ภาพรวมระบบ](#1-ภาพรวมระบบ)
2. [Tech Stack](#2-tech-stack)
3. [โครงสร้างผู้ใช้งาน](#3-โครงสร้างผู้ใช้งาน)
4. [คู่มือสำหรับลูกค้า (Customer)](#4-คู่มือสำหรับลูกค้า-customer)
5. [คู่มือสำหรับช่างภาพ (Photographer)](#5-คู่มือสำหรับช่างภาพ-photographer)
6. [คู่มือสำหรับแอดมิน (Admin)](#6-คู่มือสำหรับแอดมิน-admin)
7. [การตั้งค่า Integration ภายนอก](#7-การตั้งค่า-integration-ภายนอก)
8. [งานอัตโนมัติ (Cron)](#8-งานอัตโนมัติ-cron)
9. [การดูแลรักษาระบบ](#9-การดูแลรักษาระบบ)
10. [คำถามที่พบบ่อย](#10-คำถามที่พบบ่อย)

---

## 1. ภาพรวมระบบ

ระบบนี้เป็น **Marketplace ขายรูปอีเวนต์ออนไลน์** สำหรับตลาดประเทศไทย โดยเชื่อมระหว่าง:

- **ช่างภาพมืออาชีพ** ที่ถ่ายงาน (วิ่ง, รับปริญญา, แต่งงาน, คอนเสิร์ต, อีเวนต์บริษัท)
- **ลูกค้า** ที่ต้องการค้นหาและซื้อรูปของตัวเองในงาน

### จุดเด่นที่มีในระบบจริง

- **AI Face Search** — ลูกค้าอัปโหลด selfie ค้นหารูปตัวเองในงานด้วย AWS Rekognition
- **PromptPay + SlipOK** — ชำระเงินด้วย QR PromptPay พร้อมยืนยันสลิปอัตโนมัติ
- **ส่งรูปทาง LINE** — ลูกค้าจ่ายเงินเสร็จ ระบบส่งลิงก์ดาวน์โหลดเข้า LINE ทันที
- **Google Drive Import** — ช่างภาพอัปโหลดเข้า Drive แล้ว import เข้าระบบครั้งเดียวจบ
- **Subscription Plans** — ช่างภาพสมัครรายเดือน/รายปี (มีหลาย tier: Free, Starter, Pro, Business, Studio)
- **Add-on Store** — ซื้อ storage เพิ่ม, AI credits, โปรโมท, branding (DB-managed)
- **ประกาศ + ข่าวสาร** — แอดมินโพสต์ข่าวให้ช่างภาพ/ลูกค้าเห็น
- **ระบบแชท** — ลูกค้าคุยกับช่างภาพ (เปิด-ปิดได้)
- **ค่าคอมมิชชั่น Tier** — ค่าคอมเปลี่ยนตาม lifetime revenue ของช่างภาพ
- **Payout อัตโนมัติ** — โอนเงินเข้าบัญชี PromptPay ของช่างภาพตามรอบที่กำหนด
- **Admin Dashboard** — จัดการทุกอย่างใน 24+ section (orders, users, photographers, commission, monetization, etc.)

---

## 2. Tech Stack

| ส่วน | ของที่ใช้ | เวอร์ชัน |
|---|---|---|
| Backend | Laravel | 12.x |
| Language | PHP | 8.2+ |
| Database | PostgreSQL | 16 (พัฒนาบน 16-Alpine) |
| Cache / Queue | Redis (production) / file (dev) | 7.x |
| Frontend | Vite + Tailwind CSS + Alpine.js | Vite 7, Tailwind 4 |
| Storage | Cloudflare R2 (S3-compatible) หรือ AWS S3 | - |
| Mail | SMTP / SES / Postmark / Resend | - |
| Error tracking | Sentry | 4.25 |

### Library สำคัญ
- `aws/aws-sdk-php` — Rekognition + S3
- `stripe/stripe-php` — Stripe gateway + Stripe Connect
- `laravel/socialite` — OAuth (Google, LINE, Facebook)
- `barryvdh/laravel-dompdf` — สร้างใบเสร็จ PDF
- `league/flysystem-aws-s3-v3` — File storage
- `sentry/sentry-laravel` — Error monitoring

---

## 3. โครงสร้างผู้ใช้งาน

ระบบมี 3 user types แยกตาราง

| ตาราง | ใช้สำหรับ | URL เข้าระบบ |
|---|---|---|
| `auth_users` | ลูกค้าทั่วไป + ช่างภาพ (id เดียวกัน, photographer = มี photographer_profile) | `/auth/login`, `/photographer/login` |
| `auth_admins` | แอดมิน (แยกจาก auth_users) | `/admin/login` |
| `auth_social_logins` | OAuth provider links (Google/LINE/Facebook) | - |

### บัญชีทดสอบ default (จาก `DefaultAccountsSeeder`)

```
แอดมิน:    admin@photogallery.com / password123
ลูกค้า:    user@photogallery.com / password123
```

### บัญชีช่างภาพทดสอบ (จาก `TestPhotographersSeeder`)

ทั้ง 6 บัญชี password คือ `password123` พร้อม Google + LINE linked + PromptPay verified ไว้ให้แล้ว

| Email | ชื่อร้าน | Tier | จังหวัด |
|---|---|---|---|
| wedding-bkk@test.local | Mali Wedding Studio | Pro | กรุงเทพ |
| graduation-cmu@test.local | Nop Graduation CMU | Seller | เชียงใหม่ |
| running-phuket@test.local | Jey Phuket Run | Creator | ภูเก็ต |
| concert-bkk@test.local | Tew Concert House | Pro | กรุงเทพ |
| corporate-bkk@test.local | Oo Corporate Events | Seller | กรุงเทพ |
| prewedding-huahin@test.local | Ann Hua Hin Studio | Seller | ประจวบฯ |

---

## 4. คู่มือสำหรับลูกค้า (Customer)

### หน้า public ที่ลูกค้าเข้าได้ (ไม่ต้อง login)

| URL | หน้าที่ |
|---|---|
| `/` | หน้าแรก — แสดงอีเวนต์เด่น, ช่างภาพ, หมวดหมู่ |
| `/events` | ค้นหาอีเวนต์ทั้งหมด — filter ด้วย หมวด, จังหวัด, ราคา |
| `/events/{slug}` | หน้าแกลเลอรี่อีเวนต์ + ปุ่มซื้อรูป |
| `/photographers` | รายชื่อช่างภาพทั้งหมด — boost-aware ranking |
| `/photographers/p/{slug}` | หน้า landing ช่างภาพ — bio, ผลงาน, รีวิว, จองคิว |
| `/announcements` | ประกาศ/ข่าวสารสำหรับลูกค้า |
| `/blog`, `/blog/{slug}` | บทความ |
| `/products`, `/products/{slug}` | สินค้าดิจิทัล |
| `/promo`, `/why-us` | หน้า marketing |
| `/help`, `/contact` | ช่วยเหลือ + ติดต่อ |
| `/privacy-policy`, `/terms-of-service`, `/refund-policy` | เอกสารกฎหมาย |

### Flow การซื้อรูป

1. **เข้าหน้าอีเวนต์** `/events/{slug}` — ดูภาพ thumbnail (ลายน้ำ)
2. **AI Face Search** (ถ้าเปิดในงานนี้) — อัปโหลด selfie 1 ใบ → ระบบ match รูปที่มีหน้าตัวเอง
3. **เลือกภาพใส่ตะกร้า** — กดที่ภาพที่ต้องการ → "ใส่ตะกร้า"
4. **Checkout** — กรอก email + ตรวจราคา → สร้าง order
5. **เลือกวิธีจ่าย** — ระบบรองรับ:
   - PromptPay QR (อัปโหลดสลิป → SlipOK ตรวจอัตโนมัติ)
   - บัตรเครดิต ผ่าน Stripe
   - Omise (PromptPay/Card/บัญชีธนาคาร)
   - PayPal, 2C2P, TrueMoney, LINE Pay
6. **รับรูปทาง LINE** — ระบบส่ง flex message พร้อมลิงก์ดาวน์โหลดเข้า LINE OA ทันทีหลังจ่ายเสร็จ (ถ้าผูก LINE ไว้)
7. **Email + หน้า Order** — ลิงก์ดาวน์โหลด full-resolution (TTL 30 วัน)

### หน้าหลัง login (ลูกค้า)

หลัง login ที่ `/auth/login` จะมี:

- **`/orders`** — ประวัติคำสั่งซื้อ + ลิงก์ดาวน์โหลดอีก
- **`/wishlist`** — รายการที่บันทึกไว้
- **`/profile`** — แก้ไขข้อมูลส่วนตัว, password
- **`/storage`** — Cloud storage ของลูกค้า (ถ้าเปิด `user_storage_enabled`)
- **`/chat`** — แชทกับช่างภาพ (ถ้าเปิด `feature_chat_enabled`)

---

## 5. คู่มือสำหรับช่างภาพ (Photographer)

### การสมัครเป็นช่างภาพ

1. ลูกค้า login ปกติที่ `/auth/login`
2. เข้า `/become-photographer/quick` — กรอกข้อมูลเบื้องต้น (slug, bio, specialty)
3. เชื่อม **Google หรือ LINE** ที่ `/photographer/connect-google` (บังคับ — middleware `RequireGoogleLinked`)
4. ใส่ข้อมูลรับเงิน — `bank_account_*`, `promptpay_number` ที่ `/photographer/profile/setup-bank`
5. รอแอดมิน approve (สถานะ → `approved`)

### ระบบ Tier ของช่างภาพ

ระบบจะ auto-sync tier ตามข้อมูลที่ช่างภาพกรอก (ไม่ต้องให้แอดมินตั้งเอง):

| Tier | เงื่อนไข | สิทธิ์ |
|---|---|---|
| **Creator** | ลงชื่อเฉย ๆ | อัปโหลด, สร้างอีเวนต์เป็น draft |
| **Seller** | + ใส่ PromptPay + bank_account_name | publish + ขายได้ |
| **Pro** | + verify ID + ผ่านขั้นตอนแอดมิน | ไม่มีลิมิต + commission rate ที่ดีกว่า |

### Dashboard `/photographer/`

| Section | URL | หน้าที่ |
|---|---|---|
| Dashboard | `/photographer/` | สรุปยอดขาย, ออเดอร์ล่าสุด |
| **Events** | `/photographer/events` | สร้าง/แก้ไขอีเวนต์, QR code, status toggle |
| **Photos** | `/photographer/events/{event}/photos` | อัปโหลดรูป, จัด cover, bulk delete |
| **Bookings** | `/photographer/bookings` | confirm/cancel งานที่จอง, calendar feed |
| **Availability** | `/photographer/availability` | ตั้งเวลาว่าง |
| **Earnings** | `/photographer/earnings` | ยอดขาย, payout history |
| **Analytics** | `/photographer/analytics` | สถิติอีเวนต์, view, conversion |
| **Subscription** | `/photographer/subscription` | สมัคร/เปลี่ยนแผน, ใบเสร็จ |
| **Store** | `/photographer/store` | ซื้อ Boost, Storage, AI Credits, Branding |
| **Store Status** | `/photographer/store/status` | ดูแผน + add-on + การใช้งานทั้งหมด |
| **Reviews** | `/photographer/reviews` | ดูรีวิวลูกค้า + ตอบกลับ |
| **AI Tools** | `/photographer/ai-tools` | Auto-tag, Best Shot, Color Enhance |
| **Presets** | `/photographer/presets` | ลายน้ำ + branding |
| **Profile** | `/photographer/profile` | bio, bank, PromptPay, Stripe Connect |
| **Announcements** | `/photographer/announcements` | ประกาศจากแอดมินที่ส่งให้ช่างภาพ |
| **Chat** | `/photographer/chat` | คุยกับลูกค้า (ถ้าเปิด feature) |

### Flow ตั้งงานขายรูป

1. **`/photographer/events/create`** — กรอก ชื่องาน, หมวด, จังหวัด, วันที่ถ่าย, ราคาต่อภาพ, visibility (public/password/private)
2. **อัปโหลดรูป** — มี 2 ทาง:
   - อัปโหลดตรงผ่านหน้าเว็บ (multipart batch)
   - import จาก Google Drive folder (paste link)
3. **AI Processing** — ถ้าเปิด AI Tools ระบบจะ:
   - Generate watermark + thumbnail
   - คัดรูปเสีย (Quality Filter)
   - หาใบหน้าและ index ไปที่ AWS Rekognition
   - Auto-tag (objects, scenes)
   - Best Shot (กลุ่มภาพคล้าย → เลือกตัวเด่น)
4. **เปิดขาย** — เปลี่ยน status เป็น `active` + visibility เป็น `public`
5. **แชร์ URL** — `/events/{slug}` หรือ QR code

### การรับเงิน (Payout)

- ลูกค้าจ่าย → ระบบหัก commission (เริ่ม 20%, ลดลงตาม tier) → บันทึก `photographer_payouts` row
- เมื่อถึงรอบ payout (ตั้งใน Admin → Payout Settings) → ระบบรวมยอด → โอนเข้า PromptPay/Bank ของช่างภาพ
- ส่ง notification ทั้ง in-app + LINE + email

---

## 6. คู่มือสำหรับแอดมิน (Admin)

เข้าระบบที่ `/admin/login` ด้วย `auth_admins` account

### โครงสร้าง Admin Dashboard

| หมวด | URL | งาน |
|---|---|---|
| **Dashboard** | `/admin` | KPIs, online users, recent orders |
| **Orders** | `/admin/orders` | รายการคำสั่งซื้อ + manage status |
| **Users** | `/admin/users` | จัดการลูกค้า + reset password |
| **Photographers** | `/admin/photographers` | approve/suspend/reactivate ช่างภาพ + ตั้ง commission rate |
| **Commission** | `/admin/commission` | ตั้งค่าคอม + tier rules |
| **Events** | `/admin/events` | จัดการอีเวนต์ทั้งหมด + QR code |
| **Categories** | `/admin/categories` | หมวดอีเวนต์ |
| **Subscriptions** | `/admin/subscriptions` | จัดการแผน photographer subscription |
| **Pricing** | `/admin/pricing` | จัดการราคา subscription plans |
| **Monetization** | `/admin/monetization` | Brand Campaigns, Photographer Promotions, Addon Catalog |
| **Coupons** | `/admin/coupons` | สร้าง/จัดการ coupon code |
| **Notifications** | `/admin/notifications` | broadcast ไปลูกค้า/ช่างภาพ |
| **Announcements** | `/admin/announcements` | ประกาศข่าวสาร (target ลูกค้า/ช่างภาพ/ทั้งหมด) |
| **Blog** | `/admin/blog/posts` | บทความ + AI generation tools |
| **Analytics** | `/admin/analytics/capacity`, `/admin/analytics/trend` | ระบบ usage + trend |
| **Storage** | `/admin/storage` | ดูยอดใช้พื้นที่รวม |
| **Settings** | `/admin/settings/*` | site name, mail, payment gateways, LINE, etc. |
| **Features** | `/admin/features` | เปิด/ปิด features (chat, face_search, AI tools, etc.) |
| **Alert Rules** | `/admin/alerts/rules` | กฎเตือนเมื่อ metric เกิน threshold |
| **Moderation** | `/admin/moderation` | จัดการรูปที่ flag + spam |
| **Payout Settings** | `/admin/payout-settings` | กฎการจ่ายเงินช่างภาพ (schedule + threshold) |
| **Legal Pages** | `/admin/legal-pages` | privacy/terms/refund custom pages |
| **SEO** | `/admin/seo/management`, `/admin/seo/analyzer` | per-page SEO + audit |
| **Security** | `/admin/security/scan` | 14-point security check |
| **Diagnostics** | `/admin/diagnostics` | system health, scheduler, queue |
| **Activity Log** | `/admin/activity-log` | audit trail |
| **Admins** | `/admin/admins` | จัดการ admin users (superadmin only) |
| **Deployment** | `/admin/deployment` | install wizard, env test |

### หน้า Settings สำคัญ

#### `/admin/settings/payment-gateways`
ตั้งค่า API key ทุก gateway:
- **SlipOK**: `slipok_api_key`, `slipok_branch_id`, `slipok_webhook_secret`, toggle `slipok_enabled`
- **Omise**: public/secret keys + `omise_webhook_secret`
- **Stripe**: `stripe_key`, `stripe_secret`, `stripe_webhook_secret`
- **PayPal**, **2C2P**, **TrueMoney**, **LINE Pay** — แต่ละตัวมี secrets แยก

#### `/admin/settings/line`
- `line_channel_access_token`
- `line_channel_secret`
- ตั้ง webhook URL
- ปุ่ม test push

#### `/admin/features`
toggle on/off ทีละตัว:
- AI: face_search, quality_filter, auto_tagging, best_shot
- Workflow: priority_upload, customer_analytics, presets
- Branding: custom_branding, white_label
- Platform: chat, team_seats (deprecated), api_access (deprecated)

#### `/admin/alerts/rules`
มี 16 default rules ที่ seed ไว้ ครอบคลุม:
- **Infrastructure**: disk 80%/92%, CPU 90%, RAM 85%, DB connections 80%
- **Money**: pending_slips > 20, stuck_slips > 12 ชม., pending_payouts > 30, failed_disbursements_24h > 5
- **Customer trust**: line_failed_deliveries_24h > 30, admin_email_failures_24h > 20
- **Abuse**: flagged_photos > 30, new_users_24h > 500
- **Capacity**: capacity_util_pct >= 85

---

## 7. การตั้งค่า Integration ภายนอก

ทุกอย่างจัดการที่ `/admin/settings` (เก็บใน DB ผ่าน `app_settings` ตาราง — ไม่ต้องแก้ .env)

### LINE Messaging API

**ที่ต้องเตรียม:**
1. สมัคร LINE Official Account ที่ https://www.linebiz.com/th/
2. เข้า LINE Developers Console → สร้าง Messaging API channel
3. เก็บ `Channel Access Token (long-lived)` + `Channel Secret`

**ตั้งค่าในระบบ:**
1. เข้า `/admin/settings/line`
2. กรอก `line_channel_access_token` + `line_channel_secret`
3. เปิด toggle `line_messaging_enabled`
4. ใน LINE Developers Console → Webhook URL: `https://yourdomain.com/api/webhooks/line`
5. กดปุ่ม "Test Push" ในระบบ → ทดสอบส่งข้อความถึง admin

**ฟีเจอร์ที่เปิดใช้งานได้:**
- ส่งรูปให้ลูกค้าทาง LINE หลังจ่ายเงิน
- คุยกับลูกค้าผ่าน LINE OA (chat reply)
- ส่งแจ้งเตือน payout, order, refund
- รับ webhook events (เมื่อลูกค้าทักหา OA)

### Google OAuth + Drive + Calendar

**ที่ต้องเตรียม:**
1. ไปที่ Google Cloud Console → Create project
2. Enable APIs: Drive API, Calendar API, People API
3. สร้าง OAuth Client ID (Web app) — เพิ่ม redirect URI: `https://yourdomain.com/auth/google/callback`
4. เก็บ Client ID + Client Secret

**ตั้งค่าในระบบ:**
1. `/admin/settings/google` (หรือ `/admin/settings/general`)
2. กรอก `google_client_id` + `google_client_secret`
3. เปิด `google_oauth_enabled`

**ฟีเจอร์ที่เปิดใช้งาน:**
- ลูกค้า/ช่างภาพ login ด้วย Google
- ช่างภาพ import รูปจาก Google Drive folder
- Sync booking ↔ Google Calendar 2 ทาง

### SlipOK (PromptPay slip auto-verify)

**ที่ต้องเตรียม:**
1. สมัคร SlipOK ที่ https://slipok.com (ใช้ LINE OA สมัครได้)
2. เก็บ API Key + Branch ID

**ตั้งค่าในระบบ:**
1. `/admin/settings/payment-gateways` → SlipOK section
2. กรอก `slipok_api_key` + `slipok_branch_id`
3. (option) ตั้ง `slipok_webhook_secret` สำหรับ HMAC verify webhook
4. เปิด `slipok_enabled`
5. ตั้งค่า slip verification mode:
   - `manual` — แอดมินตรวจเอง
   - `auto` — auto-approve เมื่อ score >= threshold (default 80)
6. ใน SlipOK dashboard → set webhook URL: `https://yourdomain.com/api/webhooks/slipok`

### AWS Rekognition (Face Search)

**ที่ต้องเตรียม:**
1. AWS account → IAM user with `AmazonRekognitionFullAccess`
2. สร้าง access key/secret pair
3. เลือก region (แนะนำ `ap-southeast-1` Singapore)

**ตั้งค่าในระบบ:**
1. `/admin/settings/ai` หรือ `/admin/settings/general`
2. กรอก AWS credentials
3. เปิด feature `face_search` ที่ `/admin/features`

### AWS S3 / Cloudflare R2 (Storage)

**ตั้งใน `.env` (ไม่ใช่ใน admin UI):**
```env
FILESYSTEM_DISK=r2  # หรือ s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=auto  # R2 ใช้ 'auto', S3 ใช้ region จริง
AWS_BUCKET=your-bucket-name
AWS_URL=https://your-bucket.r2.dev  # หรือ S3 URL
AWS_ENDPOINT=https://YOUR-ACCOUNT.r2.cloudflarestorage.com  # R2 only
AWS_USE_PATH_STYLE_ENDPOINT=false
```

### Payment Gateways อื่น ๆ

ทุก gateway ตั้งใน `/admin/settings/payment-gateways`:
- **Omise**: public + secret key, webhook secret
- **Stripe**: API key + webhook signing secret + (option) Stripe Connect
- **PayPal**: client ID + secret + webhook ID
- **2C2P**: merchant ID + secret key + JWT secret
- **TrueMoney**: app ID + signature key
- **LINE Pay**: channel ID + channel secret

ระบบ webhook ของทุก gateway:
- `https://yourdomain.com/api/webhooks/stripe`
- `https://yourdomain.com/api/webhooks/omise`
- `https://yourdomain.com/api/webhooks/paypal`
- `https://yourdomain.com/api/webhooks/2c2p`
- `https://yourdomain.com/api/webhooks/truemoney`
- `https://yourdomain.com/api/webhooks/linepay`
- `https://yourdomain.com/api/webhooks/slipok`

### Sentry (Error Tracking)

ตั้งใน `.env`:
```env
SENTRY_LARAVEL_DSN=https://xxx@sentry.io/yyy
SENTRY_ENVIRONMENT=production
SENTRY_TRACES_SAMPLE_RATE=0.1
```

---

## 8. งานอัตโนมัติ (Cron)

ระบบมีงานอัตโนมัติ 30+ ตัว ใช้ Laravel Scheduler — ต้องตั้ง cron 1 entry ที่ระดับ server:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

(บน Laravel Cloud อัตโนมัติ — ดูคู่มือ deploy)

### กลุ่มงานหลัก

| กลุ่ม | จำนวน | ตัวอย่าง |
|---|---|---|
| **บำรุงรักษา** | 5 | presence cleanup ทุก 5 นาที, audit prune ตี 3 ครึ่ง, security scan ตี 4 ครึ่ง |
| **Booking** | 3 | recurring series materializer, T-3d/T-1d/T-1h reminders, Google Calendar watch renew |
| **Analytics** | 4 | rollup ทุก minute, daily aggregation, capacity alert, spike detection |
| **Subscription** | 5 | renew-due, expire-grace, expiring reminders, monthly free credits |
| **Payout** | 1 | engine ทุกชั่วโมงนาที 5 |
| **LINE/Email** | 3 | health check, delivery sweeper, queue heartbeat |
| **Slip** | 1 | reverify sweeper ทุก 15 นาที |
| **ความปลอดภัย** | 3 | security scan, threat intel cleanup, geo-IP cache |
| **เก็บกวาด** | 5 | backup ตี 3, photo orphan, rekognition orphan, notifications cleanup, etc. |
| **อื่นๆ** | 3 | gift cards expire, abandoned carts, addons expiring |

### Queue Workers

ระบบใช้ queue 3 worker channel:
- `default` — งานทั่วไป
- `notifications` — push, mail, line
- `payouts` — โอนเงินช่างภาพ

ต้องรัน:
```bash
php artisan queue:work --queue=default,notifications,payouts --tries=3 --timeout=600
```

(บน Laravel Cloud อัตโนมัติด้วย)

---

## 9. การดูแลรักษาระบบ

### Backup

- **อัตโนมัติ**: ตี 3 ทุกวัน (`backup:database` → pg_dump เก็บ 14 วัน)
- **ที่อยู่ไฟล์**: `storage/backups/` หรือ S3 (ถ้าตั้ง)
- **Manual**: `php artisan backup:database --keep-days=14`

### Monitor

ดูได้ที่ `/admin/diagnostics`:
- **System Health**: disk, memory, DB connections
- **Queue Status**: pending jobs, failed jobs (24h)
- **Scheduler**: last run, next run
- **Sentry**: ถ้าตั้งจะเห็น error rate

### Alert Rules ที่แนะนำ (default 16 ตัวที่ seed ไว้)

ดูใน `/admin/alerts/rules` — แอดมินแก้ threshold ได้เอง

| Rule | Threshold | Severity | Channels |
|---|---|---|---|
| Disk เต็ม (warn) | >= 80% | warn | admin + email |
| Disk เต็ม (critical) | >= 92% | critical | admin + email + LINE |
| Queue คั่งค้าง | > 500 jobs | warn | admin + email |
| สลิปรอตรวจ | > 20 | warn | admin + email |
| สลิปเก่า | > 12 ชม. | critical | admin + email + LINE |
| Disbursement ล้มเหลว 24h | > 5 | critical | admin + email + LINE |
| LINE ส่งล้มเหลว 24h | > 30 | warn | admin + email |
| (อีก 9 rules) | - | - | - |

### Admin notifications

เข้าที่ `/admin/notifications` — บันทึก:
- Slip ที่ต้องตรวจ
- Photographer ใหม่ที่รออนุมัติ
- Payout ล้มเหลว
- Security alerts

---

## 10. คำถามที่พบบ่อย

### Q: ลูกค้าจ่ายเงินแล้วไม่ได้รูปทาง LINE?
- ดู `/admin/diagnostics` → LINE Health
- ตรวจ `/admin/settings/line` → channel access token หมดอายุไหม
- ดู `line_deliveries` table — status='failed' หมายถึง push ไม่สำเร็จ
- ลูกค้ายังโหลดได้จากหน้า Order แม้ LINE ส่งไม่ผ่าน

### Q: SlipOK ตรวจสลิปไม่ผ่าน?
- ตั้ง mode เป็น `manual` ใน `/admin/settings/payment-gateways`
- แอดมินเข้า `/admin/orders/{id}` → ดูสลิป → กด approve/reject เอง
- หรือลด `slip_auto_approve_threshold` จาก 80 → 70 ถ้ายังเข้มเกินไป

### Q: ช่างภาพใหม่ได้ tier อะไร?
- เริ่มที่ `creator` → อัปโหลดได้แต่ขายไม่ได้
- กรอก PromptPay + bank → auto-upgrade เป็น `seller` (ขายได้)
- Pro tier ต้องแอดมิน approve หรือ verify ID ผ่าน

### Q: ระบบ AI Face Search ใช้งานไม่ได้
- เปิด toggle ที่ `/admin/features` → face_search
- ตรวจ AWS credentials + Rekognition permission
- ดู error ที่ Sentry (ถ้าตั้ง) หรือ `storage/logs/laravel.log`

### Q: ปิดระบบ Chat ชั่วคราว
- `/admin/features` → ปิด toggle `chat`
- Route จะ 404, photographer dashboard ก็ไม่เห็น link

### Q: ปิด Upload Credits system
- `/admin/features` หรือ `/admin/settings`
- toggle `credits_system_enabled` → off
- ช่างภาพอัปโหลดได้ปกติ ไม่หัก credits

### Q: เพิ่มช่างภาพใหม่ที่ใช้ในการทดสอบ?
- รัน `php artisan db:seed --class=TestPhotographersSeeder`
- ได้ 6 บัญชีพร้อม Google + LINE + PromptPay verified

### Q: เพิ่มอีเวนต์ทดสอบ?
- รัน `php artisan db:seed --class=TestEventsSeeder`
- ได้ 3 events: wedding (Bangkok), graduation (Chiang Mai), running (Phuket)

---

## ภาคผนวก: คำสั่ง Artisan ที่ใช้บ่อย

```bash
# Seed บัญชีทดสอบ
php artisan db:seed --class=DefaultAccountsSeeder
php artisan db:seed --class=TestPhotographersSeeder
php artisan db:seed --class=TestEventsSeeder
php artisan db:seed --class=AppSettingsSeeder
php artisan db:seed --class=DefaultAlertRulesSeeder
php artisan db:seed --class=AddonItemsSeeder

# Backup ฐานข้อมูล
php artisan backup:database --keep-days=14

# ตรวจ alerts
php artisan alerts:check

# Health check
php artisan health:scan

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Queue
php artisan queue:work --queue=default,notifications,payouts
php artisan queue:failed
php artisan queue:retry all

# ดู scheduled tasks ทั้งหมด
php artisan schedule:list

# Test ส่ง email
php artisan tinker
> Mail::raw('test', fn($m) => $m->to('you@example.com')->subject('test'));
```

---

**เวอร์ชันคู่มือ:** 2026.04.29
**ปรับปรุงครั้งล่าสุด:** 2026-04-29
**ขอบเขต:** อ้างอิงเฉพาะระบบที่มีอยู่จริงในโค้ดเบส (verified จาก composer.json + routes/web.php + database/migrations + database/seeders)
