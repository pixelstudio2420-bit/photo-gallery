{{-- Pick layout based on install state (same as main deployment page) --}}
@extends($installActive && !auth('admin')->check() ? 'layouts.install' : 'layouts.admin')

@section('title', 'คู่มือ Deployment')

@push('styles')
<style>
  /* ── Print-friendly + screen layout ────────────────────────────── */
  @media print {
    .no-print { display: none !important; }
    .dg-card { break-inside: avoid; page-break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd !important; }
    .dg-toc { display: none !important; }
    body { background: white !important; }
  }

  /* ── Layout grid: TOC sidebar + content ────────────────────────── */
  .dg-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
  }
  @media (min-width: 1024px) {
    .dg-grid {
      grid-template-columns: 250px 1fr;
      gap: 2.5rem;
    }
  }

  /* ── Sticky TOC ─────────────────────────────────────────────────── */
  .dg-toc {
    position: sticky; top: 1rem;
    max-height: calc(100vh - 2rem);
    overflow-y: auto;
    padding-right: 0.5rem;
  }
  .dg-toc-link {
    display: block;
    padding: 0.45rem 0.75rem;
    border-radius: 8px;
    font-size: 0.78rem;
    color: rgb(71 85 105);
    text-decoration: none;
    border-left: 2px solid transparent;
    transition: all .15s ease;
  }
  .dark .dg-toc-link { color: rgb(148 163 184); }
  .dg-toc-link:hover {
    background: rgb(241 245 249);
    color: rgb(99 102 241);
    border-left-color: rgb(165 180 252);
  }
  .dark .dg-toc-link:hover {
    background: rgba(255,255,255,0.05);
    color: rgb(165 180 252);
    border-left-color: rgb(99 102 241);
  }
  .dg-toc-link.is-active {
    background: rgb(238 242 255);
    color: rgb(79 70 229);
    border-left-color: rgb(99 102 241);
    font-weight: 600;
  }
  .dark .dg-toc-link.is-active {
    background: rgba(99,102,241,0.10);
    color: rgb(165 180 252);
  }
  .dg-toc-num {
    display: inline-block;
    width: 22px;
    font-size: 0.7rem;
    font-weight: 700;
    color: rgb(99 102 241);
  }

  /* ── Section card ──────────────────────────────────────────────── */
  .dg-card {
    background: white;
    border: 1px solid rgb(226 232 240);
    border-radius: 18px;
    padding: 1.5rem 1.5rem;
    margin-bottom: 1.25rem;
    box-shadow: 0 1px 3px rgba(15,23,42,0.04);
    scroll-margin-top: 1rem;
  }
  .dark .dg-card {
    background: rgb(15 23 42);
    border-color: rgba(255,255,255,0.08);
    box-shadow: 0 1px 3px rgba(0,0,0,0.3);
  }
  @media (min-width: 768px) {
    .dg-card { padding: 2rem 2.25rem; }
  }

  .dg-section-num {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px;
    border-radius: 10px;
    font-weight: 800; font-size: 0.85rem;
    color: white;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    flex-shrink: 0;
  }

  /* ── Code block with copy button ───────────────────────────────── */
  .dg-code {
    position: relative;
    background: rgb(15 23 42);
    color: rgb(226 232 240);
    border-radius: 10px;
    padding: 1rem 1.25rem;
    padding-right: 4rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.78rem;
    line-height: 1.55;
    overflow-x: auto;
    margin: 0.75rem 0;
  }
  .dg-code .prompt { color: rgb(110 231 183); user-select: none; }
  .dg-code .comment { color: rgb(148 163 184); font-style: italic; }
  .dg-code .key { color: rgb(196 181 253); }
  .dg-code .val { color: rgb(254 215 170); }
  .dg-code-copy {
    position: absolute;
    top: 0.6rem;
    right: 0.6rem;
    width: 30px; height: 30px;
    border-radius: 6px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.12);
    color: rgb(203 213 225);
    font-size: 0.8rem;
    display: inline-flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: all .15s ease;
  }
  .dg-code-copy:hover { background: rgba(255,255,255,0.15); color: white; }
  .dg-code-copy.is-copied { background: rgb(16 185 129); border-color: rgb(16 185 129); color: white; }

  /* ── Callout boxes (tip, warn, info, danger) ───────────────────── */
  .dg-callout {
    border-radius: 12px;
    padding: 1rem 1.25rem;
    margin: 0.75rem 0;
    border-left: 4px solid;
    display: flex;
    gap: 0.75rem;
    align-items: flex-start;
    font-size: 0.85rem;
    line-height: 1.6;
  }
  .dg-callout.tip    { background: rgb(209 250 229); border-color: rgb(16 185 129); color: rgb(5 95 70); }
  .dg-callout.info   { background: rgb(219 234 254); border-color: rgb(59 130 246); color: rgb(30 58 138); }
  .dg-callout.warn   { background: rgb(254 243 199); border-color: rgb(245 158 11); color: rgb(120 53 15); }
  .dg-callout.danger { background: rgb(254 226 226); border-color: rgb(239 68 68); color: rgb(155 28 28); }
  .dark .dg-callout.tip    { background: rgba(16,185,129,0.10); color: rgb(167 243 208); }
  .dark .dg-callout.info   { background: rgba(59,130,246,0.10); color: rgb(191 219 254); }
  .dark .dg-callout.warn   { background: rgba(245,158,11,0.10); color: rgb(254 215 170); }
  .dark .dg-callout.danger { background: rgba(239,68,68,0.10); color: rgb(252 165 165); }
  .dg-callout-icon {
    flex-shrink: 0;
    font-size: 1.1rem;
    margin-top: 0.1rem;
  }

  /* ── Table styling ─────────────────────────────────────────────── */
  .dg-table {
    width: 100%;
    border-collapse: collapse;
    margin: 0.75rem 0;
    font-size: 0.82rem;
  }
  .dg-table th, .dg-table td {
    padding: 0.65rem 0.85rem;
    text-align: left;
    border-bottom: 1px solid rgb(226 232 240);
  }
  .dark .dg-table th, .dark .dg-table td {
    border-bottom-color: rgba(255,255,255,0.08);
  }
  .dg-table th {
    background: rgb(248 250 252);
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.05em;
    color: rgb(71 85 105);
  }
  .dark .dg-table th { background: rgba(255,255,255,0.04); color: rgb(203 213 225); }

  /* ── Inline code ───────────────────────────────────────────────── */
  .dg-card code:not(.dg-code) {
    background: rgb(241 245 249);
    color: rgb(99 102 241);
    padding: 0.15em 0.4em;
    border-radius: 4px;
    font-size: 0.82em;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  }
  .dark .dg-card code:not(.dg-code) {
    background: rgba(99,102,241,0.15);
    color: rgb(196 181 253);
  }

  /* ── Step list ─────────────────────────────────────────────────── */
  .dg-steps {
    counter-reset: step;
    margin: 0.5rem 0;
  }
  .dg-steps li {
    counter-increment: step;
    position: relative;
    padding-left: 2.25rem;
    margin-bottom: 0.6rem;
    font-size: 0.88rem;
    line-height: 1.6;
  }
  .dg-steps li::before {
    content: counter(step);
    position: absolute;
    left: 0; top: 0;
    width: 24px; height: 24px;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: white;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; font-weight: 800;
  }

  /* ── Hero ──────────────────────────────────────────────────────── */
  .dg-hero {
    background: linear-gradient(135deg, #0ea5e9 0%, #6366f1 50%, #8b5cf6 100%);
    color: white;
    border-radius: 24px;
    padding: 2.5rem 1.75rem;
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
  }
  @media (min-width: 768px) {
    .dg-hero { padding: 3rem 2.5rem; }
  }
  .dg-hero::before {
    content: ''; position: absolute; inset: 0; pointer-events: none;
    background:
      radial-gradient(circle at 15% 100%, rgba(255,255,255,0.18), transparent 45%),
      radial-gradient(circle at 90% 0%,  rgba(255,255,255,0.14), transparent 50%);
  }

  /* ── Print button styling ──────────────────────────────────────── */
  .dg-action-btn {
    display: inline-flex; align-items: center; gap: 0.4rem;
    padding: 0.5rem 0.85rem;
    border-radius: 8px;
    font-size: 0.78rem; font-weight: 600;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.25);
    color: white;
    backdrop-filter: blur(8px);
    text-decoration: none;
    transition: all .15s ease;
  }
  .dg-action-btn:hover { background: rgba(255,255,255,0.25); }

  /* ── Section heading ───────────────────────────────────────────── */
  .dg-h2 {
    font-size: 1.35rem;
    font-weight: 800;
    color: rgb(15 23 42);
    margin: 0 0 0.5rem;
    line-height: 1.3;
  }
  .dark .dg-h2 { color: rgb(248 250 252); }
  .dg-h3 {
    font-size: 1rem;
    font-weight: 700;
    color: rgb(30 41 59);
    margin: 1.25rem 0 0.5rem;
  }
  .dark .dg-h3 { color: rgb(226 232 240); }
  .dg-lead {
    color: rgb(71 85 105);
    font-size: 0.9rem;
    line-height: 1.6;
    margin: 0 0 1rem;
  }
  .dark .dg-lead { color: rgb(148 163 184); }

  /* ── Body text ─────────────────────────────────────────────────── */
  .dg-card p { font-size: 0.88rem; line-height: 1.65; color: rgb(51 65 85); margin: 0.5rem 0; }
  .dark .dg-card p { color: rgb(203 213 225); }
  .dg-card ul:not(.dg-steps) {
    padding-left: 1.5rem;
    margin: 0.5rem 0;
    font-size: 0.88rem;
    line-height: 1.65;
    color: rgb(51 65 85);
  }
  .dark .dg-card ul:not(.dg-steps) { color: rgb(203 213 225); }
  .dg-card ul:not(.dg-steps) li { margin-bottom: 0.3rem; }
  .dg-card a { color: rgb(99 102 241); text-decoration: underline; text-decoration-thickness: 1px; text-underline-offset: 2px; }
  .dark .dg-card a { color: rgb(165 180 252); }
