@extends('layouts.app')

@section('title', $event->name ?? 'Event')

@section('og-meta')
@include('layouts.partials.og-meta', [
  'ogTitle' => $event->name . ' | ' . config('app.name'),
  'ogDescription' => 'ชมภาพถ่ายจากงาน ' . $event->name,
  'ogImage' => $event->cover_image_url ?? '',
  'ogType' => 'article',
])
@endsection

@push('styles')
{{-- Preconnect / DNS-prefetch hints — let the browser open TLS sockets
     to the image CDNs while it's still parsing this HTML. Saves the
     ~200ms TLS handshake on the first thumbnail request, which means
     the gallery's eager batch starts streaming bytes ~one full RTT
     earlier. The R2 host comes from app_settings (set by admin); we
     hard-code the Google ones because they're always the same. --}}
@php
  $r2Host = parse_url((string) (\App\Models\AppSetting::get('r2_custom_domain', '') ?: \App\Models\AppSetting::get('r2_public_url', '')), PHP_URL_HOST);
@endphp
@if(!empty($r2Host))
  <link rel="preconnect" href="https://{{ $r2Host }}" crossorigin>
  <link rel="dns-prefetch" href="https://{{ $r2Host }}">
@endif
<link rel="preconnect" href="https://lh3.googleusercontent.com" crossorigin>
<link rel="dns-prefetch" href="https://drive.google.com">

<style>
/* === Only CSS that Tailwind cannot handle === */

/* Gallery Toolbar */
#gallery-toolbar { top: 56px; }
.gallery-toolbar-bg {
  background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
}
.toolbar-col-active, .toolbar-col-active:hover {
  background: linear-gradient(135deg, #6366f1, #8b5cf6) !important;
  color: #fff !important; box-shadow: 0 2px 10px rgba(99,102,241,0.4);
}

/* Hero — matches face-hero style */
.event-hero {
  background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
  padding: 2.5rem 0 2rem;
  position: relative; overflow: hidden;
}
.event-hero::before {
  content: ''; position: absolute; inset: 0; pointer-events: none;
  background: radial-gradient(circle at 20% 50%, rgba(99,102,241,0.15) 0%, transparent 50%),
              radial-gradient(circle at 80% 20%, rgba(139,92,246,0.12) 0%, transparent 50%),
              radial-gradient(circle at 60% 80%, rgba(59,130,246,0.1) 0%, transparent 50%);
}
.event-hero::after {
  content: ''; position: absolute; bottom: 0; left: 0; right: 0; height: 1px;
  background: linear-gradient(90deg, transparent, rgba(99,102,241,0.3), transparent);
}
.event-hero .hero-container { position: relative; z-index: 1; }
.hero-icon-wrap {
  width: 48px; height: 48px; border-radius: 14px;
  background: linear-gradient(135deg, rgba(99,102,241,0.3), rgba(139,92,246,0.3));
  display: flex; align-items: center; justify-content: center;
  border: 1px solid rgba(255,255,255,0.1);
  backdrop-filter: blur(8px); flex-shrink: 0;
}
.hero-icon-wrap i { font-size: 1.4rem; color: #a5b4fc; }

/* Cart bar — vibrant purchase-focused */
#cart-bar { animation: slideUp 0.4s cubic-bezier(0.16,1,0.3,1); }
.cart-bar-inner {
  background: linear-gradient(135deg, rgba(15,23,42,0.97), rgba(30,27,75,0.97));
  backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px);
  border-top: 2px solid; border-image: linear-gradient(90deg, #6366f1, #8b5cf6, #ec4899) 1;
}
.cart-pulse { animation: cartPulse 2s ease-in-out infinite; }
@keyframes cartPulse { 0%,100% { box-shadow: 0 0 0 0 rgba(99,102,241,0.4); } 50% { box-shadow: 0 0 0 8px rgba(99,102,241,0); } }

/* Gallery grid — mobile defaults to 2 columns so each thumbnail is large
   enough for the buyer to recognize themselves at a glance. The
   photographer-set col-N override is honoured on phones (the buyer can
   bump density up via the dropdown) so the default just gives them a
   readable starting point, not a hard cap. */
.g-gallery { display: grid; gap: 8px; width: 100%; padding: 12px; grid-template-columns: repeat(2, 1fr); }
@media (min-width: 576px) { .g-gallery { grid-template-columns: repeat(4, 1fr); gap: 8px; padding: 16px; } }
@media (min-width: 992px) { .g-gallery { grid-template-columns: repeat(5, 1fr); gap: 8px; } }
@media (min-width: 1400px) { .g-gallery { grid-template-columns: repeat(6, 1fr); gap: 10px; } }
@media (max-width: 575.98px) {
  /* Cap density on the LARGE end so a desktop-set col-6 / col-8 doesn't
     produce 50px-wide thumbnails on phones. col-3 and col-4 are honoured
     because the user explicitly picked them via the mobile dropdown. */
  .g-gallery.col-5, .g-gallery.col-6, .g-gallery.col-8 { grid-template-columns: repeat(4, 1fr) !important; }
}
@media (min-width: 576px) and (max-width: 991.98px) {
  .g-gallery.col-6, .g-gallery.col-8 { grid-template-columns: repeat(4, 1fr) !important; }
}
.g-gallery.col-2 { grid-template-columns: repeat(2,1fr) !important; }
.g-gallery.col-3 { grid-template-columns: repeat(3,1fr) !important; }
.g-gallery.col-4 { grid-template-columns: repeat(4,1fr) !important; }
.g-gallery.col-5 { grid-template-columns: repeat(5,1fr) !important; }
.g-gallery.col-6 { grid-template-columns: repeat(6,1fr) !important; }
.g-gallery.col-8 { grid-template-columns: repeat(8,1fr) !important; }

/* Gallery item */
.g-item { content-visibility: auto; contain-intrinsic-size: 1px 200px; background: #1e293b; }
.g-item:hover img { transform: scale(1.05); }
.g-zoom { position: absolute; top: 8px; right: 8px; z-index: 5; width: 28px; height: 28px; border-radius: 50%; border: none; background: rgba(0,0,0,0.35); backdrop-filter: blur(6px); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; opacity: 0; transition: all 0.25s ease; cursor: pointer; }
.g-zoom:hover { background: rgba(255,255,255,0.3); transform: scale(1.1); }
.g-item:hover .g-zoom { opacity: 1; }
.g-item:hover .g-overlay { opacity: 1; }
.g-check { position: absolute; top: 8px; left: 8px; z-index: 5; width: 28px; height: 28px; border-radius: 50%; border: 2px solid rgba(255,255,255,0.5); background: rgba(0,0,0,0.25); backdrop-filter: blur(6px); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; transition: all 0.25s ease; cursor: pointer; }
.g-check:hover { background: rgba(99,102,241,0.6); border-color: rgba(255,255,255,0.8); transform: scale(1.1); }
.g-check i { opacity: 0.3; transition: opacity 0.2s; }
.g-item.selected { outline: 3px solid #818cf8; outline-offset: -3px; }
.g-item.selected::after { content: '\e935'; font-family: 'bootstrap-icons'; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(99,102,241,0.30), rgba(139,92,246,0.22)); pointer-events: none; z-index: 1; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: rgba(255,255,255,0.25); }
.g-item.selected .g-check { background: linear-gradient(135deg,#6366f1,#8b5cf6); border-color: #fff; box-shadow: 0 2px 14px rgba(99,102,241,0.7); transform: scale(1.2); animation: checkPop 0.3s ease; }
.g-item.selected .g-check i { opacity: 1; }
.g-item img[src^="data:"] { opacity: 0.1; animation: skeleton-pulse 1.2s ease-in-out infinite; }

/* ===== LIGHTBOX — Production cinematic viewer ===== */
#lightboxModal { position: fixed; inset: 0; z-index: 9999; display: none; flex-direction: column; }
#lightboxModal.active { display: flex; }
.lb-backdrop { position: absolute; inset: 0; background: #000; transition: opacity 0.3s ease; }
.lb-ui { opacity: 1; transition: opacity 0.3s ease; }
#lightboxModal.lb-hide-ui .lb-ui { opacity: 0; pointer-events: none; }
#lb-img { transition: opacity 0.25s ease, transform 0.25s ease; will-change: opacity, transform; }
#lb-img.lb-loading { opacity: 0.12; filter: blur(4px); transform: scale(0.98); }
#lb-img.lb-loaded { opacity: 1; filter: none; transform: scale(1); }
.lb-spinner-ring { width: 48px; height: 48px; border: 3px solid rgba(255,255,255,0.08); border-top-color: #818cf8; border-radius: 50%; animation: spin 0.8s linear infinite; }

/* Lightbox nav buttons */
.lb-nav-btn { width: 52px; height: 52px; border-radius: 16px; background: rgba(255,255,255,0.06); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.7); font-size: 1.3rem; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; }
.lb-nav-btn:hover { background: rgba(255,255,255,0.14); color: #fff; transform: scale(1.06); }
.lb-nav-btn:active { transform: scale(0.95); }

/* Lightbox footer glassmorphism */
.lb-footer { background: linear-gradient(to top, rgba(0,0,0,0.92), rgba(0,0,0,0.6)); backdrop-filter: blur(24px); -webkit-backdrop-filter: blur(24px); }

/* Lightbox watermark — repeating "proof" pattern overlaid on the full-view photo.
   Two layer modes:
     .lb-wm-image-layer → CSS-tiled logo/watermark PNG via background-image
     .lb-wm-text-layer  → flex grid of rotated text spans (fallback when no image)
   Both share .lb-wm-layer for the -25deg rotation + oversized inset so the
   rotation never leaves transparent corners. */
.lb-wm-text { transform: rotate(-25deg); }
.lb-wm-layer {
  position: absolute; inset: -30%;
  pointer-events: none; user-select: none;
  transform: rotate(-25deg);
  will-change: transform;
}
.lb-wm-image-layer {
  background-repeat: repeat;
  background-position: center;
  background-size: 240px auto;
  filter: drop-shadow(0 2px 6px rgba(0,0,0,0.35));
  mix-blend-mode: screen;
}
.lb-wm-text-layer {
  display: flex; flex-wrap: wrap;
  align-items: center; justify-content: center;
  gap: 40px 80px; padding: 40px;
  font-weight: 900; font-size: 1.35rem; letter-spacing: 6px;
  white-space: nowrap;
  text-shadow: 0 2px 8px rgba(0,0,0,0.45);
}
.lb-wm-text-layer span { white-space: nowrap; line-height: 1; }
@media (min-width: 576px) {
  .lb-wm-image-layer { background-size: 300px auto; }
  .lb-wm-text-layer { font-size: 1.6rem; gap: 48px 100px; }
}
@media (min-width: 992px) {
  .lb-wm-image-layer { background-size: 360px auto; }
  .lb-wm-text-layer { font-size: 1.9rem; gap: 56px 120px; }
}

/* Lightbox thumbnail strip */
.lb-thumbstrip { display: flex; gap: 4px; overflow-x: auto; padding: 6px 12px; scrollbar-width: none; }
.lb-thumbstrip::-webkit-scrollbar { display: none; }
.lb-thumb { width: 56px; height: 56px; border-radius: 8px; overflow: hidden; cursor: pointer; border: 2px solid transparent; transition: all 0.2s ease; flex-shrink: 0; opacity: 0.5; }
.lb-thumb:hover { opacity: 0.8; border-color: rgba(255,255,255,0.3); }
.lb-thumb.active { opacity: 1; border-color: #6366f1; box-shadow: 0 0 12px rgba(99,102,241,0.4); }
.lb-thumb img { width: 100%; height: 100%; object-fit: cover; }

/* Share dropdown */
.share-btn::after { display: none; }

/* Keyframes */
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes checkPop { 0% { transform: scale(0.8); } 50% { transform: scale(1.15); } 100% { transform: scale(1); } }
@keyframes skeleton-pulse { 0%,100% { opacity: 0.08; } 50% { opacity: 0.2; } }
@keyframes slideUp { from { transform: translateY(100%); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes lb-img-in { from { opacity: 0; transform: scale(0.96); } to { opacity: 1; transform: scale(1); } }

/* Mobile adjustments */
@media (max-width: 575.98px) {
  .g-item .g-check { width: 26px; height: 26px; font-size: 12px; top: 4px; left: 4px; }
  .g-item .g-zoom { width: 26px; height: 26px; font-size: 12px; top: 4px; right: 4px; opacity: 1; }
  .lb-nav-btn { width: 40px; height: 40px; border-radius: 10px; font-size: 1rem; }
  .lb-nav-btn.prev { left: 6px !important; }
  .lb-nav-btn.next { right: 6px !important; }
  .cart-bar-mobile { flex-direction: column; text-align: center; gap: 10px !important; }
  .cart-bar-mobile .cart-actions-mobile { width: 100%; justify-content: center; }
  .cart-bar-mobile .btn-clear-cart { display: none !important; }
  .lb-footer-wrap { flex-direction: column !important; gap: 8px !important; text-align: center; padding: 10px 14px !important; }
  .lb-footer-wrap .lb-actions { width: 100%; }
  .lb-footer-wrap .lb-actions button { flex: 1; justify-content: center; }
  #lb-img { max-width: 100vw !important; max-height: 62vh !important; }
  #lb-image-area { padding: 50px 10px 170px !important; }
  .lb-thumbstrip { display: none !important; }
  .lb-nav-btn.prev { left: 4px !important; }
  .lb-nav-btn.next { right: 4px !important; }
}
@media (min-width: 576px) and (max-width: 991.98px) {
  .lb-thumb { width: 48px; height: 48px; }
  #lb-image-area { padding: 56px 50px 180px !important; }
}

/* ──────────────────────────────────────────────────────────────
   Login-required modal (face search gate)
   - Triggered by the 🔒 button when user is a guest
   - Uses Alpine.js x-show + custom CSS keyframes (Alpine's built-in
     transitions are fine but we want a more polished spring scale-in)
   ────────────────────────────────────────────────────────────── */
.login-modal-backdrop {
  position: fixed; inset: 0; z-index: 9998;
  background: rgba(15, 23, 42, 0.65);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  animation: lm-fade-in 0.2s ease-out;
}
@keyframes lm-fade-in {
  from { opacity: 0; }
  to   { opacity: 1; }
}
.login-modal-wrap {
  position: fixed; inset: 0; z-index: 9999;
  display: flex; align-items: center; justify-content: center;
  padding: 1rem;
  pointer-events: none;
}
.login-modal {
  position: relative; width: 100%; max-width: 420px;
  background: #fff;
  border-radius: 24px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4),
              0 0 0 1px rgba(99, 102, 241, 0.08);
  overflow: hidden;
  pointer-events: auto;
  animation: lm-pop 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.dark .login-modal {
  background: #1e293b;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7),
              0 0 0 1px rgba(99, 102, 241, 0.15);
}
@keyframes lm-pop {
  0%   { opacity: 0; transform: translateY(20px) scale(0.92); }
  60%  { opacity: 1; transform: translateY(-2px) scale(1.01); }
  100% { opacity: 1; transform: translateY(0) scale(1); }
}

/* Gradient hero header with icon */
.login-modal-hero {
  position: relative;
  padding: 2.25rem 1.5rem 1.25rem;
  text-align: center;
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 60%, #d946ef 100%);
  overflow: hidden;
}
.login-modal-hero::before {
  content: '';
  position: absolute; inset: 0;
  background: radial-gradient(circle at 30% 20%, rgba(255,255,255,0.18) 0%, transparent 50%),
              radial-gradient(circle at 80% 80%, rgba(255,255,255,0.10) 0%, transparent 50%);
  pointer-events: none;
}
.login-modal-icon {
  position: relative;
  width: 72px; height: 72px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.22);
  border: 2px solid rgba(255, 255, 255, 0.35);
  backdrop-filter: blur(8px);
  margin: 0 auto 0.875rem;
  display: flex; align-items: center; justify-content: center;
  animation: lm-icon-bounce 0.6s ease-out 0.1s both;
}
.login-modal-icon i {
  font-size: 2rem; color: #fff;
  filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
}
@keyframes lm-icon-bounce {
  0%   { transform: scale(0); }
  60%  { transform: scale(1.15); }
  100% { transform: scale(1); }
}
.login-modal-hero h3 {
  position: relative;
  color: #fff;
  font-size: 1.15rem; font-weight: 800;
  margin: 0; letter-spacing: -0.01em;
  text-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

/* Body */
.login-modal-body {
  padding: 1.5rem 1.5rem 1rem;
}
.login-modal-body p {
  color: #475569;
  font-size: 0.875rem;
  line-height: 1.6;
  margin: 0 0 1rem;
  text-align: center;
}
.dark .login-modal-body p { color: #cbd5e1; }
.login-modal-features {
  list-style: none;
  padding: 0; margin: 0 0 0.5rem;
  display: grid; gap: 0.625rem;
}
.login-modal-features li {
  display: flex; align-items: flex-start; gap: 0.625rem;
  font-size: 0.8rem; color: #64748b;
  padding: 0.625rem 0.75rem;
  background: linear-gradient(135deg, rgba(99,102,241,0.04), rgba(139,92,246,0.04));
  border: 1px solid rgba(99,102,241,0.1);
  border-radius: 10px;
}
.dark .login-modal-features li {
  color: #94a3b8;
  background: linear-gradient(135deg, rgba(99,102,241,0.08), rgba(139,92,246,0.08));
  border-color: rgba(99,102,241,0.18);
}
.login-modal-features li i {
  color: #6366f1; font-size: 1rem; flex-shrink: 0; margin-top: 0.05rem;
}
.dark .login-modal-features li i { color: #a5b4fc; }

/* Footer / actions */
.login-modal-actions {
  padding: 0.5rem 1.5rem 1.5rem;
  display: grid; grid-template-columns: 1fr 1.4fr; gap: 0.625rem;
}
.login-modal-btn {
  display: flex; align-items: center; justify-content: center; gap: 0.4rem;
  padding: 0.75rem 1rem;
  border-radius: 12px;
  font-weight: 700; font-size: 0.85rem;
  border: none; cursor: pointer; transition: all 0.2s;
  text-decoration: none;
  font-family: inherit;
}
.login-modal-btn.cancel {
  background: #f1f5f9; color: #475569;
  border: 1px solid #e2e8f0;
}
.dark .login-modal-btn.cancel {
  background: rgba(255,255,255,0.05); color: #cbd5e1;
  border-color: rgba(255,255,255,0.1);
}
.login-modal-btn.cancel:hover { background: #e2e8f0; }
.dark .login-modal-btn.cancel:hover { background: rgba(255,255,255,0.08); }
.login-modal-btn.primary {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  box-shadow: 0 4px 14px rgba(99,102,241,0.4);
}
.login-modal-btn.primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(99,102,241,0.5);
}
.login-modal-btn.primary:active { transform: translateY(0); }

/* Close X button (top-right corner of hero) */
.login-modal-close {
  position: absolute; top: 0.75rem; right: 0.75rem; z-index: 2;
  width: 32px; height: 32px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.15);
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: #fff;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.2s;
}
.login-modal-close:hover {
  background: rgba(255, 255, 255, 0.3);
}
.login-modal-close i { font-size: 1rem; }

/* Tiny "secure" footer hint */
.login-modal-footer {
  text-align: center;
  font-size: 0.7rem;
  color: #94a3b8;
  padding: 0 1.5rem 1.25rem;
}
.login-modal-footer i { color: #10b981; margin-right: 0.25rem; }
.dark .login-modal-footer { color: #64748b; }

[x-cloak] { display: none !important; }

/* ──────────────────────────────────────────────────────────────
   Share modal (event share sheet)
   - Replaces the old dropdown with a modal/sheet
   - Mobile: bottom sheet · Desktop: centered modal
   - Each share target is a colored card with brand identity
   ────────────────────────────────────────────────────────────── */
.share-modal-backdrop {
  position: fixed; inset: 0; z-index: 9998;
  background: rgba(15, 23, 42, 0.6);
  backdrop-filter: blur(6px);
  -webkit-backdrop-filter: blur(6px);
  animation: sm-fade-in 0.2s ease-out;
}
@keyframes sm-fade-in {
  from { opacity: 0; }
  to   { opacity: 1; }
}
.share-modal-wrap {
  position: fixed; inset: 0; z-index: 9999;
  display: flex; align-items: center; justify-content: center;
  padding: 1rem;
  pointer-events: none;
}
@media (max-width: 540px) {
  .share-modal-wrap { align-items: flex-end; padding: 0; }
}
.share-modal {
  position: relative; width: 100%; max-width: 440px;
  background: #fff;
  border-radius: 24px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
  overflow: hidden;
  pointer-events: auto;
  animation: sm-pop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.dark .share-modal { background: #1e293b; }
@media (max-width: 540px) {
  .share-modal {
    max-width: 100%;
    border-radius: 24px 24px 0 0;
    animation: sm-slide-up 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
  }
}
@keyframes sm-pop {
  0%   { opacity: 0; transform: translateY(20px) scale(0.95); }
  100% { opacity: 1; transform: translateY(0) scale(1); }
}
@keyframes sm-slide-up {
  0%   { opacity: 0; transform: translateY(100%); }
  100% { opacity: 1; transform: translateY(0); }
}

/* Drag handle (mobile bottom-sheet feel) */
.share-modal-handle {
  display: none;
  width: 36px; height: 4px;
  background: rgba(0, 0, 0, 0.15);
  border-radius: 999px;
  margin: 0.625rem auto 0;
}
.dark .share-modal-handle { background: rgba(255, 255, 255, 0.2); }
@media (max-width: 540px) {
  .share-modal-handle { display: block; }
}

/* Header */
.share-modal-header {
  padding: 1.25rem 1.5rem 0.75rem;
  display: flex; align-items: flex-start; justify-content: space-between;
  gap: 0.75rem;
}
.share-modal-header-text { flex: 1; min-width: 0; }
.share-modal-header h3 {
  font-size: 1.05rem; font-weight: 800; margin: 0;
  color: #0f172a; letter-spacing: -0.01em;
  display: flex; align-items: center; gap: 0.5rem;
}
.dark .share-modal-header h3 { color: #f1f5f9; }
.share-modal-header h3 i { color: #6366f1; font-size: 1.1rem; }
.share-modal-header p {
  font-size: 0.8rem; color: #64748b; margin: 0.25rem 0 0;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.dark .share-modal-header p { color: #94a3b8; }
.share-modal-x {
  width: 32px; height: 32px;
  border-radius: 50%;
  background: #f1f5f9;
  border: none; color: #64748b;
  cursor: pointer; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center;
  transition: all 0.2s;
}
.share-modal-x:hover { background: #e2e8f0; color: #0f172a; transform: rotate(90deg); }
.dark .share-modal-x { background: rgba(255,255,255,0.06); color: #cbd5e1; }
.dark .share-modal-x:hover { background: rgba(255,255,255,0.12); color: #fff; }

/* Native share button (top — only shown if Web Share API available) */
.share-modal-native {
  margin: 0.5rem 1.5rem 0.75rem;
  display: flex; align-items: center; justify-content: center; gap: 0.5rem;
  padding: 0.75rem 1rem;
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  border: none; border-radius: 12px;
  font-size: 0.85rem; font-weight: 700;
  cursor: pointer; transition: transform 0.15s, box-shadow 0.2s;
  box-shadow: 0 4px 14px rgba(99,102,241,0.35);
  font-family: inherit; width: calc(100% - 3rem);
}
.share-modal-native:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(99,102,241,0.5);
}
.share-modal-native:active { transform: translateY(0); }

.share-modal-divider {
  display: flex; align-items: center; gap: 0.625rem;
  padding: 0.25rem 1.5rem 0.5rem;
  font-size: 0.7rem; color: #94a3b8; font-weight: 600;
}
.share-modal-divider::before,
.share-modal-divider::after {
  content: ''; flex: 1; height: 1px;
  background: rgba(0,0,0,0.08);
}
.dark .share-modal-divider::before,
.dark .share-modal-divider::after {
  background: rgba(255,255,255,0.08);
}

/* Share targets grid */
.share-modal-grid {
  padding: 0.5rem 1.5rem 1rem;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 0.5rem;
}
@media (max-width: 360px) {
  .share-modal-grid { grid-template-columns: repeat(3, 1fr); }
}
.share-target {
  display: flex; flex-direction: column; align-items: center; gap: 0.4rem;
  padding: 0.875rem 0.5rem;
  border-radius: 14px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  text-decoration: none;
  cursor: pointer; transition: all 0.2s;
  font-family: inherit;
}
.dark .share-target {
  background: rgba(255,255,255,0.03);
  border-color: rgba(255,255,255,0.08);
}
.share-target:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(0,0,0,0.08);
}
.dark .share-target:hover { box-shadow: 0 6px 16px rgba(0,0,0,0.3); }

.share-target-icon {
  width: 44px; height: 44px;
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  color: #fff;
  transition: transform 0.2s;
}
.share-target:hover .share-target-icon { transform: scale(1.08); }
.share-target-icon i { font-size: 1.3rem; }

/* Brand colors */
.share-target-icon.facebook { background: #1877f2; }
.share-target-icon.line     { background: #06c755; }
.share-target-icon.x        { background: #000; }
.dark .share-target-icon.x  { background: #1e293b; border: 1px solid rgba(255,255,255,0.2); }
.share-target-icon.twitter  { background: #1da1f2; }
.share-target-icon.whatsapp { background: #25d366; }
.share-target-icon.telegram { background: #0088cc; }
.share-target-icon.email    { background: #ef4444; }
.share-target-icon.messenger{ background: #0084ff; }

.share-target span {
  font-size: 0.72rem; font-weight: 600;
  color: #475569;
  text-align: center;
  line-height: 1.2;
}
.dark .share-target span { color: #cbd5e1; }

/* Copy link bar */
.share-modal-link {
  margin: 0 1.5rem 1.5rem;
  display: flex; align-items: center; gap: 0;
  background: #f1f5f9;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  overflow: hidden;
}
.dark .share-modal-link {
  background: rgba(255,255,255,0.04);
  border-color: rgba(255,255,255,0.08);
}
.share-modal-link-url {
  flex: 1; min-width: 0;
  padding: 0.7rem 0.875rem;
  font-size: 0.78rem; color: #64748b;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
}
.dark .share-modal-link-url { color: #94a3b8; }
.share-modal-link-btn {
  flex-shrink: 0;
  padding: 0.625rem 1rem;
  background: #fff;
  border: none; border-left: 1px solid #e2e8f0;
  color: #6366f1;
  font-size: 0.78rem; font-weight: 700;
  cursor: pointer; transition: all 0.2s;
  display: flex; align-items: center; gap: 0.35rem;
  font-family: inherit;
}
.dark .share-modal-link-btn {
  background: rgba(255,255,255,0.03);
  border-left-color: rgba(255,255,255,0.08);
  color: #a5b4fc;
}
.share-modal-link-btn:hover { background: #e0e7ff; }
.dark .share-modal-link-btn:hover { background: rgba(99,102,241,0.18); }
.share-modal-link-btn.copied {
  background: #d1fae5 !important;
  color: #065f46 !important;
}
.dark .share-modal-link-btn.copied {
  background: rgba(16,185,129,0.18) !important;
  color: #6ee7b7 !important;
}
</style>
@endpush

@section('hero')
{{-- ============ Hero (face-hero style) ============ --}}
<div class="event-hero">
  <div class="hero-container max-w-7xl mx-auto px-4 sm:px-6">

    {{-- Breadcrumb --}}
    <nav class="flex items-center gap-0.5 flex-wrap" style="margin-bottom:1rem;">
      <a href="{{ url('/') }}" style="color:rgba(255,255,255,0.45);text-decoration:none;font-size:0.8rem;transition:color 0.2s;"><i class="bi bi-house-door"></i></a>
      <span style="color:rgba(255,255,255,0.25);margin:0 0.5rem;font-size:0.7rem;"><i class="bi bi-chevron-right"></i></span>
      <a href="{{ route('events.index') }}" style="color:rgba(255,255,255,0.45);text-decoration:none;font-size:0.8rem;transition:color 0.2s;">อีเวนต์</a>
      @if($event->category)
      <span style="color:rgba(255,255,255,0.25);margin:0 0.5rem;font-size:0.7rem;"><i class="bi bi-chevron-right"></i></span>
      <a href="{{ route('events.index') }}?category={{ $event->category->id }}" style="color:rgba(255,255,255,0.45);text-decoration:none;font-size:0.8rem;transition:color 0.2s;">{{ $event->category->name }}</a>
      @endif
      <span style="color:rgba(255,255,255,0.25);margin:0 0.5rem;font-size:0.7rem;"><i class="bi bi-chevron-right"></i></span>
      <span style="color:rgba(255,255,255,0.3);font-size:0.8rem;">{{ Str::limit($event->name, 40) }}</span>
    </nav>

    {{-- Title with icon wrap (face-hero style) --}}
    <div style="display:flex;align-items:center;gap:0.75rem;margin-top:0.75rem;">
      <div class="hero-icon-wrap">
        <i class="bi bi-images"></i>
      </div>
      <h1 style="color:#fff;font-weight:800;font-size:1.75rem;letter-spacing:-0.025em;line-height:1.2;margin:0;">{{ $event->name }}</h1>
    </div>

    @if($event->description)
    <p style="color:rgba(255,255,255,0.5);font-size:0.9rem;margin-top:0.5rem;max-width:540px;line-height:1.6;">
      {{ Str::limit($event->description, 180) }}
    </p>
    @endif

    {{-- Meta pills row --}}
    @php
      $pgProfile = \DB::table('photographer_profiles')
        ->where('user_id', $event->photographer_id)
        ->select('display_name', 'id')
        ->first();
      $pgName = $pgProfile?->display_name;
      if (!$pgName) {
        $pgUser = \DB::table('auth_users')
          ->where('id', $event->photographer_id)
          ->select('first_name', 'last_name')
          ->first();
        $pgName = trim(($pgUser?->first_name ?? '') . ' ' . ($pgUser?->last_name ?? ''));
      }
    @endphp
    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:0.5rem;margin-top:1.25rem;">
      @if($event->shoot_date)
      <span style="display:inline-flex;align-items:center;gap:0.4rem;color:rgba(255,255,255,0.55);font-size:0.78rem;background:rgba(255,255,255,0.06);padding:0.3rem 0.75rem;border-radius:999px;border:1px solid rgba(255,255,255,0.06);">
        <i class="bi bi-calendar3" style="color:#a5b4fc;"></i>
        {{ $event->shoot_date->translatedFormat('j F Y') }}
      </span>
      @endif
      {{-- Time chip — only render when at least start_time is set.
           Format: "06:00 – 18:00" or just "06:00" if no end_time. --}}
      @if($event->start_time)
      <span style="display:inline-flex;align-items:center;gap:0.4rem;color:rgba(255,255,255,0.55);font-size:0.78rem;background:rgba(255,255,255,0.06);padding:0.3rem 0.75rem;border-radius:999px;border:1px solid rgba(255,255,255,0.06);">
        <i class="bi bi-clock" style="color:#a5b4fc;"></i>
        {{ \Illuminate\Support\Str::of($event->start_time)->limit(5, '') }}@if($event->end_time) – {{ \Illuminate\Support\Str::of($event->end_time)->limit(5, '') }} น.@else น.@endif
      </span>
      @endif
      {{-- Venue name when available — separate from `location` so the
           SERP shows BOTH the building and the city. --}}
      @if($event->venue_name)
      <span style="display:inline-flex;align-items:center;gap:0.4rem;color:rgba(255,255,255,0.55);font-size:0.78rem;background:rgba(255,255,255,0.06);padding:0.3rem 0.75rem;border-radius:999px;border:1px solid rgba(255,255,255,0.06);">
        <i class="bi bi-building" style="color:#a5b4fc;"></i>
        {{ $event->venue_name }}
      </span>
      @endif
      @if($event->location)
      <span style="display:inline-flex;align-items:center;gap:0.4rem;color:rgba(255,255,255,0.55);font-size:0.78rem;background:rgba(255,255,255,0.06);padding:0.3rem 0.75rem;border-radius:999px;border:1px solid rgba(255,255,255,0.06);">
        <i class="bi bi-geo-alt-fill" style="color:#a5b4fc;"></i>
        {{ $event->location }}
      </span>
      @endif
      {{-- Attendees badge — gives the page a credibility cue
           ("คนร่วมงาน 200+ คน") without crowding the chip row. --}}
      @if($event->expected_attendees)
      <span style="display:inline-flex;align-items:center;gap:0.4rem;color:rgba(255,255,255,0.55);font-size:0.78rem;background:rgba(255,255,255,0.06);padding:0.3rem 0.75rem;border-radius:999px;border:1px solid rgba(255,255,255,0.06);">
        <i class="bi bi-people-fill" style="color:#a5b4fc;"></i>
        {{ number_format($event->expected_attendees) }}+ คน
      </span>
      @endif
      @if($pgName)
      <a href="{{ route('photographers.show', $event->photographer_id) }}" style="display:inline-flex;align-items:center;gap:0.4rem;color:rgba(255,255,255,0.55);font-size:0.78rem;background:rgba(255,255,255,0.06);padding:0.3rem 0.75rem;border-radius:999px;border:1px solid rgba(255,255,255,0.06);text-decoration:none;transition:all 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.12)';this.style.color='rgba(255,255,255,0.8)';" onmouseout="this.style.background='rgba(255,255,255,0.06)';this.style.color='rgba(255,255,255,0.55)';">
        <i class="bi bi-camera-fill" style="color:#a5b4fc;"></i>
        {{ $pgName }}
      </a>
      @endif
      <span style="display:inline-flex;align-items:center;gap:0.4rem;color:rgba(255,255,255,0.55);font-size:0.78rem;background:rgba(255,255,255,0.06);padding:0.3rem 0.75rem;border-radius:999px;border:1px solid rgba(255,255,255,0.06);" id="hero-photo-count">
        <i class="bi bi-images" style="color:#a5b4fc;"></i>
        <span id="hero-photo-num">{{ count($photos) }}</span> ภาพ
      </span>
    </div>

    {{-- Price + Action Buttons --}}
    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:0.6rem;margin-top:1.25rem;">
      @if(!empty($prices) && $prices->count() > 0)
      <div style="display:inline-flex;align-items:center;gap:0.5rem;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;padding:0.5rem 1.25rem;border-radius:999px;font-weight:700;font-size:0.95rem;box-shadow:0 4px 16px rgba(99,102,241,0.35);">
        <i class="bi bi-tag-fill" style="opacity:0.85;"></i>
        <span>{{ number_format($prices->first()->price, 0) }} ฿ / รูป</span>
      </div>
      @elseif(isset($basePricePerPhoto) && $basePricePerPhoto > 0)
      <div style="display:inline-flex;align-items:center;gap:0.5rem;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;padding:0.5rem 1.25rem;border-radius:999px;font-weight:700;font-size:0.95rem;box-shadow:0 4px 16px rgba(99,102,241,0.35);">
        <i class="bi bi-tag-fill" style="opacity:0.85;"></i>
        <span>เริ่มต้น {{ number_format($basePricePerPhoto, 2) }} ฿ / รูป</span>
      </div>
      @elseif($event->is_free ?? false)
      <div style="display:inline-flex;align-items:center;gap:0.5rem;background:linear-gradient(135deg,#10b981,#059669);color:#fff;padding:0.5rem 1.25rem;border-radius:999px;font-weight:700;font-size:0.95rem;box-shadow:0 4px 16px rgba(16,185,129,0.3);">
        <i class="bi bi-gift-fill"></i>
        <span>ดาวน์โหลดฟรี</span>
      </div>
      @endif

      @if($event->face_search_enabled ?? true)
        @auth
          {{-- Authenticated: direct link to the face-search page --}}
          <a href="{{ route('events.face-search', $event->id) }}" style="display:inline-flex;align-items:center;gap:0.4rem;background:rgba(99,102,241,0.2);color:#a5b4fc;padding:0.4rem 0.9rem;border-radius:999px;font-size:0.78rem;font-weight:600;border:1px solid rgba(99,102,241,0.3);text-decoration:none;transition:all 0.2s;" onmouseover="this.style.background='rgba(99,102,241,0.35)';this.style.color='#c7d2fe';" onmouseout="this.style.background='rgba(99,102,241,0.2)';this.style.color='#a5b4fc';">
            <i class="bi bi-person-bounding-box"></i>ค้นหาด้วยใบหน้า
          </a>
        @else
          {{-- Guest: show a login-gated badge that opens a styled modal.
               Server-side `auth` middleware already protects the route — the
               modal is purely UX so the user understands the gate before
               being redirected to /login. --}}
          <div x-data="{ loginPrompt: false }" style="display:inline-block;">
            <button type="button"
                    @click="loginPrompt = true"
                    style="display:inline-flex;align-items:center;gap:0.4rem;background:rgba(99,102,241,0.12);color:#a5b4fc;padding:0.4rem 0.9rem;border-radius:999px;font-size:0.78rem;font-weight:600;border:1px solid rgba(99,102,241,0.2);cursor:pointer;transition:all 0.2s;font-family:inherit;"
                    onmouseover="this.style.background='rgba(99,102,241,0.22)';this.style.color='#c7d2fe';"
                    onmouseout="this.style.background='rgba(99,102,241,0.12)';this.style.color='#a5b4fc';"
                    title="ต้องเข้าสู่ระบบก่อนใช้งาน">
              <i class="bi bi-lock-fill"></i>ค้นหาด้วยใบหน้า
            </button>

            {{-- Modal --}}
            <template x-teleport="body">
              <div x-show="loginPrompt"
                   x-cloak
                   @keydown.escape.window="loginPrompt = false">
                {{-- Backdrop --}}
                <div class="login-modal-backdrop"
                     @click="loginPrompt = false"
                     x-show="loginPrompt"
                     x-transition.opacity></div>

                {{-- Modal --}}
                <div class="login-modal-wrap" x-show="loginPrompt">
                  <div class="login-modal" @click.stop>
                    {{-- Hero with icon --}}
                    <div class="login-modal-hero">
                      <button type="button" class="login-modal-close" @click="loginPrompt = false" aria-label="ปิด">
                        <i class="bi bi-x-lg"></i>
                      </button>
                      <div class="login-modal-icon">
                        <i class="bi bi-person-bounding-box"></i>
                      </div>
                      <h3>ต้องเข้าสู่ระบบก่อน</h3>
                    </div>

                    {{-- Body --}}
                    <div class="login-modal-body">
                      <p>การค้นหาด้วยใบหน้าใช้ข้อมูล <strong style="color:#6366f1;">biometric</strong> ตามกฎหมาย PDPA จึงต้องเข้าสู่ระบบก่อนใช้งาน</p>
                      <ul class="login-modal-features">
                        <li>
                          <i class="bi bi-shield-check"></i>
                          <span>ปกป้องข้อมูลใบหน้าของคุณ — ใช้งานเฉพาะคุณเท่านั้น</span>
                        </li>
                        <li>
                          <i class="bi bi-clock-history"></i>
                          <span>บันทึกประวัติการค้นหา — ดูและลบทีหลังได้</span>
                        </li>
                        <li>
                          <i class="bi bi-bookmark-heart"></i>
                          <span>บันทึกรูปที่เจอเข้า Wishlist เพื่อดูภายหลัง</span>
                        </li>
                      </ul>
                    </div>

                    {{-- Actions --}}
                    <div class="login-modal-actions">
                      <button type="button" class="login-modal-btn cancel" @click="loginPrompt = false">
                        ยกเลิก
                      </button>
                      <a href="{{ route('events.face-search', $event->id) }}" class="login-modal-btn primary">
                        <i class="bi bi-box-arrow-in-right"></i>
                        เข้าสู่ระบบ
                      </a>
                    </div>

                    {{-- Footer hint --}}
                    <div class="login-modal-footer">
                      <i class="bi bi-shield-lock-fill"></i>
                      ปลอดภัย เข้ารหัส และเป็นไปตาม PDPA §26
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </div>
        @endauth
      @endif

      {{-- ──────────────────────────────────────────────────────────
           Share button → opens a modal/bottom-sheet (replaces the old
           dropdown). On mobile we try the OS native share sheet via
           Web Share API; otherwise we fall back to brand-coloured
           targets + a copy-link bar.
           ────────────────────────────────────────────────────────── --}}
      <div x-data="{
              shareOpen: false,
              copied: false,
              canShareNative: false,
              shareUrl: '{{ route('events.show', $event->slug ?: $event->id) }}',
              shareTitle: @js($event->name),
              init() {
                this.canShareNative = !!(navigator.share);
              },
              openShare() { this.shareOpen = true; this.copied = false; },
              closeShare() { this.shareOpen = false; },
              async nativeShare() {
                try {
                  await navigator.share({ title: this.shareTitle, url: this.shareUrl });
                  this.closeShare();
                } catch (e) { /* user cancelled or unsupported */ }
              },
              async copyLink() {
                try {
                  await navigator.clipboard.writeText(this.shareUrl);
                } catch (e) {
                  const ta = document.createElement('textarea');
                  ta.value = this.shareUrl;
                  document.body.appendChild(ta); ta.select();
                  try { document.execCommand('copy'); } catch(_) {}
                  document.body.removeChild(ta);
                }
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 2000);
              }
           }"
           style="display:inline-block;">

        <button type="button"
                @click="openShare()"
                class="share-btn"
                style="display:inline-flex;align-items:center;gap:0.4rem;background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.55);padding:0.4rem 0.9rem;border-radius:999px;font-size:0.78rem;font-weight:600;border:1px solid rgba(255,255,255,0.08);cursor:pointer;transition:all 0.2s;font-family:inherit;"
                onmouseover="this.style.background='rgba(255,255,255,0.12)';this.style.color='rgba(255,255,255,0.85)';"
                onmouseout="this.style.background='rgba(255,255,255,0.06)';this.style.color='rgba(255,255,255,0.55)';">
          <i class="bi bi-share"></i>แชร์
        </button>

        {{-- Modal --}}
        <template x-teleport="body">
          <div x-show="shareOpen"
               x-cloak
               @keydown.escape.window="closeShare()">

            {{-- Backdrop --}}
            <div class="share-modal-backdrop"
                 @click="closeShare()"
                 x-show="shareOpen"
                 x-transition.opacity></div>

            {{-- Modal --}}
            <div class="share-modal-wrap" x-show="shareOpen">
              <div class="share-modal" @click.stop>
                <div class="share-modal-handle"></div>

                {{-- Header --}}
                <div class="share-modal-header">
                  <div class="share-modal-header-text">
                    <h3><i class="bi bi-share-fill"></i>แชร์งานนี้</h3>
                    <p>{{ $event->name }}</p>
                  </div>
                  <button type="button" class="share-modal-x" @click="closeShare()" aria-label="ปิด">
                    <i class="bi bi-x-lg"></i>
                  </button>
                </div>

                {{-- Native share (mobile / supported browsers) --}}
                <button type="button"
                        x-show="canShareNative"
                        @click="nativeShare()"
                        class="share-modal-native">
                  <i class="bi bi-phone"></i>
                  แชร์ผ่านแอปในเครื่อง
                </button>

                <div class="share-modal-divider" x-show="canShareNative">หรือ</div>

                {{-- Brand grid --}}
                <div class="share-modal-grid">
                  <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(route('events.show', $event->slug ?: $event->id)) }}"
                     target="_blank" rel="noopener"
                     class="share-target"
                     @click="closeShare()">
                    <div class="share-target-icon facebook"><i class="bi bi-facebook" style="color:#fff;"></i></div>
                    <span>Facebook</span>
                  </a>

                  <a href="https://social-plugins.line.me/lineit/share?url={{ urlencode(route('events.show', $event->slug ?: $event->id)) }}"
                     target="_blank" rel="noopener"
                     class="share-target"
                     @click="closeShare()">
                    <div class="share-target-icon line"><i class="bi bi-line" style="color:#fff;"></i></div>
                    <span>LINE</span>
                  </a>

                  <a href="https://twitter.com/intent/tweet?url={{ urlencode(route('events.show', $event->slug ?: $event->id)) }}&text={{ urlencode($event->name) }}"
                     target="_blank" rel="noopener"
                     class="share-target"
                     @click="closeShare()">
                    <div class="share-target-icon x"><i class="bi bi-twitter-x" style="color:#fff;"></i></div>
                    <span>X</span>
                  </a>

                  <a href="https://api.whatsapp.com/send?text={{ urlencode($event->name . ' ' . route('events.show', $event->slug ?: $event->id)) }}"
                     target="_blank" rel="noopener"
                     class="share-target"
                     @click="closeShare()">
                    <div class="share-target-icon whatsapp"><i class="bi bi-whatsapp" style="color:#fff;"></i></div>
                    <span>WhatsApp</span>
                  </a>

                  <a href="https://t.me/share/url?url={{ urlencode(route('events.show', $event->slug ?: $event->id)) }}&text={{ urlencode($event->name) }}"
                     target="_blank" rel="noopener"
                     class="share-target"
                     @click="closeShare()">
                    <div class="share-target-icon telegram"><i class="bi bi-telegram" style="color:#fff;"></i></div>
                    <span>Telegram</span>
                  </a>

                  <a href="mailto:?subject={{ rawurlencode($event->name) }}&body={{ rawurlencode(route('events.show', $event->slug ?: $event->id)) }}"
                     class="share-target"
                     @click="closeShare()">
                    <div class="share-target-icon email"><i class="bi bi-envelope-fill" style="color:#fff;"></i></div>
                    <span>อีเมล</span>
                  </a>

                  <button type="button"
                          x-show="canShareNative"
                          @click="nativeShare()"
                          class="share-target">
                    <div class="share-target-icon" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);"><i class="bi bi-three-dots" style="color:#fff;"></i></div>
                    <span>เพิ่มเติม</span>
                  </button>
                </div>

                {{-- Copy link bar --}}
                <div class="share-modal-link">
                  <div class="share-modal-link-url" x-text="shareUrl"></div>
                  <button type="button"
                          class="share-modal-link-btn"
                          :class="{ 'copied': copied }"
                          @click="copyLink()">
                    <template x-if="!copied">
                      <span style="display:inline-flex;align-items:center;gap:0.35rem;">
                        <i class="bi bi-link-45deg"></i>คัดลอก
                      </span>
                    </template>
                    <template x-if="copied">
                      <span style="display:inline-flex;align-items:center;gap:0.35rem;">
                        <i class="bi bi-check-lg"></i>คัดลอกแล้ว
                      </span>
                    </template>
                  </button>
                </div>

              </div>
            </div>
          </div>
        </template>
      </div>
    </div>

  </div>
</div>
@endsection

@section('content-full')

{{-- ============ NEW: Face Search Hero CTA ============ --}}
{{-- Renders the face_match bundle as a hero block above the regular
     bundle cards. The variable-price flow doesn't fit alongside fixed
     cards (buyers see "ราคาผันแปร 0฿" and bounce), so we surface the
     killer feature with example pricing and a single primary CTA.
     Self-renders nothing when no face_match bundle exists for this event. --}}
@include('public.events.partials._face_search_hero')

{{-- ============ NEW: Beautiful Bundle Cards ============ --}}
{{-- Full visual bundle showcase with psychology-driven design.
     The legacy chip strip below stays for quick re-selection but the
     cards above are the primary sales pitch. face_match is rendered
     in the hero CTA above, so the cards loop excludes it. --}}
@include('public.events.partials._bundle_cards')

{{-- ============ NEW: Face Bundle Modal ============ --}}
{{-- Anonymous users can still see the bundle cards (price/feature is
     public marketing info), but the face-search modal + cart upsell
     widget hit auth-gated /api/cart/* endpoints. Wrap them in @auth so
     anonymous browsers don't trigger 401s on every page load. --}}
@auth
  @include('public.events.partials._face_bundle_modal')

  {{-- ============ NEW: Smart Cart Upsell Widget (floating) ============ --}}
  @include('public.events.partials._cart_upsell_widget')
@endauth

{{-- ============ Legacy Packages Strip (compact chip nav) ============ --}}
@if($packages->count() > 0)
<div class="bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-white/[0.06] py-3">
  <div class="max-w-7xl mx-auto px-4">
    <div class="flex items-center gap-2 overflow-auto pb-1" style="scrollbar-width:none;">
      <span class="text-gray-500 dark:text-gray-400 text-xs font-bold mr-1 shrink-0">
        <i class="bi bi-box-seam mr-1"></i>แพ็กเกจ:
      </span>
      @foreach($packages as $pkg)
      <button class="pkg-chip package-btn inline-flex items-center gap-1.5 bg-white dark:bg-slate-800 border-[1.5px] border-slate-200 dark:border-white/10 px-4 py-2 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 whitespace-nowrap cursor-pointer transition-all hover:border-indigo-400 hover:text-indigo-600 hover:-translate-y-0.5 hover:shadow-md hover:shadow-indigo-500/10 shrink-0"
          data-package-id="{{ $pkg->id }}"
          data-count="{{ $pkg->photo_count }}"
          data-price="{{ $pkg->price }}">
        {{ $pkg->name }}
        <span class="text-gray-400">—</span>
        {{ $pkg->photo_count }} รูป
        <span class="text-indigo-500 font-bold">{{ number_format($pkg->price, 0) }} ฿</span>
      </button>
      @endforeach
    </div>
  </div>
</div>
<div class="bg-gradient-to-r from-indigo-500 to-violet-500 text-white py-2.5 text-center text-sm font-semibold hidden" id="pkg-info-bar">
  <div class="max-w-7xl mx-auto px-4">
    <i class="bi bi-box-seam-fill mr-1"></i>
    <span id="pkg-info-text"></span>
    <button onclick="deselectPackage()" class="text-xs text-white/80 hover:text-white ml-2 px-2 py-0.5 rounded border border-white/30 hover:border-white/60 transition-all cursor-pointer">
      <i class="bi bi-x-lg"></i> ยกเลิก
    </button>
  </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════
     Event details card — surfaces the enriched fields photographers
     fill in on the create/edit form. Shown ONLY when at least one
     enrichment field is populated, so legacy events without these
     details don't render an empty card with section headings and
     no values.

     Layout matches the dark hero above (slate-900 with white text)
     so the page reads as one continuous block before the gallery
     toolbar takes over the visual language.
     ════════════════════════════════════════════════════════════════ --}}
@php
    $hasHighlights = is_array($event->highlights) && count($event->highlights) > 0;
    $hasContact    = $event->contact_phone || $event->contact_email
                     || $event->website_url || $event->facebook_url;
    $hasLogistics  = $event->dress_code || $event->parking_info
                     || $event->organizer || $event->event_type;
    $showInfoCard  = $hasHighlights || $hasContact || $hasLogistics;

    $eventTypeLabel = null;
    if ($event->event_type) {
        $eventTypeLabel = \App\Models\Event::eventTypeOptions()[$event->event_type]
            ?? $event->event_type;
    }
@endphp
@if($showInfoCard)
<section class="bg-slate-950 border-b border-white/5" aria-label="ข้อมูลอีเวนต์">
  <div class="max-w-7xl mx-auto px-4 md:px-6 py-6 md:py-8">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-5">

      {{-- ▌ Highlights — bullet list of selling points ▌ --}}
      @if($hasHighlights)
      <div class="rounded-2xl bg-white/[0.04] border border-white/[0.06] p-4 md:p-5">
        <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-300 mb-3 flex items-center gap-2">
          <i class="bi bi-stars"></i> จุดเด่นของงาน
        </h3>
        <ul class="space-y-2">
          @foreach(array_slice($event->highlights, 0, 6) as $highlight)
            <li class="flex items-start gap-2 text-sm text-white/85">
              <i class="bi bi-check-circle-fill text-emerald-400 shrink-0 mt-0.5"></i>
              <span>{{ $highlight }}</span>
            </li>
          @endforeach
        </ul>
      </div>
      @endif

      {{-- ▌ Logistics — organizer · type · dress · parking ▌ --}}
      @if($hasLogistics)
      <div class="rounded-2xl bg-white/[0.04] border border-white/[0.06] p-4 md:p-5">
        <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-300 mb-3 flex items-center gap-2">
          <i class="bi bi-info-circle"></i> ข้อมูลงาน
        </h3>
        <dl class="space-y-2.5 text-sm">
          @if($eventTypeLabel)
            <div class="flex items-start gap-2">
              <dt class="text-white/45 shrink-0 w-24"><i class="bi bi-tag mr-1"></i>ประเภท</dt>
              <dd class="text-white/85 font-medium">{{ $eventTypeLabel }}</dd>
            </div>
          @endif
          @if($event->organizer)
            <div class="flex items-start gap-2">
              <dt class="text-white/45 shrink-0 w-24"><i class="bi bi-megaphone mr-1"></i>ผู้จัดงาน</dt>
              <dd class="text-white/85">{{ $event->organizer }}</dd>
            </div>
          @endif
          @if($event->dress_code)
            <div class="flex items-start gap-2">
              <dt class="text-white/45 shrink-0 w-24"><i class="bi bi-person-vcard mr-1"></i>การแต่งกาย</dt>
              <dd class="text-white/85">{{ $event->dress_code }}</dd>
            </div>
          @endif
          @if($event->parking_info)
            <div class="flex items-start gap-2">
              <dt class="text-white/45 shrink-0 w-24"><i class="bi bi-p-circle mr-1"></i>ที่จอดรถ</dt>
              <dd class="text-white/85">{{ $event->parking_info }}</dd>
            </div>
          @endif
        </dl>
      </div>
      @endif

      {{-- ▌ Contact — phone · email · website · facebook ▌ --}}
      @if($hasContact)
      <div class="rounded-2xl bg-white/[0.04] border border-white/[0.06] p-4 md:p-5">
        <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-300 mb-3 flex items-center gap-2">
          <i class="bi bi-telephone"></i> ติดต่อ / ลิงก์
        </h3>
        <div class="space-y-2 text-sm">
          @if($event->contact_phone)
            <a href="tel:{{ preg_replace('/[^\d+]/', '', $event->contact_phone) }}"
               class="flex items-center gap-2 text-white/85 hover:text-indigo-300 transition">
              <i class="bi bi-telephone-fill text-indigo-400"></i>
              <span>{{ $event->contact_phone }}</span>
            </a>
          @endif
          @if($event->contact_email)
            <a href="mailto:{{ $event->contact_email }}"
               class="flex items-center gap-2 text-white/85 hover:text-indigo-300 transition truncate">
              <i class="bi bi-envelope-fill text-indigo-400 shrink-0"></i>
              <span class="truncate">{{ $event->contact_email }}</span>
            </a>
          @endif
          @if($event->website_url)
            <a href="{{ $event->website_url }}" target="_blank" rel="noopener nofollow"
               class="flex items-center gap-2 text-white/85 hover:text-indigo-300 transition truncate">
              <i class="bi bi-globe text-indigo-400 shrink-0"></i>
              <span class="truncate">เว็บไซต์งาน</span>
              <i class="bi bi-box-arrow-up-right text-xs opacity-60"></i>
            </a>
          @endif
          @if($event->facebook_url)
            <a href="{{ $event->facebook_url }}" target="_blank" rel="noopener nofollow"
               class="flex items-center gap-2 text-white/85 hover:text-indigo-300 transition truncate">
              <i class="bi bi-facebook text-indigo-400 shrink-0"></i>
              <span class="truncate">Facebook</span>
              <i class="bi bi-box-arrow-up-right text-xs opacity-60"></i>
            </a>
          @endif
        </div>
      </div>
      @endif
    </div>
  </div>
</section>
@endif

{{-- ============ Gallery Toolbar (sticky) ============ --}}
<div class="gallery-toolbar-bg backdrop-blur-xl border-b border-white/[0.06] shadow-lg sticky z-20" id="gallery-toolbar" style="display:none;">
  <div class="max-w-full mx-auto px-3 md:px-5">
    <div class="flex items-center justify-between gap-3 py-2">

      {{-- Left: Photo count + Selection actions --}}
      <div class="flex items-center gap-2 flex-wrap min-w-0">
        {{-- Photo count pill --}}
        <span class="inline-flex items-center gap-1.5 bg-gradient-to-r from-indigo-500 to-violet-500 text-white pl-1.5 pr-3.5 py-1 rounded-full text-xs font-bold shadow-lg shadow-indigo-500/30">
          <span class="w-6 h-6 rounded-full bg-white/20 inline-flex items-center justify-center text-[11px]"><i class="bi bi-images"></i></span>
          <span id="photo-count">0 รูป</span>
        </span>

        {{-- Select all --}}
        <button class="inline-flex items-center gap-1.5 bg-emerald-500/15 text-emerald-400 pl-1.5 pr-3.5 py-1 rounded-full text-xs font-semibold border border-emerald-500/25 cursor-pointer whitespace-nowrap transition-all duration-200 hover:bg-gradient-to-r hover:from-emerald-500 hover:to-teal-500 hover:text-white hover:border-transparent hover:shadow-lg hover:shadow-emerald-500/30 active:scale-95" id="btn-select-all">
          <span class="w-6 h-6 rounded-full bg-emerald-500/20 inline-flex items-center justify-center text-[11px]"><i class="bi bi-check2-all"></i></span>
          <span><span class="hidden sm:inline">เลือก</span>ทั้งหมด</span>
        </button>

        {{-- Clear all --}}
        <button class="inline-flex items-center gap-1.5 bg-rose-500/15 text-rose-400 pl-1.5 pr-3.5 py-1 rounded-full text-xs font-semibold border border-rose-500/25 cursor-pointer whitespace-nowrap transition-all duration-200 hover:bg-gradient-to-r hover:from-rose-500 hover:to-pink-500 hover:text-white hover:border-transparent hover:shadow-lg hover:shadow-rose-500/30 active:scale-95" id="btn-clear-all" style="display:none;">
          <span class="w-6 h-6 rounded-full bg-rose-500/20 inline-flex items-center justify-center text-[11px]"><i class="bi bi-x-circle"></i></span>
          <span>ยกเลิกทั้งหมด</span>
        </button>

        <span class="text-gray-500 hidden text-xs" id="selected-label" style="display:none!important;"></span>
      </div>

      {{-- Right: Column controls --}}
      <div class="flex items-center gap-2 shrink-0">

        {{-- Mobile col selector --}}
        <div x-data="{ open: false }" class="relative md:hidden">
          <button @click="open = !open" class="inline-flex items-center gap-1.5 bg-sky-500/15 text-sky-400 pl-1.5 pr-3 py-1 rounded-full text-xs font-semibold border border-sky-500/25 cursor-pointer transition-all duration-200 hover:bg-sky-500 hover:text-white hover:border-sky-500 hover:shadow-lg hover:shadow-sky-500/30 active:scale-95">
            <span class="w-6 h-6 rounded-full bg-sky-500/20 inline-flex items-center justify-center text-[11px]"><i class="bi bi-grid-3x3-gap"></i></span>
            <span id="col-label-mobile">2</span>
          </button>
          <div x-show="open" @click.away="open = false" x-cloak x-transition
               class="absolute right-0 z-10 mt-2 bg-slate-800 shadow-xl shadow-black/30 rounded-2xl py-1.5 min-w-[150px] border border-white/10">
            <a class="flex items-center gap-2.5 px-4 py-2.5 hover:bg-indigo-500/10 text-sm col-opt no-underline text-slate-300 transition-colors" href="#" data-col="2" @click="open = false"><i class="bi bi-grid text-indigo-400"></i>2 คอลัมน์</a>
            <a class="flex items-center gap-2.5 px-4 py-2.5 hover:bg-violet-500/10 text-sm col-opt no-underline text-slate-300 transition-colors" href="#" data-col="3" @click="open = false"><i class="bi bi-grid-3x3 text-violet-400"></i>3 คอลัมน์</a>
            <a class="flex items-center gap-2.5 px-4 py-2.5 hover:bg-sky-500/10 text-sm col-opt no-underline text-slate-300 transition-colors" href="#" data-col="4" @click="open = false"><i class="bi bi-grid-3x3-gap text-sky-400"></i>4 คอลัมน์</a>
          </div>
        </div>

        {{-- Desktop col selector --}}
        <div class="hidden md:flex items-center gap-0.5 bg-white/[0.04] rounded-full p-1 border border-white/[0.06]">
          @foreach([3,4,5,6,8] as $c)
          <button class="cb col-btn w-7 h-7 flex items-center justify-center rounded-full text-[11px] font-bold border-0 bg-transparent text-slate-500 cursor-pointer transition-all duration-200 hover:bg-white/10 hover:text-indigo-300 {{ $c === 5 ? 'toolbar-col-active' : '' }}" data-col="{{ $c }}" title="{{ $c }} คอลัมน์">{{ $c }}</button>
          @endforeach
        </div>
      </div>

    </div>
  </div>
</div>

{{-- Hint Banner --}}
<div class="max-w-full mx-auto px-3 md:px-4 mt-3" id="hint-wrap">
  <div class="bg-gradient-to-br from-indigo-50 to-violet-50 dark:from-indigo-500/[0.06] dark:to-violet-500/[0.04] border border-indigo-500/10 dark:border-indigo-500/[0.12] rounded-2xl px-4 py-3 text-sm text-slate-600 dark:text-slate-400 flex items-center gap-3">
    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-indigo-500 to-indigo-400 text-white flex items-center justify-center shrink-0 text-sm shadow-md shadow-indigo-500/25">
      <i class="bi bi-hand-index-thumb"></i>
    </div>
    <div>
      <strong class="text-slate-800 dark:text-slate-200">คลิกที่รูป</strong> เพื่อเลือกใส่ตะกร้า &bull;
      กด <i class="bi bi-zoom-in"></i> เพื่อดูขยาย &bull;
      เลือกเสร็จแล้วกด <strong class="text-slate-800 dark:text-slate-200">"ดูตะกร้า"</strong> ด้านล่าง
    </div>
  </div>
</div>

{{-- ============ Gallery Loading / Grid / Error ============ --}}
<div id="gallery-loading" class="flex flex-col items-center justify-center py-20 gap-4">
  <div class="w-10 h-10 border-3 border-indigo-500/15 border-t-indigo-500 rounded-full" style="animation:spin 0.7s linear infinite;"></div>
  <p class="text-gray-500 dark:text-gray-400 text-sm mb-0">กำลังโหลดรูปภาพ...</p>
</div>

<div id="gallery-container" style="display:none;">
  <div id="gallery-grid" class="g-gallery"></div>
</div>

<div id="gallery-error" class="text-center py-20" style="display:none;">
  <div class="w-16 h-16 rounded-full bg-red-500/[0.08] text-red-500 inline-flex items-center justify-center text-2xl mb-3.5">
    <i class="bi bi-exclamation-triangle"></i>
  </div>
  <p class="text-red-600 dark:text-red-400 font-semibold mb-2" id="gallery-error-msg"></p>
  <button class="inline-flex items-center gap-1 bg-indigo-500 text-white px-6 py-2 rounded-lg text-sm font-semibold cursor-pointer hover:bg-indigo-600 transition-colors" onclick="loadPhotos()">
    <i class="bi bi-arrow-clockwise mr-1"></i>ลองใหม่
  </button>
</div>

@if(empty($photos))
<div id="gallery-empty" class="text-center py-16">
  <i class="bi bi-images text-6xl text-slate-300 dark:text-slate-600 block mb-3"></i>
  <p class="text-gray-500 dark:text-gray-400">ไม่พบภาพถ่ายในอีเวนต์นี้</p>
</div>
@endif

{{-- ============ Floating Cart Bar — Vibrant Purchase CTA ============ --}}
<div id="cart-bar" style="position:fixed;bottom:0;left:0;right:0;z-index:1040;display:none;">
  <div class="cart-bar-inner" style="padding:14px 20px;box-shadow:0 -8px 40px rgba(0,0,0,0.35);">
    <div class="cart-bar-mobile" style="max-width:960px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;">
      {{-- Left: Info --}}
      <div style="display:flex;align-items:center;gap:12px;">
        <div class="cart-pulse" style="width:46px;height:46px;border-radius:14px;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 16px rgba(99,102,241,0.4);">
          <i class="bi bi-bag-check-fill" style="color:#fff;font-size:1.2rem;"></i>
        </div>
        <div>
          <div id="cart-count-text" style="color:rgba(255,255,255,0.5);font-size:0.75rem;font-weight:500;">เลือก 0 รูป</div>
          <div id="cart-total-text" style="color:#fff;font-weight:700;font-size:0.95rem;"></div>
        </div>
      </div>
      {{-- Right: Action buttons --}}
      <div class="cart-actions-mobile" style="display:flex;gap:8px;align-items:center;">
        <button class="btn-clear-cart" onclick="clearCart()" style="background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.1);color:rgba(255,255,255,0.5);padding:10px 16px;border-radius:12px;font-weight:600;font-size:0.82rem;cursor:pointer;transition:all 0.2s;" onmouseover="this.style.background='rgba(239,68,68,0.15)';this.style.borderColor='rgba(239,68,68,0.4)';this.style.color='#f87171';" onmouseout="this.style.background='rgba(255,255,255,0.06)';this.style.borderColor='rgba(255,255,255,0.1)';this.style.color='rgba(255,255,255,0.5)';">
          <i class="bi bi-x-lg" style="margin-right:4px;"></i>ยกเลิก
        </button>
        @auth
        <button onclick="goToCart()" style="background:linear-gradient(135deg,#6366f1,#7c3aed);border:none;color:#fff;padding:10px 22px;border-radius:12px;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.2s;box-shadow:0 4px 20px rgba(99,102,241,0.4);display:inline-flex;align-items:center;gap:8px;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 28px rgba(99,102,241,0.5)';" onmouseout="this.style.transform='';this.style.boxShadow='0 4px 20px rgba(99,102,241,0.4)';">
          <i class="bi bi-bag-check-fill"></i>
          ตะกร้า
          <span id="cart-badge-count" style="background:rgba(255,255,255,0.2);padding:1px 7px;border-radius:6px;font-size:0.72rem;">0</span>
        </button>
        <button onclick="expressBuy()" style="background:linear-gradient(135deg,#f59e0b,#ef4444);border:none;color:#fff;padding:10px 24px;border-radius:12px;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.2s;box-shadow:0 4px 20px rgba(245,158,11,0.4);display:inline-flex;align-items:center;gap:8px;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 28px rgba(245,158,11,0.5)';" onmouseout="this.style.transform='';this.style.boxShadow='0 4px 20px rgba(245,158,11,0.4)';">
          <i class="bi bi-lightning-charge-fill"></i>ซื้อเลย
        </button>
        @else
        <button onclick="promptLogin()" style="background:linear-gradient(135deg,#6366f1,#7c3aed);border:none;color:#fff;padding:10px 22px;border-radius:12px;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.2s;box-shadow:0 4px 20px rgba(99,102,241,0.4);display:inline-flex;align-items:center;gap:8px;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 28px rgba(99,102,241,0.5)';" onmouseout="this.style.transform='';this.style.boxShadow='0 4px 20px rgba(99,102,241,0.4)';">
          <i class="bi bi-bag-check-fill"></i>
          ตะกร้า
          <span id="cart-badge-count" style="background:rgba(255,255,255,0.2);padding:1px 7px;border-radius:6px;font-size:0.72rem;">0</span>
        </button>
        <button onclick="promptLogin()" style="background:linear-gradient(135deg,#f59e0b,#ef4444);border:none;color:#fff;padding:10px 24px;border-radius:12px;font-weight:700;font-size:0.85rem;cursor:pointer;transition:all 0.2s;box-shadow:0 4px 20px rgba(245,158,11,0.4);display:inline-flex;align-items:center;gap:8px;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 8px 28px rgba(245,158,11,0.5)';" onmouseout="this.style.transform='';this.style.boxShadow='0 4px 20px rgba(245,158,11,0.4)';">
          <i class="bi bi-lightning-charge-fill"></i>ซื้อเลย
        </button>
        @endauth
      </div>
    </div>
  </div>
</div>

{{-- ============ Lightbox — Cinematic Photo Viewer ============ --}}
<div id="lightboxModal">
  {{-- Black backdrop --}}
  <div class="lb-backdrop" onclick="closeLightbox()"></div>

  {{-- Top bar (UI) --}}
  <div class="lb-ui" style="position:absolute;top:0;left:0;right:0;z-index:10;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;">
    <div style="display:flex;align-items:center;gap:10px;">
      <div style="background:rgba(255,255,255,0.08);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.08);border-radius:10px;padding:6px 14px;display:flex;align-items:center;gap:8px;">
        <span id="lb-counter" style="color:#fff;font-size:0.8rem;font-weight:600;"></span>
        <span id="lb-name" style="color:rgba(255,255,255,0.35);font-size:0.75rem;font-weight:400;"></span>
      </div>
    </div>
    <div style="display:flex;gap:6px;">
      <button class="lb-nav-btn" onclick="toggleLbUi()" title="ซ่อน/แสดง UI"><i class="bi bi-arrows-fullscreen" style="font-size:0.85rem;"></i></button>
      <button class="lb-nav-btn" onclick="closeLightbox()" title="ปิด (Esc)"><i class="bi bi-x-lg"></i></button>
    </div>
  </div>

  {{-- Image area --}}
  <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;z-index:5;padding:56px 70px 190px;pointer-events:none;" id="lb-image-area">
    <div class="lb-spinner-ring" id="lb-spinner" style="position:absolute;display:none;"></div>
    <img id="lb-img" src="" alt="" style="max-width:100%;max-height:100%;object-fit:contain;border-radius:4px;user-select:none;pointer-events:auto;" draggable="false">

    {{-- Watermark overlay — mirrors the server-side watermark from AppSetting.
         Priority: configured image watermark → configured text watermark → site logo.
         Silently hidden when nothing is configured. NEVER renders config('app.name')
         ("Photo Gallery") — the pattern must match whatever the admin actually set. --}}
    @php
      $wmType      = (string) \App\Models\AppSetting::get('watermark_type', 'text');
      $wmImagePath = (string) \App\Models\AppSetting::get('watermark_image_path', '');
      $wmText      = trim((string) \App\Models\AppSetting::get('watermark_text', ''));
      $wmOpacityDb = (int) \App\Models\AppSetting::get('watermark_opacity', 50);
      $wmColor     = (string) \App\Models\AppSetting::get('watermark_color', '#ffffff');
      $siteLogoKey = (string) \App\Models\AppSetting::get('site_logo', '');

      // The DB opacity is for the server-baked watermark; the lightbox overlay
      // sits on TOP of that already-watermarked photo so we scale down to keep
      // it tasteful (50 → 0.32, 100 → 0.55, clamped to [0.08, 0.55]).
      $lbWmOpacity = max(0.08, min(0.55, $wmOpacityDb / 150));

      $lbWmImageUrl = null;
      $lbWmText     = null;

      $resolveAssetUrl = function (string $path) {
          if ($path === '') return null;
          if (preg_match('#^(https?:)?//#i', $path)) return $path;
          try {
              $sm = app(\App\Services\StorageManager::class);
              $url = $sm->url($path, $sm->primaryDriver());
              if (!empty($url)) return $url;
          } catch (\Throwable $e) { /* fall through */ }
          return asset('storage/' . ltrim($path, '/'));
      };

      if ($wmType === 'image' && $wmImagePath !== '') {
          $lbWmImageUrl = $resolveAssetUrl($wmImagePath);
      } elseif ($wmType === 'text' && $wmText !== '') {
          $lbWmText = $wmText;
      } elseif ($siteLogoKey !== '') {
          $lbWmImageUrl = $resolveAssetUrl($siteLogoKey);
      } elseif ($wmText !== '') {
          // Last-ditch fallback: if the admin ever filled in watermark_text
          // but later switched type=image without removing the path, still
          // show something meaningful rather than nothing.
          $lbWmText = $wmText;
      }
    @endphp

    @if($lbWmImageUrl)
      <div class="lb-wm-layer lb-wm-image-layer"
           style="background-image:url('{{ $lbWmImageUrl }}');opacity:{{ number_format($lbWmOpacity, 2) }};"></div>
    @elseif($lbWmText)
      <div class="lb-wm-layer lb-wm-text-layer"
           style="color:{{ $wmColor }};opacity:{{ number_format(min(0.35, $lbWmOpacity + 0.05), 2) }};">
        @for($__wmi = 0; $__wmi < 24; $__wmi++)<span>{{ $lbWmText }}</span>@endfor
      </div>
    @endif
  </div>

  {{-- Navigation arrows --}}
  <button class="lb-nav-btn lb-ui prev" id="lb-prev" style="position:absolute;top:50%;left:16px;transform:translateY(-50%);z-index:15;"><i class="bi bi-chevron-left"></i></button>
  <button class="lb-nav-btn lb-ui next" id="lb-next" style="position:absolute;top:50%;right:16px;transform:translateY(-50%);z-index:15;"><i class="bi bi-chevron-right"></i></button>

  {{-- Thumbnail strip --}}
  <div class="lb-ui" style="position:absolute;bottom:48px;left:0;right:0;z-index:14;pointer-events:auto;">
    <div class="lb-thumbstrip" id="lb-thumbstrip"></div>
  </div>

  {{-- Bottom action bar --}}
  <div class="lb-footer lb-ui" style="position:absolute;bottom:0;left:0;right:0;z-index:15;padding:8px 20px;">
    <div class="lb-footer-wrap" style="max-width:900px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:10px;">
      <div style="flex:1;min-width:0;">
        <strong id="lb-footer-name" style="color:#fff;font-size:0.82rem;font-weight:600;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">Photo</strong>
        <div id="lb-footer-price" style="color:#a5b4fc;font-size:0.72rem;margin-top:1px;"></div>
      </div>
      <div class="lb-actions" style="display:flex;gap:6px;flex-shrink:0;">
        <button id="lb-select-btn" onclick="toggleSelect(currentLbIdx);lbShow(currentLbIdx);" style="display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:10px;font-weight:600;font-size:0.75rem;cursor:pointer;transition:all 0.2s;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.06);color:rgba(255,255,255,0.8);backdrop-filter:blur(10px);">
          <i class="bi bi-check-circle"></i> <span>เลือกรูปนี้</span>
        </button>
        <button id="lb-cart-btn" style="display:inline-flex;align-items:center;gap:5px;padding:7px 14px;border-radius:10px;font-weight:700;font-size:0.75rem;cursor:pointer;transition:all 0.2s;border:none;background:linear-gradient(135deg,#6366f1,#8b5cf6);color:#fff;box-shadow:0 4px 16px rgba(99,102,241,0.3);">
          <i class="bi bi-cart-plus"></i> <span>เพิ่มลงตะกร้า</span>
        </button>
        {{-- Express-buy button inside the lightbox — auto-selects this one
             photo, submits to /orders/express, and jumps straight to payment.
             One click from "I like this photo" → "pay the QR". The `@auth`
             gate mirrors the floating bar so guests go to login first. --}}
        @auth
        <button id="lb-buy-btn" onclick="lbBuyNow()" style="display:inline-flex;align-items:center;gap:5px;padding:7px 16px;border-radius:10px;font-weight:700;font-size:0.75rem;cursor:pointer;transition:all 0.2s;border:none;background:linear-gradient(135deg,#f59e0b,#ef4444);color:#fff;box-shadow:0 4px 16px rgba(245,158,11,0.35);">
          <i class="bi bi-lightning-charge-fill"></i> <span>ซื้อเลย</span>
        </button>
        @else
        <button id="lb-buy-btn" onclick="promptLogin()" style="display:inline-flex;align-items:center;gap:5px;padding:7px 16px;border-radius:10px;font-weight:700;font-size:0.75rem;cursor:pointer;transition:all 0.2s;border:none;background:linear-gradient(135deg,#f59e0b,#ef4444);color:#fff;box-shadow:0 4px 16px rgba(245,158,11,0.35);">
          <i class="bi bi-lightning-charge-fill"></i> <span>ซื้อเลย</span>
        </button>
        @endauth
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
// ============================================
// Data from server
// ============================================
const EVENT_ID   = {{ $event->id }};
const PRICE_PER  = {{ !empty($prices) && $prices->count() > 0 ? $prices->first()->price : ($event->price_per_photo ?? 0) }};
const HAS_PACKAGES = {{ isset($packages) && $packages->count() > 0 ? 'true' : 'false' }};
const DRIVE_FOLDER = @json($event->drive_folder_id ?? '');

const BASE_PRICE_PER = (() => {
  if (PRICE_PER > 0) return PRICE_PER;
  let min = 0;
  document.querySelectorAll('.package-btn').forEach(btn => {
    const pp = parseFloat(btn.dataset.price) / parseInt(btn.dataset.count);
    if (!min || pp < min) min = pp;
  });
  return min;
})();
const CART_ADD_URL = '{{ route("cart.add") }}';
const CSRF_TOKEN  = '{{ csrf_token() }}';
const EVENT_SLUG  = @json($event->slug ?? (string)$event->id);

const SERVER_PHOTOS = @json($photos ?? []);

// Gallery performance settings — admin-configurable via
// /admin/settings/photo-performance. Falls back to fast defaults when
// missing so an un-seeded install still renders a snappy page.
const GALLERY_EAGER_COUNT = {{ max(0,  min(60,  (int) \App\Models\AppSetting::get('photo_gallery_eager_count', 12))) }};
const GALLERY_THUMB_SIZE  = {{ max(100, min(600, (int) \App\Models\AppSetting::get('photo_gallery_thumb_size', 200))) }};

// ============================================
// State
// ============================================
let allPhotos  = [];
let selected   = new Set();
let currentLbIdx = 0;
let activePackage = null;
// Initial column density: phones default to 2 (readability over volume),
// tablets and desktops default to 5 (typical photographer preference).
// The buyer can change it any time via the col-btn / col-opt selectors.
let currentCols  = window.matchMedia('(max-width: 575.98px)').matches ? 2 : 5;

// ============================================
// Init
// ============================================
function initGallery() {
  if (SERVER_PHOTOS && SERVER_PHOTOS.length > 0) {
    allPhotos = SERVER_PHOTOS;
    renderGallery(allPhotos);
  } else {
    loadPhotos();
  }

  // Column buttons
  document.querySelectorAll('.col-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      currentCols = parseInt(btn.dataset.col);
      document.querySelectorAll('.col-btn').forEach(b => {
        b.classList.remove('toolbar-col-active', '!bg-indigo-500', '!text-white', '!border-indigo-500');
        b.classList.remove('bg-indigo-500');
      });
      btn.classList.add('toolbar-col-active');
      applyColumns(currentCols);
      document.getElementById('col-label-mobile').textContent = currentCols;
    });
  });
  document.querySelectorAll('.col-opt').forEach(a => {
    a.addEventListener('click', e => {
      e.preventDefault();
      currentCols = parseInt(a.dataset.col);
      applyColumns(currentCols);
      document.getElementById('col-label-mobile').textContent = currentCols;
    });
  });

  // Select all / clear
  document.getElementById('btn-select-all')?.addEventListener('click', selectAll);
  document.getElementById('btn-clear-all')?.addEventListener('click', clearCart);

  // Package chips
  document.querySelectorAll('.package-btn').forEach(btn => {
    btn.addEventListener('click', () => selectPackage(btn));
  });

  // Lightbox nav
  document.getElementById('lb-prev')?.addEventListener('click', () => lbNav(-1));
  document.getElementById('lb-next')?.addEventListener('click', () => lbNav(1));
  document.getElementById('lb-cart-btn')?.addEventListener('click', () => {
    toggleSelect(currentLbIdx);
    lbShow(currentLbIdx);
  });

  // Keyboard nav
  document.addEventListener('keydown', e => {
    const lb = document.getElementById('lightboxModal');
    if (!lb.classList.contains('active')) return;
    if (e.key === 'ArrowLeft') lbNav(-1);
    if (e.key === 'ArrowRight') lbNav(1);
    if (e.key === 'Escape') closeLightbox();
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initGallery);
} else {
  initGallery();
}

// ============================================
// Load photos (AJAX)
// ============================================
async function loadPhotos() {
  document.getElementById('gallery-loading').style.display = 'flex';
  document.getElementById('gallery-container').style.display = 'none';
  document.getElementById('gallery-error').style.display = 'none';

  try {
    const res = await fetch(`/api/drive/${EVENT_ID}`, {
      headers: { 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' }
    });
    if (!res.ok) throw new Error('Failed to load photos');
    const data = await res.json();
    allPhotos = data.files ?? data ?? [];
    renderGallery(allPhotos);
  } catch (err) {
    document.getElementById('gallery-loading').style.display = 'none';
    const errEl = document.getElementById('gallery-error');
    document.getElementById('gallery-error-msg').textContent = 'ไม่สามารถโหลดรูปภาพได้ กรุณาลองใหม่อีกครั้ง';
    errEl.style.display = 'block';
  }
}

// ============================================
// Render gallery — OPTIMIZED
// ============================================
const THUMB_SIZE = GALLERY_THUMB_SIZE;                // 1× size (admin-set)
const THUMB_SIZE_2X = Math.min(GALLERY_THUMB_SIZE * 2, 800);  // retina
// IMPORTANT: this URL is sometimes used inside `srcset="..."`, where the
// HTML parser treats whitespace as the URL/descriptor separator. The
// previous version had literal spaces inside the SVG attributes (between
// `xmlns='...'`, `width='1'`, `height='1'`) — perfectly valid as an
// `<img src=...>` value but it broke `srcset` parsing with
// "Failed parsing 'srcset' attribute value since it has an unknown
// descriptor" + "Dropped srcset candidate". %20 the spaces so the URL is
// a single token regardless of the attribute it lands in.
const PLACEHOLDER_SVG = "data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='1'%20height='1'%3E%3C/svg%3E";

// Lazy observer with large rootMargin for early preload.
// We render each <img> with a 1x1 placeholder + data-src attribute, then
// swap to the real URL the moment the IO callback says the row is within
// 800px of the viewport. data-srcset handling was removed in the same
// commit that dropped 1x/2x srcset duplication — the proxy already
// serves a retina-resolution variant when the device is retina.
const lazyObserver = new IntersectionObserver((entries) => {
  for (const entry of entries) {
    if (entry.isIntersecting) {
      const img = entry.target.querySelector('img');
      if (img?.dataset.src) {
        img.src = img.dataset.src;
        img.removeAttribute('data-src');
      }
      lazyObserver.unobserve(entry.target);
    }
  }
}, { rootMargin: '800px 0px' }); // preload 800px ahead

function renderGallery(photos) {
  document.getElementById('gallery-loading').style.display = 'none';
  const empty = document.getElementById('gallery-empty');
  if (empty) empty.style.display = 'none';

  if (!photos || photos.length === 0) {
    document.getElementById('gallery-container').style.display = 'none';
    if (empty) empty.style.display = 'block';
    return;
  }

  const grid = document.getElementById('gallery-grid');
  grid.innerHTML = '';

  const priceHtml = (PRICE_PER > 0 || BASE_PRICE_PER > 0)
    ? `<div class="absolute bottom-0 left-0 right-0 px-2 pb-1.5 pt-5 bg-gradient-to-t from-black/60 to-transparent text-white text-[0.68rem] font-semibold text-right pointer-events-none z-[3]">${numberFmt(PRICE_PER > 0 ? PRICE_PER : BASE_PRICE_PER)} ฿</div>`
    : '';

  // Admin-tunable eager count (default 12). The first N load immediately
  // for fast first-paint; the rest lazy-load as they approach the viewport.
  // The very first few also get `fetchpriority=high` to nudge the browser
  // past preloaded CSS/fonts and start bytes flowing ASAP.
  const EAGER_COUNT = GALLERY_EAGER_COUNT;
  const HIGH_PRIORITY_COUNT = Math.min(EAGER_COUNT, 5);
  const BATCH = 60;
  let rendered = 0;

  function renderBatch() {
    const fragment = document.createDocumentFragment();
    const end = Math.min(rendered + BATCH, photos.length);

    // Pick a single thumbnail URL based on the device pixel ratio.
    // Previously the gallery emitted `srcset="${1x} 1x, ${2x} 2x"` so the
    // browser pre-resolved BOTH URLs even though it only used one — that
    // doubled the request count for retina users and was the proximate
    // cause of the proxy throttle (120/min) kicking in mid-grid and
    // dropping the bottom rows. Picking one URL per device upfront cuts
    // the request count in half with zero visible quality difference.
    const dpr = (typeof window !== 'undefined' && window.devicePixelRatio) || 1;
    const useRetina = dpr > 1.4;

    for (let idx = rendered; idx < end; idx++) {
      const photo   = photos[idx];
      const name    = photo.name ?? photo.file_name ?? `Photo ${idx + 1}`;
      const thumb1x = getThumbUrl(photo, useRetina ? THUMB_SIZE_2X : THUMB_SIZE);
      const isEager = idx < EAGER_COUNT;
      const isHighPrio = idx < HIGH_PRIORITY_COUNT;

      const item = document.createElement('div');
      item.className = 'g-item g-item-bg relative aspect-square rounded-lg overflow-hidden cursor-pointer transition-all duration-200 hover:-translate-y-0.5 hover:shadow-xl';
      item.dataset.idx = idx;

      // Width/height lock the aspect ratio so the browser can reserve space
      // before the image loads — prevents layout shift (CLS) when batches
      // render. The square grid cell is enforced by `aspect-square`, but
      // explicit dimensions also let the browser calculate the intrinsic size.
      const dims = `width="${THUMB_SIZE}" height="${THUMB_SIZE}"`;
      // Single src per <img> — no srcset attribute. Halves request count
      // vs. the previous 1x+2x duplicate, and removes the "Failed parsing
      // srcset" warning that fired when the URL contained spaces (data
      // URLs, Drive thumbnailLinks with embedded params, etc.).
      const imgAttrs = isEager
        ? `src="${thumb1x}"`
        : `src="${PLACEHOLDER_SVG}" data-src="${thumb1x}"`;
      const priorityAttr = isHighPrio ? ' fetchpriority="high"' : '';

      item.innerHTML =
        `<img ${imgAttrs} ${dims} alt="${escHtml(name)}" class="w-full h-full object-cover transition-transform duration-500 select-none" loading="${isEager ? 'eager' : 'lazy'}" decoding="async"${priorityAttr} onerror="handleImgError(this,${idx})">` +
        `<div class="g-overlay absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-transparent opacity-0 transition-opacity duration-300 pointer-events-none z-[1]"></div>` +
        `<div class="absolute inset-0 flex items-center justify-center text-sm font-black text-white/30 tracking-widest uppercase pointer-events-none select-none whitespace-nowrap z-[2]" style="text-shadow:0 1px 3px rgba(0,0,0,0.4);transform:rotate(-25deg);">PREVIEW</div>` +
        `<div class="g-check" onclick="event.stopPropagation();toggleSelect(${idx})"><i class="bi bi-check-lg"></i></div>` +
        `<button class="g-zoom" onclick="event.stopPropagation();openLightbox(${idx})" title="ดูขยาย"><i class="bi bi-zoom-in"></i></button>` +
        priceHtml;

      item.addEventListener('click', () => toggleSelect(idx));
      fragment.appendChild(item);

      // Only lazy-observe non-eager images
      if (!isEager) lazyObserver.observe(item);
    }

    grid.appendChild(fragment);
    rendered = end;

    if (rendered < photos.length) {
      requestAnimationFrame(renderBatch);
    }
  }

  renderBatch();

  document.getElementById('gallery-container').style.display = 'block';
  document.getElementById('gallery-toolbar').style.display = '';
  document.getElementById('photo-count').innerHTML = `<span class="text-white dark:text-white">${photos.length}</span> รูป`;
  const heroNum = document.getElementById('hero-photo-num');
  if (heroNum) heroNum.textContent = photos.length;
  applyColumns(currentCols);
  // Sync the mobile dropdown label to the actual current column count
  // (the static markup hardcodes "2" but currentCols may differ on
  // tablets/desktops depending on viewport at boot).
  const colLabelMobile = document.getElementById('col-label-mobile');
  if (colLabelMobile) colLabelMobile.textContent = currentCols;
  // Highlight the matching desktop col-btn so the selected density
  // visually matches the active grid.
  document.querySelectorAll('.col-btn').forEach(b => {
    if (parseInt(b.dataset.col) === currentCols) {
      b.classList.add('toolbar-col-active');
    } else {
      b.classList.remove('toolbar-col-active');
    }
  });
}

function applyColumns(n) {
  const grid = document.getElementById('gallery-grid');
  if (!grid) return;
  grid.className = `g-gallery col-${n}`;
}

// ============================================
// Selection
// ============================================
function addToCartFromLightbox(idx) {
  toggleSelect(idx);
  // lbShow is now called from the onclick directly
}

function toggleSelect(idx) {
  const items = document.querySelectorAll('.g-item');
  if (selected.has(idx)) {
    selected.delete(idx);
    items[idx]?.classList.remove('selected');
  } else {
    if (activePackage && selected.size >= activePackage.count) {
      Swal.fire({
        icon: 'warning', title: 'เกินจำนวนแพ็กเกจ',
        text: `แพ็กเกจนี้เลือกได้สูงสุด ${activePackage.count} รูป`,
        confirmButtonColor: '#6366f1', timer: 2500,
      });
      return;
    }
    selected.add(idx);
    items[idx]?.classList.add('selected');
  }
  updateCartBar();
}

function selectAll() {
  const items = document.querySelectorAll('.g-item');
  const limit = activePackage ? activePackage.count : allPhotos.length;
  allPhotos.forEach((_, idx) => {
    if (selected.size < limit) {
      selected.add(idx);
      items[idx]?.classList.add('selected');
    }
  });
  updateCartBar();
  if (activePackage && allPhotos.length > limit) {
    Swal.fire({ icon: 'info', title: `เลือกได้สูงสุด ${limit} รูป`, text: 'ตามแพ็กเกจที่เลือก', confirmButtonColor: '#6366f1', timer: 2500 });
  }
}

function clearCart() {
  const items = document.querySelectorAll('.g-item');
  selected.forEach(idx => items[idx]?.classList.remove('selected'));
  selected.clear();
  updateCartBar();
}

function updateCartBar() {
  const count   = selected.size;
  const cartBar  = document.getElementById('cart-bar');
  const clearBtn = document.getElementById('btn-clear-all');
  const selLabel = document.getElementById('selected-label');

  let total, priceLabel;
  let canCheckout = count > 0;
  if (activePackage) {
    total = activePackage.price;
    const remaining = activePackage.count - count;
    if (remaining > 0) {
      priceLabel = `แพ็กเกจ ${numberFmt(activePackage.price)} ฿ (เลือกอีก ${remaining} รูป)`;
      canCheckout = false;
    } else {
      priceLabel = `แพ็กเกจ ${numberFmt(activePackage.price)} ฿ ✓ ครบแล้ว`;
    }
  } else {
    const effectivePrice = PRICE_PER > 0 ? PRICE_PER : BASE_PRICE_PER;
    total = count * effectivePrice;
    if (effectivePrice > 0) {
      priceLabel = `รวม ${numberFmt(total)} ฿ (${numberFmt(effectivePrice)} ฿/รูป)`;
      if (HAS_PACKAGES && count > 0) priceLabel += ' — เลือกแพ็กเกจเพื่อความคุ้มค่า';
    } else {
      priceLabel = count > 0 ? 'ฟรี' : '';
    }
  }

  document.getElementById('cart-count-text').textContent = activePackage
    ? `เลือก ${count} / ${activePackage.count} รูป`
    : `เลือก ${count} รูป`;
  document.getElementById('cart-total-text').textContent = priceLabel;
  document.getElementById('cart-badge-count').textContent = count;

  if (selLabel) selLabel.textContent = count > 0
    ? (activePackage ? `เลือก ${count}/${activePackage.count} รูป` : `เลือก ${count} รูป`)
    : '';

  const cartBtn  = document.querySelector('.btn-cart-go') ?? document.querySelector('[onclick="goToCart()"]');
  const expressBtn = document.querySelector('.btn-express-buy') ?? document.querySelector('[onclick="expressBuy()"]');
  if (cartBtn) { cartBtn.disabled = !canCheckout; cartBtn.style.opacity = canCheckout ? '1' : '0.5'; }
  if (expressBtn) { expressBtn.disabled = !canCheckout; expressBtn.style.opacity = canCheckout ? '1' : '0.5'; }

  if (count > 0) {
    cartBar.style.display = 'block';
    clearBtn.style.display = 'inline-flex';
  } else {
    cartBar.style.display = 'none';
    clearBtn.style.display = 'none';
  }
}

// ============================================
// Packages
// ============================================
function selectPackage(btn) {
  const isActive = btn.classList.contains('active');
  document.querySelectorAll('.package-btn').forEach(b => b.classList.remove('active'));
  if (isActive) { deselectPackage(); return; }
  btn.classList.add('active');
  activePackage = {
    id: btn.dataset.packageId,
    count: parseInt(btn.dataset.count),
    price: parseFloat(btn.dataset.price),
  };

  if (selected.size > activePackage.count) {
    const items = document.querySelectorAll('.g-item');
    const excess = selected.size - activePackage.count;
    const toRemove = [...selected].slice(-excess);
    toRemove.forEach(idx => { selected.delete(idx); items[idx]?.classList.remove('selected'); });
    Swal.fire({ icon: 'info', title: 'ปรับจำนวนรูปอัตโนมัติ', html: `ตัดรูปที่เกินออก <b>${excess}</b> รูป ให้เหลือ <b>${activePackage.count}</b> รูป ตามแพ็กเกจ`, confirmButtonColor: '#6366f1', timer: 3000, timerProgressBar: true });
  }

  const infoBar = document.getElementById('pkg-info-bar');
  document.getElementById('pkg-info-text').textContent =
    `แพ็กเกจ ${btn.dataset.count} รูป — ${numberFmt(activePackage.price)} ฿ (เลือกได้สูงสุด ${btn.dataset.count} รูป)`;
  infoBar.style.display = 'block';
  updatePriceTags();
  updateCartBar();
}

function deselectPackage() {
  activePackage = null;
  document.querySelectorAll('.package-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('pkg-info-bar').style.display = 'none';
  document.getElementById('pkg-info-text').textContent = '';
  updatePriceTags();
  updateCartBar();
}

function updatePriceTags() {
  const priceDivs = document.querySelectorAll('.g-item > div:last-child[class*="absolute"]');
  priceDivs.forEach(div => {
    if (!div.classList.contains('absolute') || !div.textContent.includes('฿')) return;
    if (activePackage) {
      const perPhoto = activePackage.price / activePackage.count;
      div.textContent = `${numberFmt(perPhoto)} ฿`;
      div.title = `แพ็กเกจ ${numberFmt(activePackage.price)} ฿ / ${activePackage.count} รูป`;
    } else {
      const baseP = PRICE_PER > 0 ? PRICE_PER : BASE_PRICE_PER;
      div.textContent = baseP > 0 ? `${numberFmt(baseP)} ฿` : 'ฟรี';
      div.title = '';
    }
  });
  const lbPrice = document.getElementById('lb-footer-price');
  if (lbPrice) {
    if (activePackage) {
      const perPhoto = activePackage.price / activePackage.count;
      lbPrice.textContent = `${numberFmt(perPhoto)} ฿ / รูป (แพ็กเกจ)`;
    } else {
      const baseP = PRICE_PER > 0 ? PRICE_PER : BASE_PRICE_PER;
      lbPrice.textContent = baseP > 0 ? `${numberFmt(baseP)} ฿ / รูป` : 'ฟรี';
    }
  }
}

// ============================================
// Login prompt
// ============================================
function promptLogin() {
  Swal.fire({
    icon: 'info', title: 'กรุณาเข้าสู่ระบบ',
    text: 'คุณต้องเข้าสู่ระบบก่อนจึงจะสามารถซื้อรูปภาพได้',
    confirmButtonText: '<i class="bi bi-box-arrow-in-right mr-1"></i>เข้าสู่ระบบ',
    cancelButtonText: 'ยกเลิก', showCancelButton: true, confirmButtonColor: '#6366f1',
  }).then(result => { if (result.isConfirmed) window.location.href = '{{ route("login") }}'; });
}

// ============================================
// Cart checkout
// ============================================
function buildCartItems() {
  const cartItems = [];
  selected.forEach(idx => {
    const p = allPhotos[idx];
    if (p) {
      cartItems.push({
        event_id: EVENT_ID,
        file_id: p.id ?? p.file_id,
        name: p.name ?? p.file_name ?? `Photo ${idx + 1}`,
        thumbnail: getThumbUrl(p, 200),
        price: activePackage ? (activePackage.price / activePackage.count) : (PRICE_PER > 0 ? PRICE_PER : BASE_PRICE_PER),
        package_id: activePackage?.id ?? null,
      });
    }
  });
  return cartItems;
}

function validateCartSelection() {
  if (activePackage) {
    const diff = activePackage.count - selected.size;
    if (diff > 0) {
      Swal.fire({ icon: 'warning', title: 'เลือกรูปไม่ครบ', html: `แพ็กเกจนี้ต้องเลือก <b>${activePackage.count}</b> รูป<br>คุณเลือกไว้ <b>${selected.size}</b> รูป — เลือกเพิ่มอีก <b>${diff}</b> รูป`, confirmButtonText: 'เลือกรูปเพิ่ม', confirmButtonColor: '#6366f1' });
      return false;
    }
  }
  return true;
}

async function goToCart() {
  if (selected.size === 0) return;
  if (!validateCartSelection()) return;
  const cartItems = buildCartItems();
  if (cartItems.length === 0) return;

  const btn = document.querySelector('[onclick="goToCart()"]');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>กำลังดำเนินการ...'; }

  try {
    const res = await fetch('/cart/add-bulk', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
      body: JSON.stringify({ items: cartItems }),
    });
    if (res.ok) { window.location.href = '{{ route("cart.index") }}'; }
    else throw new Error('Failed');
  } catch (e) {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-bag-check-fill"></i> ดูตะกร้า'; }
    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเพิ่มสินค้าลงตะกร้าได้ กรุณาลองใหม่' });
  }
}

// One-click buy from inside the lightbox: auto-selects the currently viewed
// photo (if not already selected) and reuses the regular expressBuy() path.
// This is the fastest lane we offer — from "zoomed into a single photo" to
// "QR code on screen" in 2 clicks.
async function lbBuyNow() {
  const photo = allPhotos[currentLbIdx];
  if (!photo) return;
  // Ensure this photo is in the selection set; expressBuy() reads from
  // `selected` so we have to seed it before calling through.
  if (!selected.has(photo.id ?? photo.file_id)) {
    toggleSelect(currentLbIdx);
  }
  await expressBuy();
}

async function expressBuy() {
  if (selected.size === 0) return;
  if (!validateCartSelection()) return;
  const cartItems = buildCartItems();
  if (cartItems.length === 0) return;

  const perPhoto = activePackage ? (activePackage.price / activePackage.count) : (PRICE_PER > 0 ? PRICE_PER : BASE_PRICE_PER);
  const totalPrice = activePackage ? activePackage.price : (cartItems.length * perPhoto);
  const result = await Swal.fire({
    icon: 'question', title: 'ยืนยันการซื้อ',
    html: `<div style="font-size:1.05rem;"><b>${cartItems.length}</b> รูป — รวม <b>${numberFmt(totalPrice)} ฿</b></div>`,
    confirmButtonText: '<i class="bi bi-lightning-charge-fill mr-1"></i>ซื้อเลย',
    cancelButtonText: 'ยกเลิก', showCancelButton: true, confirmButtonColor: '#f59e0b', cancelButtonColor: '#6b7280',
  });
  if (!result.isConfirmed) return;

  const btn = document.querySelector('[onclick="expressBuy()"]');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2"></span>กำลังสร้างคำสั่งซื้อ...'; }

  try {
    const res = await fetch('/orders/express', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TOKEN, 'Accept': 'application/json' },
      body: JSON.stringify({ items: cartItems, package_id: activePackage?.id ?? null }),
    });
    const data = await res.json();
    if (res.ok && data.redirect) { window.location.href = data.redirect; }
    else throw new Error(data.message || 'Failed');
  } catch (e) {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-lightning-charge-fill mr-1"></i>ซื้อเลย'; }
    Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: e.message || 'ไม่สามารถสร้างคำสั่งซื้อได้ กรุณาลองใหม่' });
  }
}

// ============================================
// Lightbox — Cinematic Photo Viewer
// ============================================
const LB_FULL_SIZE = 1200;
const lbCache = new Map();
let lbThumbsBuilt = false;

function lbGetFullUrl(photo) {
  // Lightbox uses the SAME baked file the gallery thumbnail uses — the
  // watermarked variant is already a self-contained, small (1200-1600px),
  // watermark-baked JPEG that can be displayed enlarged via CSS.
  // We deliberately DO NOT request a fresh sz=1200 from the proxy here
  // because that path would invoke the inline-watermark recovery
  // (DriveController::proxyImage), which fetches the original from R2,
  // composites a watermark in PHP/GD, and returns the bytes — a heavy
  // operation that 502'd under any concurrent load.
  if (photo.watermarked) return photo.watermarked;
  const thumb = photo.thumbnailLink ?? photo.thumbnail_link ?? '';
  if (thumb && !thumb.includes('googleusercontent.com') && !thumb.includes('drive.google.com')) return thumb;
  const fileId = photo.id ?? photo.file_id ?? '';
  // sz=400 hits the proxy's redirect-only branch (size ≤ 500), so the
  // proxy just 302s to the existing thumbnail/watermarked CDN URL — no
  // server-side watermarking, no 502.
  return `/api/drive/image/${fileId}?sz=400`;
}

function lbGetSmallUrl(photo) {
  const thumb = photo.thumbnailLink ?? photo.thumbnail_link ?? photo.fallback ?? '';
  if (thumb && !thumb.includes('googleusercontent.com') && !thumb.includes('drive.google.com')) return thumb;
  const fileId = photo.id ?? photo.file_id ?? '';
  return `/api/drive/image/${fileId}?sz=400`;
}

function lbPreload(idx) {
  if (idx < 0 || idx >= allPhotos.length) return;
  const url = lbGetFullUrl(allPhotos[idx]);
  if (lbCache.has(url)) return;
  const img = new Image();
  img.src = url;
  lbCache.set(url, img);
}

function buildThumbnailStrip() {
  if (lbThumbsBuilt) return;
  const strip = document.getElementById('lb-thumbstrip');
  if (!strip || allPhotos.length === 0) return;
  // Limit to max 100 thumbs for performance
  const maxThumbs = Math.min(allPhotos.length, 100);
  const frag = document.createDocumentFragment();
  for (let i = 0; i < maxThumbs; i++) {
    const div = document.createElement('div');
    div.className = 'lb-thumb';
    div.dataset.idx = i;
    div.innerHTML = `<img src="${getThumbUrl(allPhotos[i], 80)}" alt="" loading="lazy" decoding="async">`;
    div.addEventListener('click', () => lbShow(i));
    frag.appendChild(div);
  }
  strip.appendChild(frag);
  lbThumbsBuilt = true;
}

function updateThumbActive(idx) {
  const strip = document.getElementById('lb-thumbstrip');
  if (!strip) return;
  strip.querySelectorAll('.lb-thumb').forEach((t, i) => {
    t.classList.toggle('active', i === idx);
  });
  // Scroll active thumb into view
  const active = strip.querySelector('.lb-thumb.active');
  if (active) active.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
}

function toggleLbUi() {
  document.getElementById('lightboxModal')?.classList.toggle('lb-hide-ui');
}

function openLightbox(idx) {
  currentLbIdx = idx;
  const el = document.getElementById('lightboxModal');
  const backdrop = el.querySelector('.lb-backdrop');

  el.style.display = 'flex';
  backdrop.style.opacity = '0';
  document.body.style.overflow = 'hidden';

  buildThumbnailStrip();
  lbShow(idx);

  requestAnimationFrame(() => {
    el.classList.add('active');
    backdrop.style.opacity = '1';
  });
}

function closeLightbox() {
  const el = document.getElementById('lightboxModal');
  if (!el) return;
  const backdrop = el.querySelector('.lb-backdrop');
  backdrop.style.opacity = '0';

  setTimeout(() => {
    el.style.display = 'none';
    el.classList.remove('active');
    el.classList.remove('lb-hide-ui');
    document.body.style.overflow = '';
  }, 250);
}

function lbShow(idx) {
  const p = allPhotos[idx];
  if (!p) return;
  const name = p.name ?? p.file_name ?? `Photo ${idx + 1}`;
  const lbImg  = document.getElementById('lb-img');
  const spinner = document.getElementById('lb-spinner');
  const fullUrl = lbGetFullUrl(p);
  const smallUrl = lbGetSmallUrl(p);

  // Transition: fade out, swap, fade in
  lbImg.classList.remove('lb-loaded');
  lbImg.classList.add('lb-loading');
  spinner.style.display = 'block';
  lbImg.src = smallUrl;

  const cached = lbCache.get(fullUrl);
  if (cached && cached.complete && cached.naturalWidth > 0) {
    lbImg.src = fullUrl;
    lbImg.classList.remove('lb-loading');
    lbImg.classList.add('lb-loaded');
    spinner.style.display = 'none';
  } else {
    const full = cached || new Image();
    if (!cached) { full.src = fullUrl; lbCache.set(fullUrl, full); }
    full.onload = () => {
      if (currentLbIdx === idx) {
        lbImg.src = fullUrl;
        lbImg.classList.remove('lb-loading');
        lbImg.classList.add('lb-loaded');
        spinner.style.display = 'none';
      }
    };
    full.onerror = () => {
      if (currentLbIdx === idx) {
        lbImg.classList.remove('lb-loading');
        lbImg.classList.add('lb-loaded');
        spinner.style.display = 'none';
      }
    };
  }

  // Preload neighbors
  lbPreload(idx + 1);
  lbPreload(idx - 1);
  lbPreload(idx + 2);

  // Update UI text
  document.getElementById('lb-counter').textContent = `${idx + 1} / ${allPhotos.length}`;
  document.getElementById('lb-name').textContent = name;
  document.getElementById('lb-footer-name').textContent = name;
  if (activePackage) {
    const perPhoto = activePackage.price / activePackage.count;
    document.getElementById('lb-footer-price').textContent = `${numberFmt(perPhoto)} ฿ / รูป (แพ็กเกจ)`;
  } else {
    document.getElementById('lb-footer-price').textContent = PRICE_PER > 0
      ? `${numberFmt(PRICE_PER)} ฿ / รูป`
      : (BASE_PRICE_PER > 0 ? `เริ่มต้น ${numberFmt(BASE_PRICE_PER)} ฿ / รูป` : 'ฟรี');
  }

  // Selection state UI
  const isSelected = selected.has(idx);
  const selectBtn = document.getElementById('lb-select-btn');
  if (selectBtn) {
    if (isSelected) {
      selectBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i> <span>เลือกแล้ว</span>';
      selectBtn.style.background = 'rgba(16,185,129,0.2)';
      selectBtn.style.color = '#34d399';
      selectBtn.style.borderColor = 'rgba(16,185,129,0.3)';
    } else {
      selectBtn.innerHTML = '<i class="bi bi-check-circle"></i> <span>เลือกรูปนี้</span>';
      selectBtn.style.background = 'rgba(255,255,255,0.06)';
      selectBtn.style.color = 'rgba(255,255,255,0.8)';
      selectBtn.style.borderColor = 'rgba(255,255,255,0.1)';
    }
  }
  const cartBtn = document.getElementById('lb-cart-btn');
  if (cartBtn) {
    if (isSelected) {
      cartBtn.innerHTML = '<i class="bi bi-cart-check-fill"></i> <span>อยู่ในตะกร้าแล้ว</span>';
      cartBtn.style.background = 'rgba(16,185,129,0.8)';
      cartBtn.style.boxShadow = '0 4px 16px rgba(16,185,129,0.3)';
    } else {
      cartBtn.innerHTML = '<i class="bi bi-cart-plus"></i> <span>เพิ่มลงตะกร้า</span>';
      cartBtn.style.background = 'linear-gradient(135deg,#6366f1,#8b5cf6)';
      cartBtn.style.boxShadow = '0 4px 16px rgba(99,102,241,0.3)';
    }
  }

  updateThumbActive(idx);
  currentLbIdx = idx;
}

function lbNav(dir) {
  const next = (currentLbIdx + dir + allPhotos.length) % allPhotos.length;
  lbShow(next);
}

// Swipe support for mobile
(function initLbSwipe() {
  let startX = 0, startY = 0, swiping = false;
  const area = document.getElementById('lb-image-area');
  if (!area) return;
  area.addEventListener('touchstart', e => {
    if (e.touches.length === 1) { startX = e.touches[0].clientX; startY = e.touches[0].clientY; swiping = true; }
  }, { passive: true });
  area.addEventListener('touchmove', e => {}, { passive: true });
  area.addEventListener('touchend', e => {
    if (!swiping) return;
    swiping = false;
    const dx = e.changedTouches[0].clientX - startX;
    const dy = e.changedTouches[0].clientY - startY;
    if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) {
      if (dx > 0) lbNav(-1); else lbNav(1);
    }
  }, { passive: true });
})();

// ============================================
// Share / copy link
// ============================================
function copyEventLink(e) {
  e.preventDefault();
  const url = window.location.href;
  navigator.clipboard?.writeText(url).then(() => {
    const el = e.target.closest('a');
    const orig = el.innerHTML;
    el.innerHTML = '<i class="bi bi-check-lg mr-2"></i>คัดลอกแล้ว!';
    setTimeout(() => { el.innerHTML = orig; }, 2000);
  }).catch(() => {
    const ta = document.createElement('textarea');
    ta.value = url; document.body.appendChild(ta); ta.select();
    document.execCommand('copy'); document.body.removeChild(ta);
  });
}

// ============================================
// Helpers
// ============================================
function getThumbUrl(photo, size) {
  const apiThumb = photo.thumbnailLink ?? photo.thumbnail_link ?? photo.fallback ?? '';
  if (apiThumb && !apiThumb.includes('googleusercontent.com') && !apiThumb.includes('drive.google.com')) return apiThumb;
  const fileId = photo.id ?? photo.file_id ?? '';
  if (/^\d+$/.test(fileId)) {
    // R2 photo. Prefer the baked thumbnail/watermarked URL if either is
    // populated. If both are empty (the model accessors now return ''
    // when the variant_path is missing — see EventPhoto.php — to avoid
    // leaking the un-watermarked original), fall through to the proxy
    // endpoint, which knows how to inline-watermark on the fly from
    // the R2 source bytes (DriveController::proxyImage recovery path).
    // Avoid PLACEHOLDER_SVG here: it contains literal spaces inside the
    // SVG attributes that break <img srcset> parsing in Chrome — the
    // browser drops the candidate with "Unknown descriptor" and the
    // gallery image fails to render.
    return apiThumb || photo.watermarked || `/api/drive/image/${fileId}?sz=${size}`;
  }
  return `/api/drive/image/${fileId}?sz=${size}`;
}

function handleImgError(img, idx) {
  if (img.dataset.retried) {
    img.src = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='200' height='200'%3E%3Crect fill='%23f1f5f9' width='200' height='200'/%3E%3Ctext x='50%25' y='50%25' text-anchor='middle' fill='%2394a3b8' font-size='14'%3ENo Image%3C/text%3E%3C/svg%3E";
    return;
  }
  img.dataset.retried = '1';
  const photo = allPhotos[idx];
  if (photo) {
    const fileId = photo.id ?? photo.file_id ?? '';
    img.src = 'https://drive.google.com/thumbnail?id=' + fileId + '&sz=w400';
  }
}

function numberFmt(n) { return new Intl.NumberFormat('th-TH').format(Math.round(n)); }
function escHtml(str) { return str.replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }
</script>
@endpush

{{-- ============ Reviews Section ============ --}}
@php
  $eventReviewsQuery = \App\Models\Review::where('event_id', $event->id)->where('status', 'approved')->where('is_visible', true);
  $reviewStats = \App\Models\Review::statsFor($eventReviewsQuery);
  $reviewsList = \App\Models\Review::with(['user', 'event'])
      ->where('event_id', $event->id)
      ->where('status', 'approved')
      ->where('is_visible', true)
      ->orderByDesc('helpful_count')
      ->orderByDesc('created_at')
      ->limit(10)
      ->get();
@endphp

@if($reviewStats['total'] > 0)
<section class="bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-white/[0.06] py-12">
  <div class="max-w-5xl mx-auto px-4">
    <div class="flex items-end justify-between mb-6">
      <div>
        <h2 class="text-2xl md:text-3xl font-bold text-slate-800 dark:text-gray-100">
          <i class="bi bi-star-fill text-amber-400 mr-2"></i>รีวิวจากลูกค้า
        </h2>
        <p class="text-sm text-gray-500 mt-1">ความคิดเห็นจากผู้ที่เคยซื้อภาพจากอีเวนต์นี้</p>
      </div>
      @if($reviewStats['total'] > 10)
        <a href="{{ route('reviews.index', ['event_id' => $event->id]) }}" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium">
          ดูทั้งหมด {{ $reviewStats['total'] }} รีวิว →
        </a>
      @endif
    </div>

    @include('public.reviews._section', ['reviewStats' => $reviewStats, 'reviewsList' => $reviewsList, 'showEvent' => false])
  </div>
</section>
@endif

@endsection
