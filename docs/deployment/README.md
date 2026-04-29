# Deployment files

Production server config templates + automation scripts. Each file has instructions at the top.

## 📚 Documentation (HTML — open in browser)

| File | Purpose |
|------|---------|
| **`web-installer.html`** | 🆕 **Web-based installer guide** — ติดตั้งผ่าน `/admin/deployment` Wizard 4 ขั้นตอน · ไม่ต้องใช้ CLI |
| `guide.html` | คู่มือ deploy ครบชุด (CLI/manual) — Budget/aaPanel/Forge/Enterprise paths |
| `hostinger.html` | สำหรับ shared hosting (Hostinger / cPanel) |

> **เริ่มที่ไหนดี?** ใหม่กับการ deploy → `web-installer.html` ก่อน · คุ้นเคย shell อยู่แล้ว → `guide.html`

## 🛠️ Server config files

| File | Purpose |
|------|---------|
| **`provision.sh`** | 🚀 **One-shot auto-deploy** — fresh Ubuntu VPS → running site in ~10 min |
| `deploy.sh` | Zero-downtime redeploy script (run after code changes) |
| `supervisor-queue.conf` | Queue worker config (default + downloads queue) |
| `crontab.txt` | Single-line Laravel scheduler entry |
| `logrotate.conf` | 14-day log rotation for `storage/logs/` + worker logs |
| `nginx.conf` | HTTPS server block with caching + security headers |

---

## 🚀 Quick start — fresh VPS (recommended)

The `provision.sh` script does EVERYTHING below automatically:

```bash
# SSH into your fresh Ubuntu 22.04+ VPS as root, then:
curl -fsSL https://raw.githubusercontent.com/YOUR-REPO/photo-gallery/main/docs/deployment/provision.sh -o provision.sh
sudo bash provision.sh

# Or if you've already git-cloned the repo:
cd /path/to/photo-gallery
sudo bash docs/deployment/provision.sh
```

The script will interactively ask for:
- Domain name + Let's Encrypt email
- Git repo URL (skipped if run from existing checkout)
- MySQL credentials (auto-suggests a strong password)
- Whether to provision SSL now (skip if DNS not yet pointed)

Then it installs + configures:

1. System packages (PHP 8.2, MySQL 8, Redis, Nginx, Node.js 20, Supervisor, Certbot)
2. PHP-FPM tuned for photo uploads (50MB max, 512MB memory, 300s timeout, OPcache on)
3. MySQL database + app user with random strong password
4. `.env` from `.env.production.example` with all infra values pre-filled
5. Composer install + npm build + migrations + cache warming
6. Nginx site with your domain substituted
7. Let's Encrypt HTTPS (DNS-checked before calling certbot)
8. Supervisor queue workers (default + downloads)
9. Cron scheduler + logrotate
10. UFW firewall (22, 80, 443 only)
11. Runs smoke test at the end

Credentials are saved to `/root/photo-gallery-credentials.txt` (chmod 600).

---

## 🔄 Redeploy — code updates after the first install

```bash
cd /var/www/photo-gallery
sudo -u www-data ./docs/deployment/deploy.sh
```

`deploy.sh` does: git pull → composer install → npm build → maintenance mode → migrate → cache rebuild → queue restart → smoke test, with auto-restore on any failure.

---

## 🛠 Manual install (if you prefer step-by-step)

```bash
# Supervisor
sudo cp docs/deployment/supervisor-queue.conf /etc/supervisor/conf.d/photo-gallery.conf
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start photo-gallery:*

# Cron
sudo crontab -u www-data -e
# paste the line from docs/deployment/crontab.txt

# Logrotate
sudo cp docs/deployment/logrotate.conf /etc/logrotate.d/photo-gallery
sudo logrotate -d /etc/logrotate.d/photo-gallery   # dry-run

# Nginx
sudo cp docs/deployment/nginx.conf /etc/nginx/sites-available/photo-gallery
sudo ln -s /etc/nginx/sites-available/photo-gallery /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

# SSL
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Deploy script
chmod +x docs/deployment/deploy.sh
./docs/deployment/deploy.sh
```

See `docs/INSTALLATION.md` sections 14–16 for full context.