</style>
@endpush

@section('content')
<div class="max-w-7xl mx-auto pb-12">

  {{-- ════════════════════════════════════════════════════════════════════
       HERO
       ════════════════════════════════════════════════════════════════════ --}}
  <div class="dg-hero">
    <div class="relative z-10 flex items-start justify-between gap-4 flex-wrap">
      <div class="flex items-start gap-4 flex-1 min-w-0">
        <div class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-md border border-white/30 flex items-center justify-center text-2xl shrink-0 shadow-lg shadow-black/20">
          <i class="bi bi-book-half"></i>
        </div>
        <div class="min-w-0">
          <div class="text-[10px] uppercase tracking-[0.25em] text-white/75 font-bold mb-1 flex items-center gap-1.5">
            <span class="inline-block w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
            <span>Deployment Documentation</span>
          </div>
          <h1 class="font-bold text-2xl md:text-3xl tracking-tight leading-tight mb-1">
            คู่มือการ Deploy ขึ้น <span class="bg-gradient-to-r from-yellow-100 via-white to-emerald-100 bg-clip-text text-transparent">Production</span>
          </h1>
          <p class="text-sm text-white/85">
            ขั้นตอนครบทุก step ตั้งแต่เลือก VPS · ตั้งค่า DNS · ติดตั้ง PHP/MySQL · SSL · ใช้ Install Wizard
          </p>
        </div>
      </div>

      <div class="flex items-center gap-2 flex-wrap no-print">
        <a href="{{ url('/docs/deployment/web-installer.html') }}" target="_blank" class="dg-action-btn">
          <i class="bi bi-file-earmark-richtext"></i> Offline HTML
        </a>
        <button onclick="window.print()" class="dg-action-btn">
          <i class="bi bi-printer"></i> พิมพ์
        </button>
        <a href="{{ route('admin.deployment.index') }}" class="dg-action-btn">
          <i class="bi bi-arrow-left"></i> กลับหน้า Deployment
        </a>
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════════════════
       MAIN CONTENT WITH TOC SIDEBAR
       ════════════════════════════════════════════════════════════════════ --}}
  <div class="dg-grid"
       x-data="{
         active: 's1',
         observer: null,
         init() {
           const opts = { rootMargin: '-20% 0px -70% 0px' };
           this.observer = new IntersectionObserver((entries) => {
             entries.forEach(e => { if (e.isIntersecting) this.active = e.target.id; });
           }, opts);
           document.querySelectorAll('.dg-card[id]').forEach(el => this.observer.observe(el));
         },
         async copyCode(btn) {
           const code = btn.parentElement.querySelector('.code-content');
           const text = code.innerText;
           try {
             await navigator.clipboard.writeText(text);
             btn.classList.add('is-copied');
             btn.innerHTML = '<i class=&quot;bi bi-check-lg&quot;></i>';
             setTimeout(() => { btn.classList.remove('is-copied'); btn.innerHTML = '<i class=&quot;bi bi-clipboard&quot;></i>'; }, 1500);
           } catch (e) {
             const ta = document.createElement('textarea'); ta.value = text;
             document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
             btn.classList.add('is-copied');
             btn.innerHTML = '<i class=&quot;bi bi-check-lg&quot;></i>';
             setTimeout(() => { btn.classList.remove('is-copied'); btn.innerHTML = '<i class=&quot;bi bi-clipboard&quot;></i>'; }, 1500);
           }
         }
       }">

    {{-- ── TOC SIDEBAR ── --}}
    <aside class="dg-toc no-print" x-cloak>
      <div class="text-[11px] uppercase font-bold tracking-[0.15em] text-slate-500 dark:text-slate-400 mb-2 px-3">
        สารบัญ
      </div>
      @php
        $toc = [
          's1'  => ['1', 'ภาพรวม'],
          's2'  => ['2', 'Server Requirements'],
          's3'  => ['3', 'เลือก VPS / Hosting'],
          's4'  => ['4', 'ตั้งค่า DNS'],
          's5'  => ['5', 'ติดตั้ง LAMP Stack'],
          's6'  => ['6', 'อัปโหลดโค้ด'],
          's7'  => ['7', 'ติดตั้ง SSL (HTTPS)'],
          's8'  => ['8', 'รัน Install Wizard'],
          's9'  => ['9', 'ตั้งค่า External APIs'],
          's10' => ['10', 'Cron + Queue Worker'],
          's11' => ['11', 'Optimize Performance'],
          's12' => ['12', 'Backup Strategy'],
          's13' => ['13', 'Monitoring & Logs'],
          's14' => ['14', 'Troubleshooting'],
          's15' => ['15', 'Security Checklist'],
        ];
      @endphp
      @foreach($toc as $id => [$num, $label])
        <a href="#{{ $id }}" class="dg-toc-link" :class="active === '{{ $id }}' ? 'is-active' : ''">
          <span class="dg-toc-num">{{ $num }}.</span> {{ $label }}
        </a>
      @endforeach
    </aside>

    {{-- ── MAIN CONTENT ── --}}
    <main>

      {{-- 1. ภาพรวม ──────────────────────────────────────────── --}}
      <section id="s1" class="dg-card">
        <div class="flex items-center gap-3 mb-3">
          <span class="dg-section-num">1</span>
          <h2 class="dg-h2">ภาพรวม</h2>
        </div>
        <p class="dg-lead">
          คู่มือนี้พาคุณ deploy โปรเจกต์ Photo Gallery (Laravel 12 + Tailwind + LINE + AI) จาก source code ไปขึ้น Production
          server ที่เปิดใช้งานจริงได้ภายใน <strong>1-2 ชั่วโมง</strong> สำหรับ VPS ทั่วไป (DigitalOcean, Linode, Vultr, AWS Lightsail)
        </p>

        <h3 class="dg-h3">เส้นทาง Deployment ภาพรวม</h3>
        <ol class="dg-steps">
          <li>เลือก VPS + ติดตั้ง OS (Ubuntu 22.04 LTS แนะนำ)</li>
          <li>ชี้ DNS A record จาก domain ของคุณไปยัง VPS IP</li>
          <li>ติดตั้ง PHP 8.2, MySQL/MariaDB, nginx, Composer, Node.js</li>
          <li>อัปโหลด source code (git clone หรือ SFTP)</li>
          <li>ตั้งค่า nginx + Let's Encrypt SSL</li>
          <li>เปิด <code>/admin/deployment</code> ใน browser → Install Wizard นำทาง 4 ขั้นตอน</li>
          <li>กรอก credentials ของ external APIs (LINE, Stripe, Omise, ฯลฯ)</li>
          <li>ตั้ง cron + queue worker → เปิดเว็บใช้งานจริง 🎉</li>
        </ol>

        <div class="dg-callout tip">
          <i class="bi bi-lightbulb-fill dg-callout-icon"></i>
          <div>
            <strong>เคล็ดลับ:</strong> ถ้าไม่อยาก SSH เอง สามารถใช้ <a href="https://forge.laravel.com" target="_blank">Laravel Forge</a>
            ($12/เดือน) หรือ <a href="https://ploi.io" target="_blank">Ploi</a> จัดการ server ให้แทน — แต่คู่มือนี้สอนแบบ manual SSH
          </div>
        </div>
      </section>

      {{-- 2. Server Requirements ─────────────────────────────── --}}
      <section id="s2" class="dg-card">
        <div class="flex items-center gap-3 mb-3">
          <span class="dg-section-num">2</span>
          <h2 class="dg-h2">Server Requirements</h2>
        </div>

        <h3 class="dg-h3">Hardware (ขั้นต่ำ)</h3>
        <table class="dg-table">
          <thead><tr><th>Resource</th><th>Minimum</th><th>แนะนำสำหรับโปรดักชัน</th></tr></thead>
          <tbody>
            <tr><td>CPU</td><td>1 core</td><td>2-4 cores</td></tr>
            <tr><td>RAM</td><td>1 GB</td><td>2-4 GB</td></tr>
            <tr><td>Disk</td><td>10 GB SSD</td><td>40+ GB SSD (เก็บรูป)</td></tr>
            <tr><td>Bandwidth</td><td>1 TB/เดือน</td><td>ไม่จำกัด (CDN R2/S3)</td></tr>
          </tbody>
        </table>

        <h3 class="dg-h3">Software</h3>
        <table class="dg-table">
          <thead><tr><th>Component</th><th>Version</th><th>Notes</th></tr></thead>
          <tbody>
            <tr><td>OS</td><td>Ubuntu 22.04 LTS</td><td>หรือ Debian 12 / RHEL 9</td></tr>
            <tr><td>PHP</td><td><strong>8.2+</strong></td><td>Laravel 12 ต้องการ 8.2 ขึ้นไป</td></tr>
            <tr><td>MySQL / MariaDB</td><td>5.7+ / 10.3+</td><td>หรือ PostgreSQL 13+</td></tr>
            <tr><td>nginx / Apache</td><td>nginx 1.18+ (แนะนำ)</td><td>Apache 2.4+ ก็ใช้ได้</td></tr>
            <tr><td>Composer</td><td>2.x</td><td>PHP package manager</td></tr>
            <tr><td>Node.js</td><td>18+ LTS</td><td>สำหรับ build assets (Vite)</td></tr>
          </tbody>
        </table>

        <h3 class="dg-h3">PHP Extensions ที่ต้องเปิด</h3>
        <p>โปรเจกต์ตรวจให้คุณอัตโนมัติในหน้า <code>/admin/deployment</code> Tab Health แต่ทั้งหมดที่ต้องมี:</p>
        <ul>
          <li><code>pdo</code>, <code>pdo_mysql</code> — เชื่อมต่อ DB</li>
          <li><code>mbstring</code>, <code>tokenizer</code>, <code>json</code> — Laravel core</li>
          <li><code>curl</code>, <code>openssl</code> — เรียก API ภายนอก (LINE, Stripe)</li>
          <li><code>gd</code> — ประมวลผลรูป + watermark</li>
          <li><code>fileinfo</code>, <code>bcmath</code>, <code>xml</code>, <code>zip</code> — ฟังก์ชันเสริม</li>
        </ul>
      </section>

      {{-- 3. เลือก VPS ───────────────────────────────────────── --}}
      <section id="s3" class="dg-card">
        <div class="flex items-center gap-3 mb-3">
          <span class="dg-section-num">3</span>
          <h2 class="dg-h2">เลือก VPS / Hosting</h2>
        </div>
        <p class="dg-lead">เลือก provider ที่ใกล้ลูกค้าไทย (Singapore region) เพื่อ latency ต่ำ</p>

        <table class="dg-table">
          <thead><tr><th>Provider</th><th>Plan</th><th>ราคา/เดือน</th><th>Region ใกล้ไทย</th></tr></thead>
          <tbody>
            <tr><td>DigitalOcean</td><td>Basic 2GB</td><td>~$12 (~฿420)</td><td>Singapore</td></tr>
            <tr><td>Linode (Akamai)</td><td>Nanode 2GB</td><td>~$10 (~฿350)</td><td>Singapore</td></tr>
            <tr><td>Vultr</td><td>HF 2GB</td><td>~$12 (~฿420)</td><td>Singapore / Tokyo</td></tr>
            <tr><td>AWS Lightsail</td><td>2GB</td><td>~$12 (~฿420)</td><td>Singapore</td></tr>
            <tr><td>Cloudways (managed)</td><td>DO 2GB</td><td>~$26 (~฿900)</td><td>Singapore</td></tr>
            <tr><td>HostNeverDie (TH)</td><td>VPS 2GB</td><td>~฿400</td><td>Bangkok</td></tr>
            <tr><td>RuayHost (TH)</td><td>VPS 2GB</td><td>~฿450</td><td>Bangkok</td></tr>
          </tbody>
        </table>

        <div class="dg-callout info">
          <i class="bi bi-info-circle-fill dg-callout-icon"></i>
          <div>
            <strong>แนะนำสำหรับเริ่มต้น:</strong> DigitalOcean Singapore $12/mo + Cloudflare R2 สำหรับเก็บรูป (egress ฟรี)
            — รวม ~฿500/เดือน เพียงพอสำหรับ 1,000-5,000 ออเดอร์/เดือน
          </div>
        </div>
      </section>

      {{-- 4. ตั้งค่า DNS ─────────────────────────────────────── --}}
      <section id="s4" class="dg-card">
        <div class="flex items-center gap-3 mb-3">
          <span class="dg-section-num">4</span>
          <h2 class="dg-h2">ตั้งค่า DNS</h2>
        </div>
        <p class="dg-lead">ชี้ domain ไปยัง VPS IP — ทำที่ Registrar (GoDaddy, Namecheap) หรือ Cloudflare</p>

        <h3 class="dg-h3">DNS Records ที่ต้องเพิ่ม</h3>
        <table class="dg-table">
          <thead><tr><th>Type</th><th>Name</th><th>Value</th><th>TTL</th></tr></thead>
          <tbody>
            <tr><td>A</td><td>@</td><td>YOUR_VPS_IP</td><td>3600</td></tr>
            <tr><td>A</td><td>www</td><td>YOUR_VPS_IP</td><td>3600</td></tr>
            <tr><td>CNAME (optional)</td><td>cdn</td><td>your-r2-bucket.r2.dev</td><td>3600</td></tr>
          </tbody>
        </table>

        <h3 class="dg-h3">ตรวจสอบ DNS โหลดได้แล้วหรือยัง</h3>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> dig your-domain.com +short
