@extends('layouts.admin')

@section('title', 'แก้ไขหน้า — ' . $page->slug)

@section('content')
<div class="max-w-5xl mx-auto pb-16">

  <div class="flex items-center justify-between gap-3 mb-6">
    <div>
      <a href="{{ route('admin.pseo.pages') }}" class="text-xs text-slate-500 hover:text-indigo-500"><i class="bi bi-arrow-left"></i> Back to Pages</a>
      <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white mt-1">แก้ไขหน้า Landing</h1>
      <code class="text-xs text-slate-500 dark:text-slate-400">/{{ $page->slug }}</code>
    </div>
    <a href="{{ $page->url() }}" target="_blank" class="px-3 py-2 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 text-sm">
      <i class="bi bi-box-arrow-up-right"></i> ดูหน้าจริง
    </a>
  </div>

  @if(session('success'))
    <div class="mb-4 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 text-sm">
      <i class="bi bi-check-circle-fill mr-1"></i>{{ session('success') }}
    </div>
  @endif

  <form method="POST" action="{{ route('admin.pseo.page-update', $page) }}" class="space-y-5">
    @csrf
    @method('PUT')

    {{-- ════ Card 1: SEO Meta ════ --}}
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 bg-gradient-to-r from-indigo-50 to-violet-50 dark:from-indigo-500/[0.08] dark:to-violet-500/[0.08]">
        <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
          <i class="bi bi-search text-indigo-500"></i>SEO Meta (สำหรับ Google)
        </h3>
      </div>
      <div class="p-5 space-y-4">
        <div>
          <label class="block text-xs font-semibold mb-1.5">Title (ข้างใน &lt;title&gt; tag)</label>
          <input type="text" name="title" value="{{ old('title', $page->title) }}" required maxlength="500"
                 class="w-full px-3 py-2 rounded-lg text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10">
          <p class="text-[10px] text-slate-400 mt-1">แสดงในผลค้นหา Google + tab browser • ความยาวที่แนะนำ 50-60 ตัวอักษร</p>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1.5">Meta Description</label>
          <textarea name="meta_description" required maxlength="500" rows="2"
                    class="w-full px-3 py-2 rounded-lg text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10">{{ old('meta_description', $page->meta_description) }}</textarea>
          <p class="text-[10px] text-slate-400 mt-1">คำอธิบายในผล Google • 150-160 ตัวอักษร</p>
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1.5">H1 (หัวข้อบนหน้า)</label>
          <input type="text" name="h1" value="{{ old('h1', $page->h1) }}" maxlength="500"
                 class="w-full px-3 py-2 rounded-lg text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10">
        </div>
      </div>
    </div>

    {{-- ════ Card 2: Visual Theme ════ --}}
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 bg-gradient-to-r from-rose-50 to-pink-50 dark:from-rose-500/[0.08] dark:to-pink-500/[0.08]">
        <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
          <i class="bi bi-palette text-rose-500"></i>Visual Theme
        </h3>
        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">สีและธีมให้เข้ากับเนื้อหาของหน้า</p>
      </div>
      <div class="p-5 space-y-4">

        {{-- Theme picker — visual gradient previews --}}
        <div>
          <label class="block text-xs font-semibold mb-2">เลือกธีม</label>
          @php
            $themes = [
              'default'     => ['label' => 'ค่าเริ่มต้น',     'gradient' => 'from-indigo-500 via-violet-500 to-purple-600',  'icon' => 'bi-globe'],
              'wedding'     => ['label' => 'งานแต่งงาน',     'gradient' => 'from-rose-400 via-pink-500 to-fuchsia-600',     'icon' => 'bi-heart-fill'],
              'sport'       => ['label' => 'กีฬา',           'gradient' => 'from-cyan-500 via-blue-500 to-indigo-600',       'icon' => 'bi-trophy-fill'],
              'concert'     => ['label' => 'คอนเสิร์ต',      'gradient' => 'from-violet-600 via-purple-600 to-fuchsia-700',  'icon' => 'bi-music-note'],
              'corporate'   => ['label' => 'องค์กร',         'gradient' => 'from-slate-600 via-slate-700 to-zinc-800',       'icon' => 'bi-building'],
              'portrait'    => ['label' => 'พอร์ตเทรต',     'gradient' => 'from-amber-500 via-orange-500 to-red-500',        'icon' => 'bi-person-fill'],
              'festival'    => ['label' => 'เทศกาล',         'gradient' => 'from-yellow-400 via-orange-500 to-pink-500',     'icon' => 'bi-stars'],
              'photography' => ['label' => 'ถ่ายภาพ',        'gradient' => 'from-emerald-500 via-teal-600 to-cyan-700',      'icon' => 'bi-camera'],
            ];
            $current = old('theme', $page->theme ?? 'default');
          @endphp
          <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            @foreach($themes as $key => $t)
              <label class="cursor-pointer">
                <input type="radio" name="theme" value="{{ $key }}" {{ $current === $key ? 'checked' : '' }} class="sr-only peer">
                <div class="rounded-xl overflow-hidden border-2 {{ $current === $key ? 'border-indigo-500' : 'border-transparent' }} peer-checked:border-indigo-500 hover:border-indigo-300 transition">
                  <div class="h-16 bg-gradient-to-br {{ $t['gradient'] }} flex items-center justify-center">
                    <i class="bi {{ $t['icon'] }} text-white text-xl"></i>
                  </div>
                  <div class="px-2 py-1.5 text-center text-xs font-semibold text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800">
                    {{ $t['label'] }}
                  </div>
                </div>
              </label>
            @endforeach
          </div>
        </div>

        {{-- Hero image URL --}}
        <div>
          <label class="block text-xs font-semibold mb-1.5">Hero Image URL <span class="text-slate-400 font-normal">(optional — ใส่ wallpaper บน hero)</span></label>
          <input type="text" name="hero_image" value="{{ old('hero_image', $page->hero_image) }}" maxlength="500"
                 placeholder="/storage/landing/bangkok-wedding.jpg"
                 class="w-full px-3 py-2 rounded-lg text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 font-mono">
          @if($page->hero_image)
            <div class="mt-2 max-w-xs">
              <img src="{{ asset($page->hero_image) }}" alt="Hero preview" class="rounded-lg border border-slate-200 max-h-32">
            </div>
          @endif
        </div>

        {{-- OG Image URL (separate from hero) --}}
        <div>
          <label class="block text-xs font-semibold mb-1.5">OG Image URL <span class="text-slate-400 font-normal">(สำหรับแชร์ Facebook/LINE)</span></label>
          <input type="text" name="og_image" value="{{ old('og_image', $page->og_image) }}" maxlength="500"
                 class="w-full px-3 py-2 rounded-lg text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 font-mono">
        </div>
      </div>
    </div>

    {{-- ════ Card 3: Body Content ════ --}}
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 bg-gradient-to-r from-amber-50 to-yellow-50 dark:from-amber-500/[0.08] dark:to-yellow-500/[0.08]">
        <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
          <i class="bi bi-file-text text-amber-500"></i>เนื้อหา Body
        </h3>
      </div>
      <div class="p-5">
        <textarea name="body_html" rows="10"
                  class="w-full px-3 py-2 rounded-lg text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 font-sans leading-relaxed">{{ old('body_html', $page->body_html) }}</textarea>
        <p class="text-[10px] text-slate-400 mt-1">ใส่ข้อความ paragraph ปกติได้ — ระบบจะ auto-format ให้</p>
      </div>
    </div>

    {{-- ════ Card 4: Call-to-Action ════ --}}
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-500/[0.08] dark:to-teal-500/[0.08]">
        <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
          <i class="bi bi-megaphone text-emerald-500"></i>ปุ่ม CTA (Call-to-Action)
        </h3>
        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">ปุ่มหลักให้ผู้ใช้คลิกต่อ — ปล่อยว่าง = ใช้ default ของระบบ</p>
      </div>
      <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-semibold mb-1.5">CTA Text</label>
          <input type="text" name="cta_text" value="{{ old('cta_text', $page->cta_text) }}" maxlength="100"
                 placeholder="เช่น: จองช่างภาพเลย"
                 class="w-full px-3 py-2 rounded-lg text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10">
        </div>
        <div>
          <label class="block text-xs font-semibold mb-1.5">CTA URL</label>
          <input type="text" name="cta_url" value="{{ old('cta_url', $page->cta_url) }}" maxlength="500"
                 placeholder="/events หรือ https://..."
                 class="w-full px-3 py-2 rounded-lg text-sm bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 font-mono">
        </div>
      </div>
    </div>

    {{-- ════ Card 5: Section Toggles ════ --}}
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 bg-gradient-to-r from-blue-50 to-cyan-50 dark:from-blue-500/[0.08] dark:to-cyan-500/[0.08]">
        <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
          <i class="bi bi-toggles text-blue-500"></i>เปิด/ปิด ส่วนต่างๆ บนหน้านี้
        </h3>
      </div>
      <div class="p-5 grid grid-cols-1 md:grid-cols-2 gap-3">
        <label class="flex items-center gap-3 p-3 rounded-lg bg-slate-50 dark:bg-white/[0.03] cursor-pointer hover:bg-slate-100 dark:hover:bg-white/[0.06]">
          <input type="hidden" name="show_stats" value="0">
          <input type="checkbox" name="show_stats" value="1" {{ ($page->show_stats ?? true) ? 'checked' : '' }} class="w-4 h-4">
          <div class="flex-1">
            <div class="text-sm font-semibold">Stats Badges</div>
            <div class="text-[10px] text-slate-500">แสดงตัวเลข (X รายการ, Y ช่างภาพ, Z views) บน hero</div>
          </div>
        </label>
        <label class="flex items-center gap-3 p-3 rounded-lg bg-slate-50 dark:bg-white/[0.03] cursor-pointer hover:bg-slate-100 dark:hover:bg-white/[0.06]">
          <input type="hidden" name="show_gallery" value="0">
          <input type="checkbox" name="show_gallery" value="1" {{ ($page->show_gallery ?? true) ? 'checked' : '' }} class="w-4 h-4">
          <div class="flex-1">
            <div class="text-sm font-semibold">Related Items Grid</div>
            <div class="text-[10px] text-slate-500">แสดง grid อีเวนต์/ช่างภาพที่เกี่ยวข้อง</div>
          </div>
        </label>
        <label class="flex items-center gap-3 p-3 rounded-lg bg-slate-50 dark:bg-white/[0.03] cursor-pointer hover:bg-slate-100 dark:hover:bg-white/[0.06]">
          <input type="hidden" name="show_related" value="0">
          <input type="checkbox" name="show_related" value="1" {{ ($page->show_related ?? true) ? 'checked' : '' }} class="w-4 h-4">
          <div class="flex-1">
            <div class="text-sm font-semibold">Internal Links Block</div>
            <div class="text-[10px] text-slate-500">ลิงก์ไปหน้า sibling pages (helps SEO)</div>
          </div>
        </label>
        <label class="flex items-center gap-3 p-3 rounded-lg bg-slate-50 dark:bg-white/[0.03] cursor-pointer hover:bg-slate-100 dark:hover:bg-white/[0.06]">
          <input type="hidden" name="show_faq" value="0">
          <input type="checkbox" name="show_faq" value="1" {{ ($page->show_faq ?? false) ? 'checked' : '' }} class="w-4 h-4">
          <div class="flex-1">
            <div class="text-sm font-semibold">FAQ Section <span class="text-[9px] px-1 py-0.5 bg-amber-100 text-amber-700 rounded">SEO+</span></div>
            <div class="text-[10px] text-slate-500">FAQ แบบ accordion + Schema.org rich result</div>
          </div>
        </label>
      </div>
    </div>

    {{-- ════ Card 6: Extra Sections (FAQ, testimonials, custom blocks) ════ --}}
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden"
         x-data="{ sections: {{ json_encode(old('extra_sections', $page->extra_sections ?? [])) }}, addSection() { this.sections.push({type:'text', title:'', body:''}); }, removeSection(i) { this.sections.splice(i,1); } }">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-500/[0.08] dark:to-pink-500/[0.08] flex items-center justify-between">
        <h3 class="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
          <i class="bi bi-stars text-purple-500"></i>Extra Sections (FAQ / Testimonial / Custom)
        </h3>
        <button type="button" @click="addSection()" class="px-3 py-1.5 bg-purple-500 hover:bg-purple-600 text-white text-xs font-semibold rounded-lg">
          <i class="bi bi-plus-lg"></i> เพิ่ม
        </button>
      </div>
      <div class="p-5 space-y-3">
        <template x-for="(s, i) in sections" :key="i">
          <div class="rounded-lg border border-slate-200 dark:border-white/10 p-3 bg-slate-50/50 dark:bg-white/[0.02]">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-2">
              <select :name="'extra_sections['+i+'][type]'" x-model="s.type"
                      class="px-2 py-1.5 text-xs rounded bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10">
                <option value="text">Text</option>
                <option value="faq">FAQ</option>
                <option value="testimonial">Testimonial</option>
                <option value="callout">Callout</option>
              </select>
              <input type="text" :name="'extra_sections['+i+'][title]'" x-model="s.title" placeholder="หัวข้อ"
                     class="md:col-span-2 px-2 py-1.5 text-xs rounded bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10">
            </div>
            <textarea :name="'extra_sections['+i+'][body]'" x-model="s.body" rows="3" placeholder="เนื้อหา"
                      class="w-full px-2 py-1.5 text-xs rounded bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10"></textarea>
            <button type="button" @click="removeSection(i)" class="mt-2 text-[10px] text-red-500 hover:text-red-700">
              <i class="bi bi-trash"></i> ลบส่วนนี้
            </button>
          </div>
        </template>
        <div x-show="sections.length === 0" class="text-center text-xs text-slate-400 py-6">
          ยังไม่มี extra sections — กดปุ่ม "เพิ่ม" เพื่อใส่ FAQ / Testimonial / Custom blocks
        </div>
      </div>
    </div>

    {{-- ════ Card 7: Status Toggles ════ --}}
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-sm p-5">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <label class="flex items-center gap-3 p-3 rounded-lg bg-emerald-50 dark:bg-emerald-500/10 cursor-pointer">
          <input type="hidden" name="is_published" value="0">
          <input type="checkbox" name="is_published" value="1" {{ $page->is_published ? 'checked' : '' }} class="w-4 h-4">
          <div>
            <div class="text-sm font-semibold text-emerald-700 dark:text-emerald-300"><i class="bi bi-globe mr-1"></i>Published</div>
            <div class="text-[10px] text-slate-500">เปิดให้ public + sitemap เห็น</div>
          </div>
        </label>
        <label class="flex items-center gap-3 p-3 rounded-lg bg-amber-50 dark:bg-amber-500/10 cursor-pointer">
          <input type="hidden" name="is_locked" value="0">
          <input type="checkbox" name="is_locked" value="1" {{ $page->is_locked ? 'checked' : '' }} class="w-4 h-4">
          <div>
            <div class="text-sm font-semibold text-amber-700 dark:text-amber-300"><i class="bi bi-lock-fill mr-1"></i>Locked</div>
            <div class="text-[10px] text-slate-500">ห้าม regenerate ทับ (ปกป้องการแก้ไข)</div>
          </div>
        </label>
      </div>
    </div>

    {{-- Submit --}}
    <div class="flex justify-end gap-2 sticky bottom-0 bg-gradient-to-t from-slate-50 dark:from-slate-900 via-slate-50 dark:via-slate-900 to-transparent pt-4 pb-2 -mx-4 px-4">
      <a href="{{ route('admin.pseo.pages') }}" class="px-4 py-2 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 text-sm">ยกเลิก</a>
      <a href="{{ $page->url() }}" target="_blank" class="px-4 py-2 rounded-lg bg-blue-50 dark:bg-blue-500/10 text-blue-600 text-sm"><i class="bi bi-eye"></i> Preview</a>
      <button class="px-5 py-2 rounded-lg bg-gradient-to-r from-indigo-600 to-violet-600 text-white text-sm font-semibold shadow-md"><i class="bi bi-save"></i> บันทึก</button>
    </div>
  </form>

</div>
@endsection
