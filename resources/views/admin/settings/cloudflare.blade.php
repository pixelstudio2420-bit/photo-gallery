@extends('layouts.admin')

@section('title', 'Cloudflare Settings')

@section('content')
<div class="flex items-center justify-between mb-4">
  <div>
    <h4 class="font-bold mb-0">
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="#f6821f" class="mr-2" style="vertical-align:-3px;">
        <path d="M16.5 16.5c.28-1.02.17-1.96-.32-2.65-.45-.64-1.18-1.02-2.03-1.07l-.38-.01-.17-.35c-.5-1.02-1.52-1.67-2.66-1.67-1.6 0-2.94 1.27-3.03 2.87l-.03.45-.45.04c-.96.09-1.71.88-1.71 1.85 0 1.03.84 1.87 1.87 1.87l8.06-.01c.82 0 1.5-.61 1.59-1.41l.01-.1-.75.01z"/>
      </svg>
      Cloudflare
    </h4>
    <p class="text-gray-500 small mb-0 mt-1">Connect to Cloudflare for CDN, DNS management, and cache control</p>
  </div>
  <a href="{{ route('admin.settings.index') }}" class="text-sm px-3 py-1.5 rounded-lg btn-outline-secondary" style="border-radius:10px;">
    <i class="bi bi-arrow-left mr-1"></i>Back to Settings
  </a>
</div>

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

{{-- Status card (shown when configured) --}}
@if($isConfigured)
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;border-left:4px solid #f6821f !important;">
  <div class="p-5 p-4">
    <div class="flex items-center gap-3">
      <div class="rounded-full flex items-center justify-center"
         style="width:48px;height:48px;background:rgba(246,130,31,0.12);flex-shrink:0;">
        <i class="bi bi-cloud-check" style="color:#f6821f;font-size:1.4rem;"></i>
      </div>
      <div class="grow">
        @if($zoneInfo)
          <div class="font-bold">{{ $zoneInfo['name'] ?? 'Zone configured' }}</div>
          <div class="text-gray-500 small flex gap-3 mt-1">
            <span>
              <i class="bi bi-circle-fill mr-1"
                style="font-size:.5rem;color:{{ ($zoneInfo['status'] ?? '') === 'active' ? '#10b981' : '#f59e0b' }};"></i>
              Status: {{ ucfirst($zoneInfo['status'] ?? 'unknown') }}
            </span>
            @if(!empty($zoneInfo['plan']['name']))
            <span><i class="bi bi-award mr-1"></i>Plan: {{ $zoneInfo['plan']['name'] }}</span>
            @endif
          </div>
        @else
          <div class="font-bold">ตั้งค่าแล้ว</div>
          <div class="text-gray-500 small">Cloudflare is configured. Zone info could not be fetched right now.</div>
        @endif
      </div>
      <span class="badge" style="background:rgba(16,185,129,0.1);color:#059669;border-radius:8px;font-size:.8rem;">
        <i class="bi bi-check-circle mr-1"></i>Connected
      </span>
    </div>
  </div>
</div>
@endif

