@props([
  'src'    => null,     // Cover image URL
  'name'   => '',      // Event name (for initials & alt text)
  'eventId'  => null,     // For deterministic color
  'height'  => '200px',   // CSS height (200px, 180px, etc.)
  'size'   => null,     // Preset: card(200px) | small(180px) | thumb(48px) | thumb-sm(44px) | hero(300px)
  'rounded'  => null,     // Override border-radius (e.g. "10px", "16px")
  'icon'   => null,     // Custom icon class (default: auto-select)
  'date'   => null,     // shoot_date — shown as overlay badge
  'class'   => '',      // Additional CSS classes
  'imgClass' => '',      // Image CSS class
])

@php
  // ── Size Presets ──
  $presets = [
    'card'   => ['h' => '200px', 'font' => '2.2rem', 'sub' => '0.75rem', 'radius' => null,  'iconSize' => '1.6rem', 'isThumb' => false],
    'small'  => ['h' => '180px', 'font' => '2rem',  'sub' => '0.7rem', 'radius' => null,  'iconSize' => '1.4rem', 'isThumb' => false],
    'hero'   => ['h' => '300px', 'font' => '3rem',  'sub' => '0.9rem', 'radius' => null,  'iconSize' => '2rem',  'isThumb' => false],
    'thumb'  => ['h' => '48px', 'font' => '1rem',  'sub' => null,    'radius' => '10px', 'iconSize' => '1rem',  'isThumb' => true],
    'thumb-sm' => ['h' => '44px', 'font' => '0.9rem', 'sub' => null,    'radius' => '10px', 'iconSize' => '0.9rem', 'isThumb' => true],
  ];

  // Auto-detect preset from height if not specified
  if (!$size) {
    $size = match($height) {
      '200px'      => 'card',
      '180px'      => 'small',
      '300px'      => 'hero',
      '48px'      => 'thumb',
      '44px'      => 'thumb-sm',
      default      => 'card',
    };
  }
  $p = $presets[$size] ?? $presets['card'];
  $finalHeight = $p['h'];
  $finalRadius = $rounded ?? $p['radius'];
  $isThumb = $p['isThumb'];

  // ── Deterministic Color Palette (warm + vibrant, curated for event covers) ──
  $palette = [
    ['#6366f1','#4f46e5','rgba(99,102,241,0.15)'],  // Indigo
    ['#8b5cf6','#7c3aed','rgba(139,92,246,0.15)'],  // Violet
    ['#ec4899','#db2777','rgba(236,72,153,0.15)'],   // Pink
    ['#f59e0b','#d97706','rgba(245,158,11,0.15)'],   // Amber
    ['#10b981','#059669','rgba(16,185,129,0.15)'],   // Emerald
    ['#3b82f6','#2563eb','rgba(59,130,246,0.15)'],   // Blue
    ['#ef4444','#dc2626','rgba(239,68,68,0.15)'],   // Red
    ['#14b8a6','#0d9488','rgba(20,184,166,0.15)'],   // Teal
    ['#f97316','#ea580c','rgba(249,115,22,0.15)'],   // Orange
    ['#06b6d4','#0891b2','rgba(6,182,212,0.15)'],   // Cyan
    ['#a855f7','#9333ea','rgba(168,85,247,0.15)'],   // Purple
    ['#84cc16','#65a30d','rgba(132,204,22,0.15)'],   // Lime
  ];

  $seed = $eventId ?? (mb_strlen($name) > 0 ? array_sum(array_map('ord', str_split(mb_substr($name, 0, 8)))) : 0);
  $color = $palette[$seed % count($palette)];

  // ── Build Short Name / Initials ──
  $cleanName = trim($name);
  $initials = '';
  $shortName = '';

  if ($cleanName) {
    // Remove common prefixes/suffixes for cleaner display
    $parts = preg_split('/[\s\-_]+/', $cleanName);
    $parts = array_filter($parts, fn($p) => mb_strlen($p) > 0);
    $parts = array_values($parts);

    if ($isThumb) {
      // Thumbnail: 1-2 initials only
      if (count($parts) >= 2) {
        $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
      } else {
        $initials = mb_strtoupper(mb_substr($cleanName, 0, 2));
      }
    } else {
      // Card: Build abbreviated name (max ~20 chars)
      if (count($parts) >= 2) {
        $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
      } else {
        $initials = mb_strtoupper(mb_substr($cleanName, 0, 2));
      }
      // Short name for subtitle
      $shortName = mb_strlen($cleanName) > 24 ? mb_substr($cleanName, 0, 22) . '...' : $cleanName;
    }
  }
  if (!$initials) $initials = '?';

  // ── Decorative icon ──
  $displayIcon = $icon ?? ($isThumb ? 'bi-calendar-event' : 'bi-camera');

  // ── Has valid image? ──
  $hasImage = !empty($src) && $src !== 'null';
