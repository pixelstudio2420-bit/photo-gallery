@extends('layouts.photographer')

@section('title', 'เลือกแผนสมัครสมาชิก')

@php
  // All known features and their display labels. The list is filtered
  // below by the global feature-flag layer so deprecated features
  // (color_enhance / smart_captions / video_thumbnails / api_access)
  // don't show up as "missing" rows on every plan card. Single source
  // of truth: SubscriptionService::featureGloballyEnabled() — flipping
  // a flag back ON in admin restores its row here automatically.
  $featureLabelsAll = [
    'face_search'         => ['ค้นหาด้วยใบหน้า (AI)',     'bi-person-bounding-box'],
    'quality_filter'      => ['คัดรูปเสียอัตโนมัติ',       'bi-funnel'],
    'duplicate_detection' => ['ตรวจจับรูปซ้ำ',            'bi-files'],
    'auto_tagging'        => ['แท็กอัตโนมัติ',            'bi-tags'],
    'best_shot'           => ['เลือกช็อตเด็ด',            'bi-trophy'],
    'priority_upload'     => ['อัปโหลดด่วน 2x',           'bi-lightning-charge'],
    'color_enhance'       => ['ปรับสีอัตโนมัติ',          'bi-palette2'],
    'customer_analytics'  => ['Analytics ลูกค้า',         'bi-graph-up'],
    'smart_captions'      => ['Smart Captions',         'bi-chat-quote'],
    'custom_branding'     => ['Custom Branding',        'bi-palette'],
    'video_thumbnails'    => ['Video Thumbnails',       'bi-play-btn'],
    'api_access'          => ['API Access',             'bi-key'],
    'white_label'         => ['White-label',            'bi-incognito'],
    'presets'             => ['Lightroom Presets',       'bi-sliders'],
  ];
  $subs = app(\App\Services\SubscriptionService::class);
  $featureLabels = collect($featureLabelsAll)
    ->filter(fn($_, $code) => $subs->featureGloballyEnabled($code))
    ->all();

  // The "popular" plan we want to lift visually. Pro is our default sweet spot.
  $popularCode = 'pro';

  // Pre-compute global aggregates for the metrics strip
  $totalAiCredits = $plans->sum('monthly_ai_credits');
  $maxStorageGb   = $plans->max('storage_gb');
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

/* ─── Plan card ─── */
.plan-tile{
  background:#fff;
  border-radius:24px;
  border:1px solid rgba(99,102,241,.1);
  box-shadow:0 8px 24px -8px rgba(99,102,241,.10), 0 1px 3px rgba(0,0,0,.04);
  overflow:hidden;
  position:relative;
  transition:transform .3s cubic-bezier(0.34,1.56,0.64,1), box-shadow .3s, border-color .25s;
  display:flex;flex-direction:column;
}
html.dark .plan-tile{
  background:rgba(15,23,42,.85);
  backdrop-filter:blur(16px);
  border-color:rgba(255,255,255,.08);
  box-shadow:0 8px 32px -8px rgba(0,0,0,.5), inset 0 1px 0 rgba(255,255,255,.04);
}
.plan-tile:hover{
  transform:translateY(-6px) scale(1.005);
  box-shadow:0 28px 50px -16px rgba(99,102,241,.22), 0 6px 12px rgba(0,0,0,.05);
  border-color:rgba(124,58,237,.25);
}
html.dark .plan-tile:hover{
  box-shadow:0 28px 60px -16px rgba(124,58,237,.45), inset 0 1px 0 rgba(255,255,255,.06);
}

