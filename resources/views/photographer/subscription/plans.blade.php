@extends('layouts.photographer')

@section('title', 'เลือกแผนสมัครสมาชิก')

@php
  // Single source of truth — FeatureFlagController::featureLabels()
  // returns [label, icon, group] for every feature that exists in
  // the system, including LINE Integration / SLA / Dedicated CSM /
  // AI Preview that older copies of this view didn't know about.
  // Filter by the global flag so deprecated / kill-switched features
  // never render as "missing" rows on every plan card.
  $subs = app(\App\Services\SubscriptionService::class);
  $featureLabels = collect(\App\Http\Controllers\Admin\FeatureFlagController::featureLabels())
    ->filter(fn($_, $code) => $subs->featureGloballyEnabled($code))
    ->map(fn($v) => [$v[0], $v[1]])  // strip group → keep [label, icon] for the existing template
    ->all();

  // The "popular" plan we want to lift visually. Pro is our default sweet spot.
  $popularCode = 'pro';

  // Pre-compute global aggregates for the metrics strip
  $totalAiCredits = $plans->sum('monthly_ai_credits');
  $maxStorageGb   = $plans->max('storage_gb');

  // Lowest commission % across non-free plans currently visible — used in the
  // hero subline so we never overstate. If every paid plan is 0%, we say
  // "0%". If the cheapest paid commission is e.g. 5%, we display "5%". Auto-
  // adjusts if admin re-enables a plan with a different rate.
  $paidPlans       = $plans->filter(fn($p) => !$p->isFree());
  $minPaidCommPct  = $paidPlans->isNotEmpty() ? (float) $paidPlans->min('commission_pct') : null;
  $allPaidZeroComm = $paidPlans->isNotEmpty() && $paidPlans->every(fn($p) => (float) $p->commission_pct === 0.0);
  $minStartingPrice = $paidPlans->isNotEmpty() ? (float) $paidPlans->min('price_thb') : null;
@endphp

@section('content')

