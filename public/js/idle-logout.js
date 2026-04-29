/**
 * Idle Auto-Logout System
 * ────────────────────────────────────────────────
 * Monitors user activity (mouse, keyboard, scroll, touch, click).
 * Shows a warning modal before auto-logout.
 * Used for Admin & Photographer panels (NOT customers).
 *
 * Usage:
 *   IdleLogout.init({
 *       timeout: 15,           // minutes of inactivity
 *       warning: 60,           // seconds before logout to show warning
 *       logoutUrl: '/admin/logout',
 *       csrfToken: '...',
 *       loginUrl: '/admin/login',
 *       roleName: 'Admin',
 *   });
 */
const IdleLogout = (() => {
    'use strict';

    let config = {
        timeout: 15,           // minutes
        warning: 60,           // seconds before logout
        logoutUrl: '',
        csrfToken: '',
        loginUrl: '',
        roleName: 'Admin',
        pingInterval: 30,      // seconds between server pings to keep session alive while active
    };

    let idleTimer = null;
    let warningTimer = null;
    let countdownInterval = null;
    let countdownSeconds = 0;
    let modalEl = null;
    let isWarningShown = false;
    let lastActivity = Date.now();

    /* ── Helpers ── */
    const minToMs = (m) => m * 60 * 1000;
    const secToMs = (s) => s * 1000;

    /* ── Activity Tracking ── */
    const EVENTS = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'click', 'wheel'];

    function onActivity() {
        lastActivity = Date.now();

        if (isWarningShown) {
            // User is back — dismiss warning and reset
            hideWarning();
        }

        resetIdleTimer();
    }

    // Throttle activity events (fire max once per 5 seconds)
    let activityThrottled = false;
    function onActivityThrottled(e) {
        if (activityThrottled) return;
        activityThrottled = true;
        onActivity();
        setTimeout(() => { activityThrottled = false; }, 5000);
    }

    /* ── Timers ── */
    function resetIdleTimer() {
        clearTimeout(idleTimer);
        clearTimeout(warningTimer);

        const warningAt = minToMs(config.timeout) - secToMs(config.warning);

        // Start warning timer (fires X seconds before logout)
        if (warningAt > 0) {
            warningTimer = setTimeout(showWarning, warningAt);
        }

        // Start logout timer
        idleTimer = setTimeout(doLogout, minToMs(config.timeout));
    }

    /* ── Warning Modal ── */
    function createModal() {
        // Don't create if already exists
        if (document.getElementById('idleLogoutModal')) return;

        const overlay = document.createElement('div');
        overlay.id = 'idleLogoutModal';
        overlay.innerHTML = `
            <div class="idle-modal-backdrop"></div>
            <div class="idle-modal-dialog">
                <div class="idle-modal-icon">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 16 14"></polyline>
                    </svg>
                </div>
                <h5 class="idle-modal-title">ไม่มีการใช้งาน</h5>
                <p class="idle-modal-text">
                    ระบบจะออกจากระบบอัตโนมัติใน <span id="idleCountdown" class="idle-countdown">60</span> วินาที
                </p>
                <p class="idle-modal-sub">เนื่องจากไม่มีการใช้งานเป็นเวลานาน เพื่อความปลอดภัยของข้อมูล</p>
                <div class="idle-modal-progress">
                    <div class="idle-modal-progress-bar" id="idleProgressBar"></div>
                </div>
                <div class="idle-modal-actions">
                    <button type="button" class="idle-btn-continue" id="idleContinueBtn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:6px;">
                            <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                            <polyline points="10 17 15 12 10 7"></polyline>
                            <line x1="15" y1="12" x2="3" y2="12"></line>
                        </svg>
                        ใช้งานต่อ
                    </button>
                    <button type="button" class="idle-btn-logout" id="idleLogoutBtn">
                        ออกจากระบบ
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(overlay);
        modalEl = overlay;

        // Attach events
        document.getElementById('idleContinueBtn').addEventListener('click', () => {
            hideWarning();
            resetIdleTimer();
        });
        document.getElementById('idleLogoutBtn').addEventListener('click', doLogout);
    }

    function showWarning() {
        if (isWarningShown) return;
        isWarningShown = true;
        countdownSeconds = config.warning;

        createModal();
        modalEl.classList.add('idle-show');
        updateCountdown();

        countdownInterval = setInterval(() => {
            countdownSeconds--;
            updateCountdown();
            if (countdownSeconds <= 0) {
                clearInterval(countdownInterval);
                doLogout();
            }
        }, 1000);
    }

    function hideWarning() {
        isWarningShown = false;
        clearInterval(countdownInterval);
        if (modalEl) {
            modalEl.classList.remove('idle-show');
        }
    }

    function updateCountdown() {
        const el = document.getElementById('idleCountdown');
        const bar = document.getElementById('idleProgressBar');
        if (el) el.textContent = countdownSeconds;
        if (bar) {
            const pct = (countdownSeconds / config.warning) * 100;
            bar.style.width = pct + '%';
            // Color transitions: green → yellow → red
            if (pct > 50) bar.style.background = 'linear-gradient(90deg, #10b981, #34d399)';
            else if (pct > 25) bar.style.background = 'linear-gradient(90deg, #f59e0b, #fbbf24)';
            else bar.style.background = 'linear-gradient(90deg, #ef4444, #f87171)';
        }
    }

    /* ── Logout ── */
    function doLogout() {
        clearTimeout(idleTimer);
        clearTimeout(warningTimer);
        clearInterval(countdownInterval);

        // Remove event listeners
        EVENTS.forEach(e => document.removeEventListener(e, onActivityThrottled, true));

        // POST logout via hidden form
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = config.logoutUrl;
        form.style.display = 'none';

        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '_token';
        csrf.value = config.csrfToken;
        form.appendChild(csrf);

        document.body.appendChild(form);
        form.submit();
    }

    /* ── Inject Styles ── */
    function injectStyles() {
        if (document.getElementById('idle-logout-styles')) return;
        const style = document.createElement('style');
        style.id = 'idle-logout-styles';
        style.textContent = `
            #idleLogoutModal {
                display: none;
                position: fixed;
                inset: 0;
                z-index: 99999;
                align-items: center;
                justify-content: center;
            }
            #idleLogoutModal.idle-show {
                display: flex;
                animation: idleFadeIn 0.3s ease;
            }
            .idle-modal-backdrop {
                position: absolute;
                inset: 0;
                background: rgba(0,0,0,0.5);
                backdrop-filter: blur(6px);
                -webkit-backdrop-filter: blur(6px);
            }
            .idle-modal-dialog {
                position: relative;
                background: #fff;
                border-radius: 20px;
                padding: 2.5rem 2rem 2rem;
                max-width: 400px;
                width: 90%;
                text-align: center;
                box-shadow: 0 25px 60px rgba(0,0,0,0.2);
                animation: idleSlideUp 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            }
            [data-bs-theme="dark"] .idle-modal-dialog {
                background: #1e293b;
                color: #f1f5f9;
            }
            .idle-modal-icon {
                margin-bottom: 1rem;
                animation: idlePulse 2s ease-in-out infinite;
            }
            .idle-modal-title {
                font-size: 1.25rem;
                font-weight: 700;
                margin-bottom: 0.5rem;
                letter-spacing: -0.02em;
            }
            .idle-modal-text {
                color: #64748b;
                font-size: 0.95rem;
                margin-bottom: 0.25rem;
            }
            [data-bs-theme="dark"] .idle-modal-text { color: #94a3b8; }
            .idle-modal-sub {
                color: #94a3b8;
                font-size: 0.8rem;
                margin-bottom: 1.25rem;
            }
            [data-bs-theme="dark"] .idle-modal-sub { color: #64748b; }
            .idle-countdown {
                display: inline-block;
                font-size: 1.5rem;
                font-weight: 800;
                color: #ef4444;
                min-width: 2ch;
                line-height: 1;
                vertical-align: baseline;
            }
            .idle-modal-progress {
                width: 100%;
                height: 6px;
                background: #f1f5f9;
                border-radius: 3px;
                overflow: hidden;
                margin-bottom: 1.5rem;
            }
            [data-bs-theme="dark"] .idle-modal-progress { background: #334155; }
            .idle-modal-progress-bar {
                height: 100%;
                width: 100%;
                border-radius: 3px;
                transition: width 1s linear, background 0.5s;
            }
            .idle-modal-actions {
                display: flex;
                gap: 0.75rem;
                justify-content: center;
            }
            .idle-btn-continue {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.65rem 1.8rem;
                background: linear-gradient(135deg, #6366f1, #4f46e5);
                color: #fff;
                border: none;
                border-radius: 12px;
                font-size: 0.9rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
            }
            .idle-btn-continue:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
            }
            .idle-btn-logout {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.65rem 1.4rem;
                background: rgba(239, 68, 68, 0.08);
                color: #ef4444;
                border: none;
                border-radius: 12px;
                font-size: 0.9rem;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
            }
            .idle-btn-logout:hover {
                background: rgba(239, 68, 68, 0.15);
            }
            @keyframes idleFadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            @keyframes idleSlideUp {
                from { opacity: 0; transform: translateY(20px) scale(0.97); }
                to { opacity: 1; transform: translateY(0) scale(1); }
            }
            @keyframes idlePulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.08); }
            }
        `;
        document.head.appendChild(style);
    }

    /* ── Public API ── */
    function init(opts = {}) {
        Object.assign(config, opts);

        // Must have logoutUrl
        if (!config.logoutUrl) {
            console.warn('[IdleLogout] No logoutUrl provided, idle-logout disabled.');
            return;
        }

        // Disabled if timeout <= 0
        if (config.timeout <= 0) {
            return;
        }

        injectStyles();

        // Bind activity events (capture phase for reliability)
        EVENTS.forEach(e => document.addEventListener(e, onActivityThrottled, true));

        // Also track visibility change (tab switching)
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                // User came back to tab — check if idle time exceeded
                const elapsed = Date.now() - lastActivity;
                if (elapsed >= minToMs(config.timeout)) {
                    doLogout();
                } else if (elapsed >= minToMs(config.timeout) - secToMs(config.warning)) {
                    // Within warning window
                    if (!isWarningShown) showWarning();
                }
            }
        });

        // Start the timer
        resetIdleTimer();
    }

    return { init };
})();
