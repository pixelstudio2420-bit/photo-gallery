@extends('layouts.admin')

@section('title', isset($event) ? 'แก้ไขอีเวนต์' : 'สร้างอีเวนต์')

@section('content')
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0">
    <i class="bi bi-{{ isset($event) ? 'pencil' : 'plus-lg' }} mr-2 text-indigo-500"></i>
    {{ isset($event) ? 'แก้ไขอีเวนต์' : 'สร้างอีเวนต์' }}
  </h4>
  <a href="{{ route('admin.events.index') }}" class="border border-gray-300 text-gray-600 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700 px-4 py-2 rounded-lg transition inline-flex items-center gap-1">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

{{-- Validation Errors --}}
@if($errors->any())
<div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 mb-4 dark:bg-red-500/10 dark:border-red-500/20 dark:text-red-400">
  <div class="flex items-center gap-2 mb-1">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <span class="font-semibold text-sm">พบข้อผิดพลาด กรุณาตรวจสอบข้อมูล</span>
  </div>
  <ul class="mb-0 text-sm list-disc list-inside">
    @foreach($errors->all() as $error)
    <li>{{ $error }}</li>
    @endforeach
  </ul>
</div>
@endif

<form method="POST"
      action="{{ isset($event) ? route('admin.events.update', $event->id) : route('admin.events.store') }}"
      enctype="multipart/form-data">
  @csrf
  @if(isset($event)) @method('PUT') @endif

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    {{-- ═══════════════════════════════════════
         Left Column (2/3)
         ═══════════════════════════════════════ --}}
    <div class="lg:col-span-2 space-y-4">

      {{-- Card 1: ข้อมูลอีเวนต์ --}}
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/[0.06]">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="mb-0 font-semibold text-sm">
            <i class="bi bi-calendar-event mr-1.5 text-indigo-500"></i>ข้อมูลอีเวนต์
          </h6>
        </div>
        <div class="p-5 space-y-4">
          {{-- ชื่ออีเวนต์ --}}
          <div>
            <label for="title" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
              ชื่ออีเวนต์ <span class="text-red-500">*</span>
            </label>
            <input type="text" name="title" id="title"
                   class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition @error('title') border-red-400 ring-1 ring-red-400 @enderror"
                   value="{{ old('title', $event->name ?? '') }}"
                   placeholder="ระบุชื่ออีเวนต์"
                   required>
            @error('title')
            <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i>{{ $message }}</p>
            @enderror
          </div>

          {{-- รายละเอียด --}}
          <div>
            <label for="description" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
              รายละเอียด
            </label>
            <textarea name="description" id="description" rows="4"
                      class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition @error('description') border-red-400 ring-1 ring-red-400 @enderror"
                      placeholder="อธิบายรายละเอียดอีเวนต์">{{ old('description', $event->description ?? '') }}</textarea>
            @error('description')
            <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i>{{ $message }}</p>
            @enderror
          </div>

          {{-- หมวดหมู่ + วันที่ --}}
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label for="category_id" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                หมวดหมู่ <span class="text-red-500">*</span>
              </label>
              <select name="category_id" id="category_id"
                      class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition @error('category_id') border-red-400 ring-1 ring-red-400 @enderror"
                      required>
                <option value="">-- เลือกหมวดหมู่ --</option>
                @foreach($categories as $cat)
                <option value="{{ $cat->id }}" {{ old('category_id', $event->category_id ?? '') == $cat->id ? 'selected' : '' }}>
                  {{ $cat->name }}
                </option>
                @endforeach
              </select>
              @error('category_id')
              <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i>{{ $message }}</p>
              @enderror
            </div>
            <div>
              <label for="event_date" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
                วันที่ถ่ายภาพ <span class="text-red-500">*</span>
              </label>
              <input type="date" name="event_date" id="event_date"
                     class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition @error('event_date') border-red-400 ring-1 ring-red-400 @enderror"
                     value="{{ old('event_date', isset($event) ? $event->shoot_date?->format('Y-m-d') : '') }}"
                     required>
              @error('event_date')
              <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i>{{ $message }}</p>
              @enderror
            </div>
          </div>
        </div>
      </div>

      {{-- Card 2: สถานที่ (Cascading Location Picker) --}}
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/[0.06]"
           x-data="{
              provinceId: {{ old('province_id', $event->province_id ?? 'null') }},
              districtId: {{ old('district_id', $event->district_id ?? 'null') }},
              subdistrictId: {{ old('subdistrict_id', $event->subdistrict_id ?? 'null') }},
              districts: {{ isset($districts) ? $districts->toJson() : '[]' }},
              subdistricts: {{ isset($subdistricts) ? $subdistricts->toJson() : '[]' }},
              async fetchDistricts() {
                  if (!this.provinceId) { this.districts = []; this.subdistricts = []; this.districtId = null; this.subdistrictId = null; return; }
                  const res = await fetch('{{ route("admin.api.locations.districts") }}?province_id=' + this.provinceId);
                  this.districts = await res.json();
                  this.districtId = null; this.subdistrictId = null; this.subdistricts = [];
              },
              async fetchSubdistricts() {
                  if (!this.districtId) { this.subdistricts = []; this.subdistrictId = null; return; }
                  const res = await fetch('{{ route("admin.api.locations.subdistricts") }}?district_id=' + this.districtId);
                  this.subdistricts = await res.json();
                  this.subdistrictId = null;
              }
           }">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="mb-0 font-semibold text-sm">
            <i class="bi bi-geo-alt mr-1.5 text-indigo-500"></i>สถานที่
          </h6>
        </div>
        <div class="p-5 space-y-4">
          {{-- Province / District / Subdistrict --}}
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            {{-- จังหวัด --}}
            <div>
              <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">จังหวัด</label>
              <select x-model="provinceId" @change="fetchDistricts()"
                      class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                <option value="">-- เลือกจังหวัด --</option>
                @foreach($provinces as $province)
                <option value="{{ $province->id }}">{{ $province->name_th }}</option>
                @endforeach
              </select>
              @error('province_id')
              <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i>{{ $message }}</p>
              @enderror
            </div>

            {{-- อำเภอ/เขต --}}
            <div>
              <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">อำเภอ/เขต</label>
              <select x-model="districtId" @change="fetchSubdistricts()"
                      class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                      :disabled="!provinceId">
                <option value="">-- เลือกอำเภอ/เขต --</option>
                <template x-for="d in districts" :key="d.id">
                  <option :value="d.id" x-text="d.name_th"></option>
                </template>
              </select>
              @error('district_id')
              <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i>{{ $message }}</p>
              @enderror
            </div>

            {{-- ตำบล/แขวง --}}
            <div>
              <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">ตำบล/แขวง</label>
              <select x-model="subdistrictId"
                      class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                      :disabled="!districtId">
                <option value="">-- เลือกตำบล/แขวง --</option>
                <template x-for="s in subdistricts" :key="s.id">
                  <option :value="s.id" x-text="s.name_th + (s.zip_code ? ' (' + s.zip_code + ')' : '')"></option>
                </template>
              </select>
              @error('subdistrict_id')
              <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i>{{ $message }}</p>
              @enderror
            </div>
          </div>

          {{-- Hidden inputs for form submission --}}
          <input type="hidden" name="province_id" :value="provinceId">
          <input type="hidden" name="district_id" :value="districtId">
          <input type="hidden" name="subdistrict_id" :value="subdistrictId">

          {{-- รายละเอียดสถานที่เพิ่มเติม --}}
          <div>
            <label for="location_detail" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
              รายละเอียดสถานที่เพิ่มเติม
            </label>
            <input type="text" name="location_detail" id="location_detail"
                   class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                   value="{{ old('location_detail', $event->location_detail ?? '') }}"
                   placeholder="เช่น ชื่อสถานที่, ซอย, ถนน">
            @error('location_detail')
            <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i>{{ $message }}</p>
            @enderror
          </div>
        </div>
      </div>

      {{-- Card 3: Google Drive Photos --}}
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/[0.06]">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="mb-0 font-semibold text-sm">
            <i class="bi bi-google mr-1.5 text-indigo-500"></i>Google Drive Photos
          </h6>
        </div>
        <div class="p-5 space-y-3">
          <div>
            <label for="drive_folder_url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
              Google Drive Folder URL or ID
            </label>
            <input type="text" name="drive_folder_url" id="drive_folder_url"
                   class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                   value="{{ old('drive_folder_url', $event->drive_folder_id ?? '') }}"
                   placeholder="https://drive.google.com/drive/folders/...">
            <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">Paste the Google Drive shared folder link</p>
          </div>
          @if(isset($event) && $event->drive_folder_id)
          <div class="bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20 text-blue-700 dark:text-blue-400 rounded-lg p-3 text-sm">
            <i class="bi bi-info-circle mr-1"></i> Current folder ID: <span class="font-mono">{{ $event->drive_folder_id }}</span>
            <br>Photos cached: {{ $event->photosCache->count() ?? 0 }}
          </div>
          @endif
        </div>
      </div>
    </div>

    {{-- ═══════════════════════════════════════
         Right Column (1/3) - การตั้งค่า
         ═══════════════════════════════════════ --}}
    <div class="lg:col-span-1">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-white/[0.06]">
        <div class="px-5 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="mb-0 font-semibold text-sm">
            <i class="bi bi-gear mr-1.5 text-indigo-500"></i>การตั้งค่า
          </h6>
        </div>
        <div class="p-5 space-y-4">

          {{-- สถานะ --}}
          <div>
            <label for="status" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">สถานะ</label>
            <select name="status" id="status"
                    class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
              @foreach(['draft' => 'Draft', 'active' => 'Active', 'published' => 'Published', 'inactive' => 'Inactive', 'archived' => 'Archived'] as $val => $label)
              <option value="{{ $val }}" {{ old('status', $event->status ?? 'draft') === $val ? 'selected' : '' }}>
                {{ $label }}
              </option>
              @endforeach
            </select>
            @error('status')
            <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i>{{ $message }}</p>
            @enderror
          </div>

          {{-- ช่างภาพ --}}
          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">ช่างภาพ</label>
            @if(isset($event) && $event->photographer_id)
            <input type="text" class="w-full px-4 py-2.5 border border-gray-200 dark:border-gray-600 rounded-lg text-sm bg-gray-50 dark:bg-slate-700 dark:text-gray-300" value="{{ $event->photographer->name ?? 'ID: ' . $event->photographer_id }}" readonly>
            <input type="hidden" name="photographer_id" value="{{ $event->photographer_id }}">
            @else
            <div class="bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-gray-600 rounded-lg px-4 py-2.5 text-sm text-gray-500 dark:text-gray-400">
              <i class="bi bi-person mr-1"></i> ระบุภายหลังได้
            </div>
            @endif
          </div>

          {{-- การมองเห็น --}}
          <div x-data="{ visibility: '{{ old('visibility', $event->visibility ?? 'public') }}' }">
            <label for="visibility" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">การมองเห็น</label>
            <select name="visibility" id="visibility" x-model="visibility"
                    class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
              <option value="public">Public</option>
              <option value="private">Private</option>
              <option value="password">Password Protected</option>
            </select>
            @error('visibility')
            <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i>{{ $message }}</p>
            @enderror

            {{-- Password field (shown only when visibility = password) --}}
            <div x-show="visibility === 'password'" x-transition x-cloak class="mt-2">
              <label for="event_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                รหัสผ่านอีเวนต์
              </label>
              <input type="text" name="event_password" id="event_password"
                     class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition"
                     value="{{ old('event_password', $event->event_password ?? '') }}"
                     placeholder="ระบุรหัสผ่าน">
              @error('event_password')
              <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i>{{ $message }}</p>
              @enderror
            </div>
          </div>

          {{-- ราคาต่อรูป --}}
          <div x-data="{ isFree: {{ old('is_free', $event->is_free ?? 0) ? 'true' : 'false' }} }">
            <label for="price_per_photo" class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">
              ราคาต่อรูป (THB)
            </label>
            <input type="number" name="price_per_photo" id="price_per_photo"
                   class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition disabled:opacity-50 disabled:cursor-not-allowed @error('price_per_photo') border-red-400 ring-1 ring-red-400 @enderror"
                   value="{{ old('price_per_photo', $event->price_per_photo ?? $minPrice) }}"
                   min="{{ $minPrice }}"
                   step="0.01"
                   :disabled="isFree">
            <p class="text-gray-500 dark:text-gray-400 text-xs mt-1">
              ราคาขั้นต่ำ: <span class="font-semibold">{{ number_format($minPrice, 2) }}</span>
            </p>
            @error('price_per_photo')
            <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i>{{ $message }}</p>
            @enderror

            @if($minPrice > 0)
            <div class="bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-amber-700 dark:text-amber-400 rounded-lg p-2.5 text-xs mt-2">
              <i class="bi bi-info-circle mr-1"></i>
              ระบบกำหนดราคาขั้นต่ำไว้ที่ {{ number_format($minPrice, 2) }} บาท/รูป
            </div>
            @endif

            {{-- ฟรี checkbox --}}
            @if($allowFree ?? true)
            <div class="flex items-center gap-2 mt-3">
              <input type="hidden" name="is_free" value="0">
              <input type="checkbox" name="is_free" id="is_free"
                     class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                     value="1"
                     x-model="isFree"
                     {{ old('is_free', $event->is_free ?? 0) ? 'checked' : '' }}>
              <label for="is_free" class="text-sm text-gray-700 dark:text-gray-300">ฟรี (ไม่คิดค่าบริการ)</label>
            </div>
            @else
            <div class="mt-3 bg-red-50 dark:bg-red-500/10 border border-red-200 dark:border-red-500/20 text-red-600 dark:text-red-400 text-xs rounded-lg px-3 py-2">
              <i class="bi bi-lock mr-1"></i> ระบบปิดการใช้งาน "ฟรี" — อีเวนต์ต้องมีค่าบริการ
            </div>
            @endif
          </div>

          {{-- Cover Image --}}
          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1.5">ภาพหน้าปก</label>

            {{-- Current cover preview --}}
            <div id="coverPreviewWrap" class="mb-2 text-center" style="{{ (isset($event) && $event->cover_image) ? '' : 'display:none' }}">
              <img id="coverPreview"
                   src="{{ (isset($event) && $event->cover_image) ? $event->cover_image_url : '' }}"
                   class="w-full h-auto rounded-lg shadow-sm border-2 border-gray-200 dark:border-gray-600"
                   style="max-height:180px; object-fit:cover;"
                   alt="Cover preview">
            </div>

            {{-- Upload drop zone --}}
            <label class="w-full relative cursor-pointer block">
              <div id="coverDropZone"
                   class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-xl text-center p-4 bg-gray-50 dark:bg-slate-700/50 relative transition hover:border-indigo-400 hover:bg-indigo-50/30 dark:hover:border-indigo-400 dark:hover:bg-indigo-500/5">
                <i class="bi bi-cloud-arrow-up text-3xl text-indigo-500 block mb-1"></i>
                <span class="text-gray-500 dark:text-gray-400 text-sm">คลิกหรือลากวางไฟล์รูปภาพ</span>
                <br><span class="text-gray-400 dark:text-gray-500 text-[0.7rem]">JPEG, PNG, WebP -- ไม่เกิน 10MB</span>
              </div>
              <input type="file" name="cover_image" id="coverInput"
                     class="absolute top-0 left-0 w-full h-full opacity-0 cursor-pointer"
                     accept="image/jpeg,image/png,image/webp,image/gif">
            </label>
            @error('cover_image')
            <p class="text-red-500 text-xs mt-1"><i class="bi bi-exclamation-circle mr-1"></i>{{ $message }}</p>
            @enderror
          </div>

          {{-- ─────────────── Retention / Auto-Delete ─────────────── --}}
          <div class="pt-4 mt-2 border-t border-gray-200 dark:border-white/10">
            <h6 class="font-semibold text-sm text-gray-700 dark:text-gray-300 mb-2">
              <i class="bi bi-hourglass-split mr-1 text-red-500"></i> Auto-Delete Policy
            </h6>
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
              ใช้เมื่อเปิด Retention Policy ในหน้า Settings — กำหนดต่อ Event ได้
            </p>

            {{-- Exempt pin --}}
            <label class="flex items-start gap-2 mb-3 cursor-pointer p-2 rounded-lg hover:bg-gray-50 dark:hover:bg-white/5">
              <input type="hidden" name="auto_delete_exempt" value="0">
              <input type="checkbox" name="auto_delete_exempt" id="auto_delete_exempt" value="1"
                     {{ old('auto_delete_exempt', $event->auto_delete_exempt ?? 0) ? 'checked' : '' }}
                     class="mt-0.5 w-4 h-4 text-emerald-600 rounded focus:ring-emerald-500">
              <span class="text-xs">
                <span class="font-medium text-gray-700 dark:text-gray-300">ห้ามลบอัตโนมัติ (pin)</span><br>
                <span class="text-gray-500">อีเวนต์นี้จะถูกข้ามเสมอ</span>
              </span>
            </label>

            {{-- Retention days override --}}
            <div class="mb-3">
              <label for="retention_days_override" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                จำนวนวันเก็บ (override)
              </label>
              <input type="number" name="retention_days_override" id="retention_days_override"
                     min="1" max="3650"
                     value="{{ old('retention_days_override', $event->retention_days_override ?? '') }}"
                     placeholder="ว่างไว้ = ใช้ค่า default"
                     class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100">
              @error('retention_days_override')
              <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
              @enderror
            </div>

            {{-- Explicit delete date --}}
            <div>
              <label for="auto_delete_at" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                วันที่ลบเฉพาะ (override ทุกอย่าง)
              </label>
              <input type="date" name="auto_delete_at" id="auto_delete_at"
                     value="{{ old('auto_delete_at', optional($event->auto_delete_at ?? null)->format('Y-m-d')) }}"
                     class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm bg-white dark:bg-slate-700 dark:text-gray-100">
              @error('auto_delete_at')
              <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
              @enderror
            </div>
          </div>

          {{-- ─────────────────────────── Face Search (PDPA) ─────────────────────────── --}}
          <div class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700/50 rounded-xl p-4 mb-4">
            <h3 class="text-sm font-semibold text-indigo-900 dark:text-indigo-200 mb-2 flex items-center gap-1.5">
              <i class="bi bi-person-bounding-box"></i>
              ค้นหาด้วยใบหน้า (PDPA §26)
            </h3>
            <p class="text-xs text-indigo-700 dark:text-indigo-300 mb-3">
              เปิดไว้จะให้ผู้เข้าร่วมงานอัปโหลด selfie ค้นหารูปตัวเองได้ (รูปใบหน้าเป็น biometric data — ระบบบันทึกความยินยอมทุกครั้ง)
            </p>

            <label class="flex items-start gap-2 cursor-pointer p-2 rounded-lg hover:bg-white/50 dark:hover:bg-white/5">
              <input type="hidden" name="face_search_enabled" value="0">
              <input type="checkbox" name="face_search_enabled" id="face_search_enabled" value="1"
                     {{ old('face_search_enabled', $event->face_search_enabled ?? 1) ? 'checked' : '' }}
                     class="mt-0.5 w-4 h-4 text-indigo-600 rounded focus:ring-indigo-500">
              <span class="text-xs">
                <span class="font-medium text-gray-700 dark:text-gray-300">เปิดค้นหาด้วยใบหน้า</span><br>
                <span class="text-gray-500">ปิดเพื่อซ่อนปุ่ม "ค้นหาด้วยใบหน้า" และบล็อก API ของงานนี้ (เช่น งาน NDA, งานที่มีเยาวชน)</span>
              </span>
            </label>
            @error('face_search_enabled')
            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
          </div>

          {{-- Submit Button --}}
          <div class="pt-2">
            <button type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2.5 rounded-lg transition inline-flex items-center justify-center gap-1.5">
              <i class="bi bi-check-lg"></i>
              {{ isset($event) ? 'อัปเดต' : 'สร้าง' }} อีเวนต์
            </button>
          </div>

        </div>
      </div>
    </div>

  </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
  const input = document.getElementById('coverInput');
  const preview = document.getElementById('coverPreview');
  const wrap = document.getElementById('coverPreviewWrap');
  const dropZone = document.getElementById('coverDropZone');

  if (!input || !dropZone) return;

  // File selected -> show preview
  input.addEventListener('change', function() {
    handleFile(this.files);
  });

  function handleFile(files) {
    if (files && files[0]) {
      const file = files[0];

      // Validate file type
      const allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
      if (!allowed.includes(file.type)) {
        if (typeof Swal !== 'undefined') {
          Swal.fire('ไฟล์ไม่รองรับ', 'กรุณาเลือกไฟล์ JPEG, PNG หรือ WebP', 'warning');
        }
        input.value = '';
        return;
      }

      // Validate size (10MB)
      if (file.size > 10 * 1024 * 1024) {
        if (typeof Swal !== 'undefined') {
          Swal.fire('ไฟล์ใหญ่เกินไป', 'กรุณาเลือกไฟล์ที่มีขนาดไม่เกิน 10MB', 'warning');
        }
        input.value = '';
        return;
      }

      const reader = new FileReader();
      reader.onload = function(e) {
        preview.src = e.target.result;
        wrap.style.display = '';
        dropZone.innerHTML =
          '<i class="bi bi-check-circle text-2xl text-emerald-500 block mb-1"></i>' +
          '<span class="text-emerald-600 text-sm font-semibold">' + file.name + '</span>' +
          '<br><span class="text-gray-400 text-[0.7rem]">' + (file.size / 1024 / 1024).toFixed(2) + ' MB -- คลิกเพื่อเปลี่ยน</span>';
      };
      reader.readAsDataURL(file);
    }
  }

  // Drag & drop
  ['dragenter', 'dragover'].forEach(function(evt) {
    dropZone.addEventListener(evt, function(e) {
      e.preventDefault();
      e.stopPropagation();
      this.style.borderColor = '#6366f1';
      this.style.background = 'rgba(99,102,241,0.04)';
    });
  });

  ['dragleave', 'drop'].forEach(function(evt) {
    dropZone.addEventListener(evt, function(e) {
      e.preventDefault();
      e.stopPropagation();
      this.style.borderColor = '';
      this.style.background = '';
    });
  });

  dropZone.addEventListener('drop', function(e) {
    if (e.dataTransfer && e.dataTransfer.files.length) {
      input.files = e.dataTransfer.files;
      handleFile(e.dataTransfer.files);
    }
  });
});
</script>
@endpush

@endsection
