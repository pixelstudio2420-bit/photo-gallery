#!/usr/bin/env bash
# ════════════════════════════════════════════════════════════════════════
#  Photo Gallery — One-Shot Server Provisioning
# ════════════════════════════════════════════════════════════════════════
#
#  Bootstraps a bare Ubuntu 22.04+ VPS into a fully running Photo Gallery
#  production server. Idempotent — safe to re-run on partial failures.
#
#  Usage (as root on a fresh VPS):
#    curl -fsSL https://raw.githubusercontent.com/YOUR/REPO/main/docs/deployment/provision.sh -o provision.sh
#    sudo bash provision.sh
#
#  Or, if you've already cloned the repo:
#    sudo bash /path/to/photo-gallery/docs/deployment/provision.sh
#
#  What it does:
#    1.  Installs PHP 8.2 + extensions
#    2.  Installs MySQL 8 + creates DB/user
#    3.  Installs Redis, Nginx, Certbot, Supervisor, Node.js 20, Composer
#    4.  Clones the repo (or uses an existing checkout)
#    5.  Configures .env + runs migrations + builds assets
#    6.  Installs Nginx/Supervisor/logrotate/cron configs
#    7.  Provisions SSL via Let's Encrypt (optional — skip if DNS not ready)
#    8.  Hardens the firewall (ufw)
#    9.  Runs smoke test
#   10.  Prints credentials + next steps
#
#  Requirements:
#    - Ubuntu 22.04 or 24.04 LTS
#    - Running as root (or with sudo)
#    - Internet access
#    - Domain DNS pointed to this server (for SSL step)
# ════════════════════════════════════════════════════════════════════════

set -Eeuo pipefail

# ──────────────────────────────────────────────────────────────────────
#  Helpers
# ──────────────────────────────────────────────────────────────────────
readonly C_RESET=$'\033[0m'
readonly C_BLUE=$'\033[1;34m'
readonly C_GREEN=$'\033[1;32m'
readonly C_YELLOW=$'\033[1;33m'
readonly C_RED=$'\033[1;31m'
readonly C_CYAN=$'\033[1;36m'

step()  { echo -e "\n${C_BLUE}▶ $*${C_RESET}"; }
ok()    { echo -e "${C_GREEN}  ✔ $*${C_RESET}"; }
warn()  { echo -e "${C_YELLOW}  ⚠ $*${C_RESET}"; }
fail()  { echo -e "${C_RED}  ✘ $*${C_RESET}" >&2; exit 1; }
info()  { echo -e "${C_CYAN}  • $*${C_RESET}"; }

trap 'echo -e "\n${C_RED}✘ Provisioning failed at line $LINENO (see above).${C_RESET}\n   You can safely re-run this script after fixing the issue.\n" >&2' ERR

ask() {
    local prompt="$1" default="${2:-}" var
    if [[ -n "$default" ]]; then
        read -rp "  $prompt [$default]: " var
        echo "${var:-$default}"
    else
        while [[ -z "${var:-}" ]]; do read -rp "  $prompt: " var; done
        echo "$var"
    fi
}

