@extends('layouts.admin')

@section('title', 'Google Drive Settings')

@push('styles')
<style>
/* ─── Page Layout ─── */
.drive-page-header { letter-spacing: -0.02em; }
.section-back-btn {
  background: rgba(99,102,241,0.08);
  color: #6366f1;
  border-radius: 8px;
  font-weight: 500;
  border: none;
  padding: 0.4rem 1.1rem;
  font-size: 0.875rem;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  transition: background .15s;
}
.section-back-btn:hover { background: rgba(99,102,241,0.14); color: #6366f1; }

/* ─── Cards ─── */
.setting-card {
  border: none;
  border-radius: 14px;
  box-shadow: 0 2px 12px rgba(0,0,0,0.06);
}
.setting-card .px-5 py-4 border-b border-gray-100 {
  background: transparent;
  border-b: 1px solid rgba(0,0,0,0.05);
  border-radius: 14px 14px 0 0 !important;
  padding: 1.1rem 1.5rem;
}
.setting-card .p-5 { padding: 1.5rem; }
.card-icon {
  width: 36px; height: 36px;
  border-radius: 10px;
  display: inline-flex; align-items: center; justify-content: center;
  font-size: 1.1rem;
}

/* ─── Form Controls ─── */
.block text-sm font-medium text-gray-700 mb-1.5 { font-weight: 600; font-size: 0.875rem; color: #374151; margin-bottom: 0.4rem; }
.w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500, .form-select {
  border-radius: 10px;
  border: 1.5px solid #e5e7eb;
  font-size: 0.9rem;
  transition: border-color .2s, box-shadow .2s;
}
.w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500:focus, .form-select:focus {
  border-color: #6366f1;
  box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
}
.w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500[readonly] {
  background: #f8fafc;
  color: #6b7280;
  cursor: default;
}
.px-3 py-2.5 bg-gray-50 border border-gray-300 text-gray-500 {
  border-radius: 0 10px 10px 0;
  border: 1.5px solid #e5e7eb;
  border-left: none;
  background: #f8fafc;
  cursor: pointer;
  transition: background .15s;
}
.px-3 py-2.5 bg-gray-50 border border-gray-300 text-gray-500:hover { background: #f1f5f9; }
.input-group .w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 { border-radius: 10px 0 0 10px; }

/* ─── Toggle Switch ─── */
.form-switch .w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500 {
  width: 3em; height: 1.5em;
  cursor: pointer;
}
.form-switch .w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500:checked { background-color: #6366f1; border-color: #6366f1; }
.form-switch .text-sm text-gray-600 { font-weight: 500; font-size: 0.9rem; cursor: pointer; }

/* ─── Status Badge ─── */
.status-dot {
  display: inline-flex; align-items: center; gap: 0.4rem;
  font-size: 0.82rem; font-weight: 600; padding: 0.3rem 0.75rem;
  border-radius: 999px;
}
.status-dot.connected { background: rgba(16,185,129,0.1); color: #059669; }
.status-dot.disconnected { background: rgba(239,68,68,0.1); color: #dc2626; }
.status-dot.unknown { background: rgba(107,114,128,0.1); color: #6b7280; }
.status-dot::before {
  content: '';
  width: 7px; height: 7px;
  border-radius: 50%;
  background: currentColor;
  flex-shrink: 0;
}

/* ─── Help Text ─── */
.help-text {
  font-size: 0.78rem;
  color: #6b7280;
  margin-top: 0.35rem;
  line-height: 1.5;
}

/* ─── Toggle Row ─── */
.toggle-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.85rem 1rem;
  border-radius: 10px;
  background: #f8fafc;
  border: 1px solid #f1f5f9;
  transition: background .15s;
}
.toggle-row:hover { background: #f1f5f9; }
.toggle-row .toggle-info .toggle-title { font-weight: 600; font-size: 0.9rem; color: #1f2937; }
.toggle-row .toggle-info .toggle-desc { font-size: 0.78rem; color: #6b7280; margin-top: 2px; }

/* ─── Redirect URI Box ─── */
.uri-box {
  background: #f8fafc;
  border: 1.5px dashed #d1d5db;
  border-radius: 10px;
  padding: 0.65rem 1rem;
  font-family: 'Courier New', monospace;
  font-size: 0.82rem;
  color: #374151;
  display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;
  word-break: break-all;
}
.copy-btn {
  flex-shrink: 0;
  padding: 0.25rem 0.6rem;
  font-size: 0.75rem;
  border-radius: 6px;
  border: 1px solid #d1d5db;
  background: white;
  color: #6b7280;
  cursor: pointer;
  transition: all .15s;
}
.copy-btn:hover { border-color: #6366f1; color: #6366f1; }
.copy-btn.copied { border-color: #059669; color: #059669; background: rgba(16,185,129,0.06); }

/* ─── Test Button ─── */
.btn-test {
  background: rgba(99,102,241,0.08);
  color: #6366f1;
  border: 1.5px solid rgba(99,102,241,0.2);
  border-radius: 10px;
  font-weight: 600;
  font-size: 0.85rem;
  padding: 0.5rem 1.2rem;
  transition: all .2s;
}
.btn-test:hover {
  background: rgba(99,102,241,0.15);
  color: #4f46e5;
  border-color: rgba(99,102,241,0.35);
}
.btn-test:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-test .spinner-border { width: 1rem; height: 1rem; border-width: 2px; }

/* ─── Save Button ─── */
.btn-save-gradient {
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: #fff;
  border: none;
  border-radius: 10px;
  font-weight: 600;
  padding: 0.65rem 2rem;
  font-size: 0.95rem;
  transition: all .2s;
  box-shadow: 0 2px 8px rgba(99,102,241,0.25);
}
.btn-save-gradient:hover {
  background: linear-gradient(135deg, #4f46e5, #4338ca);
  color: #fff;
  box-shadow: 0 4px 14px rgba(99,102,241,0.35);
  transform: translateY(-1px);
}

/* ─── Action Buttons ─── */
.btn-action {
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.82rem;
  padding: 0.4rem 1rem;
  transition: all .15s;
  border: 1.5px solid transparent;
}
.btn-action:disabled { opacity: 0.55; cursor: not-allowed; }
.btn-action .spinner-border { width: 0.85rem; height: 0.85rem; border-width: 2px; }

.btn-action-indigo {
  background: rgba(99,102,241,0.08); color: #6366f1; border-color: rgba(99,102,241,0.2);
}
.btn-action-indigo:hover { background: rgba(99,102,241,0.15); color: #4f46e5; }

.btn-action-green {
  background: rgba(16,185,129,0.08); color: #059669; border-color: rgba(16,185,129,0.2);
}
.btn-action-green:hover { background: rgba(16,185,129,0.15); color: #047857; }

.btn-action-amber {
  background: rgba(245,158,11,0.08); color: #d97706; border-color: rgba(245,158,11,0.2);
}
.btn-action-amber:hover { background: rgba(245,158,11,0.15); color: #b45309; }

.btn-action-red {
  background: rgba(239,68,68,0.08); color: #dc2626; border-color: rgba(239,68,68,0.2);
}
.btn-action-red:hover { background: rgba(239,68,68,0.15); color: #b91c1c; }

/* ─── Section Accent Colors ─── */
.accent-drive { color: #4285f4; }
.accent-oauth { color: #ea4335; }
.accent-sync { color: #34a853; }
.accent-perf { color: #fbbc04; }
.accent-queue { color: #6366f1; }
.accent-manual { color: #8b5cf6; }

.bg-accent-drive { background: rgba(66,133,244,0.1); }
.bg-accent-oauth { background: rgba(234,67,53,0.1); }
.bg-accent-sync { background: rgba(52,168,83,0.1); }
.bg-accent-perf { background: rgba(251,188,4,0.1); }
.bg-accent-queue { background: rgba(99,102,241,0.1); }
.bg-accent-manual { background: rgba(139,92,246,0.1); }

/* ─── Stat Cards ─── */
.stat-card {
  border-radius: 12px;
  padding: 1rem 1.15rem;
  border: 1px solid rgba(0,0,0,0.04);
  transition: transform .15s, box-shadow .15s;
}
.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}
.stat-card .stat-number {
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1;
  letter-spacing: -0.02em;
}
.stat-card .stat-label {
  font-size: 0.78rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  margin-top: 0.3rem;
}

.stat-pending { background: rgba(245,158,11,0.06); }
.stat-pending .stat-number { color: #f59e0b; }
.stat-pending .stat-label { color: #92400e; }

.stat-processing { background: rgba(59,130,246,0.06); }
.stat-processing .stat-number { color: #3b82f6; }
.stat-processing .stat-label { color: #1e40af; }

.stat-completed { background: rgba(16,185,129,0.06); }
.stat-completed .stat-number { color: #10b981; }
.stat-completed .stat-label { color: #065f46; }

.stat-failed { background: rgba(239,68,68,0.06); }
.stat-failed .stat-number { color: #ef4444; }
.stat-failed .stat-label { color: #991b1b; }

/* ─── Queue Table ─── */
.queue-table {
  font-size: 0.85rem;
  margin-bottom: 0;
}
.queue-table th {
  font-weight: 600;
  font-size: 0.78rem;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  border-b: 2px solid #f1f5f9;
  padding: 0.65rem 0.75rem;
  white-space: nowrap;
}
.queue-table td {
  padding: 0.65rem 0.75rem;
  vertical-align: middle;
  border-b: 1px solid #f8fafc;
  color: #374151;
}
.queue-table tbody tr:hover { background: rgba(99,102,241,0.02); }

.badge-status {
  font-size: 0.72rem;
  font-weight: 600;
  padding: 0.25rem 0.6rem;
  border-radius: 6px;
  text-transform: capitalize;
}
.badge-pending  { background: rgba(245,158,11,0.12); color: #b45309; }
.badge-processing{ background: rgba(59,130,246,0.12); color: #1d4ed8; }
.badge-completed { background: rgba(16,185,129,0.12); color: #047857; }
.badge-failed  { background: rgba(239,68,68,0.12); color: #b91c1c; }

/* ─── Scopes List ─── */
.scope-tag {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  padding: 0.25rem 0.65rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 500;
  font-family: 'Courier New', monospace;
  background: rgba(99,102,241,0.06);
  color: #4f46e5;
  border: 1px solid rgba(99,102,241,0.12);
}

/* ─── Live Indicator ─── */
.live-dot {
  width: 8px; height: 8px;
  border-radius: 50%;
  background: #10b981;
  display: inline-block;
  animation: livePulse 2s ease-in-out infinite;
}
@keyframes livePulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.5; transform: scale(0.85); }
}

/* ─── Empty State ─── */
.empty-state {
  text-align: center;
  padding: 2.5rem 1rem;
  color: #9ca3af;
}
.empty-state i { font-size: 2rem; margin-bottom: 0.5rem; display: block; opacity: 0.5; }
.empty-state p { font-size: 0.85rem; margin: 0; }
</style>
@endpush

@section('content')

{{-- Page Header --}}
<div class="flex items-center justify-between mb-4">
  <div>
    <h4 class="font-bold mb-0 drive-page-header">
      <span style="display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:10px;margin-right:10px;vertical-align:middle;background:linear-gradient(135deg,#4285f4,#34a853);">
        <i class="bi bi-google" style="color:#fff;font-size:1.1rem;"></i>
      </span>
      Google Drive Settings
    </h4>
    <p class="text-gray-500 small mb-0 mt-1" style="padding-left:46px;">จัดการการเชื่อมต่อ Google Drive, OAuth และการซิงค์รูปภาพ</p>
  </div>
  <a href="{{ route('admin.settings.index') }}" class="section-back-btn">
    <i class="bi bi-arrow-left"></i> กลับ
  </a>
</div>

{{-- Flash Messages --}}
@if(session('success'))
  <div class="alert border-0 mb-4" style="background:rgba(16,185,129,0.08);color:#059669;border-radius:12px;">
    <i class="bi bi-check-circle mr-1"></i>{{ session('success') }}
    <button type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer" onclick="this.parentElement.remove()" style="font-size:0.7rem;"></button>
  </div>
@endif
@if(session('error'))
  <div class="alert border-0 mb-4" style="background:rgba(239,68,68,0.08);color:#dc2626;border-radius:12px;">
    <i class="bi bi-exclamation-circle mr-1"></i>{{ session('error') }}
    <button type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer" onclick="this.parentElement.remove()" style="font-size:0.7rem;"></button>
  </div>
@endif

<form method="POST" action="{{ route('admin.settings.google-drive.update') }}" id="driveSettingsForm" enctype="multipart/form-data">
  @csrf

  {{-- ════════════════════════════════════════════════════════
     Section 1: Google Drive API
  ════════════════════════════════════════════════════════ --}}
  <div class="card setting-card mb-4">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <div class="card-icon bg-accent-drive">
          <i class="bi bi-hdd-network accent-drive"></i>
        </div>
        <div>
          <h6 class="font-bold mb-0">Google Drive API</h6>
          <p class="text-gray-500 small mb-0">API Key สำหรับเข้าถึง Google Drive (อ่านไฟล์สาธารณะ)</p>
        </div>
      </div>
      <span class="status-dot {{ ($settings['google_drive_api_key'] ?? '') ? 'connected' : 'disconnected' }}" id="apiStatus">
        {{ ($settings['google_drive_api_key'] ?? '') ? 'Connected' : 'Not configured' }}
      </span>
    </div>
    <div class="p-5">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {{-- API Key --}}
        <div class="lg:col-span-2">
          <label class="block text-sm font-medium text-gray-700 mb-1.5" for="google_drive_api_key">
            Google Drive API Key
          </label>
          <div class="flex">
            <input type="password" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="google_drive_api_key" id="google_drive_api_key"
                value="{{ $settings['google_drive_api_key'] ?? '' }}"
                placeholder="{{ ($settings['google_drive_api_key'] ?? '') ? 'Key saved (enter new to change)' : 'AIzaSy...' }}"
                autocomplete="new-password">
            <span class="px-3 py-2.5 bg-gray-50 border border-gray-300 text-gray-500 toggle-pw" data-target="google_drive_api_key">
              <i class="bi bi-eye"></i>
            </span>
          </div>
          <div class="help-text">
            <i class="bi bi-info-circle mr-1"></i>
            รับ API Key ได้จาก <a href="https://console.cloud.google.com/apis/credentials" target="_blank" rel="noopener" style="color:#6366f1;">Google Cloud Console</a>
            &rarr; Credentials &rarr; Create Credentials &rarr; API key
          </div>
        </div>

        {{-- Connection Test --}}
        <div class=" flex items-end">
          <button type="button" class="btn btn-test w-full" id="btnTestDriveApi">
            <i class="bi bi-plug mr-1"></i>ทดสอบการเชื่อมต่อ
            <span class="spinner-border spinner-border-sm ml-1 hidden" role="status"></span>
          </button>
        </div>
        <div class="">
          <span class="small hidden" id="driveTestResult"></span>
        </div>
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════
     Section 1.5: Service Account (OAuth2) ★ แนะนำ
  ════════════════════════════════════════════════════════ --}}
  @php
    $saEmail = \App\Models\AppSetting::get('google_service_account_email', '');
    $hasSA  = !empty($saEmail);
  @endphp
  <div class="card setting-card mb-4" style="border:2px solid {{ $hasSA ? '#10b981' : '#6366f1' }} !important;">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between" style="background:{{ $hasSA ? 'rgba(16,185,129,0.04)' : 'rgba(99,102,241,0.04)' }};">
      <div class="flex items-center gap-2">
        <div class="card-icon" style="background:{{ $hasSA ? 'rgba(16,185,129,0.12)' : 'rgba(99,102,241,0.12)' }}">
          <i class="bi bi-shield-lock" style="color:{{ $hasSA ? '#10b981' : '#6366f1' }}"></i>
        </div>
        <div>
          <h6 class="font-bold mb-0">
            Service Account (OAuth2)
            <span class="badge rounded-full ml-1" style="font-size:0.6rem;background:#6366f1;vertical-align:middle;">แนะนำ</span>
          </h6>
          <p class="text-gray-500 small mb-0">Google เปลี่ยนเป็น OAuth2 แล้ว — ต้องใช้ Service Account แทน API Key</p>
        </div>
      </div>
      @if($hasSA)
      <span class="bg-green-100 text-green-700 text-xs font-medium px-2.5 py-0.5 rounded-full rounded-full px-3 py-2">
        <i class="bi bi-check-circle mr-1"></i>Active
      </span>
      @else
      <span class="bg-yellow-100 text-yellow-700 text-xs font-medium px-2.5 py-0.5 rounded-full text-dark rounded-full px-3 py-2">
        <i class="bi bi-exclamation-triangle mr-1"></i>ยังไม่ได้ตั้งค่า
      </span>
      @endif
    </div>
    <div class="p-5">
      @if($hasSA)
        {{-- Show current Service Account info --}}
        <div class="flex items-center gap-3 mb-3 p-3 rounded-xl" style="background:rgba(16,185,129,0.06);border:1px solid rgba(16,185,129,0.15);">
          <div class="flex items-center justify-center rounded-full" style="width:44px;height:44px;background:rgba(16,185,129,0.12);flex-shrink:0;">
            <i class="bi bi-person-badge" style="font-size:1.3rem;color:#10b981;"></i>
          </div>
          <div>
            <div class="font-semibold" style="font-size:0.9rem;">{{ $saEmail }}</div>
            <div class="text-gray-500" style="font-size:0.75rem;">Service Account ใช้งานอยู่ — เชื่อมต่อ Drive API ผ่าน OAuth2</div>
          </div>
          <button type="submit" name="remove_service_account" value="1" class="text-sm px-3 py-1.5 rounded-lg btn-outline-danger ml-auto"
              onclick="return confirm('ต้องการลบ Service Account นี้?')">
            <i class="bi bi-trash mr-1"></i>ลบ
          </button>
        </div>
        <div class="help-text mb-0">
          <i class="bi bi-lightbulb mr-1 text-yellow-600"></i>
          อย่าลืม <strong>แชร์โฟลเดอร์ Google Drive</strong> ให้กับ <code>{{ $saEmail }}</code> (ระดับ Viewer ขึ้นไป)
        </div>
      @else
        {{-- Upload form --}}
        <div class="mb-3">
          <label class="block text-sm font-medium text-gray-700 mb-1.5 font-semibold">อัปโหลด Service Account JSON Key</label>
          <label class="w-full relative" style="cursor:pointer;">
            <div class="border rounded-xl text-center p-4" style="border-style:dashed !important;border-color:#6366f1 !important;background:rgba(99,102,241,0.02);transition:all 0.2s;" id="saDropZone">
              <i class="bi bi-cloud-arrow-up text-3xl block mb-1" style="color:#6366f1;"></i>
              <span class="font-semibold" style="color:#6366f1;">คลิกเพื่อเลือกไฟล์ JSON</span>
              <br><span class="text-gray-500" style="font-size:0.75rem;">ไฟล์ .json จาก Google Cloud Console &rarr; Service Accounts &rarr; Keys</span>
            </div>
            <input type="file" name="service_account_json" accept=".json,application/json"
                class="absolute top-0 start-0 w-full h-full" style="opacity:0;cursor:pointer;"
                onchange="if(this.files[0]) document.getElementById('saDropZone').innerHTML='<i class=\'bi bi-file-earmark-check text-2xl text-green-600 block mb-1\'></i><span class=\'text-green-600 font-semibold\'>'+this.files[0].name+'</span><br><span class=\'text-gray-500\' style=\'font-size:0.75rem\'>'+(this.files[0].size/1024).toFixed(1)+' KB — คลิกบันทึกเพื่ออัปโหลด</span>';">
          </label>
        </div>

        {{-- Step-by-step guide --}}
        <div class="p-3 rounded-xl" style="background:rgba(99,102,241,0.03);border:1px solid rgba(99,102,241,0.1);">
          <div class="font-semibold small mb-2"><i class="bi bi-list-ol mr-1" style="color:#6366f1;"></i>วิธีสร้าง Service Account</div>
          <ol class="small text-gray-500 mb-0" style="padding-left:1.2rem;line-height:2;">
            <li>ไป <a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank" style="color:#6366f1;">Google Cloud Console &rarr; IAM &rarr; Service Accounts</a></li>
            <li>กด <strong>Create Service Account</strong> &rarr; ตั้งชื่อ &rarr; Done</li>
            <li>คลิกที่ Service Account &rarr; แท็บ <strong>Keys</strong> &rarr; Add Key &rarr; Create new key &rarr; <strong>JSON</strong></li>
            <li>อัปโหลดไฟล์ JSON ที่ได้มาด้านบน</li>
            <li>เปิด Google Drive API ใน <a href="https://console.cloud.google.com/apis/library/drive.googleapis.com" target="_blank" style="color:#6366f1;">API Library</a></li>
            <li><strong>แชร์โฟลเดอร์รูป</strong>ใน Google Drive ให้กับอีเมล Service Account (ระดับ Viewer)</li>
          </ol>
        </div>
      @endif
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════
     Section 2: Google OAuth
  ════════════════════════════════════════════════════════ --}}
  <div class="card setting-card mb-4">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <div class="card-icon bg-accent-oauth">
          <i class="bi bi-shield-lock accent-oauth"></i>
        </div>
        <div>
          <h6 class="font-bold mb-0">Google OAuth</h6>
          <p class="text-gray-500 small mb-0">การตั้งค่า OAuth 2.0 สำหรับ authentication กับ Google</p>
        </div>
      </div>
      <span class="status-dot {{ ($settings['google_client_id'] ?? '') && ($settings['google_client_secret'] ?? '') ? 'connected' : 'disconnected' }}">
        {{ ($settings['google_client_id'] ?? '') && ($settings['google_client_secret'] ?? '') ? 'Configured' : 'Not configured' }}
      </span>
    </div>
    <div class="p-5">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {{-- Client ID --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5" for="google_client_id">
            Google Client ID
          </label>
          <input type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="google_client_id" id="google_client_id"
              value="{{ $settings['google_client_id'] ?? '' }}"
              placeholder="123456789-abc.apps.googleusercontent.com">
          <div class="help-text">
            <i class="bi bi-info-circle mr-1"></i>
            OAuth 2.0 Client ID จาก Google Cloud Console &rarr; APIs & Services &rarr; Credentials
          </div>
        </div>

        {{-- Client Secret --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5" for="google_client_secret">
            Google Client Secret
          </label>
          <div class="flex">
            <input type="password" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="google_client_secret" id="google_client_secret"
                value="{{ $settings['google_client_secret'] ?? '' }}"
                placeholder="{{ ($settings['google_client_secret'] ?? '') ? 'Secret saved (enter new to change)' : 'GOCSPX-...' }}"
                autocomplete="new-password">
            <span class="px-3 py-2.5 bg-gray-50 border border-gray-300 text-gray-500 toggle-pw" data-target="google_client_secret">
              <i class="bi bi-eye"></i>
            </span>
          </div>
          <div class="help-text">
            <i class="bi bi-info-circle mr-1"></i>
            Client Secret ใช้สำหรับ server-side OAuth flow เท่านั้น ห้ามเปิดเผย
          </div>
        </div>

        {{-- Redirect URI --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5">Redirect URI (สำหรับตั้งค่าใน Google Cloud Console)</label>
          <div class="uri-box">
            <span id="redirectUriText">{{ rtrim(config('app.url'), '/') }}/auth/google/callback</span>
            <button type="button" class="copy-btn" id="copyRedirectUri" onclick="copyToClipboard('redirectUriText', 'copyRedirectUri')">
              <i class="bi bi-clipboard mr-1"></i>คัดลอก
            </button>
          </div>
          <div class="help-text">
            <i class="bi bi-arrow-right-circle mr-1"></i>
            นำ URL นี้ไปวางใน Google Cloud Console &rarr; OAuth consent screen &rarr; Authorized redirect URIs
          </div>
        </div>

        {{-- OAuth Scopes --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5 mb-2">OAuth Scopes ที่ใช้งาน</label>
          <div class="flex flex-wrap gap-2">
            <span class="scope-tag"><i class="bi bi-check-circle-fill" style="color:#10b981;font-size:0.65rem;"></i> openid</span>
            <span class="scope-tag"><i class="bi bi-check-circle-fill" style="color:#10b981;font-size:0.65rem;"></i> email</span>
            <span class="scope-tag"><i class="bi bi-check-circle-fill" style="color:#10b981;font-size:0.65rem;"></i> profile</span>
            <span class="scope-tag"><i class="bi bi-check-circle-fill" style="color:#10b981;font-size:0.65rem;"></i> drive.readonly</span>
          </div>
        </div>

        {{-- Setup Guide --}}
        <div class="">
          <div class="p-3" style="background:#f8fafc;border-radius:10px;border:1px solid #e5e7eb;">
            <p class="font-semibold mb-2" style="font-size:0.82rem;color:#374151;">
              <i class="bi bi-lightbulb mr-1" style="color:#f59e0b;"></i> วิธีตั้งค่า Google OAuth
            </p>
            <ol class="mb-0 ps-3" style="font-size:0.8rem;color:#6b7280;line-height:1.8;">
              <li>ไปที่ <a href="https://console.cloud.google.com/" target="_blank" rel="noopener" style="color:#6366f1;">Google Cloud Console</a> &rarr; สร้างหรือเลือก Project</li>
              <li>เปิดใช้งาน <strong>Google Drive API</strong> ใน APIs & Services &rarr; Library</li>
              <li>ไปที่ <strong>Credentials</strong> &rarr; Create Credentials &rarr; OAuth client ID</li>
              <li>เลือก Application type: <strong>Web application</strong></li>
              <li>เพิ่ม <strong>Authorized redirect URI</strong> ตาม URL ด้านบน</li>
              <li>คัดลอก <strong>Client ID</strong> และ <strong>Client Secret</strong> มาวางในช่องด้านบน</li>
            </ol>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════
     Section 3: Sync Settings (ตั้งค่าการซิงค์)
  ════════════════════════════════════════════════════════ --}}
  <div class="card setting-card mb-4">
    <div class="px-5 py-4 border-b border-gray-100">
      <div class="flex items-center gap-2">
        <div class="card-icon bg-accent-sync">
          <i class="bi bi-arrow-repeat accent-sync"></i>
        </div>
        <div>
          <h6 class="font-bold mb-0">ตั้งค่าการซิงค์</h6>
          <p class="text-gray-500 small mb-0">กำหนดการซิงค์รูปภาพจาก Google Drive อัตโนมัติ</p>
        </div>
      </div>
    </div>
    <div class="p-5">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {{-- Enable Auto-Sync --}}
        <div class="">
          <div class="toggle-row">
            <div class="toggle-info">
              <div class="toggle-title">เปิดใช้ Auto-Sync</div>
              <div class="toggle-desc">ซิงค์รูปภาพอัตโนมัติเมื่อมีการสร้างหรือแก้ไขอีเวนต์ที่มี Google Drive folder</div>
            </div>
            <div class="form-check form-switch mb-0">
              <input class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" type="checkbox" role="switch"
                  name="queue_auto_sync" id="queue_auto_sync"
                  {{ ($settings['queue_auto_sync'] ?? '0') === '1' ? 'checked' : '' }}>
            </div>
          </div>
        </div>

        {{-- Sync Interval --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5" for="queue_sync_interval_minutes">
            Sync Interval (นาที)
          </label>
          <input type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="queue_sync_interval_minutes" id="queue_sync_interval_minutes"
              value="{{ $settings['queue_sync_interval_minutes'] ?? '60' }}"
              min="5" max="1440" placeholder="60">
          <div class="help-text">
            ความถี่ในการรีเฟรชแคชรูปภาพ (5-1440 นาที) ค่าเริ่มต้น: 60 นาที
          </div>
        </div>

        {{-- Max Retry Attempts --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5" for="queue_max_retries">
            Max Retry Attempts
          </label>
          <input type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="queue_max_retries" id="queue_max_retries"
              value="{{ $settings['queue_max_retries'] ?? '3' }}"
              min="1" max="10" placeholder="3">
          <div class="help-text">
            จำนวนครั้งสูงสุดในการลองใหม่เมื่อซิงค์ล้มเหลว
          </div>
        </div>

        {{-- Max Files Per Request --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5" for="perf_api_max_files">
            Max Files / Request
          </label>
          <input type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="perf_api_max_files" id="perf_api_max_files"
              value="{{ $settings['perf_api_max_files'] ?? '500' }}"
              min="50" max="5000" placeholder="500">
          <div class="help-text">
            จำนวนไฟล์สูงสุดต่อ API request
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════
     Section 4: Cache Performance (ประสิทธิภาพแคช)
  ════════════════════════════════════════════════════════ --}}
  <div class="card setting-card mb-4">
    <div class="px-5 py-4 border-b border-gray-100">
      <div class="flex items-center gap-2">
        <div class="card-icon bg-accent-perf">
          <i class="bi bi-speedometer2 accent-perf"></i>
        </div>
        <div>
          <h6 class="font-bold mb-0">ประสิทธิภาพแคช</h6>
          <p class="text-gray-500 small mb-0">ปรับแต่งพารามิเตอร์แคชสำหรับประสิทธิภาพสูงสุด</p>
        </div>
      </div>
    </div>
    <div class="p-5">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {{-- Browser Cache Duration --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5" for="perf_browser_cache_maxage">
            Browser Cache Duration (วินาที)
          </label>
          <input type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="perf_browser_cache_maxage" id="perf_browser_cache_maxage"
              value="{{ $settings['perf_browser_cache_maxage'] ?? '300' }}"
              min="0" max="86400" placeholder="300">
          <div class="help-text">
            <i class="bi bi-globe mr-1"></i>
            ระยะเวลาที่บราวเซอร์จะเก็บแคชข้อมูล (Cache-Control: max-age) ค่าเริ่มต้น: 300 วินาที (5 นาที)
          </div>
        </div>

        {{-- Stale-While-Revalidate --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5" for="perf_stale_revalidate">
            Stale-While-Revalidate (วินาที)
          </label>
          <input type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="perf_stale_revalidate" id="perf_stale_revalidate"
              value="{{ $settings['perf_stale_revalidate'] ?? '600' }}"
              min="0" max="86400" placeholder="600">
          <div class="help-text">
            <i class="bi bi-arrow-clockwise mr-1"></i>
            ระยะเวลาที่อนุญาตให้ใช้แคชเก่าขณะรีเฟรชข้อมูลใหม่ ค่าเริ่มต้น: 600 วินาที (10 นาที)
          </div>
        </div>

        {{-- Lock Timeout --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5" for="perf_lock_timeout">
            Lock Timeout (วินาที)
          </label>
          <input type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="perf_lock_timeout" id="perf_lock_timeout"
              value="{{ $settings['perf_lock_timeout'] ?? '30' }}"
              min="5" max="300" placeholder="30">
          <div class="help-text">
            <i class="bi bi-lock mr-1"></i>
            ระยะเวลาสูงสุดในการรอ lock เมื่อมีการอัปเดตแคชพร้อมกัน ค่าเริ่มต้น: 30 วินาที
          </div>
        </div>

        {{-- Grace Period --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5" for="perf_cache_grace_hours">
            Grace Period (ชั่วโมง)
          </label>
          <input type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="perf_cache_grace_hours" id="perf_cache_grace_hours"
              value="{{ $settings['perf_cache_grace_hours'] ?? '24' }}"
              min="1" max="168" placeholder="24">
          <div class="help-text">
            <i class="bi bi-clock-history mr-1"></i>
            ระยะเวลาที่ข้อมูลแคชเก่ายังสามารถใช้ได้เมื่อ API ล้มเหลว ค่าเริ่มต้น: 24 ชั่วโมง
          </div>
        </div>

        {{-- Performance Tip --}}
        <div class="">
          <div class="p-3 rounded-xl" style="background:linear-gradient(135deg, rgba(251,188,4,0.05), rgba(245,158,11,0.05));border:1px solid rgba(251,188,4,0.12);">
            <div class="flex items-start gap-2">
              <i class="bi bi-lightbulb" style="color:#f59e0b;margin-top:2px;"></i>
              <div>
                <div class="font-semibold small" style="color:#92400e;">คำแนะนำด้านประสิทธิภาพ</div>
                <div class="text-gray-500" style="font-size:0.78rem;line-height:1.6;">
                  <strong>Browser Cache</strong> ลดจำนวน request ไปยังเซิร์ฟเวอร์ &mdash;
                  <strong>Stale-While-Revalidate</strong> ให้ผู้ใช้เห็นข้อมูลทันทีขณะรีเฟรช &mdash;
                  <strong>Lock Timeout</strong> ป้องกัน stampede เมื่อแคชหมดอายุพร้อมกัน &mdash;
                  <strong>Grace Period</strong> ให้ระบบยังใช้งานได้เมื่อ Google API ล่ม
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════
     Save Settings Button
  ════════════════════════════════════════════════════════ --}}
  <div class="flex justify-end mb-4">
    <button type="submit" class="btn btn-save-gradient">
      <i class="bi bi-save mr-2"></i>บันทึกการตั้งค่า
    </button>
  </div>
</form>

{{-- ════════════════════════════════════════════════════════
   Section 5: Queue Status (สถานะคิว) - LIVE DASHBOARD
════════════════════════════════════════════════════════ --}}
<div class="card setting-card mb-4">
  <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
    <div class="flex items-center gap-2">
      <div class="card-icon bg-accent-queue">
        <i class="bi bi-list-task accent-queue"></i>
      </div>
      <div>
        <h6 class="font-bold mb-0">สถานะคิว <span class="live-dot ml-2" title="Live"></span></h6>
        <p class="text-gray-500 small mb-0">แดชบอร์ดคิวการซิงค์แบบ Real-time อัปเดตทุก 5 วินาที</p>
      </div>
    </div>
    <span class="text-gray-500 small" id="queueLastUpdate">อัปเดตล่าสุด: --</span>
  </div>
  <div class="p-5">
    {{-- Stat Cards --}}
    <div class="row g-3 mb-4">
      <div class="">
        <div class="stat-card stat-pending">
          <div class="stat-number" id="statPending">{{ $queueStats['pending'] ?? 0 }}</div>
          <div class="stat-label"><i class="bi bi-clock mr-1"></i>Pending</div>
        </div>
      </div>
      <div class="">
        <div class="stat-card stat-processing">
          <div class="stat-number" id="statProcessing">{{ $queueStats['processing'] ?? 0 }}</div>
          <div class="stat-label"><i class="bi bi-gear-wide-connected mr-1"></i>Processing</div>
        </div>
      </div>
      <div class="">
        <div class="stat-card stat-completed">
          <div class="stat-number" id="statCompleted">{{ $queueStats['completed'] ?? 0 }}</div>
          <div class="stat-label"><i class="bi bi-check-circle mr-1"></i>Completed</div>
        </div>
      </div>
      <div class="">
        <div class="stat-card stat-failed">
          <div class="stat-number" id="statFailed">{{ $queueStats['failed'] ?? 0 }}</div>
          <div class="stat-label"><i class="bi bi-x-circle mr-1"></i>Failed</div>
        </div>
      </div>
    </div>

    {{-- Action Buttons --}}
    <div class="flex flex-wrap gap-2 mb-4">
      <button type="button" class="btn btn-action btn-action-indigo" id="btnProcessNow" onclick="queueAction('process')">
        <i class="bi bi-play-fill mr-1"></i>Process Now
        <span class="spinner-border spinner-border-sm ml-1 hidden" role="status"></span>
      </button>
      <button type="button" class="btn btn-action btn-action-green" id="btnSyncAll" onclick="queueAction('sync_all')">
        <i class="bi bi-arrow-repeat mr-1"></i>Sync All Events
        <span class="spinner-border spinner-border-sm ml-1 hidden" role="status"></span>
      </button>
      <button type="button" class="btn btn-action btn-action-amber" id="btnRetryAll" onclick="queueAction('retry_all')">
        <i class="bi bi-arrow-counterclockwise mr-1"></i>Retry All Failed
        <span class="spinner-border spinner-border-sm ml-1 hidden" role="status"></span>
      </button>
      <button type="button" class="btn btn-action btn-action-red" id="btnClearCompleted" onclick="queueAction('clear')">
        <i class="bi bi-trash3 mr-1"></i>Clear Completed
        <span class="spinner-border spinner-border-sm ml-1 hidden" role="status"></span>
      </button>
    </div>

    {{-- Recent Jobs Table --}}
    <div class="overflow-x-auto">
      <table class="table queue-table" id="queueTable">
        <thead>
          <tr>
            <th>Event</th>
            <th>Status</th>
            <th>Photos</th>
            <th>Duration</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody id="queueTableBody">
          @forelse($recentJobs as $job)
          <tr data-job-id="{{ $job->id }}">
            <td>
              <div class="font-semibold" style="font-size:0.85rem;">{{ $job->event_name ?? 'Event #'.$job->event_id }}</div>
              <div class="text-gray-500" style="font-size:0.72rem;">{{ $job->job_type }} &middot; {{ \Carbon\Carbon::parse($job->created_at)->diffForHumans() }}</div>
            </td>
            <td>
              <span class="badge-status badge-{{ $job->status }}">{{ $job->status }}</span>
            </td>
            <td style="font-size:0.85rem;">
              {{ $job->result_count ?? '-' }}
            </td>
            <td style="font-size:0.85rem;">
              @if($job->completed_at && $job->started_at)
                {{ \Carbon\Carbon::parse($job->started_at)->diffInSeconds(\Carbon\Carbon::parse($job->completed_at)) }}s
              @else
                -
              @endif
            </td>
            <td class="text-center">
              @if($job->status === 'failed')
                <button type="button" class="btn btn-action btn-action-amber btn-sm" onclick="queueAction('retry', {{ $job->id }})" style="padding:0.2rem 0.6rem;font-size:0.75rem;">
                  <i class="bi bi-arrow-counterclockwise"></i> Retry
                </button>
              @elseif($job->status === 'pending')
                <button type="button" class="btn btn-action btn-action-red btn-sm" onclick="queueAction('cancel', {{ $job->id }})" style="padding:0.2rem 0.6rem;font-size:0.75rem;">
                  <i class="bi bi-x"></i> Cancel
                </button>
              @else
                <span class="text-gray-500" style="font-size:0.75rem;">-</span>
              @endif
            </td>
          </tr>
          @empty
          <tr id="emptyQueueRow">
            <td colspan="5">
              <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <p>ยังไม่มีงานในคิว</p>
              </div>
            </td>
          </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>

{{-- ════════════════════════════════════════════════════════
   Section 6: Manual Sync (ซิงค์ด้วยตนเอง)
════════════════════════════════════════════════════════ --}}
<div class="card setting-card mb-5">
  <div class="px-5 py-4 border-b border-gray-100">
    <div class="flex items-center gap-2">
      <div class="card-icon bg-accent-manual">
        <i class="bi bi-cloud-arrow-down accent-manual"></i>
      </div>
      <div>
        <h6 class="font-bold mb-0">ซิงค์ด้วยตนเอง</h6>
        <p class="text-gray-500 small mb-0">เลือกอีเวนต์และสั่งซิงค์รูปภาพจาก Google Drive</p>
      </div>
    </div>
  </div>
  <div class="p-5">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      {{-- Event Selection --}}
      <div class="lg:col-span-2">
        <label class="block text-sm font-medium text-gray-700 mb-1.5" for="syncEventSelect">เลือกอีเวนต์</label>
        <select class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500" id="syncEventSelect">
          <option value="">-- เลือกอีเวนต์ที่มี Google Drive folder --</option>
          @foreach($events as $event)
            <option value="{{ $event->id }}">{{ $event->name }} (ID: {{ $event->id }})</option>
          @endforeach
        </select>
        <div class="help-text">
          <i class="bi bi-info-circle mr-1"></i>
          แสดงเฉพาะอีเวนต์ที่มีการตั้งค่า Google Drive folder แล้ว
        </div>
      </div>

      {{-- Sync Buttons --}}
      <div class=" flex flex-col gap-2 justify-end">
        <button type="button" class="btn btn-action btn-action-indigo" id="btnSyncSelected" onclick="syncSelectedEvent()">
          <i class="bi bi-cloud-download mr-1"></i>Sync Selected Event
          <span class="spinner-border spinner-border-sm ml-1 hidden" role="status"></span>
        </button>
        <button type="button" class="btn btn-action btn-action-green" id="btnSyncAllEvents" onclick="queueAction('sync_all')">
          <i class="bi bi-cloud-arrow-down mr-1"></i>Sync All Events
          <span class="spinner-border spinner-border-sm ml-1 hidden" role="status"></span>
        </button>
      </div>
    </div>

    {{-- Sync Result --}}
    <div class="mt-3 hidden" id="syncResultArea">
      <div class="p-3 rounded-xl" id="syncResultBox" style="border:1px solid #e5e7eb;">
        <div id="syncResultContent"></div>
      </div>
    </div>
  </div>
</div>

{{-- Setup Instructions (when no API key is configured) --}}
@if(!($settings['google_drive_api_key'] ?? ''))
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;border-left:4px solid #4285f4 !important;">
  <div class="p-5 p-4">
    <h6 class="font-bold mb-3"><i class="bi bi-info-circle mr-2" style="color:#4285f4;"></i>เริ่มต้นใช้งาน Google Drive</h6>
    <ol class="mb-0 small" style="line-height:2;">
      <li>สร้าง <a href="https://console.cloud.google.com/" target="_blank" rel="noopener" style="color:#4285f4;">Google Cloud Project</a> หากยังไม่มี</li>
      <li>เปิดใช้งาน <strong>Google Drive API</strong> ใน APIs & Services &rarr; Library</li>
      <li>สร้าง <strong>API Key</strong> ที่ Credentials &rarr; Create Credentials &rarr; API key</li>
      <li>จำกัดการเข้าถึง API Key เฉพาะ <strong>Google Drive API</strong> เพื่อความปลอดภัย</li>
      <li>คัดลอก API Key มาวางในส่วน <strong>Google Drive API</strong> ด้านบน</li>
      <li>สำหรับ OAuth login ให้สร้าง <strong>OAuth 2.0 Client ID</strong> เพิ่มเติม</li>
    </ol>
  </div>
</div>
@endif

@endsection

@push('scripts')
<script>
(function() {
  // ─── Toggle Password Visibility ──────────────────────────────────────
  document.querySelectorAll('.toggle-pw').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var target = document.getElementById(this.dataset.target);
      var icon = this.querySelector('i');
      if (!target || !icon) return;
      if (target.type === 'password') {
        target.type = 'text';
        icon.className = 'bi bi-eye-slash';
      } else {
        target.type = 'password';
        icon.className = 'bi bi-eye';
      }
    });
  });
})();

// ─── Copy to Clipboard ───────────────────────────────────────────────
function copyToClipboard(textId, btnId) {
  var text = document.getElementById(textId).textContent.trim();
  var btn = document.getElementById(btnId);
  navigator.clipboard.writeText(text).then(function() {
    btn.classList.add('copied');
    btn.innerHTML = '<i class="bi bi-check mr-1"></i>คัดลอกแล้ว';
    setTimeout(function() {
      btn.classList.remove('copied');
      btn.innerHTML = '<i class="bi bi-clipboard mr-1"></i>คัดลอก';
    }, 2000);
  });
}

// ─── SweetAlert2 Toast Helper ────────────────────────────────────────
function showToast(icon, title) {
  if (typeof Swal !== 'undefined') {
    Swal.fire({
      toast: true,
      position: 'top-end',
      icon: icon,
      title: title,
      showConfirmButton: false,
      timer: 3000,
      timerProgressBar: true,
      didOpen: function(toast) {
        toast.addEventListener('mouseenter', Swal.stopTimer);
        toast.addEventListener('mouseleave', Swal.resumeTimer);
      }
    });
  }
}

// ─── Button Loading State ────────────────────────────────────────────
function setButtonLoading(btn, loading) {
  if (!btn) return;
  var spinner = btn.querySelector('.spinner-border');
  if (loading) {
    btn.disabled = true;
    if (spinner) spinner.classList.remove('hidden');
  } else {
    btn.disabled = false;
    if (spinner) spinner.classList.add('hidden');
  }
}

// ─── CSRF Token ──────────────────────────────────────────────────────
var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}';

// ─── Test Drive API Connection ───────────────────────────────────────
document.getElementById('btnTestDriveApi')?.addEventListener('click', function() {
  var btn = this;
  var result = document.getElementById('driveTestResult');
  var statusBadge = document.getElementById('apiStatus');
  setButtonLoading(btn, true);
  result.classList.add('hidden');

  fetch('{{ route("admin.api.admin.drive-test") }}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken,
      'Accept': 'application/json'
    },
    body: JSON.stringify({
      api_key: document.getElementById('google_drive_api_key').value
    })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    setButtonLoading(btn, false);
    result.classList.remove('hidden');
    if (data.success) {
      result.innerHTML = '<span style="color:#059669;"><i class="bi bi-check-circle mr-1"></i>' + (data.message || 'เชื่อมต่อ Google Drive API สำเร็จ') + '</span>';
      statusBadge.className = 'status-dot connected';
      statusBadge.textContent = 'Connected';
      showToast('success', 'เชื่อมต่อ Google Drive สำเร็จ');
    } else {
      result.innerHTML = '<span style="color:#dc2626;"><i class="bi bi-x-circle mr-1"></i>' + (data.message || 'ไม่สามารถเชื่อมต่อได้') + '</span>';
      statusBadge.className = 'status-dot disconnected';
      statusBadge.textContent = 'Disconnected';
      showToast('error', data.message || 'การเชื่อมต่อล้มเหลว');
    }
  })
  .catch(function(err) {
    setButtonLoading(btn, false);
    result.classList.remove('hidden');
    result.innerHTML = '<span style="color:#dc2626;"><i class="bi bi-x-circle mr-1"></i>Network error: ' + err.message + '</span>';
    showToast('error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ');
  });
});

// ─── Queue Status Polling ────────────────────────────────────────────
var queuePollInterval = null;

function fetchQueueStatus() {
  fetch('{{ route("admin.api.admin.drive-queue") }}?action=status', {
    headers: {
      'Accept': 'application/json',
      'X-CSRF-TOKEN': csrfToken
    }
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (data.stats) {
      document.getElementById('statPending').textContent = data.stats.pending || 0;
      document.getElementById('statProcessing').textContent = data.stats.processing || 0;
      document.getElementById('statCompleted').textContent = data.stats.completed || 0;
      document.getElementById('statFailed').textContent = data.stats.failed || 0;
    }

    if (data.jobs && data.jobs.length > 0) {
      renderQueueTable(data.jobs);
    }

    var now = new Date();
    document.getElementById('queueLastUpdate').textContent =
      'อัปเดตล่าสุด: ' + now.toLocaleTimeString('th-TH');
  })
  .catch(function() {});
}

function renderQueueTable(jobs) {
  var tbody = document.getElementById('queueTableBody');
  if (!tbody) return;

  if (jobs.length === 0) {
    tbody.innerHTML = '<tr id="emptyQueueRow"><td colspan="5"><div class="empty-state"><i class="bi bi-inbox"></i><p>ยังไม่มีงานในคิว</p></div></td></tr>';
    return;
  }

  var html = '';
  jobs.forEach(function(job) {
    var photoCount = job.result_count || '-';
    var duration = '-';
    if (job.completed_at && job.started_at) {
      var diff = Math.round((new Date(job.completed_at) - new Date(job.started_at)) / 1000);
      duration = diff + 's';
    }

    var actionHtml = '<span class="text-gray-500" style="font-size:0.75rem;">-</span>';
    if (job.status === 'failed') {
      actionHtml = '<button type="button" class="btn btn-action btn-action-amber btn-sm" onclick="queueAction(\'retry\', ' + job.id + ')" style="padding:0.2rem 0.6rem;font-size:0.75rem;"><i class="bi bi-arrow-counterclockwise"></i> Retry</button>';
    } else if (job.status === 'pending') {
      actionHtml = '<button type="button" class="btn btn-action btn-action-red btn-sm" onclick="queueAction(\'cancel\', ' + job.id + ')" style="padding:0.2rem 0.6rem;font-size:0.75rem;"><i class="bi bi-x"></i> Cancel</button>';
    }

    var timeAgo = '';
    if (job.created_at) {
      var diffMs = Date.now() - new Date(job.created_at).getTime();
      var diffSec = Math.floor(diffMs / 1000);
      if (diffSec < 60) timeAgo = diffSec + ' seconds ago';
      else if (diffSec < 3600) timeAgo = Math.floor(diffSec / 60) + ' minutes ago';
      else if (diffSec < 86400) timeAgo = Math.floor(diffSec / 3600) + ' hours ago';
      else timeAgo = Math.floor(diffSec / 86400) + ' days ago';
    }

    html += '<tr data-job-id="' + job.id + '">'
      + '<td><div class="font-semibold" style="font-size:0.85rem;">' + (job.event_name || 'Event #' + job.event_id) + '</div>'
      + '<div class="text-gray-500" style="font-size:0.72rem;">' + (job.job_type || '') + ' &middot; ' + timeAgo + '</div></td>'
      + '<td><span class="badge-status badge-' + job.status + '">' + job.status + '</span></td>'
      + '<td style="font-size:0.85rem;">' + photoCount + '</td>'
      + '<td style="font-size:0.85rem;">' + duration + '</td>'
      + '<td class="text-center">' + actionHtml + '</td>'
      + '</tr>';
  });

  tbody.innerHTML = html;
}

// Start polling
queuePollInterval = setInterval(fetchQueueStatus, 5000);

// ─── Queue Actions ───────────────────────────────────────────────────
function queueAction(action, id) {
  var btnMap = {
    'process': 'btnProcessNow',
    'sync_all': 'btnSyncAll',
    'retry_all': 'btnRetryAll',
    'clear': 'btnClearCompleted'
  };

  var btn = document.getElementById(btnMap[action] || null);
  if (btn) setButtonLoading(btn, true);

  var bodyData = { action: action };
  if (action === 'retry' && id) bodyData.job_id = id;
  if (action === 'cancel' && id) bodyData.job_id = id;
  if (action === 'sync' && id) bodyData.event_id = id;

  fetch('{{ route("admin.api.admin.drive-queue") }}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken,
      'Accept': 'application/json'
    },
    body: JSON.stringify(bodyData)
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    if (btn) setButtonLoading(btn, false);
    if (data.success) {
      showToast('success', data.message || 'ดำเนินการสำเร็จ');
      fetchQueueStatus();
    } else {
      showToast('error', data.message || 'เกิดข้อผิดพลาด');
    }
  })
  .catch(function(err) {
    if (btn) setButtonLoading(btn, false);
    showToast('error', 'Network error: ' + err.message);
  });
}

// ─── Sync Selected Event ─────────────────────────────────────────────
function syncSelectedEvent() {
  var select = document.getElementById('syncEventSelect');
  var btn = document.getElementById('btnSyncSelected');
  var resultArea = document.getElementById('syncResultArea');
  var resultBox = document.getElementById('syncResultBox');
  var resultContent = document.getElementById('syncResultContent');

  if (!select.value) {
    showToast('warning', 'กรุณาเลือกอีเวนต์ก่อน');
    select.focus();
    return;
  }

  setButtonLoading(btn, true);
  resultArea.classList.add('hidden');

  fetch('{{ route("admin.api.admin.drive-queue") }}', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': csrfToken,
      'Accept': 'application/json'
    },
    body: JSON.stringify({
      action: 'sync',
      event_id: select.value
    })
  })
  .then(function(r) { return r.json(); })
  .then(function(data) {
    setButtonLoading(btn, false);
    resultArea.classList.remove('hidden');

    if (data.success) {
      resultBox.style.background = 'rgba(16,185,129,0.04)';
      resultBox.style.borderColor = 'rgba(16,185,129,0.2)';
      resultContent.innerHTML = '<div class="flex items-center gap-2">'
        + '<i class="bi bi-check-circle-fill" style="color:#059669;font-size:1.1rem;"></i>'
        + '<div><div class="font-semibold small" style="color:#059669;">' + (data.message || 'เพิ่มงานซิงค์เข้าคิวแล้ว') + '</div>'
        + (data.job_id ? '<div class="text-gray-500" style="font-size:0.78rem;">Job ID: ' + data.job_id + '</div>' : '')
        + '</div></div>';
      showToast('success', data.message || 'เพิ่มงานซิงค์สำเร็จ');
      fetchQueueStatus();
    } else {
      resultBox.style.background = 'rgba(239,68,68,0.04)';
      resultBox.style.borderColor = 'rgba(239,68,68,0.2)';
      resultContent.innerHTML = '<div class="flex items-center gap-2">'
        + '<i class="bi bi-x-circle-fill" style="color:#dc2626;font-size:1.1rem;"></i>'
        + '<div class="font-semibold small" style="color:#dc2626;">' + (data.message || 'ไม่สามารถซิงค์ได้') + '</div>'
        + '</div>';
      showToast('error', data.message || 'การซิงค์ล้มเหลว');
    }
  })
  .catch(function(err) {
    setButtonLoading(btn, false);
    resultArea.classList.remove('hidden');
    resultBox.style.background = 'rgba(239,68,68,0.04)';
    resultBox.style.borderColor = 'rgba(239,68,68,0.2)';
    resultContent.innerHTML = '<div class="flex items-center gap-2">'
      + '<i class="bi bi-x-circle-fill" style="color:#dc2626;font-size:1.1rem;"></i>'
      + '<div class="font-semibold small" style="color:#dc2626;">Network error: ' + err.message + '</div>'
      + '</div>';
    showToast('error', 'เกิดข้อผิดพลาดในการเชื่อมต่อ');
  });
}

// ─── Cleanup on page leave ───────────────────────────────────────────
window.addEventListener('beforeunload', function() {
  if (queuePollInterval) clearInterval(queuePollInterval);
});
</script>
@endpush
