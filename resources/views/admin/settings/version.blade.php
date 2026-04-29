@extends('layouts.admin')

@section('title', 'Version Info')

@section('content')
<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0" style="letter-spacing:-0.02em;">
    <i class="bi bi-info-circle mr-2" style="color:#6366f1;"></i>Version Info
  </h4>
  <a href="{{ route('admin.settings.index') }}" class="text-sm px-3 py-1.5 rounded-lg"
    style="background:rgba(99,102,241,0.08);color:#6366f1;border-radius:8px;font-weight:500;border:none;padding:0.4rem 1rem;">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

@if(session('success'))
  <div class="bg-green-50 border border-green-200 text-green-700 rounded-xl p-4 text-sm " role="alert" style="border-radius:12px;">
    <i class="bi bi-check-circle mr-1"></i> {{ session('success') }}
    <button type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer" onclick="this.parentElement.remove()"></button>
  </div>
@endif
@if(session('error'))
  <div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm " role="alert" style="border-radius:12px;">
    <i class="bi bi-exclamation-triangle mr-1"></i> {{ session('error') }}
    <button type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer" onclick="this.parentElement.remove()"></button>
  </div>
@endif

{{-- Tabs --}}
<div x-data="{ activeTab: 'sysinfo', showVersionForm: false }">
<ul class="flex border-b border-gray-200 mb-4" id="versionTabs" role="tablist" style="border-b:2px solid #e5e7eb;">
  <li role="presentation">
    <button class="font-medium px-5 py-2.5"
        :class="activeTab === 'sysinfo' ? 'border-b-2 border-indigo-500 text-indigo-600' : 'text-gray-500 hover:text-gray-700'"
        @click="activeTab = 'sysinfo'"
        type="button" role="tab">
      <i class="bi bi-cpu mr-1"></i>System Info
    </button>
  </li>
  <li role="presentation">
    <button class="font-medium px-5 py-2.5"
        :class="activeTab === 'history' ? 'border-b-2 border-indigo-500 text-indigo-600' : 'text-gray-500 hover:text-gray-700'"
        @click="activeTab = 'history'"
        type="button" role="tab">
      <i class="bi bi-clock-history mr-1"></i>Version History
    </button>
  </li>
</ul>

