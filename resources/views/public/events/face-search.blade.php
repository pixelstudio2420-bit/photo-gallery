@extends('layouts.app')

@section('title', 'ค้นหารูปด้วยใบหน้า — ' . $event->name)

@push('styles')
<style>
/* ── Hero ── */
.face-hero {
  background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
  padding: 3rem 0 2.5rem;
  position: relative;
  overflow: hidden;
}
.face-hero::before {
  content: '';
  position: absolute; inset: 0;
  background: radial-gradient(circle at 20% 50%, rgba(99,102,241,0.15) 0%, transparent 50%),
              radial-gradient(circle at 80% 20%, rgba(139,92,246,0.12) 0%, transparent 50%),
              radial-gradient(circle at 60% 80%, rgba(59,130,246,0.1) 0%, transparent 50%);
  pointer-events: none;
}
.face-hero .container { position: relative; z-index: 1; }
.hero-breadcrumb a { color: rgba(255,255,255,0.45); text-decoration: none; font-size: 0.8rem; transition: color 0.2s; }
.hero-breadcrumb a:hover { color: rgba(255,255,255,0.8); }
.hero-breadcrumb .separator { color: rgba(255,255,255,0.25); margin: 0 0.5rem; font-size: 0.7rem; }
.hero-breadcrumb .current { color: rgba(255,255,255,0.3); font-size: 0.8rem; }
.hero-title {
  color: #fff; font-weight: 800; font-size: 1.75rem; letter-spacing: -0.025em;
  margin-top: 1rem; display: flex; align-items: center; gap: 0.75rem;
}
.hero-title .icon-wrap {
  width: 48px; height: 48px; border-radius: 14px;
  background: linear-gradient(135deg, rgba(99,102,241,0.3), rgba(139,92,246,0.3));
  display: flex; align-items: center; justify-content: center;
  border: 1px solid rgba(255,255,255,0.1);
  backdrop-filter: blur(8px); flex-shrink: 0;
}
.hero-title .icon-wrap i { font-size: 1.4rem; color: #a5b4fc; }
.hero-subtitle {
  color: rgba(255,255,255,0.5); font-size: 0.9rem; margin-top: 0.5rem;
  max-width: 540px; line-height: 1.6;
}

/* ── Steps indicator ── */
.steps-bar {
  display: flex; align-items: center; gap: 0.5rem;
  margin-top: 1.5rem; flex-wrap: wrap;
}
.step-item {
  display: flex; align-items: center; gap: 0.5rem;
  padding: 0.4rem 0.9rem; border-radius: 999px;
  font-size: 0.75rem; font-weight: 600; transition: all 0.3s;
}
.step-item.active {
  background: rgba(99,102,241,0.2); color: #a5b4fc;
  border: 1px solid rgba(99,102,241,0.3);
}
.step-item.inactive { color: rgba(255,255,255,0.25); }
.step-item .step-num {
  width: 20px; height: 20px; border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 0.65rem; font-weight: 700;
}
.step-item.active .step-num { background: rgba(99,102,241,0.4); color: #c7d2fe; }
.step-item.inactive .step-num { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.3); }
.step-connector { width: 24px; height: 1px; background: rgba(255,255,255,0.1); }

/* ── Main card ── */
.upload-card {
  background: #fff; border-radius: 20px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 8px 32px rgba(0,0,0,0.04);
  border: 1px solid rgba(0,0,0,0.04);
  overflow: hidden; transition: box-shadow 0.3s;
}
.upload-card:hover { box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 12px 40px rgba(0,0,0,0.08); }
.dark .upload-card {
  background: #1e293b; border-color: rgba(255,255,255,0.06);
  box-shadow: 0 1px 3px rgba(0,0,0,0.2), 0 8px 32px rgba(0,0,0,0.15);
}
.upload-card-header {
  padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(0,0,0,0.05);
  display: flex; align-items: center; gap: 0.75rem;
}
.dark .upload-card-header { border-color: rgba(255,255,255,0.06); }
.upload-card-header .header-icon {
  width: 36px; height: 36px; border-radius: 10px;
  background: linear-gradient(135deg, #eef2ff, #e0e7ff);
  display: flex; align-items: center; justify-content: center;
}
.dark .upload-card-header .header-icon { background: linear-gradient(135deg, rgba(99,102,241,0.2), rgba(139,92,246,0.2)); }
.upload-card-header .header-icon i { color: #6366f1; font-size: 1rem; }
.upload-card-header h3 { font-size: 0.95rem; font-weight: 700; margin: 0; color: #1e293b; }
.dark .upload-card-header h3 { color: #e2e8f0; }
.upload-card-header p { font-size: 0.78rem; color: #94a3b8; margin: 0; }
.upload-card-body { padding: 1.5rem; }

/* ── Upload zone ── */
.upload-zone {
  border: 2px dashed #e2e8f0; border-radius: 16px; padding: 3rem 2rem;
  text-align: center; cursor: pointer; transition: all 0.3s ease;
  background: #f8fafc; position: relative;
}
.dark .upload-zone { border-color: rgba(255,255,255,0.1); background: rgba(255,255,255,0.03); }
.upload-zone:hover, .upload-zone.dragover {
  border-color: #818cf8; background: rgba(99,102,241,0.03);
  box-shadow: 0 0 0 4px rgba(99,102,241,0.06);
}
.dark .upload-zone:hover, .dark .upload-zone.dragover {
  border-color: #6366f1; background: rgba(99,102,241,0.08);
  box-shadow: 0 0 0 4px rgba(99,102,241,0.1);
}
.upload-zone.has-image {
  border-color: #34d399; background: rgba(16,185,129,0.03); padding: 1.5rem;
  box-shadow: 0 0 0 4px rgba(16,185,129,0.06);
}
.dark .upload-zone.has-image {
  border-color: #10b981; background: rgba(16,185,129,0.08);
  box-shadow: 0 0 0 4px rgba(16,185,129,0.1);
}
.upload-icon-circle {
  width: 72px; height: 72px; border-radius: 50%; margin: 0 auto 1rem;
  background: linear-gradient(135deg, #eef2ff, #e0e7ff);
  display: flex; align-items: center; justify-content: center;
  transition: transform 0.3s;
}
.dark .upload-icon-circle { background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(139,92,246,0.15)); }
.upload-zone:hover .upload-icon-circle { transform: scale(1.05); }
.upload-icon-circle i { font-size: 1.8rem; color: #6366f1; }
.upload-zone h4 { font-size: 0.95rem; font-weight: 700; color: #334155; margin-bottom: 0.25rem; }
.dark .upload-zone h4 { color: #e2e8f0; }
.upload-zone .hint { font-size: 0.78rem; color: #94a3b8; margin: 0; }
.upload-formats {
  display: inline-flex; gap: 0.4rem; margin-top: 0.75rem;
}
.upload-formats span {
  padding: 0.2rem 0.55rem; border-radius: 6px; font-size: 0.65rem; font-weight: 600;
  background: rgba(99,102,241,0.08); color: #6366f1; text-transform: uppercase;
}
.dark .upload-formats span { background: rgba(99,102,241,0.15); color: #a5b4fc; }

/* ── Preview ── */
.selfie-preview-wrap {
  display: flex; flex-direction: column; align-items: center; gap: 0.75rem;
}
.selfie-preview {
  width: 180px; height: 180px; border-radius: 16px;
  object-fit: cover; box-shadow: 0 8px 24px rgba(0,0,0,0.12);
  border: 3px solid #fff;
}
.dark .selfie-preview { border-color: #334155; }
.preview-status {
  display: inline-flex; align-items: center; gap: 0.4rem;
  padding: 0.35rem 0.8rem; border-radius: 999px;
  background: rgba(16,185,129,0.1); color: #059669;
  font-size: 0.78rem; font-weight: 600;
}
.dark .preview-status { background: rgba(16,185,129,0.15); color: #34d399; }
.change-photo-btn {
  font-size: 0.75rem; color: #6366f1; cursor: pointer;
  text-decoration: underline; text-underline-offset: 2px;
  transition: color 0.2s;
}
.change-photo-btn:hover { color: #4f46e5; }

/* ── Search button ── */
.search-btn-wrap { text-align: center; margin-top: 1.5rem; }
.search-btn {
  background: linear-gradient(135deg, #6366f1, #4f46e5); color: #fff;
  border: none; border-radius: 14px; padding: 0.85rem 2.5rem;
  font-weight: 700; font-size: 0.9rem; transition: all 0.25s;
  display: inline-flex; align-items: center; gap: 0.5rem;
  box-shadow: 0 4px 14px rgba(99,102,241,0.3);
  cursor: pointer;
}
.search-btn:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(99,102,241,0.4);
  color: #fff;
}
.search-btn:active:not(:disabled) { transform: translateY(0); }
.search-btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none; box-shadow: none; }

/* ── Tips sidebar ── */
.tips-card {
  background: linear-gradient(135deg, #faf5ff, #eef2ff);
  border-radius: 16px; padding: 1.25rem;
  border: 1px solid rgba(139,92,246,0.1);
}
.dark .tips-card {
  background: linear-gradient(135deg, rgba(139,92,246,0.08), rgba(99,102,241,0.08));
  border-color: rgba(139,92,246,0.15);
}
.tips-card h4 {
  font-size: 0.85rem; font-weight: 700; color: #6d28d9; margin-bottom: 0.75rem;
  display: flex; align-items: center; gap: 0.4rem;
}
.dark .tips-card h4 { color: #a78bfa; }
.tip-item {
  display: flex; align-items: flex-start; gap: 0.6rem;
  margin-bottom: 0.6rem; font-size: 0.78rem; color: #64748b; line-height: 1.5;
}
.dark .tip-item { color: #94a3b8; }
.tip-item i { color: #8b5cf6; margin-top: 2px; flex-shrink: 0; font-size: 0.85rem; }

/* ── Results area ── */
.results-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;
}
.results-title {
  font-size: 1.1rem; font-weight: 800; color: #1e293b;
  display: flex; align-items: center; gap: 0.5rem;
}
.dark .results-title { color: #f1f5f9; }
.results-title .title-icon {
  width: 32px; height: 32px; border-radius: 8px;
  background: linear-gradient(135deg, #ecfdf5, #d1fae5);
  display: flex; align-items: center; justify-content: center;
}
.dark .results-title .title-icon { background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(52,211,153,0.15)); }
.results-title .title-icon i { color: #10b981; font-size: 0.9rem; }
.results-count {
  display: inline-flex; align-items: center; gap: 0.35rem;
  padding: 0.35rem 0.85rem; border-radius: 999px; font-size: 0.78rem; font-weight: 600;
  background: rgba(16,185,129,0.1); color: #059669;
}
.dark .results-count { background: rgba(16,185,129,0.15); color: #34d399; }

/* ── Package chip strip ─────────────────────────────────────────────────
   Lets the buyer pick a count-bundle (e.g. "5 รูป ฿199") and have the
   price tags + total recompute on the matched cards below. Mirrors the
   chip strip on the main event page so behavior is identical for buyers
   already familiar with that UX. Hidden by default — JS shows it on
   results render when packages exist. */
.pkg-strip-wrap {
  background: linear-gradient(180deg, #f8fafc 0%, transparent 100%);
  border: 1px solid rgba(99,102,241,0.10);
  border-radius: 14px;
  padding: 0.85rem 1rem 0.95rem;
  margin-bottom: 1rem;
}
.dark .pkg-strip-wrap {
  background: linear-gradient(180deg, rgba(99,102,241,0.05) 0%, transparent 100%);
  border-color: rgba(99,102,241,0.20);
}
.pkg-strip-label {
  display: flex; align-items: center; gap: 0.4rem;
  font-size: 0.78rem; font-weight: 700; color: #475569;
  margin-bottom: 0.5rem;
}
.dark .pkg-strip-label { color: #cbd5e1; }
.pkg-strip-label i { color: #6366f1; }
.pkg-strip {
  display: flex; align-items: center; gap: 0.55rem;
  overflow-x: auto; padding-bottom: 0.25rem; scrollbar-width: none;
}
.pkg-strip::-webkit-scrollbar { display: none; }
.pkg-chip {
  flex-shrink: 0;
  display: inline-flex; align-items: center; gap: 0.45rem;
  background: #fff;
  border: 1.5px solid #e2e8f0;
  padding: 0.55rem 0.95rem;
  border-radius: 12px;
  font-size: 0.78rem; font-weight: 600; color: #475569;
  cursor: pointer;
  transition: all 0.18s ease;
  white-space: nowrap;
}
.pkg-chip:hover { border-color: #818cf8; color: #4f46e5; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.12); }
.dark .pkg-chip { background: #1e293b; border-color: rgba(255,255,255,0.10); color: #cbd5e1; }
.pkg-chip-divider { color: #cbd5e1; font-weight: 400; }
.pkg-chip-price { color: #6366f1; font-weight: 800; }
.pkg-chip.active {
  background: linear-gradient(135deg, #6366f1, #7c3aed);
  border-color: #6366f1;
  color: #fff;
  box-shadow: 0 4px 14px rgba(99,102,241,0.35);
}
.pkg-chip.active .pkg-chip-divider { color: rgba(255,255,255,0.6); }
.pkg-chip.active .pkg-chip-price { color: #fff; }
.pkg-chip.clear {
  border-style: dashed;
  border-color: #cbd5e1;
  color: #64748b;
}
.dark .pkg-chip.clear { border-color: rgba(255,255,255,0.15); color: #94a3b8; }

/* Face-match chip: distinct visual to flag "buy ALL your photos at discount"
   so it stands out from the count-bundles next to it. */
.pkg-chip.face-match {
  border-color: #f9a8d4;
  background: linear-gradient(135deg, #fff1f7, #fef3c7);
  color: #be185d;
}
.dark .pkg-chip.face-match {
  background: linear-gradient(135deg, rgba(244,114,182,0.10), rgba(251,191,36,0.08));
  border-color: rgba(244,114,182,0.35);
  color: #fbcfe8;
}
.pkg-chip.face-match i { color: #ec4899; }
.pkg-chip.face-match.active {
  background: linear-gradient(135deg, #ec4899, #f97316);
  border-color: #ec4899;
  color: #fff;
  box-shadow: 0 4px 18px rgba(236,72,153,0.40);
}
.pkg-chip.face-match.active i { color: #fff; }
.pkg-chip.face-match.active .pkg-chip-price { color: #fff; }
.pkg-info-bar {
  margin-top: 0.6rem;
  padding: 0.55rem 0.85rem;
  border-radius: 10px;
  background: linear-gradient(135deg, #eef2ff, #f5f3ff);
  color: #4f46e5;
  font-size: 0.78rem; font-weight: 600;
  display: flex; align-items: center; gap: 0.4rem;
}
.dark .pkg-info-bar { background: rgba(99,102,241,0.12); color: #a5b4fc; }
.pkg-info-bar.warn {
  background: linear-gradient(135deg, #fef3c7, #fde68a);
  color: #92400e;
}
.dark .pkg-info-bar.warn { background: rgba(245,158,11,0.15); color: #fbbf24; }
.pkg-info-bar.ok {
  background: linear-gradient(135deg, #d1fae5, #a7f3d0);
  color: #065f46;
}
.dark .pkg-info-bar.ok { background: rgba(16,185,129,0.15); color: #34d399; }

.match-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: 1rem;
  /* Reserve space for the fixed bottom selection bar so the last row
     never sits underneath it. The +env adds iOS safe-area on devices that
     have a home indicator. */
  padding-bottom: calc(110px + env(safe-area-inset-bottom));
}
.match-card {
  border-radius: 14px; overflow: hidden; position: relative;
  box-shadow: 0 1px 4px rgba(0,0,0,0.06), 0 4px 16px rgba(0,0,0,0.04);
  transition: all 0.3s ease; cursor: pointer; background: #fff;
  border: 1px solid rgba(0,0,0,0.04);
}
.dark .match-card { background: #1e293b; border-color: rgba(255,255,255,0.06); }
.match-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 32px rgba(0,0,0,0.12);
}
.match-card img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; }
.match-badge {
  position: absolute; top: 10px; right: 10px;
  background: rgba(16,185,129,0.92); color: #fff;
  padding: 0.25rem 0.6rem; border-radius: 8px; font-size: 0.7rem; font-weight: 700;
  backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
  box-shadow: 0 2px 8px rgba(16,185,129,0.3);
  display: flex; align-items: center; gap: 0.25rem;
}
.match-badge i { font-size: 0.6rem; }
.match-card-footer {
  padding: 0.6rem 0.75rem; display: flex; align-items: center; justify-content: space-between;
  border-top: 1px solid rgba(0,0,0,0.04);
}
.dark .match-card-footer { border-color: rgba(255,255,255,0.06); }
.match-card-footer .label { font-size: 0.72rem; color: #94a3b8; font-weight: 500; }
.match-card-footer .price { font-size: 0.82rem; color: #0f172a; font-weight: 700; }
.dark .match-card-footer .price { color: #e2e8f0; }
.match-card-footer .price.free { color: #059669; }

/* Selection state — photo is in the purchase basket */
.match-card.selected {
  box-shadow: 0 0 0 3px #6366f1, 0 8px 24px rgba(99,102,241,0.25);
  transform: translateY(-2px);
}
.match-card.selected::after {
  content: ''; position: absolute; inset: 0;
  background: linear-gradient(180deg, rgba(99,102,241,0.18), rgba(99,102,241,0.05));
  pointer-events: none;
}
.match-check {
  position: absolute; top: 10px; left: 10px; z-index: 2;
  width: 26px; height: 26px; border-radius: 50%;
  background: rgba(255,255,255,0.9); border: 2px solid rgba(99,102,241,0.4);
  display: flex; align-items: center; justify-content: center;
  color: #fff; font-size: 0.85rem; transition: all 0.2s;
  box-shadow: 0 2px 8px rgba(0,0,0,0.12);
}
.match-card.selected .match-check {
  background: #6366f1; border-color: #6366f1;
}
.match-check i { opacity: 0; transition: opacity 0.2s; }
.match-card.selected .match-check i { opacity: 1; }

/* ── Selection bar (appears when ≥1 match is selected) ──
   Pinned to the viewport bottom so the buy/cart actions are always
   reachable while scrolling through matches. Uses translucent blur
   for a modern docked-toolbar feel. */
.selection-bar {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  z-index: 900;
  display: flex;
  align-items: center;
  gap: 1rem;
  flex-wrap: wrap;
  padding: 0.9rem clamp(1rem, 4vw, 2.5rem);
  padding-bottom: calc(0.9rem + env(safe-area-inset-bottom));
  margin: 0;
  background: rgba(255, 255, 255, 0.88);
  backdrop-filter: blur(20px) saturate(180%);
  -webkit-backdrop-filter: blur(20px) saturate(180%);
  border: none;
  border-top: 1px solid rgba(99, 102, 241, 0.22);
  border-radius: 0;
  box-shadow:
    0 -10px 32px rgba(15, 23, 42, 0.10),
    0 -2px 8px rgba(15, 23, 42, 0.04);
  animation: sb-slide-up 0.32s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes sb-slide-up {
  from { transform: translateY(100%); opacity: 0; }
  to   { transform: translateY(0);    opacity: 1; }
}
.dark .selection-bar {
  background: rgba(15, 23, 42, 0.82);
  border-top-color: rgba(99, 102, 241, 0.30);
  box-shadow:
    0 -10px 32px rgba(0, 0, 0, 0.40),
    0 -2px 8px rgba(0, 0, 0, 0.25);
}
.selection-bar .sb-info {
  flex: 0 1 auto;
  min-width: 140px;
  display: flex;
  flex-direction: column;
  gap: 2px;
  line-height: 1.2;
}
.selection-bar .sb-count { font-size: 0.78rem; color: #64748b; font-weight: 500; }
.dark .selection-bar .sb-count { color: #94a3b8; }
.selection-bar .sb-total { font-size: 1.15rem; color: #0f172a; font-weight: 800; letter-spacing: -0.02em; }
.dark .selection-bar .sb-total { color: #f1f5f9; }
.selection-bar .sb-actions {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  align-items: center;
  margin-left: auto;
}
.sb-btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
  padding: 0.6rem 1rem; border-radius: 10px; font-weight: 700; font-size: 0.82rem;
  border: none; cursor: pointer; transition: all 0.2s;
  white-space: nowrap;
}
.sb-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.sb-btn.ghost { background: rgba(255,255,255,0.6); color: #475569; border: 1px solid rgba(148,163,184,0.3); }
.dark .sb-btn.ghost { background: rgba(255,255,255,0.06); color: #cbd5e1; border-color: rgba(255,255,255,0.1); }
.sb-btn.cart { background: linear-gradient(135deg, #6366f1, #7c3aed); color: #fff; box-shadow: 0 4px 14px rgba(99,102,241,0.35); }
.sb-btn.cart:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(99,102,241,0.45); }
.sb-btn.buy  { background: linear-gradient(135deg, #f59e0b, #ef4444); color: #fff; box-shadow: 0 4px 14px rgba(245,158,11,0.35); }
.sb-btn.buy:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(245,158,11,0.45); }

/* ── No results ── */
.no-results {
  text-align: center; padding: 3rem 1rem;
}
.no-results-icon {
  width: 80px; height: 80px; border-radius: 50%; margin: 0 auto 1rem;
  background: #f1f5f9; display: flex; align-items: center; justify-content: center;
}
.dark .no-results-icon { background: rgba(255,255,255,0.05); }
.no-results-icon i { font-size: 2rem; color: #cbd5e1; }
.no-results h4 { font-size: 1rem; font-weight: 700; color: #475569; margin-bottom: 0.25rem; }
.dark .no-results h4 { color: #94a3b8; }
.no-results p { font-size: 0.85rem; color: #94a3b8; }

/* ── Warning alert ── */
.config-warning {
  display: flex; align-items: flex-start; gap: 1rem;
  background: linear-gradient(135deg, #fffbeb, #fef3c7);
  border: 1px solid rgba(245,158,11,0.2);
  border-radius: 16px; padding: 1.25rem 1.5rem;
  max-width: 600px; margin: 2rem auto;
}
.dark .config-warning {
  background: linear-gradient(135deg, rgba(245,158,11,0.08), rgba(245,158,11,0.05));
  border-color: rgba(245,158,11,0.15);
}
.config-warning .warn-icon {
  width: 40px; height: 40px; border-radius: 10px; flex-shrink: 0;
  background: rgba(245,158,11,0.15); display: flex; align-items: center; justify-content: center;
}
.config-warning .warn-icon i { color: #d97706; font-size: 1.1rem; }
.config-warning h4 { font-size: 0.9rem; font-weight: 700; color: #92400e; margin-bottom: 0.15rem; }
.dark .config-warning h4 { color: #fbbf24; }
.config-warning p { font-size: 0.8rem; color: #a16207; margin: 0; }
.dark .config-warning p { color: #d97706; }

/* ── Scanning Overlay ── */
.scanning-overlay {
  position: fixed; inset: 0; background: rgba(2,6,23,0.88);
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  z-index: 9999; backdrop-filter: blur(4px);
}
.scan-anim {
  position: relative; width: 100px; height: 100px; margin-bottom: 1.5rem;
}
.scan-ring-outer {
  position: absolute; inset: 0; border-radius: 50%;
  border: 2px solid rgba(99,102,241,0.15);
}
.scan-ring-inner {
  position: absolute; inset: 8px; border-radius: 50%;
  border: 3px solid transparent; border-top-color: #818cf8;
  animation: spin 0.8s linear infinite;
}
.scan-dot {
  position: absolute; inset: 20px; border-radius: 50%;
  background: radial-gradient(circle, rgba(99,102,241,0.3) 0%, transparent 70%);
  animation: pulse 1.5s ease-in-out infinite;
}
.scan-icon {
  position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
  color: #a5b4fc; font-size: 1.6rem;
}
@keyframes spin { to { transform: rotate(360deg); } }
@keyframes pulse { 0%,100% { transform: scale(0.9); opacity: 0.5; } 50% { transform: scale(1.1); opacity: 1; } }
.scanning-text h4 { color: #fff; font-weight: 700; font-size: 1.1rem; margin-bottom: 0.25rem; text-align: center; }
.scanning-text p { color: rgba(255,255,255,0.45); font-size: 0.82rem; text-align: center; }
.scan-progress {
  width: 200px; height: 3px; background: rgba(255,255,255,0.08);
  border-radius: 4px; margin-top: 1.25rem; overflow: hidden;
}
.scan-progress-bar {
  height: 100%; width: 30%; border-radius: 4px;
  background: linear-gradient(90deg, #6366f1, #818cf8);
  animation: progress 2s ease-in-out infinite;
}
@keyframes progress {
  0% { width: 0%; margin-left: 0%; }
  50% { width: 60%; margin-left: 20%; }
  100% { width: 0%; margin-left: 100%; }
}

/* ── Error toast ── */
.error-toast {
  position: fixed; bottom: 1.5rem; left: 50%; transform: translateX(-50%);
  background: #fff; border: 1px solid rgba(239,68,68,0.15); border-radius: 14px;
  padding: 0.85rem 1.25rem; box-shadow: 0 8px 32px rgba(0,0,0,0.12);
  display: flex; align-items: center; gap: 0.75rem; z-index: 10000;
  animation: toastIn 0.3s ease-out;
}
.dark .error-toast { background: #1e293b; border-color: rgba(239,68,68,0.2); }
@keyframes toastIn { from { opacity: 0; transform: translateX(-50%) translateY(1rem); } }
.error-toast .toast-icon {
  width: 32px; height: 32px; border-radius: 8px;
  background: rgba(239,68,68,0.1); display: flex; align-items: center; justify-content: center;
}
.error-toast .toast-icon i { color: #ef4444; font-size: 0.9rem; }
.error-toast .toast-msg { font-size: 0.82rem; font-weight: 600; color: #334155; }
.dark .error-toast .toast-msg { color: #e2e8f0; }

/* ── Responsive ── */
@media (max-width: 768px) {
  .face-hero { padding: 2rem 0 1.5rem; }
  .hero-title { font-size: 1.35rem; }
  .match-grid {
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 0.75rem;
    padding-bottom: calc(140px + env(safe-area-inset-bottom));
  }
  .steps-bar { gap: 0.3rem; }
  .step-connector { width: 12px; }

  /* Selection bar mobile — stack info on top, actions on bottom row.
     Ghost buttons keep just the icon to save horizontal space. */
  .selection-bar {
    padding: 0.7rem 0.875rem;
    padding-bottom: calc(0.7rem + env(safe-area-inset-bottom));
    gap: 0.55rem;
  }
  .selection-bar .sb-info {
    flex: 1 1 100%;
    flex-direction: row;
    align-items: baseline;
    gap: 0.6rem;
    min-width: 0;
  }
  .selection-bar .sb-count { font-size: 0.72rem; }
  .selection-bar .sb-total { font-size: 1rem; }
  .selection-bar .sb-actions {
    flex: 1 1 100%;
    margin-left: 0;
    gap: 0.4rem;
  }
  .sb-btn {
    padding: 0.55rem 0.6rem;
    font-size: 0.74rem;
    flex: 1 1 0;
    min-width: 0;
  }
  .sb-btn.ghost {
    flex: 0 0 auto;
    padding: 0.55rem 0.7rem;
  }
  .sb-btn i { font-size: 0.95rem; }
}

@media (max-width: 380px) {
  /* Very narrow phones — collapse ghost buttons to icon-only */
  .sb-btn.ghost {
    padding: 0.55rem;
    width: 38px;
    font-size: 0;
  }
  .sb-btn.ghost i { font-size: 1rem; }
}

/* ──────────────────────────────────────────────────────────────
   Camera capture (professional flow)
   - Mobile: native camera via <input capture="user"> (already wired)
   - Desktop: live <video> preview with face-guide oval + capture btn
   - Same handleFile() integration point — the capture just produces
     a File object and feeds it through the existing pipeline.
   ────────────────────────────────────────────────────────────── */
.capture-actions {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 0.75rem;
  margin-bottom: 1rem;
}
@media (max-width: 540px) {
  .capture-actions { grid-template-columns: 1fr; }
}
.capture-btn {
  display: flex; align-items: center; justify-content: center; gap: 0.5rem;
  padding: 0.85rem 1rem; border-radius: 12px; font-weight: 700; font-size: 0.85rem;
  border: none; cursor: pointer; transition: all 0.2s; text-decoration: none;
  position: relative; overflow: hidden;
}
.capture-btn.primary {
  background: linear-gradient(135deg, #6366f1, #8b5cf6);
  color: #fff;
  box-shadow: 0 4px 14px rgba(99,102,241,0.35);
}
.capture-btn.primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px rgba(99,102,241,0.45);
}
.capture-btn.primary:active { transform: translateY(0); }
.capture-btn.secondary {
  background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;
}
.dark .capture-btn.secondary {
  background: rgba(255,255,255,0.05); color: #cbd5e1;
  border-color: rgba(255,255,255,0.1);
}
.capture-btn.secondary:hover { background: #e2e8f0; }
.dark .capture-btn.secondary:hover { background: rgba(255,255,255,0.08); }
.capture-btn i { font-size: 1.05rem; }

/* ── Camera Modal ── */
.camera-modal {
  position: fixed; inset: 0; z-index: 9999;
  background: rgba(0,0,0,0.92);
  display: flex; flex-direction: column;
  animation: cm-fade 0.25s ease;
}
@keyframes cm-fade {
  from { opacity: 0; }
  to   { opacity: 1; }
}
.camera-modal-header {
  display: flex; align-items: center; justify-content: space-between;
  padding: 1rem 1.25rem;
  background: rgba(0,0,0,0.5);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid rgba(255,255,255,0.06);
}
.camera-modal-header h3 {
  margin: 0; color: #fff; font-size: 0.95rem; font-weight: 700;
  display: flex; align-items: center; gap: 0.5rem;
}
.camera-modal-header h3 i { color: #a5b4fc; }
.camera-close-btn {
  width: 36px; height: 36px; border-radius: 50%;
  background: rgba(255,255,255,0.08); border: none; color: #fff;
  cursor: pointer; transition: background 0.2s;
  display: flex; align-items: center; justify-content: center;
}
.camera-close-btn:hover { background: rgba(255,255,255,0.15); }
.camera-close-btn i { font-size: 1.2rem; }

.camera-stage {
  flex: 1; position: relative; overflow: hidden;
  display: flex; align-items: center; justify-content: center;
  background: #000;
}
.camera-stage video {
  max-width: 100%; max-height: 100%;
  width: auto; height: auto;
  /* mirror like a selfie cam — feels natural */
  transform: scaleX(-1);
  -webkit-transform: scaleX(-1);
}
.camera-stage video.no-mirror {
  transform: none;
  -webkit-transform: none;
}

/* Face guide oval overlay */
.face-guide {
  position: absolute; top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  width: 240px; height: 320px;
  border: 3px dashed rgba(165,180,252,0.6);
  border-radius: 50%;
  pointer-events: none;
  box-shadow: 0 0 0 9999px rgba(0,0,0,0.4);
  animation: cm-pulse 2.5s ease-in-out infinite;
}
@keyframes cm-pulse {
  0%, 100% { border-color: rgba(165,180,252,0.5); }
  50%      { border-color: rgba(165,180,252,0.9); }
}
.face-guide-hint {
  position: absolute; left: 50%; bottom: calc(50% + 175px);
  transform: translateX(-50%);
  background: rgba(99,102,241,0.95); color: #fff;
  font-size: 0.78rem; font-weight: 600;
  padding: 0.4rem 0.85rem; border-radius: 999px;
  white-space: nowrap;
  box-shadow: 0 4px 14px rgba(0,0,0,0.3);
}
.face-guide-hint i { margin-right: 0.35rem; }

@media (max-width: 540px) {
  .face-guide { width: 200px; height: 270px; }
  .face-guide-hint { bottom: calc(50% + 150px); font-size: 0.72rem; }
}

/* Camera error / permission state */
.camera-error {
  position: absolute; inset: 0;
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  text-align: center; padding: 2rem; gap: 1rem;
  background: #0f172a;
}
.camera-error .err-icon {
  width: 72px; height: 72px; border-radius: 50%;
  background: rgba(239,68,68,0.15);
  display: flex; align-items: center; justify-content: center;
}
.camera-error .err-icon i { font-size: 2rem; color: #f87171; }
.camera-error h4 { color: #fff; margin: 0; font-size: 1rem; font-weight: 700; }
.camera-error p { color: rgba(255,255,255,0.6); margin: 0; font-size: 0.85rem; max-width: 320px; }
.camera-error button {
  margin-top: 0.5rem; padding: 0.7rem 1.5rem; border-radius: 10px;
  background: #fff; color: #1e293b; border: none; font-weight: 700;
  font-size: 0.85rem; cursor: pointer; transition: transform 0.2s;
}
.camera-error button:hover { transform: translateY(-1px); }

/* Camera controls bar */
.camera-controls {
  padding: 1.25rem 1rem 1.5rem;
  background: rgba(0,0,0,0.5);
  backdrop-filter: blur(12px);
  border-top: 1px solid rgba(255,255,255,0.06);
  display: flex; align-items: center; justify-content: center;
  gap: 1.25rem;
  /* Safe-area for iOS home indicator */
  padding-bottom: max(1.5rem, env(safe-area-inset-bottom));
}
.camera-ctrl-side {
  width: 48px; height: 48px; border-radius: 50%;
  background: rgba(255,255,255,0.1); color: #fff; border: none;
  cursor: pointer; transition: background 0.2s;
  display: flex; align-items: center; justify-content: center;
}
.camera-ctrl-side:hover { background: rgba(255,255,255,0.2); }
.camera-ctrl-side i { font-size: 1.2rem; }
.camera-ctrl-shutter {
  width: 76px; height: 76px; border-radius: 50%;
  background: #fff; border: 5px solid rgba(255,255,255,0.3);
  cursor: pointer; transition: transform 0.15s, box-shadow 0.2s;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 4px 20px rgba(0,0,0,0.3);
  position: relative;
}
.camera-ctrl-shutter::after {
  content: ''; position: absolute; inset: 6px;
  border-radius: 50%; background: #fff;
  border: 2px solid #1e293b;
  transition: background 0.2s;
}
.camera-ctrl-shutter:hover { transform: scale(1.05); }
.camera-ctrl-shutter:active { transform: scale(0.92); }
.camera-ctrl-shutter:active::after { background: #f1f5f9; }
.camera-ctrl-shutter:disabled {
  opacity: 0.5; cursor: not-allowed; transform: none;
}

/* Countdown / flash */
.camera-countdown {
  position: absolute; top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  font-size: 6rem; font-weight: 900; color: #fff;
  text-shadow: 0 4px 20px rgba(0,0,0,0.8);
  animation: cm-count 1s ease;
  pointer-events: none;
}
@keyframes cm-count {
  0%   { opacity: 0; transform: translate(-50%, -50%) scale(0.5); }
  20%  { opacity: 1; transform: translate(-50%, -50%) scale(1.2); }
  100% { opacity: 0; transform: translate(-50%, -50%) scale(1); }
}
.camera-flash {
  position: absolute; inset: 0;
  background: #fff; opacity: 0;
  pointer-events: none;
  animation: cm-flash 0.4s ease;
}
@keyframes cm-flash {
  0%   { opacity: 0; }
  10%  { opacity: 0.95; }
  100% { opacity: 0; }
}
</style>
@endpush

@section('hero')
<div class="face-hero">
  <div class="max-w-7xl mx-auto px-4">
    {{-- Breadcrumb --}}
    <nav class="hero-breadcrumb">
      <a href="{{ route('events.index') }}">งานทั้งหมด</a>
      <span class="separator"><i class="bi bi-chevron-right"></i></span>
      <a href="{{ route('events.show', $event->slug ?: $event->id) }}">{{ $event->name }}</a>
      <span class="separator"><i class="bi bi-chevron-right"></i></span>
      <span class="current">ค้นหาด้วยใบหน้า</span>
    </nav>

    {{-- Title --}}
    <h1 class="hero-title">
      <span class="icon-wrap"><i class="bi bi-person-bounding-box"></i></span>
      ค้นหารูปด้วยใบหน้า
    </h1>
    <p class="hero-subtitle">อัพโหลดเซลฟี่ของคุณ แล้วระบบ AI จะค้นหารูปที่มีคุณอยู่ในงาน <strong style="color:rgba(255,255,255,0.7);">{{ $event->name }}</strong></p>

    {{-- Steps --}}
    <div class="steps-bar">
      <div class="step-item active" id="step1">
        <span class="step-num">1</span>อัพโหลดรูป
      </div>
      <div class="step-connector"></div>
      <div class="step-item inactive" id="step2">
        <span class="step-num">2</span>AI ค้นหา
      </div>
      <div class="step-connector"></div>
      <div class="step-item inactive" id="step3">
        <span class="step-num">3</span>ดูผลลัพธ์
      </div>
    </div>
  </div>
</div>
@endsection

@section('content')
@if(!$configured)
  <div class="config-warning">
    <div class="warn-icon"><i class="bi bi-exclamation-triangle"></i></div>
    <div>
      <h4>ระบบ AI วิเคราะห์ภาพยังไม่พร้อม</h4>
      <p>ฟังก์ชันค้นหาใบหน้ายังไม่พร้อมใช้งาน กรุณาติดต่อผู้ดูแลระบบเพื่อเปิดใช้งาน</p>
    </div>
  </div>
@else
  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- Main upload area --}}
    <div class="lg:col-span-2">
      <div class="upload-card">
        <div class="upload-card-header">
          <div class="header-icon"><i class="bi bi-camera"></i></div>
          <div>
            <h3>อัพโหลดรูปเซลฟี่</h3>
            <p>เลือกรูปที่เห็นใบหน้าชัดเจน</p>
          </div>
        </div>
        <div class="upload-card-body">
          {{-- Two-button capture row: live camera (preferred) + file fallback --}}
          <div class="capture-actions">
            <button type="button" class="capture-btn primary" id="openCameraBtn">
              <i class="bi bi-camera-fill"></i>
              <span>ถ่ายเซลฟี่ด้วยกล้อง</span>
            </button>
            <button type="button" class="capture-btn secondary" id="pickFileBtn">
              <i class="bi bi-image"></i>
              <span>เลือกจากไฟล์</span>
            </button>
          </div>

          <div class="upload-zone" id="uploadZone" onclick="document.getElementById('selfieInput').click()">
            {{-- Placeholder --}}
            <div id="uploadPlaceholder">
              <div class="upload-icon-circle">
                <i class="bi bi-cloud-arrow-up"></i>
              </div>
              <h4>ลากไฟล์มาวางที่นี่ หรือคลิกเพื่อเลือก</h4>
              <p class="hint">รองรับไฟล์ภาพขนาดไม่เกิน 10MB</p>
              <div class="upload-formats">
                <span>JPG</span><span>PNG</span><span>WEBP</span>
              </div>
            </div>
            {{-- Preview --}}
            <div id="previewContainer" style="display:none;">
              <div class="selfie-preview-wrap">
                <img id="selfiePreview" class="selfie-preview" alt="Preview">
                <div class="preview-status"><i class="bi bi-check-circle-fill"></i>พร้อมค้นหา</div>
                <span class="change-photo-btn" onclick="event.stopPropagation();">เปลี่ยนรูป</span>
              </div>
            </div>
          </div>
          <input type="file" id="selfieInput" accept="image/*" capture="user" class="hidden">

          {{-- PDPA consent (required under Thailand PDPA §26 biometric data rules) --}}
          <div class="pdpa-consent" style="margin-top:1.25rem; padding:0.875rem 1rem; border-radius:12px; background:rgba(99,102,241,0.04); border:1px solid rgba(99,102,241,0.12);">
            <label style="display:flex; align-items:flex-start; gap:0.625rem; cursor:pointer;">
              <input type="checkbox" id="consentCheckbox" onchange="updateSearchBtn()"
                     style="margin-top:0.125rem; width:16px; height:16px; accent-color:#6366f1; flex-shrink:0;">
              <span style="font-size:0.78rem; line-height:1.5; color:#475569;">
                ฉันยินยอมให้ระบบประมวลผลใบหน้าจากรูปนี้เพื่อค้นหารูปของฉันในงานนี้เท่านั้น
                ตาม <strong>พ.ร.บ. คุ้มครองข้อมูลส่วนบุคคล (PDPA) พ.ศ. 2562 มาตรา 26</strong>
                — รูปเซลฟี่จะถูกใช้เฉพาะครั้งนี้และไม่ถูกบันทึกไว้ในระบบ
                <a href="/legal/biometric-data-privacy" target="_blank" style="color:#6366f1; text-decoration:underline;">อ่านนโยบายความเป็นส่วนตัว →</a>
              </span>
            </label>
          </div>

          {{-- Cloudflare Turnstile (anti-bot) — only renders when enabled in admin settings --}}
          @php($turnstileEnabled = \App\Models\AppSetting::get('turnstile_enabled', '0') === '1')
          @php($turnstileKey = \App\Models\AppSetting::get('turnstile_site_key', ''))
          @if($turnstileEnabled && $turnstileKey)
            <div style="margin-top:1rem; display:flex; justify-content:center;">
              <div class="cf-turnstile"
                   data-sitekey="{{ $turnstileKey }}"
                   data-theme="auto"
                   data-callback="onTurnstileSuccess"
                   data-expired-callback="onTurnstileExpired"></div>
            </div>
          @endif

          <div class="search-btn-wrap">
            <button class="search-btn" id="searchBtn" disabled onclick="startSearch()">
              <i class="bi bi-search"></i>ค้นหารูปของฉัน
            </button>
          </div>
        </div>
      </div>

      {{-- Results --}}
      <div id="resultsArea" style="display:none;" class="mt-6">
        <div class="results-header">
          <div class="results-title">
            <span class="title-icon"><i class="bi bi-images"></i></span>
            ผลการค้นหา
          </div>
          <span id="matchCount" class="results-count"></span>
        </div>

        {{-- ── Package chip strip ─────────────────────────────────────────
             Server passes `$packages` (count-bundles only). Buyer can pick
             one to override per-photo pricing — JS reprices every match
             card and the selection bar's total. Hidden when:
               • event has no active packages (count == 0)
               • search hasn't run yet (resultsArea is display:none anyway)
        --}}
        @if(isset($packages) && $packages->count() > 0)
        <div class="pkg-strip-wrap" id="pkgStripWrap" style="display:none;">
          <div class="pkg-strip-label">
            <i class="bi bi-box-seam"></i>
            แพ็กเกจประหยัด — เลือกซื้อหลายรูปในราคาพิเศษ
          </div>
          <div class="pkg-strip" id="pkgStrip">
            @foreach($packages as $pkg)
              @php($bt = $pkg->bundle_type ?? 'count')
              @if($bt === 'face_match')
                {{-- Face-match bundle: variable count, percentage discount,
                     capped at max_price. Auto-selects every match on click. --}}
                <button type="button"
                        class="pkg-chip face-match"
                        data-package-id="{{ $pkg->id }}"
                        data-bundle-type="face_match"
                        data-discount="{{ (float) ($pkg->discount_pct ?? 0) }}"
                        data-max-price="{{ (float) ($pkg->max_price ?? 0) }}"
                        data-name="{{ $pkg->name }}"
                        onclick="faceSearch.selectPackage(this)">
                  <i class="bi bi-stars"></i>
                  <span>{{ $pkg->name ?: 'เหมารูปฉัน' }}</span>
                  <span class="pkg-chip-divider">·</span>
                  <span class="pkg-chip-price">ลด {{ (int) ($pkg->discount_pct ?? 0) }}%</span>
                </button>
              @else
                {{-- Count bundle: fixed N photos for ฿X --}}
                <button type="button"
                        class="pkg-chip"
                        data-package-id="{{ $pkg->id }}"
                        data-bundle-type="count"
                        data-count="{{ $pkg->photo_count }}"
                        data-price="{{ $pkg->price }}"
                        data-name="{{ $pkg->name }}"
                        onclick="faceSearch.selectPackage(this)">
                  <span>{{ $pkg->name }}</span>
                  <span class="pkg-chip-divider">·</span>
                  <span>{{ $pkg->photo_count }} รูป</span>
                  <span class="pkg-chip-divider">·</span>
                  <span class="pkg-chip-price">{{ number_format($pkg->price, 0) }} ฿</span>
                </button>
              @endif
            @endforeach
            <button type="button"
                    class="pkg-chip clear"
                    onclick="faceSearch.clearPackage()">
              <i class="bi bi-x-circle"></i>
              ราคาต่อรูป
            </button>
          </div>
          <div class="pkg-info-bar" id="pkgInfoBar" style="display:none;"></div>
        </div>
        @endif

        {{-- Selection bar — appears only when ≥1 match is selected and the event
             is priced. Guests see login-prompt buttons instead of the real
             cart/buy actions, mirroring the gated UX on the main event page. --}}
        <div id="selectionBar" class="selection-bar" style="display:none;">
          <div class="sb-info">
            <div class="sb-count"><span id="sbSelectedCount">0</span> รูปที่เลือก</div>
            <div class="sb-total">รวม ฿<span id="sbTotal">0</span></div>
          </div>
          <div class="sb-actions">
            <button type="button" class="sb-btn ghost" onclick="faceSearch.selectAll()">
              <i class="bi bi-check2-all"></i> เลือกทั้งหมด
            </button>
            <button type="button" class="sb-btn ghost" onclick="faceSearch.clearSelection()">
              <i class="bi bi-x-lg"></i> ล้าง
            </button>
            @auth
            <button type="button" class="sb-btn cart" onclick="faceSearch.addToCart()">
              <i class="bi bi-bag-plus-fill"></i> เพิ่มลงตะกร้า
            </button>
            <button type="button" class="sb-btn buy" onclick="faceSearch.buyNow()">
              <i class="bi bi-lightning-charge-fill"></i> ซื้อเลย
            </button>
            @else
            <button type="button" class="sb-btn cart" onclick="faceSearch.promptLogin()">
              <i class="bi bi-bag-plus-fill"></i> เพิ่มลงตะกร้า
            </button>
            <button type="button" class="sb-btn buy" onclick="faceSearch.promptLogin()">
              <i class="bi bi-lightning-charge-fill"></i> ซื้อเลย
            </button>
            @endauth
          </div>
        </div>

        <div id="matchGrid" class="match-grid"></div>
        <div id="noResults" style="display:none;" class="no-results">
          <div class="no-results-icon"><i class="bi bi-person-x"></i></div>
          <h4>ไม่พบรูปที่ตรงกัน</h4>
          <p>ลองใช้รูปเซลฟี่ที่เห็นใบหน้าชัดเจนขึ้น แล้วค้นหาอีกครั้ง</p>
        </div>
      </div>
    </div>

    {{-- Tips sidebar --}}
    <div>
      <div class="tips-card">
        <h4><i class="bi bi-lightbulb"></i>เคล็ดลับ</h4>
        <div class="tip-item"><i class="bi bi-check2-circle"></i><span>ใช้รูปที่เห็นใบหน้าชัดเจน ไม่สวมแว่นกันแดด</span></div>
        <div class="tip-item"><i class="bi bi-check2-circle"></i><span>แสงสว่างเพียงพอ ไม่มืดหรือสว่างเกินไป</span></div>
        <div class="tip-item"><i class="bi bi-check2-circle"></i><span>หันหน้าตรง ไม่เอียงมากเกินไป</span></div>
        <div class="tip-item"><i class="bi bi-check2-circle"></i><span>รูปมีใบหน้าเพียง 1 คน จะได้ผลลัพธ์ดีที่สุด</span></div>
      </div>
    </div>
  </div>

  {{-- Camera Modal — live webcam preview + face guide oval --}}
  <div id="cameraModal" class="camera-modal" style="display:none;" aria-modal="true" role="dialog">
    <div class="camera-modal-header">
      <h3><i class="bi bi-camera-video"></i> ถ่ายเซลฟี่</h3>
      <button type="button" class="camera-close-btn" id="closeCameraBtn" aria-label="ปิด">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div class="camera-stage" id="cameraStage">
      <video id="cameraVideo" autoplay playsinline muted></video>
      <div class="face-guide" id="faceGuide"></div>
      <div class="face-guide-hint" id="faceGuideHint">
        <i class="bi bi-info-circle"></i>วางใบหน้าให้อยู่ในกรอบ
      </div>

      {{-- Error / permission denied state --}}
      <div class="camera-error" id="cameraError" style="display:none;">
        <div class="err-icon"><i class="bi bi-camera-video-off"></i></div>
        <h4 id="cameraErrTitle">ไม่สามารถเปิดกล้องได้</h4>
        <p id="cameraErrMsg">กรุณาอนุญาตให้เว็บไซต์ใช้กล้อง หรือใช้ปุ่ม "เลือกจากไฟล์" แทน</p>
        <button type="button" id="cameraErrPickFile">
          <i class="bi bi-image"></i> เลือกจากไฟล์แทน
        </button>
      </div>

      <div class="camera-flash" id="cameraFlash" style="display:none;"></div>
      <div class="camera-countdown" id="cameraCountdown" style="display:none;">3</div>
    </div>
    <div class="camera-controls">
      <button type="button" class="camera-ctrl-side" id="switchCameraBtn" title="สลับกล้อง" aria-label="สลับกล้อง">
        <i class="bi bi-arrow-repeat"></i>
      </button>
      <button type="button" class="camera-ctrl-shutter" id="captureBtn" aria-label="ถ่าย"></button>
      <button type="button" class="camera-ctrl-side" id="cameraPickFileBtn" title="เลือกจากไฟล์" aria-label="เลือกจากไฟล์">
        <i class="bi bi-image"></i>
      </button>
    </div>
  </div>

  {{-- Scanning Overlay --}}
  <div id="scanningOverlay" class="scanning-overlay" style="display:none;">
    <div class="scan-anim">
      <div class="scan-ring-outer"></div>
      <div class="scan-ring-inner"></div>
      <div class="scan-dot"></div>
      <div class="scan-icon"><i class="bi bi-person-bounding-box"></i></div>
    </div>
    <div class="scanning-text">
      <h4>กำลังค้นหาใบหน้า...</h4>
      <p>AI กำลังเปรียบเทียบกับรูปภาพในงาน</p>
    </div>
    <div class="scan-progress"><div class="scan-progress-bar"></div></div>
  </div>
@endif
@endsection

@push('scripts')
<script>
const eventId = {{ $event->id }};
let selectedFile = null;

// Steps
function setStep(n) {
  [1,2,3].forEach(i => {
    const el = document.getElementById('step' + i);
    if (!el) return;
    el.classList.toggle('active', i <= n);
    el.classList.toggle('inactive', i > n);
  });
}

// Drag & Drop
const zone = document.getElementById('uploadZone');
if (zone) {
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
  zone.addEventListener('drop', e => {
    e.preventDefault(); zone.classList.remove('dragover');
    if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]);
  });
}

document.getElementById('selfieInput')?.addEventListener('change', function() {
  if (this.files.length) handleFile(this.files[0]);
});

// Change photo
document.querySelector('.change-photo-btn')?.addEventListener('click', () => {
  document.getElementById('selfieInput').click();
});

function updateSearchBtn() {
  const btn = document.getElementById('searchBtn');
  if (!btn) return;
  const consented = document.getElementById('consentCheckbox')?.checked;
  btn.disabled = !(selectedFile && consented);
}

function handleFile(file) {
  if (!file.type.startsWith('image/')) return;
  if (file.size > 10 * 1024 * 1024) {
    showToast('ไฟล์ใหญ่เกินกำหนด (สูงสุด 10MB)');
    return;
  }
  selectedFile = file;
  const reader = new FileReader();
  reader.onload = e => {
    document.getElementById('selfiePreview').src = e.target.result;
    document.getElementById('uploadPlaceholder').style.display = 'none';
    document.getElementById('previewContainer').style.display = '';
    zone.classList.add('has-image');
    updateSearchBtn();  // only enable when BOTH file + consent are set
    setStep(1);
  };
  reader.readAsDataURL(file);
}

// ──────────────────────────────────────────────────────────────
//  Camera capture (getUserMedia)
//  - Opens a fullscreen modal with live <video> + face-guide oval
//  - Captures the current frame to a canvas, exports as JPEG, and
//    feeds it through the same handleFile() pipeline so the rest
//    of the page (preview, search button, PDPA gate) stays unchanged.
//  - Handles permission denied / no-camera gracefully with a fallback
//    to the existing file-picker.
// ──────────────────────────────────────────────────────────────
const camModal       = document.getElementById('cameraModal');
const camVideo       = document.getElementById('cameraVideo');
const camStage       = document.getElementById('cameraStage');
const camError       = document.getElementById('cameraError');
const camErrTitle    = document.getElementById('cameraErrTitle');
const camErrMsg      = document.getElementById('cameraErrMsg');
const camFaceGuide   = document.getElementById('faceGuide');
const camFaceHint    = document.getElementById('faceGuideHint');
const camFlash       = document.getElementById('cameraFlash');
const camCountdown   = document.getElementById('cameraCountdown');
const captureBtn     = document.getElementById('captureBtn');
const switchCamBtn   = document.getElementById('switchCameraBtn');

let camStream      = null;
let camFacingMode  = 'user'; // 'user' = front, 'environment' = rear
let camHasMultiple = false;  // populated after enumerateDevices

// Bind UI triggers
document.getElementById('openCameraBtn')?.addEventListener('click', startCamera);
document.getElementById('pickFileBtn')?.addEventListener('click', () =>
  document.getElementById('selfieInput').click()
);
document.getElementById('closeCameraBtn')?.addEventListener('click', closeCamera);
document.getElementById('cameraPickFileBtn')?.addEventListener('click', () => {
  closeCamera();
  document.getElementById('selfieInput').click();
});
document.getElementById('cameraErrPickFile')?.addEventListener('click', () => {
  closeCamera();
  document.getElementById('selfieInput').click();
});
captureBtn?.addEventListener('click', capturePhoto);
switchCamBtn?.addEventListener('click', switchCamera);

// ESC closes the modal
document.addEventListener('keydown', e => {
  if (e.key === 'Escape' && camModal?.style.display === 'flex') closeCamera();
});

async function startCamera() {
  // Feature-detect — older browsers / insecure contexts won't have it
  if (!navigator.mediaDevices?.getUserMedia) {
    showToast('เบราว์เซอร์นี้ไม่รองรับการเปิดกล้อง — ใช้ "เลือกจากไฟล์" แทนนะครับ');
    document.getElementById('selfieInput').click();
    return;
  }

  // Open the modal first so the user sees feedback while we request permission
  camModal.style.display = 'flex';
  camError.style.display = 'none';
  camFaceGuide.style.display = '';
  camFaceHint.style.display = '';
  document.body.style.overflow = 'hidden';

  try {
    camStream = await navigator.mediaDevices.getUserMedia({
      video: {
        facingMode: camFacingMode,
        width:  { ideal: 1280 },
        height: { ideal: 1280 },
      },
      audio: false,
    });
    camVideo.srcObject = camStream;
    // Mirror only the front camera — rear cam should not flip
    camVideo.classList.toggle('no-mirror', camFacingMode === 'environment');

    // Detect whether to show the switch-camera button (mobile dual-cam etc.)
    try {
      const devices = await navigator.mediaDevices.enumerateDevices();
      const cams    = devices.filter(d => d.kind === 'videoinput');
      camHasMultiple = cams.length > 1;
      switchCamBtn.style.display = camHasMultiple ? '' : 'none';
    } catch (_) {
      switchCamBtn.style.display = 'none';
    }

    captureBtn.disabled = false;
  } catch (err) {
    console.error('[face-search] camera error', err);
    camFaceGuide.style.display = 'none';
    camFaceHint.style.display = 'none';
    camError.style.display = 'flex';
    captureBtn.disabled = true;

    // Tailored messages for the most common failures
    if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
      camErrTitle.textContent = 'คุณปฏิเสธการใช้กล้อง';
      camErrMsg.textContent   = 'อนุญาตการใช้กล้องในเบราว์เซอร์ แล้วลองใหม่ หรือกดปุ่มด้านล่างเพื่อเลือกจากไฟล์';
    } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
      camErrTitle.textContent = 'ไม่พบกล้อง';
      camErrMsg.textContent   = 'อุปกรณ์นี้ไม่มีกล้อง — กดปุ่มด้านล่างเพื่อเลือกรูปจากไฟล์แทน';
    } else if (err.name === 'NotReadableError' || err.name === 'TrackStartError') {
      camErrTitle.textContent = 'กล้องถูกใช้งานอยู่';
      camErrMsg.textContent   = 'แอปอื่นกำลังใช้กล้องอยู่ — ปิดแอปนั้นแล้วลองใหม่ หรือเลือกจากไฟล์';
    } else {
      camErrTitle.textContent = 'เปิดกล้องไม่สำเร็จ';
      camErrMsg.textContent   = (err.message || 'เกิดข้อผิดพลาด') + ' — ลองเลือกจากไฟล์แทน';
    }
  }
}

function stopCamera() {
  if (camStream) {
    camStream.getTracks().forEach(t => t.stop());
    camStream = null;
  }
  if (camVideo) camVideo.srcObject = null;
}

function closeCamera() {
  stopCamera();
  if (camModal) camModal.style.display = 'none';
  document.body.style.overflow = '';
}

async function switchCamera() {
  camFacingMode = camFacingMode === 'user' ? 'environment' : 'user';
  stopCamera();
  await startCamera();
}

function capturePhoto() {
  if (!camStream || !camVideo.videoWidth) return;
  captureBtn.disabled = true;

  // Visual flash feedback
  camFlash.style.display = '';
  camFlash.style.animation = 'none';
  // Force reflow so the animation restarts on subsequent captures
  void camFlash.offsetWidth;
  camFlash.style.animation = '';
  setTimeout(() => { camFlash.style.display = 'none'; }, 400);

  // Capture current frame to a canvas
  const w = camVideo.videoWidth;
  const h = camVideo.videoHeight;
  const canvas = document.createElement('canvas');
  canvas.width  = w;
  canvas.height = h;
  const ctx = canvas.getContext('2d');

  // Mirror the canvas only for the front camera so the saved file
  // matches what the user saw on screen (selfie convention)
  if (camFacingMode === 'user') {
    ctx.translate(w, 0);
    ctx.scale(-1, 1);
  }
  ctx.drawImage(camVideo, 0, 0, w, h);

  // Export to JPEG (smaller than PNG, plenty of quality for face match)
  canvas.toBlob(blob => {
    if (!blob) {
      captureBtn.disabled = false;
      showToast('จับภาพไม่สำเร็จ ลองใหม่อีกครั้ง');
      return;
    }
    const file = new File([blob], `selfie-${Date.now()}.jpg`, { type: 'image/jpeg' });
    closeCamera();
    handleFile(file); // ← reuse the existing pipeline (preview + state + step)
  }, 'image/jpeg', 0.92);
}

async function startSearch() {
  if (!selectedFile) return;

  // PDPA: hard stop when consent is missing (should not happen because the
  // button stays disabled, but defense-in-depth for programmatic invocation)
  const consented = document.getElementById('consentCheckbox')?.checked;
  if (!consented) {
    showToast('กรุณายินยอมเงื่อนไข PDPA ก่อนเริ่มค้นหา');
    return;
  }

  setStep(2);
  document.getElementById('scanningOverlay').style.display = 'flex';
  document.getElementById('resultsArea').style.display = 'none';

  const formData = new FormData();
  formData.append('selfie', selectedFile);
  formData.append('consent', '1');

  // If Turnstile is enabled on the page, grab its response token.
  // The <input name="cf-turnstile-response"> is auto-injected by the widget.
  const turnstileToken = document.querySelector('input[name="cf-turnstile-response"]')?.value || '';
  if (turnstileToken) {
    formData.append('cf-turnstile-response', turnstileToken);
  }

  try {
    const resp = await fetch(`/api/face-search/${eventId}`, {
      method: 'POST', body: formData,
      headers: {
        // Force JSON responses from every middleware in the stack (CSRF 419,
        // RateLimit 429, ValidationException 422). Without these, those
        // middlewares return HTML — resp.json() throws and the user sees
        // only the generic "เกิดข้อผิดพลาด" fallback with no context.
        'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]')?.content || window.__csrf || '',
        'X-Requested-With': 'XMLHttpRequest',
        'Accept':           'application/json',
      },
      credentials: 'same-origin'
    });

    // Read the body as text first, then attempt JSON — if the server
    // returned HTML (e.g. whoops error page), we can still surface the
    // status code + a short snippet instead of hiding everything behind
    // a generic message.
    const raw = await resp.text();
    let json = null;
    try { json = JSON.parse(raw); } catch (_) { /* not JSON */ }

    document.getElementById('scanningOverlay').style.display = 'none';

    if (!resp.ok) {
      // Map known status codes to human-readable messages, fall back to
      // whatever the server sent in its JSON body, then to a generic
      // message with the status code appended so at least the support
      // person can debug it.
      const serverMsg = json?.message || '';
      const statusMap = {
        419: 'เซสชันหมดอายุ กรุณารีเฟรชหน้าและลองอีกครั้ง',
        422: serverMsg || 'ข้อมูลที่อัปโหลดไม่ถูกต้อง',
        429: serverMsg || 'ส่งคำขอบ่อยเกินไป กรุณารอสักครู่แล้วลองใหม่',
        503: serverMsg || 'ระบบ AI ยังไม่ได้ตั้งค่า กรุณาติดต่อผู้ดูแลระบบ',
      };
      const message = statusMap[resp.status]
        || serverMsg
        || `เกิดข้อผิดพลาด (HTTP ${resp.status}) กรุณาลองใหม่อีกครั้ง`;

      // Severity split: 4xx codes are user/input errors (selfie has no
      // face, rate limited, session expired) — these are EXPECTED business
      // responses and shouldn't render as red `console.error` in DevTools,
      // because they're not bugs and bug-trackers / Sentry pick up on
      // error-level logs as alerts. Use `warn` so the entry is still
      // visible during debugging but doesn't masquerade as a server bug.
      // 5xx + unrecognised failures keep the error-level log so real
      // production incidents stand out.
      const isClientError = resp.status >= 400 && resp.status < 500;
      const logFn = isClientError ? console.warn : console.error;
      logFn('[face-search] request failed', { status: resp.status, body: raw.slice(0, 500) });
      showToast(message);
      return;
    }

    if (!json) {
      // 2xx but not JSON — highly unusual. Log and show a diagnostic-y
      // message so we don't silently eat the response.
      console.error('[face-search] non-JSON 2xx response', raw.slice(0, 500));
      showToast('เกิดข้อผิดพลาด: เซิร์ฟเวอร์ตอบกลับในรูปแบบที่ไม่คาดคิด');
      return;
    }

    document.getElementById('resultsArea').style.display = '';
    setStep(3);

    if (json.success && json.matches && json.matches.length > 0) {
      document.getElementById('matchCount').innerHTML = '<i class="bi bi-check-circle-fill" style="font-size:0.7rem;"></i> พบ ' + json.match_count + ' รูป';
      document.getElementById('noResults').style.display = 'none';
      renderMatches(json.matches);
    } else {
      document.getElementById('matchGrid').innerHTML = '';
      document.getElementById('noResults').style.display = '';
      document.getElementById('matchCount').textContent = json.message || 'ไม่พบรูป';
      faceSearch._hideSelectionBar();
    }
  } catch (err) {
    // Only reachable on network-level failure now (fetch itself rejected)
    // — everything else is handled above.
    console.error('[face-search] network error', err);
    document.getElementById('scanningOverlay').style.display = 'none';
    showToast('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ กรุณาตรวจสอบอินเทอร์เน็ตแล้วลองใหม่');
  }
}

// ============================================================================
// Match rendering + selection/purchase flow
// ============================================================================
//
// Matches returned from the server are purchasable: the controller enriches
// each row with `event_id`, `file_id`, `price`, and `name`. We render them as
// selectable cards, track selection in a Set keyed by file_id, and mirror the
// event-page UX — POST to /cart/add-bulk for the cart flow, POST to
// /orders/express for the one-shot buy flow.
//
// Guests (checked server-side via @@auth in the button block) instead get
// promptLogin() which redirects to /login. We don't need to double-gate here
// because the buttons themselves change based on auth state.
const faceSearch = (function () {
  let matches = [];
  const selected = new Set();  // set of file_id strings

  // Active package (count-bundle). When set, every selected photo is priced
  // at `package.price / package.count` and the buy/cart payload carries
  // `package_id`. Mirrors the activePackage convention on show.blade.php.
  let activePackage = null;

  const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || window.__csrf || '';
  const BASE_PRICE_PER = {{ isset($pricePerPhoto) ? (float) $pricePerPhoto : 0 }};

  function render(ms) {
    matches = (ms || []).slice();
    selected.clear();

    const grid = document.getElementById('matchGrid');
    const priced = matches.some(m => Number(m.price) > 0)
                || (activePackage !== null)
                || BASE_PRICE_PER > 0;

    grid.innerHTML = matches.map((m, idx) => _renderCard(m, idx)).join('');

    // Hide the selection bar entirely for free events — nothing to buy.
    const bar = document.getElementById('selectionBar');
    if (bar) bar.style.display = priced ? '' : 'none';

    // Show the package strip only on priced events with results.
    const stripWrap = document.getElementById('pkgStripWrap');
    if (stripWrap) {
      stripWrap.style.display = (priced && matches.length > 0) ? '' : 'none';
    }

    _updateBar();
  }

  // Render a single match card. Pulled out of render() so selectPackage()
  // can repaint cards in place without re-running selection-clearing logic.
  function _renderCard(m, idx) {
    let perPhoto, priceLabel;
    if (activePackage && activePackage.bundle_type === 'face_match') {
      // Face-match: per-photo flips with selection size — show the
      // discount-adjusted live rate (price/N) when ≥1 photo is selected,
      // else the per-photo rate at full discount as a hint.
      const count = Math.max(1, selected.size);
      const q = _computeFaceMatchPrice(count);
      perPhoto   = q ? q.per_photo : 0;
      priceLabel = `เหมา · ลด ${activePackage.discount_pct}%`;
    } else if (activePackage) {
      perPhoto   = activePackage.price / activePackage.count;
      priceLabel = 'ราคาแพ็กเกจ';
    } else {
      perPhoto   = Number(m.price) || BASE_PRICE_PER || 0;
      priceLabel = `ตรงกัน ${m.confidence}%`;
    }
    const priceHtml = perPhoto > 0
      ? `<span class="price">฿${fmtMoney(perPhoto)}</span>`
      : `<span class="price free">ฟรี</span>`;
    return `
      <div class="match-card" data-idx="${idx}" onclick="faceSearch.toggle(${idx})">
        <div class="match-check"><i class="bi bi-check-lg"></i></div>
        <img src="${escapeHtml(m.thumbnail || m.photo_url)}" alt="Match" loading="lazy">
        <div class="match-badge"><i class="bi bi-check-circle-fill"></i>${m.confidence}%</div>
        <div class="match-card-footer">
          <span class="label">${escapeHtml(priceLabel)}</span>
          ${priceHtml}
        </div>
      </div>
    `;
  }

  // Re-render every card without resetting selection — used after a package
  // switch so existing checkmarks stay put while prices flip.
  function _repaintCards() {
    const grid = document.getElementById('matchGrid');
    if (!grid) return;
    grid.innerHTML = matches.map((m, idx) => _renderCard(m, idx)).join('');
    // Restore .selected classes since we rebuilt the DOM.
    matches.forEach((m, idx) => {
      const key = String(m.file_id ?? m.photo_id);
      if (selected.has(key)) {
        const card = grid.querySelector(`.match-card[data-idx="${idx}"]`);
        card?.classList.add('selected');
      }
    });
  }

  // ── Package picker ──────────────────────────────────────────────────────
  function selectPackage(btn) {
    if (!btn) return;
    const bundleType = btn.dataset.bundleType || 'count';
    if (bundleType === 'face_match') {
      activePackage = {
        id:           btn.dataset.packageId,
        name:         btn.dataset.name || 'เหมารูปฉัน',
        bundle_type:  'face_match',
        discount_pct: parseFloat(btn.dataset.discount)  || 0,
        max_price:    parseFloat(btn.dataset.maxPrice) || 0,
        count:        0, // not enforced — buyer chooses any subset
        price:        0, // computed dynamically
      };
      document.querySelectorAll('#pkgStrip .pkg-chip').forEach(c => c.classList.remove('active'));
      btn.classList.add('active');
      // Auto-select EVERY match — that's the whole point of "เหมารูปตัวเอง".
      // Buyer can deselect ones they don't want, total recomputes live.
      selected.clear();
      matches.forEach(m => {
        const key = String(m.file_id ?? m.photo_id);
        selected.add(key);
      });
    } else {
      activePackage = {
        id:          btn.dataset.packageId,
        name:        btn.dataset.name || '',
        bundle_type: 'count',
        count:       parseInt(btn.dataset.count, 10) || 0,
        price:       parseFloat(btn.dataset.price)  || 0,
      };
      document.querySelectorAll('#pkgStrip .pkg-chip').forEach(c => c.classList.remove('active'));
      btn.classList.add('active');
    }
    _repaintCards();
    _updateBar();
    _updatePkgInfoBar();
  }

  function clearPackage() {
    activePackage = null;
    document.querySelectorAll('#pkgStrip .pkg-chip').forEach(c => c.classList.remove('active'));
    _repaintCards();
    _updateBar();
    _updatePkgInfoBar();
  }

  // ── Face-match bundle pricing ──────────────────────────────────────
  // Mirrors BundleService::calculateFaceBundle (PHP) so the UI total
  // matches what the server will charge after expressCheckout. Keep these
  // formulas in sync — drift is hard to debug post-checkout.
  //
  //   original  = count * per_photo
  //   price     = original * (1 - discount/100)
  //   price     = min(price, max_price)        ← cap
  //   price     = max(price, per_photo)        ← floor (no ฿0 bundle)
  //
  // Returns null when math is impossible (no per-photo price set, etc.).
  function _computeFaceMatchPrice(count) {
    if (!activePackage || activePackage.bundle_type !== 'face_match') return null;
    const perPhoto = BASE_PRICE_PER;
    if (perPhoto <= 0 || count < 1) return null;
    const original  = count * perPhoto;
    let price       = original * (1 - (activePackage.discount_pct || 0) / 100);
    if (activePackage.max_price > 0) price = Math.min(price, activePackage.max_price);
    price           = Math.max(price, perPhoto);
    const savings   = Math.max(0, original - price);
    return {
      price:       Math.round(price * 100) / 100,
      original:    Math.round(original * 100) / 100,
      savings:     Math.round(savings * 100) / 100,
      savings_pct: original > 0 ? Math.round((original - price) / original * 100) : 0,
      per_photo:   count > 0 ? Math.round((price / count) * 100) / 100 : 0,
    };
  }

  function _updatePkgInfoBar() {
    const bar = document.getElementById('pkgInfoBar');
    if (!bar) return;
    if (!activePackage) {
      bar.style.display = 'none';
      return;
    }
    bar.style.display = '';

    // Face-match bundle: variable count. Show live "ซื้อ N รูป ราคา ฿X
    // ประหยัด ฿Y" so the buyer sees the deal as they pick.
    if (activePackage.bundle_type === 'face_match') {
      const have = selected.size;
      if (have === 0) {
        bar.className = 'pkg-info-bar warn';
        bar.innerHTML = `<i class="bi bi-info-circle-fill"></i>เลือกรูปอย่างน้อย <b>1</b> รูปเพื่อเหมา`;
        return;
      }
      const q = _computeFaceMatchPrice(have);
      if (!q) {
        bar.className = 'pkg-info-bar warn';
        bar.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i>คำนวณราคาไม่ได้`;
        return;
      }
      bar.className = 'pkg-info-bar ok';
      bar.innerHTML = `<i class="bi bi-stars"></i>เหมา <b>${have}</b> รูป · ราคารวม <b>฿${fmtMoney(q.price)}</b> · ประหยัด <b>฿${fmtMoney(q.savings)}</b> (${q.savings_pct}%)`;
      return;
    }

    // Count bundle: fixed count
    const need = activePackage.count;
    const have = selected.size;
    const diff = need - have;
    if (diff > 0) {
      bar.className = 'pkg-info-bar warn';
      bar.innerHTML = `<i class="bi bi-info-circle-fill"></i>เลือกอีก <b>${diff}</b> รูปเพื่อใช้แพ็กเกจ <b>${escapeHtml(activePackage.name)}</b> (รวม <b>฿${fmtMoney(activePackage.price)}</b>)`;
    } else if (diff === 0) {
      bar.className = 'pkg-info-bar ok';
      bar.innerHTML = `<i class="bi bi-check-circle-fill"></i>ครบแล้ว — แพ็กเกจ <b>${escapeHtml(activePackage.name)}</b> · <b>${need}</b> รูป · <b>฿${fmtMoney(activePackage.price)}</b>`;
    } else {
      bar.className = 'pkg-info-bar warn';
      bar.innerHTML = `<i class="bi bi-exclamation-triangle-fill"></i>เลือกเกินแพ็กเกจ — แพ็กเกจรับ <b>${need}</b> รูป แต่เลือกไว้ <b>${have}</b> รูป กรุณาเอาออก <b>${-diff}</b> รูป`;
    }
  }

  function toggle(idx) {
    const m = matches[idx];
    if (!m) return;
    const key = String(m.file_id ?? m.photo_id);
    const card = document.querySelector(`.match-card[data-idx="${idx}"]`);
    const isFaceMatch = activePackage && activePackage.bundle_type === 'face_match';
    if (selected.has(key)) {
      selected.delete(key);
      card?.classList.remove('selected');
    } else {
      // Count bundle: cap at package.count. Face-match: no cap (variable).
      if (activePackage && !isFaceMatch && selected.size >= activePackage.count) {
        showToast(`แพ็กเกจนี้รับ ${activePackage.count} รูป — เอารูปอื่นออกก่อน`);
        return;
      }
      selected.add(key);
      card?.classList.add('selected');
    }
    // Face-match per-photo display depends on selection size — repaint.
    if (isFaceMatch) _repaintCards();
    _updateBar();
    _updatePkgInfoBar();
  }

  function selectAll() {
    const isFaceMatch = activePackage && activePackage.bundle_type === 'face_match';
    matches.forEach((m, idx) => {
      const key = String(m.file_id ?? m.photo_id);
      // Count bundle: cap at package.count. Face-match: no cap.
      if (activePackage && !isFaceMatch && selected.size >= activePackage.count) return;
      selected.add(key);
      const card = document.querySelector(`.match-card[data-idx="${idx}"]`);
      card?.classList.add('selected');
    });
    if (isFaceMatch) _repaintCards();
    _updateBar();
    _updatePkgInfoBar();
  }

  function clearSelection() {
    selected.clear();
    document.querySelectorAll('.match-card.selected').forEach(c => c.classList.remove('selected'));
    _updateBar();
    _updatePkgInfoBar();
  }

  // Build the payload /cart/add-bulk and /orders/express expect. Matches the
  // shape used on the event page (show.blade.php → buildCartItems) — when a
  // package is active, every line is priced at `price/count` and carries
  // package_id so the server bills the bundle (not the per-photo total).
  function _buildItems() {
    let perPhoto = 0;
    if (activePackage && activePackage.bundle_type === 'face_match') {
      const q = _computeFaceMatchPrice(selected.size);
      perPhoto = q ? q.per_photo : 0;
    } else if (activePackage) {
      perPhoto = activePackage.price / activePackage.count;
    }
    const items = [];
    matches.forEach(m => {
      const key = String(m.file_id ?? m.photo_id);
      if (!selected.has(key)) return;
      items.push({
        event_id:   m.event_id,
        file_id:    key,
        name:       m.name || `Photo ${key}`,
        thumbnail:  m.thumbnail || m.photo_url || '',
        price:      activePackage ? perPhoto : (Number(m.price) || BASE_PRICE_PER || 0),
        package_id: activePackage ? activePackage.id : null,
      });
    });
    return items;
  }

  function _total() {
    if (activePackage && activePackage.bundle_type === 'face_match') {
      const q = _computeFaceMatchPrice(selected.size);
      return q ? q.price : 0;
    }
    if (activePackage) {
      // Count bundle: full bundle price only when count matches.
      if (selected.size === activePackage.count) return activePackage.price;
      const perPhoto = activePackage.price / activePackage.count;
      return selected.size * perPhoto;
    }
    let total = 0;
    matches.forEach(m => {
      const key = String(m.file_id ?? m.photo_id);
      if (selected.has(key)) total += Number(m.price) || BASE_PRICE_PER || 0;
    });
    return total;
  }

  // Validate selection against package rules. Returns true when ok to
  // submit, false after surfacing a Swal toast about the mismatch.
  function _validateSelection() {
    if (!activePackage) return true;

    // Face-match: variable count, just need ≥1 photo.
    if (activePackage.bundle_type === 'face_match') {
      if (selected.size < 1) {
        Swal.fire({
          icon: 'warning',
          title: 'เลือกรูปอย่างน้อย 1 รูป',
          text: 'กรุณาเลือกรูปที่ต้องการเหมาก่อนชำระเงิน',
          confirmButtonText: 'ตกลง',
          confirmButtonColor: '#ec4899',
        });
        return false;
      }
      return true;
    }

    const need = activePackage.count;
    const have = selected.size;
    if (have < need) {
      Swal.fire({
        icon: 'warning',
        title: 'เลือกรูปไม่ครบ',
        html: `แพ็กเกจ <b>${escapeHtml(activePackage.name)}</b> ต้องเลือก <b>${need}</b> รูป<br>คุณเลือกไว้ <b>${have}</b> รูป — เลือกเพิ่มอีก <b>${need - have}</b> รูป`,
        confirmButtonText: 'เลือกรูปเพิ่ม',
        confirmButtonColor: '#6366f1',
      });
      return false;
    }
    if (have > need) {
      Swal.fire({
        icon: 'warning',
        title: 'เลือกรูปเกินแพ็กเกจ',
        html: `แพ็กเกจรับ <b>${need}</b> รูป — กรุณาเอาออก <b>${have - need}</b> รูป`,
        confirmButtonText: 'เข้าใจแล้ว',
        confirmButtonColor: '#6366f1',
      });
      return false;
    }
    return true;
  }

  function _updateBar() {
    const count = selected.size;
    const countEl = document.getElementById('sbSelectedCount');
    const totalEl = document.getElementById('sbTotal');
    const bar     = document.getElementById('selectionBar');
    if (countEl) countEl.textContent = count;
    if (totalEl) totalEl.textContent = fmtMoney(_total());
    // Button states — disable cart/buy when nothing is selected.
    document.querySelectorAll('#selectionBar .sb-btn.cart, #selectionBar .sb-btn.buy').forEach(btn => {
      btn.disabled = count === 0;
    });
    // Keep bar visible on priced events once results are shown (even with 0 selected)
    // because it acts as "Select all" call-to-action. _hideSelectionBar() is used
    // when there are no results at all.
    if (bar && bar.dataset.forceHidden !== '1') {
      // do nothing — visibility was set in render()
    }
  }

  function _hideSelectionBar() {
    const bar = document.getElementById('selectionBar');
    if (bar) bar.style.display = 'none';
  }

  async function addToCart() {
    if (!_validateSelection()) return;
    const items = _buildItems();
    if (items.length === 0) { showToast('กรุณาเลือกรูปอย่างน้อย 1 รูป'); return; }

    const btns = document.querySelectorAll('#selectionBar .sb-btn');
    btns.forEach(b => b.disabled = true);
    try {
      const res = await fetch('{{ route("cart.add-bulk") }}', {
        method: 'POST',
        headers: {
          'Content-Type':     'application/json',
          'Accept':           'application/json',
          'X-CSRF-TOKEN':     CSRF,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ items, package_id: activePackage?.id ?? null }),
      });
      if (!res.ok) {
        const body = await res.text().catch(() => '');
        console.error('[face-search] addToCart failed', res.status, body.slice(0, 500));
        throw new Error('Failed');
      }
      window.location.href = '{{ route("cart.index") }}';
    } catch (e) {
      btns.forEach(b => b.disabled = false);
      _updateBar();
      Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเพิ่มสินค้าลงตะกร้าได้ กรุณาลองใหม่' });
    }
  }

  async function buyNow() {
    if (!_validateSelection()) return;
    const items = _buildItems();
    if (items.length === 0) { showToast('กรุณาเลือกรูปอย่างน้อย 1 รูป'); return; }

    const total = _total();
    let summary;
    if (activePackage && activePackage.bundle_type === 'face_match') {
      const q = _computeFaceMatchPrice(items.length);
      summary = `
        <div style="font-size:1rem; line-height:1.6;">
          <div style="margin-bottom:0.5rem; color:#ec4899; font-weight:700;">
            <i class="bi bi-stars"></i> ${escapeHtml(activePackage.name)}
          </div>
          <div>เหมา <b>${items.length}</b> รูป</div>
          <div style="text-decoration:line-through; color:#94a3b8; font-size:0.85rem;">ราคาปกติ ฿${fmtMoney(q?.original ?? 0)}</div>
          <div style="font-size:1.4rem; font-weight:800; color:#ec4899;">฿${fmtMoney(total)}</div>
          <div style="font-size:0.8rem; color:#10b981; font-weight:600;">ประหยัด ฿${fmtMoney(q?.savings ?? 0)} (${q?.savings_pct ?? 0}%)</div>
        </div>`;
    } else if (activePackage) {
      summary = `<div style="font-size:1.05rem;"><b>${escapeHtml(activePackage.name)}</b> — ${items.length} รูป<br>รวม <b>฿${fmtMoney(total)}</b></div>`;
    } else {
      summary = `<div style="font-size:1.05rem;"><b>${items.length}</b> รูป — รวม <b>฿${fmtMoney(total)}</b></div>`;
    }
    const confirm = await Swal.fire({
      icon: 'question',
      title: activePackage?.bundle_type === 'face_match' ? 'ยืนยันการเหมา & ชำระเงิน' : 'ยืนยันการซื้อ',
      html: summary,
      confirmButtonText: activePackage?.bundle_type === 'face_match'
        ? '<i class="bi bi-credit-card-fill mr-1"></i>ชำระเงินทันที'
        : '<i class="bi bi-lightning-charge-fill mr-1"></i>ซื้อเลย',
      cancelButtonText: 'ยกเลิก',
      showCancelButton: true,
      confirmButtonColor: activePackage?.bundle_type === 'face_match' ? '#ec4899' : '#f59e0b',
      cancelButtonColor:  '#6b7280',
    });
    if (!confirm.isConfirmed) return;

    const btns = document.querySelectorAll('#selectionBar .sb-btn');
    btns.forEach(b => b.disabled = true);
    try {
      const res = await fetch('{{ route("orders.express") }}', {
        method: 'POST',
        headers: {
          'Content-Type':     'application/json',
          'Accept':           'application/json',
          'X-CSRF-TOKEN':     CSRF,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ items, package_id: activePackage?.id ?? null }),
      });
      const data = await res.json().catch(() => ({}));
      if (res.ok && data.redirect) {
        window.location.href = data.redirect;
        return;
      }
      throw new Error(data.message || 'Failed');
    } catch (e) {
      btns.forEach(b => b.disabled = false);
      _updateBar();
      Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: e.message || 'ไม่สามารถสร้างคำสั่งซื้อได้ กรุณาลองใหม่' });
    }
  }

  function promptLogin() {
    Swal.fire({
      icon: 'info', title: 'กรุณาเข้าสู่ระบบ',
      text: 'คุณต้องเข้าสู่ระบบก่อนจึงจะสามารถซื้อรูปภาพได้',
      confirmButtonText: '<i class="bi bi-box-arrow-in-right mr-1"></i>เข้าสู่ระบบ',
      cancelButtonText: 'ยกเลิก', showCancelButton: true, confirmButtonColor: '#6366f1',
    }).then(r => { if (r.isConfirmed) window.location.href = '{{ route("login") }}'; });
  }

  // --- helpers (private) ---
  function fmtMoney(n) {
    return Number(n || 0).toLocaleString('th-TH', { maximumFractionDigits: 0 });
  }
  function escapeHtml(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
  }

  return {
    render, toggle, selectAll, clearSelection,
    selectPackage, clearPackage,
    addToCart, buyNow, promptLogin,
    _hideSelectionBar,
  };
})();

// Thin shim so existing callers (`renderMatches(json.matches)`) keep working
// without a rename. Delegates to the faceSearch namespace above.
function renderMatches(matches) { faceSearch.render(matches); }

function showToast(msg) {
  const existing = document.querySelector('.error-toast');
  if (existing) existing.remove();

  const toast = document.createElement('div');
  toast.className = 'error-toast';
  toast.innerHTML = `<div class="toast-icon"><i class="bi bi-exclamation-circle"></i></div><span class="toast-msg">${msg}</span>`;
  document.body.appendChild(toast);
  setTimeout(() => toast.remove(), 4000);
}
</script>
@endpush
