{{--
  Reusable province dropdown
  ──────────────────────────
  Renders a single <select name="{name}"> with all 77 Thai provinces.
  Used by:
    • Profile edit page (user sets their own province)
    • Admin announcement form (geo-target dropdown)
    • Signup form (optional location capture)

  Variables (with defaults):
    $name          — input name attribute             (default: 'province_id')
    $selected      — currently selected province id   (default: null)
    $placeholder   — first option label               (default: '— เลือกจังหวัด —')
    $required      — render required attr             (default: false)
    $allowEmpty    — let user pick "ไม่ระบุ" → null  (default: true)
    $class         — extra CSS classes                (default: '')

  Caches the province list for 1 hour — provinces don't change often
  enough to bust DB on every page render.
--}}
@php
  $name        = $name        ?? 'province_id';
  $selected    = $selected    ?? null;
  $placeholder = $placeholder ?? '— เลือกจังหวัด —';
  $required    = $required    ?? false;
  $allowEmpty  = $allowEmpty  ?? true;
  $class       = $class       ?? '';

  // 77 provinces = ~3KB cached. Stays warm across the whole site.
  $provinces = \Illuminate\Support\Facades\Cache::remember(
      'thai_provinces_dropdown',
      3600,
      fn () => \DB::table('thai_provinces')->orderBy('name_th')->get(['id','name_th','name_en'])
  );

  // Group provinces by region for visual scanning. Same region map
  // as the rest of the marketplace (matches photographer onboarding).
  // Region IDs derived from province ID ranges per Thailand's
  // standard 6-region classification.
  $regionOf = function ($provinceId) {
      $id = (int) $provinceId;
      // Bangkok metropolitan
      if (in_array($id, [10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 70, 73, 74, 75, 77])) return 'กรุงเทพและปริมณฑล';
      // Northern
      if (in_array($id, [50, 51, 52, 53, 54, 55, 56, 57, 58, 63, 64, 65, 66, 67])) return 'ภาคเหนือ';
      // Northeastern (Isan)
      if (in_array($id, [30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 49])) return 'ภาคอีสาน';
      // Eastern
      if (in_array($id, [20, 21, 22, 23, 24, 25, 26, 27])) return 'ภาคตะวันออก';
      // Western
      if (in_array($id, [60, 61, 62, 71, 72, 76])) return 'ภาคตะวันตก';
      // Southern
      if (in_array($id, [80, 81, 82, 83, 84, 85, 86, 90, 91, 92, 93, 94, 95, 96])) return 'ภาคใต้';
      return 'อื่น ๆ';
  };

  $grouped = $provinces->groupBy(fn ($p) => $regionOf($p->id));
@endphp

<select name="{{ $name }}"
        @if($required) required @endif
        class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition {{ $class }}">
    @if($allowEmpty)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach($grouped as $region => $items)
        <optgroup label="{{ $region }}">
            @foreach($items as $p)
                <option value="{{ $p->id }}" @selected((int) $selected === (int) $p->id)>
                    {{ $p->name_th }}
                </option>
            @endforeach
        </optgroup>
    @endforeach
</select>
