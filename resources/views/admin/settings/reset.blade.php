@extends('layouts.admin')

@section('title', 'System Reset')

@section('content')
<div class="flex items-center justify-between mb-4">
  <h4 class="font-bold tracking-tight">
    <i class="bi bi-arrow-counterclockwise mr-2 text-indigo-500"></i>System Reset
  </h4>
  <a href="{{ route('admin.settings.index') }}" class="inline-flex items-center px-4 py-1.5 bg-indigo-50 text-indigo-600 text-sm font-medium rounded-lg hover:bg-indigo-100 transition">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

@if(session('success'))
  <div class="bg-emerald-50 text-emerald-700 rounded-xl p-4 text-sm mb-4">
    <i class="bi bi-check-circle mr-1"></i> {{ session('success') }}
  </div>
@endif
@if(session('error'))
  <div class="bg-red-50 text-red-600 rounded-xl p-4 text-sm mb-4">
    <i class="bi bi-exclamation-triangle mr-1"></i> {{ session('error') }}
  </div>
@endif

{{-- Warning Alert --}}
<div class="flex items-start gap-3 mb-4 p-4 bg-orange-50 border-2 border-orange-400 rounded-xl text-orange-900">
  <i class="bi bi-exclamation-triangle-fill text-xl mt-0.5 text-orange-500 shrink-0"></i>
  <div>
    <div class="font-semibold mb-1">คำเตือน</div>
    <div class="text-sm leading-relaxed">
      การรีเซ็ตข้อมูล<strong>ไม่สามารถย้อนกลับได้</strong> กรุณาสำรองข้อมูลก่อนดำเนินการ
    </div>
  </div>
</div>

