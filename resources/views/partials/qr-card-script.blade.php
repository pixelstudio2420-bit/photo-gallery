{{-- ──────────────────────────────────────────────────────────────────
     html2canvas-powered "Save Card" + assets needed by qr-card.

     Loaded via @push('scripts') from the QR pages. html2canvas is
     pulled from CDN — ~200KB but only on the QR page, and most users
     hit it once when printing/sharing so the cost is acceptable.

     Required global: a slug variable for the saved filename, set by
     the calling view via window.QR_FILE_SLUG before this partial runs.
     ────────────────────────────────────────────────────────────── */
{{-- html2canvas-pro is a maintained fork that supports modern CSS color
     functions (oklch, lab, lch). Tailwind v4 uses oklch() by default,
     so the original html2canvas v1.4 throws "unsupported color function".
     Same API surface — drop-in replacement, no JS changes needed. --}}
<script src="https://cdn.jsdelivr.net/npm/html2canvas-pro@1.5.8/dist/html2canvas-pro.min.js" defer></script>
<script>
(function() {
  // Wait until the QR image has actually loaded — html2canvas would
  // otherwise rasterise an empty <img> if the user clicks Save before
  // the network round-trip finishes. Tracked via a promise so the
  // Save button can await it without polling.
  let qrReady = new Promise((resolve) => {
    const img = document.getElementById('qr-image');
    if (!img) return resolve();
    if (img.complete && img.naturalWidth > 0) return resolve();
    img.addEventListener('load', resolve, { once: true });
    // Also resolve on error — better to ship a card with a missing QR
    // than to hang forever waiting for a broken image.
    img.addEventListener('error', resolve, { once: true });
  });

  window.saveQrCard = async function(btn) {
    if (typeof html2canvas !== 'function') {
      alert('กำลังโหลดเครื่องมือบันทึก กรุณาลองใหม่ในอีกวินาที');
      return;
    }
    const card = document.getElementById('qr-card');
    if (!card) return;

    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-arrow-repeat" style="display:inline-block;animation:spin 1s linear infinite;"></i> <span>กำลังสร้างภาพ…</span>';

    try {
      await qrReady;
      // 2× pixel ratio = retina-quality download. Higher than 2 bloats
      // the file without visible benefit on most displays/prints.
      const canvas = await html2canvas(card, {
        scale: 2,
        backgroundColor: null,
        useCORS: true,
        // logging:false silences html2canvas's noisy debug output that
        // would otherwise spam the console for normal usage.
        logging: false,
      });

      // Most browsers prefer toBlob over toDataURL for large canvases —
      // toDataURL can OOM on retina images. Fall back if toBlob is null.
      canvas.toBlob((blob) => {
        const slug = (window.QR_FILE_SLUG || 'event') + '-qr.png';
        if (blob) {
          const url = URL.createObjectURL(blob);
          const a = document.createElement('a');
          a.href = url; a.download = slug; a.click();
          // Revoke after the click handler unwinds so the download
          // actually fires before we yank the blob.
          setTimeout(() => URL.revokeObjectURL(url), 500);
        } else {
          // Fallback for older Safari that doesn't honour toBlob
          const a = document.createElement('a');
          a.href = canvas.toDataURL('image/png');
          a.download = slug;
          a.click();
        }
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2"></i> <span>บันทึกแล้ว</span>';
        setTimeout(() => { btn.innerHTML = original; }, 2200);
      }, 'image/png', 0.95);
    } catch (err) {
      console.error('saveQrCard failed', err);
      btn.disabled = false;
      btn.innerHTML = original;
      alert('บันทึกไม่สำเร็จ — กรุณาลองใหม่ หรือใช้คำสั่ง Print แทน');
    }
  };
})();
</script>
<style>
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
</style>
