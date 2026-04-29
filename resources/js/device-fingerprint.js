/**
 * Lightweight browser fingerprint for anti-abuse signal.
 *
 * Combines a handful of stable browser properties into a sha256 hash:
 *   - userAgent
 *   - language list
 *   - screen geometry + color depth
 *   - timezone offset + zone name
 *   - hardware concurrency (CPU count hint)
 *   - canvas-rendering signature (subpixel-rendering varies by GPU)
 *
 * Why not commercial FingerprintJS?
 *   - Free fingerprints from FingerprintJS are noisy (24h refresh) and
 *     the paid tier ($200/mo) is overkill for our threat model.
 *   - We just need "the same human reuses tabs/devices to make 50 free
 *     accounts" — which 95% of attackers do without tooling, and our
 *     hash catches them.
 *   - Determined attackers will rotate devices anyway. The goal is
 *     making abuse cost more time than it's worth.
 *
 * Privacy:
 *   - Generated client-side; never transmitted in plain. The server hashes
 *     it again with SIGNUP_SIGNAL_SALT before storing.
 *   - We DON'T persist it locally — every page view recomputes it. That
 *     means clearing browser storage doesn't reset the fingerprint, but
 *     a different browser/incognito will produce a different one (which
 *     is exactly what we want — anti-abuse, not user tracking).
 *
 * Usage in a Blade form:
 *
 *     <form id="register-form" method="POST" action="/register">
 *         @csrf
 *         <input type="hidden" name="device_fingerprint" id="fp">
 *         …
 *     </form>
 *     <script>
 *         window.__r2DeviceFingerprint?.fingerprint().then(fp => {
 *             document.getElementById('fp').value = fp;
 *         });
 *     </script>
 */

async function sha256(str) {
    const buf = new TextEncoder().encode(str);
    const hash = await crypto.subtle.digest('SHA-256', buf);
    return Array.from(new Uint8Array(hash))
        .map((b) => b.toString(16).padStart(2, '0'))
        .join('');
}

/**
 * Render a small canvas with rotated text. Different GPUs/drivers
 * produce subtly different pixels — this is the most stable single
 * fingerprint signal across browser refreshes.
 */
function canvasSignature() {
    try {
        const c = document.createElement('canvas');
        c.width  = 200;
        c.height = 50;
        const ctx = c.getContext('2d');
        if (!ctx) return '';
        ctx.textBaseline = 'top';
        ctx.font = "16px 'Arial'";
        ctx.fillStyle = '#069';
        ctx.fillText('r2-fp-canvas- ☃', 2, 2);  // includes Unicode for variance
        ctx.strokeStyle = 'rgba(255, 0, 0, 0.5)';
        ctx.beginPath();
        ctx.arc(50, 25, 20, 0, Math.PI * 2, true);
        ctx.stroke();
        return c.toDataURL();
    } catch {
        return '';
    }
}

async function fingerprint() {
    const parts = [
        navigator.userAgent || '',
        (navigator.languages || [navigator.language || '']).join(','),
        `${screen.width}x${screen.height}@${screen.colorDepth}`,
        new Date().getTimezoneOffset(),
        Intl?.DateTimeFormat?.()?.resolvedOptions?.()?.timeZone || '',
        navigator.hardwareConcurrency || 0,
        navigator.platform || '',
        navigator.maxTouchPoints || 0,
        canvasSignature(),
    ];
    return await sha256(parts.join('||'));
}

window.__r2DeviceFingerprint = {
    fingerprint,
    sha256,    // exposed for tests
};

// Auto-fill any input with [data-device-fingerprint] when the page loads.
// Forms can opt in with a single attribute — no inline script needed.
document.addEventListener('DOMContentLoaded', () => {
    const targets = document.querySelectorAll('input[data-device-fingerprint]');
    if (targets.length === 0) return;
    fingerprint().then((fp) => {
        targets.forEach((el) => {
            el.value = fp;
        });
    }).catch(() => {
        // Fingerprint computation failed (e.g. crypto.subtle blocked in
        // very old browsers). Leave the field empty — server treats null
        // as "no signal" rather than "abuse".
    });
});