<div class="space-y-4">

  {{-- ==================== Orders & Revenue ==================== --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="px-4 pt-4 pb-2">
      <h6 class="font-semibold text-sm">
        <i class="bi bi-cart mr-1 text-indigo-500"></i> Orders & Revenue
      </h6>
    </div>
    <div class="px-4 pb-4">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">

        {{-- Reset Orders --}}
        <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg">
          <div class="font-medium text-sm mb-1">Reset Orders</div>
          <p class="text-gray-500 text-xs mb-3">
            ลบข้อมูลทั้งหมดใน orders, order_items, download_tokens, payment_slips,
            payment_transactions, payment_logs, payment_refunds, payment_audit_log และ photographer_payouts
          </p>
          <form method="POST" action="{{ route('admin.settings.reset.perform') }}" class="reset-form">
            @csrf
            <input type="hidden" name="action" value="reset_orders">
            <label class="block text-xs font-medium mb-1">พิมพ์ <code class="bg-gray-200 px-1 rounded">RESET</code> เพื่อยืนยัน</label>
            <div class="flex gap-2">
              <input type="text" name="confirmation" class="confirm-input w-full max-w-[200px] px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  data-required="RESET" placeholder="RESET">
              <button type="submit" class="submit-btn bg-red-500 text-white text-sm font-medium px-4 py-2 rounded-lg whitespace-nowrap opacity-50 transition" disabled>
                <i class="bi bi-trash mr-1"></i> Reset Orders
              </button>
            </div>
          </form>
        </div>

        {{-- Reset Photo Cache --}}
        <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg">
          <div class="font-medium text-sm mb-1">Reset Photo Cache</div>
          <p class="text-gray-500 text-xs mb-3">
            ล้างข้อมูลใน event_photos_cache ทั้งหมด ระบบจะโหลดรูปภาพใหม่ทั้งหมดในครั้งถัดไป
          </p>
          <form method="POST" action="{{ route('admin.settings.reset.perform') }}" class="reset-form">
            @csrf
            <input type="hidden" name="action" value="reset_photo_cache">
            <label class="block text-xs font-medium mb-1">พิมพ์ <code class="bg-gray-200 px-1 rounded">RESET</code> เพื่อยืนยัน</label>
            <div class="flex gap-2">
              <input type="text" name="confirmation" class="confirm-input w-full max-w-[200px] px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  data-required="RESET" placeholder="RESET">
              <button type="submit" class="submit-btn bg-amber-500 text-white text-sm font-medium px-4 py-2 rounded-lg whitespace-nowrap opacity-50 transition" disabled>
                <i class="bi bi-image mr-1"></i> Reset Cache
              </button>
            </div>
          </form>
        </div>

      </div>
    </div>
  </div>

  {{-- ==================== Events & Stats ==================== --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="px-4 pt-4 pb-2">
      <h6 class="font-semibold text-sm">
        <i class="bi bi-calendar-event mr-1 text-indigo-500"></i> Events & Stats
      </h6>
    </div>
    <div class="px-4 pb-4">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">

        {{-- Reset Event Views --}}
        <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg">
          <div class="font-medium text-sm mb-1">Reset Event Views</div>
          <p class="text-gray-500 text-xs mb-3">
            รีเซ็ต view_count ของ events ทั้งหมดให้เป็น 0
          </p>
          <form method="POST" action="{{ route('admin.settings.reset.perform') }}" class="reset-form">
            @csrf
            <input type="hidden" name="action" value="reset_event_views">
            <label class="block text-xs font-medium mb-1">พิมพ์ <code class="bg-gray-200 px-1 rounded">RESET</code> เพื่อยืนยัน</label>
            <div class="flex gap-2">
              <input type="text" name="confirmation" class="confirm-input w-full max-w-[200px] px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  data-required="RESET" placeholder="RESET">
              <button type="submit" class="submit-btn bg-amber-500 text-white text-sm font-medium px-4 py-2 rounded-lg whitespace-nowrap opacity-50 transition" disabled>
                <i class="bi bi-eye-slash mr-1"></i> Reset Views
              </button>
            </div>
          </form>
        </div>

      </div>
    </div>
  </div>

  {{-- ==================== Notifications & Logs ==================== --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="px-4 pt-4 pb-2">
      <h6 class="font-semibold text-sm">
        <i class="bi bi-bell mr-1 text-indigo-500"></i> Notifications & Logs
      </h6>
    </div>
    <div class="px-4 pb-4">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">

        {{-- Reset Notifications --}}
        <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg">
          <div class="font-medium text-sm mb-1">Reset Notifications</div>
          <p class="text-gray-500 text-xs mb-3">
            ล้างข้อมูลใน admin_notifications และ user_notifications ทั้งหมด
          </p>
          <form method="POST" action="{{ route('admin.settings.reset.perform') }}" class="reset-form">
            @csrf
            <input type="hidden" name="action" value="reset_notifications">
            <label class="block text-xs font-medium mb-1">พิมพ์ <code class="bg-gray-200 px-1 rounded">RESET</code> เพื่อยืนยัน</label>
            <div class="flex gap-2">
              <input type="text" name="confirmation" class="confirm-input w-full max-w-[200px] px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  data-required="RESET" placeholder="RESET">
              <button type="submit" class="submit-btn bg-amber-500 text-white text-sm font-medium px-4 py-2 rounded-lg whitespace-nowrap opacity-50 transition" disabled>
                <i class="bi bi-bell-slash mr-1"></i> Reset Notifications
              </button>
            </div>
          </form>
        </div>

        {{-- Reset Security Logs --}}
        <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg">
          <div class="font-medium text-sm mb-1">Reset Security Logs</div>
          <p class="text-gray-500 text-xs mb-3">
            ล้างข้อมูลใน security_logs, security_login_attempts และ security_rate_limits ทั้งหมด
          </p>
          <form method="POST" action="{{ route('admin.settings.reset.perform') }}" class="reset-form">
            @csrf
            <input type="hidden" name="action" value="reset_security_logs">
            <label class="block text-xs font-medium mb-1">พิมพ์ <code class="bg-gray-200 px-1 rounded">RESET</code> เพื่อยืนยัน</label>
            <div class="flex gap-2">
              <input type="text" name="confirmation" class="confirm-input w-full max-w-[200px] px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  data-required="RESET" placeholder="RESET">
              <button type="submit" class="submit-btn bg-amber-500 text-white text-sm font-medium px-4 py-2 rounded-lg whitespace-nowrap opacity-50 transition" disabled>
                <i class="bi bi-shield-slash mr-1"></i> Reset Security Logs
              </button>
            </div>
          </form>
        </div>

      </div>
    </div>
  </div>

  {{-- ==================== Combined ==================== --}}
  <div class="bg-white rounded-xl shadow-sm border border-gray-100">
    <div class="px-4 pt-4 pb-2">
      <h6 class="font-semibold text-sm">
        <i class="bi bi-layers mr-1 text-indigo-500"></i> Combined Reset
      </h6>
    </div>
    <div class="px-4 pb-4">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">

        {{-- Reset All Stats --}}
        <div class="p-3 bg-gray-50 border border-gray-200 rounded-lg">
          <div class="font-medium text-sm mb-1">Reset All Stats</div>
          <p class="text-gray-500 text-xs mb-3">
            รีเซ็ตทุก stats พร้อมกัน: event views, notifications, security logs และ payment logs
          </p>
          <form method="POST" action="{{ route('admin.settings.reset.perform') }}" class="reset-form">
            @csrf
            <input type="hidden" name="action" value="reset_all_stats">
            <label class="block text-xs font-medium mb-1">พิมพ์ <code class="bg-gray-200 px-1 rounded">RESET</code> เพื่อยืนยัน</label>
            <div class="flex gap-2">
              <input type="text" name="confirmation" class="confirm-input w-full max-w-[200px] px-3 py-2 border border-gray-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                  data-required="RESET" placeholder="RESET">
              <button type="submit" class="submit-btn bg-red-500 text-white text-sm font-medium px-4 py-2 rounded-lg whitespace-nowrap opacity-50 transition" disabled>
                <i class="bi bi-bar-chart-line mr-1"></i> Reset All Stats
              </button>
            </div>
          </form>
        </div>

      </div>
    </div>
  </div>

  {{-- ==================== Danger Zone ==================== --}}
  <div class="bg-white rounded-xl shadow-sm border-2 border-red-300" style="box-shadow:0 2px 8px rgba(239,68,68,0.12);">
    <div class="px-4 pt-4 pb-2 bg-gradient-to-r from-red-50 to-white rounded-t-xl">
      <h6 class="font-bold text-sm text-red-600">
        <i class="bi bi-radioactive mr-1"></i> Danger Zone
      </h6>
      <p class="text-red-500 text-xs mt-1">การกระทำในส่วนนี้อาจทำให้ระบบกลับสู่สถานะเริ่มต้นทั้งหมด</p>
    </div>
    <div class="px-4 pb-4">

      {{-- Factory Reset --}}
      <div class="p-4 bg-red-50 border-2 border-dashed border-red-300 rounded-lg">
        <div class="flex items-center gap-2 mb-2">
          <i class="bi bi-exclamation-octagon-fill text-red-600 text-lg"></i>
          <span class="font-bold text-red-600">Factory Reset</span>
        </div>
        <p class="text-gray-500 text-xs mb-3">
          ลบข้อมูล transactional ทั้งหมด (orders, payments, cache, notifications, security logs)
          และรีเซ็ตรหัสผ่าน Admin เป็น <code class="bg-red-100 px-1 rounded">Admin@1234</code>
        </p>
        <form method="POST" action="{{ route('admin.settings.reset.perform') }}" class="reset-form">
          @csrf
          <input type="hidden" name="action" value="factory_reset">
          <label class="block text-xs font-medium text-red-600 mb-1">
            พิมพ์ <code class="bg-red-100 px-1 rounded">FACTORY_RESET</code> เพื่อยืนยัน
          </label>
          <div class="flex flex-wrap gap-2 items-center">
            <input type="text" name="confirmation" class="confirm-input w-full max-w-[260px] px-3 py-2 border border-red-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-red-500 focus:border-red-500"
                data-required="FACTORY_RESET" placeholder="FACTORY_RESET">
            <button type="submit" class="submit-btn bg-red-600 text-white text-sm font-semibold px-5 py-2 rounded-lg whitespace-nowrap opacity-50 transition" disabled>
              <i class="bi bi-nuclear mr-1"></i> Factory Reset
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.confirm-input').forEach(function (input) {
    input.addEventListener('input', function () {
      var form  = input.closest('form');
      var btn  = form.querySelector('.submit-btn');
      var match = input.value === input.dataset.required;
      btn.disabled = !match;
      btn.style.opacity = match ? '1' : '0.5';
    });
  });

  document.querySelectorAll('.reset-form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var action = form.querySelector('[name="action"]').value;
      var msg = action === 'factory_reset'
        ? 'คุณแน่ใจหรือไม่? การ Factory Reset จะไม่สามารถย้อนกลับได้!'
        : 'คุณแน่ใจหรือไม่? การรีเซ็ตข้อมูลไม่สามารถย้อนกลับได้';
      if (!confirm(msg)) {
        e.preventDefault();
      }
    });
  });
});
</script>
@endsection
