@extends('layouts.admin')

@section('title', 'Analytics & Tracking')

@push('styles')
@include('admin.settings._shared-styles')
<style>
  /* Open Graph preview card */
  .og-preview-card {
    background: #fff;
    border: 1px solid rgb(226 232 240);
    border-radius: 12px;
    overflow: hidden;
    max-width: 100%;
    box-shadow: 0 1px 4px rgba(0,0,0,0.06);
  }
  .dark .og-preview-card {
    background: rgb(30 41 59);
    border-color: rgb(51 65 85);
  }
  .og-preview-img {
    width: 100%;
    height: 160px;
    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #94a3b8;
    font-size: 2rem;
  }
  .dark .og-preview-img {
    background: linear-gradient(135deg, #1e293b, #0f172a);
    color: #64748b;
  }
  .og-preview-body { padding: 0.75rem 1rem; }
  .og-preview-domain {
    font-size: 0.72rem;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.03em;
  }
  .dark .og-preview-domain { color: #94a3b8; }
  .og-preview-title {
    font-weight: 700;
    font-size: 0.9rem;
    color: #1f2937;
    margin-top: 2px;
  }
  .dark .og-preview-title { color: #f1f5f9; }
  .og-preview-desc {
    font-size: 0.78rem;
    color: #6b7280;
    margin-top: 4px;
    line-height: 1.4;
  }
  .dark .og-preview-desc { color: #94a3b8; }
</style>
@endpush

@php
  $ga4Active   = ($settings['ga4_enabled'] ?? '0') === '1' && ($settings['ga4_measurement_id'] ?? '');
  $pixelActive = ($settings['fb_pixel_enabled'] ?? '0') === '1' && ($settings['fb_pixel_id'] ?? '');
@endphp

@section('content')
<div class="max-w-7xl mx-auto pb-16">

  {{-- ═══════════════════ Header ═══════════════════ --}}
  <div class="mb-8">
    <a href="{{ route('admin.settings.index') }}"
       class="inline-flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition mb-4">
      <i class="bi bi-arrow-left"></i> Back to Settings
    </a>

    <div class="flex items-start gap-4">
      <div class="flex-shrink-0 w-12 h-12 rounded-xl bg-gradient-to-br from-sky-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-sky-500/20">
        <i class="bi bi-bar-chart-fill text-white text-xl"></i>
      </div>
      <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">
          Analytics &amp; Tracking
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
          Configure analytics tracking, social pixels, and Open Graph settings
        </p>
      </div>
    </div>
  </div>

  {{-- ═══════════════════ Flash Messages ═══════════════════ --}}
  @if(session('success'))
    <div class="mb-6 flex items-start gap-3 px-4 py-3 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30">
      <i class="bi bi-check-circle-fill text-emerald-600 dark:text-emerald-400 text-lg"></i>
      <div class="flex-1 text-sm font-medium text-emerald-800 dark:text-emerald-300">{{ session('success') }}</div>
    </div>
  @endif
  @if(session('error'))
    <div class="mb-6 flex items-start gap-3 px-4 py-3 rounded-xl bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/30">
      <i class="bi bi-exclamation-triangle-fill text-rose-600 dark:text-rose-400 text-lg"></i>
      <div class="flex-1 text-sm font-medium text-rose-800 dark:text-rose-300">{{ session('error') }}</div>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.settings.analytics.update') }}" enctype="multipart/form-data" id="analyticsSettingsForm" class="space-y-6">
    @csrf

    {{-- ═══════════════════ Google Analytics (GA4) ═══════════════════ --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm">
      <div class="px-6 py-5 border-b border-slate-200 dark:border-white/10 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-500/15 text-amber-600 dark:text-amber-400 flex items-center justify-center">
            <i class="bi bi-graph-up text-lg"></i>
          </div>
          <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Google Analytics (GA4)</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Track visitor behavior, page views, and conversions</p>
          </div>
        </div>
        <span class="status-dot {{ $ga4Active ? 'connected' : 'disconnected' }}">
          {{ $ga4Active ? 'Active' : 'Inactive' }}
        </span>
      </div>
      <div class="p-6">
        {{-- Enable GA4 --}}
        <div class="mb-5 flex items-center justify-between gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10">
          <div>
            <div class="text-sm font-semibold text-slate-900 dark:text-white">Enable Google Analytics</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Inject GA4 tracking code into all public pages. Respects user cookie consent when configured.</div>
          </div>
          <label class="tw-switch">
            <input type="checkbox" role="switch"
                   name="ga4_enabled" id="ga4_enabled"
                   {{ ($settings['ga4_enabled'] ?? '0') === '1' ? 'checked' : '' }}
                   onchange="toggleGa4Fields(this.checked)">
            <span class="tw-switch-track"></span>
            <span class="tw-switch-knob"></span>
          </label>
        </div>

        <div id="ga4Fields" class="{{ ($settings['ga4_enabled'] ?? '0') !== '1' ? 'hidden' : '' }}">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            {{-- GA4 Measurement ID --}}
            <div>
              <label for="ga4_measurement_id" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">GA4 Measurement ID</label>
              <div class="flex">
                <span class="inline-flex items-center px-3 rounded-l-lg bg-slate-100 dark:bg-slate-800 border border-r-0 border-slate-300 dark:border-white/10 text-slate-500 dark:text-slate-400">
                  <i class="bi bi-google" style="color:#4285f4;"></i>
                </span>
                <input type="text" name="ga4_measurement_id" id="ga4_measurement_id"
                       value="{{ $settings['ga4_measurement_id'] ?? '' }}"
                       placeholder="G-XXXXXXXXXX"
                       class="flex-1 min-w-0 px-3 py-2 text-sm rounded-r-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">
                Find your Measurement ID in
                <a href="https://analytics.google.com/" target="_blank" rel="noopener" class="text-sky-600 dark:text-sky-400 hover:underline">Google Analytics</a>
                &rarr; Admin &rarr; Data Streams &rarr; Web Stream.
              </p>
            </div>

            {{-- Status --}}
            <div class="flex items-stretch">
              @if($ga4Active)
                <div class="w-full p-4 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30">
                  <div class="flex items-center gap-3">
                    <i class="bi bi-check-circle-fill text-emerald-600 dark:text-emerald-400 text-xl"></i>
                    <div>
                      <div class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">Tracking Active</div>
                      <div class="text-xs text-emerald-600/80 dark:text-emerald-400/70 mt-0.5">GA4 code is being injected on all public pages.</div>
                    </div>
                  </div>
                </div>
              @else
                <div class="w-full p-4 rounded-xl bg-slate-100 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10">
                  <div class="flex items-center gap-3">
                    <i class="bi bi-dash-circle text-slate-500 dark:text-slate-400 text-xl"></i>
                    <div>
                      <div class="text-sm font-semibold text-slate-700 dark:text-slate-300">Not Tracking</div>
                      <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Enter your Measurement ID and enable to start tracking.</div>
                    </div>
                  </div>
                </div>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ═══════════════════ Facebook Pixel ═══════════════════ --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm">
      <div class="px-6 py-5 border-b border-slate-200 dark:border-white/10 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-sky-100 dark:bg-sky-500/15 text-sky-600 dark:text-sky-400 flex items-center justify-center">
            <i class="bi bi-facebook text-lg"></i>
          </div>
          <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Facebook Pixel</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">Track conversions and build audiences for Facebook Ads</p>
          </div>
        </div>
        <span class="status-dot {{ $pixelActive ? 'connected' : 'disconnected' }}">
          {{ $pixelActive ? 'Active' : 'Inactive' }}
        </span>
      </div>
      <div class="p-6">
        {{-- Enable Pixel --}}
        <div class="mb-5 flex items-center justify-between gap-4 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10">
          <div>
            <div class="text-sm font-semibold text-slate-900 dark:text-white">Enable Facebook Pixel</div>
            <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Inject Meta Pixel tracking code for conversion tracking and custom audiences.</div>
          </div>
          <label class="tw-switch">
            <input type="checkbox" role="switch"
                   name="fb_pixel_enabled" id="fb_pixel_enabled"
                   {{ ($settings['fb_pixel_enabled'] ?? '0') === '1' ? 'checked' : '' }}
                   onchange="togglePixelFields(this.checked)">
            <span class="tw-switch-track"></span>
            <span class="tw-switch-knob"></span>
          </label>
        </div>

        <div id="pixelFields" class="{{ ($settings['fb_pixel_enabled'] ?? '0') !== '1' ? 'hidden' : '' }}">
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            {{-- Pixel ID --}}
            <div>
              <label for="fb_pixel_id" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Pixel ID</label>
              <div class="flex">
                <span class="inline-flex items-center px-3 rounded-l-lg bg-slate-100 dark:bg-slate-800 border border-r-0 border-slate-300 dark:border-white/10 text-slate-500 dark:text-slate-400">
                  <i class="bi bi-facebook" style="color:#1877f2;"></i>
                </span>
                <input type="text" name="fb_pixel_id" id="fb_pixel_id"
                       value="{{ $settings['fb_pixel_id'] ?? '' }}"
                       placeholder="1234567890123456"
                       class="flex-1 min-w-0 px-3 py-2 text-sm rounded-r-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">
                Find your Pixel ID in
                <a href="https://business.facebook.com/events_manager" target="_blank" rel="noopener" class="text-sky-600 dark:text-sky-400 hover:underline">Meta Events Manager</a>
                &rarr; Data Sources &rarr; Your Pixel.
              </p>
            </div>

            {{-- Info --}}
            <div class="flex items-stretch">
              <div class="w-full p-4 rounded-xl bg-sky-50 dark:bg-sky-500/10 border border-sky-200 dark:border-sky-500/30">
                <div class="flex items-center gap-3">
                  <i class="bi bi-info-circle-fill text-sky-600 dark:text-sky-400 text-xl"></i>
                  <div>
                    <div class="text-sm font-semibold text-sky-700 dark:text-sky-300">Events Tracked</div>
                    <div class="text-xs text-sky-600/80 dark:text-sky-400/70 mt-0.5">PageView, ViewContent, AddToCart, Purchase events are sent automatically.</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- ═══════════════════ Open Graph ═══════════════════ --}}
    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-white/10 rounded-2xl shadow-sm">
      <div class="px-6 py-5 border-b border-slate-200 dark:border-white/10 flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-violet-100 dark:bg-violet-500/15 text-violet-600 dark:text-violet-400 flex items-center justify-center">
          <i class="bi bi-share-fill text-lg"></i>
        </div>
        <div>
          <h2 class="text-base font-bold text-slate-900 dark:text-white">Social Sharing (Open Graph)</h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">Control how your site appears when shared on social media</p>
        </div>
      </div>
      <div class="p-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

          {{-- LEFT — Image upload + fields --}}
          <div class="space-y-5">
            {{-- Default OG Image --}}
            <div>
              <label for="og_default_image_file" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Default OG Image</label>
              <input type="file" name="og_default_image_file" id="og_default_image_file" accept="image/*"
                     class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white file:mr-3 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 dark:file:bg-indigo-500/15 file:text-indigo-700 dark:file:text-indigo-300 hover:file:bg-indigo-100 dark:hover:file:bg-indigo-500/25 cursor-pointer">
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Upload an image (1200×630px recommended) shown when sharing pages without a specific image.</p>

              <div class="mt-3">
                <label for="og_default_image_url" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">
                  — or enter URL —
                </label>
                <input type="text" name="og_default_image" id="og_default_image_url"
                       value="{{ $settings['og_default_image'] ?? '' }}"
                       placeholder="https://yourdomain.com/images/og-banner.jpg"
                       class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Direct URL to your Open Graph image. Upload above will override this.</p>
              </div>

              @if($settings['og_default_image'] ?? '')
                <div class="mt-3 p-2 rounded-lg inline-block bg-slate-50 dark:bg-slate-800/40 border border-slate-200 dark:border-white/10">
                  <div class="text-xs text-slate-500 dark:text-slate-400">Current image:</div>
                  <img src="{{ filter_var($settings['og_default_image'], FILTER_VALIDATE_URL) ? $settings['og_default_image'] : asset('storage/' . $settings['og_default_image']) }}"
                       alt="OG Image" class="max-w-[200px] max-h-[100px] rounded-md mt-1"
                       onerror="this.style.display='none'">
                </div>
              @endif
            </div>

            {{-- Site Description --}}
            <div>
              <label for="og_site_description" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Site Description</label>
              <textarea name="og_site_description" id="og_site_description" rows="3" maxlength="300"
                        placeholder="Professional photo gallery and event photography service..."
                        class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition resize-none">{{ $settings['og_site_description'] ?? '' }}</textarea>
              <div class="mt-1.5 flex items-start justify-between gap-3">
                <p class="text-xs text-slate-500 dark:text-slate-400">Used as the default <code class="text-slate-700 dark:text-slate-200">og:description</code> meta tag. Max 300 characters.</p>
                <span class="text-xs font-mono text-slate-500 dark:text-slate-400 shrink-0" id="descCharCount">0 / 300</span>
              </div>
            </div>
          </div>

          {{-- RIGHT — Preview + FB app ID + Twitter --}}
          <div class="space-y-5">
            {{-- Preview --}}
            <div>
              <label class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Share Preview</label>
              <div class="og-preview-card">
                <div class="og-preview-img" id="ogPreviewImg">
                  @if($settings['og_default_image'] ?? '')
                    <img src="{{ filter_var($settings['og_default_image'], FILTER_VALIDATE_URL) ? $settings['og_default_image'] : asset('storage/' . $settings['og_default_image']) }}"
                         alt="Preview" style="width:100%;height:100%;object-fit:cover;"
                         onerror="this.parentElement.innerHTML='<i class=\'bi bi-image\'></i>'">
                  @else
                    <i class="bi bi-image"></i>
                  @endif
                </div>
                <div class="og-preview-body">
                  <div class="og-preview-domain">{{ request()->getHost() }}</div>
                  <div class="og-preview-title">{{ $siteName ?? config('app.name') }}</div>
                  <div class="og-preview-desc" id="ogPreviewDesc">
                    {{ ($settings['og_site_description'] ?? '') ?: 'Your site description will appear here...' }}
                  </div>
                </div>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">This is how your site will look when shared on Facebook, Twitter, LINE, etc.</p>
            </div>

            {{-- Facebook App ID --}}
            <div>
              <label for="og_fb_app_id" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Facebook App ID</label>
              <input type="text" name="og_fb_app_id" id="og_fb_app_id"
                     value="{{ $settings['og_fb_app_id'] ?? '' }}"
                     placeholder="123456789012345"
                     class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5 leading-relaxed">
                Optional. Enables Facebook Insights for shared links. Get one from
                <a href="https://developers.facebook.com/" target="_blank" rel="noopener" class="text-sky-600 dark:text-sky-400 hover:underline">Facebook Developers</a>.
              </p>
            </div>

            {{-- Twitter Card Type --}}
            <div>
              <label for="og_twitter_card_type" class="block text-xs font-semibold text-slate-600 dark:text-slate-300 mb-1.5">Twitter/X Card Type</label>
              <select name="og_twitter_card_type" id="og_twitter_card_type"
                      class="w-full px-3 py-2 text-sm rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-white/10 text-slate-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                <option value="summary" {{ ($settings['og_twitter_card_type'] ?? 'summary') === 'summary' ? 'selected' : '' }}>
                  summary — small square thumbnail
                </option>
                <option value="summary_large_image" {{ ($settings['og_twitter_card_type'] ?? '') === 'summary_large_image' ? 'selected' : '' }}>
                  summary_large_image — large banner (recommended)
                </option>
              </select>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1.5">Controls how your links appear in Twitter/X feeds. Large image is best for photo galleries.</p>
            </div>
          </div>

        </div>
      </div>
    </div>

    {{-- ═══════════════════ Save Button ═══════════════════ --}}
    <div class="flex justify-end">
      <button type="submit"
              class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold text-white bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 shadow-lg shadow-indigo-500/20 transition">
        <i class="bi bi-save"></i>
        <span>Save Analytics Settings</span>
      </button>
    </div>

  </form>

  {{-- ═══════════════════ Quick Setup Guide ═══════════════════ --}}
  @if(!($settings['ga4_measurement_id'] ?? '') && !($settings['fb_pixel_id'] ?? ''))
  <div class="mt-6 bg-indigo-50/50 dark:bg-indigo-500/5 border-l-4 border-indigo-500 dark:border-indigo-400 rounded-r-2xl shadow-sm">
    <div class="p-6">
      <h3 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2 mb-4">
        <i class="bi bi-info-circle-fill text-indigo-600 dark:text-indigo-400"></i>
        Setting Up Analytics
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
          <div class="font-semibold text-sm text-slate-900 dark:text-white mb-2 flex items-center gap-1.5">
            <i class="bi bi-google" style="color:#4285f4;"></i>
            Google Analytics (GA4)
          </div>
          <ol class="space-y-1.5 text-sm text-slate-600 dark:text-slate-400 list-decimal list-outside pl-5">
            <li>Go to <a href="https://analytics.google.com/" target="_blank" rel="noopener" class="text-sky-600 dark:text-sky-400 hover:underline">analytics.google.com</a></li>
            <li>Create a new GA4 property</li>
            <li>Add a Web data stream for your domain</li>
            <li>Copy the Measurement ID (starts with <code class="text-slate-700 dark:text-slate-200">G-</code>)</li>
            <li>Paste it above and enable tracking</li>
          </ol>
        </div>
        <div>
          <div class="font-semibold text-sm text-slate-900 dark:text-white mb-2 flex items-center gap-1.5">
            <i class="bi bi-facebook" style="color:#1877f2;"></i>
            Facebook Pixel
          </div>
          <ol class="space-y-1.5 text-sm text-slate-600 dark:text-slate-400 list-decimal list-outside pl-5">
            <li>Go to <a href="https://business.facebook.com/events_manager" target="_blank" rel="noopener" class="text-sky-600 dark:text-sky-400 hover:underline">Meta Events Manager</a></li>
            <li>Click <strong>Connect Data Sources</strong> &rarr; Web</li>
            <li>Name your Pixel and enter your website URL</li>
            <li>Copy the Pixel ID (numeric string)</li>
            <li>Paste it above and enable the pixel</li>
          </ol>
        </div>
      </div>
    </div>
  </div>
  @endif

</div>
@endsection

@push('scripts')
<script>
(function() {
  // Character count for description
  var descInput = document.getElementById('og_site_description');
  var descCount = document.getElementById('descCharCount');
  if (descInput && descCount) {
    function updateCount() {
      descCount.textContent = descInput.value.length + ' / 300';
    }
    descInput.addEventListener('input', updateCount);
    updateCount();
  }

  // Live preview for OG description
  if (descInput) {
    var previewDesc = document.getElementById('ogPreviewDesc');
    descInput.addEventListener('input', function() {
      previewDesc.textContent = this.value || 'Your site description will appear here...';
    });
  }

  // Preview uploaded image
  var fileInput = document.getElementById('og_default_image_file');
  var previewImg = document.getElementById('ogPreviewImg');
  if (fileInput && previewImg) {
    fileInput.addEventListener('change', function() {
      if (this.files && this.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
          previewImg.innerHTML = '<img src="' + e.target.result + '" alt="Preview" style="width:100%;height:100%;object-fit:cover;">';
        };
        reader.readAsDataURL(this.files[0]);
      }
    });
  }
})();

// Toggle GA4 fields
function toggleGa4Fields(show) {
  var el = document.getElementById('ga4Fields');
  if (!el) return;
  if (show) {
    el.classList.remove('hidden');
  } else {
    el.classList.add('hidden');
  }
}

// Toggle Facebook Pixel fields
function togglePixelFields(show) {
  var el = document.getElementById('pixelFields');
  if (!el) return;
  if (show) {
    el.classList.remove('hidden');
  } else {
    el.classList.add('hidden');
  }
}
</script>
@endpush