@endphp

@if($isThumb)
  {{-- ===== THUMBNAIL MODE (48px / 44px) ===== --}}
  <div class="ec-cover ec-thumb {{ $class }}"
     style="width:{{ $finalHeight }};height:{{ $finalHeight }};{{ $finalRadius ? "border-radius:{$finalRadius};" : '' }}"
     data-event-id="{{ $eventId }}">
    @if($hasImage)
      <img class="ec-cover-img"
         src="{{ $src }}"
         alt="{{ $cleanName }}"
         loading="lazy"
         style="{{ $finalRadius ? "border-radius:{$finalRadius};" : '' }}"
         onerror="this.parentElement.classList.add('ec-fallback');this.style.display='none';">
    @endif
    <div class="ec-cover-fallback text-center"
       style="background:linear-gradient(135deg,{{ $color[0] }},{{ $color[1] }});{{ $finalRadius ? "border-radius:{$finalRadius};" : '' }}">
      <span style="font-size:{{ $p['font'] }};font-weight:700;color:#fff;letter-spacing:0.02em;text-shadow:0 1px 2px rgba(0,0,0,0.15);">
        {{ $initials }}
      </span>
    </div>
  </div>
@else
  {{-- ===== CARD / HERO MODE ===== --}}
  <div class="ec-cover {{ $class }}"
     style="height:{{ $finalHeight }};{{ $finalRadius ? "border-radius:{$finalRadius};" : '' }}"
     data-event-id="{{ $eventId }}">
    @if($hasImage)
      <img class="ec-cover-img w-full object-cover {{ $imgClass }}"
         src="{{ $src }}"
         alt="{{ $cleanName }}"
         loading="lazy"
         style="height:{{ $finalHeight }};transition:transform 0.5s;{{ $finalRadius ? "border-radius:{$finalRadius};" : '' }}"
         onerror="this.parentElement.classList.add('ec-fallback');this.style.display='none';">
    @endif
    <div class="ec-cover-fallback text-center"
       style="height:{{ $finalHeight }};background:linear-gradient(135deg,{{ $color[0] }} 0%,{{ $color[1] }} 100%);{{ $finalRadius ? "border-radius:{$finalRadius};" : '' }}">
      {{-- Decorative pattern overlay --}}
      <div class="ec-pattern"></div>
      {{-- Decorative icon (top-right, faded) --}}
      <i class="bi {{ $displayIcon }} ec-deco-icon"></i>
      {{-- Content --}}
      <div class="ec-content">
        <span class="ec-initials" style="font-size:{{ $p['font'] }};">{{ $initials }}</span>
        @if($shortName)
          <span class="ec-name" style="font-size:{{ $p['sub'] }};">{{ $shortName }}</span>
        @endif
      </div>
    </div>

    @if($date)
      <div class="absolute bottom-2 right-2">
        <span class="inline-block rounded-lg px-2.5 py-1 text-xs font-medium text-white" style="background:rgba(0,0,0,0.55);-webkit-backdrop-filter:blur(4px);backdrop-filter:blur(4px);">
          <i class="bi bi-calendar3 mr-1"></i>{{ $date instanceof \Carbon\Carbon ? $date->format('d M Y') : $date }}
        </span>
      </div>
    @endif
  </div>
@endif