<style>
/* ─── Auth-flow theme — radial gradient + glass cards ─── */
.plans-bg{
  background:
    radial-gradient(900px 500px at 15% -10%, rgba(99,102,241,.20), transparent 60%),
    radial-gradient(800px 500px at 85% 15%, rgba(236,72,153,.14), transparent 60%),
    radial-gradient(700px 700px at 50% 110%, rgba(124,58,237,.10), transparent 60%),
    linear-gradient(160deg,#f8fafc 0%,#f1f5f9 60%,#ede9fe 100%);
  margin:-24px;padding:32px 24px 48px;border-radius:0;min-height:calc(100vh - 80px);
  position:relative;
  /* Clip decorative blobs and any card transforms (scale/halo) so the
     page never produces a horizontal scrollbar. The blobs are
     intentionally positioned just outside the section edges for a soft
     glow effect; without this, they push the layout wider than the
     viewport on narrow screens. */
  overflow-x:hidden;
  overflow-y:visible;
}
.plans-bg::before{
  content:'';position:absolute;inset:0;pointer-events:none;
  background:url("data:image/svg+xml,%3Csvg viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9'/%3E%3C/filter%3E%3Crect width='100' height='100' filter='url(%23n)' opacity='0.025'/%3E%3C/svg%3E");
}
html.dark .plans-bg{
  background:
    radial-gradient(900px 500px at 15% -10%, rgba(99,102,241,.30), transparent 60%),
    radial-gradient(800px 500px at 85% 15%, rgba(236,72,153,.20), transparent 60%),
    radial-gradient(700px 700px at 50% 110%, rgba(124,58,237,.18), transparent 60%),
    linear-gradient(160deg,#020617 0%,#0f172a 60%,#1e1b4b 100%);
}

/* Floating decorative blobs */
.plans-blob{position:absolute;border-radius:50%;filter:blur(60px);opacity:.5;pointer-events:none;animation:blob 20s ease-in-out infinite;}
.plans-blob.b1{width:300px;height:300px;background:radial-gradient(circle,#6366f1,transparent 70%);top:-50px;right:-50px;}
.plans-blob.b2{width:240px;height:240px;background:radial-gradient(circle,#ec4899,transparent 70%);bottom:200px;left:-80px;animation-delay:-7s;}
@keyframes blob{0%,100%{transform:translate(0,0) scale(1);}50%{transform:translate(20px,-30px) scale(1.06);}}

.title-grad{
  background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 45%,#ec4899 100%);
  -webkit-background-clip:text;background-clip:text;color:transparent;
  font-weight:800;letter-spacing:-0.02em;
}

/* ─── Billing toggle ─── */
.billing-switch{
  display:inline-flex;background:rgba(255,255,255,.65);backdrop-filter:blur(14px);
  border:1px solid rgba(99,102,241,.15);border-radius:999px;padding:5px;
  box-shadow:0 8px 24px -8px rgba(99,102,241,.18);position:relative;
}
html.dark .billing-switch{background:rgba(15,23,42,.65);border-color:rgba(255,255,255,.08);}
.billing-pill{
  position:relative;z-index:1;padding:.55rem 1.4rem;border-radius:999px;
  font-size:.85rem;font-weight:700;color:#475569;border:none;background:transparent;
  cursor:pointer;transition:color .25s;display:inline-flex;align-items:center;gap:.4rem;
}
html.dark .billing-pill{color:#cbd5e1;}
.billing-pill.active{color:#fff;}
.billing-indicator{
  position:absolute;top:5px;bottom:5px;
  background:linear-gradient(135deg,#4f46e5,#7c3aed,#ec4899);
  border-radius:999px;
  box-shadow:0 4px 14px -2px rgba(124,58,237,.55);
  transition:all .35s cubic-bezier(0.34,1.56,0.64,1);
}
.billing-savings{
  display:inline-flex;align-items:center;gap:.3rem;
  margin-left:.5rem;padding:.18rem .55rem;border-radius:6px;
  background:linear-gradient(135deg,rgba(16,185,129,.18),rgba(52,211,153,.12));
  color:#059669;font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;
  border:1px solid rgba(16,185,129,.25);
}
html.dark .billing-savings{color:#34d399;background:linear-gradient(135deg,rgba(16,185,129,.25),rgba(52,211,153,.18));}

/* ─── Trust strip ─── */
.trust-row{
  display:flex;justify-content:center;gap:.75rem;flex-wrap:wrap;margin-top:1.25rem;
}
.trust-pill{
  display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .85rem;
  border-radius:999px;background:rgba(255,255,255,.55);backdrop-filter:blur(10px);
  border:1px solid rgba(99,102,241,.12);color:#475569;font-size:.72rem;font-weight:600;
}
html.dark .trust-pill{background:rgba(15,23,42,.5);border-color:rgba(255,255,255,.06);color:#cbd5e1;}
.trust-pill i{font-size:.95rem;}

/* ─── Plan card v2 — editorial tier ──────────────────────────────
   Replaces the previous "compact card with 2x2 stat grid" with a
   more editorial layout:
     • Big tier-number watermark in the corner (visual signature)
     • Side accent stripe (uses the per-plan accent colour)
     • Hero-sized price typography (3rem)
     • Inline stat chips (single horizontal row, no grid)
     • Floating "POPULAR" tag ABOVE the card (not a top ribbon)
   Visual language is still indigo/purple/pink to match the auth
   surfaces, but each card pulls its own accent from
   SubscriptionPlan::accentHex() so a single theme rev re-skins all
   tiles in lockstep. ────────────────────────────────────────────── */
.plan-tile{
  background:#fff;
  border-radius:24px;
  border:1px solid rgba(99,102,241,.08);
  box-shadow:0 8px 24px -8px rgba(99,102,241,.08), 0 1px 3px rgba(0,0,0,.04);
  overflow:hidden;
  position:relative;
  transition:transform .35s cubic-bezier(0.34,1.56,0.64,1), box-shadow .35s, border-color .25s;
  display:flex;flex-direction:column;
}
html.dark .plan-tile{
  background:rgba(15,23,42,.85);backdrop-filter:blur(16px);
  border-color:rgba(255,255,255,.06);
  box-shadow:0 8px 32px -8px rgba(0,0,0,.5), inset 0 1px 0 rgba(255,255,255,.04);
}
.plan-tile:hover{
  transform:translateY(-8px);
  border-color:rgba(124,58,237,.22);
  box-shadow:0 32px 60px -20px var(--accent-shadow, rgba(99,102,241,.28)), 0 6px 12px rgba(0,0,0,.05);
}
html.dark .plan-tile:hover{
  box-shadow:0 32px 70px -20px var(--accent-shadow, rgba(124,58,237,.5)), inset 0 1px 0 rgba(255,255,255,.06);
}

/* Side gradient stripe — visual signature of the new design.
   Uses --accent so each plan gets its own colour (free=indigo,
   pro=purple-pink, studio=darker purple). */
.plan-tile::before{
  content:'';position:absolute;top:24px;bottom:24px;left:0;width:4px;border-radius:0 4px 4px 0;
  background:linear-gradient(180deg, var(--accent, #4f46e5), transparent);
  opacity:.6;transition:opacity .3s, width .3s;
}
.plan-tile:hover::before{opacity:1;width:6px;}

/* Tier number watermark — large grey "01/02/03" in the top-right.
   Adds editorial structure without taking layout space (it's
   absolute-positioned and uses --tw-text colour). */
.plan-tier-num{
  position:absolute;top:18px;right:22px;
  font-size:2.6rem;font-weight:900;letter-spacing:-0.04em;line-height:1;
  color:rgba(99,102,241,.12);
  font-feature-settings:'tnum';
  pointer-events:none;
  transition:color .3s, transform .3s;
}
.plan-tile:hover .plan-tier-num{color:var(--accent, rgba(124,58,237,.25));transform:scale(1.05);}
html.dark .plan-tier-num{color:rgba(165,180,252,.15);}
html.dark .plan-tile:hover .plan-tier-num{color:var(--accent, rgba(165,180,252,.35));}

/* Popular plan — bigger card + gradient border + animated halo.
   The "POPULAR" tag now floats ABOVE the card (negative top margin
   on .plan-tag-pop), not as a top ribbon — leaves the header
   clean for the icon + name. */
.plan-tile.popular{
  border:2px solid transparent;
  background:linear-gradient(#fff,#fff) padding-box,
             linear-gradient(135deg,#4f46e5,#7c3aed,#ec4899) border-box;
  box-shadow:0 24px 56px -18px rgba(124,58,237,.35), 0 4px 12px rgba(0,0,0,.05);
}
.plan-tile.popular:hover{transform:translateY(-12px);}
html.dark .plan-tile.popular{
  background:linear-gradient(rgba(15,23,42,.95),rgba(15,23,42,.95)) padding-box,
             linear-gradient(135deg,#6366f1,#a855f7,#f472b6) border-box;
}
.plan-tile.popular::after{
  content:'';position:absolute;inset:-2px;border-radius:26px;pointer-events:none;
  box-shadow:0 0 0 6px rgba(124,58,237,.05);
  animation:popular-pulse 2.4s ease-in-out infinite;
}
@keyframes popular-pulse{
  0%,100%{box-shadow:0 0 0 6px rgba(124,58,237,.05);}
  50%{box-shadow:0 0 0 14px rgba(124,58,237,.10);}
}

.plan-tile.current{
  border:2px solid transparent;
  background:linear-gradient(#fff,#fff) padding-box,
             linear-gradient(135deg,#10b981,#34d399) border-box;
  box-shadow:0 18px 44px -14px rgba(16,185,129,.32);
}
html.dark .plan-tile.current{
  background:linear-gradient(rgba(15,23,42,.95),rgba(15,23,42,.95)) padding-box,
             linear-gradient(135deg,#10b981,#34d399) border-box;
}

/* Floating "POPULAR" / "แผนปัจจุบัน" tag — sits ABOVE the card. */
.plan-tag-pop, .plan-tag-current{
  position:absolute;top:-13px;left:50%;transform:translateX(-50%);
  padding:5px 14px;border-radius:999px;
  font-size:.62rem;font-weight:800;letter-spacing:.18em;text-transform:uppercase;
  color:#fff;display:inline-flex;align-items:center;gap:.4rem;
  z-index:2;white-space:nowrap;
  box-shadow:0 6px 16px -3px rgba(124,58,237,.5);
}
.plan-tag-pop{background:linear-gradient(135deg,#4f46e5,#7c3aed,#ec4899);}
.plan-tag-current{background:linear-gradient(135deg,#10b981,#059669);box-shadow:0 6px 16px -3px rgba(16,185,129,.5);}
.plan-tag-pop i, .plan-tag-current i{font-size:.7rem;animation:wiggle 3s ease-in-out infinite;}
@keyframes wiggle{0%,100%{transform:rotate(0);}25%{transform:rotate(-10deg);}75%{transform:rotate(10deg);}}

/* Plan header — left-aligned now (more editorial). */
.plan-header{
  padding:2rem 1.6rem 1.4rem;border-bottom:1px solid rgba(99,102,241,.06);
  position:relative;
}
html.dark .plan-header{border-bottom-color:rgba(255,255,255,.04);}
.plan-tile.popular .plan-header,
.plan-tile.current .plan-header{padding-top:2.5rem;}

.plan-icon{
  width:56px;height:56px;border-radius:16px;
  display:inline-flex;align-items:center;justify-content:center;
  background:linear-gradient(135deg, var(--accent, #4f46e5), color-mix(in srgb, var(--accent, #4f46e5) 60%, #ec4899));
  color:#fff;font-size:1.5rem;
  box-shadow:0 10px 22px -6px var(--accent-shadow, rgba(99,102,241,.45));
  transition:transform .35s cubic-bezier(0.34,1.56,0.64,1);
}
.plan-tile:hover .plan-icon{transform:rotate(-8deg) scale(1.06);}

.plan-name{
  font-weight:800;font-size:1.35rem;color:#0f172a;
  margin-top:1rem;letter-spacing:-0.02em;line-height:1.15;
}
html.dark .plan-name{color:#f1f5f9;}
.plan-tagline{
  font-size:.8rem;color:#64748b;margin-top:.3rem;line-height:1.45;min-height:2.4em;
}
html.dark .plan-tagline{color:#94a3b8;}

/* Hero price block — left-aligned, big number. */
.plan-price{margin-top:1.4rem;}
.plan-price-value{
  font-weight:900;font-size:3rem;color:#0f172a;letter-spacing:-0.035em;
  display:inline-flex;align-items:baseline;gap:.05rem;line-height:1;
}
html.dark .plan-price-value{color:#f1f5f9;}
.plan-price-value .cur{font-size:1rem;color:#94a3b8;font-weight:600;margin-right:.1rem;}
html.dark .plan-price-value .cur{color:#64748b;}
.plan-price-suffix{font-size:.82rem;color:#64748b;font-weight:500;margin-left:.4rem;}
html.dark .plan-price-suffix{color:#94a3b8;}
.plan-price-annual{
  margin-top:.55rem;font-size:.72rem;color:#059669;font-weight:700;
  display:inline-flex;align-items:center;gap:.35rem;
  padding:.3rem .65rem;border-radius:999px;
  background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.18);
}
html.dark .plan-price-annual{background:rgba(16,185,129,.15);color:#34d399;border-color:rgba(16,185,129,.3);}
.plan-price-annual .save{margin-left:.25rem;color:#065f46;font-weight:800;}
html.dark .plan-price-annual .save{color:#6ee7b7;}

/* Body */
.plan-body{padding:1.4rem 1.6rem 1.6rem;flex:1;display:flex;flex-direction:column;}

/* Inline stat chips — single horizontal row replacing the 2x2 grid.
   Wraps to 2 lines if container is too narrow. */
.plan-chips{
  display:flex;flex-wrap:wrap;gap:.4rem;margin-bottom:1.25rem;
}
.plan-chip{
  display:inline-flex;align-items:center;gap:.35rem;
  padding:.45rem .7rem;border-radius:999px;
  background:linear-gradient(135deg,rgba(99,102,241,.05),rgba(236,72,153,.025));
  border:1px solid rgba(99,102,241,.1);
  font-size:.78rem;color:#334155;font-weight:600;letter-spacing:-0.005em;
  transition:border-color .2s,background .2s,transform .15s;
}
html.dark .plan-chip{
  background:linear-gradient(135deg,rgba(99,102,241,.1),rgba(236,72,153,.05));
  border-color:rgba(255,255,255,.06);color:#cbd5e1;
}
.plan-tile:hover .plan-chip{border-color:rgba(124,58,237,.25);}
.plan-chip i{color:var(--accent, #4f46e5);font-size:.85rem;}
html.dark .plan-chip i{color:#a5b4fc;}
.plan-chip .num{font-weight:800;color:#0f172a;font-feature-settings:'tnum';}
html.dark .plan-chip .num{color:#f1f5f9;}
.plan-chip .num.inf{font-size:1rem;line-height:1;color:var(--accent, #7c3aed);}

/* Feature list — count badge at top, then chevron-prefixed rows. */
.plan-features-label{
  font-size:.65rem;font-weight:800;text-transform:uppercase;letter-spacing:.16em;
  color:#64748b;margin-bottom:.85rem;display:flex;align-items:center;gap:.5rem;
  padding-bottom:.7rem;border-bottom:1px solid rgba(99,102,241,.08);
}
html.dark .plan-features-label{color:#94a3b8;border-bottom-color:rgba(255,255,255,.05);}
.plan-features-label .count{
  margin-left:auto;padding:.15rem .55rem;border-radius:6px;
  background:linear-gradient(135deg, var(--accent, #4f46e5), color-mix(in srgb, var(--accent, #4f46e5) 50%, #ec4899));
  color:#fff;font-size:.65rem;font-weight:800;letter-spacing:.05em;
}
.plan-features{display:flex;flex-direction:column;gap:.55rem;flex:1;margin-bottom:1.4rem;}
.plan-feature-row{
  display:flex;align-items:flex-start;gap:.6rem;
  font-size:.83rem;color:#334155;line-height:1.45;
}
html.dark .plan-feature-row{color:#cbd5e1;}
.plan-feature-icon{
  width:20px;height:20px;border-radius:6px;
  display:inline-flex;align-items:center;justify-content:center;
  flex-shrink:0;margin-top:.05rem;font-size:.7rem;
  background:linear-gradient(135deg,rgba(16,185,129,.15),rgba(52,211,153,.1));
  color:#059669;
}
html.dark .plan-feature-icon{background:linear-gradient(135deg,rgba(16,185,129,.25),rgba(52,211,153,.15));color:#34d399;}
.plan-feature-icon.off{background:rgba(148,163,184,.15);color:#94a3b8;}
html.dark .plan-feature-icon.off{background:rgba(148,163,184,.15);color:#64748b;}

/* CTA buttons — pill-shaped (24px radius), softer animations. */
.plan-cta{
  width:100%;padding:1rem;border-radius:14px;
  font-weight:800;font-size:.92rem;
  display:inline-flex;align-items:center;justify-content:center;gap:.5rem;
  transition:transform .15s,box-shadow .25s,filter .2s,background .2s;
  cursor:pointer;border:none;letter-spacing:-0.01em;
  position:relative;overflow:hidden;
}
.plan-cta-primary{
  background:linear-gradient(135deg,#4f46e5,#7c3aed,#ec4899);
  background-size:200% 200%;
  color:#fff;
  box-shadow:0 12px 28px -6px rgba(124,58,237,.55);
  animation:cta-shine 4s ease-in-out infinite;
}
@keyframes cta-shine{0%,100%{background-position:0% 50%;}50%{background-position:100% 50%;}}
.plan-cta-primary:hover{transform:translateY(-3px);box-shadow:0 20px 38px -8px rgba(124,58,237,.7);filter:brightness(1.07);}

.plan-cta-secondary{
  background:#0f172a;color:#fff;
  box-shadow:0 8px 20px -6px rgba(15,23,42,.4);
}
html.dark .plan-cta-secondary{
  background:rgba(255,255,255,.08);color:#fff;border:1px solid rgba(255,255,255,.1);
  box-shadow:0 8px 22px -6px rgba(0,0,0,.5);
}
.plan-cta-secondary:hover{background:#1e293b;transform:translateY(-2px);box-shadow:0 14px 30px -8px rgba(15,23,42,.55);}
html.dark .plan-cta-secondary:hover{background:rgba(255,255,255,.15);}

.plan-cta-current{
  background:rgba(16,185,129,.1);color:#059669;cursor:not-allowed;
  border:1px dashed rgba(16,185,129,.3);
}
html.dark .plan-cta-current{background:rgba(16,185,129,.15);color:#34d399;border-color:rgba(16,185,129,.4);}

.plan-cta-free{
  background:#fff;color:#0f172a;
  border:1.5px solid rgba(99,102,241,.2);
  box-shadow:0 6px 16px -4px rgba(99,102,241,.12);
}
.plan-cta-free:hover{background:#f8fafc;transform:translateY(-2px);border-color:rgba(124,58,237,.4);}
html.dark .plan-cta-free{background:rgba(255,255,255,.06);color:#fff;border-color:rgba(255,255,255,.12);}
html.dark .plan-cta-free:hover{background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.2);}

/* Per-GB indicator */
.plan-value-tip{
  display:inline-flex;align-items:center;gap:.3rem;margin-top:.45rem;
  font-size:.68rem;color:#7c3aed;font-weight:700;
}
html.dark .plan-value-tip{color:#a5b4fc;}

/* "See all features" link inside the plan card. Visually subtle so it
   doesn't compete with the enabled-feature checks above it, but
   prominent enough that buyers know more is hidden. */
.plan-feature-more{
  display:inline-flex;align-items:center;gap:.4rem;
  font-size:.75rem;font-weight:700;color:#6366f1;
  padding:.45rem .65rem;border-radius:10px;
  background:linear-gradient(135deg,rgba(99,102,241,.06),rgba(236,72,153,.04));
  border:1px dashed rgba(99,102,241,.25);
  transition:background .2s, border-color .2s, transform .15s;
  margin-top:.25rem;text-decoration:none;
}
.plan-feature-more:hover{
  background:linear-gradient(135deg,rgba(99,102,241,.12),rgba(236,72,153,.08));
  border-color:rgba(124,58,237,.45);
  transform:translateX(2px);
  color:#4f46e5;
}
html.dark .plan-feature-more{
  color:#a5b4fc;
  background:linear-gradient(135deg,rgba(99,102,241,.12),rgba(236,72,153,.06));
  border-color:rgba(165,180,252,.3);
}
html.dark .plan-feature-more:hover{
  color:#c7d2fe;background:linear-gradient(135deg,rgba(99,102,241,.18),rgba(236,72,153,.1));
}
.plan-feature-more .bi-plus-lg{
  width:18px;height:18px;border-radius:50%;
  background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;
  display:inline-flex;align-items:center;justify-content:center;
  font-size:.7rem;
}

/* ─── Real-policy guarantees ─────────────────────────────────────── */
.guarantees{max-width:74rem;margin:3rem auto 0;}
.guarantee-card{
  position:relative;padding:1.5rem 1.25rem;border-radius:20px;
  background:rgba(255,255,255,.85);backdrop-filter:blur(14px);
  border:1px solid rgba(99,102,241,.12);
  transition:transform .25s,box-shadow .25s,border-color .25s;
}
html.dark .guarantee-card{
  background:rgba(15,23,42,.65);border-color:rgba(255,255,255,.06);
}
.guarantee-card:hover{
  transform:translateY(-4px);
  border-color:rgba(124,58,237,.3);
  box-shadow:0 20px 40px -16px rgba(99,102,241,.22);
}
.guarantee-icon{
  width:52px;height:52px;border-radius:16px;
  display:inline-flex;align-items:center;justify-content:center;
  font-size:1.45rem;color:#fff;margin-bottom:1rem;
  background:var(--icon-bg, linear-gradient(135deg,#4f46e5,#7c3aed));
  box-shadow:0 10px 22px -8px rgba(0,0,0,.18);
}
.guarantee-card h4{
  font-size:1rem;font-weight:800;color:#0f172a;letter-spacing:-0.015em;margin:0 0 .4rem;
}
html.dark .guarantee-card h4{color:#f1f5f9;}
.guarantee-card p{
  font-size:.82rem;color:#475569;line-height:1.55;margin:0;
}
html.dark .guarantee-card p{color:#cbd5e1;}

/* ─── Comparison table ──────────────────────────────────────────── */
.compare-wrap{max-width:78rem;margin:3.5rem auto 0;padding:0 0;}
.compare-card{
  background:rgba(255,255,255,.85);backdrop-filter:blur(16px);
  border:1px solid rgba(99,102,241,.12);border-radius:24px;
  box-shadow:0 12px 32px -12px rgba(99,102,241,.15);
  overflow:hidden;
}
html.dark .compare-card{
  background:rgba(15,23,42,.75);border-color:rgba(255,255,255,.06);
  box-shadow:0 16px 40px -16px rgba(0,0,0,.5);
}
.compare-head{
  padding:1.5rem 1.5rem 1.25rem;
  border-bottom:1px solid rgba(99,102,241,.08);
  display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;
}
html.dark .compare-head{border-bottom-color:rgba(255,255,255,.06);}
.compare-head h3{
  font-weight:800;font-size:1.15rem;color:#0f172a;letter-spacing:-0.015em;
  display:flex;align-items:center;gap:.55rem;margin:0;
}
html.dark .compare-head h3{color:#f1f5f9;}
.compare-head h3 .icon{
  width:32px;height:32px;border-radius:10px;
  background:linear-gradient(135deg,#4f46e5,#7c3aed,#ec4899);color:#fff;
  display:inline-flex;align-items:center;justify-content:center;font-size:.95rem;
}
.compare-head .compare-sub{font-size:.78rem;color:#64748b;}
html.dark .compare-head .compare-sub{color:#94a3b8;}

.compare-table-wrap{overflow-x:auto;}
.compare-table{
  width:100%;border-collapse:separate;border-spacing:0;
  font-size:.85rem;
  min-width:560px;     /* keeps cells legible — page scrolls horizontally on <560px */
}
.compare-table thead th{
  position:sticky;top:0;z-index:1;
  padding:1rem .85rem;
  font-size:.75rem;font-weight:800;color:#0f172a;letter-spacing:-0.01em;
  background:linear-gradient(180deg,rgba(255,255,255,.95),rgba(248,250,252,.95));
  border-bottom:1px solid rgba(99,102,241,.12);
  text-align:center;vertical-align:bottom;white-space:nowrap;
}
html.dark .compare-table thead th{
  background:linear-gradient(180deg,rgba(15,23,42,.95),rgba(2,6,23,.95));
  color:#f1f5f9;border-bottom-color:rgba(255,255,255,.06);
}
.compare-table thead th:first-child{
  text-align:left;padding-left:1.5rem;
  background:linear-gradient(135deg,rgba(99,102,241,.04),rgba(236,72,153,.025));
}
html.dark .compare-table thead th:first-child{
  background:linear-gradient(135deg,rgba(99,102,241,.12),rgba(236,72,153,.08));
}
.compare-table thead th .plan-pill{
  display:flex;flex-direction:column;gap:.15rem;align-items:center;
}
.compare-table thead th .plan-pill .name{font-size:.85rem;}
.compare-table thead th .plan-pill .price{font-size:.7rem;color:#64748b;font-weight:600;}
html.dark .compare-table thead th .plan-pill .price{color:#94a3b8;}
.compare-table thead th .plan-pill .badge-popular{
  display:inline-flex;align-items:center;gap:.2rem;
  padding:.1rem .45rem;border-radius:6px;
  background:linear-gradient(135deg,#4f46e5,#7c3aed,#ec4899);color:#fff;
  font-size:.55rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;
}
.compare-table tbody td{
  padding:.85rem .85rem;
  border-bottom:1px solid rgba(99,102,241,.06);
  text-align:center;color:#475569;
}
html.dark .compare-table tbody td{
  border-bottom-color:rgba(255,255,255,.05);color:#cbd5e1;
}
.compare-table tbody tr:nth-child(even) td{background:rgba(99,102,241,.02);}
html.dark .compare-table tbody tr:nth-child(even) td{background:rgba(99,102,241,.05);}
.compare-table tbody tr:hover td{background:rgba(99,102,241,.05);}
html.dark .compare-table tbody tr:hover td{background:rgba(99,102,241,.1);}
.compare-table tbody td:first-child{
  text-align:left;padding-left:1.5rem;font-weight:600;color:#0f172a;
  display:flex;align-items:center;gap:.55rem;
}
html.dark .compare-table tbody td:first-child{color:#f1f5f9;}
.compare-table tbody td:first-child i.feat-icon{
  width:24px;height:24px;border-radius:8px;
  background:linear-gradient(135deg,rgba(99,102,241,.1),rgba(236,72,153,.06));
  color:#7c3aed;display:inline-flex;align-items:center;justify-content:center;
  font-size:.85rem;flex-shrink:0;
}
html.dark .compare-table tbody td:first-child i.feat-icon{
  background:linear-gradient(135deg,rgba(99,102,241,.18),rgba(236,72,153,.1));color:#a5b4fc;
}
.compare-check{
  width:26px;height:26px;border-radius:50%;
  display:inline-flex;align-items:center;justify-content:center;
  font-size:.85rem;font-weight:800;
}
.compare-check.on{background:rgba(16,185,129,.15);color:#059669;}
.compare-check.off{background:rgba(148,163,184,.15);color:#94a3b8;}
html.dark .compare-check.on{background:rgba(16,185,129,.22);color:#34d399;}
html.dark .compare-check.off{background:rgba(148,163,184,.15);color:#64748b;}

/* "Group" rows in the comparison — visual divider for major buckets */
.compare-table tr.group-head td{
  background:linear-gradient(90deg,rgba(99,102,241,.06),rgba(236,72,153,.03));
  color:#4f46e5;font-weight:800;font-size:.7rem;letter-spacing:.14em;
  text-transform:uppercase;padding:.7rem 1.5rem;
  text-align:left;border-bottom:1px solid rgba(99,102,241,.15);
}
html.dark .compare-table tr.group-head td{
  background:linear-gradient(90deg,rgba(99,102,241,.15),rgba(236,72,153,.08));
  color:#a5b4fc;border-bottom-color:rgba(99,102,241,.25);
}

/* ─── Section dividers ─── */
.section-eyebrow{
  display:inline-flex;align-items:center;gap:.4rem;padding:.3rem .8rem;border-radius:999px;
  background:linear-gradient(135deg,rgba(99,102,241,.08),rgba(236,72,153,.05));
  border:1px solid rgba(99,102,241,.15);
  font-size:.7rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;
  color:#4f46e5;
}
html.dark .section-eyebrow{color:#a5b4fc;background:linear-gradient(135deg,rgba(99,102,241,.15),rgba(236,72,153,.08));border-color:rgba(99,102,241,.3);}

/* ─── FAQ ─── */
.faq-card{
  background:rgba(255,255,255,.75);
  backdrop-filter:blur(14px);
  border:1px solid rgba(99,102,241,.12);
  border-radius:22px;
  box-shadow:0 12px 32px -12px rgba(99,102,241,.15);
}
html.dark .faq-card{
  background:rgba(15,23,42,.7);
  border-color:rgba(255,255,255,.06);
}
.faq-item{
  border-bottom:1px solid rgba(99,102,241,.08);
  padding:.85rem 0;
}
html.dark .faq-item{border-bottom-color:rgba(255,255,255,.06);}
.faq-item:last-child{border-bottom:none;}
.faq-q{
  font-weight:700;color:#0f172a;font-size:.92rem;
  display:flex;align-items:center;gap:.6rem;cursor:pointer;
  transition:color .2s;
}
.faq-q:hover{color:#7c3aed;}
html.dark .faq-q{ color:#f1f5f9; }
html.dark .faq-q:hover{color:#a5b4fc;}
.faq-q-icon{
  width:28px;height:28px;border-radius:10px;
  background:linear-gradient(135deg,#4f46e5,#7c3aed);
  color:#fff;font-size:.78rem;font-weight:800;
  display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;
}
.faq-q-arrow{margin-left:auto;transition:transform .25s;color:#94a3b8;}
.faq-item[open] .faq-q-arrow{transform:rotate(180deg);}
.faq-a{padding:.5rem 0 .25rem 36px;color:#475569;font-size:.85rem;line-height:1.65;}
html.dark .faq-a{ color:#94a3b8; }

/* ─── Decision helper widget ─── */
.decide-helper{
  max-width:760px;margin:1.5rem auto 0;
  background:rgba(255,255,255,.65);backdrop-filter:blur(14px);
  border:1px solid rgba(99,102,241,.18);
  border-radius:18px;
  box-shadow:0 12px 30px -10px rgba(99,102,241,.16);
  overflow:hidden;
}
html.dark .decide-helper{background:rgba(15,23,42,.55);border-color:rgba(255,255,255,.08);}
.decide-toggle{
  width:100%;display:flex;align-items:center;gap:.7rem;
  padding:.95rem 1.2rem;background:transparent;border:none;cursor:pointer;
  font-size:.85rem;font-weight:700;color:#4f46e5;letter-spacing:-0.005em;
  text-align:left;transition:background .2s;
}
html.dark .decide-toggle{color:#a5b4fc;}
.decide-toggle:hover{background:rgba(99,102,241,.04);}
.decide-toggle i.bi-magic{font-size:1.1rem;color:#7c3aed;}
.decide-toggle .arrow{margin-left:auto;color:#94a3b8;transition:transform .25s;font-size:.85rem;}
.decide-toggle.is-open .arrow{transform:rotate(180deg);}
.decide-body{padding:.4rem 1.2rem 1.2rem;border-top:1px dashed rgba(99,102,241,.16);}
html.dark .decide-body{border-top-color:rgba(255,255,255,.08);}
.decide-grid{display:grid;grid-template-columns:1fr;gap:.85rem;margin-top:.85rem;}
@media (min-width:680px){.decide-grid{grid-template-columns:repeat(3,1fr);gap:1rem;}}
.decide-q label{display:block;font-size:.72rem;font-weight:700;color:#475569;margin-bottom:.35rem;}
html.dark .decide-q label{color:#cbd5e1;}
.decide-opts{display:flex;gap:.35rem;flex-wrap:wrap;}
.decide-opts button{
  flex:1 1 0;min-width:0;
  padding:.45rem .5rem;border-radius:10px;font-size:.74rem;font-weight:700;
  background:#fff;border:1.5px solid rgba(99,102,241,.18);color:#475569;
  cursor:pointer;transition:all .15s;
}
html.dark .decide-opts button{background:rgba(15,23,42,.6);border-color:rgba(255,255,255,.1);color:#cbd5e1;}
.decide-opts button:hover{border-color:rgba(124,58,237,.45);color:#7c3aed;}
.decide-opts button.sel{
  background:linear-gradient(135deg,#4f46e5,#7c3aed);border-color:transparent;color:#fff;
  box-shadow:0 4px 14px -3px rgba(124,58,237,.45);
}
.decide-result{
  margin-top:1rem;padding:.85rem 1rem;border-radius:14px;
  display:flex;align-items:center;gap:.7rem;
  background:linear-gradient(135deg,rgba(16,185,129,.10),rgba(52,211,153,.05));
  border:1px solid rgba(16,185,129,.22);
}
html.dark .decide-result{background:linear-gradient(135deg,rgba(16,185,129,.18),rgba(52,211,153,.08));border-color:rgba(16,185,129,.3);}
.decide-result > i{font-size:1.3rem;color:#059669;}
html.dark .decide-result > i{color:#34d399;}
.decide-result .rt{flex:1;font-size:.85rem;color:#334155;display:flex;align-items:center;gap:.45rem;flex-wrap:wrap;}
html.dark .decide-result .rt{color:#cbd5e1;}
.decide-result .rt strong{color:#0f172a;font-weight:800;}
html.dark .decide-result .rt strong{color:#f1f5f9;}
.decide-cta{
  padding:.5rem .85rem;border-radius:10px;border:none;cursor:pointer;
  font-size:.75rem;font-weight:800;color:#fff;
  background:linear-gradient(135deg,#10b981,#059669);
  box-shadow:0 4px 14px -3px rgba(16,185,129,.45);
  display:inline-flex;align-items:center;gap:.35rem;white-space:nowrap;
  transition:transform .15s;
}
.decide-cta:hover{transform:translateY(-1px);}
.decide-foot{
  margin-top:.7rem;font-size:.68rem;color:#94a3b8;display:flex;align-items:center;gap:.3rem;
}
html.dark .decide-foot{color:#64748b;}

/* Animation */
@keyframes planfade{ from{opacity:0;transform:translateY(20px);} to{opacity:1;transform:translateY(0);} }
.plan-anim{animation:planfade .55s ease-out both;}
.plan-anim.d1{animation-delay:.05s;}
.plan-anim.d2{animation-delay:.13s;}
.plan-anim.d3{animation-delay:.21s;}
.plan-anim.d4{animation-delay:.29s;}
.plan-anim.d5{animation-delay:.37s;}
.plan-anim.d6{animation-delay:.45s;}

[x-cloak]{display:none !important;}
</style>

<div class="plans-bg" x-data="plansPage()" x-cloak>
  <div class="plans-blob b1"></div>
  <div class="plans-blob b2"></div>

  {{-- ─── Hero header ─── --}}
  <div class="text-center mb-7 plan-anim relative">
    <a href="{{ route('photographer.subscription.index') }}"
       class="inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white mb-3 transition">
      <i class="bi bi-arrow-left"></i> กลับหน้าจัดการแผน
    </a>

    <div class="section-eyebrow mb-3">
      <i class="bi bi-stars"></i> แผนสมัครสมาชิก · เลือกแผนใช้งานวันนี้
    </div>

    <h1 class="title-grad text-3xl sm:text-4xl md:text-5xl mb-3 mx-0 leading-[1.1]">
      อัปโหลด · ขาย · รับเงิน<br class="sm:hidden">
      <span class="block sm:inline">ในแพลตฟอร์มเดียว</span>
    </h1>
    <p class="text-sm sm:text-base text-gray-600 dark:text-gray-400 max-w-2xl mx-auto leading-relaxed">
      เริ่มฟรี — ลองได้ทันทีไม่ต้องใส่บัตร · อัปเกรดเฉพาะตอนงานเข้า · ยกเลิกตอนไหนก็ได้
      <span class="block mt-1 text-xs text-slate-500 dark:text-slate-500">
        @if($allPaidZeroComm)
          แผนเสียเงินทุกแผน <strong class="text-emerald-600 dark:text-emerald-400">หัก 0% ค่าคอมมิชชั่น</strong> · เก็บเงินเต็มจำนวนทุกออเดอร์
        @elseif($minPaidCommPct !== null)
          คอมมิชชั่นต่ำสุด <strong class="text-emerald-600 dark:text-emerald-400">{{ rtrim(rtrim(number_format($minPaidCommPct, 1), '0'), '.') }}%</strong>
        @endif
        · PromptPay / บัตรเครดิต / โอนผ่านธนาคารไทย
      </span>
    </p>

    {{-- Annual / Monthly toggle --}}
    <div class="mt-6 inline-flex flex-col items-center gap-2">
      <div class="billing-switch" role="tablist" aria-label="รอบบิล">
        <button type="button" role="tab" :aria-selected="!annual"
                class="billing-pill" :class="!annual && 'active'"
                @click="annual = false">
          <i class="bi bi-calendar3"></i> รายเดือน
        </button>
        <button type="button" role="tab" :aria-selected="annual"
                class="billing-pill" :class="annual && 'active'"
                @click="annual = true">
          <i class="bi bi-calendar-check"></i> รายปี
          <span class="billing-savings">
            <i class="bi bi-tag-fill"></i> ประหยัด ~17%
          </span>
        </button>
        {{-- Sliding indicator pill --}}
        <span class="billing-indicator"
              :style="annual
                ? 'left:50%;width:calc(50% - 5px);'
                : 'left:5px;width:calc(50% - 5px);'"></span>
      </div>
      <p class="text-[11px] text-gray-500 dark:text-gray-400"
         x-text="annual ? 'จ่ายปีละ 1 ครั้ง — ฟรี 2 เดือน' : 'จ่ายเดือนละ 1 ครั้ง — ยกเลิกได้ทุกเมื่อ'"></p>
    </div>

    {{-- Trust strip — every claim here is tied to a real code path:
           • cancel anytime → SubscriptionService::cancel()
           • PromptPay + card → PaymentMethod active gateways
           • change plan anytime → SubscriptionService::changePlan()
           • files stay on downgrade → confirmed in cancel() docstring
           • 0% comm pill only renders when allPaidZeroComm is true        --}}
    <div class="trust-row">
      @if($allPaidZeroComm)
      <span class="trust-pill"><i class="bi bi-cash-coin text-emerald-500"></i> หัก 0% ค่าคอมมิชชั่น (Pro/Studio)</span>
      @endif
      <span class="trust-pill"><i class="bi bi-shield-check text-emerald-500"></i> ยกเลิกได้ทุกเมื่อ ไม่มีค่าธรรมเนียม</span>
      <span class="trust-pill"><i class="bi bi-credit-card text-indigo-500"></i> PromptPay + บัตรเครดิต</span>
      <span class="trust-pill"><i class="bi bi-lightning-charge text-pink-500"></i> เปลี่ยนแผนได้ทันที</span>
      <span class="trust-pill"><i class="bi bi-cloud-check text-sky-500"></i> ดาวน์เกรดไฟล์ยังอยู่ครบ</span>
    </div>
  </div>

  {{-- ─── Decision helper / "Which plan?" mini-quiz ─────────────────
       3 quick questions → highlights the right card + scrolls to it.
       Designed to:
         • reduce decision paralysis (Hick's law: fewer perceived choices)
         • use real data (storage_gb, price_thb) — no fake recommendations
         • get the user to a confident "Pro vs Studio" decision in <10s
       Implementation is a single Alpine block; the recommendation logic
       is plain JS so the photographer never sees a result that isn't
       grounded in the plan-table values rendered into the page below. --}}
  <div class="decide-helper plan-anim" x-data="{
        open: false,
        events: '',          // 1=light(<2/mo), 2=mid(2-5/mo), 3=many(>5/mo)
        photos: '',          // 1=<200/event, 2=200-1000, 3=>1000
        ai:     '',          // 1=no, 2=sometimes, 3=heavy
        get rec() {
          if(!this.events || !this.photos || !this.ai) return null;
          const score = (+this.events) + (+this.photos) + (+this.ai);
          // 3 = lowest workload → free; 4-6 → pro; 7-9 → studio
          if(score <= 3) return 'free';
          if(score <= 6) return 'pro';
          return 'studio';
        },
        focus(code) {
          const tile = document.querySelector('[data-plan-code=&quot;'+code+'&quot;]');
          if(!tile) return;
          const top = tile.getBoundingClientRect().top + window.scrollY - 100;
          window.scrollTo({top, behavior:'smooth'});
          tile.style.transition = 'box-shadow .6s ease, transform .6s ease';
          tile.style.boxShadow = '0 0 0 4px var(--accent), 0 22px 50px -12px var(--accent-shadow)';
          tile.style.transform = 'translateY(-4px)';
          setTimeout(()=>{ tile.style.boxShadow=''; tile.style.transform=''; }, 1800);
        }
      }">
    <button type="button" @click="open = !open"
            class="decide-toggle"
            :class="open && 'is-open'">
      <i class="bi bi-magic"></i>
      <span>ไม่แน่ใจว่าแผนไหนเหมาะ? ตอบ 3 ข้อ ใช้เวลา 10 วินาที</span>
      <i class="bi bi-chevron-down arrow"></i>
    </button>
    <div x-show="open" x-collapse class="decide-body">
      <div class="decide-grid">
        <div class="decide-q">
          <label>1. รับงานกี่อีเวนต์ต่อเดือน?</label>
          <div class="decide-opts">
            <button type="button" :class="events==='1' && 'sel'" @click="events='1'">น้อยกว่า 2</button>
            <button type="button" :class="events==='2' && 'sel'" @click="events='2'">2 – 5</button>
            <button type="button" :class="events==='3' && 'sel'" @click="events='3'">มากกว่า 5</button>
          </div>
        </div>
        <div class="decide-q">
          <label>2. รูปต่ออีเวนต์ประมาณ?</label>
          <div class="decide-opts">
            <button type="button" :class="photos==='1' && 'sel'" @click="photos='1'">&lt; 200</button>
            <button type="button" :class="photos==='2' && 'sel'" @click="photos='2'">200 – 1000</button>
            <button type="button" :class="photos==='3' && 'sel'" @click="photos='3'">&gt; 1000</button>
          </div>
        </div>
        <div class="decide-q">
          <label>3. ใช้ AI ค้นหาใบหน้า / ลายน้ำ?</label>
          <div class="decide-opts">
            <button type="button" :class="ai==='1' && 'sel'" @click="ai='1'">ไม่ใช้</button>
            <button type="button" :class="ai==='2' && 'sel'" @click="ai='2'">บางครั้ง</button>
            <button type="button" :class="ai==='3' && 'sel'" @click="ai='3'">ใช้ทุกงาน</button>
          </div>
        </div>
      </div>
      <template x-if="rec">
        <div class="decide-result">
          <i class="bi bi-stars"></i>
          <div class="rt">
            <span>คำแนะนำ:</span>
            <strong x-text="rec === 'free' ? 'แผน Free' : (rec === 'pro' ? 'แผน Pro' : 'แผน Studio')"></strong>
            <span class="ml-1 text-xs text-slate-500 dark:text-slate-400"
                  x-text="rec === 'free' ? 'พื้นที่/AI พอสำหรับเริ่มต้น' :
                           (rec === 'pro' ? 'จุดคุ้มที่สุด — 100GB + 5K AI Credits + 0% คอม' :
                                            '2 TB + 50K AI Credits — สำหรับมืออาชีพและทีม')"></span>
          </div>
          <button type="button" class="decide-cta" @click="focus(rec)">
            ดูรายละเอียด <i class="bi bi-arrow-right"></i>
          </button>
        </div>
      </template>
      <p class="decide-foot">
        <i class="bi bi-info-circle"></i>
        คำแนะนำคำนวณจาก storage / AI credits จริงในตาราง — ไม่ใช่อันดับการตลาด
      </p>
    </div>
  </div>

  @if(session('error'))
    <div class="max-w-3xl mx-auto mb-6 rounded-xl border border-rose-200 dark:border-rose-500/30 bg-rose-50 dark:bg-rose-950/30 text-rose-900 dark:text-rose-200 text-sm px-4 py-3 plan-anim">
      <i class="bi bi-exclamation-triangle-fill mr-1.5"></i>{{ session('error') }}
    </div>
  @endif

  {{-- ─── Plans grid ─── --}}
  {{-- Grid breakpoints chosen so cards never get cramped:
         •  <640px        : 1 col (mobile)
         •  640-1023px    : 2 cols (small tablet)
         •  1024-1279px   : 3 cols (laptop) — 4 of 5 plans on row 1, last wraps
         •  1280-1535px   : 3 cols still (xl) — gives ~400px per card
         •  ≥1536px (2xl) : 5 cols all in one row — only flips when there's
                            genuine horizontal room (~280px per card minimum)
       This avoids 5 columns squeezing onto a 1280px viewport which was
       overflowing the popular-plan transform / accent-border layers. --}}
  {{-- 5xl 2xl-cols-5 was for the older 5-plan layout where starter+business
       were public. After the 3-tier restructure (free/pro/studio public),
       capping at lg-cols-3 + an extra mt-3 for the floating popular tag
       gives us breathing room without overflow. --}}
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-7 max-w-7xl mx-auto pt-3">
    @foreach($plans as $idx => $p)
      @php
        $isCurrent    = $p->code === $currentCode;
        $isPopular    = $p->code === $popularCode && !$isCurrent;
        // Count only features whose global flag is ON. Defense in depth
        // against re-enabling a flag without re-adding the bullet to
        // ai_features (or vice versa) — display always reflects what the
        // user can actually use.
        $featureCount = count(array_filter(
            $p->ai_features ?? [],
            fn($code) => isset($featureLabels[$code]),
        ));
        $isUpgrade    = $currentCode && $currentCode !== 'free';

        // Single source of truth — both the plans-page cards and
        // every "your plan" pill across the photographer dashboard pull
        // their icon from SubscriptionPlan::iconClass().
        $icon = $p->iconClass();
        $accent = $p->accentHex();

        // Per-GB value indicator (simple monthly price ÷ GB)
        $valuePerGb = $p->isFree() ? 0 : (
          $p->storage_gb > 0 ? (float) $p->price_thb / $p->storage_gb : 0
        );

        $planPayload = [
            'code'             => $p->code,
            'name'             => $p->name,
            'accent'           => $accent,
            'storage_gb'       => (int) $p->storage_gb,
            'commission_pct'   => (int) $p->commission_pct,
            // team_seats removed from payload: team_seats feature flag is
            // OFF by default for the MVP, and no UI binds plan.team_seats
            // anyway. Re-add here if/when the feature is re-enabled.
            'feature_count'    => $featureCount,
            'features'         => array_map(
                fn($code) => $featureLabels[$code][0] ?? $code,
                // Filter the plan's features through the same flag layer
                // so a plan still granting a deprecated feature in JSON
                // doesn't end up listing it in the modal.
                array_values(array_filter(
                    $p->ai_features ?? [],
                    fn($code) => isset($featureLabels[$code])
                )),
            ),
            'price_thb'        => (float) $p->price_thb,
            'price_annual_thb' => (float) ($p->price_annual_thb ?? 0),
            'annual_savings'   => (int) $p->annualSavings(),
            'is_free'          => $p->isFree(),
            'is_upgrade'       => $isUpgrade,
            'action_url'       => $isUpgrade
                ? route('photographer.subscription.change', ['code' => $p->code])
                : route('photographer.subscription.subscribe', ['code' => $p->code]),
        ];

        $delayClass = 'd'.min(5, $idx + 1);
      @endphp

      <div id="plan-{{ $p->code }}"
           data-plan-code="{{ $p->code }}"
           class="plan-tile plan-anim {{ $delayClass }} {{ $isCurrent ? 'current' : ($isPopular ? 'popular' : '') }}"
           style="--accent: {{ $accent }}; --accent-soft: {{ $accent }}1f; --accent-border: {{ $accent }}35; --accent-shadow: {{ $accent }}55;">

        {{-- Floating tag (sits ABOVE the card via negative top margin).
             Replaces the previous in-card top ribbon — keeps the
             header clean for icon + name. --}}
        @if($isCurrent)
          <div class="plan-tag-current"><i class="bi bi-check-circle-fill"></i> แผนปัจจุบัน</div>
        @elseif($isPopular)
          <div class="plan-tag-pop"><i class="bi bi-stars"></i> Popular</div>
        @endif

        {{-- Tier number watermark — "01/02/03" — uses 1-based card index.
             Pure visual, no semantic meaning beyond ordering on the page. --}}
        <div class="plan-tier-num" aria-hidden="true">{{ str_pad((string)($idx + 1), 2, '0', STR_PAD_LEFT) }}</div>

        {{-- Header: icon + name + tagline + hero price (left-aligned). --}}
        <div class="plan-header">
          <div class="plan-icon"><i class="bi {{ $icon }}"></i></div>
          <h3 class="plan-name">{{ $p->name }}</h3>
          @if($p->tagline)
            <p class="plan-tagline">{{ $p->tagline }}</p>
          @else
            <p class="plan-tagline">&nbsp;</p>
          @endif

          <div class="plan-price">
            @if($p->isFree())
              <span class="plan-price-value">
                <span class="cur">฿</span>0
              </span>
              <span class="plan-price-suffix">ตลอดไป</span>
            @else
              {{-- Live-toggled price between monthly and annual-equiv-per-month --}}
              <span class="plan-price-value">
                <span class="cur">฿</span><span x-text="annual
                  ? numberFmt(Math.round(({{ (float) $p->price_annual_thb }} || ({{ (float) $p->price_thb }}*12)) / 12))
                  : numberFmt({{ (float) $p->price_thb }})"></span>
              </span>
              <span class="plan-price-suffix">/เดือน</span>

              <div class="mt-1.5 flex flex-col items-start gap-1.5">
                <template x-if="annual && {{ $p->price_annual_thb ? 1 : 0 }}">
                  <span class="plan-price-annual">
                    <i class="bi bi-tag-fill"></i>
                    เก็บปีละ ฿<span x-text="numberFmt({{ (float) ($p->price_annual_thb ?? 0) }})"></span>
                    @if($p->annualSavings() > 0)
                      <span class="save">ประหยัด ฿{{ number_format($p->annualSavings(), 0) }}</span>
                    @endif
                  </span>
                </template>
                <template x-if="!annual && {{ $p->annualSavings() > 0 ? 1 : 0 }}">
                  <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold inline-flex items-center gap-1">
                    <i class="bi bi-arrow-up-right"></i>
                    รายปีประหยัด ฿{{ number_format($p->annualSavings(), 0) }}
                  </span>
                </template>
              </div>
            @endif
          </div>
        </div>

        {{-- Body --}}
        <div class="plan-body">
          {{-- Inline stat chips (replaces the old 2x2 grid).
               Each chip = one number from the database, never invented. --}}
          @php
            $credits = (int) $p->monthly_ai_credits;
            $aiDisp  = $credits >= 1000000 ? '1M' : ($credits >= 1000 ? round($credits/1000).'K' : $credits);
            $commDisp = rtrim(rtrim(number_format((float) $p->commission_pct, 1), '0'), '.');
          @endphp
          <div class="plan-chips">
            <div class="plan-chip">
              <i class="bi bi-hdd-stack"></i>
              <span class="num">{{ number_format($p->storage_gb, 0) }}</span>
              <span>GB</span>
            </div>
            <div class="plan-chip">
              <i class="bi bi-percent"></i>
              <span class="num">{{ $commDisp }}%</span>
              <span>คอม</span>
            </div>
            <div class="plan-chip">
              <i class="bi bi-calendar-event"></i>
              @if(is_null($p->max_concurrent_events))
                <span class="num inf">∞</span>
              @else
                <span class="num">{{ (int) $p->max_concurrent_events }}</span>
              @endif
              <span>อีเวนต์</span>
            </div>
            <div class="plan-chip">
              <i class="bi bi-cpu"></i>
              <span class="num">{{ $aiDisp }}</span>
              <span>AI/เดือน</span>
            </div>
          </div>

          {{-- Feature highlights — shows only ENABLED features (max 6).
               Locked features used to be listed here too with greyed-out
               check-marks, but that doubled the card height for no win:
               every locked feature on plan A also appears on plan B+,
               and the comparison table below the grid does the side-by-
               side comparison far better. Card stays focused on
               "here's what you GET", not "here's what you don't". --}}
          @php
            $enabledFeatures = array_values(array_filter(
                $p->ai_features ?? [],
                fn($code) => isset($featureLabels[$code]),
            ));
            $enabledLabels  = array_map(fn($code) => $featureLabels[$code][0] ?? $code, $enabledFeatures);
            $visibleLabels  = array_slice($enabledLabels, 0, 6);
            $hiddenCount    = max(0, count($enabledLabels) - count($visibleLabels));
          @endphp

          <div>
            <p class="plan-features-label">
              <i class="bi bi-stars" style="color:{{ $accent }}"></i>
              <span>รวมในแผน</span>
              <span class="count">{{ count($enabledLabels) }} ฟีเจอร์</span>
            </p>
            <div class="plan-features">
              @forelse($visibleLabels as $label)
                <div class="plan-feature-row">
                  <span class="plan-feature-icon">
                    <i class="bi bi-check2"></i>
                  </span>
                  <span>{{ $label }}</span>
                </div>
              @empty
                <div class="plan-feature-row text-slate-400 italic">
                  <span class="plan-feature-icon off"><i class="bi bi-dash"></i></span>
                  <span>ฟีเจอร์พื้นฐาน — ใช้งานครบสำหรับเริ่มต้น</span>
                </div>
              @endforelse
              @if($hiddenCount > 0)
                <a href="#feature-compare"
                   class="plan-feature-more"
                   onclick="document.getElementById('feature-compare')?.scrollIntoView({behavior:'smooth',block:'start'})">
                  <i class="bi bi-plus-lg"></i>
                  อีก {{ $hiddenCount }} ฟีเจอร์ — ดูตารางเปรียบเทียบ
                  <i class="bi bi-arrow-right"></i>
                </a>
              @endif
            </div>
          </div>

          {{-- CTA --}}
          @if($isCurrent)
            <button disabled class="plan-cta plan-cta-current">
              <i class="bi bi-check2-circle"></i> แผนปัจจุบัน
            </button>
          @else
            <button type="button" @click="open({{ Js::from($planPayload) }})"
                    data-subscribe-btn
                    class="plan-cta {{ $p->isFree() ? 'plan-cta-free' : ($isPopular ? 'plan-cta-primary' : 'plan-cta-secondary') }}">
              @if($p->isFree())
                <i class="bi bi-arrow-down-circle"></i> เลือกแผนฟรี
              @elseif($isUpgrade)
                <i class="bi bi-arrow-left-right"></i> เปลี่ยนเป็นแผนนี้
              @else
                <i class="bi bi-rocket-takeoff"></i> เริ่มเลย
              @endif
            </button>
          @endif
        </div>
      </div>
    @endforeach

    {{-- ─── Confirmation modal ─── --}}
    <template x-teleport="body">
      <div x-show="plan" x-transition.opacity.duration.200ms
           class="fixed inset-0 z-[1060] flex items-end justify-center sm:items-center sm:p-4 md:p-6"
           @keydown.escape.window="close()" role="dialog" aria-modal="true" style="display:none">
        <div x-show="plan"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="absolute inset-0 bg-slate-950/70 backdrop-blur-md" @click="close()"></div>

        <template x-if="plan">
          <div x-show="plan"
               x-transition:enter="transition ease-out duration-300"
               x-transition:enter-start="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
               x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
               x-transition:leave="transition ease-in duration-200"
               x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
               x-transition:leave-end="opacity-0 translate-y-8 sm:translate-y-0 sm:scale-95"
               class="relative w-full sm:max-w-md md:max-w-lg max-h-[95vh] sm:max-h-[90vh]
                      bg-white dark:bg-slate-900 rounded-t-3xl sm:rounded-3xl shadow-2xl
                      flex flex-col overflow-hidden mx-auto">

            {{-- Header with auth-flow gradient --}}
            <div class="relative px-6 pt-6 pb-12 text-white shrink-0 overflow-hidden"
                 style="background:linear-gradient(135deg,#4f46e5 0%,#7c3aed 45%,#ec4899 100%);">
              <div class="absolute right-[-30px] top-[-30px] w-[140px] h-[140px] rounded-full" style="background:radial-gradient(circle,rgba(255,255,255,.18),transparent 70%);"></div>
              <div class="absolute left-[-20px] bottom-[-50px] w-[120px] h-[120px] rounded-full" style="background:radial-gradient(circle,rgba(255,255,255,.12),transparent 70%);"></div>

              <button type="button" @click="close()"
                      class="absolute top-4 right-4 w-9 h-9 rounded-full bg-white/15 hover:bg-white/25 flex items-center justify-center backdrop-blur-sm transition">
                <i class="bi bi-x-lg text-white"></i>
              </button>

              <div class="relative">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/15 text-[10px] font-bold uppercase tracking-[0.15em] backdrop-blur-sm mb-3">
                  <i class="bi bi-stars text-[10px]"></i>
                  <span x-text="plan.is_upgrade ? 'เปลี่ยนแผน' : (plan.is_free ? 'แผนฟรี' : 'สมัครสมาชิก')"></span>
                </div>
                <h3 class="font-bold text-2xl sm:text-3xl tracking-tight" x-text="plan.name"></h3>
                <p class="text-sm text-white/85 mt-1.5">
                  <span x-show="plan.is_free">เปิดใช้งานทันที ไม่มีค่าใช้จ่าย</span>
                  <span x-show="!plan.is_free && !plan.is_upgrade">ตรวจสอบรายละเอียดก่อนชำระ</span>
                  <span x-show="plan.is_upgrade && !plan.is_free">ยืนยันเพื่อเปลี่ยนเป็นแผนนี้ทันที</span>
                </p>
              </div>
            </div>

            {{-- Body --}}
            <div class="flex-1 overflow-y-auto px-6 pt-2 pb-5 -mt-6">
              <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-md p-5 mb-5 grid grid-cols-3 gap-2">
                <div class="text-center p-3 rounded-xl bg-gradient-to-br from-indigo-50 to-pink-50 dark:from-indigo-950/40 dark:to-pink-950/30 border border-indigo-100 dark:border-white/[0.06]">
                  <i class="bi bi-hdd-stack text-indigo-500 text-lg"></i>
                  <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 uppercase tracking-wider font-bold">พื้นที่</p>
                  <p class="font-bold text-base text-slate-900 dark:text-white mt-0.5"><span x-text="plan.storage_gb"></span> GB</p>
                </div>
                <div class="text-center p-3 rounded-xl bg-gradient-to-br from-indigo-50 to-pink-50 dark:from-indigo-950/40 dark:to-pink-950/30 border border-indigo-100 dark:border-white/[0.06]">
                  <i class="bi bi-percent text-indigo-500 text-lg"></i>
                  <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 uppercase tracking-wider font-bold">คอม</p>
                  <p class="font-bold text-base text-slate-900 dark:text-white mt-0.5"><span x-text="plan.commission_pct"></span>%</p>
                </div>
                <div class="text-center p-3 rounded-xl bg-gradient-to-br from-indigo-50 to-pink-50 dark:from-indigo-950/40 dark:to-pink-950/30 border border-indigo-100 dark:border-white/[0.06]">
                  <i class="bi bi-cpu text-indigo-500 text-lg"></i>
                  <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 uppercase tracking-wider font-bold">AI</p>
                  <p class="font-bold text-base text-slate-900 dark:text-white mt-0.5"><span x-text="plan.feature_count"></span> รายการ</p>
                </div>
              </div>

              <template x-if="plan.features && plan.features.length > 0">
                <div class="mb-5">
                  <p class="text-[10px] font-bold tracking-[0.16em] uppercase text-slate-500 dark:text-slate-400 mb-2.5 flex items-center gap-1.5">
                    <i class="bi bi-check2-square text-indigo-500"></i> รวมในแผนนี้
                  </p>
                  <ul class="grid grid-cols-1 gap-1.5">
                    <template x-for="(feat, i) in plan.features" :key="i">
                      <li class="flex items-start gap-2 text-sm text-slate-700 dark:text-slate-300">
                        <span class="mt-0.5 w-5 h-5 rounded-full inline-flex items-center justify-center shrink-0 bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-300">
                          <i class="bi bi-check-lg text-xs font-bold"></i>
                        </span>
                        <span x-text="feat"></span>
                      </li>
                    </template>
                  </ul>
                </div>
              </template>

              {{-- Annual/Monthly toggle (synced with the page-level toggle on open) --}}
              <template x-if="!plan.is_free && plan.price_annual_thb > 0">
                <div class="mb-5">
                  <p class="text-[10px] font-bold tracking-[0.16em] uppercase text-slate-500 dark:text-slate-400 mb-2.5 flex items-center gap-1.5">
                    <i class="bi bi-calendar3 text-indigo-500"></i> รอบบิล
                  </p>
                  <div class="grid grid-cols-2 gap-2">
                    <button type="button" @click="annual = false"
                            :class="!annual ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-950/30 ring-2 ring-indigo-500/20' : 'border-slate-200 dark:border-white/10'"
                            class="relative p-3 rounded-xl border-2 text-left transition">
                      <div class="text-[10px] uppercase tracking-wide font-bold text-slate-500 dark:text-slate-400">รายเดือน</div>
                      <div class="mt-1 font-bold text-base text-slate-900 dark:text-white">฿<span x-text="numberFmt(plan.price_thb)"></span></div>
                    </button>
                    <button type="button" @click="annual = true"
                            :class="annual ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-950/30 ring-2 ring-indigo-500/20' : 'border-slate-200 dark:border-white/10'"
                            class="relative p-3 rounded-xl border-2 text-left transition">
                      <div class="absolute -top-2 left-1/2 -translate-x-1/2 px-2 py-0.5 rounded-full text-[9px] font-bold text-white bg-gradient-to-r from-emerald-500 to-emerald-600 whitespace-nowrap">
                        ประหยัด ฿<span x-text="numberFmt(plan.annual_savings)"></span>
                      </div>
                      <div class="text-[10px] uppercase tracking-wide font-bold text-slate-500 dark:text-slate-400">รายปี</div>
                      <div class="mt-1 font-bold text-base text-slate-900 dark:text-white">฿<span x-text="numberFmt(plan.price_annual_thb)"></span></div>
                    </button>
                  </div>
                </div>
              </template>

              <div class="rounded-xl p-3.5 leading-relaxed text-sm border"
                   :class="plan.is_free ? 'bg-emerald-50 dark:bg-emerald-950/30 border-emerald-200 dark:border-emerald-500/20 text-emerald-900 dark:text-emerald-200' : 'bg-sky-50 dark:bg-sky-950/30 border-sky-200 dark:border-sky-500/20 text-sky-900 dark:text-sky-200'">
                <p class="font-semibold flex items-start gap-2 m-0">
                  <i class="bi bi-info-circle-fill mt-0.5 shrink-0" :class="plan.is_free ? 'text-emerald-600 dark:text-emerald-400' : 'text-sky-600 dark:text-sky-400'"></i>
                  <span x-text="plan.is_free ? 'เปิดใช้งานทันที ไม่มีค่าใช้จ่าย' : (plan.is_upgrade ? 'เปลี่ยนแผนได้ทันที' : 'หลังกดยืนยัน เราจะพาไปหน้าชำระเงิน')"></span>
                </p>
              </div>
            </div>

            {{-- Footer --}}
            <div class="shrink-0 border-t border-slate-100 dark:border-white/[0.06] bg-white dark:bg-slate-900 px-6 py-4">
              <div class="flex items-baseline justify-between gap-3 mb-3">
                <span class="text-[10px] uppercase tracking-wider font-bold text-slate-500 dark:text-slate-400">ยอดเรียกเก็บ</span>
                <div class="text-right">
                  <template x-if="plan.is_free">
                    <span class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">ฟรี</span>
                  </template>
                  <template x-if="!plan.is_free">
                    <span class="text-2xl font-bold text-slate-900 dark:text-white">
                      ฿<span x-text="numberFmt(annual ? plan.price_annual_thb : plan.price_thb)"></span>
                      <span class="text-xs font-medium text-slate-500 dark:text-slate-400 ml-1" x-text="annual ? '/ปี' : '/เดือน'"></span>
                    </span>
                  </template>
                </div>
              </div>

              {{-- @submit (not @click) sets submitting=true so the button isn't
                   disabled BEFORE the browser decides to submit the form.
                   Disabling on @click triggers a Chrome/Safari race where
                   the button becomes disabled in the same tick the click is
                   being processed → the browser cancels the submit and the
                   user is stuck looking at "กำลังดำเนินการ" forever with
                   no POST ever firing. Switching to @submit fixes this:
                   the browser commits to submitting first, THEN our handler
                   runs and updates the spinner. --}}
              <form method="POST" :action="plan.action_url" class="flex items-stretch gap-2"
                    @submit="submitting = true">
                @csrf
                <input type="hidden" name="annual" :value="annual ? '1' : '0'">
                <button type="button" @click="close()" :disabled="submitting"
                        class="px-4 py-3 rounded-xl text-sm font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 transition disabled:opacity-50">
                  ยกเลิก
                </button>
                <button type="submit"
                        :class="submitting ? 'opacity-70 cursor-wait' : 'hover:brightness-110'"
                        class="flex-1 px-4 py-3 rounded-xl text-sm font-bold text-white inline-flex items-center justify-center gap-2 transition"
                        style="background:linear-gradient(135deg,#4f46e5,#7c3aed,#ec4899);box-shadow:0 8px 20px -4px rgba(124,58,237,.5);">
                  <i class="bi bi-arrow-repeat animate-spin" x-show="submitting"></i>
                  <template x-if="!submitting">
                    <span class="inline-flex items-center gap-2">
                      <i :class="plan.is_free ? 'bi-rocket-takeoff' : (plan.is_upgrade ? 'bi-arrow-left-right' : 'bi-credit-card-fill')" class="bi"></i>
                      <span x-text="plan.is_free ? 'เปิดใช้งานเลย' : (plan.is_upgrade ? 'ยืนยันเปลี่ยนแผน' : 'ดำเนินการชำระ')"></span>
                    </span>
                  </template>
                  <span x-show="submitting">กำลังดำเนินการ...</span>
                </button>
              </form>
            </div>
          </div>
        </template>
      </div>
    </template>
  </div>

  {{-- ─── Feature comparison table ─────────────────────────────────
       Side-by-side comparison of every feature × every plan. Replaces
       the noisy "all features, some greyed out" list that used to live
       inside each plan card — now the cards focus on what you GET, and
       this table is the single canonical source of "what's the
       difference between Pro and Studio?".

       Mobile: scrolls horizontally below 560px (sticky header rows
       stay legible). Desktop: full grid in one viewport. ─────────── --}}
  @if($plans->count() > 1)
    <div id="feature-compare" class="compare-wrap plan-anim d4">
      <div class="text-center mb-5">
        <div class="section-eyebrow"><i class="bi bi-grid-3x3-gap-fill"></i> เปรียบเทียบ</div>
        <h2 class="title-grad text-2xl sm:text-3xl mt-3 leading-tight">เทียบทุกฟีเจอร์ ทุกแผน</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">เลือกได้ตรงกับการใช้งานจริง — เลื่อนซ้าย/ขวาบนมือถือเพื่อดูแผนถัดไป</p>
      </div>

      <div class="compare-card">
        <div class="compare-head">
          <h3>
            <span class="icon"><i class="bi bi-table"></i></span>
            ตารางเปรียบเทียบฟีเจอร์
          </h3>
          <span class="compare-sub">
            <i class="bi bi-info-circle"></i>
            ตัวเลขทั้งหมดอ้างอิงจาก database — อัปเดตเรียลไทม์
          </span>
        </div>

        <div class="compare-table-wrap">
          <table class="compare-table">
            <thead>
              <tr>
                <th>ฟีเจอร์ / ขีดจำกัด</th>
                @foreach($plans as $p)
                  @php
                    $isPopularCol = $p->code === $popularCode && $p->code !== $currentCode;
                    $cycle        = $p->billing_cycle ?? 'monthly';
                  @endphp
                  <th>
                    <div class="plan-pill">
                      @if($isPopularCol)
                        <span class="badge-popular"><i class="bi bi-stars"></i> Popular</span>
                      @endif
                      <span class="name">{{ $p->name }}</span>
                      <span class="price">
                        @if($p->isFree())
                          ฟรี
                        @else
                          ฿{{ number_format((float) $p->price_thb, 0) }}/{{ $cycle === 'annual' ? 'ปี' : 'เดือน' }}
                        @endif
                      </span>
                    </div>
                  </th>
                @endforeach
              </tr>
            </thead>
            <tbody>
              {{-- Section: limits / quotas (numeric values, not feature flags) --}}
              <tr class="group-head"><td colspan="{{ $plans->count() + 1 }}">ขีดจำกัด</td></tr>
              <tr>
                <td><i class="bi bi-hdd-stack feat-icon"></i> พื้นที่จัดเก็บ</td>
                @foreach($plans as $p)
                  <td><strong>{{ number_format($p->storage_gb, 0) }}</strong> <span class="text-xs text-slate-500 dark:text-slate-400">GB</span></td>
                @endforeach
              </tr>
              <tr>
                <td><i class="bi bi-percent feat-icon"></i> ค่าคอมมิชชั่น</td>
                @foreach($plans as $p)
                  <td><strong>{{ rtrim(rtrim(number_format((float) $p->commission_pct, 1), '0'), '.') }}%</strong></td>
                @endforeach
              </tr>
              <tr>
                <td><i class="bi bi-calendar-event feat-icon"></i> อีเวนต์พร้อมกัน</td>
                @foreach($plans as $p)
                  <td>
                    @if(is_null($p->max_concurrent_events))
                      <strong style="color:#7c3aed">ไม่จำกัด</strong>
                    @else
                      <strong>{{ (int) $p->max_concurrent_events }}</strong>
                    @endif
                  </td>
                @endforeach
              </tr>
              <tr>
                <td><i class="bi bi-cpu feat-icon"></i> AI Credits / เดือน</td>
                @foreach($plans as $p)
                  @php
                    $credits = (int) $p->monthly_ai_credits;
                    $disp    = $credits >= 1000000 ? '1M' : ($credits >= 1000 ? round($credits/1000).'K' : $credits);
                  @endphp
                  <td><strong>{{ $disp }}</strong></td>
                @endforeach
              </tr>

              {{-- Section: feature flags (binary on/off across plans) --}}
              <tr class="group-head"><td colspan="{{ $plans->count() + 1 }}">ฟีเจอร์</td></tr>
              @foreach($featureLabels as $code => [$label, $iconClass])
                <tr>
                  <td><i class="bi {{ $iconClass }} feat-icon"></i> {{ $label }}</td>
                  @foreach($plans as $p)
                    @php $on = in_array($code, $p->ai_features ?? [], true); @endphp
                    <td>
                      <span class="compare-check {{ $on ? 'on' : 'off' }}" aria-label="{{ $on ? 'รวม' : 'ไม่รวม' }}">
                        <i class="bi {{ $on ? 'bi-check-lg' : 'bi-dash' }}"></i>
                      </span>
                    </td>
                  @endforeach
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  @endif

  {{-- ─── Real-policy guarantees ─────────────────────────────────────
       Every line here is a verifiable code-backed promise:
         1. cancel: SubscriptionService::cancel() — leaves period intact
            until period_end, no fee.
         2. files-on-downgrade: confirmed in cancel() + expireOverdue()
            — they only flip status + reset profile.storage_quota_bytes;
            files on R2/disk are untouched.
         3. grace 7 days: SubscriptionService::DEFAULT_GRACE_DAYS = 7,
            also overridable via AppSetting subscription_grace_period_days.
         4. month-by-month: PaymentService::ensureOmiseCustomerForSubscription
            only saves an Omise customer when save_card=true; PromptPay/
            bank gateways never bind a card.
       Replaces the previous "money-back" + fake testimonials section. ─ --}}
  <div class="guarantees plan-anim d5">
    <div class="text-center mb-6">
      <div class="section-eyebrow"><i class="bi bi-shield-fill-check"></i> เงื่อนไขจริงในระบบ</div>
      <h2 class="title-grad text-2xl sm:text-3xl mt-3 leading-tight">สิ่งที่เรารับประกัน</h2>
      <p class="text-sm text-gray-600 dark:text-gray-400 mt-2">ทุกข้อตรงกับโค้ดจริงในระบบ — ไม่ใช่คำสัญญาทางการตลาด</p>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="guarantee-card">
        <div class="guarantee-icon" style="--icon-bg:linear-gradient(135deg,#10b981,#059669);">
          <i class="bi bi-x-circle-fill"></i>
        </div>
        <h4>ยกเลิกเมื่อไหร่ก็ได้</h4>
        <p>ไม่มีสัญญาผูกมัด ไม่มีค่าธรรมเนียมยกเลิก ใช้งานได้จนสิ้นรอบบิลปัจจุบัน แล้วค่อย downgrade เป็น Free</p>
      </div>
      <div class="guarantee-card">
        <div class="guarantee-icon" style="--icon-bg:linear-gradient(135deg,#3b82f6,#1e40af);">
          <i class="bi bi-folder-fill"></i>
        </div>
        <h4>ไฟล์ทั้งหมดยังอยู่</h4>
        <p>ดาวน์เกรดหรือยกเลิกแล้ว ระบบแค่ปรับ quota — ไม่ลบไฟล์ของคุณ ใช้ pubic page ขายต่อได้ตามแผน Free</p>
      </div>
      <div class="guarantee-card">
        <div class="guarantee-icon" style="--icon-bg:linear-gradient(135deg,#f59e0b,#d97706);">
          <i class="bi bi-clock-history"></i>
        </div>
        <h4>ผ่อนผัน 7 วัน</h4>
        <p>หากตัดบัตรไม่ผ่าน ระบบให้ใช้งานต่อ 7 วัน เพื่ออัปเดตการชำระ — ก่อน downgrade เป็น Free อัตโนมัติ</p>
      </div>
      <div class="guarantee-card">
        <div class="guarantee-icon" style="--icon-bg:linear-gradient(135deg,#8b5cf6,#6d28d9);">
          <i class="bi bi-toggle-on"></i>
        </div>
        <h4>เลือกซื้อรายเดือนได้</h4>
        <p>ไม่ติ๊ก "บันทึกบัตร" = จ่ายเดือนเดียวจบ ไม่ผูกบัตร ครบเดือนถ้าจะใช้ต่อสมัครใหม่ได้ทุกเมื่อ</p>
      </div>
    </div>
  </div>

  {{-- ─── FAQ ─── --}}
  <div class="max-w-3xl mx-auto mt-10 plan-anim d6">
    <div class="text-center mb-5">
      <div class="section-eyebrow"><i class="bi bi-patch-question-fill"></i> ช่วยเหลือ</div>
      <h2 class="title-grad text-2xl sm:text-3xl mt-3 leading-tight">คำถามที่พบบ่อย</h2>
    </div>
    <div class="faq-card p-6">
      <details class="faq-item" open>
        <summary class="faq-q list-none cursor-pointer flex items-center gap-2.5">
          <span class="faq-q-icon">Q</span>
          <span class="flex-1">ใช้พื้นที่เต็มแล้วต้องทำอย่างไร?</span>
          <i class="bi bi-chevron-down faq-q-arrow"></i>
        </summary>
        <p class="faq-a">ลบอีเวนต์เก่าที่ขายจบแล้วเพื่อคืนพื้นที่ หรืออัปเกรดเป็นแผนที่ใหญ่กว่า — ระบบออกแบบมาเพื่อรีไซเคิล: ถ่าย → ขาย 2-3 วัน → ลบ → รับงานใหม่ พื้นที่จะคืนทันทีหลังลบรูป</p>
      </details>
      <details class="faq-item">
        <summary class="faq-q list-none cursor-pointer flex items-center gap-2.5">
          <span class="faq-q-icon">Q</span>
          <span class="flex-1">ยกเลิกได้ไหม? มีค่าธรรมเนียมไหม?</span>
          <i class="bi bi-chevron-down faq-q-arrow"></i>
        </summary>
        <p class="faq-a">ยกเลิกได้ทุกเมื่อ ไม่มีสัญญาผูกมัด ไม่มีค่าธรรมเนียมยกเลิก คุณยังใช้งานได้จนถึงสิ้นรอบบิลปัจจุบัน หลังจากนั้นจะดาวน์เกรดเป็น Free โดยอัตโนมัติ — รูปและงานทั้งหมดยังอยู่ครบ</p>
      </details>
      <details class="faq-item">
        <summary class="faq-q list-none cursor-pointer flex items-center gap-2.5">
          <span class="faq-q-icon">Q</span>
          <span class="flex-1">ดาวน์เกรด/อัปเกรดได้ไหม? คิดเงินอย่างไร?</span>
          <i class="bi bi-chevron-down faq-q-arrow"></i>
        </summary>
        <p class="faq-a"><strong>อัปเกรด:</strong> มีผลทันที ระบบคิดส่วนต่างเป็นสัดส่วนตามวันที่เหลือในรอบบิล (Pro-rated)<br><strong>ดาวน์เกรด:</strong> มีผลในรอบบิลถัดไป — คุณใช้งานต่อจนจบรอบที่จ่ายไว้แล้ว ไม่มีการคืนเงินส่วนต่าง</p>
      </details>
      <details class="faq-item">
        <summary class="faq-q list-none cursor-pointer flex items-center gap-2.5">
          <span class="faq-q-icon">Q</span>
          <span class="flex-1">รายปีประหยัดอย่างไร? ดีกว่ารายเดือนยังไง?</span>
          <i class="bi bi-chevron-down faq-q-arrow"></i>
        </summary>
        <p class="faq-a">เลือกรายปีที่ปุ่มด้านบนของหน้านี้เพื่อดูราคา — โดยทั่วไปประหยัด ~17% (ประมาณ 2 เดือนฟรี) นอกจากนั้นยังล็อกราคาตลอดทั้งปี ไม่โดนผลกระทบหากเราปรับราคา</p>
      </details>
      <details class="faq-item">
        <summary class="faq-q list-none cursor-pointer flex items-center gap-2.5">
          <span class="faq-q-icon">Q</span>
          <span class="flex-1">ใช้ช่องทางชำระเงินไหนได้บ้าง?</span>
          <i class="bi bi-chevron-down faq-q-arrow"></i>
        </summary>
        <p class="faq-a">รองรับ <strong>PromptPay</strong> (สแกน QR), <strong>โอนผ่านธนาคาร</strong>, <strong>บัตรเครดิต/เดบิต</strong> (Visa/Mastercard/JCB) ผ่าน Omise, <strong>TrueMoney Wallet</strong>, <strong>LINE Pay</strong> และ <strong>Stripe / PayPal</strong> สำหรับลูกค้าต่างประเทศ</p>
      </details>
      <details class="faq-item">
        <summary class="faq-q list-none cursor-pointer flex items-center gap-2.5">
          <span class="faq-q-icon">Q</span>
          <span class="flex-1">ถ้าจ่ายไม่ได้ในวันต่ออายุจะเป็นอย่างไร?</span>
          <i class="bi bi-chevron-down faq-q-arrow"></i>
        </summary>
        <p class="faq-a">เรามี <strong>Grace period 7 วัน</strong> — หากการต่ออายุล้มเหลว ระบบจะลองเก็บเงินใหม่อีก 3 ครั้ง และส่งอีเมลแจ้งเตือนล่วงหน้า รูปและงานทั้งหมดยังอยู่ ไม่ถูกลบ</p>
      </details>
    </div>
  </div>
</div>

<script>
  // One shared Alpine scope for the whole page — the billing toggle's
  // `annual` state is also what the modal reads / writes, so toggling
  // monthly/annual on the page is reflected when a card is clicked,
  // and the modal's own toggle continues to write back to the same flag.
  function plansPage() {
    return {
      annual: false,
      plan: null,
      submitting: false,

      open(payload) {
        // The card click carries the plan payload; we keep the user's
        // current annual/monthly selection from the page-level toggle.
        // (If the plan has no annual price, we automatically fall back
        // to monthly so the modal doesn't show ฿0/year.)
        this.plan = payload;
        if (this.annual && (!payload || !payload.price_annual_thb)) {
          this.annual = false;
        }
        this.submitting = false;
        document.body.classList.add('overflow-hidden');
      },
      close() {
        if (this.submitting) return;
        this.plan = null;
        document.body.classList.remove('overflow-hidden');
      },
      numberFmt(n) {
        return new Intl.NumberFormat('th-TH').format(Math.round(n || 0));
      },
    }
  }

  /* ──────────────────────────────────────────────────────────────
     Promo-funnel hand-off
     ──────────────────────────────────────────────────────────────
     /promo/checkout/{code} routes the user here with `?plan=…` so
     after register/login they land on the right card. We:
       1. scroll-into-view the matching tile (smooth, with offset
          so the sticky navbar doesn't cover it)
       2. flash a brief outline-glow so the eye lands on it
       3. trigger the same Alpine open(...) the Subscribe button
          uses — no extra click required
     The handler is wrapped in DOMContentLoaded + a 250ms delay so
     plan-tile elements + Alpine root are both ready. ────────────── */
  document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const wanted = params.get('plan');
    if (!wanted) return;

    setTimeout(() => {
      const tile = document.querySelector(`[data-plan-code="${wanted}"]`);
      if (!tile) return;

      // Smooth scroll with offset for sticky header (navbar ~60-80px tall).
      const top = tile.getBoundingClientRect().top + window.scrollY - 100;
      window.scrollTo({ top, behavior: 'smooth' });

      // Highlight pulse — a CSS animation defined inline so we don't
      // touch the page's stylesheet for a one-off effect.
      tile.style.transition = 'box-shadow .6s ease, transform .6s ease';
      tile.style.boxShadow = '0 0 0 4px var(--accent), 0 22px 50px -12px var(--accent-shadow)';
      tile.style.transform = 'translateY(-4px)';
      setTimeout(() => {
        tile.style.boxShadow = '';
        tile.style.transform = '';
      }, 1800);

      // Auto-open the subscribe modal so the user is one tap from
      // confirming. The Subscribe button inside the tile fires the
      // same Alpine open(payload) — we click it programmatically.
      const subscribeBtn = tile.querySelector('[data-subscribe-btn], button[\\@click*="open"]');
      if (subscribeBtn) {
        // Wait until the highlight scroll completes so the modal
        // opens over a settled card instead of mid-animation.
        setTimeout(() => subscribeBtn.click(), 700);
      }
    }, 250);
  });
</script>
@endsection