<div id="versionTabsContent">

  {{-- ==================== SYSTEM INFO TAB ==================== --}}
  <div x-show="activeTab === 'sysinfo'" x-cloak id="sysinfo-pane" role="tabpanel">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

      {{-- Application Info --}}
      <div class="">
        <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
          <div class="p-5 p-4">
            <h6 class="font-semibold mb-3">
              <i class="bi bi-app mr-1" style="color:#6366f1;"></i> Application
            </h6>
            <table class="table table-sm table-borderless mb-0" style="font-size:0.875rem;">
              <tbody>
                <tr>
                  <td class="text-gray-500" style="width:45%;">App Name</td>
                  <td class="font-medium">{{ $systemInfo['app_name'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                  <td class="text-gray-500">Version</td>
                  <td>
                    <span class="badge" style="background:#6366f1;color:#fff;border-radius:6px;font-size:0.8rem;">
                      {{ $systemInfo['app_version'] ?? 'N/A' }}
                    </span>
                  </td>
                </tr>
                <tr>
                  <td class="text-gray-500">Environment</td>
                  <td>
                    @php
                      $env = $systemInfo['app_env'] ?? 'N/A';
                      $envColor = $env === 'production' ? '#10b981' : ($env === 'local' ? '#f59e0b' : '#6b7280');
                    @endphp
                    <span style="color:{{ $envColor }};font-weight:600;">{{ $env }}</span>
                  </td>
                </tr>
                <tr>
                  <td class="text-gray-500">Laravel Version</td>
                  <td class="font-medium">{{ $systemInfo['laravel_version'] ?? 'N/A' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      {{-- Server Info --}}
      <div class="">
        <div class="card border-0 h-full" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
          <div class="p-5 p-4">
            <h6 class="font-semibold mb-3">
              <i class="bi bi-server mr-1" style="color:#6366f1;"></i> Server
            </h6>
            <table class="table table-sm table-borderless mb-0" style="font-size:0.875rem;">
              <tbody>
                <tr>
                  <td class="text-gray-500" style="width:45%;">PHP Version</td>
                  <td class="font-medium">{{ $systemInfo['php_version'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                  <td class="text-gray-500">Web Server</td>
                  <td class="font-medium" style="word-break:break-word;">{{ $systemInfo['server_software'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                  <td class="text-gray-500">OS</td>
                  <td class="font-medium">{{ $systemInfo['os'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                  <td class="text-gray-500">Database</td>
                  <td class="font-medium">{{ $systemInfo['db_version'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                  <td class="text-gray-500">GD</td>
                  <td class="font-medium">{{ $systemInfo['gd_version'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                  <td class="text-gray-500">cURL</td>
                  <td class="font-medium">{{ $systemInfo['curl_version'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                  <td class="text-gray-500">memory_limit</td>
                  <td class="font-medium">{{ $systemInfo['memory_limit'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                  <td class="text-gray-500">upload_max_filesize</td>
                  <td class="font-medium">{{ $systemInfo['upload_max_filesize'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                  <td class="text-gray-500">max_execution_time</td>
                  <td class="font-medium">{{ $systemInfo['max_execution_time'] ?? 'N/A' }} s</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>

  {{-- ==================== VERSION HISTORY TAB ==================== --}}
  <div x-show="activeTab === 'history'" x-cloak id="history-pane" role="tabpanel">

    {{-- Collapsible Record Form --}}
    <div class="mb-4">
      <button class="text-sm px-3 py-1.5 rounded-lg flex items-center gap-1" type="button"
          @click="showVersionForm = !showVersionForm"
          style="background:rgba(99,102,241,0.08);color:#6366f1;border:none;border-radius:8px;font-weight:500;padding:0.4rem 1rem;">
        <i class="bi bi-plus-circle"></i> บันทึก Version ใหม่
      </button>

      <div x-show="showVersionForm" x-cloak x-transition class="mt-3" id="recordVersionForm">
        <div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.06);max-width:580px;">
          <div class="p-5 p-4">
            <form method="POST" action="{{ route('admin.settings.version.record') }}">
              @csrf
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div class="col-sm-5">
                  <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium small">Version <span class="text-red-600">*</span></label>
                  <input type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="version"
                      placeholder="1.0.0" required maxlength="20"
                      style="border-radius:10px;" value="{{ old('version') }}">
                </div>
                <div class="">
                  <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium small">Type <span class="text-red-600">*</span></label>
                  <select class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500" name="type" required style="border-radius:10px;">
                    <option value="patch" {{ old('type') == 'patch' ? 'selected' : '' }}>Patch</option>
                    <option value="minor" {{ old('type') == 'minor' ? 'selected' : '' }}>Minor</option>
                    <option value="major" {{ old('type') == 'major' ? 'selected' : '' }}>Major</option>
                    <option value="hotfix" {{ old('type') == 'hotfix' ? 'selected' : '' }}>Hotfix</option>
                  </select>
                </div>
                <div class="">
                  <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium small">Title <span class="text-red-600">*</span></label>
                  <input type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="title" required maxlength="255"
                      style="border-radius:10px;" value="{{ old('title') }}"
                      placeholder="ชื่อ release">
                </div>
                <div class="">
                  <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium small">Description</label>
                  <textarea class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="description" rows="3"
                       style="border-radius:10px;" placeholder="รายละเอียดการเปลี่ยนแปลง">{{ old('description') }}</textarea>
                </div>
                <div class="">
                  <button type="submit" class="btn px-4"
                      style="background:#6366f1;color:#fff;border-radius:10px;border:none;font-weight:500;">
                    <i class="bi bi-save mr-1"></i> บันทึก
                  </button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    {{-- Version History Table --}}
    <div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
      <div class="p-5 p-0">
        @if($versionHistory->isEmpty())
          <div class="text-center py-5 text-gray-500">
            <i class="bi bi-inbox" style="font-size:2rem;opacity:0.4;"></i>
            <div class="mt-2">ยังไม่มีประวัติ version</div>
          </div>
        @else
          <div class="overflow-x-auto">
            <table class="table table-hover align-middle mb-0" style="font-size:0.875rem;">
              <thead style="background:#f8f9fb;">
                <tr>
                  <th class="px-3 py-3 text-gray-500 font-medium">Version</th>
                  <th class="px-3 py-3 text-gray-500 font-medium">Title</th>
                  <th class="px-3 py-3 text-gray-500 font-medium">Description</th>
                  <th class="px-3 py-3 text-gray-500 font-medium">Date</th>
                </tr>
              </thead>
              <tbody>
                @foreach($versionHistory as $v)
                @php
                  $typeColors = [
                    'major' => 'background:#fee2e2;color:#991b1b;',
                    'minor' => 'background:#dbeafe;color:#1e40af;',
                    'patch' => 'background:#d1fae5;color:#065f46;',
                    'hotfix' => 'background:#ffedd5;color:#9a3412;',
                  ];
                  $tc = $typeColors[$v->type ?? ''] ?? 'background:#f3f4f6;color:#374151;';
                @endphp
                <tr>
                  <td class="px-3">
                    <span class="badge mr-1" style="{{ $tc }}border-radius:6px;font-size:0.75rem;">
                      {{ strtoupper($v->type ?? '') }}
                    </span>
                    <span class="font-semibold">{{ $v->version }}</span>
                  </td>
                  <td class="px-3">{{ $v->title }}</td>
                  <td class="px-3 text-gray-500" style="max-width:260px;">
                    {{ \Illuminate\Support\Str::limit($v->description ?? '', 80) ?: '—' }}
                  </td>
                  <td class="px-3 text-gray-500">{{ $v->created_at }}</td>
                </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </div>
    </div>
  </div>

</div>
</div>
@endsection
