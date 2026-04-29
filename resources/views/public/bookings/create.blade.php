@extends('layouts.app')

@section('title', 'จองช่างภาพ — ' . $photographer->display_name)

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
@endpush

@section('content')
<div class="max-w-3xl mx-auto py-8 px-4">

  <div class="mb-5">
    <a href="{{ route('photographers.show', $photographer->user_id) }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
      <i class="bi bi-arrow-left"></i> กลับโปรไฟล์ช่างภาพ
    </a>
  </div>

  <div class="rounded-3xl overflow-hidden bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 shadow-lg">
    {{-- Hero --}}
    <div class="px-6 py-8 text-center text-white" style="background:linear-gradient(135deg, #06C755 0%, #6366f1 100%);">
      <div class="text-[10px] uppercase tracking-[0.25em] opacity-80 mb-2">📅 จองคิวงาน</div>
      <h1 class="text-2xl font-extrabold leading-tight mb-1">จองช่างภาพ {{ $photographer->display_name }}</h1>
      <p class="text-sm opacity-90">กรอกข้อมูลด้านล่าง — ช่างภาพจะยืนยันใน 24 ชม. ผ่าน LINE</p>
    </div>

    @if(session('warning'))
      <div class="mx-6 mt-5 p-3.5 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 text-amber-800 dark:text-amber-200 flex items-start gap-2">
        <i class="bi bi-exclamation-triangle-fill mt-0.5 shrink-0"></i><span class="text-sm">{{ session('warning') }}</span>
      </div>
    @endif

    @if($errors->any())
      <div class="mx-6 mt-5 p-3.5 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 text-sm">
        <ul class="list-disc list-inside m-0">
          @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
      </div>
    @endif

    <form action="{{ route('bookings.store', $photographer->user_id) }}" method="POST" class="p-6 space-y-4">
      @csrf

      <div>
        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1.5">
          <i class="bi bi-card-text"></i> ชื่องาน / หัวข้อ <span class="text-rose-500">*</span>
        </label>
        <input type="text" name="title" required maxlength="255"
               value="{{ old('title') }}"
               placeholder="เช่น งานแต่ง คุณนุช + คุณเจมส์ / รับปริญญา จุฬาฯ"
               class="w-full px-3.5 py-2 rounded-lg text-sm border bg-white dark:bg-slate-800 border-slate-200 dark:border-white/10 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1.5">
            <i class="bi bi-calendar-event"></i> วัน + เวลาเริ่ม <span class="text-rose-500">*</span>
          </label>
          <input type="datetime-local" name="scheduled_at" required min="{{ $minDate }}"
                 value="{{ old('scheduled_at') }}"
                 class="w-full px-3.5 py-2 rounded-lg text-sm border bg-white dark:bg-slate-800 border-slate-200 dark:border-white/10 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
          <p class="text-[10px] text-slate-500 mt-1">อย่างน้อย 12 ชั่วโมงล่วงหน้า</p>
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1.5">
            <i class="bi bi-clock-history"></i> ระยะเวลา (นาที) <span class="text-rose-500">*</span>
          </label>
          <select name="duration_minutes" required
                  class="w-full px-3.5 py-2 rounded-lg text-sm border bg-white dark:bg-slate-800 border-slate-200 dark:border-white/10 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
            <option value="60" {{ old('duration_minutes') == '60' ? 'selected' : '' }}>1 ชั่วโมง</option>
            <option value="120" {{ old('duration_minutes', '120') == '120' ? 'selected' : '' }}>2 ชั่วโมง</option>
            <option value="180" {{ old('duration_minutes') == '180' ? 'selected' : '' }}>3 ชั่วโมง</option>
            <option value="240" {{ old('duration_minutes') == '240' ? 'selected' : '' }}>4 ชั่วโมง</option>
            <option value="360" {{ old('duration_minutes') == '360' ? 'selected' : '' }}>6 ชั่วโมง</option>
            <option value="480" {{ old('duration_minutes') == '480' ? 'selected' : '' }}>8 ชั่วโมง (ทั้งวัน)</option>
            <option value="720" {{ old('duration_minutes') == '720' ? 'selected' : '' }}>12 ชั่วโมง</option>
          </select>
        </div>
      </div>

      <div x-data="{
        lat: {{ old('location_lat', 'null') }},
        lng: {{ old('location_lng', 'null') }},
        showMap: false,
        map: null,
        marker: null,
        toggleMap() {
          this.showMap = !this.showMap;
          if (this.showMap) this.$nextTick(() => this.initMap());
        },
        initMap() {
          if (this.map) return;
          const center = this.lat && this.lng ? [this.lat, this.lng] : [13.7563, 100.5018]; // BKK default
          this.map = L.map('locationMap').setView(center, this.lat ? 15 : 11);
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap',
            maxZoom: 19,
          }).addTo(this.map);
          if (this.lat && this.lng) {
            this.marker = L.marker([this.lat, this.lng]).addTo(this.map);
          }
          this.map.on('click', (e) => {
            this.lat = e.latlng.lat.toFixed(7);
            this.lng = e.latlng.lng.toFixed(7);
            if (this.marker) this.marker.setLatLng(e.latlng);
            else this.marker = L.marker(e.latlng).addTo(this.map);
          });
          // Force re-render in case container was hidden
          setTimeout(() => this.map.invalidateSize(), 100);
        },
      }">
        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1.5">
          <i class="bi bi-geo-alt"></i> สถานที่
        </label>
        <input type="text" name="location" maxlength="500"
               value="{{ old('location') }}"
               placeholder="เช่น โรงแรม Centara สาทร / สวนเบญจกิติ"
               class="w-full px-3.5 py-2 rounded-lg text-sm border bg-white dark:bg-slate-800 border-slate-200 dark:border-white/10 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">

        <input type="hidden" name="location_lat" :value="lat ?? ''">
        <input type="hidden" name="location_lng" :value="lng ?? ''">

        <div class="mt-2 flex items-center justify-between gap-2">
          <button type="button" @click="toggleMap"
                  class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 transition">
            <i class="bi" :class="showMap ? 'bi-map' : 'bi-pin-map-fill'"></i>
            <span x-text="showMap ? 'ซ่อนแผนที่' : 'ปักหมุดบนแผนที่'"></span>
          </button>
          <span x-show="lat && lng" x-cloak class="text-[10px] text-emerald-600 dark:text-emerald-400 font-mono">
            <i class="bi bi-check-circle-fill"></i> <span x-text="lat + ', ' + lng"></span>
          </span>
        </div>

        <div x-show="showMap" x-cloak class="mt-2">
          <div id="locationMap" style="height:280px;border-radius:12px;border:1px solid #e2e8f0;z-index:0;"></div>
          <p class="text-[10px] text-slate-500 mt-1">คลิกบนแผนที่เพื่อปักหมุด — ช่างภาพจะเห็นพิกัดและไปได้ถูก</p>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1.5">
            <i class="bi bi-images"></i> จำนวนรูปที่ต้องการ
          </label>
          <input type="number" name="expected_photos" min="1" max="10000"
                 value="{{ old('expected_photos') }}"
                 placeholder="เช่น 200"
                 class="w-full px-3.5 py-2 rounded-lg text-sm border bg-white dark:bg-slate-800 border-slate-200 dark:border-white/10 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
        </div>
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1.5">
            <i class="bi bi-cash"></i> ราคาที่คาดหวัง (฿)
          </label>
          <input type="number" name="agreed_price" min="0" step="100"
                 value="{{ old('agreed_price') }}"
                 placeholder="เช่น 5000"
                 class="w-full px-3.5 py-2 rounded-lg text-sm border bg-white dark:bg-slate-800 border-slate-200 dark:border-white/10 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1.5">
          <i class="bi bi-telephone"></i> เบอร์ติดต่อ
        </label>
        <input type="tel" name="customer_phone" maxlength="30"
               value="{{ old('customer_phone', auth()->user()?->phone) }}"
               placeholder="เช่น 081-234-5678"
               class="w-full px-3.5 py-2 rounded-lg text-sm border bg-white dark:bg-slate-800 border-slate-200 dark:border-white/10 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
      </div>

      <div>
        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-200 mb-1.5">
          <i class="bi bi-chat-text"></i> รายละเอียด / ข้อความถึงช่างภาพ
        </label>
        <textarea name="customer_notes" rows="4" maxlength="2000"
                  placeholder="ธีมงาน, ความต้องการพิเศษ, จำนวนแขก..."
                  class="w-full px-3.5 py-2 rounded-lg text-sm border bg-white dark:bg-slate-800 border-slate-200 dark:border-white/10 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500 resize-y">{{ old('customer_notes') }}</textarea>
      </div>

      <div class="rounded-xl p-3.5 bg-emerald-50/60 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 flex items-start gap-2">
        <i class="bi bi-info-circle-fill text-emerald-600 mt-0.5 shrink-0"></i>
        <div class="text-xs text-emerald-800 dark:text-emerald-200 leading-relaxed">
          ระบบจะส่ง LINE หาช่างภาพให้ <strong>ยืนยันใน 24 ชม.</strong> · คุณจะได้รับ reminder ก่อนวันงาน
          <strong>3 วัน · 1 วัน · วันงาน</strong> ทาง LINE
        </div>
      </div>

      <div class="flex items-center justify-between gap-3 pt-2 flex-wrap">
        <a href="{{ route('photographers.show', $photographer->user_id) }}" class="text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">
          <i class="bi bi-arrow-left"></i> กลับ
        </a>
        <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 rounded-xl text-sm font-bold text-white shadow-lg shadow-indigo-500/30 hover:-translate-y-0.5 transition" style="background:linear-gradient(135deg,#06C755,#6366f1);">
          <i class="bi bi-calendar-check"></i> ส่งคำขอจองคิว
        </button>
      </div>
    </form>
  </div>
</div>
@endsection
