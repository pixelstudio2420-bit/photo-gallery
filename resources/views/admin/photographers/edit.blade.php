@extends('layouts.admin')

@section('title', 'แก้ไขช่างภาพ — ' . $photographer->display_name)

@section('content')
<div class="mb-6">
  <a href="{{ route('admin.photographers.show', $photographer) }}" class="inline-flex items-center text-sm text-gray-500 hover:text-indigo-600 transition">
    <i class="bi bi-arrow-left mr-1"></i> กลับไปหน้ารายละเอียด
  </a>
</div>

<div class="flex justify-between items-center mb-6 flex-wrap gap-2">
  <div>
    <h4 class="font-bold mb-1 text-xl tracking-tight">
      <i class="bi bi-pencil-square mr-2 text-indigo-500"></i>แก้ไขช่างภาพ
    </h4>
    <p class="text-gray-500 mb-0 text-sm">{{ $photographer->display_name }} — {{ $photographer->photographer_code }}</p>
  </div>
</div>

@if($errors->any())
<div class="mb-4 flex items-start gap-2 px-4 py-3 rounded-xl bg-red-50 text-red-700 text-sm border border-red-100 dark:bg-red-500/10 dark:text-red-400 dark:border-red-500/20">
  <i class="bi bi-exclamation-circle-fill mt-0.5"></i>
  <ul class="list-disc list-inside space-y-0.5">
    @foreach($errors->all() as $error)
    <li>{{ $error }}</li>
    @endforeach
  </ul>
</div>
@endif

