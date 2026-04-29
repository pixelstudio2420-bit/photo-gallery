@php
  $isEdit = ($mode ?? 'create') === 'edit';
  $action = $isEdit ? route('admin.products.update', $product->id) : route('admin.products.store');
  $title  = $isEdit ? 'แก้ไขสินค้าดิจิทัล' : 'เพิ่มสินค้าดิจิทัล';
  $icon   = $isEdit ? 'bi-pencil-square' : 'bi-plus-lg';
@endphp

<div class="flex justify-between items-center mb-4">
  <h4 class="font-bold mb-0" style="letter-spacing:-0.02em;">
    <i class="bi {{ $icon }} mr-2" style="color:#6366f1;"></i>{{ $title }}
  </h4>
  <a href="{{ route('admin.products.index') }}" class="btn" style="background:rgba(99,102,241,0.08);color:#6366f1;border-radius:10px;font-weight:500;padding:0.5rem 1.2rem;border:none;">
    <i class="bi bi-arrow-left mr-1"></i> กลับ
  </a>
</div>

@if($errors->any())
<div class="bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm mb-4">
  <div class="font-semibold mb-1"><i class="bi bi-exclamation-triangle-fill mr-1"></i> พบข้อผิดพลาด</div>
  <ul class="list-disc pl-5 mb-0">
    @foreach($errors->all() as $error)
    <li>{{ $error }}</li>
    @endforeach
  </ul>
</div>
@endif

