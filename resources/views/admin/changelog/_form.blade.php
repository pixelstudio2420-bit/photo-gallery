@php $entry = $entry ?? null; $isEdit = (bool) $entry; @endphp

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
        <label class="block text-sm mb-1 dark:text-gray-200">Version <span class="text-rose-500">*</span></label>
        <input type="text" name="version" value="{{ old('version', $entry?->version) }}" required
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm" placeholder="1.2.0">
    </div>
    <div>
        <label class="block text-sm mb-1 dark:text-gray-200">วันที่ปล่อย <span class="text-rose-500">*</span></label>
        <input type="date" name="released_on" value="{{ old('released_on', $entry?->released_on?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
    </div>
    <div>
        <label class="block text-sm mb-1 dark:text-gray-200">ประเภท <span class="text-rose-500">*</span></label>
        <select name="type" required class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
            @foreach($types as $key => $cfg)
                <option value="{{ $key }}" @selected(old('type', $entry?->type) === $key)>{{ $cfg['label'] }}</option>
            @endforeach
        </select>
    </div>

    <div class="md:col-span-3">
        <label class="block text-sm mb-1 dark:text-gray-200">หัวข้อ <span class="text-rose-500">*</span></label>
        <input type="text" name="title" value="{{ old('title', $entry?->title) }}" required maxlength="200"
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
    </div>

    <div class="md:col-span-3">
        <label class="block text-sm mb-1 dark:text-gray-200">เนื้อหา (Markdown ได้)</label>
        <textarea name="body" rows="8" class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm font-mono">{{ old('body', $entry?->body) }}</textarea>
    </div>

    <div>
        <label class="block text-sm mb-1 dark:text-gray-200">เผยแพร่ให้</label>
        <select name="audience" class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
            @foreach($audiences as $key => $label)
                <option value="{{ $key }}" @selected(old('audience', $entry?->audience ?? 'all') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="md:col-span-2 flex items-center">
        <label class="inline-flex items-center gap-2 mt-6">
            <input type="hidden" name="is_published" value="0">
            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $entry?->is_published ?? true)) class="rounded">
            <span class="text-sm dark:text-gray-200">เผยแพร่ทันที</span>
        </label>
    </div>
</div>

<div class="flex gap-2 mt-6 justify-end">
    <a href="{{ route('admin.changelog.index') }}" class="px-4 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-700">ยกเลิก</a>
    <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
        <i class="bi bi-check-lg mr-1"></i>{{ $isEdit ? 'บันทึก' : 'สร้าง' }}
    </button>
</div>
