@props([
  'src'  => null,    // Image URL (avatar / profile photo)
  'name'  => '',     // Full name for initials (e.g. "John Doe" → "JD")
  'size'  => 'md',    // xs(24) sm(32) md(40) lg(56) xl(80) 2xl(120)
  'rounded' => 'circle',  // circle | rounded | square
  'ring'  => false,    // Show decorative ring border
  'status' => null,    // online | away | offline | null
  'userId' => null,    // For deterministic color (optional)
  'badge' => null,    // Text badge overlay (e.g. role icon)
  'class' => '',     // Additional CSS classes
])

@php
  // ── Size Map ──
  $sizeMap = [
    'xs' => ['px' => 24, 'font' => '0.65rem', 'status' => 7, 'ring' => 2],
    'sm' => ['px' => 32, 'font' => '0.75rem', 'status' => 8, 'ring' => 2],
    'md' => ['px' => 40, 'font' => '0.9rem', 'status' => 10, 'ring' => 2],
    'lg' => ['px' => 56, 'font' => '1.15rem', 'status' => 12, 'ring' => 3],
    'xl' => ['px' => 80, 'font' => '1.8rem', 'status' => 14, 'ring' => 3],
    '2xl' => ['px' => 120,'font' => '2.8rem', 'status' => 16, 'ring' => 4],
  ];
  $s = $sizeMap[$size] ?? $sizeMap['md'];

  // ── Deterministic Color Palette ── (Professionally curated — WCAG AA on white text)
  $palette = [
    ['#6366f1','#4f46e5'], // Indigo
    ['#8b5cf6','#7c3aed'], // Violet
    ['#ec4899','#db2777'], // Pink
    ['#f59e0b','#d97706'], // Amber
    ['#10b981','#059669'], // Emerald
    ['#3b82f6','#2563eb'], // Blue
    ['#ef4444','#dc2626'], // Red
    ['#14b8a6','#0d9488'], // Teal
    ['#f97316','#ea580c'], // Orange
    ['#06b6d4','#0891b2'], // Cyan
    ['#a855f7','#9333ea'], // Purple
    ['#84cc16','#65a30d'], // Lime
  ];

  // Pick color deterministically by userId or name hash
  $seed = $userId ?? (mb_strlen($name) > 0 ? array_sum(array_map('ord', str_split(mb_substr($name, 0, 6)))) : 0);
  $color = $palette[$seed % count($palette)];

  // ── Build Initials ──
  $initials = '';
  $cleanName = trim($name);
  if ($cleanName) {
    $parts = preg_split('/[\s]+/', $cleanName);
    if (count($parts) >= 2) {
      $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
    } else {
      $initials = mb_strtoupper(mb_substr($cleanName, 0, 1));
    }
  }
  if (!$initials) $initials = '?';

  // For small sizes, only show 1 initial to avoid cramming
  if (in_array($size, ['xs', 'sm']) && mb_strlen($initials) > 1) {
    $initials = mb_substr($initials, 0, 1);
  }

  // ── Border-Radius ──
  $radiusMap = ['circle' => '50%', 'rounded' => '12px', 'square' => '4px'];
  $radius = $radiusMap[$rounded] ?? '50%';

  // ── Resolve relative paths to URLs ──
  // Social-login avatars come in as full https:// URLs (Google, LINE, FB).
  // Our own uploads come in as relative paths on whichever driver was the
  // upload target at the time — `photographers/5/profile/abc.webp` might
  // live on R2, S3, or local public depending on when it was uploaded.
  //
  // Route through StorageManager::resolveUrl(): it probes the primary
  // driver first (R2 in production), falls back to other drivers for
  // legacy rows, and passes http(s):// / data: / absolute paths through
  // unchanged. Previously hardcoded `Storage::disk('public')->url($src)`
  // silently broke every avatar on R2 — browser would render a /storage/…
  // path that 404s because the file isn't on the local disk.
  if (!empty($src) && $src !== 'null'
      && !str_starts_with($src, 'http')
      && !str_starts_with($src, '/')
      && !str_starts_with($src, 'data:')) {
    try {
      $resolved = app(\App\Services\StorageManager::class)->resolveUrl($src);
      if (!empty($resolved)) $src = $resolved;
    } catch (\Throwable) { /* fall through; browser will 404 just like before */ }
  }

  // ── Has valid image? ──
  $hasImage = !empty($src) && $src !== 'null';
@endphp

<div class="ua-avatar {{ $class }}"
   style="width:{{ $s['px'] }}px;height:{{ $s['px'] }}px;border-radius:{{ $radius }};{{ $ring ? "box-shadow:0 0 0 {$s['ring']}px #fff, 0 0 0 " . ($s['ring'] + 2) . "px {$color[0]};" : '' }}"
   data-initials="{{ $initials }}"
   data-color-from="{{ $color[0] }}"
   data-color-to="{{ $color[1] }}"
   {{ $attributes->except(['src','name','size','rounded','ring','status','userId','badge','class']) }}>

  {{-- Image layer --}}
  @if($hasImage)
    <img class="ua-avatar-img"
       src="{{ $src }}"
       alt="{{ $cleanName }}"
       loading="lazy"
       style="border-radius:{{ $radius }};"
       onerror="this.parentElement.classList.add('ua-fallback');this.style.display='none';">
  @endif

  {{-- Initials layer (shown when no image or image fails) --}}
  <span class="ua-avatar-initials"
     style="font-size:{{ $s['font'] }};background:linear-gradient(135deg,{{ $color[0] }},{{ $color[1] }});border-radius:{{ $radius }};">
    {{ $initials }}
  </span>

  {{-- Status indicator --}}
  @if($status)
    <span class="ua-avatar-status ua-status-{{ $status }}"
       style="width:{{ $s['status'] }}px;height:{{ $s['status'] }}px;border-width:{{ $s['status'] > 10 ? 3 : 2 }}px;"></span>
  @endif

  {{-- Badge overlay --}}
  @if($badge)
    <span class="ua-avatar-badge">{!! $badge !!}</span>
  @endif
</div>
