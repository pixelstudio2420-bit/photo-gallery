@extends('layouts.admin')

@section('title', 'เทศกาล / Festival popups')

@section('content')
<div class="container-fluid py-4" x-data="{ openId: null }">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 mb-1">🎉 เทศกาล / Festival popups</h1>
            <p class="text-muted mb-0">
                Popup ตามเทศกาล (สงกรานต์, ลอยกระทง, ปีใหม่, ฯลฯ) — แสดงในช่วงเวลาที่กำหนด พร้อมธีมสีตามเทศกาล
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-light text-dark border">
                <i class="bi bi-info-circle"></i>
                ระบบจะ seed เทศกาลใหม่อัตโนมัติทุกครั้งที่ deploy
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Stats --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card"><div class="card-body py-3">
                <div class="text-muted small">เทศกาลทั้งหมด</div>
                <div class="h4 mb-0">{{ $stats['total'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-success"><div class="card-body py-3">
                <div class="text-muted small">เปิดใช้งาน</div>
                <div class="h4 mb-0 text-success">{{ $stats['enabled'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-danger"><div class="card-body py-3">
                <div class="text-muted small">🔥 กำลังโชว์ตอนนี้</div>
                <div class="h4 mb-0 text-danger">{{ $stats['currently_live'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-info"><div class="card-body py-3">
                <div class="text-muted small">กำลังจะมา (30 วัน)</div>
                <div class="h4 mb-0 text-info">{{ $stats['upcoming_30d'] }}</div>
            </div></div>
        </div>
    </div>

    {{-- Festival list --}}
    <div class="row g-3">
        @forelse($festivals as $f)
            @php
                $theme = \App\Services\FestivalThemeService::theme($f->theme_variant);
                $now    = now()->startOfDay();
                $popupStart = $f->starts_at?->copy()->subDays($f->popup_lead_days);
                $isLiveNow  = $f->enabled && $popupStart && $now->between($popupStart, $f->ends_at);
                $isPast     = $f->ends_at->lt($now);
                $isUpcoming = $popupStart && $popupStart->gt($now);
            @endphp
            <div class="col-12">
                <div class="card border-0 shadow-sm overflow-hidden">
                    {{-- Themed strip + status banner --}}
                    <div style="background: {{ $theme['gradient_css'] }}; height: 4px;"></div>
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                                    <span class="fs-3">{{ $f->emoji }}</span>
                                    <h5 class="mb-0">{{ $f->name }}</h5>
                                    @if($isLiveNow)
                                        <span class="badge bg-danger">
                                            <span class="d-inline-block bg-white rounded-circle me-1" style="width: 6px; height: 6px;"></span>
                                            LIVE
                                        </span>
                                    @elseif($isPast)
                                        <span class="badge bg-secondary">⌛ ผ่านมาแล้ว</span>
                                    @elseif($isUpcoming)
                                        <span class="badge bg-info">⏰ {{ now()->diffInDays($popupStart) }} วันถึงโชว์</span>
                                    @endif
                                    @if(!$f->enabled)
                                        <span class="badge bg-warning text-dark">ปิดอยู่</span>
                                    @endif
                                    <span class="badge text-bg-light border" title="ธีม">
                                        🎨 {{ $theme['label'] }}
                                    </span>
                                </div>
                                <p class="text-muted mb-2 small">{{ $f->headline }}</p>
                                <div class="d-flex gap-3 flex-wrap small text-muted">
                                    <span><i class="bi bi-calendar-event"></i>
                                        เทศกาล: <strong>{{ $f->starts_at->format('d M Y') }}</strong>
                                        @if(!$f->starts_at->isSameDay($f->ends_at))
                                            — {{ $f->ends_at->format('d M Y') }}
                                        @endif
                                    </span>
                                    <span><i class="bi bi-megaphone"></i>
                                        Popup เริ่ม: <strong>{{ $popupStart->format('d M Y') }}</strong>
                                        ({{ $f->popup_lead_days }} วันก่อน)
                                    </span>
                                    <span><i class="bi bi-arrow-up"></i> Priority: <strong>{{ $f->show_priority }}</strong></span>
                                </div>
                            </div>
                            <div class="d-flex flex-column gap-2 align-items-end">
                                {{-- Toggle on/off --}}
                                <form method="POST" action="{{ route('admin.festivals.toggle', $f->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit"
                                            class="btn btn-sm {{ $f->enabled ? 'btn-success' : 'btn-outline-secondary' }}"
                                            title="{{ $f->enabled ? 'กดเพื่อปิด popup' : 'กดเพื่อเปิด popup' }}">
                                        <i class="bi {{ $f->enabled ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                        {{ $f->enabled ? 'เปิด' : 'ปิด' }}
                                    </button>
                                </form>

                                {{-- Bump year — only for past + recurring --}}
                                @if($isPast && $f->is_recurring)
                                <form method="POST" action="{{ route('admin.festivals.bump-year', $f->id) }}" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-info"
                                            title="ปรับวันที่เป็นปีถัดไปทันที">
                                        <i class="bi bi-arrow-clockwise"></i> ปรับเป็นปีหน้า
                                    </button>
                                </form>
                                @endif

                                {{-- Edit toggle --}}
                                <button type="button" @click="openId = openId === {{ $f->id }} ? null : {{ $f->id }}"
                                        class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i>
                                    <span x-show="openId !== {{ $f->id }}">แก้ไข</span>
                                    <span x-show="openId === {{ $f->id }}" x-cloak>ยกเลิก</span>
                                </button>
                            </div>
                        </div>

                        {{-- Inline edit form --}}
                        <div x-show="openId === {{ $f->id }}" x-cloak x-collapse class="mt-3 pt-3 border-top">
                            <form method="POST" action="{{ route('admin.festivals.update', $f->id) }}">
                                @csrf @method('PUT')
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label small">หัวข้อเทศกาล</label>
                                        <input type="text" name="name" value="{{ old('name', $f->name) }}"
                                               class="form-control" required maxlength="200">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small">ชื่อย่อ</label>
                                        <input type="text" name="short_name" value="{{ old('short_name', $f->short_name) }}"
                                               class="form-control" maxlength="80">
                                    </div>

                                    <div class="col-md-3">
                                        <label class="form-label small">Emoji</label>
                                        <input type="text" name="emoji" value="{{ old('emoji', $f->emoji) }}"
                                               class="form-control" maxlength="30">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label small">ธีมสี</label>
                                        <select name="theme_variant" class="form-select">
                                            @foreach($themes as $key => $themeOpt)
                                                <option value="{{ $key }}" @selected($f->theme_variant === $key)>
                                                    {{ $themeOpt['label'] }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">Priority</label>
                                        <input type="number" name="show_priority" value="{{ old('show_priority', $f->show_priority) }}"
                                               class="form-control" min="0" max="255">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small">เปิด popup</label>
                                        <div class="form-check form-switch mt-2">
                                            <input type="hidden" name="enabled" value="0">
                                            <input class="form-check-input" type="checkbox" name="enabled" value="1"
                                                   id="enabled-{{ $f->id }}" {{ $f->enabled ? 'checked' : '' }}>
                                            <label class="form-check-label" for="enabled-{{ $f->id }}">
                                                ใช้งาน
                                            </label>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label small">เริ่มเทศกาล</label>
                                        <input type="date" name="starts_at" value="{{ old('starts_at', $f->starts_at->format('Y-m-d')) }}"
                                               class="form-control" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small">จบเทศกาล</label>
                                        <input type="date" name="ends_at" value="{{ old('ends_at', $f->ends_at->format('Y-m-d')) }}"
                                               class="form-control" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label small">โชว์ popup ล่วงหน้ากี่วัน</label>
                                        <input type="number" name="popup_lead_days" value="{{ old('popup_lead_days', $f->popup_lead_days) }}"
                                               class="form-control" min="0" max="90" required>
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label small">หัวข้อโชว์ใน popup</label>
                                        <input type="text" name="headline" value="{{ old('headline', $f->headline) }}"
                                               class="form-control" required maxlength="250">
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label small">เนื้อหา (Markdown ได้)</label>
                                        <textarea name="body_md" class="form-control" rows="4" maxlength="5000">{{ old('body_md', $f->body_md) }}</textarea>
                                    </div>

                                    <div class="col-md-4">
                                        <label class="form-label small">ปุ่ม CTA</label>
                                        <input type="text" name="cta_label" value="{{ old('cta_label', $f->cta_label) }}"
                                               class="form-control" maxlength="80" placeholder="เช่น ดูช่างภาพ">
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label small">ลิงก์ปุ่ม</label>
                                        <input type="text" name="cta_url" value="{{ old('cta_url', $f->cta_url) }}"
                                               class="form-control" maxlength="500" placeholder="/events?tag=...">
                                    </div>

                                    <div class="col-12 d-flex justify-content-end gap-2">
                                        <button type="button" @click="openId = null" class="btn btn-light">ยกเลิก</button>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="bi bi-check-lg"></i> บันทึกการเปลี่ยนแปลง
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    ยังไม่มีเทศกาลในระบบ — ระบบจะ seed อัตโนมัติเมื่อ deploy หรือรัน
                    <code>php artisan db:seed --class=FestivalsSeeder</code>
                </div>
            </div>
        @endforelse
    </div>
</div>
@endsection