<span class="comment"># ควรได้ IP ของ VPS — ถ้ายังไม่ได้รอ 5-30 นาที</span>

<span class="prompt">$</span> nslookup your-domain.com
<span class="prompt">$</span> ping your-domain.com</div>
        </div>

        <div class="dg-callout tip">
          <i class="bi bi-lightbulb-fill dg-callout-icon"></i>
          <div>
            <strong>เคล็ดลับ Cloudflare:</strong> ถ้าใช้ Cloudflare ตั้ง <strong>Proxy: DNS only</strong> (cloud icon สีเทา)
            ตอน setup SSL จาก Let's Encrypt ก่อน — เปลี่ยนเป็นเขียวทีหลัง
          </div>
        </div>
      </section>

      {{-- 5. ติดตั้ง LAMP Stack ───────────────────────────── --}}
      <section id="s5" class="dg-card">
        <div class="flex items-center gap-3 mb-3">
          <span class="dg-section-num">5</span>
          <h2 class="dg-h2">ติดตั้ง LAMP Stack (Ubuntu 22.04)</h2>
        </div>
        <p class="dg-lead">SSH เข้า VPS แล้วรันคำสั่งทีละชุด (ใช้เวลา ~15 นาที)</p>

        <h3 class="dg-h3">5.1 อัปเดตระบบ + ติดตั้ง basic tools</h3>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> sudo apt update && sudo apt upgrade -y
