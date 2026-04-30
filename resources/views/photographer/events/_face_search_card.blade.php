{{-- ════════════════════════════════════════════════════════════════════
     Face Search per-event toggle — shared between create.blade.php and
     edit.blade.php. The caller passes:

        $checked  bool — initial checked state (true = enabled)

     Why a partial:
       Both forms render exactly the same toggle + use-case guide, and
       any future copy change should land in both forms simultaneously.
       Inlining duplicated would let one form drift from the other on
       the next edit.

     What this renders:
       1. The peer-checked toggle card (fancy checkbox styling)
       2. A two-column "ควรเปิด vs ควรปิด" decision guide so the
          photographer can pick correctly without reading external docs
       3. A live status pill that updates as the toggle flips

     Form field name: `face_search_enabled` (1 = on, omitted = off via
     boolean cast in controller). Validation + persistence wiring:
     EventController::store() / update() at lines 195 + 327 + 269 + 423.
═══════════════════════════════════════════════════════════════════════ --}}
<div class="md:col-span-2">
  <label class="block text-sm font-medium text-gray-700 mb-2">
    ฟีเจอร์ AI สำหรับลูกค้า
  </label>

  {{-- The toggle card itself — peer-checked drives visual state. --}}
  <label class="flex items-start gap-3 p-4 rounded-xl border-2 border-gray-200 hover:border-indigo-300 cursor-pointer bg-white transition has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50/40">
    <input type="checkbox" name="face_search_enabled" value="1"
           class="peer sr-only"
           {{ ($checked ?? true) ? 'checked' : '' }}>
    <div class="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center bg-violet-100 text-violet-600 peer-checked:bg-violet-200 peer-checked:text-violet-700 transition">
      <i class="bi bi-search-heart text-xl"></i>
    </div>
    <div class="flex-1 min-w-0">
      <div class="flex items-center gap-2 flex-wrap">
        <span class="font-bold text-sm text-slate-800">ค้นหาด้วยใบหน้า (Face Search)</span>
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">
          <i class="bi bi-magic"></i> AI
        </span>
      </div>
      <p class="text-xs text-slate-500 mt-1 leading-relaxed">
        ลูกค้าอัปโหลด selfie แล้ว AI หาทุกรูปที่มีใบหน้าตรงกัน — ดูคู่มือเลือกใช้ด้านล่าง
      </p>
      <div class="mt-2 inline-flex items-center gap-1.5 text-[11px] font-semibold">
        {{-- Live status — flips visually with the checkbox via
             peer-checked. No JS required. --}}
        <span class="hidden peer-checked:inline text-indigo-600">
          <i class="bi bi-check-circle-fill"></i> เปิดใช้งาน — ลูกค้าค้นใบหน้าได้
        </span>
        <span class="inline peer-checked:hidden text-slate-400">
          <i class="bi bi-circle"></i> ปิด — ซ่อนปุ่มค้นใบหน้าจากลูกค้า
        </span>
      </div>
    </div>
  </label>

  {{-- ── Use-case decision guide ──────────────────────────────────────
       Two-column compare so the photographer picks correctly at a
       glance. Mobile stacks the columns. The headline doubles as
       light social proof: most operators DO want this on for big
       public events because face search drives self-service
       purchases.
       ─────────────────────────────────────────────────────────── --}}
  <div class="mt-3 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-white/10 overflow-hidden">
    <div class="px-4 py-2.5 bg-slate-100/80 dark:bg-slate-900/40 border-b border-slate-200 dark:border-white/5 flex items-center gap-2">
      <i class="bi bi-lightbulb-fill text-amber-500"></i>
      <span class="text-xs font-bold text-slate-700 dark:text-slate-200">
        เลือกอย่างไหนดี? — แนะนำตามประเภทงาน
      </span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 divide-y sm:divide-y-0 sm:divide-x divide-slate-200 dark:divide-white/10">
      {{-- ── ✅ ควรเปิด — งานสาธารณะ ────────────────────────── --}}
      <div class="p-4">
        <div class="flex items-center gap-2 mb-2.5">
          <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-emerald-100 text-emerald-700">
            <i class="bi bi-check-lg"></i>
          </span>
          <span class="text-sm font-bold text-emerald-700 dark:text-emerald-300">ควร เปิด เมื่อ</span>
          <span class="ml-auto text-[10px] font-bold uppercase tracking-wider text-emerald-600 bg-emerald-100 dark:bg-emerald-500/15 dark:text-emerald-300 px-2 py-0.5 rounded-full">
            เพิ่มยอดขาย
          </span>
        </div>
        @php
          $shouldEnable = [
            ['icon' => 'bi-trophy-fill',          'tone' => 'text-amber-500',  'label' => 'งานวิ่ง / มาราธอน',         'why' => 'ผู้เข้าร่วมหลักพันคน — ลูกค้าหาตัวเองในรูปยาก ถ้าไม่มี AI'],
            ['icon' => 'bi-music-note-beamed',    'tone' => 'text-pink-500',   'label' => 'คอนเสิร์ต / เทศกาลดนตรี',   'why' => 'งานสาธารณะ ภาพเยอะ — face search = self-service ขายดี'],
            ['icon' => 'bi-mortarboard-fill',     'tone' => 'text-indigo-500', 'label' => 'รับปริญญา (สนามใหญ่)',      'why' => 'นักศึกษาหลายคณะ ลูกค้าเลือกเฉพาะตัวเองได้ทันที'],
            ['icon' => 'bi-stars',                'tone' => 'text-violet-500', 'label' => 'อีเวนต์เปิดสำหรับสาธารณะ',   'why' => 'ใครก็ดูได้อยู่แล้ว → ใช้ AI เพิ่ม conversion'],
            ['icon' => 'bi-building',             'tone' => 'text-slate-500',  'label' => 'งานเปิดตัวสินค้า / Town Hall', 'why' => 'พนักงานทุกแผนกหารูปตัวเองได้สบาย'],
          ];
        @endphp
        <ul class="space-y-2.5">
          @foreach($shouldEnable as $row)
            <li class="flex items-start gap-2.5 text-xs">
              <i class="bi {{ $row['icon'] }} {{ $row['tone'] }} mt-0.5 shrink-0 text-base"></i>
              <div class="min-w-0">
                <div class="font-semibold text-slate-800 dark:text-slate-100">{{ $row['label'] }}</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">{{ $row['why'] }}</div>
              </div>
            </li>
          @endforeach
        </ul>
      </div>

      {{-- ── ❌ ควรปิด — งานละเอียดอ่อน ───────────────────── --}}
      <div class="p-4">
        <div class="flex items-center gap-2 mb-2.5">
          <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg bg-rose-100 text-rose-700">
            <i class="bi bi-shield-lock-fill"></i>
          </span>
          <span class="text-sm font-bold text-rose-700 dark:text-rose-300">ควร ปิด เมื่อ</span>
          <span class="ml-auto text-[10px] font-bold uppercase tracking-wider text-rose-600 bg-rose-100 dark:bg-rose-500/15 dark:text-rose-300 px-2 py-0.5 rounded-full">
            ปกป้องความเป็นส่วนตัว
          </span>
        </div>
        @php
          $shouldDisable = [
            ['icon' => 'bi-emoji-smile-fill',     'tone' => 'text-amber-500', 'label' => 'งานเด็ก / อนุบาล / โรงเรียน',  'why' => 'PDPA + กฎหมายคุ้มครองเยาวชน — ห้ามให้คนแปลกหน้าค้นใบหน้าเด็ก'],
            ['icon' => 'bi-heart-fill',           'tone' => 'text-rose-500',  'label' => 'งานแต่ง / Pre-Wedding',         'why' => 'เจ้าภาพไม่อยากให้คนทั่วไปค้นแขกในงาน'],
            ['icon' => 'bi-briefcase-fill',       'tone' => 'text-slate-500', 'label' => 'งาน VIP / Corporate (ปิดประชุม)', 'why' => 'ผู้บริหาร/แขก VIP ขอความเป็นส่วนตัวสูง'],
            ['icon' => 'bi-cake2-fill',           'tone' => 'text-pink-500',  'label' => 'งานวันเกิด / งานครอบครัว',     'why' => 'งานส่วนตัว — เจ้าของงานควบคุมการแชร์เอง'],
            ['icon' => 'bi-incognito',            'tone' => 'text-violet-500','label' => 'งานเซ็นสัญญา NDA / Confidential', 'why' => 'ลูกค้าระบุเงื่อนไขห้ามค้นภาพ'],
          ];
        @endphp
        <ul class="space-y-2.5">
          @foreach($shouldDisable as $row)
            <li class="flex items-start gap-2.5 text-xs">
              <i class="bi {{ $row['icon'] }} {{ $row['tone'] }} mt-0.5 shrink-0 text-base"></i>
              <div class="min-w-0">
                <div class="font-semibold text-slate-800 dark:text-slate-100">{{ $row['label'] }}</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">{{ $row['why'] }}</div>
              </div>
            </li>
          @endforeach
        </ul>
      </div>
    </div>

    {{-- Footer hint — single-line tip the photographer can act on
         without reading the full grid above. --}}
    <div class="px-4 py-2.5 bg-amber-50 dark:bg-amber-500/10 border-t border-amber-200 dark:border-amber-500/20 flex items-center gap-2">
      <i class="bi bi-info-circle-fill text-amber-600 dark:text-amber-400 shrink-0"></i>
      <p class="text-[11px] text-amber-800 dark:text-amber-200 leading-relaxed">
        <strong>ไม่แน่ใจ?</strong> เปิดไว้สำหรับงานสาธารณะ (วิ่ง · คอนเสิร์ต · รับปริญญา) ขายดีกว่า ~30%
        — ปิดเฉพาะงานที่เจ้าภาพระบุชัดว่า ห้ามใช้ AI ค้นใบหน้า เปลี่ยนได้ตลอดผ่านปุ่มแก้ไขอีเวนต์
      </p>
    </div>
  </div>
</div>
