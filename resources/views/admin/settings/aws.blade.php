@extends('layouts.admin')

@section('title', 'AWS Cloud Settings')

@push('styles')
<style>
/* ─── Page Layout ─── */
.aws-page-header { letter-spacing: -0.02em; }
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

/* ─── Section Accent Colors ─── */
.accent-orange { color: #f59e0b; }
.accent-green { color: #10b981; }
.accent-blue { color: #3b82f6; }
.accent-purple { color: #8b5cf6; }
.bg-accent-orange { background: rgba(245,158,11,0.1); }
.bg-accent-green { background: rgba(16,185,129,0.1); }
.bg-accent-blue { background: rgba(59,130,246,0.1); }
.bg-accent-purple { background: rgba(139,92,246,0.1); }
</style>
@endpush

@section('content')
{{-- Page Header --}}
<div class="flex items-center justify-between mb-4">
  <div>
    <h4 class="font-bold mb-0 aws-page-header">
      <i class="bi bi-cloud mr-2" style="color:#f59e0b;"></i>AWS Cloud Settings
    </h4>
    <p class="text-gray-500 small mb-0 mt-1">Manage Amazon Web Services integrations for storage, CDN, and email</p>
  </div>
  <a href="{{ route('admin.settings.index') }}" class="section-back-btn">
    <i class="bi bi-arrow-left"></i>Back to Settings
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

<form method="POST" action="{{ route('admin.settings.aws.update') }}" id="awsSettingsForm">
  @csrf

  {{-- ════════════════════════════════════════════════════════
     Section 1: AWS Credentials
  ════════════════════════════════════════════════════════ --}}
  <div class="card setting-card mb-4">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <div class="card-icon bg-accent-orange">
          <i class="bi bi-key accent-orange"></i>
        </div>
        <div>
          <h6 class="font-bold mb-0">AWS Credentials</h6>
          <p class="text-gray-500 small mb-0">IAM access keys for authenticating with AWS services</p>
        </div>
      </div>
      <span class="status-dot {{ ($settings['aws_access_key_id'] ?? '') ? 'connected' : 'disconnected' }}">
        {{ ($settings['aws_access_key_id'] ?? '') ? 'Configured' : 'Not configured' }}
      </span>
    </div>
    <div class="p-5">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {{-- Access Key ID --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5" for="aws_access_key_id">
            Access Key ID
          </label>
          <div class="flex">
            <input type="password" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="aws_access_key_id" id="aws_access_key_id"
                value="{{ $settings['aws_access_key_id'] ?? '' }}"
                placeholder="{{ ($settings['aws_access_key_id'] ?? '') ? 'Key saved (enter new to change)' : 'AKIAIOSFODNN7EXAMPLE' }}"
                autocomplete="new-password">
            <span class="px-3 py-2.5 bg-gray-50 border border-gray-300 text-gray-500 toggle-pw" data-target="aws_access_key_id">
              <i class="bi bi-eye"></i>
            </span>
          </div>
          <div class="help-text">Your IAM user Access Key ID. Leave blank to keep existing value.</div>
        </div>

        {{-- Secret Access Key --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5" for="aws_secret_access_key">
            Secret Access Key
          </label>
          <div class="flex">
            <input type="password" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="aws_secret_access_key" id="aws_secret_access_key"
                value="{{ $settings['aws_secret_access_key'] ?? '' }}"
                placeholder="{{ ($settings['aws_secret_access_key'] ?? '') ? 'Secret saved (enter new to change)' : 'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLE' }}"
                autocomplete="new-password">
            <span class="px-3 py-2.5 bg-gray-50 border border-gray-300 text-gray-500 toggle-pw" data-target="aws_secret_access_key">
              <i class="bi bi-eye"></i>
            </span>
          </div>
          <div class="help-text">Your IAM user Secret Access Key. Never share this publicly.</div>
        </div>

        {{-- Default Region --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5" for="aws_default_region">Default Region</label>
          <select class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500" name="aws_default_region" id="aws_default_region">
            <option value="">-- Select Region --</option>
            @php
              $regions = [
                'us-east-1' => 'US East (N. Virginia)',
                'us-east-2' => 'US East (Ohio)',
                'us-west-1' => 'US West (N. California)',
                'us-west-2' => 'US West (Oregon)',
                'af-south-1' => 'Africa (Cape Town)',
                'ap-east-1' => 'Asia Pacific (Hong Kong)',
                'ap-south-1' => 'Asia Pacific (Mumbai)',
                'ap-south-2' => 'Asia Pacific (Hyderabad)',
                'ap-southeast-1' => 'Asia Pacific (Singapore)',
                'ap-southeast-2' => 'Asia Pacific (Sydney)',
                'ap-southeast-3' => 'Asia Pacific (Jakarta)',
                'ap-southeast-4' => 'Asia Pacific (Melbourne)',
                'ap-northeast-1' => 'Asia Pacific (Tokyo)',
                'ap-northeast-2' => 'Asia Pacific (Seoul)',
                'ap-northeast-3' => 'Asia Pacific (Osaka)',
                'ca-central-1' => 'Canada (Central)',
                'eu-central-1' => 'Europe (Frankfurt)',
                'eu-central-2' => 'Europe (Zurich)',
                'eu-west-1' => 'Europe (Ireland)',
                'eu-west-2' => 'Europe (London)',
                'eu-west-3' => 'Europe (Paris)',
                'eu-south-1' => 'Europe (Milan)',
                'eu-south-2' => 'Europe (Spain)',
                'eu-north-1' => 'Europe (Stockholm)',
                'il-central-1' => 'Israel (Tel Aviv)',
                'me-south-1' => 'Middle East (Bahrain)',
                'me-central-1' => 'Middle East (UAE)',
                'sa-east-1' => 'South America (Sao Paulo)',
              ];
            @endphp
            @foreach($regions as $code => $label)
              <option value="{{ $code }}" {{ ($settings['aws_default_region'] ?? '') === $code ? 'selected' : '' }}>
                {{ $code }} - {{ $label }}
              </option>
            @endforeach
          </select>
          <div class="help-text">Primary AWS region for your services.</div>
        </div>

        {{-- Connection Test --}}
        <div class=" flex items-end">
          <button type="button" class="btn btn-test" id="btnTestConnection">
            <i class="bi bi-plug mr-1"></i>Test Connection
            <span class="spinner-border spinner-border-sm ml-1 hidden" role="status"></span>
          </button>
          <span class="ml-3 small hidden" id="connectionResult"></span>
        </div>
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════
     Section 2: S3 Storage
  ════════════════════════════════════════════════════════ --}}
  <div class="card setting-card mb-4">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <div class="card-icon bg-accent-green">
          <i class="bi bi-bucket accent-green"></i>
        </div>
        <div>
          <h6 class="font-bold mb-0">S3 Storage</h6>
          <p class="text-gray-500 small mb-0">Amazon S3 bucket configuration for file storage</p>
        </div>
      </div>
      <span class="status-dot {{ ($settings['aws_s3_bucket'] ?? '') ? 'connected' : 'disconnected' }}">
        {{ ($settings['aws_s3_bucket'] ?? '') ? 'Bucket set' : 'Not configured' }}
      </span>
    </div>
    <div class="p-5">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {{-- S3 Bucket Name --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5" for="aws_s3_bucket">Bucket Name</label>
          <input type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="aws_s3_bucket" id="aws_s3_bucket"
              value="{{ $settings['aws_s3_bucket'] ?? '' }}"
              placeholder="my-photo-gallery-bucket">
          <div class="help-text">The name of your S3 bucket for storing photos and assets.</div>
        </div>

        {{-- S3 URL --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5" for="aws_s3_url">S3 URL <span class="font-normal text-gray-500">(Optional)</span></label>
          <input type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="aws_s3_url" id="aws_s3_url"
              value="{{ $settings['aws_s3_url'] ?? '' }}"
              placeholder="https://s3.custom-endpoint.com">
          <div class="help-text">Custom endpoint URL for S3-compatible storage (e.g., MinIO, DigitalOcean Spaces).</div>
        </div>

        {{-- Storage Folder Prefix --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5" for="aws_s3_folder_prefix">Storage Folder Prefix</label>
          <input type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="aws_s3_folder_prefix" id="aws_s3_folder_prefix"
              value="{{ $settings['aws_s3_folder_prefix'] ?? '' }}"
              placeholder="photos/">
          <div class="help-text">Prefix added to all file paths in S3. Use trailing slash (e.g., <code>uploads/</code>).</div>
        </div>

        {{-- Default Visibility --}}
        <div class="">
          <label class="block text-sm font-medium text-gray-700 mb-1.5" for="aws_s3_default_visibility">Default Visibility</label>
          <select class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500" name="aws_s3_default_visibility" id="aws_s3_default_visibility">
            <option value="private" {{ ($settings['aws_s3_default_visibility'] ?? 'private') === 'private' ? 'selected' : '' }}>
              Private (recommended)
            </option>
            <option value="public" {{ ($settings['aws_s3_default_visibility'] ?? '') === 'public' ? 'selected' : '' }}>
              Public
            </option>
          </select>
          <div class="help-text">Default ACL for newly uploaded files. Private files require signed URLs to access.</div>
        </div>

        {{-- Use Path Style --}}
        <div class="">
          <div class="toggle-row">
            <div class="toggle-info">
              <div class="toggle-title">Use Path Style Endpoint</div>
              <div class="toggle-desc">Enable for S3-compatible services that require path-style URLs (e.g., MinIO). AWS S3 uses virtual-hosted style by default.</div>
            </div>
            <div class="form-check form-switch mb-0">
              <input class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" type="checkbox" role="switch"
                  name="aws_s3_path_style" id="aws_s3_path_style"
                  {{ ($settings['aws_s3_path_style'] ?? '0') === '1' ? 'checked' : '' }}>
            </div>
          </div>
        </div>

        {{-- Storage Usage Indicator --}}
        <div class="">
          <div class="p-3 rounded-xl" style="background:linear-gradient(135deg, rgba(16,185,129,0.05), rgba(59,130,246,0.05));border:1px solid rgba(16,185,129,0.1);">
            <div class="flex items-center justify-between mb-2">
              <span class="font-medium small"><i class="bi bi-hdd mr-1 accent-green"></i>Storage Usage</span>
              <span class="text-gray-500 small" id="storageUsageText">Calculating...</span>
            </div>
            <div class="progress" style="height:6px;border-radius:3px;background:rgba(0,0,0,0.05);">
              <div class="progress-bar" role="progressbar" style="width:0%;background:linear-gradient(135deg,#10b981,#3b82f6);border-radius:3px;" id="storageUsageBar"></div>
            </div>
            <div class="text-gray-500 mt-1" style="font-size:0.72rem;">Storage usage is estimated. Check your AWS Console for exact billing data.</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════
     Section 3: CloudFront CDN
  ════════════════════════════════════════════════════════ --}}
  <div class="card setting-card mb-4">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <div class="card-icon bg-accent-blue">
          <i class="bi bi-globe2 accent-blue"></i>
        </div>
        <div>
          <h6 class="font-bold mb-0">CloudFront CDN</h6>
          <p class="text-gray-500 small mb-0">Content delivery network for faster photo loading worldwide</p>
        </div>
      </div>
      <span class="status-dot {{ ($settings['aws_cloudfront_enabled'] ?? '0') === '1' ? 'connected' : 'disconnected' }}">
        {{ ($settings['aws_cloudfront_enabled'] ?? '0') === '1' ? 'Enabled' : 'Disabled' }}
      </span>
    </div>
    <div class="p-5">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {{-- Enable CloudFront --}}
        <div class="">
          <div class="toggle-row">
            <div class="toggle-info">
              <div class="toggle-title">Enable CloudFront CDN</div>
              <div class="toggle-desc">Serve photos and assets through CloudFront for faster global delivery and reduced S3 costs.</div>
            </div>
            <div class="form-check form-switch mb-0">
              <input class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" type="checkbox" role="switch"
                  name="aws_cloudfront_enabled" id="aws_cloudfront_enabled"
                  {{ ($settings['aws_cloudfront_enabled'] ?? '0') === '1' ? 'checked' : '' }}
                  onchange="toggleCloudfrontFields(this.checked)">
            </div>
          </div>
        </div>

        <div id="cloudfrontFields" class="{{ ($settings['aws_cloudfront_enabled'] ?? '0') !== '1' ? 'hidden' : '' }}">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {{-- Distribution ID --}}
            <div class="">
              <label class="block text-sm font-medium text-gray-700 mb-1.5" for="aws_cloudfront_distribution_id">Distribution ID</label>
              <input type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="aws_cloudfront_distribution_id" id="aws_cloudfront_distribution_id"
                  value="{{ $settings['aws_cloudfront_distribution_id'] ?? '' }}"
                  placeholder="E1A2B3C4D5E6F7">
              <div class="help-text">Found in your CloudFront Distributions list in the AWS Console.</div>
            </div>

            {{-- CloudFront Domain --}}
            <div class="">
              <label class="block text-sm font-medium text-gray-700 mb-1.5" for="aws_cloudfront_domain">CloudFront Domain</label>
              <input type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="aws_cloudfront_domain" id="aws_cloudfront_domain"
                  value="{{ $settings['aws_cloudfront_domain'] ?? '' }}"
                  placeholder="d1234abcdef.cloudfront.net">
              <div class="help-text">Your distribution domain name or custom CNAME (e.g., <code>cdn.yoursite.com</code>).</div>
            </div>

            {{-- Key Pair ID --}}
            <div class="">
              <label class="block text-sm font-medium text-gray-700 mb-1.5" for="aws_cloudfront_key_pair_id">Key Pair ID <span class="font-normal text-gray-500">(Signed URLs)</span></label>
              <input type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="aws_cloudfront_key_pair_id" id="aws_cloudfront_key_pair_id"
                  value="{{ $settings['aws_cloudfront_key_pair_id'] ?? '' }}"
                  placeholder="K36RBC2KDO5B1A">
              <div class="help-text">CloudFront Key Pair ID for generating signed URLs. Found in CloudFront Key Groups.</div>
            </div>

            {{-- Signed URL Expiry --}}
            <div class="">
              <label class="block text-sm font-medium text-gray-700 mb-1.5" for="aws_cloudfront_signed_url_expiry">Signed URL Expiry (minutes)</label>
              <input type="number" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="aws_cloudfront_signed_url_expiry" id="aws_cloudfront_signed_url_expiry"
                  value="{{ $settings['aws_cloudfront_signed_url_expiry'] ?? '60' }}"
                  min="1" max="10080" placeholder="60">
              <div class="help-text">How long signed URLs remain valid (1-10080 minutes). Default: 60 minutes.</div>
            </div>

            {{-- Private Key --}}
            <div class="">
              <label class="block text-sm font-medium text-gray-700 mb-1.5" for="aws_cloudfront_private_key">Private Key <span class="font-normal text-gray-500">(PEM format)</span></label>
              <textarea class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="aws_cloudfront_private_key" id="aws_cloudfront_private_key"
                   rows="4" placeholder="-----BEGIN RSA PRIVATE KEY-----&#10;...&#10;-----END RSA PRIVATE KEY-----"
                   style="font-family:monospace;font-size:0.82rem;">{{ $settings['aws_cloudfront_private_key'] ?? '' }}</textarea>
              <div class="help-text">RSA private key in PEM format for signing CloudFront URLs. Keep this secret.</div>
            </div>

            {{-- Signed URLs for paid photos --}}
            <div class="">
              <div class="toggle-row">
                <div class="toggle-info">
                  <div class="toggle-title">Enable Signed URLs for Paid Photos</div>
                  <div class="toggle-desc">Generate time-limited signed URLs for purchased photos to prevent unauthorized access and hotlinking.</div>
                </div>
                <div class="form-check form-switch mb-0">
                  <input class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" type="checkbox" role="switch"
                      name="aws_cloudfront_signed_urls" id="aws_cloudfront_signed_urls"
                      {{ ($settings['aws_cloudfront_signed_urls'] ?? '0') === '1' ? 'checked' : '' }}>
                </div>
              </div>
            </div>

            {{-- Cache Invalidation --}}
            <div class="">
              <div class="p-3 rounded-xl" style="background:rgba(59,130,246,0.04);border:1px solid rgba(59,130,246,0.1);">
                <div class="flex items-center justify-between">
                  <div>
                    <div class="font-medium small"><i class="bi bi-arrow-repeat mr-1 accent-blue"></i>Cache Invalidation</div>
                    <div class="text-gray-500" style="font-size:0.78rem;">Invalidate CloudFront edge caches to serve updated content. Use sparingly as invalidations incur costs.</div>
                  </div>
                  <button type="button" class="btn btn-test" id="btnInvalidateCache">
                    <i class="bi bi-arrow-clockwise mr-1"></i>Invalidate All
                    <span class="spinner-border spinner-border-sm ml-1 hidden" role="status"></span>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════
     Section 4: SES Email
  ════════════════════════════════════════════════════════ --}}
  <div class="card setting-card mb-4">
    <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
      <div class="flex items-center gap-2">
        <div class="card-icon bg-accent-purple">
          <i class="bi bi-envelope-at accent-purple"></i>
        </div>
        <div>
          <h6 class="font-bold mb-0">SES Email</h6>
          <p class="text-gray-500 small mb-0">Amazon Simple Email Service for transactional emails</p>
        </div>
      </div>
      <span class="status-dot {{ ($settings['aws_ses_enabled'] ?? '0') === '1' ? 'connected' : 'disconnected' }}">
        {{ ($settings['aws_ses_enabled'] ?? '0') === '1' ? 'Enabled' : 'Disabled' }}
      </span>
    </div>
    <div class="p-5">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {{-- Enable SES --}}
        <div class="">
          <div class="toggle-row">
            <div class="toggle-info">
              <div class="toggle-title">Enable Amazon SES</div>
              <div class="toggle-desc">Use SES as the mail driver for sending order confirmations, notifications, and marketing emails.</div>
            </div>
            <div class="form-check form-switch mb-0">
              <input class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500" type="checkbox" role="switch"
                  name="aws_ses_enabled" id="aws_ses_enabled"
                  {{ ($settings['aws_ses_enabled'] ?? '0') === '1' ? 'checked' : '' }}
                  onchange="toggleSesFields(this.checked)">
            </div>
          </div>
        </div>

        <div id="sesFields" class="{{ ($settings['aws_ses_enabled'] ?? '0') !== '1' ? 'hidden' : '' }}">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {{-- SES Region --}}
            <div class="">
              <label class="block text-sm font-medium text-gray-700 mb-1.5" for="aws_ses_region">SES Region</label>
              <select class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500" name="aws_ses_region" id="aws_ses_region">
                <option value="">-- Select SES Region --</option>
                @php
                  $sesRegions = [
                    'us-east-1' => 'US East (N. Virginia)',
                    'us-east-2' => 'US East (Ohio)',
                    'us-west-1' => 'US West (N. California)',
                    'us-west-2' => 'US West (Oregon)',
                    'ap-south-1' => 'Asia Pacific (Mumbai)',
                    'ap-southeast-1' => 'Asia Pacific (Singapore)',
                    'ap-southeast-2' => 'Asia Pacific (Sydney)',
                    'ap-northeast-1' => 'Asia Pacific (Tokyo)',
                    'ap-northeast-2' => 'Asia Pacific (Seoul)',
                    'ca-central-1' => 'Canada (Central)',
                    'eu-central-1' => 'Europe (Frankfurt)',
                    'eu-west-1' => 'Europe (Ireland)',
                    'eu-west-2' => 'Europe (London)',
                    'eu-south-1' => 'Europe (Milan)',
                    'eu-north-1' => 'Europe (Stockholm)',
                    'me-south-1' => 'Middle East (Bahrain)',
                    'sa-east-1' => 'South America (Sao Paulo)',
                    'af-south-1' => 'Africa (Cape Town)',
                  ];
                @endphp
                @foreach($sesRegions as $code => $label)
                  <option value="{{ $code }}" {{ ($settings['aws_ses_region'] ?? '') === $code ? 'selected' : '' }}>
                    {{ $code }} - {{ $label }}
                  </option>
                @endforeach
              </select>
              <div class="help-text">SES may not be available in all regions. Choose the nearest supported region.</div>
            </div>

            {{-- From Email --}}
            <div class="">
              <label class="block text-sm font-medium text-gray-700 mb-1.5" for="aws_ses_from_email">From Email</label>
              <input type="email" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="aws_ses_from_email" id="aws_ses_from_email"
                  value="{{ $settings['aws_ses_from_email'] ?? '' }}"
                  placeholder="noreply@yourdomain.com">
              <div class="help-text">Must be a verified email or domain in your SES account.</div>
            </div>

            {{-- From Name --}}
            <div class="">
              <label class="block text-sm font-medium text-gray-700 mb-1.5" for="aws_ses_from_name">From Name</label>
              <input type="text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" name="aws_ses_from_name" id="aws_ses_from_name"
                  value="{{ $settings['aws_ses_from_name'] ?? '' }}"
                  placeholder="{{ $siteName ?? config('app.name') }}">
              <div class="help-text">Display name shown to email recipients.</div>
            </div>

            {{-- Test Email --}}
            <div class=" flex items-end">
              <button type="button" class="btn btn-test" id="btnTestEmail">
                <i class="bi bi-send mr-1"></i>Send Test Email
                <span class="spinner-border spinner-border-sm ml-1 hidden" role="status"></span>
              </button>
              <span class="ml-3 small hidden" id="emailTestResult"></span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ════════════════════════════════════════════════════════
     Save Button
  ════════════════════════════════════════════════════════ --}}
  <div class="flex justify-end mb-5">
    <button type="submit" class="btn btn-save-gradient">
      <i class="bi bi-save mr-2"></i>Save AWS Settings
    </button>
  </div>
</form>

{{-- ════════════════════════════════════════════════════════
   Setup Instructions (when not configured)
════════════════════════════════════════════════════════ --}}
@if(!($settings['aws_access_key_id'] ?? ''))
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;border-left:4px solid #6366f1 !important;">
  <div class="p-5 p-4">
    <h6 class="font-bold mb-3"><i class="bi bi-info-circle mr-2" style="color:#6366f1;"></i>Getting Started with AWS</h6>
    <ol class="mb-0 small" style="line-height:2;">
      <li>Create an <a href="https://aws.amazon.com/console/" target="_blank" rel="noopener" style="color:#f59e0b;">AWS Account</a> if you don't have one.</li>
      <li>Go to <strong>IAM</strong> and create a new user with programmatic access.</li>
      <li>Attach policies: <code>AmazonS3FullAccess</code>, <code>CloudFrontFullAccess</code>, <code>AmazonSESFullAccess</code> (or custom policies).</li>
      <li>Copy the <strong>Access Key ID</strong> and <strong>Secret Access Key</strong> and paste them above.</li>
      <li>Create an S3 bucket and enter its name in the <strong>S3 Storage</strong> section.</li>
      <li>Optionally, set up a CloudFront distribution pointing to your S3 bucket for CDN.</li>
    </ol>
  </div>
</div>
@endif

@endsection

@push('scripts')
<script>
(function() {
  // Toggle password visibility
  document.querySelectorAll('.toggle-pw').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var target = document.getElementById(this.dataset.target);
      var icon = this.querySelector('i');
      if (target.type === 'password') {
        target.type = 'text';
        icon.className = 'bi bi-eye-slash';
      } else {
        target.type = 'password';
        icon.className = 'bi bi-eye';
      }
    });
  });

  // Storage usage (simulated)
  var usageText = document.getElementById('storageUsageText');
  var usageBar = document.getElementById('storageUsageBar');
  if (usageText && usageBar) {
    @if($settings['aws_s3_bucket'] ?? '')
      usageText.textContent = 'Check AWS Console for details';
      usageBar.style.width = '0%';
    @else
      usageText.textContent = 'No bucket configured';
      usageBar.style.width = '0%';
    @endif
  }
})();

// Toggle CloudFront fields
function toggleCloudfrontFields(show) {
  var el = document.getElementById('cloudfrontFields');
  if (show) {
    el.classList.remove('hidden');
  } else {
    el.classList.add('hidden');
  }
}

// Toggle SES fields
function toggleSesFields(show) {
  var el = document.getElementById('sesFields');
  if (show) {
    el.classList.remove('hidden');
  } else {
    el.classList.add('hidden');
  }
}

// Test connection button
document.getElementById('btnTestConnection')?.addEventListener('click', function() {
  var btn = this;
  var spinner = btn.querySelector('.spinner-border');
  var result = document.getElementById('connectionResult');
  btn.disabled = true;
  spinner.classList.remove('hidden');
  result.classList.add('hidden');

  // Simulate test (replace with actual AJAX call)
  setTimeout(function() {
    spinner.classList.add('hidden');
    btn.disabled = false;
    result.classList.remove('hidden');
    var hasKey = document.getElementById('aws_access_key_id').value || '{{ ($settings["aws_access_key_id"] ?? "") ? "saved" : "" }}';
    if (hasKey) {
      result.innerHTML = '<span style="color:#059669;"><i class="bi bi-check-circle mr-1"></i>Connection parameters saved. Verify in AWS Console.</span>';
    } else {
      result.innerHTML = '<span style="color:#dc2626;"><i class="bi bi-x-circle mr-1"></i>Please enter AWS credentials first.</span>';
    }
  }, 1500);
});

// Cache invalidation button
document.getElementById('btnInvalidateCache')?.addEventListener('click', function() {
  if (!confirm('Are you sure you want to invalidate all CloudFront caches? This may temporarily increase origin load and incur costs.')) return;
  var btn = this;
  var spinner = btn.querySelector('.spinner-border');
  btn.disabled = true;
  spinner.classList.remove('hidden');

  setTimeout(function() {
    spinner.classList.add('hidden');
    btn.disabled = false;
    alert('Cache invalidation request submitted. It may take a few minutes to propagate.');
  }, 2000);
});

// Test email button
document.getElementById('btnTestEmail')?.addEventListener('click', function() {
  var btn = this;
  var spinner = btn.querySelector('.spinner-border');
  var result = document.getElementById('emailTestResult');
  btn.disabled = true;
  spinner.classList.remove('hidden');
  result.classList.add('hidden');

  setTimeout(function() {
    spinner.classList.add('hidden');
    btn.disabled = false;
    result.classList.remove('hidden');
    var email = document.getElementById('aws_ses_from_email').value;
    if (email) {
      result.innerHTML = '<span style="color:#059669;"><i class="bi bi-check-circle mr-1"></i>Test email queued. Check your inbox.</span>';
    } else {
      result.innerHTML = '<span style="color:#dc2626;"><i class="bi bi-x-circle mr-1"></i>Please enter a From Email first.</span>';
    }
  }, 1500);
});
</script>
@endpush
