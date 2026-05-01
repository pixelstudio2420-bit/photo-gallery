@extends('layouts.app')

@section('title', 'ค้นหาช่างภาพมืออาชีพ — รวมทุกจังหวัดทั่วไทย')
@section('meta_description', 'ค้นหาช่างภาพมืออาชีพในประเทศไทย กว่า ' . number_format($totalCount ?? 0) . ' คน — แต่งงาน · พรีเวดดิ้ง · รับปริญญา · งานวิ่ง · คอนเสิร์ต · อีเวนต์บริษัท')

@section('content-full')

{{-- ╔══════════════════════════════════════════════════════════════════╗
     ║  HERO with sticky search                                         ║
     ╚══════════════════════════════════════════════════════════════════╝ --}}
<section class="relative overflow-hidden bg-gradient-to-br from-indigo-600 via-violet-600 to-pink-500 text-white">
    <div class="absolute -top-32 -right-32 w-[28rem] h-[28rem] bg-white/10 rounded-full blur-3xl"></div>
    <div class="absolute -bottom-32 -left-32 w-[32rem] h-[32rem] bg-white/10 rounded-full blur-3xl"></div>

    <div class="relative max-w-6xl mx-auto px-4 py-12 md:py-20">
        <div class="text-center mb-7">
            <div class="inline-flex items-center gap-2 px-3 py-1.5 bg-white/15 backdrop-blur-sm rounded-full text-xs font-bold mb-4">
                <i class="bi bi-camera-fill"></i>
                <span>ช่างภาพมืออาชีพ</span>
            </div>
            <h1 class="text-3xl md:text-5xl font-extrabold tracking-tight mb-3 leading-tight">
                ค้นหาช่างภาพ <span class="text-yellow-200">{{ number_format($totalCount ?? 0) }}+ คน</span><br class="hidden md:block">
                ทั่วประเทศไทย
            </h1>
            <p class="text-base md:text-lg text-white/90 max-w-2xl mx-auto">
                เลือกประเภทงาน · ทุกจังหวัดทั่วไทย · จองช่างภาพมืออาชีพได้ทันทีออนไลน์
            </p>
        </div>

        {{-- Search form --}}
        <form method="GET" class="max-w-3xl mx-auto">
            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl p-2 flex flex-col md:flex-row gap-2">
                <div class="relative flex-1">
                    <i class="bi bi-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-400"></i>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="ค้นหาด้วยชื่อ / ความเชี่ยวชาญ / สไตล์"
                           class="w-full h-12 md:h-14 pl-11 pr-4 rounded-xl border-0 bg-slate-50 dark:bg-slate-800 text-sm md:text-base text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
                </div>
                <div class="relative md:w-56">
                    <i class="bi bi-geo-alt absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                    <select name="province"
                            class="appearance-none w-full h-12 md:h-14 pl-11 pr-9 rounded-xl border-0 bg-slate-50 dark:bg-slate-800 text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500/30">
                        <option value="">ทุกจังหวัด</option>
                        @foreach($provinces as $p)
                            <option value="{{ $p->id }}" @selected(request('province')==$p->id)>{{ $p->name_th }}</option>
                        @endforeach
                    </select>
                    <i class="bi bi-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none"></i>
                </div>
                <button class="h-12 md:h-14 px-6 md:px-8 rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-500 hover:to-violet-500 text-white font-bold transition active:scale-95">
                    <i class="bi bi-search md:hidden"></i>
                    <span class="hidden md:inline">ค้นหา</span>
                </button>
            </div>

            {{-- Persistent state for chip filters --}}
            <input type="hidden" name="specialty"  value="{{ request('specialty') }}">
            <input type="hidden" name="accepting"  value="{{ request('accepting') }}">
            <input type="hidden" name="min_years"  value="{{ request('min_years') }}">
            <input type="hidden" name="sort"       value="{{ request('sort', 'popular') }}">
        </form>
    </div>
</section>

{{-- ╔══════════════════════════════════════════════════════════════════╗
     ║  Specialty + filter chips                                        ║
     ╚══════════════════════════════════════════════════════════════════╝ --}}
