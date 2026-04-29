@extends('layouts.admin')

@section('title', 'เพิ่มช่างภาพ')

@section('content')
<div class="mb-6">
  <a href="{{ route('admin.photographers.index') }}" class="inline-flex items-center text-sm text-gray-500 hover:text-indigo-600 transition">
    <i class="bi bi-arrow-left mr-1"></i> กลับไปรายการช่างภาพ
  </a>
</div>

<div class="flex justify-between items-center mb-6">
  <div>
    <h4 class="font-bold mb-1 text-xl tracking-tight">
      <i class="bi bi-person-plus mr-2 text-indigo-500"></i>เพิ่มช่างภาพใหม่
    </h4>
    <p class="text-gray-500 mb-0 text-sm">สร้างโปรไฟล์ช่างภาพจากบัญชีผู้ใช้ที่มีอยู่ในระบบ</p>
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

<form method="POST" action="{{ route('admin.photographers.store') }}">
  @csrf

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    {{-- ── Left Column ── --}}
    <div class="lg:col-span-2 space-y-6">

      {{-- User Selection --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="font-semibold text-sm"><i class="bi bi-person-check mr-1 text-blue-500"></i>เลือกผู้ใช้</h6>
        </div>
        <div class="p-6" x-data="{
          search: '',
          selectedId: '{{ old('user_id', '') }}',
          selectedName: '',
          get filteredUsers() {
            if (!this.search) return [];
            const s = this.search.toLowerCase();
            return window.__users?.filter(u =>
              u.name.toLowerCase().includes(s) || u.email.toLowerCase().includes(s)
            ).slice(0, 10) || [];
          }
        }">
          <input type="hidden" name="user_id" :value="selectedId">

          {{-- Selected user display --}}
          <div x-show="selectedId" class="mb-3 p-3 rounded-xl bg-indigo-50 dark:bg-indigo-500/10 flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-lg bg-indigo-500/20 text-indigo-600 flex items-center justify-center text-sm font-bold"
                   x-text="selectedName.charAt(0)?.toUpperCase()"></div>
              <div>
                <div class="font-medium text-sm text-indigo-700 dark:text-indigo-300" x-text="selectedName"></div>
                <div class="text-xs text-indigo-500">User ID: <span x-text="selectedId"></span></div>
              </div>
            </div>
            <button type="button" @click="selectedId = ''; selectedName = ''; search = ''" class="text-indigo-400 hover:text-indigo-600 text-lg">&times;</button>
          </div>

          {{-- Search --}}
          <div x-show="!selectedId">
            <div class="relative">
              <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
              <input type="text" x-model="search" placeholder="ค้นหาชื่อหรืออีเมลผู้ใช้..."
                     class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
            </div>
            {{-- Dropdown --}}
            <div x-show="search.length >= 2 && filteredUsers.length > 0" x-transition
                 class="mt-1 border border-gray-200 dark:border-white/10 rounded-xl overflow-hidden shadow-lg bg-white dark:bg-slate-800 max-h-60 overflow-y-auto">
              <template x-for="user in filteredUsers" :key="user.id">
                <button type="button"
                        @click="selectedId = user.id; selectedName = user.name + ' (' + user.email + ')'; search = ''"
                        class="w-full flex items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-white/[0.04] text-left transition border-b border-gray-100 dark:border-white/[0.04] last:border-0">
                  <div class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-white/10 flex items-center justify-center text-sm font-bold text-gray-500"
                       x-text="user.name.charAt(0)?.toUpperCase()"></div>
                  <div class="min-w-0">
                    <div class="font-medium text-sm truncate" x-text="user.name"></div>
                    <div class="text-xs text-gray-400 truncate" x-text="user.email"></div>
                  </div>
                  <span class="ml-auto text-xs text-gray-400" x-text="'ID: ' + user.id"></span>
                </button>
              </template>
            </div>
            <div x-show="search.length >= 2 && filteredUsers.length === 0" class="mt-1 p-3 text-center text-sm text-gray-400">
              ไม่พบผู้ใช้
            </div>
          </div>

          <p class="text-xs text-gray-400 mt-2"><i class="bi bi-info-circle mr-0.5"></i>แสดงเฉพาะผู้ใช้ที่ยังไม่มีโปรไฟล์ช่างภาพ</p>
        </div>
      </div>

      {{-- Profile Section --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="font-semibold text-sm"><i class="bi bi-person-badge mr-1 text-indigo-500"></i>ข้อมูลโปรไฟล์</h6>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">ชื่อที่แสดง <span class="text-red-500">*</span></label>
            <input type="text" name="display_name" value="{{ old('display_name') }}" required
                   class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Bio</label>
            <textarea name="bio" rows="4"
                      class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">{{ old('bio') }}</textarea>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Portfolio URL</label>
            <div class="flex">
              <span class="inline-flex items-center px-3 rounded-l-xl border border-r-0 border-gray-200 bg-gray-50 text-gray-500 text-sm dark:bg-slate-700 dark:border-white/10">
                <i class="bi bi-globe"></i>
              </span>
              <input type="url" name="portfolio_url" value="{{ old('portfolio_url') }}"
                     placeholder="https://example.com"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-r-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
            </div>
          </div>
        </div>
      </div>

      {{-- Bank Info Section --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="font-semibold text-sm"><i class="bi bi-bank mr-1 text-emerald-500"></i>ข้อมูลบัญชีธนาคาร <span class="text-gray-400 font-normal">(ไม่จำเป็น)</span></h6>
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
              <option value="{{ $bank }}" {{ old('bank_name') === $bank ? 'selected' : '' }}>{{ $bank }}</option>
              @endforeach
            </select>
          </div>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">เลขบัญชี</label>
              <input type="text" name="bank_account_number" value="{{ old('bank_account_number') }}"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">ชื่อบัญชี</label>
              <input type="text" name="bank_account_name" value="{{ old('bank_account_name') }}"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">PromptPay</label>
            <div class="flex">
              <span class="inline-flex items-center px-3 rounded-l-xl border border-r-0 border-gray-200 bg-gray-50 text-gray-500 text-sm dark:bg-slate-700 dark:border-white/10">
                <i class="bi bi-phone"></i>
              </span>
              <input type="text" name="promptpay_number" value="{{ old('promptpay_number') }}"
                     placeholder="08x-xxx-xxxx"
                     class="w-full px-4 py-2.5 border border-gray-200 rounded-r-xl text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ── Right Column ── --}}
    <div class="space-y-6">
      {{-- Status --}}
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="font-semibold text-sm"><i class="bi bi-toggle-on mr-1 text-emerald-500"></i>สถานะเริ่มต้น</h6>
        </div>
        <div class="p-6">
          <div class="space-y-3">
            <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-white/10 cursor-pointer hover:bg-gray-50 dark:hover:bg-white/[0.03] transition">
              <input type="radio" name="status" value="approved" {{ old('status', 'approved') === 'approved' ? 'checked' : '' }}
                     class="w-4 h-4 text-indigo-600 focus:ring-indigo-500">
              <div>
                <span class="font-medium text-sm">อนุมัติทันที</span>
                <p class="text-xs text-gray-400 mb-0">ช่างภาพสามารถเข้าใช้งานได้เลย</p>
              </div>
            </label>
            <label class="flex items-center gap-3 p-3 rounded-xl border border-gray-200 dark:border-white/10 cursor-pointer hover:bg-gray-50 dark:hover:bg-white/[0.03] transition">
              <input type="radio" name="status" value="pending" {{ old('status') === 'pending' ? 'checked' : '' }}
                     class="w-4 h-4 text-indigo-600 focus:ring-indigo-500">
              <div>
                <span class="font-medium text-sm">รอตรวจสอบ</span>
                <p class="text-xs text-gray-400 mb-0">ต้องอนุมัติก่อนจึงจะเข้าใช้งานได้</p>
              </div>
            </label>
          </div>
        </div>
      </div>

      {{-- Commission --}}
      @php
        $cMin = (float) \App\Models\AppSetting::get('min_commission_rate', 0);
        $cMax = (float) \App\Models\AppSetting::get('max_commission_rate', 100);
        // Default to the configured photographer share so the prefilled
        // number matches whatever the Commission Settings screen promises.
        $cDefault = (float) \App\Models\AppSetting::get(
            'photographer_commission_rate',
            100 - (float) \App\Models\AppSetting::get('platform_commission', 20)
        );
        $cDefault = max($cMin, min($cMax, $cDefault));
        $platformShare = 100 - $cDefault;
      @endphp
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 dark:bg-slate-800 dark:border-white/[0.06]">
        <div class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.06]">
          <h6 class="font-semibold text-sm"><i class="bi bi-percent mr-1 text-indigo-500"></i>ค่าคอมมิชชั่น</h6>
        </div>
        <div class="p-6">
          <div class="flex items-center gap-3">
            <input type="number" name="commission_rate" value="{{ old('commission_rate', $cDefault) }}" min="{{ $cMin }}" max="{{ $cMax }}" step="1" required
                   class="w-24 px-4 py-2.5 border border-gray-200 rounded-xl text-sm text-center font-semibold focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 dark:bg-slate-700 dark:border-white/10 dark:text-gray-100">
            <span class="text-gray-500 text-sm font-medium">% ช่างภาพได้รับ <small class="text-gray-400">(ต้องอยู่ระหว่าง {{ $cMin }}–{{ $cMax }}%)</small></span>
          </div>
          <p class="text-xs text-gray-400 mt-2"><i class="bi bi-info-circle mr-0.5"></i>ค่าเริ่มต้น {{ $cDefault }}% — แพลตฟอร์มได้ {{ $platformShare }}%</p>
        </div>
      </div>

      {{-- Save Button --}}
      <button type="submit" class="w-full inline-flex items-center justify-center py-3 bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-xl font-medium transition hover:from-indigo-600 hover:to-indigo-700">
        <i class="bi bi-person-plus mr-1"></i> สร้างช่างภาพ
      </button>
    </div>
  </div>
</form>

@push('scripts')
<script>
  // Pass users list to Alpine
  window.__users = @json($users->map(fn($u) => ['id' => $u->id, 'name' => $u->first_name . ' ' . $u->last_name, 'email' => $u->email]));
</script>
@endpush
@endsection
