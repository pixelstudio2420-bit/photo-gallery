@extends('layouts.admin')

@section('title', $announcement->exists ? 'แก้ไขประกาศ' : 'สร้างประกาศ')

@section('content')
<div class="container-fluid py-4" style="max-width:1100px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="{{ route('admin.announcements.index') }}" class="text-decoration-none small text-muted mb-2 d-inline-block">
                <i class="bi bi-arrow-left"></i> กลับไปหน้ารายการ
            </a>
            <h1 class="h3 mb-0">{{ $announcement->exists ? '✏️ แก้ไขประกาศ' : '➕ สร้างประกาศใหม่' }}</h1>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>กรอกข้อมูลไม่ครบ:</strong>
            <ul class="mb-0">@foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach</ul>
        </div>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $announcement->exists ? route('admin.announcements.update', $announcement->id) : route('admin.announcements.store') }}">
        @csrf
        @if($announcement->exists) @method('PUT') @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card mb-4"><div class="card-body">
                    <h5 class="card-title mb-3">เนื้อหาหลัก</h5>

                    <div class="mb-3">
                        <label class="form-label">หัวข้อ <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required maxlength="200"
                               value="{{ old('title', $announcement->title) }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Slug (URL — ปล่อยว่างให้ระบบสร้างจากหัวข้อ)</label>
                        <input type="text" name="slug" class="form-control" pattern="[a-z0-9\-]+" maxlength="220"
                               value="{{ old('slug', $announcement->slug) }}"
                               placeholder="เช่น new-promo-may-2026">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">เกริ่นนำ (excerpt)</label>
                        <textarea name="excerpt" class="form-control" rows="2" maxlength="300"
                                  placeholder="ข้อความสั้น ๆ แสดงในรายการประกาศ">{{ old('excerpt', $announcement->excerpt) }}</textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">เนื้อหา (รองรับ HTML/Markdown)</label>
                        <textarea name="body" class="form-control" rows="12">{{ old('body', $announcement->body) }}</textarea>
                        <div class="form-text">รองรับ tag พื้นฐาน: p, h2, h3, ul/ol, strong, em, a, br</div>
                    </div>
                </div></div>

                {{-- Cover Image --}}
                <div class="card mb-4"><div class="card-body">
                    <h5 class="card-title mb-3">รูปหน้าปก</h5>

                    @if($announcement->cover_image_path)
                        <div class="mb-3">
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('media.disk', 'r2'))->url($announcement->cover_image_path) }}"
                                 alt="" style="max-width:300px;border-radius:8px;">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="remove_cover" value="1" id="removeCover">
                                <label class="form-check-label" for="removeCover">ลบรูปหน้าปกปัจจุบัน</label>
                            </div>
                        </div>
                    @endif

                    <input type="file" name="cover_image" class="form-control" accept="image/*">
                    <div class="form-text">JPG/PNG/WebP — ขนาดไม่เกิน 5MB</div>
                </div></div>

                {{-- Attachments --}}
                @if($announcement->exists)
                    <div class="card mb-4"><div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">รูปประกอบเพิ่มเติม</h5>
                            <span class="text-muted small">{{ $announcement->attachments->count() }} รูป</span>
                        </div>

                        @if($announcement->attachments->count() > 0)
                            <div class="row g-2 mb-3">
                                @foreach($announcement->attachments as $att)
                                    <div class="col-6 col-md-3">
                                        <div class="position-relative">
                                            <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('media.disk', 'r2'))->url($att->image_path) }}"
                                                 alt="" style="width:100%;height:120px;object-fit:cover;border-radius:6px;">
                                            <form method="POST"
                                                  action="{{ route('admin.announcements.attachments.destroy', $att->id) }}"
                                                  class="position-absolute top-0 end-0 m-1"
                                                  onsubmit="return confirm('ลบรูปนี้?')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-danger"><i class="bi bi-x"></i></button>
                                            </form>
                                            @if($att->caption)
                                                <div class="small text-muted mt-1">{{ $att->caption }}</div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div></div>

                    {{-- Separate form for adding attachments (so submitting it doesn't try to update the whole announcement) --}}
                @endif
            </div>

            <div class="col-lg-4">
                <div class="card mb-4"><div class="card-body">
                    <h5 class="card-title mb-3">การเผยแพร่</h5>

                    <div class="mb-3">
                        <label class="form-label">สถานะ</label>
                        <select name="status" class="form-select">
                            @foreach(['draft' => 'ฉบับร่าง', 'published' => 'เผยแพร่', 'archived' => 'เก็บแล้ว'] as $v => $l)
                                <option value="{{ $v }}" @selected(old('status', $announcement->status)===$v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">กลุ่มเป้าหมาย</label>
                        <select name="audience" class="form-select">
                            <option value="all" @selected(old('audience', $announcement->audience)==='all')>ทุกคน (ช่าง + ลูกค้า)</option>
                            <option value="photographer" @selected(old('audience', $announcement->audience)==='photographer')>เฉพาะช่างภาพ</option>
                            <option value="customer" @selected(old('audience', $announcement->audience)==='customer')>เฉพาะลูกค้า</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ความสำคัญ</label>
                        <select name="priority" class="form-select">
                            <option value="low"    @selected(old('priority', $announcement->priority)==='low')>ต่ำ</option>
                            <option value="normal" @selected(old('priority', $announcement->priority)==='normal')>ปกติ</option>
                            <option value="high"   @selected(old('priority', $announcement->priority)==='high')>สูง (ขึ้นบนสุด)</option>
                        </select>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="is_pinned" value="1" id="isPinned"
                               @checked(old('is_pinned', $announcement->is_pinned))>
                        <label class="form-check-label" for="isPinned">📌 ปักหมุดบนสุด</label>
                    </div>

                    <hr>

                    <div class="mb-3">
                        <label class="form-label">เริ่มแสดง</label>
                        <input type="datetime-local" name="starts_at" class="form-control"
                               value="{{ old('starts_at', $announcement->starts_at?->format('Y-m-d\TH:i')) }}">
                        <div class="form-text">ปล่อยว่าง = แสดงทันทีเมื่อเผยแพร่</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">สิ้นสุด</label>
                        <input type="datetime-local" name="ends_at" class="form-control"
                               value="{{ old('ends_at', $announcement->ends_at?->format('Y-m-d\TH:i')) }}">
                        <div class="form-text">ปล่อยว่าง = แสดงตลอดไป</div>
                    </div>
                </div></div>

                <div class="card mb-4"><div class="card-body">
                    <h5 class="card-title mb-3">ปุ่ม Call-to-Action (ถ้ามี)</h5>
                    <div class="mb-3">
                        <label class="form-label">ข้อความบนปุ่ม</label>
                        <input type="text" name="cta_label" class="form-control" maxlength="60"
                               value="{{ old('cta_label', $announcement->cta_label) }}"
                               placeholder="เช่น สมัครเลย">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ลิงก์</label>
                        <input type="url" name="cta_url" class="form-control" maxlength="500"
                               value="{{ old('cta_url', $announcement->cta_url) }}"
                               placeholder="https://...">
                    </div>
                </div></div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-lg"></i> {{ $announcement->exists ? 'บันทึกการเปลี่ยนแปลง' : 'สร้างประกาศ' }}
                    </button>
                    <a href="{{ route('admin.announcements.index') }}" class="btn btn-outline-secondary">ยกเลิก</a>
                </div>
            </div>
        </div>
    </form>

    {{-- Attachments form (separate so it submits independently) --}}
    @if($announcement->exists)
        <div class="card mt-4"><div class="card-body">
            <h5 class="card-title mb-3">เพิ่มรูปประกอบใหม่</h5>
            <form method="POST" enctype="multipart/form-data"
                  action="{{ route('admin.announcements.attachments.store', $announcement->id) }}">
                @csrf
                <div class="row g-2">
                    <div class="col-md-6">
                        <input type="file" name="image" class="form-control" accept="image/*" required>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="caption" class="form-control" maxlength="200" placeholder="คำบรรยายภาพ (ไม่บังคับ)">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-success w-100"><i class="bi bi-upload"></i> อัปโหลด</button>
                    </div>
                </div>
            </form>
        </div></div>
    @endif
</div>
@endsection
