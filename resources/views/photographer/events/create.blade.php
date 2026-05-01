@extends('layouts.photographer')

@section('title', 'สร้างอีเวนต์ใหม่')

@section('content')
@include('photographer.partials.page-hero', [
  'icon'     => 'bi-plus-circle',
  'eyebrow'  => 'การทำงาน',
  'title'    => 'สร้างอีเวนต์ใหม่',
  'subtitle' => 'ตั้งชื่อ · วันที่ · ราคาต่อภาพ · ตั้งค่าการเข้าถึง',
  'actions'  => '<a href="'.route('photographer.events.index').'" class="pg-btn-ghost"><i class="bi bi-arrow-left"></i> กลับ</a>',
])

<div class="pg-card">
  <div class="p-5">
    <form action="{{ route('photographer.events.store') }}" method="POST" enctype="multipart/form-data">
      @csrf
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">ชื่ออีเวนต์ <span class="text-red-500">*</span></label>
          <input type="text" name="name" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('name') border-red-500 @enderror" value="{{ old('name') }}" required>
          @error('name')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1.5">สถานที่</label>
          <input type="text" name="location" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('location') border-red-500 @enderror" value="{{ old('location') }}">
          @error('location')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">รายละเอียด</label>
          <textarea name="description" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('description') border-red-500 @enderror" rows="3">{{ old('description') }}</textarea>
          @error('description')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
        </div>
        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">วันที่ถ่าย <span class="text-red-500">*</span></label>
            <input type="date" name="shoot_date" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('shoot_date') border-red-500 @enderror" value="{{ old('shoot_date') }}" required>
            @error('shoot_date')
              <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
              ราคาต่อภาพ (THB) <span class="text-red-500">*</span>
            </label>
            <input type="number" name="price_per_photo"
                   class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('price_per_photo') border-red-500 @enderror"
                   value="{{ old('price_per_photo', $minPrice ?? 100) }}"
                   step="0.01"
                   min="{{ $minPrice ?? 100 }}"
                   required>
            <p class="text-gray-500 text-xs mt-1">
              ขั้นต่ำ: <span class="font-semibold">{{ number_format($minPrice ?? 100, 2) }}</span> บาท/ภาพ
            </p>
            @error('price_per_photo')
              <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i>{{ $message }}</p>
            @enderror
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">การมองเห็น</label>
            <select name="visibility" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 @error('visibility') border-red-500 @enderror">
              <option value="public" {{ old('visibility') === 'public' ? 'selected' : '' }}>สาธารณะ</option>
              <option value="private" {{ old('visibility') === 'private' ? 'selected' : '' }}>ส่วนตัว</option>
              <option value="unlisted" {{ old('visibility') === 'unlisted' ? 'selected' : '' }}>ไม่แสดงในรายการ</option>
            </select>
            @error('visibility')
              <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
          </div>
        </div>
        <div class="md:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-2">สถานะ</label>
          {{--
            Radio-card status picker — replaces the dropdown so users
            can SEE all four options at once with their meaning, instead
            of guessing what the difference between "active" / "published"
            is. The controller validates `in:draft,active,published,closed`,
            so all four must be present here.

            Tier-gate note: Free / Creator photographers will get any
            sellable status downgraded to 'draft' on the server side
            (with a flash). The hint below the picker telegraphs that.
          --}}
          <div class="grid grid-cols-2 lg:grid-cols-4 gap-2">
            @php
              $current = old('status', 'draft');
              // Pre-baked static class strings per status. Tailwind v4
              // does static analysis on blade files (`@source` scan in
              // app.css) so dynamic class interpolation like
              // `border-{$color}-500` would be invisible to the compiler
              // and never end up in the built CSS. Each option below
              // has its full Tailwind classes spelled out so the
              // scanner picks them up.
              $statusOptions = [
                ['value'=>'draft','icon'=>'bi-pencil-square','label'=>'ร่าง','hint'=>'บันทึกชั่วคราว · ยังไม่เผยแพร่',
                 'card'=>'hover:border-amber-300 peer-checked:border-amber-500 peer-checked:bg-amber-50 peer-focus:ring-amber-200',
                 'icon_color'=>'text-amber-600'],
                ['value'=>'active','icon'=>'bi-broadcast','label'=>'เปิดขาย','hint'=>'ลูกค้าซื้อรูปได้ทันที',
                 'card'=>'hover:border-emerald-300 peer-checked:border-emerald-500 peer-checked:bg-emerald-50 peer-focus:ring-emerald-200',
                 'icon_color'=>'text-emerald-600'],
                ['value'=>'published','icon'=>'bi-megaphone','label'=>'เผยแพร่','hint'=>'แสดงสาธารณะ · ลิสต์ในหน้าค้นหา',
                 'card'=>'hover:border-blue-300 peer-checked:border-blue-500 peer-checked:bg-blue-50 peer-focus:ring-blue-200',
                 'icon_color'=>'text-blue-600'],
                ['value'=>'closed','icon'=>'bi-archive','label'=>'ปิดงาน','hint'=>'จบงานแล้ว · ไม่รับลูกค้าใหม่',
                 'card'=>'hover:border-slate-300 peer-checked:border-slate-500 peer-checked:bg-slate-50 peer-focus:ring-slate-200',
                 'icon_color'=>'text-slate-600'],
              ];
            @endphp
            @foreach($statusOptions as $opt)
              <label class="cursor-pointer block">
                <input type="radio" name="status" value="{{ $opt['value'] }}"
                       class="peer sr-only"
                       {{ $current === $opt['value'] ? 'checked' : '' }}>
                <div class="h-full p-3 rounded-xl border-2 border-gray-200 bg-white transition
                            peer-checked:shadow-sm peer-focus:ring-2 {{ $opt['card'] }}">
                  <div class="flex items-center gap-1.5 mb-1">
                    <i class="bi {{ $opt['icon'] }} {{ $opt['icon_color'] }}"></i>
                    <span class="font-bold text-sm text-slate-800">{{ $opt['label'] }}</span>
                  </div>
                  <p class="text-[11px] text-slate-500 leading-snug">{{ $opt['hint'] }}</p>
                </div>
              </label>
            @endforeach
          </div>
          @error('status')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
          @enderror
          <p class="text-[11px] text-slate-500 mt-2">
            <i class="bi bi-info-circle"></i>
            Free/Creator tier จะถูก save เป็น <strong>ร่าง</strong> เสมอ — กรอก PromptPay ในโปรไฟล์เพื่อปลดล็อก
          </p>
        </div>

        {{-- ── Extra event info (collapsible — SEO + customer UX) ── --}}
        @include('photographer.events._extra_info_card', ['event' => null])

        {{-- ── AI features (Face Search) ──────────────────────── --}}
        @include('photographer.events._face_search_card', [
            'checked' => old('face_search_enabled', '1') === '1',
        ])
      </div>
      <div class="mt-6">
        <button type="submit" class="bg-gradient-to-br from-indigo-600 to-indigo-700 text-white font-medium px-6 py-2.5 rounded-lg border-none inline-flex items-center gap-1 transition hover:shadow-lg">
          <i class="bi bi-check-lg mr-1"></i> สร้างอีเวนต์
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