<form method="POST" action="{{ $action }}" enctype="multipart/form-data" x-data="productForm({
  fileSource: '{{ old('file_source', $product->file_source ?? 'local') }}'
})">
  @csrf
  @if($isEdit) @method('PUT') @endif

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

    {{-- LEFT: Main info --}}
    <div class="lg:col-span-2 space-y-4">

      {{-- Card: Basic info --}}
      <div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <div class="p-5">
          <h5 class="font-bold mb-3 text-sm uppercase tracking-wide text-slate-500"><i class="bi bi-info-circle mr-1"></i> ข้อมูลพื้นฐาน</h5>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-2">
              <label class="block text-sm font-semibold text-gray-700 mb-1.5">ชื่อสินค้า <span class="text-red-600">*</span></label>
              <input type="text" name="name" class="w-full px-4 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500" style="border-radius:10px;border-color:#e2e8f0;" value="{{ old('name', $product->name ?? '') }}" required>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1.5">Slug</label>
              <input type="text" name="slug" class="w-full px-4 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500" style="border-radius:10px;border-color:#e2e8f0;" value="{{ old('slug', $product->slug ?? '') }}" placeholder="สร้างอัตโนมัติ">
            </div>
          </div>

          <div class="mt-3">
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">คำอธิบายสั้น</label>
            <input type="text" name="short_description" maxlength="500" class="w-full px-4 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500" style="border-radius:10px;border-color:#e2e8f0;" value="{{ old('short_description', $product->short_description ?? '') }}" placeholder="อธิบายในหนึ่งประโยค">
          </div>

          <div class="mt-3">
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">รายละเอียด</label>
            <textarea name="description" class="w-full px-4 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500" style="border-radius:10px;border-color:#e2e8f0;" rows="5">{{ old('description', $product->description ?? '') }}</textarea>
          </div>
        </div>
      </div>

      {{-- Card: Digital file source --}}
      <div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <div class="p-5">
          <h5 class="font-bold mb-3 text-sm uppercase tracking-wide text-slate-500"><i class="bi bi-cloud-arrow-up mr-1"></i> ไฟล์ดิจิทัล</h5>

          {{-- Source selector (pill tabs) --}}
          <div class="mb-4">
            <label class="block text-sm font-semibold text-gray-700 mb-2">แหล่งไฟล์</label>
            <div class="grid grid-cols-3 gap-2">
              @foreach([
                ['key'=>'local',  'label'=>'อัปโหลดไฟล์',     'icon'=>'bi-upload',     'desc'=>'ฝากไว้ในเซิร์ฟเวอร์'],
                ['key'=>'drive',  'label'=>'Google Drive',  'icon'=>'bi-google',     'desc'=>'ลิงก์จาก Drive'],
                ['key'=>'direct', 'label'=>'Direct URL',    'icon'=>'bi-link-45deg', 'desc'=>'ลิงก์ตรง'],
              ] as $opt)
                <label class="cursor-pointer">
                  <input type="radio" name="file_source" value="{{ $opt['key'] }}" x-model="fileSource" class="peer sr-only">
                  <div class="rounded-xl border-2 p-3 text-center transition peer-checked:border-indigo-500 peer-checked:bg-indigo-50 peer-checked:shadow-sm hover:bg-slate-50"
                       :class="fileSource === '{{ $opt['key'] }}' ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200'">
                    <i class="bi {{ $opt['icon'] }} text-xl" :class="fileSource === '{{ $opt['key'] }}' ? 'text-indigo-600' : 'text-slate-400'"></i>
                    <div class="text-xs font-semibold mt-1" :class="fileSource === '{{ $opt['key'] }}' ? 'text-indigo-700' : 'text-slate-700'">{{ $opt['label'] }}</div>
                    <div class="text-[10px] text-slate-500 mt-0.5">{{ $opt['desc'] }}</div>
                  </div>
                </label>
              @endforeach
            </div>
          </div>

          {{-- LOCAL UPLOAD --}}
          <div x-show="fileSource === 'local'" x-cloak>
            @if($isEdit && !empty($product->local_file))
              <div class="mb-3 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center gap-2">
                <i class="bi bi-file-earmark-check-fill text-lg"></i>
                <span class="flex-1 min-w-0">
                  <div class="font-semibold truncate">ไฟล์ปัจจุบัน: {{ $product->local_file }}</div>
                  @if(!empty($product->file_size))<div class="text-xs opacity-80">{{ $product->file_size }}</div>@endif
                </span>
              </div>
            @endif
            <label class="block cursor-pointer rounded-xl border-2 border-dashed border-slate-300 hover:border-indigo-400 hover:bg-indigo-50/30 transition p-6 text-center">
              <input type="file" name="local_file" class="hidden" @change="onFileChange($event)">
              <i class="bi bi-cloud-upload text-3xl text-slate-400"></i>
              <div class="mt-2 text-sm font-semibold text-slate-700" x-text="fileName || 'คลิกเพื่อเลือกไฟล์'"></div>
              <div class="text-xs text-slate-500 mt-1" x-text="fileSizeHuman || 'ขนาดสูงสุด 500 MB • ZIP, PDF, PSD, LR, .zip, .rar ฯลฯ'"></div>
            </label>
          </div>

          {{-- GOOGLE DRIVE --}}
          <div x-show="fileSource === 'drive'" x-cloak class="space-y-3">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1.5">ลิงก์ Google Drive</label>
              <input type="url" name="drive_file_url" class="w-full px-4 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500" style="border-radius:10px;border-color:#e2e8f0;" value="{{ old('drive_file_url', $product->drive_file_url ?? '') }}" placeholder="https://drive.google.com/file/d/FILE_ID/view">
              <p class="text-xs text-slate-500 mt-1"><i class="bi bi-info-circle"></i> ระบบจะตัด File ID อัตโนมัติ · อย่าลืมตั้งค่า Share เป็น <strong>"ใครก็ได้ที่มีลิงก์"</strong></p>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1.5">File ID (กรอกเองก็ได้)</label>
              <input type="text" name="drive_file_id" class="w-full px-4 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 font-mono" style="border-radius:10px;border-color:#e2e8f0;" value="{{ old('drive_file_id', $product->drive_file_id ?? '') }}" placeholder="1A2b3C4d...">
            </div>
          </div>

          {{-- DIRECT URL --}}
          <div x-show="fileSource === 'direct'" x-cloak>
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Direct Download URL</label>
            <input type="url" name="direct_url" class="w-full px-4 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500" style="border-radius:10px;border-color:#e2e8f0;" value="{{ old('direct_url', $product->direct_url ?? '') }}" placeholder="https://cdn.example.com/file.zip">
            <p class="text-xs text-slate-500 mt-1"><i class="bi bi-info-circle"></i> ลิงก์ต้องเป็น Public ไม่ต้องใช้ Auth</p>
          </div>

          {{-- Metadata --}}
          <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">ฟอร์แมต</label>
              <input type="text" name="file_format" class="w-full px-3 py-2 border rounded-lg text-sm" style="border-radius:8px;border-color:#e2e8f0;" value="{{ old('file_format', $product->file_format ?? '') }}" placeholder="เช่น zip, psd, lrtemplate">
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">เวอร์ชัน</label>
              <input type="text" name="version" class="w-full px-3 py-2 border rounded-lg text-sm" style="border-radius:8px;border-color:#e2e8f0;" value="{{ old('version', $product->version ?? '') }}" placeholder="เช่น 1.0.0">
            </div>
            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">ขนาดไฟล์ (แสดงผล)</label>
              <input type="text" readonly class="w-full px-3 py-2 border rounded-lg text-sm bg-slate-50" style="border-radius:8px;border-color:#e2e8f0;" value="{{ $product->file_size ?? '— อัปโหลดเพื่อคำนวณอัตโนมัติ —' }}">
            </div>
          </div>

          {{-- Download limits --}}
          <div class="mt-4 p-4 rounded-xl bg-slate-50 border border-slate-200">
            <div class="text-xs font-semibold text-slate-600 mb-2"><i class="bi bi-shield-lock mr-1"></i> ข้อจำกัดการดาวน์โหลด</div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs text-slate-600 mb-1">จำนวนครั้งที่ดาวน์โหลดได้</label>
                <input type="number" name="download_limit" min="1" max="999" class="w-full px-3 py-2 border rounded-lg text-sm" style="border-radius:8px;border-color:#e2e8f0;" value="{{ old('download_limit', $product->download_limit ?? 5) }}">
              </div>
              <div>
                <label class="block text-xs text-slate-600 mb-1">ลิงก์หมดอายุใน (วัน)</label>
                <input type="number" name="download_expiry_days" min="1" max="3650" class="w-full px-3 py-2 border rounded-lg text-sm" style="border-radius:8px;border-color:#e2e8f0;" value="{{ old('download_expiry_days', $product->download_expiry_days ?? 30) }}">
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    {{-- RIGHT: Sidebar (price + status + cover) --}}
    <div class="space-y-4">

      {{-- Card: Pricing --}}
      <div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <div class="p-5">
          <h5 class="font-bold mb-3 text-sm uppercase tracking-wide text-slate-500"><i class="bi bi-tag mr-1"></i> ราคา</h5>
          <div class="space-y-3">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1.5">ราคา (฿) <span class="text-red-600">*</span></label>
              <input type="number" name="price" min="0" step="0.01" class="w-full px-4 py-2.5 border rounded-lg text-sm font-bold focus:ring-2 focus:ring-indigo-500" style="border-radius:10px;border-color:#e2e8f0;" value="{{ old('price', $product->price ?? '') }}" required>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1.5">ราคาลด (฿)</label>
              <input type="number" name="sale_price" min="0" step="0.01" class="w-full px-4 py-2.5 border rounded-lg text-sm focus:ring-2 focus:ring-indigo-500" style="border-radius:10px;border-color:#e2e8f0;" value="{{ old('sale_price', $product->sale_price ?? '') }}" placeholder="เว้นว่างถ้าไม่ลด">
            </div>
          </div>
        </div>
      </div>

      {{-- Card: Category / Status --}}
      <div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <div class="p-5">
          <h5 class="font-bold mb-3 text-sm uppercase tracking-wide text-slate-500"><i class="bi bi-collection mr-1"></i> หมวดหมู่ / สถานะ</h5>
          <div class="space-y-3">
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1.5">ประเภทสินค้า <span class="text-red-600">*</span></label>
              <select name="product_type" class="w-full px-4 py-2.5 border rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500" style="border-radius:10px;border-color:#e2e8f0;" required>
                @foreach([
                  'preset'   => 'พรีเซ็ต (Preset)',
                  'overlay'  => 'โอเวอร์เลย์ (Overlay)',
                  'template' => 'เทมเพลต (Template)',
                  'other'    => 'อื่นๆ',
                ] as $k => $v)
                  <option value="{{ $k }}" {{ old('product_type', $product->product_type ?? 'preset') === $k ? 'selected' : '' }}>{{ $v }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1.5">สถานะ</label>
              <select name="status" class="w-full px-4 py-2.5 border rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500" style="border-radius:10px;border-color:#e2e8f0;">
                <option value="active" {{ old('status', $product->status ?? 'draft') === 'active' ? 'selected' : '' }}>เผยแพร่ (Active)</option>
                <option value="draft" {{ old('status', $product->status ?? 'draft') === 'draft' ? 'selected' : '' }}>ร่าง (Draft)</option>
                <option value="inactive" {{ old('status', $product->status ?? '') === 'inactive' ? 'selected' : '' }}>ปิด (Inactive)</option>
              </select>
            </div>
            <div class="flex items-center gap-3">
              <label class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                <input type="hidden" name="is_featured" value="0">
                <input type="checkbox" name="is_featured" value="1" class="rounded text-indigo-600 focus:ring-indigo-500" {{ old('is_featured', $product->is_featured ?? false) ? 'checked' : '' }}>
                <span class="font-semibold"><i class="bi bi-star-fill text-amber-500"></i> แนะนำ (Featured)</span>
              </label>
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-700 mb-1.5">ลำดับ</label>
              <input type="number" name="sort_order" class="w-full px-4 py-2.5 border rounded-lg text-sm" style="border-radius:10px;border-color:#e2e8f0;" value="{{ old('sort_order', $product->sort_order ?? 0) }}">
            </div>
          </div>
        </div>
      </div>

      {{-- Card: Cover image --}}
      <div class="card border-0" style="border-radius:14px;box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <div class="p-5">
          <h5 class="font-bold mb-3 text-sm uppercase tracking-wide text-slate-500"><i class="bi bi-image mr-1"></i> รูปปก</h5>
          @if($isEdit && !empty($product->cover_image))
            <img src="{{ $product->cover_image_url }}" class="w-full h-40 object-cover rounded-lg mb-3">
          @endif
          <label class="block cursor-pointer rounded-lg border-2 border-dashed border-slate-300 hover:border-indigo-400 p-4 text-center transition">
            <input type="file" name="cover_image" accept="image/*" class="hidden" @change="onCoverChange($event)">
            <i class="bi bi-image text-2xl text-slate-400"></i>
            <div class="text-xs text-slate-600 mt-1" x-text="coverName || 'คลิกเพื่อเลือกรูป'"></div>
            <div class="text-[10px] text-slate-400 mt-0.5">JPG / PNG · สูงสุด 5MB</div>
          </label>
        </div>
      </div>

      {{-- Actions --}}
      <div class="flex gap-2">
        <button type="submit" class="flex-1 px-4 py-2.5 font-semibold text-white rounded-lg shadow-md hover:shadow-lg transition" style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
          <i class="bi bi-check-lg mr-1"></i> {{ $isEdit ? 'บันทึกการแก้ไข' : 'สร้างสินค้า' }}
        </button>
        <a href="{{ route('admin.products.index') }}" class="px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50 text-sm font-medium transition">ยกเลิก</a>
      </div>
    </div>
  </div>
</form>

@push('styles')
<style>[x-cloak]{display:none!important}</style>
@endpush

@push('scripts')
<script>
function productForm(init) {
  return {
    fileSource: init.fileSource || 'local',
    fileName: '',
    fileSizeHuman: '',
    coverName: '',
    onFileChange(e) {
      const f = e.target.files && e.target.files[0];
      if (!f) { this.fileName=''; this.fileSizeHuman=''; return; }
      this.fileName = f.name;
      this.fileSizeHuman = this.humanSize(f.size);
    },
    onCoverChange(e) {
      const f = e.target.files && e.target.files[0];
      this.coverName = f ? f.name : '';
    },
    humanSize(b) {
      if (b < 1024) return b + ' B';
      if (b < 1048576) return (b/1024).toFixed(1) + ' KB';
      if (b < 1073741824) return (b/1048576).toFixed(2) + ' MB';
      return (b/1073741824).toFixed(2) + ' GB';
    },
  };
}
</script>
@endpush
