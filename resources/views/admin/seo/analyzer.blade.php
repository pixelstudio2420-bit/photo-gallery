@extends('layouts.admin')

@section('title', 'SEO Analyzer')

@section('content')
<div class="space-y-5">

  {{-- Header --}}
  <div>
    <h1 class="text-2xl font-bold text-slate-800 dark:text-gray-100">
      <i class="bi bi-graph-up-arrow text-indigo-500 mr-2"></i>SEO Analyzer
    </h1>
    <p class="text-sm text-gray-500 mt-1">วิเคราะห์ SEO ของแต่ละหน้า พร้อมคำแนะนำการปรับปรุง</p>
  </div>

  {{-- Sitemap Stats + Actions --}}
  <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
    <div class="bg-white border border-gray-100 rounded-2xl p-4">
      <div class="text-xs text-gray-500">URLs ใน Sitemap</div>
      <div class="text-2xl font-bold text-indigo-600">{{ number_format($stats['total_urls']) }}</div>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl p-4">
      <div class="text-xs text-gray-500">ขนาด Sitemap</div>
      <div class="text-2xl font-bold">{{ $stats['size_kb'] }} KB</div>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl p-4">
      <div class="text-xs text-gray-500">Cache Status</div>
      <div class="text-sm font-bold {{ $stats['cached'] ? 'text-emerald-600' : 'text-amber-600' }}">
        {{ $stats['cached'] ? '✓ Cached' : '⚠ Not cached' }}
      </div>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl p-4 space-y-2">
      <a href="{{ url('/sitemap.xml') }}" target="_blank" class="block text-center text-xs px-3 py-1.5 bg-indigo-50 text-indigo-600 rounded-lg hover:bg-indigo-100">
        <i class="bi bi-file-earmark-code"></i> ดู Sitemap
      </a>
      <form method="POST" action="{{ route('admin.settings.seo.refresh-sitemap') }}">
        @csrf
        <button type="submit" class="w-full text-xs px-3 py-1.5 bg-emerald-50 text-emerald-600 rounded-lg hover:bg-emerald-100">
          <i class="bi bi-arrow-repeat"></i> Refresh
        </button>
      </form>
    </div>
  </div>

  {{-- URL Input --}}
  <form method="GET" class="bg-white border border-gray-100 rounded-2xl p-5">
    <label class="block text-sm font-semibold text-gray-700 mb-2">URL ที่ต้องการวิเคราะห์</label>
    <div class="flex gap-2">
      <input type="url" name="url" value="{{ $url }}" placeholder="https://example.com/..." required
             class="flex-1 px-4 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-indigo-200 font-mono text-sm">
      <button type="submit" class="px-6 py-2.5 bg-gradient-to-br from-indigo-500 to-indigo-600 text-white rounded-xl font-medium hover:shadow-lg">
        <i class="bi bi-search"></i> วิเคราะห์
      </button>
    </div>

    <div class="mt-3 flex gap-2 flex-wrap">
      <span class="text-xs text-gray-500">ทดสอบเร็ว:</span>
      @foreach($suggestions as $s)
      <a href="?url={{ urlencode($s['url']) }}" class="text-xs px-2 py-1 bg-gray-100 text-gray-700 rounded hover:bg-indigo-100 hover:text-indigo-700">
        {{ $s['label'] }}
      </a>
      @endforeach
    </div>
  </form>

  {{-- Results --}}
  @if($result)
    @if(isset($result['error']))
    <div class="bg-red-50 border border-red-200 rounded-2xl p-5">
      <h3 class="font-bold text-red-800"><i class="bi bi-exclamation-circle-fill mr-1"></i>เกิดข้อผิดพลาด</h3>
      <p class="text-red-700 mt-1">{{ $result['error'] }}</p>
    </div>
    @else

    {{-- Score Header --}}
    <div class="bg-gradient-to-br from-indigo-500 to-violet-600 text-white rounded-2xl p-6">
      <div class="flex items-center justify-between gap-6 flex-wrap">
        <div>
          <div class="text-xs text-indigo-100 uppercase">SEO Score</div>
          <div class="flex items-baseline gap-3">
            <div class="text-6xl font-bold">{{ $result['score'] }}</div>
            <div class="text-sm opacity-90">/100</div>
            <div class="text-4xl font-bold {{ $result['grade'] === 'A' ? 'text-emerald-300' : ($result['grade'] === 'B' ? 'text-lime-300' : ($result['grade'] === 'C' ? 'text-yellow-300' : 'text-orange-300')) }}">{{ $result['grade'] }}</div>
          </div>
          <div class="text-sm opacity-90 mt-2 truncate max-w-2xl">{{ $url }}</div>
        </div>
        <div class="flex gap-4 flex-wrap">
          <div class="text-center">
            <div class="text-3xl font-bold text-red-300">{{ count($result['issues']) }}</div>
            <div class="text-xs text-indigo-100">Critical</div>
          </div>
          <div class="text-center">
            <div class="text-3xl font-bold text-amber-300">{{ count($result['warnings']) }}</div>
            <div class="text-xs text-indigo-100">Warnings</div>
          </div>
          <div class="text-center">
            <div class="text-3xl font-bold text-emerald-300">{{ count($result['passes']) }}</div>
            <div class="text-xs text-indigo-100">Passed</div>
          </div>
        </div>
      </div>
    </div>

    {{-- Critical Issues --}}
    @if(count($result['issues']) > 0)
    <div class="bg-white border border-red-200 rounded-2xl p-5">
      <h3 class="font-bold text-red-700 mb-3">
        <i class="bi bi-exclamation-circle-fill"></i> Critical Issues ({{ count($result['issues']) }})
      </h3>
      <div class="space-y-2">
        @foreach($result['issues'] as $issue)
        <div class="flex items-start gap-2 p-3 bg-red-50 rounded-lg">
          <i class="bi bi-x-circle-fill text-red-500 mt-0.5"></i>
          <span class="text-sm text-red-900">{{ $issue['message'] }}</span>
        </div>
        @endforeach
      </div>
    </div>
    @endif

    {{-- Warnings --}}
    @if(count($result['warnings']) > 0)
    <div class="bg-white border border-amber-200 rounded-2xl p-5">
      <h3 class="font-bold text-amber-700 mb-3">
        <i class="bi bi-exclamation-triangle-fill"></i> Warnings ({{ count($result['warnings']) }})
      </h3>
      <div class="space-y-2">
        @foreach($result['warnings'] as $warn)
        <div class="flex items-start gap-2 p-3 bg-amber-50 rounded-lg">
          <i class="bi bi-exclamation-triangle-fill text-amber-500 mt-0.5"></i>
          <span class="text-sm text-amber-900">{{ $warn['message'] }}</span>
        </div>
        @endforeach
      </div>
    </div>
    @endif

    {{-- Passes --}}
    @if(count($result['passes']) > 0)
    <div class="bg-white border border-emerald-200 rounded-2xl p-5">
      <h3 class="font-bold text-emerald-700 mb-3">
        <i class="bi bi-check-circle-fill"></i> Passed Checks ({{ count($result['passes']) }})
      </h3>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        @foreach($result['passes'] as $pass)
        <div class="flex items-start gap-2 p-2 bg-emerald-50 rounded-lg">
          <i class="bi bi-check-circle-fill text-emerald-500 mt-0.5 shrink-0"></i>
          <span class="text-sm text-emerald-900">{{ $pass['message'] }}</span>
        </div>
        @endforeach
      </div>
    </div>
    @endif

    {{-- Stats + Meta Preview --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

      {{-- Page Stats --}}
      <div class="bg-white border border-gray-100 rounded-2xl p-5">
        <h3 class="font-bold text-slate-800 mb-3">
          <i class="bi bi-bar-chart"></i> Page Stats
        </h3>
        <div class="grid grid-cols-2 gap-2 text-sm">
          <div class="p-2 bg-gray-50 rounded">
            <div class="text-xs text-gray-500">Title</div>
            <div class="font-bold">{{ $result['stats']['title_length'] }} chars</div>
          </div>
          <div class="p-2 bg-gray-50 rounded">
            <div class="text-xs text-gray-500">Description</div>
            <div class="font-bold">{{ $result['stats']['desc_length'] }} chars</div>
          </div>
          <div class="p-2 bg-gray-50 rounded">
            <div class="text-xs text-gray-500">H1 / H2 / H3</div>
            <div class="font-bold">{{ $result['stats']['h1_count'] }} / {{ $result['stats']['h2_count'] }} / {{ $result['stats']['h3_count'] }}</div>
          </div>
          <div class="p-2 bg-gray-50 rounded">
            <div class="text-xs text-gray-500">Word Count</div>
            <div class="font-bold">{{ number_format($result['stats']['word_count']) }}</div>
          </div>
          <div class="p-2 bg-gray-50 rounded">
            <div class="text-xs text-gray-500">Images (w/ alt)</div>
            <div class="font-bold">{{ $result['stats']['images_total'] - $result['stats']['images_without_alt'] }}/{{ $result['stats']['images_total'] }}</div>
          </div>
          <div class="p-2 bg-gray-50 rounded">
            <div class="text-xs text-gray-500">Links (int/ext)</div>
            <div class="font-bold">{{ $result['stats']['internal_links'] }} / {{ $result['stats']['external_links'] }}</div>
          </div>
          <div class="p-2 bg-gray-50 rounded">
            <div class="text-xs text-gray-500">Schemas</div>
            <div class="font-bold">{{ $result['stats']['schemas_count'] }}</div>
          </div>
        </div>
      </div>

      {{-- Google Preview --}}
      <div class="bg-white border border-gray-100 rounded-2xl p-5">
        <h3 class="font-bold text-slate-800 mb-3">
          <i class="bi bi-google"></i> Google Preview
        </h3>
        <div class="p-4 bg-slate-50 rounded-lg">
          <div class="text-[#1a0dab] text-lg truncate hover:underline cursor-pointer">
            {{ $result['meta']['title'] ?: 'No title' }}
          </div>
          <div class="text-[#006621] text-sm mt-1 truncate">{{ $url }}</div>
          <p class="text-sm text-[#4d5156] mt-1 line-clamp-2">
            {{ $result['meta']['description'] ?: 'No meta description' }}
          </p>
        </div>

        <h3 class="font-bold text-slate-800 mt-4 mb-2">
          <i class="bi bi-facebook"></i> Facebook Preview
        </h3>
        @if($result['meta']['og_image'])
        <img src="{{ $result['meta']['og_image'] }}" alt="OG Image" class="w-full h-40 object-cover rounded-lg bg-gray-100">
        @endif
        <div class="p-3 bg-slate-50 rounded-b-lg -mt-1">
          <div class="text-xs text-gray-500 uppercase">{{ parse_url($url, PHP_URL_HOST) }}</div>
          <div class="font-bold text-slate-800 text-sm">{{ $result['meta']['og_title'] ?: $result['meta']['title'] ?: 'No title' }}</div>
          <div class="text-xs text-gray-600">{{ \Str::limit($result['meta']['description'], 120) }}</div>
        </div>
      </div>
    </div>

    @endif
  @endif
</div>
@endsection
