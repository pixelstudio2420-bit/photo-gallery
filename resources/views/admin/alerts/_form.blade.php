@php
    /** @var \App\Models\AlertRule|null $rule */
    $rule = $rule ?? null;
    $isEdit = (bool) $rule;
    $selectedChannels = old('channels', $rule?->channels ?? ['admin']);
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1 dark:text-gray-200">ชื่อ Rule <span class="text-rose-500">*</span></label>
        <input type="text" name="name" value="{{ old('name', $rule?->name) }}" required
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
        @error('name')<p class="text-xs text-rose-500 mt-1">{{ $message }}</p>@enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1 dark:text-gray-200">คำอธิบาย</label>
        <input type="text" name="description" value="{{ old('description', $rule?->description) }}"
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm"
               placeholder="บันทึกช่วยจำสั้นๆ ว่า rule นี้ทำอะไร">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1 dark:text-gray-200">Metric <span class="text-rose-500">*</span></label>
        <select name="metric" required
                class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
            @foreach($metrics as $key => $m)
                <option value="{{ $key }}" @selected(old('metric', $rule?->metric) === $key)>
                    {{ $m['label'] }} ({{ $m['unit'] }})
                </option>
            @endforeach
        </select>
        <p class="text-[11px] text-gray-400 mt-1">ค่าที่เราจะอ่านมาเทียบกับ threshold</p>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1 dark:text-gray-200">Severity <span class="text-rose-500">*</span></label>
        <select name="severity" required
                class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
            @foreach($severities as $key => $label)
                <option value="{{ $key }}" @selected(old('severity', $rule?->severity ?? 'warn') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1 dark:text-gray-200">Operator <span class="text-rose-500">*</span></label>
        <select name="operator" required
                class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
            @foreach($operators as $key => $label)
                <option value="{{ $key }}" @selected(old('operator', $rule?->operator ?? '>') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1 dark:text-gray-200">Threshold <span class="text-rose-500">*</span></label>
        <input type="number" step="0.0001" name="threshold" value="{{ old('threshold', $rule?->threshold) }}" required
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium mb-1 dark:text-gray-200">Cooldown (นาที) <span class="text-rose-500">*</span></label>
        <input type="number" min="1" max="10080" name="cooldown_minutes" value="{{ old('cooldown_minutes', $rule?->cooldown_minutes ?? 60) }}" required
               class="w-full px-3 py-2 rounded-lg border border-gray-200 dark:border-white/10 bg-white dark:bg-slate-900 text-sm">
        <p class="text-[11px] text-gray-400 mt-1">หลังแจ้งครั้งหนึ่ง จะเงียบไปนานเท่าไรก่อนยอมแจ้งซ้ำ</p>
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-2 dark:text-gray-200">ช่องทางแจ้งเตือน</label>
        <div class="flex flex-wrap gap-3">
            @foreach($channelOptions as $key => $cfg)
                <label class="inline-flex items-center gap-2 px-3 py-2 border border-gray-200 dark:border-white/10 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-slate-700">
                    <input type="checkbox" name="channels[]" value="{{ $key }}"
                           @checked(in_array($key, $selectedChannels, true))
                           class="rounded">
                    <i class="bi {{ $cfg['icon'] }}"></i>
                    <span class="text-sm">{{ $cfg['label'] }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="md:col-span-2">
        <label class="inline-flex items-center gap-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $rule?->is_active ?? true)) class="rounded">
            <span class="text-sm dark:text-gray-200">เปิดใช้งาน rule นี้</span>
        </label>
    </div>
</div>

<div class="flex gap-2 mt-6 justify-end">
    <a href="{{ route('admin.alerts.index') }}"
       class="px-4 py-2 border border-gray-200 dark:border-white/10 rounded-lg text-sm dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-slate-700">
        ยกเลิก
    </a>
    <button class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm">
        <i class="bi bi-check-lg mr-1"></i>{{ $isEdit ? 'บันทึก' : 'สร้าง' }}
    </button>
</div>