<form method="POST" action="{{ route('admin.photographers.update', $photographer) }}">
  @csrf
  @method('PUT')

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- ── Left Column: Profile Info ── --}}
    <div class="lg:col-span-2 space-y-6">
      {{-- Profile Section --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="font-semibold text-sm"><i class="bi bi-person-badge mr-1 text-indigo-500"></i>ข้อมูลโปรไฟล์</h6>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">ชื่อที่แสดง <span class="text-red-500">*</span></label>
            <input type="text" name="display_name" value="{{ old('display_name', $photographer->display_name) }}" required
                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Bio</label>
            <textarea name="bio" rows="4"
                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">{{ old('bio', $photographer->bio) }}</textarea>
            <small class="text-gray-400 mt-1">สูงสุด 1,000 ตัวอักษร</small>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Portfolio URL</label>
            <div class="flex">
              <span class="inline-flex items-center px-3 rounded-l-xl border border-r-0 border-gray-200 bg-gray-50 text-gray-500 text-sm dark:bg-slate-700 dark:border-white/10">
                <i class="bi bi-globe"></i>
              </span>
              <input type="url" name="portfolio_url" value="{{ old('portfolio_url', $photographer->portfolio_url) }}"
                     placeholder="https://example.com"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-r-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
            </div>
          </div>
        </div>
      </div>

      {{-- Bank Info Section --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="font-semibold text-sm"><i class="bi bi-bank mr-1 text-emerald-500"></i>ข้อมูลบัญชีธนาคาร</h6>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">ชื่อธนาคาร</label>
            <select name="bank_name" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
              <option value="">-- เลือกธนาคาร --</option>
              @php
                $banks = ['กสิกรไทย (KBANK)', 'กรุงเทพ (BBL)', 'กรุงไทย (KTB)', 'ไทยพาณิชย์ (SCB)', 'กรุงศรี (BAY)', 'ทหารไทยธนชาต (TTB)', 'ออมสิน (GSB)', 'ธ.ก.ส. (BAAC)', 'เกียรตินาคินภัทร (KKP)', 'ซีไอเอ็มบี (CIMBT)', 'ยูโอบี (UOBT)', 'แลนด์ แอนด์ เฮ้าส์ (LHBANK)'];
              @endphp
              @foreach($banks as $bank)
              <option value="{{ $bank }}" {{ old('bank_name', $photographer->bank_name) === $bank ? 'selected' : '' }}>{{ $bank }}</option>
              @endforeach
            </select>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">เลขบัญชี</label>
              <input type="text" name="bank_account_number" value="{{ old('bank_account_number', $photographer->bank_account_number) }}"
                     placeholder="xxx-x-xxxxx-x"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">ชื่อบัญชี</label>
              <input type="text" name="bank_account_name" value="{{ old('bank_account_name', $photographer->bank_account_name) }}"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">หมายเลข PromptPay</label>
            <div class="flex">
              <span class="inline-flex items-center px-3 rounded-l-xl border border-r-0 border-gray-200 bg-gray-50 text-gray-500 text-sm dark:bg-slate-700 dark:border-white/10">
                <i class="bi bi-phone"></i>
              </span>
              <input type="text" name="promptpay_number" value="{{ old('promptpay_number', $photographer->promptpay_number) }}"
                     placeholder="08x-xxx-xxxx หรือ เลขบัตรประชาชน"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-r-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ── Right Column: Settings ── --}}
    <div class="space-y-6">
      {{-- Status --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="font-semibold text-sm"><i class="bi bi-toggle-on mr-1 text-emerald-500"></i>สถานะ</h6>
        </div>
        <div class="p-6">
          <select name="status" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
            <option value="pending" {{ old('status', $photographer->status) === 'pending' ? 'selected' : '' }}>รอตรวจสอบ (Pending)</option>
            <option value="approved" {{ old('status', $photographer->status) === 'approved' ? 'selected' : '' }}>อนุมัติ (Approved)</option>
            <option value="suspended" {{ old('status', $photographer->status) === 'suspended' ? 'selected' : '' }}>ระงับ (Suspended)</option>
          </select>
          @php
            $stMap = [
              'pending' => ['color' => 'amber', 'desc' => 'ช่างภาพยังไม่ได้รับการอนุมัติ ไม่สามารถเข้าใช้งานระบบได้'],
              'approved' => ['color' => 'emerald', 'desc' => 'ช่างภาพสามารถเข้าใช้งานระบบได้ตามปกติ'],
              'suspended' => ['color' => 'red', 'desc' => 'ช่างภาพถูกระงับ ไม่สามารถเข้าใช้งานระบบได้'],
            ];
            $curSt = $stMap[$photographer->status] ?? $stMap['pending'];
          @endphp
          <p class="text-xs text-gray-400 mt-2"><i class="bi bi-info-circle mr-0.5"></i>{{ $curSt['desc'] }}</p>
        </div>
      </div>

      {{-- Commission --}}
      @php
        // Mirror the server-side bounds from ResolvesCommissionBounds so the
        // HTML5 validation matches what the backend will accept. Avoids
        // "you can type it but the form rejects it" confusion.
        $cMin = (float) \App\Models\AppSetting::get('min_commission_rate', 0);
        $cMax = (float) \App\Models\AppSetting::get('max_commission_rate', 100);
      @endphp
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="font-semibold text-sm"><i class="bi bi-percent mr-1 text-indigo-500"></i>ค่าคอมมิชชั่น</h6>
        </div>
        <div class="p-6">
          <div class="flex items-center gap-3">
            <input type="number" name="commission_rate" value="{{ old('commission_rate', $photographer->commission_rate) }}" min="{{ $cMin }}" max="{{ $cMax }}" step="1" required
                   class="w-24 px-4 py-2.5 border border-gray-200 rounded-xl text-sm text-center font-semibold focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
            <span class="text-gray-500 text-sm font-medium">% ช่างภาพได้รับ <small class="text-gray-400">(ต้องอยู่ระหว่าง {{ $cMin }}–{{ $cMax }}%)</small></span>
          </div>
          <div class="mt-3 p-3 rounded-lg bg-gray-50 dark:bg-white/[0.03]">
            <div class="flex justify-between text-sm">
              <span class="text-gray-500">ช่างภาพได้รับ</span>
              <span class="font-semibold text-indigo-600">{{ number_format($photographer->commission_rate, 0) }}%</span>
            </div>
            <div class="flex justify-between text-sm mt-1">
              <span class="text-gray-500">แพลตฟอร์มได้รับ</span>
              <span class="font-semibold text-gray-500">{{ number_format(100 - $photographer->commission_rate, 0) }}%</span>
            </div>
          </div>
        </div>
      </div>

      {{-- Account Info (read-only) --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="font-semibold text-sm"><i class="bi bi-info-circle mr-1 text-blue-500"></i>ข้อมูลบัญชี</h6>
        </div>
        <div class="p-6 space-y-3 text-sm">
          <div class="flex justify-between">
            <span class="text-gray-500">User ID</span>
            <span class="font-medium">{{ $photographer->user_id }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">อีเมล</span>
            <span class="font-medium">{{ $photographer->user->email ?? '-' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">ชื่อ-นามสกุล</span>
            <span class="font-medium">{{ ($photographer->user->first_name ?? '') . ' ' . ($photographer->user->last_name ?? '') }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">รหัสช่างภาพ</span>
            <span class="font-mono text-indigo-600">{{ $photographer->photographer_code }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-gray-500">สมัครเมื่อ</span>
            <span>{{ $photographer->created_at?->format('d/m/Y H:i') }}</span>
          </div>
        </div>
      </div>

      {{-- Save Button --}}
      <button type="submit" class="w-full inline-flex items-center justify-center py-3 bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-xl font-medium transition hover:from-indigo-600 hover:to-indigo-700">
        <i class="bi bi-check-lg mr-1"></i> บันทึกการเปลี่ยนแปลง
      </button>
    </div>
  </div>
</form>
@endsection