<span class="prompt">$</span> sudo apt install -y software-properties-common curl git unzip ufw</div>
        </div>

        <h3 class="dg-h3">5.2 ติดตั้ง PHP 8.2 + Extensions</h3>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> sudo add-apt-repository ppa:ondrej/php -y
<span class="prompt">$</span> sudo apt update
<span class="prompt">$</span> sudo apt install -y php8.2-fpm php8.2-cli php8.2-mysql \
    php8.2-mbstring php8.2-tokenizer php8.2-curl php8.2-gd \
    php8.2-bcmath php8.2-xml php8.2-zip php8.2-intl

<span class="comment"># ตรวจ version</span>
<span class="prompt">$</span> php -v</div>
        </div>

        <h3 class="dg-h3">5.3 ติดตั้ง MariaDB + ตั้งค่าเบื้องต้น</h3>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> sudo apt install -y mariadb-server
<span class="prompt">$</span> sudo mysql_secure_installation
<span class="comment"># ตอบ Y ทุกข้อ + ตั้ง root password</span>

<span class="comment"># สร้าง database + user สำหรับโปรเจกต์</span>
<span class="prompt">$</span> sudo mysql -u root -p
<span class="key">mysql></span> CREATE DATABASE photo_gallery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
<span class="key">mysql></span> CREATE USER 'pg_user'@'localhost' IDENTIFIED BY '<span class="val">YOUR_STRONG_PASSWORD</span>';
<span class="key">mysql></span> GRANT ALL ON photo_gallery.* TO 'pg_user'@'localhost';
<span class="key">mysql></span> FLUSH PRIVILEGES;
<span class="key">mysql></span> EXIT;</div>
        </div>

        <h3 class="dg-h3">5.4 ติดตั้ง Composer</h3>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> curl -sS https://getcomposer.org/installer | sudo php -- \
    --install-dir=/usr/local/bin --filename=composer
