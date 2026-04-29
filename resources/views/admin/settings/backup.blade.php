@extends('layouts.admin')

@section('title', 'Backup System')

@push('styles')
<style>
  .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(16,185,129,.12) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(20,184,166,.10) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(6,182,212,.12) 0px, transparent 50%);
  }
  .dark .gradient-mesh {
    background:
      radial-gradient(at 0% 0%, rgba(16,185,129,.18) 0px, transparent 50%),
      radial-gradient(at 50% 50%, rgba(20,184,166,.14) 0px, transparent 50%),
      radial-gradient(at 100% 100%, rgba(6,182,212,.18) 0px, transparent 50%);
  }
  .backup-card {
    transition: transform .25s cubic-bezier(.34,1.56,.64,1), box-shadow .25s, border-color .25s;
  }
  .backup-card:hover { transform: translateY(-2px); box-shadow: 0 18px 36px -12px rgba(0,0,0,.18); }
  @keyframes pulse-soft {
    0%,100% { box-shadow: 0 0 0 0 var(--c, rgba(16,185,129,.5)); }
    50%     { box-shadow: 0 0 0 10px transparent; }
  }
  .pulse-soft { animation: pulse-soft 2.5s ease-in-out infinite; }
</style>
@endpush

@section('content')
@php
  // Driver detection — show different binary status per active driver
  $driver = \DB::connection()->getDriverName();
  $isPg   = $driver === 'pgsql';
  $isMy   = in_array($driver, ['mysql','mariadb'], true);
  $dbName = config("database.connections.{$driver}.database", '?');

  // Find the binary path so we can show it in the UI as evidence
  $finder    = new \Symfony\Component\Process\ExecutableFinder();
  $binName   = $isPg ? 'pg_dump' : 'mysqldump';
  $binPath   = $finder->find($binName);
  if (!$binPath) {
    $candidates = $isPg
      ? ['C:\Program Files\PostgreSQL\17\bin\pg_dump.exe',
         'C:\Program Files\PostgreSQL\16\bin\pg_dump.exe',
         'C:\PostgresData\pg16-portable\pgsql\bin\pg_dump.exe',
         '/usr/bin/pg_dump','/usr/local/bin/pg_dump','/opt/homebrew/bin/pg_dump']
      : ['C:\xampp\mysql\bin\mysqldump.exe',
         'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe',
         '/usr/bin/mysqldump'];
    foreach ($candidates as $p) {
      if (is_file($p)) { $binPath = $p; break; }
    }
  }

  // Stats
  $totalBytes = collect($backupFiles)->sum('size');
  $latest     = collect($backupFiles)->sortByDesc(fn($f) => $f['modified']->timestamp)->first();
  $latestAge  = $latest ? $latest['modified']->diffForHumans() : 'ยังไม่มี';

  // Compute next 03:00 in local TZ
  $nextRun = now()->setTime(3, 0, 0);
  if ($nextRun->isPast()) $nextRun->addDay();

  // Count by type
  $countByType = ['db' => 0, 'files' => 0, 'full' => 0];
  foreach ($backupFiles as $f) {
    if (str_starts_with($f['name'], 'backup_full_'))    $countByType['full']++;
    elseif (str_starts_with($f['name'], 'backup_files_')) $countByType['files']++;
    elseif (str_ends_with($f['name'], '.sql'))            $countByType['db']++;
  }
@endphp

