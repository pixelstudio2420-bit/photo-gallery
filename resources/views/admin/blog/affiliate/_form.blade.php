{{-- Shared form partial for affiliate link create/edit --}}
@php $isEdit = isset($link) && $link->exists; @endphp

<div x-data="affiliateForm()">
    <form method="POST"
          action="{{ $isEdit ? route('admin.blog.affiliate.update', $link) : route('admin.blog.affiliate.store') }}"
          enctype="multipart/form-data">
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Left Column --}}
            <div class="space-y-6">
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] p-6 space-y-4">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2 mb-4">
                        <i class="bi bi-info-circle text-indigo-500"></i>ข้อมูลลิงก์
                    </h3>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">ชื่อ <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $link->name ?? '') }}" required
                               x-model="name" @input="autoSlug()"
                               placeholder="เช่น Amazon Camera Link"
                               class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Slug (URL Cloaked)</label>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-400 flex-shrink-0">{{ url('/go') }}/</span>
                            <input type="text" name="slug" value="{{ old('slug', $link->slug ?? '') }}"
                                   x-model="slug" placeholder="auto-generated"
                                   class="flex-1 text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500 font-mono">
                        </div>
                        @error('slug') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">URL ปลายทาง <span class="text-red-500">*</span></label>
                        <input type="url" name="destination_url" value="{{ old('destination_url', $link->destination_url ?? '') }}" required
                               placeholder="https://www.example.com/product?ref=..."
                               class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        @error('destination_url') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">ผู้ให้บริการ</label>
                            <select name="provider" x-model="provider"
                                    class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                                <option value="">-- เลือก --</option>
                                <option value="amazon" {{ old('provider', $link->provider ?? '') == 'amazon' ? 'selected' : '' }}>Amazon</option>
                                <option value="lazada" {{ old('provider', $link->provider ?? '') == 'lazada' ? 'selected' : '' }}>Lazada</option>
                                <option value="shopee" {{ old('provider', $link->provider ?? '') == 'shopee' ? 'selected' : '' }}>Shopee</option>
                                <option value="accesstrade" {{ old('provider', $link->provider ?? '') == 'accesstrade' ? 'selected' : '' }}>AccessTrade</option>
                                <option value="other" {{ old('provider', $link->provider ?? '') == 'other' ? 'selected' : '' }}>อื่นๆ</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">แคมเปญ</label>
                            <input type="text" name="campaign" value="{{ old('campaign', $link->campaign ?? '') }}"
                                   placeholder="เช่น summer-2025"
                                   class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">อัตราค่าคอมมิชชัน (%)</label>
                        <input type="number" name="commission_rate" value="{{ old('commission_rate', $link->commission_rate ?? '') }}"
                               step="0.01" min="0" max="100" placeholder="0.00"
                               class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">คำอธิบาย</label>
                        <textarea name="description" rows="3" placeholder="คำอธิบายเกี่ยวกับลิงก์ Affiliate นี้..."
                                  class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500 resize-none"
                                  >{{ old('description', $link->description ?? '') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Right Column --}}
            <div class="space-y-6">
                {{-- Image --}}
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] p-6">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2 mb-4">
                        <i class="bi bi-image text-pink-500"></i>รูปภาพ
                    </h3>
                    <input type="file" name="image" accept="image/*"
                           class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-600 dark:file:bg-indigo-500/20 dark:file:text-indigo-400 hover:file:bg-indigo-100">
                    @if($isEdit && $link->image)
                        <div class="mt-3">
                            <img src="{{ asset('storage/' . $link->image) }}" alt="" class="w-32 h-32 object-cover rounded-xl border border-gray-200 dark:border-white/10">
                        </div>
                    @endif
                </div>

                {{-- Settings --}}
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-gray-100 dark:border-white/[0.06] p-6 space-y-4">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-white flex items-center gap-2 mb-4">
                        <i class="bi bi-gear text-gray-500"></i>ตั้งค่า
                    </h3>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <div class="relative" x-data="{ on: {{ old('nofollow', $link->nofollow ?? true) ? 'true' : 'false' }} }">
                            <input type="hidden" name="nofollow" :value="on ? 1 : 0">
                            <button type="button" @click="on = !on"
                                    :class="on ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-slate-600'"
                                    class="relative w-11 h-6 rounded-full transition-colors">
                                <span :class="on ? 'translate-x-5' : 'translate-x-0.5'"
                                      class="inline-block w-5 h-5 bg-white rounded-full shadow transform transition-transform"></span>
                            </button>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-slate-700 dark:text-gray-200">Nofollow</span>
                            <p class="text-xs text-gray-400">เพิ่ม rel="nofollow" ให้ลิงก์</p>
                        </div>
                    </label>

                    <label class="flex items-center gap-3 cursor-pointer">
                        <div class="relative" x-data="{ on: {{ old('is_active', $link->is_active ?? true) ? 'true' : 'false' }} }">
                            <input type="hidden" name="is_active" :value="on ? 1 : 0">
                            <button type="button" @click="on = !on"
                                    :class="on ? 'bg-emerald-500' : 'bg-gray-300 dark:bg-slate-600'"
                                    class="relative w-11 h-6 rounded-full transition-colors">
                                <span :class="on ? 'translate-x-5' : 'translate-x-0.5'"
                                      class="inline-block w-5 h-5 bg-white rounded-full shadow transform transition-transform"></span>
                            </button>
                        </div>
                        <div>
                            <span class="text-sm font-medium text-slate-700 dark:text-gray-200">ใช้งาน</span>
                            <p class="text-xs text-gray-400">เปิด/ปิดการใช้งานลิงก์นี้</p>
                        </div>
                    </label>

                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">วันหมดอายุ</label>
                        <input type="datetime-local" name="expires_at"
                               value="{{ old('expires_at', $isEdit && $link->expires_at ? $link->expires_at->format('Y-m-d\TH:i') : '') }}"
                               class="w-full text-sm px-3 py-2.5 border border-gray-200 dark:border-white/10 rounded-xl bg-gray-50 dark:bg-slate-700/50 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        <p class="text-xs text-gray-400 mt-1">เว้นว่างถ้าไม่มีวันหมดอายุ</p>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="flex items-center gap-3">
                    <a href="{{ route('admin.blog.affiliate.index') }}"
                       class="flex-1 px-4 py-2.5 text-center text-sm font-medium bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">
                        ยกเลิก
                    </a>
                    <button type="submit"
                            class="flex-1 px-4 py-2.5 text-sm font-medium bg-indigo-600 text-white rounded-xl hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-500/25">
                        <i class="bi bi-check-lg mr-1"></i>{{ $isEdit ? 'อัปเดต' : 'สร้างลิงก์' }}
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
function affiliateForm() {
    return {
        name: @json(old('name', $link->name ?? '')),
        slug: @json(old('slug', $link->slug ?? '')),
        provider: @json(old('provider', $link->provider ?? '')),

        autoSlug() {
            if (!this.slug) {
                this.slug = this.name.toLowerCase()
                    .replace(/[^a-z0-9\s-]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-')
                    .replace(/^-|-$/g, '');
            }
        }
    };
}
</script>
@endpush