<span class="prompt">$</span> composer --version</div>
        </div>

        <h3 class="dg-h3">5.5 ติดตั้ง Node.js 20 (LTS)</h3>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
<span class="prompt">$</span> sudo apt install -y nodejs
<span class="prompt">$</span> node -v && npm -v</div>
        </div>

        <h3 class="dg-h3">5.6 ติดตั้ง nginx</h3>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> sudo apt install -y nginx
<span class="prompt">$</span> sudo systemctl enable nginx --now

<span class="comment"># เปิด firewall</span>
<span class="prompt">$</span> sudo ufw allow OpenSSH
<span class="prompt">$</span> sudo ufw allow 'Nginx Full'
<span class="prompt">$</span> sudo ufw enable</div>
        </div>
      </section>

      {{-- 6. อัปโหลดโค้ด ────────────────────────────────────── --}}
      <section id="s6" class="dg-card">
        <div class="flex items-center gap-3 mb-3">
          <span class="dg-section-num">6</span>
          <h2 class="dg-h2">อัปโหลดโค้ดและ install dependencies</h2>
        </div>

        <h3 class="dg-h3">6.1 Clone โค้ดไปไว้ที่ /var/www</h3>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> sudo mkdir -p /var/www
<span class="prompt">$</span> sudo chown $USER:$USER /var/www
<span class="prompt">$</span> cd /var/www
<span class="prompt">$</span> git clone https://github.com/your-org/photo-gallery-tailwind.git
<span class="prompt">$</span> cd photo-gallery-tailwind</div>
        </div>

        <h3 class="dg-h3">6.2 สร้าง .env + ตั้ง permissions</h3>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> cp .env.example .env
<span class="prompt">$</span> sudo chown -R www-data:www-data /var/www/photo-gallery-tailwind
<span class="prompt">$</span> sudo find /var/www/photo-gallery-tailwind -type f -exec chmod 644 {} \;
<span class="prompt">$</span> sudo find /var/www/photo-gallery-tailwind -type d -exec chmod 755 {} \;
<span class="prompt">$</span> sudo chmod -R 775 storage bootstrap/cache
<span class="prompt">$</span> sudo chmod 664 .env</div>
        </div>

        <h3 class="dg-h3">6.3 ติดตั้ง dependencies + build assets</h3>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> composer install --no-dev --optimize-autoloader --no-interaction
<span class="prompt">$</span> npm ci
<span class="prompt">$</span> npm run build  <span class="comment"># build production assets (CSS/JS)</span></div>
        </div>

        <h3 class="dg-h3">6.4 ตั้งค่า nginx vhost</h3>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> sudo nano /etc/nginx/sites-available/photo-gallery</div>
        </div>
        <p>วาง config นี้ (เปลี่ยน <code>your-domain.com</code> เป็น domain ของคุณ):</p>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content">server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    root /var/www/photo-gallery-tailwind/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* { deny all; }

    client_max_body_size 100M;  <span class="comment"># สำหรับอัปโหลดรูปขนาดใหญ่</span>
}</div>
        </div>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> sudo ln -s /etc/nginx/sites-available/photo-gallery /etc/nginx/sites-enabled/
<span class="prompt">$</span> sudo rm /etc/nginx/sites-enabled/default  <span class="comment"># เอา default ออก</span>
<span class="prompt">$</span> sudo nginx -t
<span class="prompt">$</span> sudo systemctl reload nginx</div>
        </div>
      </section>

      {{-- 7. SSL ─────────────────────────────────────────────── --}}
      <section id="s7" class="dg-card">
        <div class="flex items-center gap-3 mb-3">
          <span class="dg-section-num">7</span>
          <h2 class="dg-h2">ติดตั้ง SSL ด้วย Let's Encrypt (ฟรี)</h2>
        </div>
        <p class="dg-lead">SSL จำเป็นสำหรับ LINE Login + payment webhooks (provider บังคับ HTTPS)</p>

        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> sudo apt install -y certbot python3-certbot-nginx
