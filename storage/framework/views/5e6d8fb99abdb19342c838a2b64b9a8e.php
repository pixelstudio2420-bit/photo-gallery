<?php
    $mk = app(\App\Services\Marketing\MarketingService::class);
    $push = app(\App\Services\Marketing\PushService::class);
    $enabled = $mk->enabled('push') && $push->publicVapidKey();
    $delay = (int) \App\Models\AppSetting::get('marketing_push_prompt_delay', 10);
    $text = \App\Models\AppSetting::get('marketing_push_prompt_text', 'รับข่าวสารและโปรโมชั่นแบบทันใจ?');
?>

<?php if($enabled): ?>
<div id="push-prompt" class="fixed bottom-4 right-4 z-50 hidden max-w-sm rounded-2xl bg-gradient-to-br from-pink-600 to-rose-700 text-white shadow-2xl p-4"
     role="alertdialog" aria-label="Notification prompt">
    <div class="flex items-start gap-3">
        <i class="bi bi-bell-fill text-2xl"></i>
        <div class="flex-1">
            <div class="text-sm font-bold"><?php echo e($text); ?></div>
            <div class="mt-3 flex items-center gap-2">
                <button id="push-allow" class="px-3 py-1.5 rounded-lg bg-white text-rose-600 text-sm font-semibold hover:bg-slate-100">อนุญาต</button>
                <button id="push-later" class="px-3 py-1.5 rounded-lg border border-white/30 text-sm hover:bg-white/10">ไม่ตอนนี้</button>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;
    if (Notification.permission === 'denied' || Notification.permission === 'granted') return;
    if (localStorage.getItem('push_prompt_dismissed')) return;

    var publicKey = <?php echo json_encode($push->publicVapidKey(), 15, 512) ?>;
    if (!publicKey) return;

    function urlBase64ToUint8Array(b64) {
        var padding = '='.repeat((4 - b64.length % 4) % 4);
        var base64 = (b64 + padding).replace(/\-/g, '+').replace(/_/g, '/');
        var raw = atob(base64);
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; ++i) out[i] = raw.charCodeAt(i);
        return out;
    }

    navigator.serviceWorker.register('<?php echo e(url("/push-sw.js")); ?>').then(function(reg) {
        setTimeout(function() {
            var prompt = document.getElementById('push-prompt');
            if (prompt) prompt.classList.remove('hidden');

            var allow = document.getElementById('push-allow');
            var later = document.getElementById('push-later');
            allow && (allow.onclick = async function() {
                try {
                    var sub = await reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(publicKey),
                    });
                    await fetch('<?php echo e(route("marketing.push.subscribe")); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '<?php echo e(csrf_token()); ?>',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify(sub),
                    });
                    prompt.classList.add('hidden');
                } catch (e) {
                    console.warn('Push subscribe failed:', e);
                    prompt.classList.add('hidden');
                }
            });
            later && (later.onclick = function() {
                localStorage.setItem('push_prompt_dismissed', Date.now());
                prompt.classList.add('hidden');
            });
        }, <?php echo e($delay * 1000); ?>);
    }).catch(function(e) { console.warn('SW reg failed', e); });
})();
</script>
<?php endif; ?>
<?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/components/marketing/push-prompt.blade.php ENDPATH**/ ?>