<div class="space-y-5 pb-12">

  {{-- ── HERO HEADER ─────────────────────────────────────────────── --}}
  <div class="relative overflow-hidden rounded-2xl border border-emerald-100 dark:border-emerald-500/20 gradient-mesh">
    <div class="relative p-6 md:p-7">
      <nav class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400 mb-3">
        <a href="{{ route('admin.settings.index') }}" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition">Settings</a>
        <i class="bi bi-chevron-right text-[0.6rem] opacity-50"></i>
        <span class="text-slate-700 dark:text-slate-200 font-medium">Backup &amp; Restore</span>
      </nav>

      <div class="flex items-start justify-between flex-wrap gap-4">
        <div class="flex items-start gap-4 min-w-0 flex-1">
          <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-500 to-cyan-500 text-white flex items-center justify-center shadow-lg shrink-0">
            <i class="bi bi-cloud-download-fill text-2xl"></i>
          </div>
          <div class="min-w-0 flex-1">
            <h1 class="text-2xl md:text-[1.65rem] font-bold text-slate-800 dark:text-slate-100 tracking-tight leading-tight">
              สำรองข้อมูล &amp; กู้คืน
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
              Database snapshot · Project files · Full backup · CLI + auto-schedule + 14-day retention
            </p>

            <div class="flex items-center gap-2 mt-3 flex-wrap">
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                {{ $isPg ? 'PostgreSQL' : ($isMy ? 'MySQL/MariaDB' : ucfirst($driver)) }}
              </span>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-cyan-100 text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-300">
                <i class="bi bi-database"></i> {{ $dbName }}
              </span>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold {{ $binPath ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' }}">
                <i class="bi {{ $binPath ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' }}"></i>
                {{ $binName }} {{ $binPath ? 'พร้อมใช้' : 'ไม่พบ (จะใช้ PHP fallback)' }}
              </span>
              <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
                <i class="bi bi-clock-history"></i> Auto: ทุกวัน 03:00 น.
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Flash messages are shown by layouts/admin.blade.php at the top of <main>. --}}
  {{-- We add an extra inline confirmation toast pinned near the action cards   --}}
  {{-- so the user gets a high-visibility "✓ Backup created" cue + auto-scroll. --}}
  @if(session('success'))
    <div id="backup-success-toast"
         x-data="{ show: true }" x-show="show" x-cloak
         x-init="setTimeout(() => { document.getElementById('backup-files-section')?.scrollIntoView({behavior:'smooth', block:'start'}); }, 300)"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="relative overflow-hidden rounded-2xl border-2 border-emerald-300 dark:border-emerald-500/40 shadow-xl shadow-emerald-500/10"
         style="background: linear-gradient(135deg, rgba(16,185,129,.12) 0%, rgba(20,184,166,.08) 50%, rgba(6,182,212,.10) 100%);">
      <div class="absolute -top-8 -right-8 w-32 h-32 rounded-full opacity-20 bg-gradient-to-br from-emerald-400 to-cyan-400 blur-2xl"></div>
      <div class="relative p-4 flex items-center gap-4 flex-wrap">
        <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 via-teal-500 to-cyan-500 text-white flex items-center justify-center shadow-lg shadow-emerald-500/30 shrink-0">
          <i class="bi bi-check-circle-fill text-2xl"></i>
        </div>
        <div class="flex-1 min-w-0">
          <div class="font-bold text-emerald-800 dark:text-emerald-200 text-sm">สำรองสำเร็จ!</div>
          <div class="text-xs text-emerald-700/80 dark:text-emerald-300/80 mt-0.5 break-all">{{ session('success') }}</div>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <a href="#backup-files-section"
             class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-md">
            <i class="bi bi-arrow-down"></i> ดูไฟล์
          </a>
          <button type="button" @click="show=false"
                  class="text-emerald-600/60 hover:text-emerald-800 dark:text-emerald-400/60 dark:hover:text-emerald-200 transition p-1 rounded">
            <i class="bi bi-x-lg text-sm"></i>
          </button>
        </div>
      </div>
    </div>
  @endif
  @if(session('error'))
    <div class="flex items-center gap-2 p-4 rounded-2xl bg-rose-50 dark:bg-rose-500/10 border-2 border-rose-300 dark:border-rose-500/30 text-rose-700 dark:text-rose-300 shadow-md shadow-rose-500/10">
      <div class="w-10 h-10 rounded-lg bg-rose-500 text-white flex items-center justify-center shrink-0">
        <i class="bi bi-exclamation-triangle-fill text-lg"></i>
      </div>
      <div class="flex-1">
        <div class="font-bold text-rose-800 dark:text-rose-200 text-sm">เกิดข้อผิดพลาด</div>
        <div class="text-xs text-rose-700/80 dark:text-rose-300/80 mt-0.5 break-all">{{ session('error') }}</div>
      </div>
    </div>
  @endif

  {{-- ── STATS ROW ───────────────────────────────────────────────── --}}
  <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
    {{-- Total backups --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-emerald-100 dark:bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
          <i class="bi bi-archive-fill"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">ไฟล์ทั้งหมด</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ count($backupFiles) }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-snug">
        DB {{ $countByType['db'] }} · Files {{ $countByType['files'] }} · Full {{ $countByType['full'] }}
      </div>
    </div>

    {{-- Total size --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-cyan-100 dark:bg-cyan-500/15 text-cyan-600 dark:text-cyan-400 flex items-center justify-center">
          <i class="bi bi-hdd-fill"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">พื้นที่ใช้</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">
        @if($totalBytes >= 1073741824)
          {{ number_format($totalBytes / 1073741824, 2) }} <span class="text-sm font-normal text-slate-500">GB</span>
        @elseif($totalBytes >= 1048576)
          {{ number_format($totalBytes / 1048576, 1) }} <span class="text-sm font-normal text-slate-500">MB</span>
        @elseif($totalBytes > 0)
          {{ number_format($totalBytes / 1024, 1) }} <span class="text-sm font-normal text-slate-500">KB</span>
        @else
          0 <span class="text-sm font-normal text-slate-500">B</span>
        @endif
      </div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">storage/app/backups/</div>
    </div>

    {{-- Latest backup --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl p-4 shadow-sm">
      <div class="flex items-center gap-2 mb-2">
        <div class="w-9 h-9 rounded-lg bg-violet-100 dark:bg-violet-500/15 text-violet-600 dark:text-violet-400 flex items-center justify-center">
          <i class="bi bi-clock-history"></i>
        </div>
        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">backup ล่าสุด</span>
      </div>
      <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $latestAge }}</div>
      <div class="text-xs text-slate-500 dark:text-slate-400 mt-1 truncate">
        {{ $latest ? $latest['modified']->format('d/m/Y H:i') : '—' }}
      </div>
    </div>

    {{-- Next auto backup --}}
    <div class="bg-white dark:bg-slate-800 border border-emerald-200/40 dark:border-emerald-500/15 rounded-2xl p-4 shadow-sm relative overflow-hidden">
      <div class="absolute -top-6 -right-6 w-20 h-20 rounded-full opacity-30 bg-gradient-to-br from-emerald-300 to-teal-300 blur-2xl"></div>
      <div class="relative">
        <div class="flex items-center gap-2 mb-2">
          <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-emerald-500 to-teal-500 text-white flex items-center justify-center pulse-soft" style="--c:rgba(16,185,129,.5);">
            <i class="bi bi-calendar-check-fill"></i>
          </div>
          <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">Auto-backup รอบหน้า</span>
        </div>
        <div class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{ $nextRun->diffForHumans(['parts' => 1, 'short' => true]) }}</div>
        <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ $nextRun->format('d/m/Y') }} 03:00</div>
      </div>
    </div>
  </div>

  {{-- ── BACKUP ACTION CARDS ─────────────────────────────────────── --}}
  <div>
    <div class="flex items-center justify-between mb-3 px-1">
      <h2 class="text-sm font-bold text-slate-700 dark:text-slate-200 flex items-center gap-2">
        <i class="bi bi-lightning-charge-fill text-amber-500"></i>
        สำรองข้อมูลตอนนี้
      </h2>
      <span class="text-xs text-slate-500 dark:text-slate-400">เลือกประเภทที่ต้องการ</span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
      {{-- Database only --}}
      <div class="backup-card bg-white dark:bg-slate-800 border border-slate-200/70 dark:border-white/[0.06] rounded-2xl p-5 shadow-sm relative overflow-hidden">
        <div class="absolute -top-12 -right-12 w-32 h-32 rounded-full opacity-20 bg-gradient-to-br from-indigo-400 to-violet-500 blur-2xl"></div>
        <div class="relative">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-500 text-white flex items-center justify-center shadow-md shadow-indigo-500/30">
              <i class="bi bi-database-fill-down text-xl"></i>
            </div>
            <div>
              <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100">ฐานข้อมูล</h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">SQL dump</p>
            </div>
          </div>
          <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed mb-4 min-h-[36px]">
            ไฟล์ <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700/50 text-[11px] font-mono">.sql</code> รวม schema + data + indexes พร้อม restore
          </p>
          <form method="POST" action="{{ route('admin.settings.backup.database') }}"
                onsubmit="return confirmBackup(this, 'สำรองฐานข้อมูลเดี๋ยวนี้?');">
            @csrf
            <button type="submit"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold
                           bg-gradient-to-br from-indigo-500 to-violet-500 text-white shadow-md hover:shadow-lg
                           hover:-translate-y-0.5 transition-all duration-200">
              <i class="bi bi-download"></i> สำรอง DB
            </button>
          </form>
          <div class="mt-2 text-[10px] text-slate-400 dark:text-slate-500 text-center">
            ใช้เวลา ~1-5 วินาที
          </div>
        </div>
      </div>

      {{-- Files only --}}
      <div class="backup-card bg-white dark:bg-slate-800 border border-slate-200/70 dark:border-white/[0.06] rounded-2xl p-5 shadow-sm relative overflow-hidden">
        <div class="absolute -top-12 -right-12 w-32 h-32 rounded-full opacity-20 bg-gradient-to-br from-amber-400 to-orange-500 blur-2xl"></div>
        <div class="relative">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 text-white flex items-center justify-center shadow-md shadow-amber-500/30">
              <i class="bi bi-folder-symlink-fill text-xl"></i>
            </div>
            <div>
              <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100">โปรเจกต์</h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">Source code ZIP</p>
            </div>
          </div>
          <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed mb-4 min-h-[36px]">
            <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700/50 text-[11px] font-mono">.zip</code> ไฟล์โค้ด — ข้าม vendor / node_modules / .git / cache / logs
          </p>
          <form method="POST" action="{{ route('admin.settings.backup.files') }}"
                onsubmit="return confirmBackup(this, 'สำรองโปรเจกต์เดี๋ยวนี้? อาจใช้เวลา 1-3 นาที');">
            @csrf
            <button type="submit"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold
                           bg-gradient-to-br from-amber-500 to-orange-500 text-white shadow-md hover:shadow-lg
                           hover:-translate-y-0.5 transition-all duration-200">
              <i class="bi bi-file-earmark-zip"></i> สำรองไฟล์
            </button>
          </form>
          <div class="mt-2 text-[10px] text-slate-400 dark:text-slate-500 text-center">
            ใช้เวลา ~1-3 นาที
          </div>
        </div>
      </div>

      {{-- Full backup (recommended) --}}
      <div class="backup-card bg-white dark:bg-slate-800 border-2 border-emerald-300 dark:border-emerald-500/30 rounded-2xl p-5 shadow-md shadow-emerald-500/10 relative overflow-hidden">
        <span class="absolute top-2 right-2 px-2.5 py-0.5 bg-gradient-to-r from-emerald-500 to-teal-500 text-white text-[10px] font-bold rounded-full shadow-md">
          <i class="bi bi-star-fill text-[8px]"></i> แนะนำ
        </span>
        <div class="absolute -top-12 -right-12 w-32 h-32 rounded-full opacity-25 bg-gradient-to-br from-emerald-400 to-teal-500 blur-2xl"></div>
        <div class="relative">
          <div class="flex items-center gap-3 mb-3">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-emerald-500 via-teal-500 to-cyan-500 text-white flex items-center justify-center shadow-md shadow-emerald-500/30">
              <i class="bi bi-box-seam-fill text-xl"></i>
            </div>
            <div>
              <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100">ทั้งหมด</h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">DB + Files combined</p>
            </div>
          </div>
          <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed mb-4 min-h-[36px]">
            ZIP รวม <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700/50 text-[11px] font-mono">database.sql</code> + โปรเจกต์ — เหมาะกับการย้าย server
          </p>
          <form method="POST" action="{{ route('admin.settings.backup.full') }}"
                onsubmit="return confirmBackup(this, 'สำรองทั้งระบบเดี๋ยวนี้? อาจใช้เวลา 2-5 นาที');">
            @csrf
            <button type="submit"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold
                           bg-gradient-to-br from-emerald-500 via-teal-500 to-cyan-500 text-white shadow-md hover:shadow-xl
                           hover:-translate-y-0.5 transition-all duration-200">
              <i class="bi bi-cloud-download-fill"></i> สำรองทั้งหมด
            </button>
          </form>
          <div class="mt-2 text-[10px] text-slate-400 dark:text-slate-500 text-center">
            ใช้เวลา ~2-5 นาที
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ── AUTO-SCHEDULE INFO + CLI ────────────────────────────────── --}}
  <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
    {{-- Auto-schedule card --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-100 dark:border-white/[0.06] bg-gradient-to-r from-violet-50/50 to-transparent dark:from-violet-500/5">
        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
          <i class="bi bi-clock-history text-violet-500"></i>
          Auto-schedule (Cron)
          <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">เปิดอยู่</span>
        </h2>
      </div>
      <div class="p-5 space-y-3 text-sm">
        <div class="flex items-start gap-3">
          <i class="bi bi-arrow-right-circle-fill text-violet-500 mt-0.5"></i>
          <div>
            <div class="font-semibold text-slate-700 dark:text-slate-200">ทุกวันเวลา 03:00 น.</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">รอบหน้า: {{ $nextRun->format('d/m/Y H:i') }} ({{ $nextRun->diffForHumans() }})</div>
          </div>
        </div>
        <div class="flex items-start gap-3">
          <i class="bi bi-arrow-right-circle-fill text-violet-500 mt-0.5"></i>
          <div>
            <div class="font-semibold text-slate-700 dark:text-slate-200">เก็บไฟล์ 14 วัน</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">ไฟล์เก่ากว่า 14 วันถูกลบอัตโนมัติ</div>
          </div>
        </div>
        <div class="flex items-start gap-3">
          <i class="bi bi-arrow-right-circle-fill text-violet-500 mt-0.5"></i>
          <div>
            <div class="font-semibold text-slate-700 dark:text-slate-200">Lock-aware</div>
            <div class="text-xs text-slate-500 dark:text-slate-400">ไม่รัน 2 ตัวพร้อมกัน — ป้องกัน corruption</div>
          </div>
        </div>
        <div class="mt-3 pt-3 border-t border-slate-100 dark:border-white/[0.06] text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
          <i class="bi bi-info-circle"></i>
          ต้องตั้ง cron บน server: <code class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-700/50 font-mono">* * * * * php artisan schedule:run</code>
        </div>
      </div>
    </div>

    {{-- CLI command card --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden">
      <div class="px-5 py-3 border-b border-slate-100 dark:border-white/[0.06] bg-gradient-to-r from-cyan-50/50 to-transparent dark:from-cyan-500/5">
        <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
          <i class="bi bi-terminal-fill text-cyan-500"></i>
          CLI Command
        </h2>
      </div>
      <div class="p-5 space-y-3 text-sm">
        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
          รันด้วย artisan ได้เลย — เหมาะสำหรับ cron, manual SSH, หรือ deploy script
        </p>

        <div class="space-y-2">
          <div class="flex items-center justify-between gap-2 p-2.5 rounded-lg bg-slate-900 dark:bg-black/40 text-emerald-300 font-mono text-xs">
            <code class="select-all">php artisan backup:database</code>
            <button type="button" onclick="navigator.clipboard.writeText('php artisan backup:database')"
                    class="text-slate-500 hover:text-emerald-400 transition" title="Copy">
              <i class="bi bi-clipboard"></i>
            </button>
          </div>
          <div class="flex items-center justify-between gap-2 p-2.5 rounded-lg bg-slate-900 dark:bg-black/40 text-emerald-300 font-mono text-[11px]">
            <code class="select-all">php artisan backup:database --keep-days=30</code>
            <button type="button" onclick="navigator.clipboard.writeText('php artisan backup:database --keep-days=30')"
                    class="text-slate-500 hover:text-emerald-400 transition" title="Copy">
              <i class="bi bi-clipboard"></i>
            </button>
          </div>
        </div>

        <div class="text-[11px] text-slate-500 dark:text-slate-400 space-y-1 leading-relaxed">
          <div><code class="text-cyan-600 dark:text-cyan-400">--keep-days=N</code> เก็บไฟล์ N วัน (default 14, ใส่ 0 = เก็บถาวร)</div>
          <div><code class="text-cyan-600 dark:text-cyan-400">--quiet-success</code> ไม่แสดงข้อความ success (เหมาะกับ cron)</div>
        </div>
      </div>
    </div>
  </div>

  {{-- ── BACKUP FILE LIST ────────────────────────────────────────── --}}
  <div id="backup-files-section" class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm overflow-hidden scroll-mt-20">
    <div class="px-5 py-3 border-b border-slate-100 dark:border-white/[0.06] bg-gradient-to-r from-emerald-50/50 to-transparent dark:from-emerald-500/5 flex items-center justify-between flex-wrap gap-2">
      <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
        <i class="bi bi-archive-fill text-emerald-500"></i>
        ไฟล์ backup ทั้งหมด
        @if(count($backupFiles) > 0)
          <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
            {{ count($backupFiles) }}
          </span>
        @endif
      </h2>
      @if($totalBytes > 0)
      <span class="text-xs text-slate-500 dark:text-slate-400">
        รวม
        @if($totalBytes >= 1073741824) {{ number_format($totalBytes / 1073741824, 2) }} GB
        @elseif($totalBytes >= 1048576) {{ number_format($totalBytes / 1048576, 1) }} MB
        @else {{ number_format($totalBytes / 1024, 1) }} KB
        @endif
      </span>
      @endif
    </div>

    @if(count($backupFiles) > 0)
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-slate-50/50 dark:bg-slate-900/30">
            <th class="px-5 py-3 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">ชื่อไฟล์</th>
            <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">ประเภท</th>
            <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">ขนาด</th>
            <th class="px-4 py-3 text-left text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">สร้างเมื่อ</th>
            <th class="px-4 py-3 text-right text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">จัดการ</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-white/[0.04]">
          @foreach($backupFiles as $file)
            @php
              $name = $file['name'];
              $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
              if (str_starts_with($name, 'backup_full_')) {
                $typeIcon = 'bi-box-seam-fill';
                $typeGrad = 'from-emerald-500 to-teal-500';
                $typeBg   = 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300';
                $typeLabel = 'ทั้งหมด';
              } elseif (str_starts_with($name, 'backup_files_')) {
                $typeIcon = 'bi-file-earmark-zip-fill';
                $typeGrad = 'from-amber-500 to-orange-500';
                $typeBg   = 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300';
                $typeLabel = 'โปรเจกต์';
              } elseif ($ext === 'sql' || str_starts_with($name, 'backup_')) {
                $typeIcon = 'bi-database-fill-down';
                $typeGrad = 'from-indigo-500 to-violet-500';
                $typeBg   = 'bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300';
                $typeLabel = 'ฐานข้อมูล';
              } else {
                $typeIcon = 'bi-file-earmark';
                $typeGrad = 'from-slate-400 to-slate-500';
                $typeBg   = 'bg-slate-100 text-slate-600 dark:bg-slate-700/50 dark:text-slate-300';
                $typeLabel = 'อื่นๆ';
              }
              // Highlight files created in the last 60 seconds — the one
              // the user just created via the form button.
              // Compare Unix timestamps directly to avoid timezone issues
              // (Carbon::createFromTimestamp returns UTC, now() uses APP_TZ).
              $isFresh = (time() - $file['modified']->timestamp) < 60;
            @endphp
          <tr class="{{ $isFresh ? 'bg-gradient-to-r from-emerald-50 via-emerald-50/60 to-transparent dark:from-emerald-500/15 dark:via-emerald-500/8 ring-2 ring-emerald-300 dark:ring-emerald-500/40 ring-inset' : 'hover:bg-slate-50/70 dark:hover:bg-slate-700/20' }} transition group">
            <td class="px-5 py-3">
              <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-lg bg-gradient-to-br {{ $typeGrad }} text-white flex items-center justify-center shrink-0 shadow-sm">
                  <i class="bi {{ $typeIcon }} text-sm"></i>
                </div>
                <code class="font-mono text-xs text-slate-700 dark:text-slate-200 truncate">{{ $name }}</code>
                @if($isFresh)
                  <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-gradient-to-r from-emerald-500 to-teal-500 text-white shadow-md animate-pulse">
                    <i class="bi bi-stars text-[8px]"></i> ใหม่!
                  </span>
                @endif
              </div>
            </td>
            <td class="px-4 py-3">
              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold {{ $typeBg }}">
                {{ $typeLabel }}
              </span>
            </td>
            <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400 font-mono">
              @if($file['size'] >= 1073741824) {{ number_format($file['size'] / 1073741824, 2) }} GB
              @elseif($file['size'] >= 1048576) {{ number_format($file['size'] / 1048576, 1) }} MB
              @else {{ number_format($file['size'] / 1024, 1) }} KB
              @endif
            </td>
            <td class="px-4 py-3 text-xs text-slate-500 dark:text-slate-400">
              <div>{{ $file['modified']->format('d/m/Y H:i') }}</div>
              <div class="text-[10px] opacity-60">{{ $file['modified']->diffForHumans() }}</div>
            </td>
            <td class="px-4 py-3 text-right whitespace-nowrap">
              <a href="{{ route('admin.settings.backup.download', ['filename' => $name]) }}"
                 class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold
                        bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300
                        hover:bg-emerald-100 dark:hover:bg-emerald-500/20 transition">
                <i class="bi bi-download"></i> ดาวน์โหลด
              </a>
              <form method="POST" action="{{ route('admin.settings.backup.delete', ['filename' => $name]) }}" class="inline-block ml-1"
                    onsubmit="return confirm('ลบ {{ $name }}? ลบแล้วไม่สามารถกู้คืนได้');">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold
                               bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300
                               hover:bg-rose-100 dark:hover:bg-rose-500/20 transition">
                  <i class="bi bi-trash"></i> ลบ
                </button>
              </form>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @else
    <div class="p-12 text-center">
      <div class="inline-flex w-16 h-16 rounded-2xl bg-gradient-to-br from-slate-100 to-slate-200 dark:from-slate-700 dark:to-slate-800 text-slate-400 dark:text-slate-500 items-center justify-center mb-3">
        <i class="bi bi-archive text-3xl"></i>
      </div>
      <p class="text-slate-700 dark:text-slate-300 font-semibold mb-1">ยังไม่มีไฟล์ backup</p>
      <p class="text-xs text-slate-500 dark:text-slate-400">เลือกประเภทด้านบนเพื่อสำรองครั้งแรก หรือรอ auto-backup ตอน 03:00 น.</p>
    </div>
    @endif
  </div>

  {{-- ── RESTORE INSTRUCTIONS ────────────────────────────────────── --}}
  <details class="bg-white dark:bg-slate-800 border border-slate-200/60 dark:border-white/[0.06] rounded-2xl shadow-sm group">
    <summary class="px-5 py-3 cursor-pointer flex items-center justify-between font-semibold text-sm text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/30 rounded-2xl transition">
      <span class="flex items-center gap-2">
        <i class="bi bi-arrow-counterclockwise text-cyan-500"></i>
        วิธีกู้คืนจาก backup
      </span>
      <i class="bi bi-chevron-down text-xs transition-transform group-open:rotate-180"></i>
    </summary>
    <div class="px-5 pb-5 pt-1 space-y-3 text-sm">
      <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-4 border border-slate-200 dark:border-white/[0.04]">
        <div class="font-semibold text-slate-700 dark:text-slate-200 mb-2 flex items-center gap-2">
          <span class="w-6 h-6 rounded-full bg-indigo-500 text-white text-xs font-bold flex items-center justify-center">1</span>
          กู้คืน Database
        </div>
        @if($isPg)
        <pre class="text-[11px] font-mono bg-slate-900 dark:bg-black/40 text-emerald-300 p-3 rounded-lg overflow-x-auto"><code>psql -U {{ config('database.connections.pgsql.username', 'postgres') }} -d {{ $dbName }} -f backup_{{ $dbName }}_YYYY-MM-DD.sql</code></pre>
        @else
        <pre class="text-[11px] font-mono bg-slate-900 dark:bg-black/40 text-emerald-300 p-3 rounded-lg overflow-x-auto"><code>mysql -u {{ config('database.connections.mysql.username', 'root') }} -p {{ $dbName }} &lt; backup_{{ $dbName }}_YYYY-MM-DD.sql</code></pre>
        @endif
      </div>
      <div class="rounded-xl bg-slate-50 dark:bg-slate-900/50 p-4 border border-slate-200 dark:border-white/[0.04]">
        <div class="font-semibold text-slate-700 dark:text-slate-200 mb-2 flex items-center gap-2">
          <span class="w-6 h-6 rounded-full bg-amber-500 text-white text-xs font-bold flex items-center justify-center">2</span>
          กู้คืน Project files (จาก ZIP)
        </div>
        <pre class="text-[11px] font-mono bg-slate-900 dark:bg-black/40 text-emerald-300 p-3 rounded-lg overflow-x-auto"><code>unzip backup_files_YYYY-MM-DD.zip -d /var/www/myproject
cd /var/www/myproject
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan storage:link
php artisan optimize:clear</code></pre>
      </div>
      <div class="rounded-xl bg-emerald-50 dark:bg-emerald-500/10 p-4 border border-emerald-200 dark:border-emerald-500/30">
        <div class="font-semibold text-emerald-700 dark:text-emerald-300 mb-2 flex items-center gap-2">
          <span class="w-6 h-6 rounded-full bg-emerald-500 text-white text-xs font-bold flex items-center justify-center">3</span>
          Full backup (วิธีง่ายที่สุด — ย้าย server)
        </div>
        <p class="text-xs text-emerald-700 dark:text-emerald-200 leading-relaxed">
          ใช้ <code class="px-1.5 py-0.5 rounded bg-white dark:bg-emerald-900/40 font-mono">backup_full_*.zip</code> ที่มีทั้ง <code class="px-1.5 py-0.5 rounded bg-white dark:bg-emerald-900/40 font-mono">database.sql</code> + ไฟล์โครงการในไฟล์เดียว unzip → import DB → composer install → ใช้ได้เลย
        </p>
      </div>
    </div>
  </details>

</div>

{{-- Progress overlay --}}
<div id="backupLoadingOverlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm hidden z-50 items-center justify-center">
  <div class="bg-white dark:bg-slate-800 rounded-2xl p-8 max-w-sm mx-4 text-center shadow-2xl border border-slate-200 dark:border-white/[0.06]">
    <div class="flex justify-center mb-4">
      <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-emerald-500 via-teal-500 to-cyan-500 flex items-center justify-center shadow-lg shadow-emerald-500/30 animate-pulse">
        <i class="bi bi-cloud-arrow-up-fill text-white text-3xl"></i>
      </div>
    </div>
    <h3 class="font-bold text-lg text-slate-800 dark:text-slate-100 mb-1">กำลังสำรองข้อมูล…</h3>
    <p class="text-slate-500 dark:text-slate-400 text-sm" id="backupLoadingMsg">กรุณารอสักครู่ — อย่าปิดหน้านี้</p>
    <div class="mt-4 flex justify-center">
      <div class="w-3/4 h-1 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
        <div class="h-full bg-gradient-to-r from-emerald-500 to-cyan-500 rounded-full" style="width:100%; animation: progress 1.5s ease-in-out infinite;"></div>
      </div>
    </div>
    <p class="text-slate-400 dark:text-slate-500 text-xs mt-3">การสำรองไฟล์ใหญ่อาจใช้เวลา 1-5 นาที</p>
  </div>
</div>

<style>
@keyframes progress {
  0% { transform: translateX(-100%); }
  100% { transform: translateX(100%); }
}
</style>

<script>
function confirmBackup(formEl, msg) {
  if (!confirm(msg)) return false;
  const overlay = document.getElementById('backupLoadingOverlay');
  if (overlay) {
    overlay.classList.remove('hidden');
    overlay.classList.add('flex');
  }
  return true;
}
</script>
@endsection
