@extends('layouts.app')

@section('title', 'เลือกบทบาท')

@section('content-full')
<div class="flex items-center justify-center py-12 px-4 min-h-[85vh] bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50 dark:from-slate-900 dark:via-indigo-950/30 dark:to-slate-900 relative overflow-hidden">

  {{-- Decorative blobs --}}
  <div class="fixed w-[500px] h-[500px] rounded-full top-[-150px] right-[-100px] bg-gradient-radial from-indigo-400/10 to-transparent blur-3xl pointer-events-none"></div>
  <div class="fixed w-[350px] h-[350px] rounded-full bottom-[-80px] left-[-80px] bg-gradient-radial from-rose-400/10 to-transparent blur-3xl pointer-events-none"></div>

  <div class="relative w-full max-w-3xl">

    {{-- Welcome --}}
    <div class="text-center mb-8">
      <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-br from-indigo-600 to-purple-700 text-white shadow-xl mb-4">
        <i class="bi bi-stars text-3xl"></i>
      </div>
      <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-slate-900 dark:text-white mb-1">
        ยินดีต้อนรับ{{ Auth::check() ? ', ' . Auth::user()->first_name : '' }}!
      </h1>
      <p class="text-sm md:text-base text-slate-500 dark:text-slate-400">คุณต้องการใช้งานในฐานะอะไร?</p>
    </div>

    @if($errors->any())
      <div class="max-w-lg mx-auto mb-5 p-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 text-rose-800 dark:text-rose-300 text-sm flex items-start gap-2">
        <i class="bi bi-exclamation-circle-fill mt-0.5"></i> {{ $errors->first() }}
      </div>
    @endif
    @if(session('success'))
      <div class="max-w-lg mx-auto mb-5 p-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/20 text-emerald-800 dark:text-emerald-300 text-sm flex items-start gap-2">
        <i class="bi bi-check-circle-fill mt-0.5"></i> {{ session('success') }}
      </div>
    @endif

    <form method="POST" action="{{ route('choose-role.store') }}" id="roleForm">
      @csrf
      <input type="hidden" name="role" id="roleInput" value="">

      {{-- Role Cards --}}
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        {{-- Customer --}}
        <div class="role-card rc-customer cursor-pointer rounded-2xl p-6 bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-white/10 shadow-sm hover:border-indigo-300 dark:hover:border-indigo-500/50 hover:-translate-y-1 hover:shadow-xl transition-all duration-300 relative text-center" data-role="customer">
          <div class="rc-check absolute top-4 right-4 w-7 h-7 rounded-full border-2 border-slate-300 dark:border-white/20 flex items-center justify-center text-xs text-transparent transition-all duration-300">
            <i class="bi bi-check2"></i>
          </div>
          <div class="rc-icon mx-auto mb-4 w-20 h-20 rounded-2xl flex items-center justify-center text-4xl transition-all duration-300 bg-gradient-to-br from-blue-100 to-blue-50 dark:from-blue-500/20 dark:to-blue-500/10 text-blue-600 dark:text-blue-400">
            <i class="bi bi-bag-heart-fill"></i>
          </div>
          <span class="inline-block mb-2 text-xs px-3 py-1 rounded-full bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300 font-semibold">
            <i class="bi bi-person mr-1"></i>สำหรับผู้ซื้อ
          </span>
          <h2 class="text-xl font-bold mb-1 text-slate-900 dark:text-white">ลูกค้า</h2>
          <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">ค้นหา เลือกซื้อ และดาวน์โหลดรูปภาพคุณภาพสูง</p>
          <ul class="text-left text-xs text-slate-600 dark:text-slate-300 space-y-1.5">
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 flex-shrink-0"></i>ค้นหารูปจากงานอีเวนต์ต่างๆ</li>
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 flex-shrink-0"></i>ซื้อและดาวน์โหลดได้ทันที</li>
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 flex-shrink-0"></i>รูปภาพคุณภาพสูงไม่มีลายน้ำ</li>
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 flex-shrink-0"></i>Wishlist เก็บรูปที่สนใจ</li>
          </ul>
        </div>

        {{-- Photographer --}}
        <div class="role-card rc-photographer cursor-pointer rounded-2xl p-6 bg-white dark:bg-slate-800 border-2 border-slate-200 dark:border-white/10 shadow-sm hover:border-pink-300 dark:hover:border-pink-500/50 hover:-translate-y-1 hover:shadow-xl transition-all duration-300 relative text-center" data-role="photographer">
          <div class="rc-check absolute top-4 right-4 w-7 h-7 rounded-full border-2 border-slate-300 dark:border-white/20 flex items-center justify-center text-xs text-transparent transition-all duration-300">
            <i class="bi bi-check2"></i>
          </div>
          <div class="rc-icon mx-auto mb-4 w-20 h-20 rounded-2xl flex items-center justify-center text-4xl transition-all duration-300 bg-gradient-to-br from-pink-100 to-pink-50 dark:from-pink-500/20 dark:to-pink-500/10 text-pink-600 dark:text-pink-400">
            <i class="bi bi-camera-fill"></i>
          </div>
          <span class="inline-block mb-2 text-xs px-3 py-1 rounded-full bg-pink-100 dark:bg-pink-500/20 text-pink-700 dark:text-pink-300 font-semibold">
            <i class="bi bi-stars mr-1"></i>สร้างรายได้
          </span>
          <h2 class="text-xl font-bold mb-1 text-slate-900 dark:text-white">ช่างภาพ</h2>
          <p class="text-sm text-slate-500 dark:text-slate-400 mb-4">อัปโหลดผลงาน ขายรูปภาพ และสร้างรายได้</p>
          <ul class="text-left text-xs text-slate-600 dark:text-slate-300 space-y-1.5">
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 flex-shrink-0"></i>อัปโหลดและขายรูปได้ไม่จำกัด</li>
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 flex-shrink-0"></i>รับส่วนแบ่งรายได้สูงสุด 80%</li>
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 flex-shrink-0"></i>Dashboard จัดการออเดอร์ + รายได้</li>
            <li class="flex items-start gap-2"><i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 flex-shrink-0"></i>แจ้งเตือนยอดขายผ่าน LINE</li>
          </ul>
        </div>
      </div>

      {{-- Photographer Extra Fields --}}
      <div id="pgExtra" class="hidden animate-[slideDown_0.3s_ease]">
        <div class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-white/10 shadow-sm mb-6 overflow-hidden">
          <div class="p-6">
            <h3 class="font-bold text-slate-900 dark:text-white mb-4 flex items-center gap-2">
              <i class="bi bi-person-badge text-indigo-500"></i> ข้อมูลเพิ่มเติมสำหรับช่างภาพ
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">ชื่อที่แสดงในเว็บ <span class="text-rose-500">*</span></label>
                <input type="text" name="display_name" id="displayName" placeholder="เช่น John Photo Studio" value="{{ old('display_name') }}"
                       class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-pink-500 transition @error('display_name') border-rose-500 focus:ring-rose-500 @enderror">
                @error('display_name')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">ชื่อที่ลูกค้าจะเห็นในหน้าเว็บ</p>
              </div>
              <div>
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-1.5">เบอร์โทรศัพท์ <span class="text-rose-500">*</span></label>
                <input type="tel" name="phone" id="phoneInput" placeholder="0xx-xxx-xxxx" value="{{ old('phone', Auth::user()->phone ?? '') }}"
                       class="w-full px-3 py-2.5 rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-900 text-slate-900 dark:text-white text-sm placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-pink-500 transition @error('phone') border-rose-500 focus:ring-rose-500 @enderror">
                @error('phone')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
              </div>
            </div>
            <div class="mt-4 p-3 rounded-xl bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/20 text-xs text-amber-800 dark:text-amber-300">
              <i class="bi bi-info-circle mr-1"></i>
              บัญชีช่างภาพต้องรอ Admin อนุมัติก่อนใช้งาน หลังอนุมัติแล้วจะต้องเชื่อมต่อบัญชีรับเงิน (PromptPay) เพื่อรับรายได้
            </div>
          </div>
        </div>
      </div>

      {{-- Submit --}}
      <div class="text-center mb-3">
        <button type="submit" id="submitBtn" disabled
                class="inline-flex items-center gap-2 px-10 py-3 rounded-xl bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-700 hover:to-purple-700 text-white font-semibold shadow-lg hover:shadow-xl transition-all min-w-[280px] disabled:opacity-50 disabled:cursor-not-allowed">
          <span id="submitText">กรุณาเลือกบทบาท</span>
          <i class="bi bi-arrow-right"></i>
        </button>
      </div>

      {{-- Skip --}}
      <div class="text-center">
        <a href="#" class="skip-link inline-flex items-center gap-1 text-sm text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
          <i class="bi bi-skip-forward"></i> ข้ามไปก่อน (เป็นลูกค้าเริ่มต้น)
        </a>
      </div>
    </form>
  </div>
