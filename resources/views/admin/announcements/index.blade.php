@extends('layouts.admin')

@section('title', 'ประกาศ / ข่าวสาร')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">📢 ประกาศและข่าวสาร</h1>
            <p class="text-muted mb-0">จัดการประกาศกิจกรรมสำหรับช่างภาพและลูกค้า</p>
        </div>
        <a href="{{ route('admin.announcements.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> สร้างประกาศใหม่
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Stats cards --}}
    <div class="row mb-4 g-3">
        <div class="col-6 col-md-3">
            <div class="card"><div class="card-body py-3">
                <div class="text-muted small">ทั้งหมด</div>
                <div class="h4 mb-0">{{ $stats['total'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-success"><div class="card-body py-3">
                <div class="text-muted small">กำลังเผยแพร่</div>
                <div class="h4 mb-0 text-success">{{ $stats['live'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-warning"><div class="card-body py-3">
                <div class="text-muted small">ฉบับร่าง</div>
                <div class="h4 mb-0 text-warning">{{ $stats['draft'] }}</div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card border-secondary"><div class="card-body py-3">
                <div class="text-muted small">เก็บแล้ว</div>
                <div class="h4 mb-0 text-secondary">{{ $stats['archived'] }}</div>
            </div></div>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="card mb-4"><div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control"
                       placeholder="ค้นหาจากหัวข้อ/เกริ่นนำ..." value="{{ request('search') }}">
            </div>
            <div class="col-md-3">
                <select name="audience" class="form-select">
                    <option value="all_filter">— ทุกกลุ่มเป้าหมาย —</option>
                    <option value="all" @selected(request('audience')==='all')>ทั้งหมด (ช่าง + ลูกค้า)</option>
                    <option value="photographer" @selected(request('audience')==='photographer')>ช่างภาพ</option>
                    <option value="customer" @selected(request('audience')==='customer')>ลูกค้า</option>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">— ทุกสถานะ —</option>
                    <option value="published" @selected(request('status')==='published')>เผยแพร่</option>
                    <option value="draft"     @selected(request('status')==='draft')>ฉบับร่าง</option>
                    <option value="archived"  @selected(request('status')==='archived')>เก็บแล้ว</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-outline-primary flex-grow-1">กรอง</button>
                <a href="{{ route('admin.announcements.index') }}" class="btn btn-outline-secondary">ล้าง</a>
            </div>
        </div>
        <div class="form-check mt-2">
            <input class="form-check-input" type="checkbox" name="only_trashed" value="1"
                   id="onlyTrashed" @checked(request('only_trashed')) onchange="this.form.submit()">
            <label class="form-check-label" for="onlyTrashed">แสดงเฉพาะที่ลบแล้ว</label>
        </div>
    </div></form>

    {{-- List --}}
    <div class="card"><div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:80px;"></th>
                    <th>หัวข้อ</th>
                    <th>กลุ่มเป้าหมาย</th>
                    <th>สถานะ</th>
                    <th>ความสำคัญ</th>
                    <th>ช่วงเวลา</th>
                    <th>วิว</th>
                    <th class="text-end">จัดการ</th>
                </tr>
            </thead>
            <tbody>
            @forelse($announcements as $a)
                <tr class="{{ $a->trashed() ? 'table-secondary' : '' }}">
                    <td>
                        @if($a->cover_image_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('media.disk', 'r2'))->url($a->cover_image_path) }}"
                                 alt="" style="width:60px;height:60px;object-fit:cover;border-radius:6px;">
                        @else
                            <div style="width:60px;height:60px;border-radius:6px;background:#e2e8f0;display:flex;align-items:center;justify-content:center;color:#94a3b8;">
                                <i class="bi bi-image"></i>
                            </div>
                        @endif
                    </td>
                    <td>
                        @if($a->is_pinned)
                            <i class="bi bi-pin-angle-fill text-danger me-1" title="ปักหมุด"></i>
                        @endif
                        <strong>{{ $a->title }}</strong>
                        <div class="text-muted small">{{ $a->slug }}</div>
                    </td>
                    <td>
                        @switch($a->audience)
                            @case('photographer') <span class="badge bg-primary">ช่างภาพ</span> @break
                            @case('customer')     <span class="badge bg-info">ลูกค้า</span> @break
                            @default              <span class="badge bg-secondary">ทั้งหมด</span>
                        @endswitch
                    </td>
                    <td>
                        @if($a->trashed())
                            <span class="badge bg-danger">ลบแล้ว</span>
                        @elseif($a->status === 'published' && $a->isLive())
                            <span class="badge bg-success">กำลังเผยแพร่</span>
                        @elseif($a->status === 'published')
                            <span class="badge bg-warning text-dark">เผยแพร่ (นอกช่วง)</span>
                        @elseif($a->status === 'draft')
                            <span class="badge bg-secondary">ร่าง</span>
                        @else
                            <span class="badge bg-dark">เก็บแล้ว</span>
                        @endif
                    </td>
                    <td>
                        @switch($a->priority)
                            @case('high')   <span class="text-danger fw-semibold">สูง</span> @break
                            @case('low')    <span class="text-muted">ต่ำ</span> @break
                            @default        ปกติ
                        @endswitch
                    </td>
                    <td class="small">
                        @if($a->starts_at) <div>เริ่ม: {{ $a->starts_at->format('d/m/y H:i') }}</div> @endif
                        @if($a->ends_at)   <div>สิ้นสุด: {{ $a->ends_at->format('d/m/y H:i') }}</div> @endif
                        @if(!$a->starts_at && !$a->ends_at) <span class="text-muted">— ไม่จำกัด —</span> @endif
                    </td>
                    <td>{{ number_format($a->view_count) }}</td>
                    <td class="text-end">
                        @if($a->trashed())
                            <form method="POST" action="{{ route('admin.announcements.restore', $a->id) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-outline-success">กู้คืน</button>
                            </form>
                        @else
                            <a href="{{ route('admin.announcements.edit', $a->id) }}" class="btn btn-sm btn-outline-primary">แก้ไข</a>
                            @if($a->status !== 'published')
                                <form method="POST" action="{{ route('admin.announcements.publish', $a->id) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-success">เผยแพร่</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.announcements.archive', $a->id) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary">เก็บ</button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.announcements.destroy', $a->id) }}" class="d-inline"
                                  onsubmit="return confirm('ต้องการลบประกาศนี้?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">ลบ</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center text-muted py-5">ยังไม่มีประกาศ —
                    <a href="{{ route('admin.announcements.create') }}">สร้างประกาศแรก</a></td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>

    @if($announcements->hasPages())
        <div class="mt-3">{{ $announcements->links() }}</div>
    @endif
</div>
@endsection