{{-- Configuration card --}}
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="px-5 py-4 border-b border-gray-100 bg-transparent border-b px-4 pt-4 pb-3" style="border-radius:14px 14px 0 0;">
    <h6 class="font-bold mb-0"><i class="bi bi-key mr-2" style="color:#f6821f;"></i>API Configuration</h6>
    <p class="text-gray-500 small mb-0 mt-1">Enter your Cloudflare API credentials to enable integration</p>
  </div>
  <form method="POST" action="{{ route('admin.settings.cloudflare.update') }}">
    @csrf
    <div class="p-5 p-4">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium small" for="cloudflare_api_token">
            API Token
            <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank" rel="noopener"
              class="ml-1 small" style="color:#f6821f;">
              <i class="bi bi-box-arrow-up-right"></i> Get token
            </a>
          </label>
          <div class="flex">
            <input type="password" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="cloudflare_api_token" id="cloudflare_api_token"
                placeholder="{{ $settings['cloudflare_api_token'] ? 'Token saved (enter new value to change)' : 'Enter API token…' }}"
                value="{{ $settings['cloudflare_api_token'] ?? '' }}"
                autocomplete="new-password"
                style="border-radius:8px 0 0 8px;">
            <button class="border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg transition" type="button" id="toggleToken"
                style="border-radius:0 8px 8px 0;" title="Show/hide">
              <i class="bi bi-eye" id="toggleTokenIcon"></i>
            </button>
          </div>
          <div class="text-gray-500 mt-1" style="font-size:0.78rem;">
            Leave blank to keep the existing token. Needs <code>Zone:Read</code> and <code>Cache Purge</code> permissions.
          </div>
        </div>

        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium small" for="cloudflare_zone_id">Zone ID</label>
          <input type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="cloudflare_zone_id" id="cloudflare_zone_id"
              placeholder="e.g. 023e105f4ecef8ad9ca31a8372d0c353"
              value="{{ $settings['cloudflare_zone_id'] ?? '' }}"
              style="border-radius:8px;">
          <div class="text-gray-500 mt-1" style="font-size:0.78rem;">
            Find this in the Overview tab of your domain in the Cloudflare dashboard.
          </div>
        </div>

        <div class="">
          <div class="flex items-center justify-between p-3 rounded-xl"
             style="background:rgba(246,130,31,0.06);">
            <div>
              <div class="font-medium small">Enable CDN / Proxy</div>
              <div class="text-gray-500" style="font-size:0.78rem;">
                Route traffic through Cloudflare's global network for caching and DDoS protection
              </div>
            </div>
            <div class="form-check form-switch mb-0 ml-3">
              <input class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" type="checkbox" role="switch"
                  name="cloudflare_cdn_enabled" id="cloudflare_cdn_enabled"
                  style="width:2.5rem;height:1.25rem;cursor:pointer;"
                  {{ ($settings['cloudflare_cdn_enabled'] ?? '0') === '1' ? 'checked' : '' }}>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="px-5 py-3 border-t border-gray-100 bg-transparent border-t px-4 py-3 flex justify-end" style="border-radius:0 0 14px 14px;">
      <button type="submit" class="btn px-4" style="background:#f6821f;color:#fff;border-radius:10px;font-weight:600;">
        <i class="bi bi-save mr-2"></i>Save Cloudflare Settings
      </button>
    </div>
  </form>
</div>

{{-- Cache Management (only when configured) --}}
@if($isConfigured)
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="px-5 py-4 border-b border-gray-100 bg-transparent border-b px-4 pt-4 pb-3" style="border-radius:14px 14px 0 0;">
    <h6 class="font-bold mb-0"><i class="bi bi-arrow-repeat mr-2" style="color:#f6821f;"></i>Cache Management</h6>
    <p class="text-gray-500 small mb-0 mt-1">Purge cached content from Cloudflare's edge nodes</p>
  </div>
  <div class="p-5 p-4">
    <div class="flex items-center justify-between p-3 rounded-xl border">
      <div>
        <div class="font-medium">Purge All Cache</div>
        <div class="text-gray-500 small">Remove all cached files from Cloudflare's edge nodes globally. Visitors will fetch fresh content.</div>
      </div>
      <form method="POST" action="{{ route('admin.settings.cloudflare.update') }}" class="ml-3">
        @csrf
        <input type="hidden" name="action" value="purge_all">
        <button type="submit" class="border border-red-600 text-red-600 hover:bg-red-600 hover:text-white px-4 py-2 rounded-lg transition px-3"
            style="border-radius:8px;"
            onclick="return confirm('Are you sure you want to purge ALL Cloudflare cache? This will cause a temporary increase in origin server load.')">
          <i class="bi bi-trash mr-1"></i>Purge All Cache
        </button>
      </form>
    </div>
  </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════════
   R2 Object Storage