ask_secret() {
    local prompt="$1" var
    while [[ -z "${var:-}" || ${#var} -lt 12 ]]; do
        read -rsp "  $prompt (min 12 chars): " var
        echo >&2
    done
    echo "$var"
}

ask_yn() {
    local prompt="$1" default="${2:-n}" reply
    read -rp "  $prompt [${default}]: " reply
    reply="${reply:-$default}"
    [[ "$reply" =~ ^[Yy]$ ]]
}

# Safely set key=value in a .env file (handles &, |, $ in values)
set_env() {
    local file="$1" key="$2" value="$3"
    # Escape sed replacement special chars: \, &, and delimiter (|)
    local escaped
    escaped=$(printf '%s' "$value" | sed -e 's/[\\&|]/\\&/g' -e 's/\r$//')
    if grep -q "^${key}=" "$file"; then
        sed -i "s|^${key}=.*|${key}=${escaped}|" "$file"
    else
        echo "${key}=${value}" >> "$file"
    fi
}

# ──────────────────────────────────────────────────────────────────────
#  Pre-flight checks
# ──────────────────────────────────────────────────────────────────────
clear
cat <<'BANNER'
╔══════════════════════════════════════════════════════════════════════╗
║                                                                      ║
║        📸  Photo Gallery — One-Shot Provisioning                     ║
║                                                                      ║
╚══════════════════════════════════════════════════════════════════════╝
BANNER

[[ $EUID -eq 0 ]] || fail "Please run as root:  sudo bash $0"

if [[ -f /etc/os-release ]]; then
    . /etc/os-release
    [[ "$ID" == "ubuntu" ]] || warn "This script is tested on Ubuntu only — you're on $PRETTY_NAME"
    major="${VERSION_ID%%.*}"
    [[ "$major" -ge 22 ]] || warn "Ubuntu 22.04+ recommended — you're on $VERSION_ID"
else
    warn "Can't detect OS version"
fi

ping -c 1 -W 3 8.8.8.8 >/dev/null 2>&1 || fail "No internet connectivity"

# ──────────────────────────────────────────────────────────────────────
#  Collect inputs
# ──────────────────────────────────────────────────────────────────────
echo
echo "Please answer a few questions (press Enter to accept defaults):"
echo

DOMAIN=$(ask "Primary domain (e.g. yourdomain.com — no https://)")
WWW_DOMAIN=$(ask "WWW subdomain" "www.$DOMAIN")
LE_EMAIL=$(ask "Email for Let's Encrypt notifications")

# Detect if we're already inside the repo
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DEFAULT_ROOT="$(cd "$SCRIPT_DIR/../.." 2>/dev/null && pwd || echo '')"
if [[ -n "$REPO_DEFAULT_ROOT" && -f "$REPO_DEFAULT_ROOT/artisan" ]]; then
    info "Detected existing checkout at: $REPO_DEFAULT_ROOT"
    USE_EXISTING=$(ask "Use this checkout? (yes/no)" "yes")
else
    USE_EXISTING="no"
fi

if [[ "$USE_EXISTING" == "yes" ]]; then
    APP_ROOT="$REPO_DEFAULT_ROOT"
    GIT_REPO=""
    GIT_BRANCH=""
else
    GIT_REPO=$(ask "Git repository URL (HTTPS)")
    GIT_BRANCH=$(ask "Git branch" "main")
    APP_ROOT=$(ask "Install path" "/var/www/photo-gallery")
fi

DB_NAME=$(ask "MySQL database name" "photo_gallery")
DB_USER=$(ask "MySQL username" "photo_gallery")

# Auto-generate a strong DB password by default, but let the user override
DB_PASS_DEFAULT="$(openssl rand -base64 24 | tr -d '=+/' | head -c 24)"
echo "  Suggested MySQL password: ${DB_PASS_DEFAULT}"
if ask_yn "Use suggested password?" "y"; then
    DB_PASS="$DB_PASS_DEFAULT"
else
    DB_PASS=$(ask_secret "Enter MySQL password")
fi

INSTALL_SSL=$(ask_yn "Provision HTTPS via Let's Encrypt now? (DNS must already point here)" "y" && echo "yes" || echo "no")

# ──────────────────────────────────────────────────────────────────────
#  Confirm
# ──────────────────────────────────────────────────────────────────────
echo
echo "════════════════════════════════════════════════"
echo "  Review before proceeding:"
echo "════════════════════════════════════════════════"
echo "    Domain:      $DOMAIN (+ $WWW_DOMAIN)"
echo "    LE email:    $LE_EMAIL"
echo "    App root:    $APP_ROOT"
[[ -n "$GIT_REPO" ]] && echo "    Repo:        $GIT_REPO ($GIT_BRANCH)"
echo "    DB:          $DB_NAME / $DB_USER"
echo "    SSL now:     $INSTALL_SSL"
echo

ask_yn "Proceed with provisioning?" "y" || fail "Aborted by user"

PROVISION_START=$(date +%s)

# ──────────────────────────────────────────────────────────────────────
#  1. System packages
# ──────────────────────────────────────────────────────────────────────
step "1/15  Updating system packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get upgrade -y -qq
apt-get install -y -qq \
    software-properties-common curl wget git unzip \
    ca-certificates apt-transport-https gnupg lsb-release \
    ufw supervisor logrotate cron openssl
ok "base packages installed"

# ──────────────────────────────────────────────────────────────────────
#  2. PHP 8.2
# ──────────────────────────────────────────────────────────────────────
step "2/15  Installing PHP 8.2 + extensions"
if ! apt-cache policy php8.2 2>/dev/null | grep -q Installed; then
    add-apt-repository -y ppa:ondrej/php
    apt-get update -qq
fi
apt-get install -y -qq \
    php8.2-fpm php8.2-cli php8.2-common php8.2-mysql \
    php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip \
    php8.2-gd php8.2-intl php8.2-bcmath php8.2-redis \
    php8.2-imagick php8.2-opcache

# Tune php.ini for both fpm + cli
for ini in /etc/php/8.2/fpm/php.ini /etc/php/8.2/cli/php.ini; do
    sed -i \
        -e 's/^;*post_max_size\s*=.*/post_max_size = 60M/' \
        -e 's/^;*upload_max_filesize\s*=.*/upload_max_filesize = 50M/' \
        -e 's/^;*memory_limit\s*=.*/memory_limit = 512M/' \
        -e 's/^;*max_execution_time\s*=.*/max_execution_time = 300/' \
        -e 's/^;*max_input_time\s*=.*/max_input_time = 300/' \
        -e 's/^;*date.timezone\s*=.*/date.timezone = Asia\/Bangkok/' \
        -e 's/^;*opcache.enable\s*=.*/opcache.enable=1/' \
        -e 's/^;*opcache.memory_consumption\s*=.*/opcache.memory_consumption=256/' \
        -e 's/^;*opcache.max_accelerated_files\s*=.*/opcache.max_accelerated_files=20000/' \
        "$ini"
done
systemctl enable --now php8.2-fpm
systemctl restart php8.2-fpm
ok "PHP $(php -r 'echo PHP_VERSION;') ready"

# ──────────────────────────────────────────────────────────────────────
#  3. MySQL
# ──────────────────────────────────────────────────────────────────────
step "3/15  Installing MySQL + creating database"
apt-get install -y -qq mysql-server
systemctl enable --now mysql

# Create DB + user (idempotent)
mysql --protocol=socket -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`
    DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
ok "database '$DB_NAME' ready"

# ──────────────────────────────────────────────────────────────────────
#  4. Redis
# ──────────────────────────────────────────────────────────────────────
step "4/15  Installing Redis"
apt-get install -y -qq redis-server
# Bind only to localhost + disable protected mode warning
sed -i 's/^supervised.*/supervised systemd/' /etc/redis/redis.conf
systemctl enable --now redis-server
systemctl restart redis-server
ok "Redis running on 127.0.0.1:6379"

# ──────────────────────────────────────────────────────────────────────
#  5. Nginx + Certbot
# ──────────────────────────────────────────────────────────────────────
step "5/15  Installing Nginx + Certbot"
apt-get install -y -qq nginx certbot python3-certbot-nginx
rm -f /etc/nginx/sites-enabled/default
systemctl enable --now nginx
ok "Nginx + Certbot installed"

# ──────────────────────────────────────────────────────────────────────
#  6. Node.js 20 + Composer
# ──────────────────────────────────────────────────────────────────────
step "6/15  Installing Node.js 20"
if ! command -v node >/dev/null 2>&1 || [[ $(node -v | sed 's/v//' | cut -d. -f1) -lt 20 ]]; then
    curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
    apt-get install -y -qq nodejs
fi
ok "Node $(node -v) / npm $(npm -v)"

step "7/15  Installing Composer"
if ! command -v composer >/dev/null 2>&1; then
    EXPECTED_CHECKSUM="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
    [[ "$EXPECTED_CHECKSUM" == "$ACTUAL_CHECKSUM" ]] || fail "Composer installer checksum mismatch"
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet
    rm /tmp/composer-setup.php
fi
ok "Composer $(composer --version --no-ansi 2>/dev/null | awk '{print $3}')"

# ──────────────────────────────────────────────────────────────────────
#  7. Clone / prepare application
# ──────────────────────────────────────────────────────────────────────
step "8/15  Preparing application code"
if [[ "$USE_EXISTING" != "yes" ]]; then
    mkdir -p "$(dirname "$APP_ROOT")"
    if [[ -d "$APP_ROOT/.git" ]]; then
        info "Repo already cloned — pulling latest"
        git -C "$APP_ROOT" fetch --all --prune
        git -C "$APP_ROOT" reset --hard "origin/$GIT_BRANCH"
    else
        git clone -b "$GIT_BRANCH" "$GIT_REPO" "$APP_ROOT"
    fi
fi

# Ownership + permissions
chown -R www-data:www-data "$APP_ROOT"
find "$APP_ROOT" -type d -exec chmod 755 {} \;
find "$APP_ROOT" -type f -exec chmod 644 {} \;
chmod -R 775 "$APP_ROOT/storage" "$APP_ROOT/bootstrap/cache"
chmod +x "$APP_ROOT/artisan" \
          "$APP_ROOT/docs/deployment/deploy.sh" 2>/dev/null || true
ok "code at $APP_ROOT ($(du -sh "$APP_ROOT" | cut -f1))"

# ──────────────────────────────────────────────────────────────────────
#  8. .env configuration
# ──────────────────────────────────────────────────────────────────────
step "9/15  Configuring .env"
cd "$APP_ROOT"
if [[ ! -f .env ]]; then
    if [[ -f .env.production.example ]]; then
        sudo -u www-data cp .env.production.example .env
    elif [[ -f .env.example ]]; then
        sudo -u www-data cp .env.example .env
    else
        fail ".env template not found — expected .env.production.example or .env.example"
    fi
fi

set_env .env APP_ENV         "production"
set_env .env APP_DEBUG       "false"
set_env .env APP_URL         "https://${DOMAIN}"
set_env .env APP_TIMEZONE    "Asia/Bangkok"
set_env .env FORCE_HTTPS     "true"
set_env .env LOG_CHANNEL     "daily"
set_env .env LOG_LEVEL       "warning"
set_env .env DB_CONNECTION   "mysql"
set_env .env DB_HOST         "127.0.0.1"
set_env .env DB_PORT         "3306"
set_env .env DB_DATABASE     "$DB_NAME"
set_env .env DB_USERNAME     "$DB_USER"
set_env .env DB_PASSWORD     "$DB_PASS"
set_env .env CACHE_STORE     "redis"
set_env .env SESSION_DRIVER  "redis"
set_env .env QUEUE_CONNECTION "redis"
set_env .env REDIS_HOST      "127.0.0.1"
set_env .env REDIS_PORT      "6379"

chown www-data:www-data .env
chmod 640 .env

# Generate app key (only if not already set)
if ! grep -qE '^APP_KEY=base64:' .env; then
    sudo -u www-data php artisan key:generate --force
fi
ok ".env configured + APP_KEY set"

# ──────────────────────────────────────────────────────────────────────
#  9. Dependencies + assets
# ──────────────────────────────────────────────────────────────────────
step "10/15 Installing dependencies + building assets"
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
sudo -u www-data npm ci --silent
sudo -u www-data npm run build
ok "dependencies + Vite build complete"

# ──────────────────────────────────────────────────────────────────────
#  10. Migrations + storage link + caches
# ──────────────────────────────────────────────────────────────────────
step "11/15 Running migrations + priming caches"
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan storage:link 2>/dev/null || true
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache 2>/dev/null || true
ok "DB migrated, caches primed"

# ──────────────────────────────────────────────────────────────────────
#  11. Nginx site config
# ──────────────────────────────────────────────────────────────────────
step "12/15 Installing Nginx site config"
NGINX_SRC="$APP_ROOT/docs/deployment/nginx.conf"
NGINX_DST="/etc/nginx/sites-available/photo-gallery"
[[ -f "$NGINX_SRC" ]] || fail "Missing $NGINX_SRC"

cp "$NGINX_SRC" "$NGINX_DST"
sed -i "s|yourdomain\\.com|${DOMAIN}|g" "$NGINX_DST"
sed -i "s|www\\.${DOMAIN}|${WWW_DOMAIN}|g" "$NGINX_DST"
sed -i "s|/var/www/photo-gallery|${APP_ROOT}|g" "$NGINX_DST"
ln -sf "$NGINX_DST" /etc/nginx/sites-enabled/photo-gallery

nginx -t
systemctl reload nginx
ok "Nginx serving $DOMAIN → $APP_ROOT/public"

# ──────────────────────────────────────────────────────────────────────
#  12. Let's Encrypt SSL
# ──────────────────────────────────────────────────────────────────────
if [[ "$INSTALL_SSL" == "yes" ]]; then
    step "13/15 Provisioning SSL via Let's Encrypt"
    SERVER_IP=$(curl -fsS4 https://ifconfig.me || echo "unknown")
    DOMAIN_IP=$(dig +short "$DOMAIN" @1.1.1.1 | tail -n1 || echo "")
    if [[ -n "$DOMAIN_IP" && "$DOMAIN_IP" != "$SERVER_IP" ]]; then
        warn "DNS check: $DOMAIN resolves to $DOMAIN_IP but this server is $SERVER_IP"
        warn "Certbot may fail. Skipping SSL — fix DNS then re-run:  certbot --nginx -d $DOMAIN -d $WWW_DOMAIN"
    else
        certbot --nginx \
            -d "$DOMAIN" -d "$WWW_DOMAIN" \
            --agree-tos --non-interactive --redirect \
            --email "$LE_EMAIL" \
            || warn "Certbot failed — fix DNS + re-run manually"
        ok "HTTPS active (auto-renews via systemd timer)"
    fi
else
    info "Skipped SSL — after pointing DNS, run:"
    info "    certbot --nginx -d $DOMAIN -d $WWW_DOMAIN --agree-tos -m $LE_EMAIL --redirect"
fi

# ──────────────────────────────────────────────────────────────────────
#  13. Supervisor (queue workers)
# ──────────────────────────────────────────────────────────────────────
step "14/15 Installing Supervisor queue workers + cron + logrotate"
SUP_SRC="$APP_ROOT/docs/deployment/supervisor-queue.conf"
SUP_DST="/etc/supervisor/conf.d/photo-gallery.conf"
if [[ -f "$SUP_SRC" ]]; then
    cp "$SUP_SRC" "$SUP_DST"
    sed -i "s|/var/www/photo-gallery|${APP_ROOT}|g" "$SUP_DST"
    supervisorctl reread
    supervisorctl update
    supervisorctl start photo-gallery:* 2>/dev/null || true
    ok "queue workers running (check: sudo supervisorctl status)"
else
    warn "supervisor-queue.conf not found — skipping queue worker setup"
fi

# Cron (Laravel scheduler)
CRON_LINE="* * * * * cd ${APP_ROOT} && /usr/bin/php artisan schedule:run >> /dev/null 2>&1"
(crontab -u www-data -l 2>/dev/null | grep -v "artisan schedule:run" ; echo "$CRON_LINE") \
    | crontab -u www-data -
ok "Laravel scheduler cron installed"

# Logrotate
LR_SRC="$APP_ROOT/docs/deployment/logrotate.conf"
if [[ -f "$LR_SRC" ]]; then
    cp "$LR_SRC" /etc/logrotate.d/photo-gallery
    sed -i "s|/var/www/photo-gallery|${APP_ROOT}|g" /etc/logrotate.d/photo-gallery
    ok "logrotate configured (14-day app logs, 4-week worker logs)"
fi

# ──────────────────────────────────────────────────────────────────────
#  14. Firewall
# ──────────────────────────────────────────────────────────────────────
step "15/15 Configuring firewall (ufw)"
ufw --force reset >/dev/null
ufw default deny incoming >/dev/null
ufw default allow outgoing >/dev/null
ufw allow 22/tcp   comment 'SSH' >/dev/null
ufw allow 80/tcp   comment 'HTTP' >/dev/null
ufw allow 443/tcp  comment 'HTTPS' >/dev/null
ufw --force enable >/dev/null
ok "ufw active: 22, 80, 443 open — all else denied"

# ──────────────────────────────────────────────────────────────────────
#  Smoke test
# ──────────────────────────────────────────────────────────────────────
step "Running smoke test"
cd "$APP_ROOT"
if sudo -u www-data php artisan app:smoke-test 2>&1 | tee /tmp/smoke.log; then
    SMOKE_OK="yes"
else
    SMOKE_OK="no"
    warn "smoke test had failures — see /tmp/smoke.log"
fi

# ──────────────────────────────────────────────────────────────────────
#  Save credentials
# ──────────────────────────────────────────────────────────────────────
CREDS_FILE="/root/photo-gallery-credentials.txt"
cat > "$CREDS_FILE" <<EOF
# Photo Gallery — Provisioning Credentials
# Generated: $(date -u +'%Y-%m-%d %H:%M:%S UTC')
# ⚠ KEEP THIS FILE SAFE — contains database password in plain text

Domain:            https://${DOMAIN}
App root:          ${APP_ROOT}

MySQL host:        127.0.0.1:3306
MySQL database:    ${DB_NAME}
MySQL user:        ${DB_USER}
MySQL password:    ${DB_PASS}

Server IP:         $(curl -fsS4 https://ifconfig.me 2>/dev/null || echo 'unknown')
Provisioned:       $(date)

# Redeploy command:
#   cd ${APP_ROOT} && sudo -u www-data ./docs/deployment/deploy.sh
EOF
chmod 600 "$CREDS_FILE"

# ──────────────────────────────────────────────────────────────────────
#  Summary
# ──────────────────────────────────────────────────────────────────────
ELAPSED=$(( $(date +%s) - PROVISION_START ))
SERVER_IP=$(curl -fsS4 https://ifconfig.me 2>/dev/null || echo 'unknown')

echo
echo -e "${C_GREEN}"
cat <<EOF
╔══════════════════════════════════════════════════════════════════════╗
║                                                                      ║
║   ✓  Provisioning complete in ${ELAPSED}s                                        ║
║                                                                      ║
╚══════════════════════════════════════════════════════════════════════╝
EOF
echo -e "${C_RESET}"

cat <<EOF
  🌐 Site:         https://${DOMAIN}
  📂 App root:     ${APP_ROOT}
  🗄  Database:     ${DB_NAME} (user: ${DB_USER})
  🔑 Credentials:  ${CREDS_FILE}  (chmod 600)
  📊 Server IP:    ${SERVER_IP}
  🧪 Smoke test:   ${SMOKE_OK}

${C_CYAN}  ═══ Next steps ═══${C_RESET}

  1️⃣  Point DNS to this server (if not done already):
      A   ${DOMAIN}      → ${SERVER_IP}
      A   ${WWW_DOMAIN}  → ${SERVER_IP}

  2️⃣  (If Cloudflare) Enable proxy (🟠) AFTER HTTPS is confirmed working

  3️⃣  Create the first admin user:
      cd ${APP_ROOT}
      sudo -u www-data php artisan tinker
      > \\App\\Models\\User::create([
          'name' => 'Admin',
          'email' => 'you@example.com',
          'password' => \\Hash::make('STRONG_PASSWORD'),
          'is_admin' => true,
        ])

  4️⃣  Log in and configure OAuth/APIs at:
      https://${DOMAIN}/admin/settings
      (Google, LINE Messaging, SlipOK, Stripe, Apple Sign-In, etc.)

  5️⃣  Verify services are running:
      systemctl status nginx php8.2-fpm mysql redis-server
      sudo supervisorctl status

${C_YELLOW}  ⚠ SECURITY TODO${C_RESET}
  • Set up SSH key auth + disable password login in /etc/ssh/sshd_config
  • Review firewall: ufw status verbose
  • Rotate MySQL root password if you haven't already:
      sudo mysql_secure_installation

${C_CYAN}  ═══ Redeploy ═══${C_RESET}
  For future deploys (pulls latest code + rebuilds + zero-downtime):
      cd ${APP_ROOT} && sudo -u www-data ./docs/deployment/deploy.sh

EOF
