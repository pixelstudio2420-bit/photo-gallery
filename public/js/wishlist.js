/**
 * Wishlist Module — Professional Favorites System
 * Handles toggle, badge updates, card overlays, and animations
 */
(function () {
    'use strict';

    const baseUrl = document.querySelector('meta[name="base-url"]')?.content || '';
    const API_URL = baseUrl + '/api/wishlist';

    // CSRF token (set by footer script)
    function getCsrf() {
        // Try multiple sources
        const meta = document.querySelector('meta[name="csrf-token"]')?.content;
        if (meta) return meta;
        const input = document.querySelector('input[name="csrf_token"]');
        if (input) return input.value;
        return window.__csrf || '';
    }

    // ==========================================
    // Badge: update navbar wishlist count
    // ==========================================
    function updateBadge(count) {
        const badge = document.getElementById('wishlistBadge');
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
    }

    function loadBadgeCount() {
        fetch(API_URL + '?action=count', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => { if (res.success) updateBadge(res.count); })
            .catch(() => {});
    }

    // ==========================================
    // Toggle: add/remove event from wishlist
    // ==========================================
    function toggle(eventId, btn, opts) {
        opts = opts || {};
        if (btn) btn.disabled = true;

        const body = new URLSearchParams();
        body.append('event_id', eventId);

        return fetch(API_URL + '/toggle', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': getCsrf()
            },
            body: body.toString(),
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(res => {
            if (btn) btn.disabled = false;
            if (!res.success) return res;

            // Update all heart buttons for this event
            document.querySelectorAll('[data-wishlist-id="' + eventId + '"]').forEach(el => {
                const icon = el.querySelector('i');
                if (res.in_wishlist) {
                    el.classList.add('active', 'wishlisted');
                    if (icon) icon.className = 'bi bi-heart-fill';
                } else {
                    el.classList.remove('active', 'wishlisted');
                    if (icon) icon.className = 'bi bi-heart';
                }
            });

            // Also update by id (event view page)
            const viewIcon = document.getElementById('wishlist-icon');
            const viewBtn = document.getElementById('wishlist-btn');
            if (viewIcon && viewBtn) {
                if (res.in_wishlist) {
                    viewIcon.className = 'bi bi-heart-fill';
                    viewBtn.classList.add('active');
                } else {
                    viewIcon.className = 'bi bi-heart';
                    viewBtn.classList.remove('active');
                }
            }

            updateBadge(res.count);

            // Toast feedback
            if (typeof Swal !== 'undefined' && opts.toast !== false) {
                Swal.fire({
                    toast: true, position: 'top-end', timer: 2000,
                    timerProgressBar: true, showConfirmButton: false,
                    icon: 'success',
                    title: res.in_wishlist ? 'เพิ่มในรายการโปรดแล้ว' : 'ลบออกจากรายการโปรดแล้ว'
                });
            }

            return res;
        })
        .catch((err) => {
            console.warn('[Wishlist] Error:', err.message);
            if (btn) btn.disabled = false;
            if (typeof Swal !== 'undefined') {
                Swal.fire({ toast: true, position: 'top-end', timer: 3000, showConfirmButton: false, icon: 'error', title: 'ไม่สามารถอัปเดตรายการโปรดได้' });
            }
            return { success: false };
        });
    }

    // ==========================================
    // Check: is event in wishlist?
    // ==========================================
    function check(eventId) {
        return fetch(API_URL + '?action=check&event_id=' + eventId, { credentials: 'same-origin' })
            .then(r => r.json())
            .catch(() => ({ success: false }));
    }

    // ==========================================
    // Bulk check: check multiple events at once
    // ==========================================
    function bulkCheck(eventIds) {
        if (!eventIds.length) return Promise.resolve({});
        return fetch(API_URL + '?action=list', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => {
                if (!res.success) return {};
                const map = {};
                (res.data || []).forEach(item => { map[item.event_id] = true; });
                return map;
            })
            .catch(() => ({}));
    }

    // ==========================================
    // Init card overlays: attach heart buttons
    // ==========================================
    function initCardOverlays() {
        const btns = document.querySelectorAll('.wishlist-heart-btn');
        if (!btns.length) return;

        // Collect event IDs for bulk check
        const ids = [];
        btns.forEach(btn => {
            const eid = btn.dataset.wishlistId;
            if (eid) ids.push(parseInt(eid));

            btn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                const eventId = this.dataset.wishlistId;

                // Quick visual toggle
                const icon = this.querySelector('i');
                const isActive = this.classList.contains('wishlisted');
                if (isActive) {
                    this.classList.remove('wishlisted');
                    if (icon) icon.className = 'bi bi-heart';
                } else {
                    this.classList.add('wishlisted');
                    if (icon) icon.className = 'bi bi-heart-fill';
                    // Pulse animation
                    this.style.transform = 'scale(1.3)';
                    setTimeout(() => { this.style.transform = ''; }, 200);
                }

                toggle(eventId, null, { toast: true });
            });
        });

        // Bulk check status
        if (ids.length > 0) {
            bulkCheck(ids).then(map => {
                btns.forEach(btn => {
                    const eid = btn.dataset.wishlistId;
                    if (map[eid]) {
                        btn.classList.add('wishlisted');
                        const icon = btn.querySelector('i');
                        if (icon) icon.className = 'bi bi-heart-fill';
                    }
                });
            });
        }
    }

    // ==========================================
    // Remove: remove with animation
    // ==========================================
    function remove(eventId, cardEl) {
        if (cardEl) {
            cardEl.style.transition = 'transform 0.3s, opacity 0.3s';
            cardEl.style.transform = 'scale(0.9)';
            cardEl.style.opacity = '0';
        }

        const body = new URLSearchParams();
        body.append('event_id', eventId);

        return fetch(API_URL + '/toggle', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': getCsrf()
            },
            body: body.toString(),
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                updateBadge(res.count);
                setTimeout(() => { if (cardEl) cardEl.remove(); }, 300);
            } else {
                if (cardEl) { cardEl.style.transform = ''; cardEl.style.opacity = ''; }
            }
            return res;
        })
        .catch(() => {
            if (cardEl) { cardEl.style.transform = ''; cardEl.style.opacity = ''; }
            return { success: false };
        });
    }

    // ==========================================
    // Clear all
    // ==========================================
    function clearAll() {
        const body = new URLSearchParams();
        body.append('action', 'clear_all');

        return fetch(API_URL + '/toggle', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': getCsrf()
            },
            body: body.toString(),
            credentials: 'same-origin'
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) updateBadge(0);
            return res;
        })
        .catch(() => ({ success: false }));
    }

    // ==========================================
    // Auto-init on DOMContentLoaded
    // ==========================================
    document.addEventListener('DOMContentLoaded', function () {
        loadBadgeCount();
        initCardOverlays();
    });

    // Export globally
    window.Wishlist = {
        toggle: toggle,
        check: check,
        remove: remove,
        clearAll: clearAll,
        updateBadge: updateBadge,
        loadBadgeCount: loadBadgeCount,
        initCardOverlays: initCardOverlays,
        API_URL: API_URL
    };
})();