/* Accent corner gleam (uses plan color via --accent) */
.plan-tile::before{
  content:'';position:absolute;top:0;right:0;width:140px;height:140px;
  background:radial-gradient(circle at top right, var(--accent, #7c3aed) 0%, transparent 65%);
  opacity:.08;pointer-events:none;transition:opacity .3s;
}
.plan-tile:hover::before{opacity:.18;}
html.dark .plan-tile::before{opacity:.12;}
html.dark .plan-tile:hover::before{opacity:.25;}

/* Popular plan — extra emphasis
   We avoid `scale(>1)` because it bleeds the card past its grid cell
   and was the main cause of horizontal overflow on small viewports.
   Instead we lift the card up and rely on the gradient border + box
   shadow + halo pulse to make it stand out — no width change. */
.plan-tile.popular{
  border:2px solid transparent;
  background:linear-gradient(#fff,#fff) padding-box,
             linear-gradient(135deg,#4f46e5,#7c3aed,#ec4899) border-box;
  box-shadow:0 22px 52px -16px rgba(124,58,237,.32), 0 4px 12px rgba(0,0,0,.05);
  transform:translateY(-6px);
}
.plan-tile.popular:hover{
  transform:translateY(-10px);
}
html.dark .plan-tile.popular{
  background:linear-gradient(rgba(15,23,42,.95),rgba(15,23,42,.95)) padding-box,
             linear-gradient(135deg,#6366f1,#a855f7,#f472b6) border-box;
}
.plan-tile.popular::after{
  content:'';position:absolute;inset:0;border-radius:24px;pointer-events:none;
  box-shadow:0 0 0 6px rgba(124,58,237,.06);
  animation:popular-pulse 2.4s ease-in-out infinite;
}
@keyframes popular-pulse{
  0%,100%{box-shadow:0 0 0 6px rgba(124,58,237,.05);}
  50%{box-shadow:0 0 0 14px rgba(124,58,237,.08);}
}

.plan-tile.current{
  border:2px solid #10b981;
  background:linear-gradient(#fff,#fff) padding-box,
             linear-gradient(135deg,#10b981,#34d399) border-box;
  box-shadow:0 16px 40px -12px rgba(16,185,129,.3);
}
html.dark .plan-tile.current{
  background:linear-gradient(rgba(15,23,42,.95),rgba(15,23,42,.95)) padding-box,
             linear-gradient(135deg,#10b981,#34d399) border-box;
}

/* Top ribbon */
.plan-ribbon{
  position:absolute;top:0;left:0;right:0;
  padding:7px 12px;
  font-size:.66rem;font-weight:800;letter-spacing:.18em;text-transform:uppercase;
  color:#fff;text-align:center;
  background:linear-gradient(135deg,#4f46e5,#7c3aed,#ec4899);
  display:flex;align-items:center;justify-content:center;gap:.4rem;
  box-shadow:0 4px 12px -2px rgba(124,58,237,.35);
}
.plan-ribbon.current{ background:linear-gradient(135deg,#10b981,#059669); box-shadow:0 4px 12px -2px rgba(16,185,129,.4); }
.plan-ribbon i{animation:wiggle 3s ease-in-out infinite;}
@keyframes wiggle{0%,100%{transform:rotate(0);}25%{transform:rotate(-10deg);}75%{transform:rotate(10deg);}}

/* Plan header */
.plan-header{padding:1.85rem 1.5rem 1.35rem;text-align:center;border-bottom:1px solid rgba(99,102,241,.08);position:relative;}
html.dark .plan-header{ border-bottom-color:rgba(255,255,255,.06); }
.plan-tile.popular .plan-header,
.plan-tile.current .plan-header{ padding-top:2.5rem; }

.plan-icon{
  width:60px;height:60px;border-radius:18px;
  display:inline-flex;align-items:center;justify-content:center;
  background:linear-gradient(135deg,var(--accent-soft, rgba(99,102,241,.1)),rgba(236,72,153,.06));
  color:var(--accent, #4f46e5);font-size:1.65rem;
  border:1px solid var(--accent-border, rgba(99,102,241,.18));
  margin:0 auto;position:relative;
  box-shadow:0 6px 16px -6px var(--accent-shadow, rgba(99,102,241,.25));
  transition:transform .35s cubic-bezier(0.34,1.56,0.64,1);
}
.plan-tile:hover .plan-icon{transform:rotate(-6deg) scale(1.06);}
html.dark .plan-icon{
  background:linear-gradient(135deg,var(--accent-soft, rgba(99,102,241,.2)),rgba(236,72,153,.12));
  border-color:rgba(255,255,255,.08);
}
.plan-name{
  font-weight:800;font-size:1.2rem;color:#0f172a;
  margin-top:.95rem;letter-spacing:-0.015em;
}
html.dark .plan-name{ color:#f1f5f9; }
.plan-tagline{font-size:.78rem;color:#64748b;margin-top:.25rem;line-height:1.4;min-height:2.2em;}
html.dark .plan-tagline{ color:#94a3b8; }

.plan-price{margin-top:1.1rem;}
.plan-price-value{
  font-weight:800;font-size:2.4rem;color:#0f172a;letter-spacing:-0.025em;
  background:linear-gradient(135deg,#0f172a,#475569);
  -webkit-background-clip:text;background-clip:text;color:transparent;
  display:inline-flex;align-items:baseline;gap:.1rem;
}
html.dark .plan-price-value{
  background:linear-gradient(135deg,#f1f5f9,#cbd5e1);
  -webkit-background-clip:text;background-clip:text;color:transparent;
}
.plan-price-suffix{font-size:.85rem;color:#64748b;font-weight:500;margin-left:.25rem;}
html.dark .plan-price-suffix{ color:#94a3b8; }
.plan-price-annual{
  margin-top:.4rem;font-size:.72rem;color:#059669;font-weight:700;
  display:inline-flex;align-items:center;gap:.3rem;
  padding:.25rem .6rem;border-radius:999px;background:rgba(16,185,129,.08);
  border:1px solid rgba(16,185,129,.18);
}
html.dark .plan-price-annual{ background:rgba(16,185,129,.15);color:#34d399;border-color:rgba(16,185,129,.3); }
.plan-price-strike{
  font-size:.78rem;color:#94a3b8;text-decoration:line-through;font-weight:500;margin-right:.3rem;
}

/* Body */
.plan-body{padding:1.35rem 1.5rem 1.5rem;flex:1;display:flex;flex-direction:column;}
.plan-stats{
  display:grid;grid-template-columns:repeat(2,1fr);gap:.55rem;margin-bottom:1.25rem;
}
.plan-stat-item{
  padding:.7rem .55rem;border-radius:12px;
  background:linear-gradient(135deg,rgba(99,102,241,.04),rgba(236,72,153,.025));
  border:1px solid rgba(99,102,241,.1);
  text-align:center;transition:transform .2s,border-color .2s;
}
html.dark .plan-stat-item{ background:linear-gradient(135deg,rgba(99,102,241,.08),rgba(236,72,153,.05)); border-color:rgba(255,255,255,.06); }
.plan-tile:hover .plan-stat-item{border-color:rgba(124,58,237,.25);}
.plan-stat-icon{font-size:.95rem;color:var(--accent,#4f46e5);margin-bottom:.15rem;}
html.dark .plan-stat-icon{color:#a5b4fc;}
.plan-stat-label{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:.1rem;}
html.dark .plan-stat-label{ color:#94a3b8; }
.plan-stat-val{font-weight:800;font-size:.98rem;color:#0f172a;letter-spacing:-0.01em;}
html.dark .plan-stat-val{ color:#f1f5f9; }
.plan-stat-val small{font-weight:500;color:#64748b;font-size:.7rem;margin-left:.15rem;}
html.dark .plan-stat-val small{ color:#94a3b8; }

.plan-features-label{
  font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.16em;
  color:#64748b;margin-bottom:.7rem;display:flex;align-items:center;gap:.4rem;
}
html.dark .plan-features-label{ color:#94a3b8; }
.plan-features{display:flex;flex-direction:column;gap:.45rem;flex:1;margin-bottom:1.25rem;}
.plan-feature-row{
  display:flex;align-items:flex-start;gap:.55rem;
  font-size:.8rem;color:#334155;line-height:1.4;
}
html.dark .plan-feature-row{ color:#cbd5e1; }
.plan-feature-row.locked{ opacity:.35; }
.plan-feature-icon{
  width:18px;height:18px;border-radius:50%;
  display:inline-flex;align-items:center;justify-content:center;
  flex-shrink:0;margin-top:.1rem;font-size:.65rem;
}
.plan-feature-icon.on{background:rgba(16,185,129,.14);color:#059669;}
.plan-feature-icon.off{background:rgba(148,163,184,.15);color:#94a3b8;}
html.dark .plan-feature-icon.on{ background:rgba(16,185,129,.22); color:#34d399; }
html.dark .plan-feature-icon.off{ background:rgba(148,163,184,.15); color:#64748b; }

/* CTA buttons */
.plan-cta{
  width:100%;padding:.95rem;border-radius:14px;
  font-weight:800;font-size:.92rem;
  display:inline-flex;align-items:center;justify-content:center;gap:.45rem;
  transition:transform .15s,box-shadow .25s,filter .2s;cursor:pointer;border:none;
  letter-spacing:-0.01em;
}
.plan-cta-primary{
  background:linear-gradient(135deg,#4f46e5,#7c3aed,#ec4899);
  background-size:200% 200%;
  color:#fff;
  box-shadow:0 10px 24px -6px rgba(124,58,237,.5);
  animation:cta-shine 4s ease-in-out infinite;
}
@keyframes cta-shine{0%,100%{background-position:0% 50%;}50%{background-position:100% 50%;}}
.plan-cta-primary:hover{ transform:translateY(-2px);box-shadow:0 16px 32px -6px rgba(124,58,237,.65);filter:brightness(1.06); }
.plan-cta-secondary{
  background:#f1f5f9;color:#334155;border:1px solid rgba(99,102,241,.1);
}
html.dark .plan-cta-secondary{ background:rgba(255,255,255,.06); color:#cbd5e1; border:1px solid rgba(255,255,255,.08); }
.plan-cta-secondary:hover{ background:#e2e8f0; transform:translateY(-1px); }
html.dark .plan-cta-secondary:hover{ background:rgba(255,255,255,.1); }
.plan-cta-current{
  background:rgba(16,185,129,.1);color:#059669;cursor:not-allowed;
  border:1px dashed rgba(16,185,129,.3);
}
html.dark .plan-cta-current{ background:rgba(16,185,129,.15); color:#34d399; border-color:rgba(16,185,129,.4); }
.plan-cta-free{
  background:#0f172a;color:#fff;
}
.plan-cta-free:hover{ background:#1e293b;transform:translateY(-1px); }
html.dark .plan-cta-free{ background:rgba(255,255,255,.1); color:#fff; border:1px solid rgba(255,255,255,.15); }
html.dark .plan-cta-free:hover{ background:rgba(255,255,255,.15); }

/* Per-GB indicator */
.plan-value-tip{
  display:inline-flex;align-items:center;gap:.3rem;margin-top:.45rem;
  font-size:.68rem;color:#7c3aed;font-weight:700;
}
html.dark .plan-value-tip{color:#a5b4fc;}

/* ─── Money-back banner ─── */
.money-back{
  margin:2.5rem auto 0;max-width:54rem;padding:1.1rem 1.5rem;border-radius:18px;
  background:linear-gradient(135deg,rgba(16,185,129,.08),rgba(52,211,153,.04));
  border:1px solid rgba(16,185,129,.2);
  display:flex;align-items:center;gap:1rem;flex-wrap:wrap;
}
html.dark .money-back{background:linear-gradient(135deg,rgba(16,185,129,.18),rgba(52,211,153,.1));border-color:rgba(16,185,129,.3);}
.money-back-icon{
  width:48px;height:48px;border-radius:14px;flex-shrink:0;
  background:linear-gradient(135deg,#10b981,#059669);color:#fff;
  display:inline-flex;align-items:center;justify-content:center;font-size:1.4rem;
  box-shadow:0 8px 20px -4px rgba(16,185,129,.4);
}
.money-back-text{flex:1;min-width:200px;}
.money-back-title{font-weight:800;color:#065f46;font-size:.95rem;letter-spacing:-0.01em;}
html.dark .money-back-title{color:#34d399;}
.money-back-sub{font-size:.78rem;color:#475569;margin-top:.15rem;}
html.dark .money-back-sub{color:#94a3b8;}

/* ─── Testimonials ─── */
.tm-card{
  background:rgba(255,255,255,.7);backdrop-filter:blur(14px);
  border:1px solid rgba(99,102,241,.12);border-radius:18px;
  padding:1.25rem;display:flex;flex-direction:column;gap:.75rem;
  transition:transform .25s,box-shadow .25s;
}
html.dark .tm-card{background:rgba(15,23,42,.65);border-color:rgba(255,255,255,.06);}
.tm-card:hover{transform:translateY(-3px);box-shadow:0 12px 28px -8px rgba(99,102,241,.18);}
.tm-stars{color:#f59e0b;font-size:.85rem;letter-spacing:.05em;}
.tm-quote{font-size:.86rem;color:#334155;line-height:1.55;font-style:italic;}
html.dark .tm-quote{color:#cbd5e1;}
.tm-quote::before{content:'\201C';font-size:1.4rem;color:#7c3aed;font-weight:800;margin-right:.15rem;line-height:0;}
.tm-author{display:flex;align-items:center;gap:.6rem;margin-top:auto;padding-top:.5rem;border-top:1px solid rgba(99,102,241,.08);}
html.dark .tm-author{border-top-color:rgba(255,255,255,.06);}
.tm-avatar{
  width:38px;height:38px;border-radius:50%;flex-shrink:0;
  background:linear-gradient(135deg,#4f46e5,#7c3aed,#ec4899);
  color:#fff;display:inline-flex;align-items:center;justify-content:center;
  font-weight:800;font-size:.95rem;letter-spacing:-0.01em;
  box-shadow:0 4px 10px -2px rgba(124,58,237,.35);
}
.tm-name{font-weight:800;color:#0f172a;font-size:.85rem;letter-spacing:-0.01em;}
html.dark .tm-name{color:#f1f5f9;}
.tm-role{font-size:.7rem;color:#64748b;}
html.dark .tm-role{color:#94a3b8;}

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
      <i class="bi bi-stars"></i> แผนสมัครสมาชิก
    </div>

    <h1 class="title-grad text-3xl sm:text-4xl md:text-5xl mb-3 mx-0 leading-[1.1]">
      เลือกแผนที่<br class="sm:hidden">เหมาะกับคุณ
    </h1>
    <p class="text-sm sm:text-base text-gray-600 dark:text-gray-400 max-w-2xl mx-auto leading-relaxed">
      จ่ายเฉพาะที่ใช้ — เริ่มฟรี อัปเกรดเมื่อพร้อม ยกเลิกได้ทุกเมื่อ
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

    {{-- Trust strip --}}
    <div class="trust-row">
      <span class="trust-pill"><i class="bi bi-shield-check text-emerald-500"></i> ยกเลิกได้ทุกเมื่อ</span>
      <span class="trust-pill"><i class="bi bi-credit-card text-indigo-500"></i> PromptPay + บัตรเครดิต</span>
      <span class="trust-pill"><i class="bi bi-lightning-charge text-pink-500"></i> เปลี่ยนแผนได้ทันที</span>
      <span class="trust-pill"><i class="bi bi-cloud-check text-sky-500"></i> ข้อมูลไม่หายเวลาดาวน์เกรด</span>
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
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5 gap-5 max-w-8xl mx-auto">
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
        @if($isCurrent)
          <div class="plan-ribbon current"><i class="bi bi-check-circle-fill"></i> แผนปัจจุบัน</div>
        @elseif($isPopular)
          <div class="plan-ribbon"><i class="bi bi-stars"></i> ขายดีที่สุด</div>
        @endif

        {{-- Header --}}
        <div class="plan-header">
          <div class="plan-icon"><i class="bi {{ $icon }}"></i></div>
          <h3 class="plan-name">{{ $p->name }}</h3>
          @if($p->tagline)
            <p class="plan-tagline">{{ $p->tagline }}</p>
          @endif

          <div class="plan-price">
            @if($p->isFree())
              <span class="plan-price-value">ฟรี</span>
              <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-1">ตลอดไป · ไม่ต้องใช้บัตรเครดิต</p>
            @else
              {{-- Live-toggled price between monthly and annual-equiv-per-month --}}
              <span class="plan-price-value">
                <span class="text-base text-slate-500 dark:text-slate-400 font-semibold">฿</span>
                <span x-text="annual
                  ? numberFmt(Math.round(({{ (float) $p->price_annual_thb }} || ({{ (float) $p->price_thb }}*12)) / 12))
                  : numberFmt({{ (float) $p->price_thb }})"></span>
              </span>
              <span class="plan-price-suffix">/เดือน</span>

              <div class="mt-2 flex flex-col items-center gap-1.5">
                {{-- Annual selected: show "billed annually ฿XX (save ฿YY)" --}}
                <template x-if="annual && {{ $p->price_annual_thb ? 1 : 0 }}">
                  <span class="plan-price-annual">
                    <i class="bi bi-tag-fill"></i>
                    เก็บปีละ ฿<span x-text="numberFmt({{ (float) ($p->price_annual_thb ?? 0) }})"></span>
                    @if($p->annualSavings() > 0)
                      <span class="text-emerald-700 dark:text-emerald-300 font-extrabold">· ประหยัด ฿{{ number_format($p->annualSavings(), 0) }}</span>
                    @endif
                  </span>
                </template>
                {{-- Monthly selected: show "save ฿XX with annual" hint --}}
                <template x-if="!annual && {{ $p->annualSavings() > 0 ? 1 : 0 }}">
                  <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold inline-flex items-center gap-1">
                    <i class="bi bi-arrow-up-right"></i>
                    เลือกรายปีประหยัด ฿{{ number_format($p->annualSavings(), 0) }}
                  </span>
                </template>

                @if($valuePerGb > 0 && $valuePerGb < 100)
                  <span class="plan-value-tip">
                    <i class="bi bi-graph-down-arrow"></i>
                    ฿{{ number_format($valuePerGb, $valuePerGb < 1 ? 2 : 1) }} ต่อ GB
                  </span>
                @endif
              </div>
            @endif
          </div>
        </div>

        {{-- Body --}}
        <div class="plan-body">
          {{-- Stats grid --}}
          <div class="plan-stats">
            <div class="plan-stat-item">
              <div class="plan-stat-icon"><i class="bi bi-hdd-stack"></i></div>
              <div class="plan-stat-label">พื้นที่</div>
              <div class="plan-stat-val">{{ number_format($p->storage_gb, 0) }}<small>GB</small></div>
            </div>
            <div class="plan-stat-item">
              <div class="plan-stat-icon"><i class="bi bi-percent"></i></div>
              <div class="plan-stat-label">ค่าคอม</div>
              <div class="plan-stat-val">{{ rtrim(rtrim(number_format((float) $p->commission_pct, 1), '0'), '.') }}<small>%</small></div>
            </div>
            <div class="plan-stat-item">
              <div class="plan-stat-icon"><i class="bi bi-calendar-event"></i></div>
              <div class="plan-stat-label">อีเวนต์</div>
              <div class="plan-stat-val">
                @if(is_null($p->max_concurrent_events))
                  <span style="color:{{ $accent }}">∞</span>
                @else
                  {{ (int) $p->max_concurrent_events }}
                @endif
              </div>
            </div>
            <div class="plan-stat-item">
              <div class="plan-stat-icon"><i class="bi bi-cpu"></i></div>
              <div class="plan-stat-label">AI/เดือน</div>
              <div class="plan-stat-val">
                @php
                  $credits = (int) $p->monthly_ai_credits;
                  $disp = $credits >= 1000000 ? '1M' : ($credits >= 1000 ? round($credits/1000).'K' : $credits);
                @endphp
                {{ $disp }}
              </div>
            </div>
          </div>

          {{-- Feature comparison --}}
          <div>
            <p class="plan-features-label">
              <i class="bi bi-magic" style="color:{{ $accent }}"></i>
              <span>ฟีเจอร์ <span class="text-indigo-600 dark:text-indigo-400 font-extrabold">{{ $featureCount }}</span> รายการ</span>
            </p>
            <div class="plan-features">
              @foreach($featureLabels as $code => [$label, $iconClass])
                @php $enabled = in_array($code, $p->ai_features ?? [], true); @endphp
                <div class="plan-feature-row {{ $enabled ? '' : 'locked' }}">
                  <span class="plan-feature-icon {{ $enabled ? 'on' : 'off' }}">
                    <i class="bi {{ $enabled ? 'bi-check-lg' : 'bi-dash' }}"></i>
                  </span>
                  <span>{{ $label }}</span>
                </div>
              @endforeach
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

              <form method="POST" :action="plan.action_url" class="flex items-stretch gap-2">
                @csrf
                <input type="hidden" name="annual" :value="annual ? '1' : '0'">
                <button type="button" @click="close()" :disabled="submitting"
                        class="px-4 py-3 rounded-xl text-sm font-semibold text-slate-700 dark:text-slate-200 bg-slate-100 dark:bg-white/5 hover:bg-slate-200 dark:hover:bg-white/10 transition disabled:opacity-50">
                  ยกเลิก
                </button>
                <button type="submit" :disabled="submitting" @click="submitting = true"
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

  {{-- ─── Money-back guarantee ─── --}}
  <div class="money-back plan-anim d5">
    <div class="money-back-icon"><i class="bi bi-shield-fill-check"></i></div>
    <div class="money-back-text">
      <p class="money-back-title">ทดลองใช้แบบ Risk-free 7 วัน</p>
      <p class="money-back-sub">ภายใน 7 วันแรก หากไม่พอใจ ติดต่อทีมเราเพื่อขอเงินคืนเต็มจำนวน · ไม่มีคำถามใดๆ</p>
    </div>
    <a href="mailto:support@photogallery.com"
       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-sm transition">
      <i class="bi bi-headset"></i> ติดต่อทีม
    </a>
  </div>

  {{-- ─── Testimonials ─── --}}
  <div class="max-w-6xl mx-auto mt-12 plan-anim d6">
    <div class="text-center mb-6">
      <div class="section-eyebrow"><i class="bi bi-quote"></i> ผู้ใช้งานจริง</div>
      <h2 class="title-grad text-2xl sm:text-3xl mt-3 leading-tight">ช่างภาพมืออาชีพไว้ใจเรา</h2>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <div class="tm-card">
        <div class="tm-stars">
          <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
        </div>
        <p class="tm-quote">งานวิ่งมาราธอน 5,000 รูป AI ค้นหาด้วยใบหน้าเจอลูกค้าใน 3 วินาที — ขายได้เกือบทุกใบ ก่อนหน้านี้ใช้ Drive ลูกค้าหาเองหลายชั่วโมง</p>
        <div class="tm-author">
          <div class="tm-avatar">ช</div>
          <div>
            <div class="tm-name">ช่างเอ ฟิตเนส</div>
            <div class="tm-role">Sport Photographer · Pro plan</div>
          </div>
        </div>
      </div>
      <div class="tm-card">
        <div class="tm-stars">
          <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
        </div>
        <p class="tm-quote">Custom branding ของ Business plan ทำให้ลูกค้าจำเราได้ ไม่ใช่จำแพลตฟอร์ม — ส่งงานออกมาดูมืออาชีพ ราคาเริ่มขายได้สูงขึ้น 30%</p>
        <div class="tm-author">
          <div class="tm-avatar">ม</div>
          <div>
            <div class="tm-name">มะนาว สตูดิโอ</div>
            <div class="tm-role">Wedding Studio · Business plan</div>
          </div>
        </div>
      </div>
      <div class="tm-card">
        <div class="tm-stars">
          <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
        </div>
        <p class="tm-quote">เริ่มจาก Free plan ลองระบบ 1 เดือน แล้วอัปเป็น Starter — รายได้เพิ่มขึ้น 3 เท่าจากการขายภาพออนไลน์ คุ้มมากกับ ฿299</p>
        <div class="tm-author">
          <div class="tm-avatar">ป</div>
          <div>
            <div class="tm-name">ป๊อป Freelance</div>
            <div class="tm-role">Freelance Photographer · Starter plan</div>
          </div>
        </div>
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
