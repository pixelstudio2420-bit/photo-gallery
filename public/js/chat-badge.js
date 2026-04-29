/**
 * Chat Badge Updater
 * Fetches unread chat count and updates the navbar badge.
 * Uses meta[name="base-url"] for reliable URL detection.
 * Pauses polling when tab is hidden to save bandwidth.
 */
(function() {
    const badge = document.getElementById('chatUnreadBadge');
    if (!badge) return;

    // Reliable base URL from meta tag (set by Laravel layouts)
    const baseUrl = (document.querySelector('meta[name="base-url"]')?.content || '').replace(/\/$/, '');
    let timer = null;
    const POLL_INTERVAL = 15000;

    async function fetchUnread() {
        try {
            const res = await fetch(baseUrl + '/api/chat/unread-count', { credentials: 'same-origin' });
            if (!res.ok) return;
            const json = await res.json();
            if (json.success) {
                const count = parseInt(json.data?.count ?? json.count) || 0;
                if (count > 0) {
                    badge.textContent = count > 99 ? '99+' : count;
                    badge.style.display = '';
                } else {
                    badge.style.display = 'none';
                }
            }
        } catch (e) {
            console.warn('[ChatBadge] Fetch failed:', e.message);
        }
    }

    function startPolling() {
        if (timer) clearInterval(timer);
        timer = setInterval(fetchUnread, POLL_INTERVAL);
    }

    function stopPolling() {
        if (timer) { clearInterval(timer); timer = null; }
    }

    // Pause polling when tab is hidden
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopPolling();
        } else {
            fetchUnread();
            startPolling();
        }
    });

    // Initial fetch + start
    fetchUnread();
    startPolling();
})();