<span class="prompt">$</span> sudo certbot --nginx -d your-domain.com -d www.your-domain.com

<span class="comment"># ตอบ:</span>
<span class="comment"># - ใส่อีเมล (ใช้รับแจ้งเตือนใกล้หมดอายุ)</span>
<span class="comment"># - ตอบ A (Agree)</span>
<span class="comment"># - ตอบ N (ไม่ share อีเมลกับ EFF)</span>
<span class="comment"># - เลือก 2 (redirect HTTP → HTTPS)</span>

<span class="comment"># ตรวจ auto-renew (renew อัตโนมัติทุก 60 วัน)</span>
<span class="prompt">$</span> sudo certbot renew --dry-run</div>
        </div>

        <div class="dg-callout warn">
          <i class="bi bi-exclamation-triangle-fill dg-callout-icon"></i>
          <div>
            ก่อนรัน Certbot — DNS A record ต้อง resolve มา VPS แล้ว ไม่งั้น Let's Encrypt verify ไม่ผ่าน
          </div>
        </div>
      </section>

      {{-- 8. Install Wizard ──────────────────────────────────── --}}
      <section id="s8" class="dg-card">
        <div class="flex items-center gap-3 mb-3">
          <span class="dg-section-num">8</span>
          <h2 class="dg-h2">รัน Install Wizard ผ่าน Web</h2>
        </div>
        <p class="dg-lead">
          เปิด <code>https://your-domain.com/admin/deployment</code> — ระบบจะอยู่ใน <strong>Install Mode</strong> อัตโนมัติ
          (เพราะยังไม่มี admin user) → ทำตาม 4 ขั้นตอน
        </p>

        <h3 class="dg-h3">Step 1: Generate APP_KEY</h3>
        <p>กดปุ่ม "Generate APP_KEY" — ระบบจะ random key 32 bytes แล้วเขียนลง <code>.env</code></p>

        <h3 class="dg-h3">Step 2: ตั้งค่า Database</h3>
        <p>ไปที่ Tab Database — กรอก:</p>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="key">DB_HOST</span>     = 127.0.0.1
<span class="key">DB_PORT</span>     = 3306
<span class="key">DB_DATABASE</span> = photo_gallery
<span class="key">DB_USERNAME</span> = pg_user
<span class="key">DB_PASSWORD</span> = <span class="val">YOUR_STRONG_PASSWORD</span></div>
        </div>
        <p>กด <strong>[ทดสอบเชื่อมต่อ]</strong> → ✓ → กด <strong>[บันทึก Database]</strong></p>

        <h3 class="dg-h3">Step 3: รัน Migrations</h3>
        <p>กลับมาที่ banner Install Mode → กด <strong>[รัน php artisan migrate]</strong> — ใช้เวลา 30 วินาที สร้าง 120+ tables</p>

        <h3 class="dg-h3">Step 4: สร้าง Admin User คนแรก</h3>
        <p>กรอก name + email + password (≥ 8 ตัว) → กด <strong>[สร้าง Admin]</strong> → redirect ไป login page</p>

        <div class="dg-callout danger">
          <i class="bi bi-shield-fill-exclamation dg-callout-icon"></i>
          <div>
            <strong>Security:</strong> Install mode บน production ป้องกัน drive-by ด้วย IP check —
            ทำให้ขั้นตอนนี้ปลอดภัยภายใน 5-10 นาทีหลัง deploy แต่ <strong>ห้ามทิ้งไว้ค้าง</strong>
            ทำ Step 1-4 ติดต่อกันให้เสร็จในรอบเดียว
          </div>
        </div>
      </section>

      {{-- 9. External APIs ──────────────────────────────────── --}}
      <section id="s9" class="dg-card">
        <div class="flex items-center gap-3 mb-3">
          <span class="dg-section-num">9</span>
          <h2 class="dg-h2">ตั้งค่า External APIs</h2>
        </div>
        <p class="dg-lead">หลัง login admin แล้ว ตั้งค่า API credentials ในแต่ละหน้า</p>

        <table class="dg-table">
          <thead><tr><th>Service</th><th>หน้าตั้งค่า</th><th>ที่ขอ key</th></tr></thead>
          <tbody>
            <tr><td>LINE Login + Messaging</td><td><code>/admin/settings/line</code></td><td>developers.line.biz</td></tr>
            <tr><td>LINE Rich Menu</td><td><code>/admin/settings/line/richmenu</code></td><td>same channel</td></tr>
            <tr><td>Stripe / Omise / PayPal</td><td><code>/admin/settings/payment-gateways</code></td><td>provider dashboard</td></tr>
            <tr><td>Google / LINE / Facebook OAuth</td><td><code>/admin/settings/social-auth</code></td><td>console.cloud.google.com</td></tr>
            <tr><td>AWS Rekognition + S3</td><td><code>/admin/settings/aws</code></td><td>console.aws.amazon.com</td></tr>
            <tr><td>SMTP / Email</td><td><code>/admin/deployment</code> Tab Mail</td><td>Gmail / SES / Mailgun</td></tr>
          </tbody>
        </table>

        <div class="dg-callout info">
          <i class="bi bi-info-circle-fill dg-callout-icon"></i>
          <div>
            <strong>Webhook URLs ที่ต้อง register:</strong> ดูครบทุก URL ที่ <code>/admin/deployment</code> Tab <strong>URLs</strong>
            — ทุก URL auto-generate จาก APP_URL พร้อมปุ่ม Copy
          </div>
        </div>
      </section>

      {{-- 10. Cron + Queue ──────────────────────────────────── --}}
      <section id="s10" class="dg-card">
        <div class="flex items-center gap-3 mb-3">
          <span class="dg-section-num">10</span>
          <h2 class="dg-h2">ตั้ง Cron + Queue Worker</h2>
        </div>

        <h3 class="dg-h3">10.1 Cron — รัน scheduled tasks ทุก 1 นาที</h3>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> sudo crontab -e -u www-data
