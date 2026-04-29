/**
 * Admin Notification System
 * Persistent notifications with read/unread state, audio alerts & color-coded types
 */
const AdminNotify = {
    pollInterval: 15000,
    lastCheck: null,        // legacy time-based cursor (kept for back-compat with server)
    sinceId: 0,             // primary cursor — monotonic notification id, no timezone issues
    isFirstPoll: true,      // suppress toasts on the initial baseline poll
    shownToastIds: null,    // Set of notification ids we've already toasted (dedupe)
    maxVisibleToasts: 5,    // cap concurrent toasts to prevent flooding
    timer: null,
    audioCtx: null,
    enabled: true,
    soundEnabled: JSON.parse(localStorage.getItem('admin_sound_enabled') ?? 'true'),
    volume: parseFloat(localStorage.getItem('admin_sound_volume') ?? '0.5'),
    toastContainer: null,
    unreadCount: 0,
    baseUrl: '',
    apiBase: '',
    csrfToken: '',

    // Distinct color per notification type
    typeConfig: {
        order:           { icon: 'bi-bag-check-fill',      color: '#0d9488', bg: 'rgba(13,148,136,.1)',  label: 'ออเดอร์ใหม่' },
        user:            { icon: 'bi-person-plus-fill',    color: '#3b82f6', bg: 'rgba(59,130,246,.1)',  label: 'สมาชิกใหม่' },
        slip:            { icon: 'bi-receipt',             color: '#f59e0b', bg: 'rgba(245,158,11,.1)',  label: 'สลิปรอตรวจ' },
        photographer:    { icon: 'bi-camera-fill',         color: '#8b5cf6', bg: 'rgba(139,92,246,.1)',  label: 'ช่างภาพใหม่' },
        payment:         { icon: 'bi-check-circle-fill',   color: '#22c55e', bg: 'rgba(34,197,94,.1)',   label: 'ชำระเงินสำเร็จ' },
        // ─── Digital products ───
        digital_order:   { icon: 'bi-box-seam-fill',       color: '#6366f1', bg: 'rgba(99,102,241,.12)', label: 'สินค้าดิจิทัล' },
        digital_slip:    { icon: 'bi-cloud-upload-fill',   color: '#d946ef', bg: 'rgba(217,70,239,.12)', label: 'สลิปสินค้าดิจิทัล' },
        order_approved:  { icon: 'bi-patch-check-fill',    color: '#22c55e', bg: 'rgba(34,197,94,.1)',   label: 'อนุมัติแล้ว' },
        order_rejected:  { icon: 'bi-x-circle-fill',       color: '#ef4444', bg: 'rgba(239,68,68,.1)',   label: 'ถูกปฏิเสธ' },
        download_ready:  { icon: 'bi-cloud-download-fill', color: '#10b981', bg: 'rgba(16,185,129,.1)',  label: 'พร้อมดาวน์โหลด' },
    },

    init() {
        this.baseUrl   = (document.querySelector('meta[name="base-url"]')?.content || '').replace(/\/$/, '');
        this.apiBase   = this.baseUrl + '/admin/notifications/api';
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        // Init cursor state. We keep an empty timestamp + zero id —
        // first poll will go without a cursor, server returns the
        // baseline `latest_id` which we then use as our cursor going
        // forward. This avoids the old bug where `new Date().toISOString()`
        // produced a UTC string that the server mis-parsed as local
        // time and returned every notification from the past 7 hours.
        this.lastCheck = '';
        this.sinceId   = 0;
        this.isFirstPoll = true;
        this.shownToastIds = new Set();
        // Restore previously-toasted ids from sessionStorage so a
        // single-page navigation (admin clicking from /orders to /users
        // within the same tab) doesn't re-toast the same items. The
        // Set is reset on a full reload, which is correct — a fresh
        // session intentionally shows nothing on baseline.
        try {
            const stored = sessionStorage.getItem('admin_toasted_ids');
            if (stored) {
                JSON.parse(stored).forEach(id => this.shownToastIds.add(id));
            }
        } catch (_) {}
        this.injectStyles();
        this.createToastContainer();
        this.attachBell();
        this.syncSoundUI();
        this.loadNotifications();
        this.poll();
        // Polling is gated on document.hidden so backgrounded tabs don't
        // continue firing API requests every 15s. With 3 admins × 3 tabs
        // each that previously meant 12 requests/min even when nobody
        // was looking. We still poll once on visibilitychange→visible
        // (see below) so a tab that's been hidden for hours catches up
        // immediately when re-focused.
        this.timer = setInterval(() => {
            if (!document.hidden) this.poll();
        }, this.pollInterval);
        this.updateStats();
        this.statsTimer = setInterval(() => {
            if (!document.hidden) this.updateStats();
        }, 30000);

        // Realtime refresh triggers
        // 1) Tab regains focus → refresh immediately (covers multi-tab admin work)
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) this.refresh();
        });
        window.addEventListener('focus', () => this.refresh());

        // 2) Global event: any page can dispatch this after an action
        //    window.dispatchEvent(new CustomEvent('admin-notify:refresh'))
        //    window.dispatchEvent(new CustomEvent('admin-notify:ref-handled', {detail:{types:[...], refId:'12'}}))
        window.addEventListener('admin-notify:refresh', () => this.refresh());
        window.addEventListener('admin-notify:ref-handled', (e) => {
            const d = e.detail || {};
            this.markByRef(d.types || [], d.refId);
        });

        // 3) Intercept admin form submissions that act on orders/slips → refresh after
        document.addEventListener('submit', (e) => {
            const f = e.target;
            if (!(f instanceof HTMLFormElement)) return;
            const action = f.action || '';
            if (/\/admin\/(digital-orders|orders|payments|slips)\//i.test(action)) {
                // Defer so the server-side dismiss has time to commit
                setTimeout(() => this.refresh(), 800);
            }
        }, true);
    },

    // Force an immediate poll + panel refresh
    refresh() {
        this.loadNotifications();
    },

    // Mark-by-reference: dismiss all unread notifs matching types + optional refId
    async markByRef(types, refId) {
        if ((!types || !types.length) && !refId) return;
        try {
            const resp = await fetch(`${this.apiBase}/mark-by-ref`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ types: types || [], ref_id: refId ?? null }),
            });
            if (!resp.ok) return;
            const json = await resp.json();
            if (json.success) {
                this.unreadCount = json.unread_count ?? 0;
                this.updateBadge();
                this.loadNotifications();
            }
        } catch (e) {}
    },

    injectStyles() {
        if (document.getElementById('admin-notify-styles')) return;
        const s = document.createElement('style');
        s.id = 'admin-notify-styles';
        s.textContent = `
            /* ── Toast (right-corner alerts) ────────────────────────── */
            .admin-notify-toast{animation:ntfSlideIn .3s ease}
            .admin-notify-toast.animate-slide-out{animation:ntfSlideOut .3s ease forwards}
            @keyframes ntfSlideIn{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}
            @keyframes ntfSlideOut{from{opacity:1;transform:translateX(0)}to{opacity:0;transform:translateX(40px)}}

            /* ── Notification dropdown list (id="notifyList") ─────────────────────
               New design: modern card layout with gradient accent,
               left-border highlight for unread, type pill, smooth hover.
               Theme-aligned with admin indigo/violet palette + dark mode. */

            #notifyList{ scrollbar-width:thin; scrollbar-color:rgba(99,102,241,.25) transparent; }
            #notifyList::-webkit-scrollbar{ width:6px; }
            #notifyList::-webkit-scrollbar-thumb{ background:rgba(99,102,241,.25); border-radius:3px; }
            #notifyList::-webkit-scrollbar-thumb:hover{ background:rgba(99,102,241,.45); }

            .notify-item{
                position:relative;
                display:flex; gap:.75rem;
                padding:.875rem 1.25rem .875rem 1rem;
                border-bottom:1px solid rgba(15,23,42,.05);
                cursor:pointer;
                transition:background .2s ease, transform .2s ease, padding-left .2s ease;
                animation:ntfItemIn .25s ease backwards;
            }
            .dark .notify-item{ border-bottom-color:rgba(255,255,255,.05); }
            .notify-item:last-child{ border-bottom:none; }

            @keyframes ntfItemIn{ from{opacity:0;transform:translateY(4px)} to{opacity:1;transform:translateY(0)} }

            /* Hover state — gentle gradient wash with type accent */
            .notify-item:hover{
                background:linear-gradient(90deg, var(--accent-bg, rgba(99,102,241,.06)) 0%, transparent 70%);
            }
            .notify-item:hover .notify-item__chevron{ opacity:1; transform:translate(0,-50%); }
            .notify-item:hover .notify-item__icon{ transform:scale(1.06) rotate(-3deg); }

            /* Unread state — left accent bar + tinted bg + colored title */
            .notify-item--unread{
                background:linear-gradient(90deg, var(--accent-bg, rgba(99,102,241,.06)) 0%, transparent 75%);
            }
            .notify-item--unread::before{
                content:'';
                position:absolute; left:0; top:.625rem; bottom:.625rem;
                width:3px;
                background:var(--accent-color,#6366f1);
                border-radius:0 4px 4px 0;
                box-shadow:0 0 8px var(--accent-color,#6366f1);
            }

            /* Read state — muted */
            .notify-item--read{ opacity:.62; }
            .notify-item--read:hover{ opacity:.92; }

            /* Icon container */
            .notify-item__icon{
                flex-shrink:0;
                width:38px; height:38px;
                border-radius:11px;
                display:flex; align-items:center; justify-content:center;
                background:var(--accent-bg, rgba(99,102,241,.08));
                color:var(--accent-color,#6366f1);
                font-size:1rem;
                position:relative;
                transition:transform .25s cubic-bezier(.34,1.56,.64,1);
                margin-top:1px;
            }
            .notify-item__icon::after{
                content:''; position:absolute; inset:0; border-radius:11px;
                background:linear-gradient(135deg, rgba(255,255,255,.3) 0%, transparent 50%);
                pointer-events:none;
            }
            .dark .notify-item__icon::after{
                background:linear-gradient(135deg, rgba(255,255,255,.08) 0%, transparent 50%);
            }

            /* Body */
            .notify-item__body{ flex:1; min-width:0; }
            .notify-item__meta{
                display:flex; align-items:center; gap:.5rem;
                margin-bottom:.125rem; flex-wrap:wrap;
            }
            .notify-item__pill{
                display:inline-flex; align-items:center;
                padding:1px 8px; border-radius:999px;
                font-size:10px; font-weight:600; letter-spacing:.02em;
                background:var(--accent-bg, rgba(99,102,241,.1));
                color:var(--accent-color,#6366f1);
                line-height:1.4;
            }
            .notify-item__time{
                font-size:10.5px; color:#94a3b8; font-weight:500;
                margin-left:auto; white-space:nowrap;
            }
            .dark .notify-item__time{ color:#64748b; }

            .notify-item__title{
                margin:0; font-size:13px; font-weight:600;
                color:#1e293b; line-height:1.35;
                overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
            }
            .dark .notify-item__title{ color:#f1f5f9; }
            .notify-item--unread .notify-item__title{
                color:var(--accent-color,#6366f1); font-weight:700;
            }

            .notify-item__message{
                margin:2px 0 0; font-size:12px;
                color:#6b7280; line-height:1.45;
                display:-webkit-box;
                -webkit-line-clamp:2; -webkit-box-orient:vertical;
                overflow:hidden;
            }
            .dark .notify-item__message{ color:#94a3b8; }

            /* Chevron — slides in on hover */
            .notify-item__chevron{
                position:absolute;
                right:.875rem; top:50%;
                transform:translate(6px,-50%);
                opacity:0;
                transition:opacity .18s ease, transform .18s ease;
                color:var(--accent-color,#9ca3af);
                font-size:.7rem;
                pointer-events:none;
            }

            /* Empty state — redesigned with gradient blob */
            .notify-empty{
                text-align:center; padding:3rem 1.5rem;
                animation:ntfItemIn .35s ease;
            }
            .notify-empty__icon{
                display:inline-flex; width:60px; height:60px;
                border-radius:18px;
                background:linear-gradient(135deg, rgba(99,102,241,.1), rgba(139,92,246,.12));
                color:#6366f1;
                align-items:center; justify-content:center;
                font-size:1.5rem;
                margin:0 auto 1rem;
                box-shadow:0 8px 20px -8px rgba(99,102,241,.25);
            }
            .dark .notify-empty__icon{
                background:linear-gradient(135deg, rgba(99,102,241,.18), rgba(139,92,246,.22));
                color:#a78bfa;
                box-shadow:0 8px 20px -8px rgba(0,0,0,.4);
            }
            .notify-empty__title{
                font-size:13.5px; font-weight:600; color:#475569;
                margin:0 0 4px;
            }
            .dark .notify-empty__title{ color:#cbd5e1; }
            .notify-empty__hint{
                font-size:11.5px; color:#94a3b8; margin:0;
            }
            .dark .notify-empty__hint{ color:#64748b; }

            /* ── Bell button states (unchanged but kept) ───────────── */
            @keyframes bellRing{0%,100%{transform:rotate(0)}15%{transform:rotate(14deg)}30%{transform:rotate(-12deg)}45%{transform:rotate(8deg)}60%{transform:rotate(-6deg)}75%{transform:rotate(3deg)}}
            .bell-ring i{animation:bellRing .7s ease}
            @keyframes bellPulse{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.55)}50%{box-shadow:0 0 0 6px rgba(239,68,68,0)}}
            @keyframes badgePulse{0%,100%{transform:scale(1)}50%{transform:scale(1.15)}}
            #adminNotifyBell.bell-has-unread{
                background:linear-gradient(135deg,#fef3c7,#fee2e2)!important;
                color:#dc2626!important;
                animation:bellPulse 2s infinite;
            }
            .dark #adminNotifyBell.bell-has-unread{
                background:linear-gradient(135deg,rgba(245,158,11,.18),rgba(239,68,68,.25))!important;
                color:#fca5a5!important;
            }
            .notify-badge-active{
                background:linear-gradient(135deg,#ef4444,#dc2626)!important;
                box-shadow:0 2px 6px rgba(239,68,68,.5);
                animation:badgePulse 1.5s ease-in-out infinite;
                border:2px solid #fff;
            }
            .dark .notify-badge-active{border-color:#1e293b}

            /* Header label color tweak for dark */
            .notify-mark-read-btn{font-size:11px;color:#6366f1;cursor:pointer;border:none;background:none;padding:2px 6px;border-radius:4px;transition:background .15s}
            .notify-mark-read-btn:hover{background:rgba(99,102,241,.08)}
        `;
        document.head.appendChild(s);
    },

    // ========== Audio ==========
    getAudioCtx() {
        if (!this.audioCtx) this.audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        if (this.audioCtx.state === 'suspended') this.audioCtx.resume();
        return this.audioCtx;
    },

    playTone(frequencies, durations, type = 'sine') {
        if (!this.soundEnabled) return;
        try {
            const ctx = this.getAudioCtx();
            const gain = ctx.createGain();
            gain.connect(ctx.destination);
            gain.gain.setValueAtTime(this.volume * 0.3, ctx.currentTime);
            let t = ctx.currentTime;
            frequencies.forEach((freq, i) => {
                const osc = ctx.createOscillator();
                osc.type = type;
                osc.frequency.setValueAtTime(freq, t);
                osc.connect(gain);
                osc.start(t);
                const dur = durations[i] || 0.15;
                gain.gain.setValueAtTime(this.volume * 0.3, t);
                gain.gain.exponentialRampToValueAtTime(0.001, t + dur);
                osc.stop(t + dur + 0.01);
                t += dur + 0.03;
            });
        } catch (e) { console.warn('[AdminNotify] Audio error:', e.message); }
    },

    sounds: {
        order()           { AdminNotify.playTone([523, 659, 784, 1047], [0.1, 0.1, 0.1, 0.25], 'sine'); },
        user()            { AdminNotify.playTone([440, 554], [0.15, 0.2], 'triangle'); },
        slip()            { AdminNotify.playTone([880, 660, 880], [0.1, 0.08, 0.15], 'square'); },
        photographer()    { AdminNotify.playTone([392, 494, 587], [0.12, 0.12, 0.2], 'sine'); },
        payment()         { AdminNotify.playTone([523, 659, 784, 1047, 1319], [0.08, 0.08, 0.08, 0.08, 0.3], 'sine'); },
        digital_order()   { AdminNotify.playTone([659, 880, 1047], [0.1, 0.1, 0.25], 'sine'); },
        digital_slip()    { AdminNotify.playTone([784, 988, 784], [0.1, 0.08, 0.2], 'triangle'); },
        order_approved()  { AdminNotify.playTone([659, 784, 988], [0.1, 0.1, 0.2], 'sine'); },
        order_rejected()  { AdminNotify.playTone([330, 277], [0.2, 0.3], 'sawtooth'); },
        download_ready()  { AdminNotify.playTone([784, 1047, 1319], [0.08, 0.08, 0.25], 'sine'); },
    },

    // ========== Toast ==========
    createToastContainer() {
        this.toastContainer = document.createElement('div');
        this.toastContainer.id = 'admin-toast-stack';
        this.toastContainer.style.cssText = 'position:fixed;top:70px;right:20px;z-index:10000;display:flex;flex-direction:column;gap:8px;max-width:380px;pointer-events:none;';
        document.body.appendChild(this.toastContainer);
    },

    showToast(title, message, type, link, notificationId) {
        const tc = this.typeConfig[type] || this.typeConfig.order;

        // Belt-and-braces dedupe at the DOM level: if a toast for this
        // notification id is already on screen, don't stack a second
        // copy. The Set check in poll() handles the common case, but
        // showToast may be called from other paths in future.
        if (notificationId && this.toastContainer
            && this.toastContainer.querySelector(`[data-notif-id="${notificationId}"]`)) {
            return;
        }

        // Cap concurrent toasts — drop the oldest when at limit so the
        // latest-and-most-relevant always wins screen space.
        if (this.toastContainer) {
            while (this.toastContainer.children.length >= this.maxVisibleToasts) {
                this.toastContainer.firstElementChild?.remove();
            }
        }

        const toast = document.createElement('div');
        toast.className = 'admin-notify-toast';
        toast.style.pointerEvents = 'auto';
        if (notificationId) toast.dataset.notifId = notificationId;
        toast.innerHTML = `
            <div class="d-flex align-items-start gap-2 p-3 bg-white rounded-3 shadow-lg" style="min-width:300px;border-left:4px solid ${tc.color}">
                <div class="rounded-circle p-2 flex-shrink-0 d-flex align-items-center justify-content-center" style="background:${tc.bg};width:36px;height:36px">
                    <i class="bi ${tc.icon}" style="font-size:1.1rem;color:${tc.color}"></i>
                </div>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-bold small text-dark">${this.escHtml(title)}</div>
                    <div class="text-muted" style="font-size:.8rem">${this.escHtml(message)}</div>
                </div>
                <button class="btn-close btn-close-sm flex-shrink-0" style="font-size:.6rem" onclick="this.closest('.admin-notify-toast').remove()"></button>
            </div>`;
        if (link && this.isSafeRelativePath(link)) {
            toast.style.cursor = 'pointer';
            toast.addEventListener('click', e => {
                if (!e.target.closest('.btn-close')) {
                    // Mark the notification as read on click — same
                    // behaviour as clicking from the bell dropdown.
                    if (notificationId) {
                        this.markOneRead(notificationId);
                    }
                    window.location.href = this.baseUrl + '/' + link;
                }
            });
        }
        this.toastContainer.appendChild(toast);
        setTimeout(() => { toast.classList.add('animate-slide-out'); setTimeout(() => toast.remove(), 300); }, 8000);
    },

    // Best-effort POST to mark a single notification read. Used when
    // user clicks a toast — fire and forget; bell counter will reconcile
    // on the next poll regardless.
    async markOneRead(id) {
        try {
            await fetch(`${this.apiBase}/${id}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
            });
            this.unreadCount = Math.max(0, this.unreadCount - 1);
            this.updateBadge();
        } catch (_) {}
    },

    // ========== Bell Icon (attaches to admin topbar) ==========
    attachBell() {
        const target = document.getElementById('adminNotifyBell');
        if (!target) return;

        // Refresh list when bell is clicked (Alpine dropdown)
        target.addEventListener('click', () => {
            this.loadNotifications();
            // Unlock audio context on first user interaction (autoplay policy)
            try { this.getAudioCtx(); } catch (_) {}
        });

        // Also listen for legacy Bootstrap dropdown if present
        const dd = target.closest('.dropdown');
        dd?.addEventListener('show.bs.dropdown', () => this.loadNotifications());
    },

    // ========== Load persisted notifications ==========
    async loadNotifications() {
        try {
            const resp = await fetch(`${this.apiBase}`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                credentials: 'same-origin',
            });
            if (!resp.ok) return;
            const json = await resp.json();
            if (!json.success) return;

            this.unreadCount = json.unread_count;
            this.updateBadge();
            this.renderPanel(json.notifications);
        } catch (e) {}
    },

    renderPanel(items) {
        const list = document.getElementById('notifyList');
        if (!list) return;

        if (!items || items.length === 0) {
            list.innerHTML = `
                <div class="notify-empty">
                    <div class="notify-empty__icon">
                        <i class="bi bi-bell-slash"></i>
                    </div>
                    <p class="notify-empty__title">ไม่มีการแจ้งเตือน</p>
                    <p class="notify-empty__hint">เมื่อมีกิจกรรมใหม่ในระบบ จะแสดงที่นี่</p>
                </div>`;
            return;
        }

        list.innerHTML = items.map((n, idx) => {
            const tc = this.typeConfig[n.type] || this.typeConfig.order;
            const isRead = n.is_read == 1;
            const stateClass = isRead ? 'notify-item--read' : 'notify-item--unread';
            const time = this.formatTime(n.created_at);
            // Stagger animation for visual polish (max 6 items so list renders fast)
            const delay = Math.min(idx, 6) * 30;
            return `
                <div class="notify-item ${stateClass}"
                     data-id="${n.id}" data-link="${n.link || ''}"
                     style="--accent-color:${tc.color};--accent-bg:${tc.bg};animation-delay:${delay}ms"
                     onclick="AdminNotify.clickNotification(this)">
                    <div class="notify-item__icon">
                        <i class="bi ${tc.icon}"></i>
                    </div>
                    <div class="notify-item__body">
                        <div class="notify-item__meta">
                            <span class="notify-item__pill">${this.escHtml(tc.label)}</span>
                            <span class="notify-item__time">${time}</span>
                        </div>
                        <h4 class="notify-item__title">${this.escHtml(n.title)}</h4>
                        ${n.message ? `<p class="notify-item__message">${this.escHtml(n.message)}</p>` : ''}
                    </div>
                    <i class="bi bi-chevron-right notify-item__chevron"></i>
                </div>`;
        }).join('');
    },

    // ========== Click notification → mark read + navigate ==========
    async clickNotification(el) {
        const id = el.dataset.id;
        const link = el.dataset.link;

        if (el.classList.contains('notify-item--unread')) {
            el.classList.remove('notify-item--unread');
            el.classList.add('notify-item--read');

            try {
                await fetch(`${this.apiBase}/${id}/read`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });
                this.unreadCount = Math.max(0, this.unreadCount - 1);
                this.updateBadge();
            } catch (e) {}
        }

        // Defence in depth: server-side already sanitises `link` (see
        // AdminNotification::sanitiseLink), but if any historical row
        // contains a protocol-relative or absolute URL we refuse to
        // navigate to it. This prevents a notification row from
        // redirecting admins off-site.
        if (link && this.isSafeRelativePath(link)) {
            window.location.href = `${this.baseUrl}/${link}`;
        }
    },

    // Reject:
    //   - protocol-relative `//evil.com/x`
    //   - absolute URLs `http://...` `https://...`
    //   - schemes like `javascript:`, `data:`, `vbscript:`
    // Accept any other string — we already strip leading slashes.
    isSafeRelativePath(s) {
        if (typeof s !== 'string' || !s) return false;
        const trimmed = s.replace(/^\/+/, '');
        // Block scheme-prefixed paths.
        if (/^[a-z][a-z0-9+.\-]*:/i.test(trimmed)) return false;
        // Block protocol-relative even after leading-slash strip
        // (in case it was `//evil.com/x` originally).
        if (s.startsWith('//')) return false;
        return true;
    },

    // ========== Mark all read ==========
    async markAllRead() {
        try {
            await fetch(`${this.apiBase}/read-all`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': this.csrfToken, 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            this.unreadCount = 0;
            this.updateBadge();
            document.querySelectorAll('.notify-item').forEach(item => {
                item.classList.remove('notify-item--unread');
                item.classList.add('notify-item--read');
            });
        } catch (e) {}
    },

    // ========== Badge ==========
    updateBadge() {
        const badge = document.getElementById('notifyBadge');
        const label = document.getElementById('notifyCountLabel');
        const markBtn = document.getElementById('markAllReadBtn');
        const bell = document.getElementById('adminNotifyBell');

        if (badge) {
            if (this.unreadCount > 0) {
                badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
                badge.classList.remove('hidden', 'd-none');
                badge.classList.add('notify-badge-active');
            } else {
                badge.classList.add('hidden');
                badge.classList.remove('notify-badge-active');
            }
        }

        // Bell: switch color + pulse when there are unread
        if (bell) {
            if (this.unreadCount > 0) {
                bell.classList.add('bell-has-unread');
            } else {
                bell.classList.remove('bell-has-unread');
            }
        }

        if (label)   label.textContent = this.unreadCount > 0 ? `(${this.unreadCount} ใหม่)` : '';
        if (markBtn) {
            if (this.unreadCount > 0) markBtn.classList.remove('hidden', 'd-none');
            else                      markBtn.classList.add('hidden');
        }
    },

    // ========== Polling ==========
    async poll() {
        try {
            // Build cursor URL. After the first poll we have a sinceId
            // (the highest notification id we've seen). Before that,
            // request without any cursor — server returns latest_id
            // baseline + no new_items (so we don't toast historical
            // notifications when admin opens the page).
            let url = this.apiBase;
            if (this.sinceId > 0) {
                url += `?since_id=${this.sinceId}`;
            }

            const resp = await fetch(url, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                credentials: 'same-origin',
            });
            if (!resp.ok) return;
            const json = await resp.json();
            if (!json.success) return;

            this.lastCheck = json.timestamp || this.lastCheck;
            this.unreadCount = json.unread_count;
            this.updateBadge();

            // Advance the cursor to the latest known id BEFORE we
            // process new items. Even if showToast throws below, the
            // cursor moves forward so we don't keep re-toasting the
            // same items on every poll.
            const latestId = parseInt(json.latest_id || 0, 10) || 0;
            if (latestId > this.sinceId) {
                this.sinceId = latestId;
            }

            // Suppress toasts on the very first poll (baseline) —
            // the user just opened/refreshed the page, they don't
            // need a flood of "new" toasts for already-existing
            // notifications.
            if (this.isFirstPoll) {
                this.isFirstPoll = false;
                return;
            }

            if (json.new_items?.length) {
                const bell = document.getElementById('adminNotifyBell');
                if (bell) { bell.classList.add('bell-ring'); setTimeout(() => bell.classList.remove('bell-ring'), 700); }

                // Filter: only toast items that are
                //   1. unread (already-clicked notifications shouldn't pop again), AND
                //   2. not already toasted in this session (dedupe)
                // Cap to maxVisibleToasts so a sudden burst of 20 events
                // doesn't whitewash the screen.
                const fresh = json.new_items
                    .filter(item => Number(item.is_read) !== 1)
                    .filter(item => !this.shownToastIds.has(item.id))
                    .slice(0, this.maxVisibleToasts);

                fresh.forEach(item => {
                    this.shownToastIds.add(item.id);
                    const t = item.type;
                    if (this.sounds[t]) this.sounds[t]();
                    this.showToast(item.title, item.message || '', t, item.link || '', item.id);
                });

                // Persist the dedupe set across SPA-like navigations.
                // Cap at 200 entries (FIFO drop) so it doesn't grow
                // unbounded over a long admin session.
                if (this.shownToastIds.size > 200) {
                    const arr = Array.from(this.shownToastIds);
                    this.shownToastIds = new Set(arr.slice(-200));
                }
                try {
                    sessionStorage.setItem(
                        'admin_toasted_ids',
                        JSON.stringify(Array.from(this.shownToastIds))
                    );
                } catch (_) {}

                if (fresh.length > 0) {
                    this.loadNotifications();
                }
            }
        } catch (e) {}
    },

    // ========== Sound controls ==========
    toggleSound() {
        this.soundEnabled = !this.soundEnabled;
        localStorage.setItem('admin_sound_enabled', JSON.stringify(this.soundEnabled));
        const btn = document.getElementById('soundToggleBtn');
        const icon = btn?.querySelector('i');
        if (icon) {
            icon.className = `bi text-sm ${this.soundEnabled ? 'bi-volume-up-fill' : 'bi-volume-mute-fill'}`;
        }
        if (btn) {
            btn.style.color = this.soundEnabled ? '#6366f1' : '#9ca3af';
            btn.title = this.soundEnabled ? 'ปิดเสียง' : 'เปิดเสียง';
        }
        if (this.soundEnabled) this.sounds.order();
    },

    // Reflect sound state on initial load
    syncSoundUI() {
        const btn = document.getElementById('soundToggleBtn');
        const icon = btn?.querySelector('i');
        if (icon) icon.className = `bi text-sm ${this.soundEnabled ? 'bi-volume-up-fill' : 'bi-volume-mute-fill'}`;
        if (btn) {
            btn.style.color = this.soundEnabled ? '#6366f1' : '#9ca3af';
            btn.title = this.soundEnabled ? 'ปิดเสียง' : 'เปิดเสียง';
        }
    },

    // ========== Stats ==========
    async updateStats() {
        try {
            const resp = await fetch(`${this.apiBase}/stats`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': this.csrfToken },
                credentials: 'same-origin',
            });
            if (!resp.ok) return;
            const json = await resp.json();
            if (!json.success) return;
            const s = json.stats;
            document.querySelectorAll('[data-live-stat]').forEach(el => {
                const key = el.dataset.liveStat;
                if (s[key] !== undefined) {
                    el.textContent = key === 'today_revenue' ? '฿' + parseFloat(s[key]).toLocaleString() : s[key];
                }
            });
        } catch (e) {}
    },

    // ========== Helpers ==========
    formatTime(dateStr) {
        const d = new Date(dateStr);
        const now = new Date();
        const diff = Math.floor((now - d) / 1000);
        if (diff < 60) return 'ตอนนี้';
        if (diff < 3600) return Math.floor(diff / 60) + ' น.';
        if (diff < 86400) return Math.floor(diff / 3600) + ' ชม.';
        if (diff < 604800) return Math.floor(diff / 86400) + ' วัน';
        return d.toLocaleDateString('th-TH', { day: 'numeric', month: 'short' });
    },

    escHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    },

    destroy() {
        if (this.timer) clearInterval(this.timer);
        if (this.statsTimer) clearInterval(this.statsTimer);
        if (this.audioCtx) this.audioCtx.close();
    }
};

// Auto-init
document.addEventListener('DOMContentLoaded', () => AdminNotify.init());
