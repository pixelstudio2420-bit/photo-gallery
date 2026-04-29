@props([
    'category'    => null,        // required: "events.photos", "auth.avatar", etc.
    'resourceId'  => null,        // required when the category demands it
    'multiple'    => false,
    'accept'      => 'image/*',   // browser <input accept> hint
    'name'        => 'files',
    'onSuccess'   => null,        // optional JS callback name on window
    'maxSizeMb'   => null,        // hint shown to user; server still enforces
])

@php
    if (!$category) {
        throw new \InvalidArgumentException('<x-r2-upload> requires a `category` attribute');
    }
@endphp

{{--
    R2 direct-upload component (Alpine.js).

    Renders a dashed file picker plus a per-file progress list. The browser
    talks directly to Cloudflare R2 via presigned PUT — the Laravel app
    only mints the URL and confirms completion.

    Listens for the JS event 'r2-upload:done' on the host element so the
    parent page can react when files finish (e.g. add a card to a gallery,
    update a photo count, etc.).
--}}
<div
    x-data="r2Upload({
        category:   '{{ $category }}',
        resourceId: {{ $resourceId !== null ? (int) $resourceId : 'null' }},
        multiple:   {{ $multiple ? 'true' : 'false' }},
        onSuccess:  (file) => {
            $dispatch('r2-upload-done', file);
            @if($onSuccess) (window['{{ $onSuccess }}'] || (() => {}))(file); @endif
        },
        onError: (err) => {
            $dispatch('r2-upload-error', err);
        },
    })"
    {{ $attributes->class(['r2-upload-host']) }}
>
    {{-- Picker --}}
    <label
        class="block border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-lg p-6 text-center cursor-pointer hover:border-blue-500 transition"
        @dragover.prevent="$el.classList.add('border-blue-500')"
        @dragleave="$el.classList.remove('border-blue-500')"
        @drop.prevent="
            $el.classList.remove('border-blue-500');
            const synthetic = { target: { files: $event.dataTransfer.files, value: '' } };
            onFilesPicked(synthetic);
        "
    >
        <input
            type="file"
            class="hidden"
            x-ref="input"
            @change="onFilesPicked"
            {{ $multiple ? 'multiple' : '' }}
            accept="{{ $accept }}"
            aria-label="Upload files"
        >
        <div class="text-sm text-gray-600 dark:text-gray-300">
            <strong>คลิกเพื่อเลือกไฟล์</strong> หรือลากมาวาง
            @if($maxSizeMb)
                <p class="text-xs mt-1 text-gray-500">ขนาดสูงสุด {{ $maxSizeMb }} MB ต่อไฟล์</p>
            @endif
        </div>
    </label>

    {{-- Per-file progress list --}}
    <ul class="mt-4 space-y-2" x-show="queue.length > 0">
        <template x-for="f in queue" :key="f.id">
            <li class="border border-gray-200 dark:border-gray-700 rounded p-3">
                <div class="flex items-center justify-between text-sm">
                    <span class="truncate flex-1 mr-3" x-text="f.name"></span>
                    <span x-text="f.status"
                          :class="{
                              'text-green-600': f.status === 'done',
                              'text-red-600':   f.status === 'failed',
                              'text-gray-500':  ['queued', 'signing', 'confirming'].includes(f.status),
                              'text-blue-600':  f.status === 'uploading',
                          }"></span>
                </div>
                <div class="mt-2 w-full bg-gray-200 dark:bg-gray-700 rounded h-1.5">
                    <div class="h-1.5 rounded transition-all"
                         :class="f.status === 'failed' ? 'bg-red-500' : 'bg-blue-500'"
                         :style="`width: ${f.progress}%`"></div>
                </div>
                <div x-show="f.error" class="mt-1 text-xs text-red-600" x-text="f.error"></div>
                <button
                    type="button"
                    x-show="f.status === 'failed'"
                    @click="retry(f)"
                    class="mt-2 text-xs text-blue-600 hover:underline"
                >ลองใหม่</button>
            </li>
        </template>
    </ul>

    {{-- Summary line — useful for forms that need to wait until all uploads finish --}}
    <div x-show="queue.length > 0" class="mt-3 text-xs text-gray-500">
        เสร็จแล้ว <span x-text="done.length"></span>/<span x-text="queue.length"></span>
        <span x-show="failed.length > 0">— <span class="text-red-600">ล้มเหลว <span x-text="failed.length"></span></span></span>
    </div>
</div>
