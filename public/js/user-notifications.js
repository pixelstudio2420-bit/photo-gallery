/**
 * User In-App Notification System
 * Bell icon with dropdown, smart adaptive polling, desktop notifications, Thai locale
 */
const UserNotify = {
    // Adaptive polling: fast when active, slow when idle
    POLL_FAST: 15000,    // 15s when new activity detected
    POLL_NORMAL: 30000,  // 30s default
    POLL_SLOW: 60000,    // 60s when tab is hidden or idle
    POLL_BACKOFF: 120000,// 120s backoff on rate-limit (429)
    pollInterval: 30000,
    timer: null,
    audioCtx: null,
    unreadCount: 0,
    soundEnabled: JSON.parse(localStorage.getItem('user_sound_enabled') ?? 'true'),
    desktopEnabled: JSON.parse(localStorage.getItem('user_desktop_notif') ?? 'false'),
    baseUrl: '',
    panelOpen: false,
    lastKnownCount: 0,
    lastPollTime: null,         // ISO timestamp of last successful poll (legacy)
    sinceId: 0,                 // Monotonic id-based cursor (preferred, timezone-safe)
    isFirstPoll: true,          // Suppress sounds + desktop notifs on baseline poll
    seenIds: null,              // Set of ids we've already toasted/dinged (dedupe)
    maxToastsPerTick: 3,        // Cap how many sounds/desktop fires in one poll
    idleSince: null,            // Track idle state
    visibilityHidden: false,
    consecutiveErrors: 0,       // Track consecutive errors for backoff
    isBackingOff: false,        // Prevent polling during backoff

    // Color per notification type
    typeConfig: {
        order:    { icon: 'bi-bag-check-fill',    color: '#6366f1', bg: 'rgba(99,102,241,.1)',   label: 'คำสั่งซื้อ' },
        payment:  { icon: 'bi-check-circle-fill',  color: '#10b981', bg: 'rgba(16,185,129,.1)',   label: 'การชำระเงิน' },
        slip:     { icon: 'bi-receipt',             color: '#f59e0b', bg: 'rgba(245,158,11,.1)',   label: 'สลิป' },
        download: { icon: 'bi-download',            color: '#3b82f6', bg: 'rgba(59,130,246,.1)',   label: 'ดาวน์โหลด' },
        system:   { icon: 'bi-info-circle-fill',    color: '#64748b', bg: 'rgba(100,116,139,.1)',  label: 'ระบบ' },
        order_status:   { icon: 'bi-bag-check-fill',   color: '#6366f1', bg: 'rgba(99,102,241,.1)',  label: 'สถานะคำสั่งซื้อ' },
        slip_approved:  { icon: 'bi-check-circle-fill', color: '#10b981', bg: 'rgba(16,185,129,.1)',  label: 'สลิปอนุมัติ' },
        slip_rejected:  { icon: 'bi-x-circle-fill',     color: '#ef4444', bg: 'rgba(239,68,68,.1)',   label: 'สลิปปฏิเสธ' },
    },

    init() {
        this.baseUrl = document.querySelector('meta[name="base-url"]')?.content || '';
        // Hydrate dedupe set from sessionStorage so a cross-page click
        // (admin/orders → admin/users etc.) doesn't re-ding for items
        // we already toasted. Capped at 200 entries (FIFO drop) so it
        // doesn't grow unbounded over a long session.
        this.seenIds = new Set();
        try {
            const stored = sessionStorage.getItem('user_seen_notif_ids');
            if (stored) JSON.parse(stored).forEach(id => this.seenIds.add(id));
        } catch (_) {}
        this.injectStyles();
        this.createPanel();
        this.bindEvents();
        this.loadNotifications();
        this.startPolling();
        this.setupVisibilityTracking();
        this.setupIdleTracking();

        // Request desktop notification permission if user opted in before
        if (this.desktopEnabled && 'Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }
    },

    // ========== Styles ==========
    injectStyles() {
        if (document.getElementById('user-notify-styles')) return;
        const s = document.createElement('style');
        s.id = 'user-notify-styles';
        s.textContent = `
            .user-notify-panel{position:absolute;top:100%;right:0;width:370px;max-height:480px;background:#fff;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,.15);overflow:hidden;display:none;z-index:10000}
            .user-notify-panel.show{display:block;animation:unSlideIn .2s ease}
            @keyframes unSlideIn{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
            .un-header{display:flex;justify-content:space-between;align-items:center;padding:12px 16px;border-bottom:1px solid #e5e7eb;background:#f9fafb}
            .un-header .fw-bold{font-size:14px}
            .un-mark-all{font-size:11px;color:#6366f1;cursor:pointer;border:none;background:none;padding:2px 8px;border-radius:4px;transition:background .15s}
            .un-mark-all:hover{background:rgba(99,102,241,.08)}
            .un-list{max-height:400px;overflow-y:auto}
            .un-item{display:flex;align-items:flex-start;gap:10px;padding:10px 16px;cursor:pointer;transition:background .15s;border-bottom:1px solid #f3f4f6}
            .un-item:hover{background:#f9fafb}
            .un-item.is-read{opacity:.5}
            .un-item.is-read:hover{opacity:.75}
            .un-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:6px}
            .un-empty{text-align:center;padding:50px 20px;color:#9ca3af}
            .un-empty i{font-size:2.2rem;display:block;margin-bottom:10px;opacity:.35}
            .un-time{font-size:10px;color:#9ca3af;white-space:nowrap}
            .un-sound-btn{font-size:14px;cursor:pointer;border:none;background:none;padding:2px;opacity:.6;transition:opacity .15s}
            .un-sound-btn:hover{opacity:1}
            .un-desktop-btn{font-size:14px;cursor:pointer;border:none;background:none;padding:2px;opacity:.6;transition:opacity .15s}
            .un-desktop-btn:hover{opacity:1}
            .un-new-badge{display:inline-block;font-size:9px;background:#6366f1;color:#fff;padding:1px 6px;border-radius:8px;margin-left:4px;animation:unPulse 2s infinite}
            @keyframes unPulse{0%,100%{opacity:1}50%{opacity:.6}}
        `;
        document.head.appendChild(s);
    },

    // ========== Panel ==========
    createPanel() {
        const toggle = document.getElementById('userNotifyToggle');
        if (!toggle) return;

        const panel = document.createElement('div');
        panel.className = 'user-notify-panel';
        panel.id = 'userNotifyPanel';
        panel.innerHTML = `
            <div class="un-header">
                <span class="fw-bold">การแจ้งเตือน <span class="text-muted fw-normal" id="unCountLabel"></span></span>
                <div class="d-flex align-items-center gap-1">
                    <button class="un-mark-all" id="unMarkAllBtn" onclick="UserNotify.markAllRead()" style="display:none">
                        <i class="bi bi-check2-all me-1"></i>อ่านทั้งหมด
                    </button>
                    <button class="un-desktop-btn" onclick="UserNotify.toggleDesktop()" id="unDesktopBtn" title="เปิด/ปิดแจ้งเตือนเดสก์ท็อป">
                        <i class="bi ${this.desktopEnabled ? 'bi-window-fullscreen' : 'bi-window'}"></i>
                    </button>
                    <button class="un-sound-btn" onclick="UserNotify.toggleSound()" id="unSoundBtn" title="เปิด/ปิดเสียง">
                        <i class="bi ${this.soundEnabled ? 'bi-volume-up-fill' : 'bi-volume-mute-fill'}"></i>
                    </button>
                </div>
            </div>
            <div class="un-list" id="unList"></div>
            <div style="padding:10px 16px;border-top:1px solid #e5e7eb;background:#f9fafb;text-align:center;">
                <a href="/notifications" style="font-size:12px;color:#6366f1;text-decoration:none;font-weight:500;">
                    <i class="bi bi-arrow-right me-1"></i>ดูการแจ้งเตือนทั้งหมด
                </a>
            </div>
        `;
        toggle.parentElement.style.position = 'relative';
        toggle.parentElement.appendChild(panel);
    },

    bindEvents() {
        const toggle = document.getElementById('userNotifyToggle');
        const panel = document.getElementById('userNotifyPanel');
        if (!toggle || !panel) return;

        toggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.panelOpen = !this.panelOpen;
            panel.classList.toggle('show', this.panelOpen);
            if (this.panelOpen) this.loadNotifications();
        });

        document.addEventListener('click', (e) => {
            if (this.panelOpen && !e.target.closest('#userNotifyToggle') && !e.target.closest('#userNotifyPanel')) {
                this.panelOpen = false;
                panel.classList.remove('show');
            }
        });
    },

    // ========== Visibility & Idle Tracking ==========
    setupVisibilityTracking() {
        document.addEventListener('visibilitychange', () => {
            this.visibilityHidden = document.hidden;
            this.adjustPollingRate();

            // When tab becomes visible again, do an immediate poll
            if (!document.hidden) {
                this.poll();
            }
        });
    },

    setupIdleTracking() {
        const resetIdle = () => {
            this.idleSince = null;
            this.adjustPollingRate();
        };
        // User interaction resets idle
        ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(evt => {
            document.addEventListener(evt, resetIdle, { passive: true });
        });

        // Mark idle after 2 minutes of no interaction
        this.idleCheckTimer = setInterval(() => {
            if (!this.idleSince) {
                this.idleSince = Date.now();
            }
        }, 120000);
    },

    adjustPollingRate() {
        let newInterval;
        if (this.visibilityHidden) {
            newInterval = this.POLL_SLOW;
        } else if (this.idleSince && (Date.now() - this.idleSince > 120000)) {
            newInterval = this.POLL_SLOW;
        } else {
            newInterval = this.POLL_NORMAL;
        }

        if (newInterval !== this.pollInterval) {
            this.pollInterval = newInterval;
            this.startPolling();
        }
    },

    startPolling() {
        if (this.timer) clearInterval(this.timer);
        this.timer = setInterval(() => this.poll(), this.pollInterval);
    },

    // ========== Load notifications (full) ==========
    async loadNotifications() {
        try {
            const resp = await fetch(`${this.baseUrl}/api/notifications`, { credentials: 'same-origin' });

            if (resp.status === 429) {
                this.handleRateLimit();
                return;
            }
            if (!resp.ok) return;

            const json = await resp.json();
            if (!json.success) return;

            this.consecutiveErrors = 0;
            this.unreadCount = json.unread_count;
            this.lastKnownCount = json.unread_count;
            this.lastPollTime = new Date().toISOString();
            this.updateBadge();
            this.renderPanel(json.notifications);
        } catch (e) {
            this.consecutiveErrors++;
            console.warn('[UserNotify] Poll error:', e.message);
        }
    },

    renderPanel(items) {
        const list = document.getElementById('unList');
        if (!list) return;

        if (!items || items.length === 0) {
            list.innerHTML = `<div class="un-empty"><i class="bi bi-bell-slash"></i>ไม่มีการแจ้งเตือน</div>`;
            return;
        }

        // Show max 15
        const show = items.slice(0, 15);
        list.innerHTML = show.map(n => {
            const tc = this.typeConfig[n.type] || this.typeConfig.system;
            const isRead = n.is_read == 1;
            const time = this.formatTime(n.created_at);
            // Mark items less than 60 seconds old as "new"
            const isNew = !isRead && (Date.now() - new Date(n.created_at).getTime()) < 60000;
            return `
                <div class="un-item ${isRead ? 'is-read' : ''}" data-id="${n.id}" data-link="${n.action_url || n.link || ''}" onclick="UserNotify.clickNotification(this)">
                    <div class="rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center"
                         style="background:${tc.bg};width:32px;height:32px;min-width:32px">
                        <i class="bi ${tc.icon}" style="font-size:.85rem;color:${tc.color}"></i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold" style="font-size:.8rem;color:${isRead ? '#9ca3af' : tc.color}">
                            ${this.escHtml(n.title)}${isNew ? '<span class="un-new-badge">ใหม่</span>' : ''}
                        </div>
                        <div class="text-truncate" style="font-size:.75rem;color:${isRead ? '#b0b0b0' : '#6b7280'}">${this.escHtml(n.message)}</div>
                    </div>
                    <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                        <span class="un-time">${time}</span>
                        ${!isRead ? `<div class="un-dot" style="background:${tc.color}"></div>` : ''}
                    </div>
                </div>`;
        }).join('');
    },

    // ========== Click → mark read + navigate ==========
    async clickNotification(el) {
        const id = el.dataset.id;
        const link = el.dataset.link;

        if (!el.classList.contains('is-read')) {
            el.classList.add('is-read');
            const dot = el.querySelector('.un-dot');
            if (dot) dot.remove();
            const newBadge = el.querySelector('.un-new-badge');
            if (newBadge) newBadge.remove();

            try {
                await fetch(`${this.baseUrl}/api/notifications/${id}/read`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || window.__csrf || ''
                    },
                    credentials: 'same-origin',
                });
                this.unreadCount = Math.max(0, this.unreadCount - 1);
                this.updateBadge();
            } catch (e) {}
        }

        if (link) {
            window.location.href = link.startsWith('http') ? link : `${this.baseUrl}/${link}`;
        }
    },

    // ========== Mark all read ==========
    async markAllRead() {
        try {
            await fetch(`${this.baseUrl}/api/notifications/read-all`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || window.__csrf || ''
                },
                credentials: 'same-origin',
            });
            this.unreadCount = 0;
            this.updateBadge();

            document.querySelectorAll('.un-item').forEach(item => {
                item.classList.add('is-read');
                const dot = item.querySelector('.un-dot');
                if (dot) dot.remove();
                const newBadge = item.querySelector('.un-new-badge');
                if (newBadge) newBadge.remove();
            });
        } catch (e) {}
    },

    // ========== Badge ==========
    updateBadge() {
        const badge = document.getElementById('userNotifyBadge');
        const label = document.getElementById('unCountLabel');
        const markBtn = document.getElementById('unMarkAllBtn');

        if (badge) {
            if (this.unreadCount > 0) {
                badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
                badge.style.display = '';
            } else {
                badge.style.display = 'none';
            }
        }
        if (label) {
            label.textContent = this.unreadCount > 0 ? `(${this.unreadCount} ใหม่)` : '';
        }
        if (markBtn) {
            markBtn.style.display = this.unreadCount > 0 ? '' : 'none';
        }
    },

    // ========== Smart Polling ==========
    async poll() {
        // Skip if currently in backoff mode
        if (this.isBackingOff) return;

        try {
            // Use lightweight unread-count endpoint when panel is closed
            if (!this.panelOpen) {
                const resp = await fetch(`${this.baseUrl}/api/notifications/unread-count`, { credentials: 'same-origin' });

                // Handle rate limiting (429) — back off to avoid death spiral
                if (resp.status === 429) {
                    this.handleRateLimit();
                    return;
                }
                if (!resp.ok) return;

                const json = await resp.json();
                if (!json.success) return;

                // Successful poll — reset error counter
                this.consecutiveErrors = 0;

                const newCount = json.unread_count;

                // Suppress the new-activity ping on the very first poll —
                // a fresh page load shouldn't beep just because the user
                // already had unread items waiting in their inbox.
                if (newCount > this.lastKnownCount && this.lastKnownCount >= 0 && !this.isFirstPoll) {
                    this.onNewNotifications(newCount - this.lastKnownCount);
                    // Speed up polling temporarily after new activity
                    this.pollInterval = this.POLL_FAST;
                    this.startPolling();
                } else if (this.pollInterval === this.POLL_FAST) {
                    // Slow back to normal after a few fast polls with no new activity
                    this.adjustPollingRate();
                }

                this.isFirstPoll = false;
                this.lastKnownCount = newCount;
                this.unreadCount = newCount;
                this.updateBadge();
            } else {
                // Panel is open — do a full fetch with `since_id` to get new items.
                // Falls back to no-cursor on first poll (server returns
                // baseline + latest_id but no `new_items` to suppress flood).
                const url = this.sinceId > 0
                    ? `${this.baseUrl}/api/notifications?since_id=${this.sinceId}`
                    : `${this.baseUrl}/api/notifications`;

                const resp = await fetch(url, { credentials: 'same-origin' });

                // Handle rate limiting (429)
                if (resp.status === 429) {
                    this.handleRateLimit();
                    return;
                }
                if (!resp.ok) return;

                const json = await resp.json();
                if (!json.success) return;

                // Successful poll — reset error counter
                this.consecutiveErrors = 0;

                const newCount = json.unread_count;

                // Advance the cursor BEFORE processing items — even if
                // onNewNotifications throws, we don't keep re-firing on
                // the same cursor. Server tells us the latest id.
                const latestId = parseInt(json.latest_id || 0, 10) || 0;
                if (latestId > this.sinceId) this.sinceId = latestId;

                if (newCount > this.lastKnownCount && this.lastKnownCount >= 0 && !this.isFirstPoll) {
                    // Filter to items we haven't toasted before AND
                    // that are still unread (read items shouldn't ding).
                    // Cap to maxToastsPerTick so a sudden burst doesn't
                    // chain-fire desktop notifications.
                    const items = (json.new_items || json.notifications || [])
                        .filter(n => Number(n.is_read) !== 1)
                        .filter(n => !this.seenIds.has(n.id))
                        .slice(0, this.maxToastsPerTick);

                    if (items.length > 0) {
                        this.onNewNotifications(items.length);
                        items.forEach(n => this.seenIds.add(n.id));
                        // Persist + cap the dedupe set.
                        if (this.seenIds.size > 200) {
                            this.seenIds = new Set(Array.from(this.seenIds).slice(-200));
                        }
                        try {
                            sessionStorage.setItem('user_seen_notif_ids',
                                JSON.stringify(Array.from(this.seenIds)));
                        } catch (_) {}
                    }
                }

                this.isFirstPoll = false;
                this.lastKnownCount = newCount;
                this.unreadCount = newCount;
                this.lastPollTime = json.timestamp || new Date().toISOString();
                this.updateBadge();

                // If new items came in, prepend them to the panel
                if (json.notifications && json.notifications.length > 0) {
                    this.prependNewItems(json.notifications);
                }
            }
        } catch (e) {
            // Network error — apply backoff to prevent hammering
            this.consecutiveErrors++;
            if (this.consecutiveErrors >= 3) {
                this.handleRateLimit();
            }
        }
    },

    /**
     * Handle 429 rate-limit: exponential backoff, then resume normal polling.
     */
    handleRateLimit() {
        this.consecutiveErrors++;
        this.isBackingOff = true;

        // Exponential backoff: 120s, 240s, 480s... max 10 minutes
        const backoffMs = Math.min(this.POLL_BACKOFF * Math.pow(2, this.consecutiveErrors - 1), 600000);

        // Stop current polling timer
        if (this.timer) clearInterval(this.timer);

        // Resume after backoff period
        setTimeout(() => {
            this.isBackingOff = false;
            this.pollInterval = this.POLL_SLOW; // Resume at slow rate
            this.startPolling();
        }, backoffMs);
    },

    /**
     * Prepend newly arrived notifications to the panel without full re-render.
     */
    prependNewItems(newItems) {
        const list = document.getElementById('unList');
        if (!list) return;

        // Remove empty state if present
        const empty = list.querySelector('.un-empty');
        if (empty) empty.remove();

        // Get existing IDs to avoid duplicates
        const existingIds = new Set();
        list.querySelectorAll('.un-item').forEach(el => existingIds.add(el.dataset.id));

        const fragment = document.createDocumentFragment();
        newItems.forEach(n => {
            if (existingIds.has(String(n.id))) return;

            const tc = this.typeConfig[n.type] || this.typeConfig.system;
            const time = this.formatTime(n.created_at);
            const div = document.createElement('div');
            div.className = 'un-item';
            div.dataset.id = n.id;
            div.dataset.link = n.action_url || n.link || '';
            div.setAttribute('onclick', 'UserNotify.clickNotification(this)');
            div.innerHTML = `
                <div class="rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center"
                     style="background:${tc.bg};width:32px;height:32px;min-width:32px">
                    <i class="bi ${tc.icon}" style="font-size:.85rem;color:${tc.color}"></i>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold" style="font-size:.8rem;color:${tc.color}">
                        ${this.escHtml(n.title)}<span class="un-new-badge">ใหม่</span>
                    </div>
                    <div class="text-truncate" style="font-size:.75rem;color:#6b7280">${this.escHtml(n.message)}</div>
                </div>
                <div class="d-flex flex-column align-items-end gap-1 flex-shrink-0">
                    <span class="un-time">${time}</span>
                    <div class="un-dot" style="background:${tc.color}"></div>
                </div>`;
            // Highlight effect
            div.style.background = 'rgba(99,102,241,.06)';
            setTimeout(() => { div.style.background = ''; }, 3000);
            fragment.appendChild(div);
        });

        list.prepend(fragment);

        // Trim list to 15 items
        const allItems = list.querySelectorAll('.un-item');
        if (allItems.length > 15) {
            for (let i = 15; i < allItems.length; i++) allItems[i].remove();
        }
    },

    // ========== New notification handler ==========
    onNewNotifications(count) {
        this.playNotifySound();
        this.showDesktopNotification(count);
    },

    // ========== Desktop Notifications ==========
    showDesktopNotification(count) {
        if (!this.desktopEnabled) return;
        if (!('Notification' in window)) return;
        if (Notification.permission !== 'granted') return;
        if (!this.visibilityHidden) return; // Only show when tab is in background

        try {
            const notif = new Notification('การแจ้งเตือนใหม่', {
                body: `คุณมี ${count} การแจ้งเตือนใหม่`,
                icon: '/favicon.ico',
                tag: 'user-notify', // Replace previous notification
            });
            notif.onclick = () => {
                window.focus();
                notif.close();
            };
            // Auto-close after 5 seconds
            setTimeout(() => notif.close(), 5000);
        } catch (e) {}
    },

    toggleDesktop() {
        if (!('Notification' in window)) return;

        if (!this.desktopEnabled) {
            // Enable — request permission first
            Notification.requestPermission().then(perm => {
                if (perm === 'granted') {
                    this.desktopEnabled = true;
                    localStorage.setItem('user_desktop_notif', 'true');
                    this.updateDesktopIcon();
                }
            });
        } else {
            this.desktopEnabled = false;
            localStorage.setItem('user_desktop_notif', 'false');
            this.updateDesktopIcon();
        }
    },

    updateDesktopIcon() {
        const icon = document.querySelector('#unDesktopBtn i');
        if (icon) icon.className = `bi ${this.desktopEnabled ? 'bi-window-fullscreen' : 'bi-window'}`;
    },

    // ========== Sound ==========
    getAudioCtx() {
        if (!this.audioCtx) this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        if (this.audioCtx.state === 'suspended') this.audioCtx.resume();
        return this.audioCtx;
    },

    playNotifySound() {
        if (!this.soundEnabled) return;
        try {
            const ctx = this.getAudioCtx();
            const gain = ctx.createGain();
            gain.connect(ctx.destination);
            gain.gain.setValueAtTime(0.15, ctx.currentTime);

            const freqs = [523, 659, 784];
            const durs = [0.1, 0.1, 0.2];
            let t = ctx.currentTime;

            freqs.forEach((freq, i) => {
                const osc = ctx.createOscillator();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, t);
                osc.connect(gain);
                osc.start(t);
                const dur = durs[i];
                gain.gain.setValueAtTime(0.15, t);
                gain.gain.exponentialRampToValueAtTime(0.001, t + dur);
                osc.stop(t + dur + 0.01);
                t += dur + 0.02;
            });
        } catch (e) { console.warn('[UserNotify] Audio error:', e.message); }
    },

    toggleSound() {
        this.soundEnabled = !this.soundEnabled;
        localStorage.setItem('user_sound_enabled', JSON.stringify(this.soundEnabled));
        const icon = document.querySelector('#unSoundBtn i');
        if (icon) icon.className = `bi ${this.soundEnabled ? 'bi-volume-up-fill' : 'bi-volume-mute-fill'}`;
        if (this.soundEnabled) this.playNotifySound();
    },

    // ========== Helpers ==========
    formatTime(dateStr) {
        const d = new Date(dateStr);
        const now = new Date();
        const diff = Math.floor((now - d) / 1000);

        if (diff < 60) return 'ตอนนี้';
        if (diff < 3600) return Math.floor(diff / 60) + ' นาที';
        if (diff < 86400) return Math.floor(diff / 3600) + ' ชม.';
        if (diff < 604800) return Math.floor(diff / 86400) + ' วัน';
        return d.toLocaleDateString('th-TH', { day: 'numeric', month: 'short' });
    },

    escHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    },

    destroy() {
        if (this.timer) clearInterval(this.timer);
        if (this.idleCheckTimer) clearInterval(this.idleCheckTimer);
        if (this.audioCtx) this.audioCtx.close();
    }
};

// Auto-init
document.addEventListener('DOMContentLoaded', () => UserNotify.init());