═══════════════════════════════════════════════════════════════════ --}}
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="px-5 py-4 border-b border-gray-100 bg-transparent border-b px-4 pt-4 pb-3" style="border-radius:14px 14px 0 0;">
    <div class="flex items-center justify-between">
      <div>
        <h6 class="font-bold mb-0"><i class="bi bi-bucket mr-2" style="color:#f6821f;"></i>R2 Object Storage</h6>
        <p class="text-gray-500 small mb-0 mt-1">S3-compatible storage for photos, thumbnails, and downloads</p>
      </div>
      @if($r2Configured)
        <span class="badge" style="background:rgba(16,185,129,0.1);color:#059669;border-radius:8px;font-size:.8rem;">
          <i class="bi bi-check-circle mr-1"></i>Configured
        </span>
      @else
        <span class="badge" style="background:rgba(239,68,68,0.1);color:#dc2626;border-radius:8px;font-size:.8rem;">
          Not configured
        </span>
      @endif
    </div>
  </div>
  <form method="POST" action="{{ route('admin.settings.cloudflare.update') }}">
    @csrf
    <input type="hidden" name="r2_section" value="1">
    <div class="p-5 p-4">
      {{-- Enable toggle --}}
      <div class="flex items-center justify-between p-3 rounded-xl mb-4"
         style="background:rgba(246,130,31,0.06);">
        <div>
          <div class="font-medium small">Enable R2 Storage</div>
          <div class="text-gray-500" style="font-size:0.78rem;">
            Use Cloudflare R2 as the primary storage for uploaded photos
          </div>
        </div>
        <div class="form-check form-switch mb-0 ml-3">
          <input class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" type="checkbox" role="switch"
              name="r2_enabled" id="r2_enabled"
              style="width:2.5rem;height:1.25rem;cursor:pointer;"
              {{ ($settings['r2_enabled'] ?? '0') === '1' ? 'checked' : '' }}>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {{-- Access Key ID --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium small" for="r2_access_key_id">Access Key ID</label>
          <input type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="r2_access_key_id" id="r2_access_key_id"
              value="{{ $settings['r2_access_key_id'] ?? '' }}"
              placeholder="R2 Access Key ID"
              style="border-radius:8px;">
          <div class="text-gray-500 mt-1" style="font-size:0.78rem;">
            Create an R2 API token in <strong>Cloudflare Dashboard &gt; R2 &gt; Manage R2 API Tokens</strong>.
          </div>
        </div>

        {{-- Secret Access Key --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium small" for="r2_secret_access_key">Secret Access Key</label>
          <div class="flex">
            <input type="password" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="r2_secret_access_key" id="r2_secret_access_key"
                placeholder="{{ ($settings['r2_secret_masked'] ?? '') ? 'Secret saved (enter new to change)' : 'R2 Secret Access Key' }}"
                value="{{ $settings['r2_secret_masked'] ?? '' }}"
                autocomplete="new-password"
                style="border-radius:8px 0 0 8px;">
            <button class="border border-gray-300 text-gray-700 hover:bg-gray-50 px-4 py-2 rounded-lg transition toggle-pw-btn" type="button" data-target="r2_secret_access_key"
                style="border-radius:0 8px 8px 0;" title="Show/hide">
              <i class="bi bi-eye"></i>
            </button>
          </div>
          <div class="text-gray-500 mt-1" style="font-size:0.78rem;">Leave blank to keep existing value.</div>
        </div>

        {{-- Bucket Name --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium small" for="r2_bucket">Bucket Name</label>
          <input type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="r2_bucket" id="r2_bucket"
              value="{{ $settings['r2_bucket'] ?? '' }}"
              placeholder="my-photo-bucket"
              style="border-radius:8px;">
          <div class="text-gray-500 mt-1" style="font-size:0.78rem;">
            The R2 bucket name you created in the Cloudflare dashboard.
          </div>
        </div>

        {{-- Endpoint --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium small" for="r2_endpoint">S3 API Endpoint</label>
          <input type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="r2_endpoint" id="r2_endpoint"
              value="{{ $settings['r2_endpoint'] ?? '' }}"
              placeholder="https://<ACCOUNT_ID>.r2.cloudflarestorage.com"
              style="border-radius:8px;">
          <div class="text-gray-500 mt-1" style="font-size:0.78rem;">
            Format: <code>https://&lt;ACCOUNT_ID&gt;.r2.cloudflarestorage.com</code>
          </div>
        </div>

        {{-- Public URL (r2.dev) --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium small" for="r2_public_url">Public Bucket URL <span class="text-gray-500 font-normal">(optional)</span></label>
          <input type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="r2_public_url" id="r2_public_url"
              value="{{ $settings['r2_public_url'] ?? '' }}"
              placeholder="https://pub-xxx.r2.dev"
              style="border-radius:8px;">
          <div class="text-gray-500 mt-1" style="font-size:0.78rem;">
            Enable "Public access" on your R2 bucket to get a <code>pub-xxx.r2.dev</code> URL. Used for serving files publicly.
          </div>
        </div>

        {{-- Custom Domain --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium small" for="r2_custom_domain">Custom Domain <span class="text-gray-500 font-normal">(optional)</span></label>
          <input type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="r2_custom_domain" id="r2_custom_domain"
              value="{{ $settings['r2_custom_domain'] ?? '' }}"
              placeholder="cdn.example.com"
              style="border-radius:8px;">
          <div class="text-gray-500 mt-1" style="font-size:0.78rem;">
            Connect a custom domain to your R2 bucket for branded URLs. Set this in R2 &gt; Settings &gt; Custom Domains.
          </div>
        </div>
      </div>

      {{-- R2 Test Connection --}}
      @if($r2Configured)
      <div class="mt-4 p-3 rounded-xl border flex items-center justify-between">
        <div>
          <div class="font-medium small">Test R2 Connection</div>
          <div class="text-gray-500" style="font-size:0.78rem;">Verify your R2 credentials and bucket access</div>
        </div>
        <button type="button" class="text-sm px-3 py-1.5 rounded-lg px-3" style="background:rgba(246,130,31,0.1);color:#f6821f;border-radius:8px;font-weight:600;" id="btnTestR2">
          <i class="bi bi-plug mr-1"></i>Test Connection
          <span class="spinner-border spinner-border-sm ml-1 hidden" role="status" id="r2Spinner"></span>
        </button>
      </div>
      <div class="mt-2 small hidden" id="r2TestResult"></div>
      @endif
    </div>
    <div class="px-5 py-3 border-t border-gray-100 bg-transparent border-t px-4 py-3 flex justify-end" style="border-radius:0 0 14px 14px;">
      <button type="submit" class="btn px-4" style="background:#f6821f;color:#fff;border-radius:10px;font-weight:600;">
        <i class="bi bi-save mr-2"></i>Save R2 Settings
      </button>
    </div>
  </form>
</div>

{{-- Setup instructions (only when NOT configured) --}}
@if(!$isConfigured)
<div class="card border-0 shadow-sm" style="border-radius:14px;border-left:4px solid #6366f1 !important;">
  <div class="p-5 p-4">
    <h6 class="font-bold mb-3"><i class="bi bi-info-circle mr-2" style="color:#6366f1;"></i>How to Set Up Cloudflare Integration</h6>
    <ol class="mb-0 small" style="line-height:2;">
      <li>Log in to your <a href="https://dash.cloudflare.com" target="_blank" rel="noopener" style="color:#f6821f;">Cloudflare dashboard</a>.</li>
      <li>Select the domain (zone) you want to connect to this site.</li>
      <li>Copy the <strong>Zone ID</strong> from the right-hand sidebar of the Overview page and paste it above.</li>
      <li>Go to <strong>My Profile → API Tokens</strong> and create a token with <code>Zone:Read</code> and <code>Cache Purge</code> permissions for your zone.</li>
      <li>Paste the token in the <strong>API Token</strong> field above and save.</li>
    </ol>
  </div>
</div>
@endif

<script>
(function () {
  // Toggle CF API token visibility
  var btn = document.getElementById('toggleToken');
  var icon = document.getElementById('toggleTokenIcon');
  var inp = document.getElementById('cloudflare_api_token');
  if (btn && inp) {
    btn.addEventListener('click', function () {
      if (inp.type === 'password') {
        inp.type = 'text';
        icon.className = 'bi bi-eye-slash';
      } else {
        inp.type = 'password';
        icon.className = 'bi bi-eye';
      }
    });
  }

  // Toggle R2 secret visibility
  document.querySelectorAll('.toggle-pw-btn').forEach(function(b) {
    b.addEventListener('click', function() {
      var target = document.getElementById(this.dataset.target);
      if (!target) return;
      var ic = this.querySelector('i');
      if (target.type === 'password') {
        target.type = 'text';
        ic.className = 'bi bi-eye-slash';
      } else {
        target.type = 'password';
        ic.className = 'bi bi-eye';
      }
    });
  });

  // R2 Test Connection
  var testBtn = document.getElementById('btnTestR2');
  if (testBtn) {
    testBtn.addEventListener('click', function() {
      var spinner = document.getElementById('r2Spinner');
      var result = document.getElementById('r2TestResult');
      spinner.classList.remove('hidden');
      result.classList.add('hidden');
      testBtn.disabled = true;

      fetch('{{ route("admin.settings.cloudflare.update") }}', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': '{{ csrf_token() }}',
          'Accept': 'application/json',
        },
        body: JSON.stringify({ action: 'test_r2' })
      })
      .then(r => r.json())
      .then(data => {
        result.classList.remove('hidden');
        if (data.success) {
          result.innerHTML = '<span class="text-green-600"><i class="bi bi-check-circle mr-1"></i>' + data.message + '</span>';
        } else {
          result.innerHTML = '<span class="text-red-600"><i class="bi bi-x-circle mr-1"></i>' + (data.message || 'Connection failed') + '</span>';
        }
      })
      .catch(function() {
        result.classList.remove('hidden');
        result.innerHTML = '<span class="text-red-600"><i class="bi bi-x-circle mr-1"></i>Request failed</span>';
      })
      .finally(function() {
        spinner.classList.add('hidden');
        testBtn.disabled = false;
      });
    });
  }
})();
</script>
@endsection
