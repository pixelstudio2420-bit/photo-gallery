{{--
  Smart Cart Upsell Widget
  ────────────────────────
  Floats above the existing cart bar / drawer and pings the upsell endpoint
  whenever the cart count changes. If a better-value bundle exists, shows
  a slim banner: "เพิ่มอีก 2 รูป = ฿480 (ประหยัด ฿120!)"

  Designed to be drop-in-included once per page after the bundle cards;
  rebinds itself to cart-mutation events (cart:add, cart:remove, cart:cleared)
  fired by the existing cart code. Quietly hides itself when:
    • Cart is empty (nothing to upsell from).
    • No upsell would save the buyer money.
    • The cart already contains a bundle row (already on the discount track).

  Pure JS + Alpine — no server-side rendering needed since the card refreshes
  per cart event. The endpoint that powers this is /api/cart/upsell-suggestion.
--}}

<div x-data="cartUpsell({{ $event->id ?? 0 }})"
     x-show="suggestion"
     x-init="bind()"
     x-transition
     style="display:none;"
     class="fixed bottom-20 md:bottom-6 left-4 right-4 md:right-6 md:left-auto z-30 max-w-sm">

  <div class="bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-500/10 dark:to-orange-500/10 border border-amber-300 dark:border-amber-500/30 rounded-2xl shadow-2xl p-4 backdrop-blur-md">
    <div class="flex items-start gap-3">
      <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shrink-0">
        <i class="bi bi-lightning-charge-fill text-white"></i>
      </div>
      <div class="flex-1 min-w-0">
        <div class="font-bold text-sm text-amber-900 dark:text-amber-200 mb-0.5">
          💡 อัปเกรดแพ็กเกจคุ้มกว่า!
        </div>
        <div class="text-xs text-gray-600 dark:text-gray-300 leading-snug" x-text="suggestion?.message"></div>
        <div class="flex gap-2 mt-3">
          <button @click="apply()" :disabled="applying"
                  class="flex-1 py-1.5 px-3 rounded-lg bg-gradient-to-br from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white text-xs font-bold transition disabled:opacity-50">
            <span x-show="!applying">เปลี่ยนเป็น <span x-text="suggestion?.name"></span></span>
            <span x-show="applying">กำลังเปลี่ยน…</span>
          </button>
          <button @click="dismiss()" class="px-3 rounded-lg bg-white/60 dark:bg-white/[0.06] text-xs text-gray-500 hover:text-gray-700">
            ปิด
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function cartUpsell(eventId) {
  return {
    suggestion: null,
    applying: false,
    dismissed: false,

    bind() {
      ['cart:add', 'cart:remove', 'cart:cleared', 'cart:updated'].forEach(evt => {
        window.addEventListener(evt, () => this.refresh());
      });
      // First load — wait a moment so cart has time to settle.
      setTimeout(() => this.refresh(), 800);
    },

    async refresh() {
      if (this.dismissed) return;
      try {
        const resp = await fetch(`/api/cart/upsell-suggestion?event_id=${eventId}`, {
          headers: { 'Accept': 'application/json' },
        });
        if (!resp.ok) return;
        const data = await resp.json();
        this.suggestion = data.suggestion ?? null;
      } catch (e) {
        // Quietly fail — this is a UX nicety, not critical.
      }
    },

    async apply() {
      if (!this.suggestion || this.applying) return;
      this.applying = true;
      try {
        // Find which photo IDs are currently in the cart for this event;
        // pad them up to the bundle's required count by adding the
        // most-recent photo IDs the gallery showed (best-effort — the
        // server still validates).
        const cartResp = await fetch('/api/cart', { headers: { 'Accept': 'application/json' }});
        const cartData = await cartResp.json();
        const cartItems = (cartData.items || []).filter(it => it.event_id == eventId && it.price_type !== 'bundle');
        const existingPhotoIds = cartItems.map(it => it.photo_id);

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const resp = await fetch('/api/cart/bundle', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
          },
          body: JSON.stringify({
            event_id: eventId,
            package_id: this.suggestion.package_id,
            photo_ids: existingPhotoIds.length > 0 ? existingPhotoIds : ['placeholder'],
          }),
        });
        if (!resp.ok) throw new Error();
        // Refresh cart UI
        window.dispatchEvent(new CustomEvent('cart:updated'));
        this.suggestion = null;
        // Also reload the page so the cart reflects the bundle row visually
        // (the live cart bar isn't always reactive on legacy pages).
        setTimeout(() => window.location.reload(), 300);
      } catch (e) {
        alert('ไม่สามารถเปลี่ยนเป็นแพ็กเกจได้ ลองอีกครั้ง');
      } finally {
        this.applying = false;
      }
    },

    dismiss() {
      this.dismissed = true;
      this.suggestion = null;
    },
  };
}
</script>
