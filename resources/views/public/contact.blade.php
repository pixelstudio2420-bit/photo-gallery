@extends('layouts.app')

@section('title', 'ติดต่อเรา')

@section('content')
{{-- Page H1 — required for SEO; was missing pre-2026-04-28 audit. --}}
<header class="max-w-4xl mx-auto text-center mb-8">
  <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white mb-2">ติดต่อทีมงาน Loadroop</h1>
  <p class="text-base text-slate-600 dark:text-slate-400">ส่งคำถามหรือปัญหา · เราตอบกลับภายใน 24 ชั่วโมง</p>
</header>

<div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-3 gap-6">

  {{-- Contact Info Sidebar --}}
  <div class="md:col-span-1 space-y-4">
    <div class="bg-gradient-to-br from-indigo-500 to-violet-600 text-white rounded-2xl p-6">
      <i class="bi bi-headset text-3xl mb-3"></i>
      <h3 class="font-bold text-lg mb-2">ติดต่อทีมงาน</h3>
      <p class="text-sm text-white/90">เรายินดีช่วยเหลือคุณทุกปัญหา ตอบกลับภายใน 24 ชั่วโมง</p>
    </div>

    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl p-5 space-y-3">
      <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
        <i class="bi bi-envelope text-indigo-500 dark:text-indigo-400 w-5"></i>
        <span class="break-all">{{ \App\Models\AppSetting::get('support_email', 'support@example.com') }}</span>
      </div>
      @if($phone = \App\Models\AppSetting::get('footer_contact_phone'))
      <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
        <i class="bi bi-telephone text-indigo-500 dark:text-indigo-400 w-5"></i>
        <span>{{ $phone }}</span>
      </div>
      @endif
      @if($line = \App\Models\AppSetting::get('footer_contact_line_id'))
      <div class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
        <i class="bi bi-line text-green-500 dark:text-green-400 w-5"></i>
        <span>{{ $line }}</span>
      </div>
      @endif
    </div>

    @auth
    <a href="{{ route('support.index') }}"
       class="block bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl p-4 hover:border-indigo-200 dark:hover:border-indigo-400/40 transition">
      <div class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
        <i class="bi bi-ticket-perforated text-indigo-500 dark:text-indigo-400"></i>
        <span>Tickets ของฉัน</span>
        <i class="bi bi-chevron-right ml-auto text-gray-400 dark:text-gray-500"></i>
      </div>
      <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">ดูและตอบกลับ tickets ที่สร้างไว้</p>
    </a>
    @endauth

    {{-- SLA Info --}}
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl p-5">
      <h4 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase mb-3">เวลาตอบกลับ (SLA)</h4>
      <div class="space-y-2 text-xs">
        @foreach(\App\Models\ContactMessage::PRIORITIES as $k => $p)
        <div class="flex items-center justify-between">
          {{-- Priority pills — dark-mode variants pinned per palette so
               each colour stays readable. The bg-{color}-100 / text-
               {color}-700 pairs are pre-baked in app.css's @source scan,
               same for the dark equivalents below. --}}
          <span class="px-2 py-0.5 bg-{{ $p['color'] }}-100 dark:bg-{{ $p['color'] }}-500/15 text-{{ $p['color'] }}-700 dark:text-{{ $p['color'] }}-300 rounded font-medium">{{ $p['label'] }}</span>
          <span class="text-gray-600 dark:text-gray-300">{{ $p['sla_hours'] }} ชั่วโมง</span>
        </div>
        @endforeach
      </div>
    </div>
  </div>

  {{-- Contact Form --}}
  <div class="md:col-span-2">
    <div class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/10 rounded-2xl p-6 md:p-8">
      <div class="mb-5">
        <h2 class="text-2xl font-bold text-slate-800 dark:text-slate-100">ส่งคำถาม / รายงานปัญหา</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">กรอกแบบฟอร์มด้านล่าง ทีมงานจะตอบกลับโดยเร็ว</p>
      </div>

      @if(session('success'))
        <div class="bg-emerald-50 dark:bg-emerald-500/10 border-l-4 border-emerald-400 rounded-r-lg p-4 mb-4 text-sm text-emerald-800 dark:text-emerald-300">
          <i class="bi bi-check-circle-fill mr-1"></i>{!! session('success') !!}
        </div>
      @endif

      @if($errors->any())
        <div class="bg-red-50 dark:bg-red-500/10 border-l-4 border-red-400 rounded-r-lg p-4 mb-4 text-sm text-red-800 dark:text-red-300">
          <ul class="list-disc list-inside">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
          </ul>
        </div>
      @endif

      {{--
        Form fields — every label/input/select/textarea carries the
        full dark-mode pair so the form stays usable on dark theme:
          • bg-white                → dark:bg-slate-700
          • text-gray-700 (label)   → dark:text-gray-200
          • text-slate-900 (input)  → dark:text-slate-100
          • border-gray-200         → dark:border-white/10
          • placeholder shows muted in both themes
      --}}
      <form method="POST" action="{{ route('contact.store') }}" class="space-y-4">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5">ชื่อ <span class="text-red-500">*</span></label>
            <input type="text" name="name" required maxlength="200"
                   value="{{ old('name', auth()->user()?->first_name ?? '') }}"
                   class="w-full px-4 py-2.5 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-gray-200 dark:border-white/10 rounded-xl text-sm focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-400/30 focus:border-indigo-400 placeholder-gray-400 dark:placeholder-gray-500">
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5">อีเมล <span class="text-red-500">*</span></label>
            <input type="email" name="email" required
                   value="{{ old('email', auth()->user()?->email ?? '') }}"
                   class="w-full px-4 py-2.5 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-gray-200 dark:border-white/10 rounded-xl text-sm focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-400/30 focus:border-indigo-400 placeholder-gray-400 dark:placeholder-gray-500">
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5">หมวดหมู่ <span class="text-red-500">*</span></label>
            <select name="category" required
                    class="w-full px-4 py-2.5 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-gray-200 dark:border-white/10 rounded-xl text-sm focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-400/30 focus:border-indigo-400">
              @foreach(\App\Models\ContactMessage::CATEGORIES as $k => $label)
                <option value="{{ $k }}" {{ old('category') === $k ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5">ความสำคัญ</label>
            <select name="priority"
                    class="w-full px-4 py-2.5 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-gray-200 dark:border-white/10 rounded-xl text-sm focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-400/30 focus:border-indigo-400">
              @foreach(\App\Models\ContactMessage::PRIORITIES as $k => $p)
                @if($k === 'low' || $k === 'normal' || auth()->check())
                <option value="{{ $k }}" {{ old('priority', 'normal') === $k ? 'selected' : '' }}>{{ $p['label'] }} (ภายใน {{ $p['sla_hours'] }}h)</option>
                @endif
              @endforeach
            </select>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5">หัวข้อ <span class="text-red-500">*</span></label>
          <input type="text" name="subject" required maxlength="300" value="{{ old('subject') }}"
                 placeholder="สรุปปัญหาหรือคำถามของคุณ"
                 class="w-full px-4 py-2.5 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-gray-200 dark:border-white/10 rounded-xl text-sm focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-400/30 focus:border-indigo-400 placeholder-gray-400 dark:placeholder-gray-500">
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 dark:text-gray-200 mb-1.5">รายละเอียด <span class="text-red-500">*</span></label>
          <textarea name="message" required rows="6" maxlength="5000"
                    placeholder="อธิบายปัญหาหรือคำถามของคุณโดยละเอียด..."
                    class="w-full px-4 py-2.5 bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-gray-200 dark:border-white/10 rounded-xl text-sm focus:ring-2 focus:ring-indigo-200 dark:focus:ring-indigo-400/30 focus:border-indigo-400 placeholder-gray-400 dark:placeholder-gray-500">{{ old('message') }}</textarea>
          <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">ยิ่งละเอียด ยิ่งตอบกลับได้เร็วและแม่นยำ</p>
        </div>

        <div class="pt-2">
          <button type="submit" class="w-full py-3 bg-gradient-to-br from-indigo-500 to-indigo-600 dark:from-indigo-500 dark:to-indigo-600 text-white rounded-xl font-semibold hover:shadow-lg transition">
            <i class="bi bi-send mr-1"></i>ส่งข้อความ
          </button>
        </div>

        <p class="text-xs text-gray-400 dark:text-gray-500 text-center">
          การส่งข้อความแสดงว่าคุณยอมรับนโยบายความเป็นส่วนตัวของเรา
        </p>
      </form>
    </div>
  </div>
</div>
@endsection