</div>

@push('styles')
<style>
  @keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .role-card.active {
    border-color: rgb(99 102 241) !important;
    background: rgba(99,102,241,0.05) !important;
    box-shadow: 0 0 0 4px rgba(99,102,241,0.15), 0 20px 40px rgba(99,102,241,0.2) !important;
    transform: translateY(-4px);
  }
  .dark .role-card.active {
    background: rgba(99,102,241,0.1) !important;
  }
  .role-card.active .rc-check {
    background: rgb(99 102 241) !important;
    border-color: rgb(99 102 241) !important;
    color: #fff !important;
  }
  .rc-customer.active .rc-icon {
    background: linear-gradient(135deg,#3b82f6,#1d4ed8) !important;
    color: #fff !important;
  }
  .rc-photographer.active .rc-icon {
    background: linear-gradient(135deg,#ec4899,#be185d) !important;
    color: #fff !important;
  }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  const cards = document.querySelectorAll('.role-card');
  const roleInput = document.getElementById('roleInput');
  const pgExtra = document.getElementById('pgExtra');
  const submitBtn = document.getElementById('submitBtn');
  const submitText = document.getElementById('submitText');
  const displayName = document.getElementById('displayName');
  const phoneInput = document.getElementById('phoneInput');

  @if(old('role') === 'photographer')
    activateCard('photographer');
  @endif

  cards.forEach(card => card.addEventListener('click', function() { activateCard(this.dataset.role); }));

  function activateCard(role) {
    roleInput.value = role;
    cards.forEach(c => c.classList.remove('active'));
    document.querySelector('[data-role="' + role + '"]').classList.add('active');

    const isPhotographer = role === 'photographer';
    pgExtra.style.display = isPhotographer ? 'block' : 'none';
    if (isPhotographer) pgExtra.classList.remove('hidden');
    if (displayName) displayName.required = isPhotographer;
    if (phoneInput) phoneInput.required = isPhotographer;

    submitBtn.disabled = false;
    if (isPhotographer) {
      submitText.textContent = 'สมัครเป็นช่างภาพ';
      submitBtn.classList.remove('from-indigo-600', 'to-purple-600', 'hover:from-indigo-700', 'hover:to-purple-700');
      submitBtn.classList.add('from-pink-500', 'to-rose-500', 'hover:from-pink-600', 'hover:to-rose-600');
    } else {
      submitText.textContent = 'เริ่มต้นซื้อรูปภาพ';
      submitBtn.classList.remove('from-pink-500', 'to-rose-500', 'hover:from-pink-600', 'hover:to-rose-600');
      submitBtn.classList.add('from-indigo-600', 'to-purple-600', 'hover:from-indigo-700', 'hover:to-purple-700');
    }
  }

  document.querySelector('.skip-link').addEventListener('click', function(e) {
    e.preventDefault();
    roleInput.value = 'customer';
    document.getElementById('roleForm').submit();
  });
});
</script>
@endpush
@endsection