<span class="comment"># เพิ่มบรรทัดนี้:</span>
* * * * * cd /var/www/photo-gallery-tailwind && php artisan schedule:run >> /dev/null 2>&1</div>
        </div>

        <h3 class="dg-h3">10.2 Queue Worker — ใช้ Supervisor</h3>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> sudo apt install -y supervisor
<span class="prompt">$</span> sudo nano /etc/supervisor/conf.d/photo-gallery-worker.conf</div>
        </div>
        <p>วาง:</p>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content">[program:photo-gallery-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/photo-gallery-tailwind/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/photo-gallery-worker.log
stopwaitsecs=3600</div>
        </div>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> sudo supervisorctl reread
<span class="prompt">$</span> sudo supervisorctl update
<span class="prompt">$</span> sudo supervisorctl start photo-gallery-worker:*
<span class="prompt">$</span> sudo supervisorctl status</div>
        </div>
      </section>

      {{-- 11. Optimize ──────────────────────────────────────── --}}
      <section id="s11" class="dg-card">
        <div class="flex items-center gap-3 mb-3">
          <span class="dg-section-num">11</span>
          <h2 class="dg-h2">Optimize Performance</h2>
        </div>
        <p class="dg-lead">cache routes/config/views เพื่อลด overhead 30-50%</p>

        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> cd /var/www/photo-gallery-tailwind
<span class="prompt">$</span> php artisan config:cache
<span class="prompt">$</span> php artisan route:cache
<span class="prompt">$</span> php artisan view:cache
<span class="prompt">$</span> php artisan event:cache
<span class="prompt">$</span> php artisan optimize  <span class="comment"># รวมทุกอย่างใน 1 คำสั่ง</span>

<span class="comment"># Storage symlink (สำหรับเข้าถึง storage/app/public ผ่าน /storage)</span>
<span class="prompt">$</span> php artisan storage:link</div>
        </div>

        <h3 class="dg-h3">เปิด PHP OPcache</h3>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> sudo nano /etc/php/8.2/fpm/conf.d/10-opcache.ini
<span class="comment"># แก้:</span>
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0  <span class="comment"># production: 0 (รีโหลด PHP-FPM เมื่อ deploy)</span>

<span class="prompt">$</span> sudo systemctl restart php8.2-fpm</div>
        </div>
      </section>

      {{-- 12. Backup ────────────────────────────────────────── --}}
      <section id="s12" class="dg-card">
        <div class="flex items-center gap-3 mb-3">
          <span class="dg-section-num">12</span>
          <h2 class="dg-h2">Backup Strategy</h2>
        </div>

        <h3 class="dg-h3">Daily DB backup → /backups</h3>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> sudo mkdir -p /backups && sudo chown www-data:www-data /backups
<span class="prompt">$</span> sudo nano /usr/local/bin/db-backup.sh</div>
        </div>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content">#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u pg_user -p<span class="val">YOUR_PASSWORD</span> photo_gallery | gzip > /backups/db_$DATE.sql.gz
<span class="comment"># เก็บ 30 ไฟล์ล่าสุด ลบที่เหลือ</span>
ls -tp /backups/db_*.sql.gz | grep -v '/$' | tail -n +31 | xargs -I {} rm -- {}</div>
        </div>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> sudo chmod +x /usr/local/bin/db-backup.sh
<span class="prompt">$</span> sudo crontab -e
<span class="comment"># เพิ่ม:</span>
0 3 * * * /usr/local/bin/db-backup.sh  <span class="comment"># รันทุกตี 3</span></div>
        </div>

        <div class="dg-callout tip">
          <i class="bi bi-lightbulb-fill dg-callout-icon"></i>
          <div>
            <strong>เคล็ดลับ:</strong> ใช้ <code>rclone</code> sync /backups ขึ้น R2/S3 ทุกคืน
            — กันกรณี VPS เสีย ยังกู้คืน DB ได้
          </div>
        </div>
      </section>

      {{-- 13. Monitoring ───────────────────────────────────── --}}
      <section id="s13" class="dg-card">
        <div class="flex items-center gap-3 mb-3">
          <span class="dg-section-num">13</span>
          <h2 class="dg-h2">Monitoring & Logs</h2>
        </div>

        <h3 class="dg-h3">Log files ที่ต้องดู</h3>
        <table class="dg-table">
          <thead><tr><th>Log</th><th>Path</th><th>เมื่อไหร่ดู</th></tr></thead>
          <tbody>
            <tr><td>Laravel</td><td><code>storage/logs/laravel.log</code></td><td>error 500, exception</td></tr>
            <tr><td>nginx access</td><td><code>/var/log/nginx/access.log</code></td><td>ดู traffic</td></tr>
            <tr><td>nginx error</td><td><code>/var/log/nginx/error.log</code></td><td>502/504 issues</td></tr>
            <tr><td>PHP-FPM</td><td><code>/var/log/php8.2-fpm.log</code></td><td>worker crash</td></tr>
            <tr><td>Worker</td><td><code>/var/log/photo-gallery-worker.log</code></td><td>queue jobs</td></tr>
          </tbody>
        </table>

        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="comment"># ดู log แบบ live</span>
<span class="prompt">$</span> tail -f storage/logs/laravel.log
<span class="prompt">$</span> sudo tail -f /var/log/nginx/error.log

<span class="comment"># Health check (cron friendly)</span>
<span class="prompt">$</span> curl -s -o /dev/null -w "%{http_code}" https://your-domain.com/up</div>
        </div>

        <p>หรือใช้ <strong>Sentry</strong> (มี integration ติดตั้งแล้ว — แค่ใส่ DSN ใน <code>.env</code>):</p>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="key">SENTRY_LARAVEL_DSN</span>=<span class="val">https://xxx@sentry.io/yyy</span></div>
        </div>
      </section>

      {{-- 14. Troubleshooting ──────────────────────────────── --}}
      <section id="s14" class="dg-card">
        <div class="flex items-center gap-3 mb-3">
          <span class="dg-section-num">14</span>
          <h2 class="dg-h2">Troubleshooting (ปัญหาที่เจอบ่อย)</h2>
        </div>

        <h3 class="dg-h3">❌ 500 Internal Server Error ตอนเปิดเว็บ</h3>
        <ul>
          <li>ดู <code>storage/logs/laravel.log</code> — มักเป็น exception หรือ permission issue</li>
          <li>ตรวจ <code>chmod -R 775 storage bootstrap/cache</code></li>
          <li>ตรวจว่า APP_KEY ตั้งแล้ว (<code>php artisan key:generate</code>)</li>
        </ul>

        <h3 class="dg-h3">❌ "Permission denied" ตอน upload รูป</h3>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> sudo chown -R www-data:www-data storage public/storage
