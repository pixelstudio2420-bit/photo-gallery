@extends('layouts.admin')

@section('title', $announcement->exists ? 'แก้ไขประกาศ' : 'สร้างประกาศ')

@section('content')
<div class="max-w-5xl mx-auto">

  {{-- ═══════════════════════════════════════════════════════════════
       HEADER — back link + title
       ═══════════════════════════════════════════════════════════════ --}}
  <div class="mb-6">
    <a href="{{ route('admin.announcements.index') }}"
       class="inline-flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition mb-2">
      <i class="bi bi-arrow-left"></i> กลับไปหน้ารายการประกาศ
    </a>
    <div class="flex items-center gap-3">
      <div class="h-11 w-11 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center shadow-md shadow-indigo-500/30">
        <i class="bi {{ $announcement->exists ? 'bi-pencil-square' : 'bi-plus-lg' }} text-xl"></i>
      </div>
      <div>
        <h1 class="text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-100">
          {{ $announcement->exists ? 'แก้ไขประกาศ' : 'สร้างประกาศใหม่' }}
        </h1>
        <p class="text-xs text-slate-500 dark:text-slate-400">
          {{ $announcement->exists ? 'อัปเดตเนื้อหา / ตั้งค่าการเผยแพร่' : 'แจ้งข่าวสาร / banner / popup ให้ผู้ใช้' }}
        </p>
      </div>
    </div>
  </div>

  {{-- Flash + validation errors --}}
  @if(session('success'))
    <div class="mb-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-800 dark:text-emerald-200 px-4 py-3 text-sm">
      <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
    </div>
  @endif
  @if($errors->any())
    <div class="mb-4 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 px-4 py-3 text-sm">
      <p class="font-semibold mb-1"><i class="bi bi-exclamation-triangle-fill"></i> กรอกข้อมูลไม่ครบ:</p>
      <ul class="list-disc pl-5 space-y-0.5">
        @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
      </ul>
    </div>
  @endif

  {{-- ═══════════════════════════════════════════════════════════════
       MAIN FORM
       ═══════════════════════════════════════════════════════════════ --}}
  <form method="POST" enctype="multipart/form-data"
        action="{{ $announcement->exists ? route('admin.announcements.update', $announcement->id) : route('admin.announcements.store') }}">
    @csrf
    @if($announcement->exists) @method('PUT') @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

      {{-- ─── LEFT COLUMN — content + cover ─── --}}
      <div class="lg:col-span-2 space-y-5">

        {{-- Content card --}}
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center">
              <i class="bi bi-card-text"></i>
            </div>
            <div>
              <h3 class="font-semibold text-slate-900 dark:text-slate-100 text-sm">เนื้อหาหลัก</h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">หัวข้อ / เนื้อหา / รูปหน้าปก</p>
            </div>
          </div>

          <div class="p-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                หัวข้อ <span class="text-rose-500">*</span>
              </label>
              <input type="text" name="title" required maxlength="200"
                     value="{{ old('title', $announcement->title) }}"
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                Slug
                <span class="text-slate-400 font-normal text-[10px]">(URL — ปล่อยว่างเพื่อ auto-generate)</span>
              </label>
              <input type="text" name="slug" pattern="[a-z0-9\-]+" maxlength="220"
                     value="{{ old('slug', $announcement->slug) }}"
                     placeholder="new-promo-may-2026"
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                เกริ่นนำ <span class="text-slate-400 font-normal text-[10px]">(ข้อความสั้นๆ แสดงในรายการ)</span>
              </label>
              <textarea name="excerpt" rows="2" maxlength="300"
                        placeholder="สรุปข่าว 1-2 บรรทัด"
                        class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">{{ old('excerpt', $announcement->excerpt) }}</textarea>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                เนื้อหา <span class="text-slate-400 font-normal text-[10px]">(รองรับ HTML/Markdown — p, h2, h3, ul/ol, strong, em, a, br)</span>
              </label>
              <textarea name="body" rows="12"
                        class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 font-mono focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">{{ old('body', $announcement->body) }}</textarea>
            </div>
          </div>
        </div>

        {{-- Cover image card --}}
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
              <i class="bi bi-image"></i>
            </div>
            <div>
              <h3 class="font-semibold text-slate-900 dark:text-slate-100 text-sm">รูปหน้าปก</h3>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">JPG/PNG/WebP สูงสุด 5MB</p>
            </div>
          </div>

          <div class="p-5 space-y-3">
            @if($announcement->cover_image_path)
              @php
                $coverUrl = '';
                try { $coverUrl = app(\App\Services\StorageManager::class)->resolveUrl($announcement->cover_image_path); } catch (\Throwable) {}
              @endphp
              @if($coverUrl)
                <div class="relative inline-block rounded-xl overflow-hidden border border-slate-200 dark:border-white/10">
                  <img src="{{ $coverUrl }}" alt="" class="max-w-xs h-auto block">
                </div>
                <label class="flex items-center gap-2 cursor-pointer text-xs text-rose-600 dark:text-rose-400">
                  <input type="checkbox" name="remove_cover" value="1" class="rounded border-slate-300 text-rose-500 focus:ring-rose-500">
                  ลบรูปหน้าปกปัจจุบัน
                </label>
              @endif
            @endif

            <input type="file" name="cover_image" accept="image/*"
                   class="block w-full text-sm text-slate-900 dark:text-slate-100
                          file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0
                          file:text-sm file:font-medium file:bg-indigo-100 file:text-indigo-700
                          dark:file:bg-indigo-500/20 dark:file:text-indigo-300
                          hover:file:bg-indigo-200 dark:hover:file:bg-indigo-500/30 transition">
          </div>
        </div>

        {{-- Attachments (existing only — separate form below for adding new) --}}
        @if($announcement->exists && $announcement->attachments->count() > 0)
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center justify-between gap-3">
            <div class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center">
                <i class="bi bi-images"></i>
              </div>
              <div>
                <h3 class="font-semibold text-slate-900 dark:text-slate-100 text-sm">รูปประกอบ</h3>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $announcement->attachments->count() }} รูป</p>
              </div>
            </div>
          </div>

          <div class="p-5 grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach($announcement->attachments as $att)
              @php
                $attUrl = '';
                try { $attUrl = app(\App\Services\StorageManager::class)->resolveUrl($att->image_path); } catch (\Throwable) {}
              @endphp
              <div class="relative group">
                <img src="{{ $attUrl }}" alt="" class="w-full h-32 object-cover rounded-xl border border-slate-200 dark:border-white/10">
                <form method="POST" action="{{ route('admin.announcements.attachments.destroy', $att->id) }}"
                      class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition"
                      onsubmit="return confirm('ลบรูปนี้?')">
                  @csrf @method('DELETE')
                  <button type="submit"
                          class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-rose-500 hover:bg-rose-600 text-white text-xs shadow-md transition">
                    <i class="bi bi-x-lg"></i>
                  </button>
                </form>
                @if($att->caption)
                  <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5 line-clamp-1">{{ $att->caption }}</p>
                @endif
              </div>
            @endforeach
          </div>
        </div>
        @endif

      </div>

      {{-- ─── RIGHT COLUMN — publishing settings + CTA + submit ─── --}}
      <div class="lg:col-span-1 space-y-5">

        {{-- Publishing settings --}}
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center">
              <i class="bi bi-broadcast"></i>
            </div>
            <h3 class="font-semibold text-slate-900 dark:text-slate-100 text-sm">การเผยแพร่</h3>
          </div>

          <div class="p-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">สถานะ</label>
              <select name="status" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                @foreach(['draft' => '📝 ฉบับร่าง', 'published' => '📡 เผยแพร่', 'archived' => '📦 เก็บแล้ว'] as $v => $l)
                  <option value="{{ $v }}" @selected(old('status', $announcement->status)===$v)>{{ $l }}</option>
                @endforeach
              </select>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">กลุ่มเป้าหมาย</label>
              <select name="audience" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                <option value="all" @selected(old('audience', $announcement->audience)==='all')>👥 ทุกคน (ช่าง + ลูกค้า)</option>
                <option value="photographer" @selected(old('audience', $announcement->audience)==='photographer')>📷 เฉพาะช่างภาพ</option>
                <option value="customer" @selected(old('audience', $announcement->audience)==='customer')>🛍️ เฉพาะลูกค้า</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">ความสำคัญ</label>
              <select name="priority" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                <option value="low"    @selected(old('priority', $announcement->priority)==='low')>🔽 ต่ำ</option>
                <option value="normal" @selected(old('priority', $announcement->priority)==='normal')>● ปกติ</option>
                <option value="high"   @selected(old('priority', $announcement->priority)==='high')>🔺 สูง (ขึ้นบนสุด)</option>
              </select>
            </div>

            <div class="space-y-2 pt-2 border-t border-slate-200 dark:border-white/10">
              <label class="flex items-start gap-2.5 cursor-pointer p-3 rounded-xl border border-slate-200 dark:border-white/10 hover:bg-slate-50 dark:hover:bg-white/5 transition">
                <input type="hidden" name="is_pinned" value="0">
                <input type="checkbox" name="is_pinned" value="1" @checked(old('is_pinned', $announcement->is_pinned))
                       class="mt-0.5 rounded border-slate-300 text-rose-500 focus:ring-rose-500">
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium text-slate-900 dark:text-slate-100">📌 ปักหมุดบนสุด</div>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400">แสดงก่อนประกาศอื่นๆ ในรายการ</div>
                </div>
              </label>

              <label class="flex items-start gap-2.5 cursor-pointer p-3 rounded-xl border border-slate-200 dark:border-white/10 hover:bg-slate-50 dark:hover:bg-white/5 transition">
                <input type="hidden" name="show_as_popup" value="0">
                <input type="checkbox" name="show_as_popup" value="1" @checked(old('show_as_popup', $announcement->show_as_popup ?? false))
                       class="mt-0.5 rounded border-slate-300 text-purple-500 focus:ring-purple-500">
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-medium text-slate-900 dark:text-slate-100">🎯 แสดงเป็น popup</div>
                  <div class="text-[11px] text-slate-500 dark:text-slate-400">เด้ง popup กลางจอ (ปกติ = แสดงในรายการ)</div>
                </div>
              </label>
            </div>
          </div>
        </div>

        {{-- Geo targeting + schedule --}}
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 flex items-center justify-center">
              <i class="bi bi-geo-alt"></i>
            </div>
            <h3 class="font-semibold text-slate-900 dark:text-slate-100 text-sm">การกำหนดเป้าหมาย</h3>
          </div>

          <div class="p-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                จำกัดเฉพาะจังหวัด
                <span class="text-slate-400 font-normal text-[10px] block mt-0.5">เว้นว่าง = แสดงทั่วประเทศ</span>
              </label>
              @include('partials.province-select', [
                  'name'        => 'target_province_id',
                  'selected'    => old('target_province_id', $announcement->target_province_id ?? null),
                  'placeholder' => '— ทั่วประเทศ —',
              ])
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                เริ่มแสดง
                <span class="text-slate-400 font-normal text-[10px] block mt-0.5">เว้นว่าง = แสดงทันทีเมื่อเผยแพร่</span>
              </label>
              <input type="datetime-local" name="starts_at"
                     value="{{ old('starts_at', $announcement->starts_at?->format('Y-m-d\TH:i')) }}"
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            </div>

            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                สิ้นสุด
                <span class="text-slate-400 font-normal text-[10px] block mt-0.5">เว้นว่าง = แสดงตลอดไป</span>
              </label>
              <input type="datetime-local" name="ends_at"
                     value="{{ old('ends_at', $announcement->ends_at?->format('Y-m-d\TH:i')) }}"
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            </div>
          </div>
        </div>

        {{-- CTA card --}}
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-pink-500/10 text-pink-600 dark:text-pink-400 flex items-center justify-center">
              <i class="bi bi-arrow-up-right-square"></i>
            </div>
            <h3 class="font-semibold text-slate-900 dark:text-slate-100 text-sm">ปุ่ม Call-to-Action</h3>
          </div>

          <div class="p-5 space-y-4">
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">ข้อความบนปุ่ม</label>
              <input type="text" name="cta_label" maxlength="60"
                     value="{{ old('cta_label', $announcement->cta_label) }}"
                     placeholder="เช่น สมัครเลย"
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            </div>
            <div>
              <label class="block text-xs font-medium text-slate-700 dark:text-slate-300 mb-1.5">ลิงก์</label>
              <input type="url" name="cta_url" maxlength="500"
                     value="{{ old('cta_url', $announcement->cta_url) }}"
                     placeholder="https://..."
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            </div>
          </div>
        </div>

        {{-- Submit row --}}
        <div class="space-y-2">
          <button type="submit"
                  class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white px-4 py-3 text-sm font-semibold shadow-md shadow-indigo-500/25 transition">
            <i class="bi bi-check-lg"></i>
            {{ $announcement->exists ? 'บันทึกการเปลี่ยนแปลง' : 'สร้างประกาศ' }}
          </button>
          <a href="{{ route('admin.announcements.index') }}"
             class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-white dark:bg-white/5 border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/10 px-4 py-2.5 text-sm font-medium transition">
            ยกเลิก
          </a>
        </div>
      </div>
    </div>
  </form>

  {{-- ═══════════════════════════════════════════════════════════════
       ATTACHMENTS UPLOAD — separate form to avoid main-form interference
       (only on edit, after the announcement exists)
       ═══════════════════════════════════════════════════════════════ --}}
  @if($announcement->exists)
    <div class="mt-5 bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
      <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center">
          <i class="bi bi-cloud-upload"></i>
        </div>
        <div>
          <h3 class="font-semibold text-slate-900 dark:text-slate-100 text-sm">เพิ่มรูปประกอบใหม่</h3>
          <p class="text-[11px] text-slate-500 dark:text-slate-400">รูปประกอบจะแสดงในหน้ารายละเอียดประกาศ</p>
        </div>
      </div>

      <form method="POST" enctype="multipart/form-data" action="{{ route('admin.announcements.attachments.store', $announcement->id) }}" class="p-5">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
          <div class="md:col-span-6">
            <input type="file" name="image" accept="image/*" required
                   class="block w-full text-sm text-slate-900 dark:text-slate-100
                          file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0
                          file:text-sm file:font-medium file:bg-purple-100 file:text-purple-700
                          dark:file:bg-purple-500/20 dark:file:text-purple-300
                          hover:file:bg-purple-200 dark:hover:file:bg-purple-500/30 transition">
          </div>
          <div class="md:col-span-4">
            <input type="text" name="caption" maxlength="200" placeholder="คำบรรยายภาพ (ไม่บังคับ)"
                   class="w-full px-3 py-2 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-2 focus:ring-purple-500 transition">
          </div>
          <div class="md:col-span-2">
            <button type="submit"
                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-purple-500 hover:bg-purple-600 text-white px-3 py-2 text-sm font-medium shadow-sm transition">
              <i class="bi bi-upload"></i> อัปโหลด
            </button>
          </div>
        </div>
      </form>
    </div>
  @endif
</div>
@endsection
