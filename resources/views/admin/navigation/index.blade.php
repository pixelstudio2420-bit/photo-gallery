@extends('layouts.admin')

@section('title', 'จัดการเมนูนำทาง')

@section('content')
<div class="max-w-6xl mx-auto" x-data="navMenuManager()">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h4 class="font-bold tracking-tight flex items-center gap-2 mb-1">
                <i class="bi bi-list-nested text-indigo-500"></i>
                จัดการเมนูนำทาง
            </h4>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                ลาก-วาง เพื่อเรียงลำดับ · กดปุ่ม Toggle เพื่อเปิด/ปิด · คลิกชื่อเพื่อแก้ไข ·
                เปลี่ยน "ตำแหน่งแสดงผล" เพื่อย้ายระหว่าง <strong>Navbar</strong> ↔ <strong>Footer</strong>
            </p>
        </div>
        <a href="{{ route('admin.navigation.create') }}"
           class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold shadow-sm transition">
            <i class="bi bi-plus-lg"></i> เพิ่มเมนูใหม่
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-900 text-sm px-4 py-3 dark:bg-emerald-500/10 dark:border-emerald-500/30 dark:text-emerald-300">
            <i class="bi bi-check-circle-fill mr-1.5"></i>{!! session('success') !!}
        </div>
    @endif

    {{-- Quick legend --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-5 text-xs">
        <div class="rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-white/10 p-3">
            <div class="font-bold text-slate-800 dark:text-white mb-0.5">📍 ตำแหน่ง</div>
            <div class="text-slate-500 dark:text-slate-400">navbar = ด้านบน · footer = ด้านล่าง · both = ทั้งสองที่ · hidden = ปิด</div>
        </div>
        <div class="rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-white/10 p-3">
            <div class="font-bold text-slate-800 dark:text-white mb-0.5">👤 ผู้เห็น</div>
            <div class="text-slate-500 dark:text-slate-400">public = ทุกคน · guest = ยังไม่ login · photographer = ช่างภาพเท่านั้น</div>
        </div>
        <div class="rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-white/10 p-3">
            <div class="font-bold text-slate-800 dark:text-white mb-0.5">🎨 รูปแบบ</div>
            <div class="text-slate-500 dark:text-slate-400">default = ลิงก์ปกติ · accent = สีเหลือง (CTA)</div>
        </div>
        <div class="rounded-lg bg-slate-50 dark:bg-slate-800/50 border border-slate-200 dark:border-white/10 p-3">
            <div class="font-bold text-slate-800 dark:text-white mb-0.5">🔄 cache</div>
            <div class="text-slate-500 dark:text-slate-400">บันทึก = อัพเดททันที (cache flush อัตโนมัติ)</div>
        </div>
    </div>

    {{-- Items grouped by location, drag-drop sortable within each group --}}
    @foreach(['navbar', 'both', 'footer', 'hidden'] as $loc)
        @php
            $list = $itemsByLocation->get($loc, collect());
            $titles = [
                'navbar' => ['📌 แสดงเฉพาะ Navbar (ด้านบน)', 'indigo'],
                'both'   => ['🔁 แสดงทั้ง Navbar + Footer',   'emerald'],
                'footer' => ['📎 แสดงเฉพาะ Footer (ด้านล่าง)', 'amber'],
                'hidden' => ['🚫 ซ่อน (ไม่แสดง)',              'slate'],
            ];
            [$title, $color] = $titles[$loc];
        @endphp

        <div class="mb-6">
            <h5 class="font-bold text-sm mb-3 flex items-center gap-2">
                <span class="text-{{ $color }}-600 dark:text-{{ $color }}-400">{{ $title }}</span>
                <span class="text-xs font-normal text-slate-400">({{ $list->count() }} รายการ)</span>
            </h5>

            @if($list->isEmpty())
                <div class="rounded-lg border-2 border-dashed border-slate-200 dark:border-white/10 p-8 text-center text-sm text-slate-400">
                    ยังไม่มีรายการในกลุ่มนี้ — สร้างใหม่ หรือลากจากกลุ่มอื่นมาวางที่นี่
                </div>
            @else
                <ul class="space-y-2 sortable" data-location="{{ $loc }}">
                    @foreach($list as $item)
                        <li class="rounded-xl border border-slate-200 dark:border-white/10 bg-white dark:bg-slate-800 p-3 flex items-center gap-3 sortable-item {{ !$item->is_active ? 'opacity-50' : '' }}"
                            data-id="{{ $item->id }}">
                            {{-- Drag handle --}}
                            <span class="cursor-move text-slate-300 hover:text-slate-500 px-1" title="ลากเพื่อเรียงใหม่">
                                <i class="bi bi-grip-vertical text-lg"></i>
                            </span>

                            {{-- Icon preview --}}
                            <span class="w-9 h-9 rounded-lg bg-slate-100 dark:bg-white/5 flex items-center justify-center text-slate-600 dark:text-slate-300">
                                @if($item->icon)
                                    <i class="bi bi-{{ $item->icon }}"></i>
                                @else
                                    <i class="bi bi-link-45deg"></i>
                                @endif
                            </span>

                            {{-- Label + URL --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.navigation.edit', $item) }}"
                                       class="font-semibold text-slate-800 dark:text-white hover:text-indigo-600 truncate">
                                        {{ $item->label }}
                                    </a>
                                    @if($item->cta_style === 'accent')
                                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-amber-500/15 text-amber-700 dark:text-amber-400 font-bold">CTA</span>
                                    @elseif($item->cta_style === 'primary')
                                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-blue-500/15 text-blue-700 dark:text-blue-400 font-bold">PRIMARY</span>
                                    @endif
                                    @if($item->badge_text)
                                        <span class="text-[9px] px-1.5 py-0.5 rounded bg-{{ $item->badge_color ?: 'slate' }}-500/15 text-{{ $item->badge_color ?: 'slate' }}-700 dark:text-{{ $item->badge_color ?: 'slate' }}-400 font-bold">{{ $item->badge_text }}</span>
                                    @endif
                                </div>
                                <div class="text-xs text-slate-500 dark:text-slate-400 truncate font-mono">{{ $item->url }}</div>
                            </div>

                            {{-- Audience pill --}}
                            <span class="text-[10px] px-2 py-1 rounded-full
                                @if($item->audience === 'public')        bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400
                                @elseif($item->audience === 'guest')      bg-slate-100   text-slate-700   dark:bg-white/5         dark:text-slate-300
                                @elseif($item->audience === 'photographer') bg-violet-100 text-violet-700 dark:bg-violet-500/15  dark:text-violet-400
                                @else                                     bg-blue-100    text-blue-700    dark:bg-blue-500/15    dark:text-blue-400
                                @endif">
                                {{ $audiences[$item->audience] ?? $item->audience }}
                            </span>

                            {{-- Toggle is_active --}}
                            <form method="POST" action="{{ route('admin.navigation.toggle', $item) }}" class="m-0">
                                @csrf
                                <button type="submit" class="text-xs px-2.5 py-1.5 rounded-lg font-semibold transition
                                        {{ $item->is_active
                                            ? 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-400 dark:hover:bg-emerald-500/25'
                                            : 'bg-rose-100    text-rose-700    hover:bg-rose-200    dark:bg-rose-500/15    dark:text-rose-400    dark:hover:bg-rose-500/25' }}"
                                        title="{{ $item->is_active ? 'คลิกเพื่อปิด' : 'คลิกเพื่อเปิด' }}">
                                    <i class="bi {{ $item->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                    {{ $item->is_active ? 'เปิด' : 'ปิด' }}
                                </button>
                            </form>

                            {{-- Edit + delete --}}
                            <a href="{{ route('admin.navigation.edit', $item) }}"
                               class="text-slate-500 hover:text-indigo-600 px-2 py-1.5" title="แก้ไข">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                            <form method="POST" action="{{ route('admin.navigation.destroy', $item) }}" class="m-0"
                                  onsubmit="return confirm('ลบเมนู &quot;{{ $item->label }}&quot; แน่นอน?\n\nหากต้องการแค่ซ่อนไว้ ใช้ปุ่ม Toggle (ปิด) แทนจะดีกว่า');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-slate-500 hover:text-rose-600 px-2 py-1.5" title="ลบถาวร">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endforeach
</div>

@push('scripts')
{{-- SortableJS — drag-drop reorder. Loaded from CDN; the admin layout
     already pulls Tailwind+Alpine so this adds ~50KB just here. --}}
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
function navMenuManager() {
    return {
        init() {
            // Initialize a Sortable instance per location group, with
            // cross-group dragging enabled so admin can drag an item
            // FROM "navbar" group INTO "footer" group to relocate it.
            // The PHP reorder endpoint accepts the new ids in order
            // and recomputes sort_order + new location atomically.
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const groupName = 'navMenuItems';

            document.querySelectorAll('.sortable').forEach(list => {
                new Sortable(list, {
                    group: groupName,
                    animation: 150,
                    handle: '.cursor-move',
                    onEnd: (evt) => {
                        const fromList = evt.from.closest('.sortable');
                        const toList   = evt.to.closest('.sortable');
                        if (!toList) return;

                        // Build the new id order for the target list.
                        const ids = Array.from(toList.querySelectorAll('.sortable-item'))
                                         .map(el => parseInt(el.dataset.id, 10));
                        const location = toList.dataset.location;

                        fetch('{{ route("admin.navigation.reorder") }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ location, ids }),
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (!data.success) throw new Error('reorder failed');
                            // Soft visual confirmation — flash the moved row.
                            evt.item.classList.add('ring-2', 'ring-emerald-400');
                            setTimeout(() => evt.item.classList.remove('ring-2', 'ring-emerald-400'), 600);
                        })
                        .catch(err => {
                            console.error(err);
                            alert('บันทึกการเรียงลำดับล้มเหลว — รีเฟรชหน้าเพื่อ undo');
                        });
                    },
                });
            });
        },
    };
}
</script>
@endpush
@endsection