<span class="prompt">$</span> sudo chmod -R 775 storage</div>
        </div>

        <h3 class="dg-h3">❌ 502 Bad Gateway</h3>
        <ul>
          <li>PHP-FPM ไม่ทำงาน → <code>sudo systemctl restart php8.2-fpm</code></li>
          <li>ตรวจ socket path ใน nginx config ตรงกับ PHP version</li>
        </ul>

        <h3 class="dg-h3">❌ LINE Login redirect ไม่กลับ</h3>
        <ul>
          <li>ตรวจว่า Callback URL ใน developers.line.biz ตรงกับ <code>{APP_URL}/auth/line/callback</code> ทุกตัวอักษร</li>
          <li>ต้องเป็น HTTPS (LINE บังคับ)</li>
          <li>APP_URL ใน .env ต้องไม่มี trailing slash</li>
        </ul>

        <h3 class="dg-h3">❌ Webhook ไม่เข้า (ไม่ได้รับ event)</h3>
        <ul>
          <li>ตรวจ firewall เปิด port 443 จากภายนอก</li>
          <li>SSL ต้อง valid (Let's Encrypt OK ทุก provider)</li>
          <li>ดู nginx access.log ว่า provider ส่ง POST มาจริงไหม</li>
        </ul>

        <h3 class="dg-h3">❌ Queue job ไม่รัน</h3>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content"><span class="prompt">$</span> sudo supervisorctl status
<span class="prompt">$</span> sudo supervisorctl restart photo-gallery-worker:*

<span class="comment"># หาก deploy code ใหม่ ต้อง restart worker</span>
<span class="prompt">$</span> php artisan queue:restart</div>
        </div>
      </section>

      {{-- 15. Security Checklist ────────────────────────────── --}}
      <section id="s15" class="dg-card">
        <div class="flex items-center gap-3 mb-3">
          <span class="dg-section-num">15</span>
          <h2 class="dg-h2">Security Checklist</h2>
        </div>
        <p class="dg-lead">ทำตามรายการนี้ก่อนเปิดให้ลูกค้าใช้จริง</p>

        <h3 class="dg-h3">.env Settings</h3>
        <ul>
          <li>✅ <code>APP_DEBUG=false</code> (ห้ามเปิดบน production)</li>
          <li>✅ <code>APP_ENV=production</code></li>
          <li>✅ <code>APP_KEY</code> ถูก generate (32 bytes)</li>
          <li>✅ <code>chmod 664 .env</code> + <code>chown www-data:www-data .env</code></li>
        </ul>

        <h3 class="dg-h3">Database</h3>
        <ul>
          <li>✅ DB user มี permission เฉพาะ <code>photo_gallery.*</code> ไม่ใช่ ALL</li>
          <li>✅ DB password ≥ 16 ตัวอักษร mix uppercase/lowercase/symbol</li>
          <li>✅ MySQL bind-address = 127.0.0.1 (ห้าม listen public)</li>
        </ul>

        <h3 class="dg-h3">Server</h3>
        <ul>
          <li>✅ UFW เปิดเฉพาะ port 22, 80, 443</li>
          <li>✅ SSH disable password login (ใช้ keys อย่างเดียว)</li>
          <li>✅ Fail2ban ติดตั้งกัน brute force</li>
          <li>✅ <code>unattended-upgrades</code> เปิดสำหรับ security patches</li>
        </ul>

        <h3 class="dg-h3">Application</h3>
        <ul>
          <li>✅ Admin password ≥ 12 ตัว + เปิด 2FA</li>
          <li>✅ Stripe/Omise webhook signing secret ตั้งแล้ว</li>
          <li>✅ LINE channel secret ใส่ใน Webhook verify</li>
          <li>✅ R2/S3 bucket policy ไม่ public list</li>
          <li>✅ Cloudflare Turnstile (CAPTCHA) เปิดในหน้า login</li>
        </ul>

        <h3 class="dg-h3">SSH Hardening (อยู่ <code>/etc/ssh/sshd_config</code>)</h3>
        <div class="dg-code">
          <button class="dg-code-copy" @click="copyCode($el)"><i class="bi bi-clipboard"></i></button>
          <div class="code-content">PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
Port 22  <span class="comment"># เปลี่ยนเป็น port อื่นได้ (แต่ต้องจำ)</span></div>
        </div>

        <div class="dg-callout danger">
          <i class="bi bi-shield-fill-exclamation dg-callout-icon"></i>
          <div>
            <strong>คำเตือน:</strong> APP_DEBUG=true บน production แสดง stack trace + ข้อมูล sensitive
            ให้ผู้โจมตีเห็น — ตรวจซ้ำให้แน่ใจว่าเป็น <code>false</code>
          </div>
        </div>
      </section>

      {{-- Footer call-to-action --}}
      <div class="dg-card text-center" style="background:linear-gradient(135deg,#6366f1,#8b5cf6); color:white; border:none;">
        <h3 class="dg-h2" style="color:white;">🎉 พร้อม Deploy แล้ว!</h3>
        <p style="color:rgba(255,255,255,0.9); margin:0.5rem 0 1rem;">
          ขั้นตอนทั้งหมด ~ 1-2 ชั่วโมง — ลำดับความสำคัญ: DNS → SSL → Install Wizard → Webhook URLs
        </p>
        <a href="{{ route('admin.deployment.index') }}"
           class="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-bold text-violet-700 bg-white shadow-lg hover:-translate-y-0.5 transition no-underline">
          <i class="bi bi-rocket-takeoff-fill"></i> ไปยังหน้า Deployment
        </a>
      </div>

    </main>
  </div>

</div>
@endsection
