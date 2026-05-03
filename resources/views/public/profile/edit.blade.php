@extends('layouts.app')

@section('title', 'โปรไฟล์ของฉัน')

@section('content')
<div class="max-w-5xl mx-auto px-4 md:px-6 py-6">

  {{-- Header --}}
  <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
    <h1 class="text-2xl md:text-3xl font-bold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
      <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-md">
        <i class="bi bi-person-gear"></i>
      </span>
      โปรไฟล์ของฉัน
    </h1>
    <a href="{{ route('profile') }}"
       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg border border-slate-200 dark:border-white/10 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-white/5 text-sm transition">
      <i class="bi bi-arrow-left"></i> แดชบอร์ด
    </a>
  </div>

  @if(session('success'))
    <div class="mb-4 p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-800 dark:text-emerald-300 text-sm flex items-start gap-2">
      <i class="bi bi-check-circle-fill mt-0.5"></i> {{ session('success') }}
    </div>
  @endif

  @if($errors->any())
    <div class="mb-4 p-4 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 text-rose-800 dark:text-rose-300 text-sm">
      <div class="flex items-start gap-2">
        <i class="bi bi-exclamation-circle-fill mt-0.5"></i>
        <ul class="space-y-0.5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
      </div>
    </div>
  @endif

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
    <div class="lg:col-span-2 space-y-5">
      {{-- Profile Info Form --}}
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center shadow-md">
            <i class="bi bi-person"></i>
          </div>
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-white">ข้อมูลส่วนตัว</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">แก้ไขชื่อ อีเมล และเบอร์ติดต่อ</p>
          </div>
        </div>
        <div class="p-5">
          <form method="POST" action="{{ route('profile.update') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">ชื่อจริง</label>
                <input type="text" name="first_name" value="{{ old('first_name', $user->first_name) }}"
                       class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">นามสกุล</label>
                <input type="text" name="last_name" value="{{ old('last_name', $user->last_name) }}"
                       class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
              </div>
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                <i class="bi bi-envelope mr-1 text-slate-400"></i> อีเมล
              </label>
              <input type="email" name="email" value="{{ old('email', $user->email) }}"
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            </div>
            <div>
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                <i class="bi bi-telephone mr-1 text-slate-400"></i> เบอร์โทรศัพท์
              </label>
              <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}"
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
            </div>

            {{-- Province — drives geo-targeting (announcements, festivals,
                 new-event popups for users in your area). Optional —
                 leave blank to receive nationwide messages only. --}}
            <div>
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">
                <i class="bi bi-geo-alt mr-1 text-slate-400"></i> จังหวัด
                <span class="text-[10px] font-normal text-slate-400 ml-1">(เพื่อรับข้อมูลกิจกรรมในพื้นที่)</span>
              </label>
              @include('partials.province-select', [
                  'name'     => 'province_id',
                  'selected' => old('province_id', $user->province_id ?? null),
              ])
              <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                <i class="bi bi-info-circle"></i>
                ตั้งจังหวัดเพื่อรับ popup กิจกรรม/อีเวนต์ใหม่ในพื้นที่ของคุณก่อนใคร
              </p>
            </div>

            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-md hover:shadow-lg transition-all">
              <i class="bi bi-check-lg"></i> บันทึกการเปลี่ยนแปลง
            </button>
          </form>
        </div>
      </div>

      {{-- Change Password --}}
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-amber-500 to-orange-500 text-white flex items-center justify-center shadow-md">
            <i class="bi bi-key-fill"></i>
          </div>
          <div>
            <h3 class="font-semibold text-slate-900 dark:text-white">เปลี่ยนรหัสผ่าน</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">ใช้รหัสผ่านที่แข็งแรงเพื่อความปลอดภัย</p>
          </div>
        </div>
        <div class="p-5">
          <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div>
              <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">รหัสผ่านปัจจุบัน</label>
              <input type="password" name="current_password" required
                     class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 transition">
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">รหัสผ่านใหม่</label>
                <input type="password" name="password" required
                       class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 transition">
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1.5">ยืนยันรหัสผ่านใหม่</label>
                <input type="password" name="password_confirmation" required
                       class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500 transition">
              </div>
            </div>
            <button type="submit"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-semibold shadow-md hover:shadow-lg transition-all">
              <i class="bi bi-key-fill"></i> เปลี่ยนรหัสผ่าน
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="lg:col-span-1 space-y-5">
      {{-- Avatar Card --}}
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="h-20 bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500"></div>
        <div class="px-5 pb-5 text-center -mt-10">
          {{-- Use the shared <x-avatar> component so the image is resolved
               through StorageManager::resolveUrl() (R2 / public / social
               URLs all render correctly), with a deterministic initials
               fallback when the user has no avatar yet. --}}
          <div class="inline-block ring-4 ring-white dark:ring-slate-800 rounded-full shadow-xl">
            <x-avatar :src="$user->avatar"
                      :name="$user->first_name . ' ' . $user->last_name"
                      :user-id="$user->id"
                      size="xl" />
          </div>
          <h3 class="font-semibold text-slate-900 dark:text-white mt-3">{{ $user->first_name }} {{ $user->last_name }}</h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5 truncate">{{ $user->email }}</p>

          {{-- Avatar upload / remove — mutually-exclusive: picking a file
               replaces the avatar, ticking "ลบรูป" removes it. The form
               must be multipart/form-data because it carries a file. --}}
          <form method="POST" action="{{ route('profile.avatar.update') }}"
                enctype="multipart/form-data"
                class="mt-4 space-y-2 text-left">
            @csrf
            @method('PUT')

            <label class="block text-xs font-medium text-slate-700 dark:text-slate-300">
              เปลี่ยนรูปโปรไฟล์
            </label>
            <input type="file" name="avatar"
                   accept="image/png,image/jpeg,image/webp,image/gif"
                   class="w-full text-xs text-slate-600 dark:text-slate-300
                          file:mr-2 file:py-1.5 file:px-3 file:rounded-lg
                          file:border-0 file:text-xs file:font-semibold
                          file:bg-indigo-50 dark:file:bg-indigo-500/20
                          file:text-indigo-600 dark:file:text-indigo-300
                          hover:file:bg-indigo-100 dark:hover:file:bg-indigo-500/30
                          cursor-pointer">

            @if($user->avatar)
              <label class="inline-flex items-center gap-1.5 text-xs text-rose-600 dark:text-rose-400 cursor-pointer">
                <input type="checkbox" name="remove_avatar" value="1"
                       class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                ลบรูปโปรไฟล์
              </label>
            @endif

            <button type="submit"
                    class="w-full mt-2 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold transition">
              <i class="bi bi-upload"></i> บันทึกรูปโปรไฟล์
            </button>
          </form>
        </div>
      </div>

      {{-- Connected accounts --}}
      <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white flex items-center justify-center shadow-md">
            <i class="bi bi-link-45deg"></i>
          </div>
          <h3 class="font-semibold text-slate-900 dark:text-white">บัญชีที่เชื่อมต่อ</h3>
        </div>
        <div class="p-3 space-y-1">
          @foreach([
            'google'   => ['label' => 'Google',   'icon' => 'google',    'color' => 'text-rose-500'],
            'facebook' => ['label' => 'Facebook', 'icon' => 'facebook',  'color' => 'text-blue-600'],
            'line'     => ['label' => 'LINE',     'icon' => 'chat-fill', 'color' => 'text-emerald-500'],
          ] as $provider => $info)
            <div class="flex items-center justify-between p-2.5 rounded-xl hover:bg-slate-50 dark:hover:bg-white/5 transition">
              <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-white/5 flex items-center justify-center">
                  <i class="bi bi-{{ $info['icon'] }} {{ $info['color'] }}"></i>
                </div>
                <span class="font-medium text-sm text-slate-900 dark:text-white">{{ $info['label'] }}</span>
              </div>
              @if($user->socialLogins->where('provider', $provider)->first())
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 text-xs font-semibold">
                  <i class="bi bi-check-lg"></i> เชื่อมแล้ว
                </span>
              @else
                <a href="{{ url('/auth/' . $provider) }}"
                   class="inline-flex items-center px-3 py-1 rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 hover:bg-indigo-100 dark:hover:bg-indigo-500/20 text-xs font-medium transition">
                  เชื่อมต่อ
                </a>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