<div class="max-w-7xl mx-auto px-4 mt-6">
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider mr-2">
            <i class="bi bi-tags-fill"></i> ประเภทงาน
        </span>
        @php
          $currentSpec = request('specialty');
          $allParams = request()->except(['specialty', 'page']);
        @endphp
        <a href="{{ route('photographers.index', $allParams) }}"
           class="px-3 py-1.5 rounded-full text-xs font-semibold transition
                  {{ !$currentSpec ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200' }}">
            ทั้งหมด
        </a>
        @foreach($topSpecialties ?? [] as $tag)
            <a href="{{ route('photographers.index', array_merge($allParams, ['specialty' => $tag])) }}"
               class="px-3 py-1.5 rounded-full text-xs font-semibold transition
                      {{ $currentSpec === $tag ? 'bg-indigo-600 text-white' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-200' }}">
                {{ $tag }}
            </a>
        @endforeach
    </div>

    {{-- Toggle filters + sort --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5 pb-4 border-b border-slate-200 dark:border-white/[0.06]">
        <div class="flex flex-wrap items-center gap-2">
            @php
              $accepting = request('accepting');
              $minYears  = request('min_years');
            @endphp
            <a href="{{ route('photographers.index', array_merge(request()->except(['accepting', 'page']), $accepting ? [] : ['accepting' => 1])) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold transition
                      {{ $accepting ? 'bg-emerald-500 text-white' : 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 hover:bg-emerald-100' }}">
                <i class="bi {{ $accepting ? 'bi-check-circle-fill' : 'bi-circle' }}"></i>
                เฉพาะที่รับงาน
            </a>
            @foreach([['1+', 1], ['3+', 3], ['5+', 5], ['10+', 10]] as [$label, $val])
                <a href="{{ route('photographers.index', array_merge(request()->except(['min_years', 'page']), (int) $minYears === $val ? [] : ['min_years' => $val])) }}"
                   class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold transition
                          {{ (int) $minYears === $val ? 'bg-amber-500 text-white' : 'bg-amber-50 dark:bg-amber-500/10 text-amber-700 dark:text-amber-400 hover:bg-amber-100' }}">
                    <i class="bi bi-clock-history"></i>{{ $label }} ปี
                </a>
            @endforeach
            @if(request('search') || request('province') || request('specialty') || $accepting || $minYears)
                <a href="{{ route('photographers.index') }}"
                   class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-semibold bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400 hover:bg-rose-100">
                    <i class="bi bi-x-lg"></i> ล้างทั้งหมด
                </a>
            @endif
        </div>

        {{-- Sort dropdown --}}
        <div class="flex items-center gap-2">
            <span class="text-xs text-slate-500 hidden sm:inline">เรียงตาม:</span>
            <select onchange="window.location.href = this.value"
                    class="text-xs font-semibold rounded-full border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 px-3 py-1.5 cursor-pointer">
                @foreach([['popular','🔥 ขายดีที่สุด'],['newest','✨ ใหม่ล่าสุด'],['experience','⏳ ประสบการณ์']] as [$key, $label])
                    <option value="{{ route('photographers.index', array_merge(request()->except(['sort', 'page']), ['sort' => $key])) }}"
                            {{ request('sort', 'popular') === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Result counter --}}
    <div class="mb-5 text-sm text-slate-600 dark:text-slate-400">
        พบ <span class="font-extrabold text-slate-900 dark:text-white">{{ number_format($totalCount ?? 0) }}</span> ช่างภาพ
        @if(request('province'))
            @php $pName = collect($provinces)->firstWhere('id', (int) request('province'))?->name_th; @endphp
            @if($pName) ใน <span class="font-semibold">{{ $pName }}</span> @endif
        @endif
        @if(request('specialty'))
            ที่เชี่ยวชาญ <span class="font-semibold">{{ request('specialty') }}</span>
        @endif
    </div>
</div>

<div class="max-w-7xl mx-auto p-4 md:p-6 pt-0">

    {{-- Photographer grid --}}
    @if($rows->count() === 0)
        <div class="bg-white dark:bg-slate-800 rounded-2xl p-12 text-center text-slate-500">
            <i class="bi bi-camera text-5xl mb-3 block opacity-50"></i>
            ไม่พบช่างภาพตรงเงื่อนไขที่ค้นหา
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($rows as $row)
                <a href="{{ $row->slug ? route('photographers.show.slug', $row->slug) : route('photographers.show', $row->user_id) }}"
                   class="block bg-white dark:bg-slate-800 rounded-2xl overflow-hidden shadow-sm hover:shadow-lg transition group">
                    {{-- Avatar header --}}
                    @php
                        // Resolve avatar key into a real R2/S3 URL via the
                        // media service rather than Storage::disk()->url(),
                        // which on this stack returns a bare object key
                        // ("system/avatars/...") that 404s when emitted.
                        $avatarUrl = null;
                        if ($row->avatar) {
                            if (preg_match('#^(?:https?:)?//#i', $row->avatar)) {
                                $avatarUrl = $row->avatar;
                            } else {
                                try {
                                    $avatarUrl = (string) app(\App\Services\Media\R2MediaService::class)->url($row->avatar);
                                    if (!preg_match('#^(?:https?:)?//#i', $avatarUrl)) {
                                        $avatarUrl = '/storage/' . ltrim($row->avatar, '/');
                                    }
                                } catch (\Throwable) {
                                    $avatarUrl = '/storage/' . ltrim($row->avatar, '/');
                                }
                            }
                        }
                    @endphp
                    <div class="relative h-32 bg-gradient-to-br from-indigo-500 via-violet-500 to-pink-500 flex items-center justify-center">
                        @if($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="" loading="lazy"
                                 class="absolute inset-0 w-full h-full object-cover opacity-40">
                        @endif
                        @if($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="{{ $row->display_name }}"
                                 class="absolute -bottom-7 left-5 w-14 h-14 rounded-2xl object-cover shadow-md ring-4 ring-white dark:ring-slate-800">
                        @else
                            <div class="absolute -bottom-7 left-5 w-14 h-14 rounded-2xl bg-white shadow-md flex items-center justify-center text-2xl font-extrabold text-indigo-600 ring-4 ring-white dark:ring-slate-800">
                                {{ mb_strtoupper(mb_substr($row->display_name ?? $row->first_name ?? 'P', 0, 1, 'UTF-8'), 'UTF-8') }}
                            </div>
                        @endif
                        @if($row->tier === 'pro')
                            <span class="absolute top-3 right-3 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold text-white bg-amber-500/90">
                                ⭐ PRO
                            </span>
                        @endif
                    </div>
                    <div class="px-5 pt-9 pb-5">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="text-lg font-extrabold text-slate-900 dark:text-white group-hover:text-indigo-600 transition">
                                {{ $row->display_name ?? trim($row->first_name . ' ' . $row->last_name) }}
                            </h3>
                            @if($row->accepts_bookings ?? false)
                                <span class="inline-flex items-center gap-1 text-[9px] font-bold px-1.5 py-0.5 bg-emerald-100 text-emerald-700 rounded-full shrink-0 mt-0.5">
                                    <i class="bi bi-check-circle-fill"></i> รับงาน
                                </span>
                            @endif
                        </div>
                        @if($row->headline)
                            <div class="text-xs text-indigo-600 dark:text-indigo-400 font-semibold mt-0.5">
                                {{ $row->headline }}
                            </div>
                        @elseif($row->bio)
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1 line-clamp-2">
                                {{ $row->bio }}
                            </p>
                        @endif

                        {{-- Stat row: events + experience + province --}}
                        <div class="flex flex-wrap items-center gap-3 mt-3 text-xs text-slate-500">
                            <span class="inline-flex items-center gap-1">
                                <i class="bi bi-camera text-indigo-500"></i> {{ $row->events_count }} อีเวนต์
                            </span>
                            @if($row->years_experience)
                                <span class="inline-flex items-center gap-1">
                                    <i class="bi bi-clock-history text-amber-500"></i> {{ $row->years_experience }} ปี
                                </span>
                            @endif
                            @if($row->province_name)
                                <span class="inline-flex items-center gap-1 ml-auto">
                                    <i class="bi bi-geo-alt-fill text-rose-500"></i>
                                    <span class="font-medium text-slate-600 dark:text-slate-300">{{ $row->province_name }}</span>
                                </span>
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Pagination --}}
        <div class="mt-8">
            {{ $rows->links() }}
        </div>
    @endif
</div>
@endsection
