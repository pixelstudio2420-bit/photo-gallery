@extends('layouts.admin')

@section('title', 'SEO Settings')

@section('content')
<div class="flex items-center justify-between mb-4">
  <div>
    <h4 class="font-bold mb-0"><i class="bi bi-search mr-2" style="color:#6366f1;"></i>SEO Settings</h4>
    <p class="text-gray-500 small mb-0 mt-1">Manage search engine optimisation, social sharing, and analytics</p>
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

{{-- Tab Navigation --}}
<div class="card border-0 shadow-sm mb-0" style="border-radius:14px;" x-data="{ activeTab: localStorage.getItem('seo-settings-tab') || 'general' }" x-init="$watch('activeTab', val => localStorage.setItem('seo-settings-tab', val))">
  <div class="px-5 py-4 border-b border-gray-100 bg-transparent border-b" style="border-radius:14px 14px 0 0; padding: 0 1.5rem;">
    <ul class="flex border-b border-gray-200 border-0" id="seoTabs" role="tablist" style="gap:0.25rem;">
      <li role="presentation">
        <button class="font-medium px-4 py-3"
            :class="activeTab === 'general' ? 'border-b-2 border-indigo-500 text-indigo-600' : 'text-gray-500 hover:text-gray-700'"
            @click="activeTab = 'general'"
            type="button" role="tab" style="border:none;border-b:3px solid transparent;background:transparent;border-radius:0;">
          <i class="bi bi-globe mr-1"></i>General
        </button>
      </li>
      <li role="presentation">
        <button class="font-medium px-4 py-3"
            :class="activeTab === 'social' ? 'border-b-2 border-indigo-500 text-indigo-600' : 'text-gray-500 hover:text-gray-700'"
            @click="activeTab = 'social'"
            type="button" role="tab" style="border:none;border-b:3px solid transparent;background:transparent;border-radius:0;">
          <i class="bi bi-share mr-1"></i>Social &amp; OG
        </button>
      </li>
      <li role="presentation">
        <button class="font-medium px-4 py-3"
            :class="activeTab === 'analytics' ? 'border-b-2 border-indigo-500 text-indigo-600' : 'text-gray-500 hover:text-gray-700'"
            @click="activeTab = 'analytics'"
            type="button" role="tab" style="border:none;border-b:3px solid transparent;background:transparent;border-radius:0;">
          <i class="bi bi-bar-chart mr-1"></i>Analytics
        </button>
      </li>
      <li role="presentation">
        <button class="font-medium px-4 py-3"
            :class="activeTab === 'advanced' ? 'border-b-2 border-indigo-500 text-indigo-600' : 'text-gray-500 hover:text-gray-700'"
            @click="activeTab = 'advanced'"
            type="button" role="tab" style="border:none;border-b:3px solid transparent;background:transparent;border-radius:0;">
          <i class="bi bi-sliders mr-1"></i>Advanced
        </button>
      </li>
    </ul>
  </div>

  <form method="POST" action="{{ route('admin.settings.seo.update') }}" enctype="multipart/form-data">
    @csrf
    <div>

      {{-- ===================== TAB 1: GENERAL ===================== --}}
      <div x-show="activeTab === 'general'" x-cloak id="pane-general" role="tabpanel">
        <div class="p-5 p-4">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="">
              <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium">Site Name</label>
                <input type="text" name="seo_site_name" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;"
                  value="{{ $settings['seo_site_name'] ?? '' }}"
                  placeholder="{{ config('app.name') }}">
                <div class="text-gray-500 text-xs mt-1">Used in page titles and Open Graph tags.</div>
              </div>

              <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium">Site Tagline</label>
                <input type="text" name="seo_site_tagline" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;"
                  value="{{ $settings['seo_site_tagline'] ?? '' }}"
                  placeholder="Professional photography for every moment">
                <div class="text-gray-500 text-xs mt-1">Shown on the homepage title when no page title is set.</div>
              </div>

              <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium">Site Description</label>
                <textarea name="seo_site_description" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" rows="3" style="border-radius:10px;"
                  placeholder="A short description of your site (up to 160 characters)…">{{ $settings['seo_site_description'] ?? '' }}</textarea>
                <div class="text-gray-500 text-xs mt-1">Default meta description when no page-specific description is set.</div>
              </div>

              <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium">Title Separator</label>
                <input type="text" name="seo_title_separator" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;max-width:120px;"
                  value="{{ $settings['seo_title_separator'] ?? '—' }}"
                  placeholder="—">
                <div class="text-gray-500 text-xs mt-1">Character placed between page title and site name (e.g. — or |).</div>
              </div>
            </div>

            <div class="">
              <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium">Default Keywords</label>
                <textarea name="seo_default_keywords" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" rows="3" style="border-radius:10px;"
                  placeholder="photography, events, photos, gallery">{{ $settings['seo_default_keywords'] ?? '' }}</textarea>
                <div class="text-gray-500 text-xs mt-1">Comma-separated keywords. Used as fallback when page has none.</div>
              </div>

              <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium">Author</label>
                <input type="text" name="seo_author" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;"
                  value="{{ $settings['seo_author'] ?? '' }}"
                  placeholder="Photo Gallery Team">
              </div>

              <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium">Default Robots</label>
                <select name="seo_default_robots" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500" style="border-radius:10px;">
                  @php
                    $robotsOptions = [
                      'index, follow'   => 'index, follow (default — allow all)',
                      'noindex, follow'  => 'noindex, follow',
                      'index, nofollow'  => 'index, nofollow',
                      'noindex, nofollow' => 'noindex, nofollow (block all)',
                    ];
                    $currentRobots = $settings['seo_default_robots'] ?? 'index, follow';
                  @endphp
                  @foreach($robotsOptions as $value => $label)
                    <option value="{{ $value }}" {{ $currentRobots === $value ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
                <div class="text-gray-500 text-xs mt-1">Controls how search engines index your site by default.</div>
              </div>

              <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium">Theme Color</label>
                <div class="flex items-center gap-2">
                  <input type="color" name="seo_theme_color" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500-color" style="border-radius:10px;width:60px;height:40px;"
                    value="{{ $settings['seo_theme_color'] ?? '#6366f1' }}">
                  <input type="text" id="theme_color_text" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;max-width:130px;"
                    value="{{ $settings['seo_theme_color'] ?? '#6366f1' }}"
                    placeholder="#6366f1" readonly>
                </div>
                <div class="text-gray-500 text-xs mt-1">Browser toolbar / PWA theme color.</div>
              </div>
            </div>
          </div>
        </div>
        <div class="px-5 py-3 border-t border-gray-100 bg-transparent border-t flex justify-end p-3" style="border-radius:0 0 14px 14px;">
          <button type="submit" class="btn font-semibold px-4" style="background:#6366f1;color:#fff;border-radius:10px;">
            <i class="bi bi-check-lg mr-1"></i>Save General Settings
          </button>
        </div>
      </div>

      {{-- ===================== TAB 2: SOCIAL & OG ===================== --}}
      <div x-show="activeTab === 'social'" x-cloak id="pane-social" role="tabpanel">
        <div class="p-5 p-4">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="">
              <h6 class="font-semibold mb-3 text-gray-500 text-uppercase" style="font-size:0.75rem;letter-spacing:0.08em;">Open Graph</h6>

              <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium">Default OG Image</label>
                @if(!empty($settings['seo_og_default_image']))
                  @php
                    // Same R2 → public-disk → asset() fallback as the
                    // favicon preview below. R2-stored OG images would
                    // 404 on the plain `asset('storage/'.$key)` path.
                    $ogKey = $settings['seo_og_default_image'];
                    $ogPreviewUrl = '';
                    try {
                        $ogPreviewUrl = (string) app(\App\Services\Media\R2MediaService::class)->url($ogKey);
                    } catch (\Throwable) {
                        try {
                            $ogPreviewUrl = (string) \Illuminate\Support\Facades\Storage::disk('public')->url($ogKey);
                        } catch (\Throwable) {
                            $ogPreviewUrl = '';
                        }
                    }
                    if (!preg_match('#^(?:https?:)?/#i', $ogPreviewUrl)) {
                        $ogPreviewUrl = asset('storage/' . $ogKey);
                    }
                  @endphp
                  <div class="mb-2">
                    <img src="{{ $ogPreviewUrl }}"
                       alt="OG Image Preview" class="border border-gray-200 rounded-lg p-1"
                       style="max-height:120px;border-radius:10px;background:#f8fafc;"
                       onerror="this.style.opacity='0.3';this.alt='⚠️ ไฟล์เก่าไม่พบ — อัปโหลดใหม่';">
                  </div>
                @endif
                <input type="file" name="seo_og_default_image" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;"
                  accept="image/jpeg,image/png,image/webp">
                <div class="text-gray-500 text-xs mt-1">Recommended: 1200×630px. Used when sharing on Facebook, LinkedIn, etc.</div>
              </div>

              <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium">Twitter Card Type</label>
                <select name="seo_twitter_card_type" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500" style="border-radius:10px;">
                  @php
                    $twitterCardOptions = [
                      'summary_large_image' => 'summary_large_image (large image card)',
                      'summary'       => 'summary (small image card)',
                      'app'         => 'app',
                      'player'       => 'player',
                    ];
                    $currentCard = $settings['seo_twitter_card_type'] ?? 'summary_large_image';
                  @endphp
                  @foreach($twitterCardOptions as $value => $label)
                    <option value="{{ $value }}" {{ $currentCard === $value ? 'selected' : '' }}>{{ $label }}</option>
                  @endforeach
                </select>
              </div>

              <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium">Twitter Site Handle</label>
                <div class="flex" style="border-radius:10px;">
                  <span class="px-3 py-2.5 bg-gray-50 border border-gray-300 text-gray-500" style="border-radius:10px 0 0 10px;">@</span>
                  <input type="text" name="seo_twitter_site" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:0 10px 10px 0;"
                    value="{{ ltrim($settings['seo_twitter_site'] ?? '', '@') }}"
                    placeholder="yoursitehandle">
                </div>
                <div class="text-gray-500 text-xs mt-1">Your site's Twitter / X username (without the @).</div>
              </div>
            </div>

            <div class="">
              <h6 class="font-semibold mb-3 text-gray-500 text-uppercase" style="font-size:0.75rem;letter-spacing:0.08em;">Social Links</h6>

              <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium"><i class="bi bi-facebook mr-1" style="color:#1877f2;"></i>Facebook URL</label>
                <input type="url" name="seo_social_facebook" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;"
                  value="{{ $settings['seo_social_facebook'] ?? '' }}"
                  placeholder="https://facebook.com/yourpage">
              </div>

              <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium"><i class="bi bi-instagram mr-1" style="color:#e1306c;"></i>Instagram URL</label>
                <input type="url" name="seo_social_instagram" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;"
                  value="{{ $settings['seo_social_instagram'] ?? '' }}"
                  placeholder="https://instagram.com/yourprofile">
              </div>

              <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium"><i class="bi bi-twitter-x mr-1"></i>Twitter / X URL</label>
                <input type="url" name="seo_social_twitter" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;"
                  value="{{ $settings['seo_social_twitter'] ?? '' }}"
                  placeholder="https://x.com/yourprofile">
              </div>

              <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium"><i class="bi bi-youtube mr-1" style="color:#ff0000;"></i>YouTube URL</label>
                <input type="url" name="seo_social_youtube" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;"
                  value="{{ $settings['seo_social_youtube'] ?? '' }}"
                  placeholder="https://youtube.com/@yourchannel">
              </div>

              <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium"><i class="bi bi-chat-dots mr-1" style="color:#06c755;"></i>LINE URL</label>
                <input type="url" name="seo_social_line" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;"
                  value="{{ $settings['seo_social_line'] ?? '' }}"
                  placeholder="https://line.me/ti/p/~yourlineid">
              </div>
            </div>
          </div>
        </div>
        <div class="px-5 py-3 border-t border-gray-100 bg-transparent border-t flex justify-end p-3" style="border-radius:0 0 14px 14px;">
          <button type="submit" class="btn font-semibold px-4" style="background:#6366f1;color:#fff;border-radius:10px;">
            <i class="bi bi-check-lg mr-1"></i>Save Social & OG Settings
          </button>
        </div>
      </div>

      {{-- ===================== TAB 3: ANALYTICS ===================== --}}
      <div x-show="activeTab === 'analytics'" x-cloak id="pane-analytics" role="tabpanel">
        <div class="p-5 p-4">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="">
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium"><i class="bi bi-google mr-1"></i>Google Analytics ID</label>
                <input type="text" name="seo_google_analytics" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;"
                  value="{{ $settings['seo_google_analytics'] ?? '' }}"
                  placeholder="G-XXXXXXXXXX">
                <div class="text-gray-500 text-xs mt-1">Your GA4 Measurement ID (starts with G-). Leave blank to disable.</div>
              </div>

              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium"><i class="bi bi-patch-check mr-1"></i>Google Site Verification</label>
                <input type="text" name="seo_google_verification" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;"
                  value="{{ $settings['seo_google_verification'] ?? '' }}"
                  placeholder="Paste your verification token here">
                <div class="text-gray-500 text-xs mt-1">Content value from the Google Search Console verification meta tag.</div>
              </div>

              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium"><i class="bi bi-facebook mr-1" style="color:#1877f2;"></i>Facebook Domain Verification</label>
                <input type="text" name="seo_facebook_verification" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;"
                  value="{{ $settings['seo_facebook_verification'] ?? '' }}"
                  placeholder="Paste your Facebook domain verification token">
                <div class="text-gray-500 text-xs mt-1">Token from Facebook Business Manager domain verification.</div>
              </div>
            </div>

            <div class="">
              <div class="p-4 rounded-xl border" style="background:rgba(99,102,241,0.04);border-color:#e8eaf6 !important;">
                <h6 class="font-semibold mb-2"><i class="bi bi-info-circle mr-1" style="color:#6366f1;"></i>How to get your IDs</h6>
                <ul class="small text-gray-500 mb-0 ps-3" style="line-height:1.8;">
                  <li><strong>Google Analytics:</strong> Go to GA4 → Admin → Data Streams → Measurement ID</li>
                  <li><strong>Google Verification:</strong> Google Search Console → Settings → Ownership Verification → HTML tag → copy only the content attribute value</li>
                  <li><strong>Facebook Verification:</strong> Facebook Business Manager → Brand Safety → Domains → Verify</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
        <div class="px-5 py-3 border-t border-gray-100 bg-transparent border-t flex justify-end p-3" style="border-radius:0 0 14px 14px;">
          <button type="submit" class="btn font-semibold px-4" style="background:#6366f1;color:#fff;border-radius:10px;">
            <i class="bi bi-check-lg mr-1"></i>Save Analytics Settings
          </button>
        </div>
      </div>

      {{-- ===================== TAB 4: ADVANCED ===================== --}}
      <div x-show="activeTab === 'advanced'" x-cloak id="pane-advanced" role="tabpanel">
        <div class="p-5 p-4">
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="">
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium">Custom robots.txt</label>
                <textarea name="seo_robots_txt" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-monospace" rows="12" style="border-radius:10px;font-size:0.85rem;"
                  placeholder="User-agent: *&#10;Allow: /&#10;Disallow: /admin/&#10;&#10;Sitemap: {{ url('/sitemap.xml') }}">{{ $settings['seo_robots_txt'] ?? '' }}</textarea>
                <div class="text-gray-500 text-xs mt-1">
                  Leave blank to use the default robots.txt. The current default blocks
                  <code>/admin/</code>, <code>/photographer/</code>, and <code>/api/</code>.
                  <a href="{{ url('/robots.txt') }}" target="_blank" class="ml-1">Preview</a>
                </div>
              </div>

              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium">Favicon</label>
                @if(!empty($settings['seo_favicon']))
                  @php
                    // Resolve the stored key through R2 first (where the
                    // file actually lives once R2 is configured), then
                    // fall back to the local public disk. The plain
                    // `asset('storage/'.$key)` shortcut only worked for
                    // local-disk uploads, so on an R2-only deploy the
                    // preview was 404'ing here even though the upload
                    // itself succeeded.
                    $faviconKey = $settings['seo_favicon'];
                    $faviconPreviewUrl = '';
                    try {
                        $faviconPreviewUrl = (string) app(\App\Services\Media\R2MediaService::class)->url($faviconKey);
                    } catch (\Throwable) {
                        try {
                            $faviconPreviewUrl = (string) \Illuminate\Support\Facades\Storage::disk('public')->url($faviconKey);
                        } catch (\Throwable) {
                            $faviconPreviewUrl = '';
                        }
                    }
                    // Reject relative-looking URLs that would be resolved
                    // against /admin/settings/seo and 404 again.
                    if (!preg_match('#^(?:https?:)?/#i', $faviconPreviewUrl)) {
                        $faviconPreviewUrl = asset('storage/' . $faviconKey);
                    }
                  @endphp
                  <div class="mb-2 flex items-center gap-2">
                    <img src="{{ $faviconPreviewUrl }}"
                       alt="Favicon Preview"
                       style="width:32px;height:32px;object-fit:contain;border:1px solid #e2e8f0;border-radius:6px;padding:2px;background:#f8fafc;"
                       onerror="this.style.opacity='0.3';this.alt='⚠️ ไฟล์เก่าไม่พบ — อัปโหลดใหม่';">
                    <span class="small text-gray-500">Current favicon</span>
                    <a href="{{ $faviconPreviewUrl }}" target="_blank" rel="noopener" class="text-xs text-indigo-600 hover:underline ml-auto">
                      <i class="bi bi-box-arrow-up-right"></i> เปิดในแท็บใหม่
                    </a>
                  </div>
                @endif
                <input type="file" name="seo_favicon" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" style="border-radius:10px;"
                  accept="image/x-icon,image/png,image/svg+xml,image/webp">
                <div class="text-gray-500 text-xs mt-1">Recommended: 32×32px or 64×64px PNG/ICO.</div>
              </div>
            </div>

            <div class="">
              <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1.5 font-medium">Custom Head Code</label>
                <textarea name="seo_custom_head_code" class="w-full px-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-monospace" rows="12" style="border-radius:10px;font-size:0.85rem;"
                  placeholder="&lt;!-- Custom scripts, meta tags, or verification codes --&gt;">{{ $settings['seo_custom_head_code'] ?? '' }}</textarea>
                <div class="text-gray-500 text-xs mt-1">
                  Raw HTML injected inside <code>&lt;head&gt;</code> on every public page.
                  Use for third-party scripts, custom meta tags, or JSON-LD snippets.
                  <strong class="text-yellow-600">Be careful — invalid HTML may break the page.</strong>
                </div>
              </div>

              <div class="p-3 rounded-xl border" style="background:rgba(245,158,11,0.06);border-color:#fcd34d !important;">
                <h6 class="font-semibold mb-1 small" style="color:#d97706;"><i class="bi bi-exclamation-triangle mr-1"></i>Advanced use only</h6>
                <p class="small mb-2 text-gray-500">Changes here affect every public page. Test after saving.</p>
                <a href="{{ url('/sitemap.xml') }}" target="_blank" class="text-sm px-3 py-1.5 rounded-lg mr-1" style="background:rgba(99,102,241,0.08);color:#6366f1;border-radius:8px;font-size:0.8rem;">
                  <i class="bi bi-file-code mr-1"></i>View Sitemap
                </a>
                <a href="{{ url('/robots.txt') }}" target="_blank" class="text-sm px-3 py-1.5 rounded-lg" style="background:rgba(99,102,241,0.08);color:#6366f1;border-radius:8px;font-size:0.8rem;">
                  <i class="bi bi-robot mr-1"></i>View robots.txt
                </a>
              </div>
            </div>
          </div>
        </div>
        <div class="px-5 py-3 border-t border-gray-100 bg-transparent border-t flex justify-end p-3" style="border-radius:0 0 14px 14px;">
          <button type="submit" class="btn font-semibold px-4" style="background:#6366f1;color:#fff;border-radius:10px;">
            <i class="bi bi-check-lg mr-1"></i>Save Advanced Settings
          </button>
        </div>
      </div>

    </div>
  </form>
</div>

@push('styles')
<style>
  .font-monospace { font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace !important; }
  [x-cloak] { display: none !important; }
</style>
@endpush

@push('scripts')
<script>
  // Sync colour picker with hex text display
  document.querySelector('[name="seo_theme_color"]')?.addEventListener('input', function() {
    document.getElementById('theme_color_text').value = this.value;
  });
</script>
@endpush
@endsection
