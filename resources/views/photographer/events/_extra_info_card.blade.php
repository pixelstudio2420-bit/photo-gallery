{{--
    ┌──────────────────────────────────────────────────────────────────┐
    │ Extra Info Card — collapsible "เพิ่มข้อมูลอีเวนต์ให้ครบ" section │
    │                                                                  │
    │ Splits the rich event metadata into 4 visual groups so the form  │
    │ doesn't feel overwhelming on first glance:                       │
    │   1. Time + venue + organizer                                    │
    │   2. Type + expected attendees                                   │
    │   3. Highlights + tags                                           │
    │   4. Contact + links + logistics                                 │
    │                                                                  │
    │ Wrapped in <details> so the basic create flow stays a one-screen │
    │ form — photographers in a hurry click "สร้างอีเวนต์" without ever │
    │ opening it. Re-edit later when SEO tuning matters.               │
    │                                                                  │
    │ Used by:                                                         │
    │   resources/views/photographer/events/create.blade.php           │
    │   resources/views/photographer/events/edit.blade.php             │
    │                                                                  │
    │ Contract:                                                        │
    │   $event   — Event|null. When editing pass the model so the      │
    │              fields prefill; when creating leave null.           │
    │   $oldFn   — Closure(string $key, mixed $default) — defaults to  │
    │              `old()` but tests can swap it.                      │
    └──────────────────────────────────────────────────────────────────┘
--}}
@php
    $event   = $event ?? null;
    $oldFn   = $oldFn ?? fn ($k, $d = null) => old($k, $d);

    /** Helper: pull a value from old() → $event->$k → fallback. */
    $val = function (string $k, $default = null) use ($event, $oldFn) {
        return $oldFn($k, $event?->$k ?? $default);
    };

    /** Time helper — Postgres stores `time` as "HH:MM:SS" but the
     *  HTML5 <input type="time"> only displays "HH:MM" (or rejects
     *  the value silently in some browsers). Trim seconds before
     *  rendering. */
    $timeVal = function (string $k) use ($val) {
        $v = (string) ($val($k) ?? '');
        if ($v === '') return '';
        // Accept either "HH:MM" or "HH:MM:SS" → normalize to "HH:MM".
        return mb_substr($v, 0, 5);
    };

    /** highlights / tags ship as JSON arrays in the model — render them
     *  as plain newline / comma-separated strings for the textarea so
     *  photographers don't have to remember array syntax. */
    $highlights = $oldFn('highlights', $event?->highlights ?? []);
    $highlightsText = is_array($highlights)
        ? implode("\n", array_map('strval', $highlights))
        : (string) $highlights;

    $tags = $oldFn('tags', $event?->tags ?? []);
    $tagsText = is_array($tags)
        ? implode(', ', array_map('strval', $tags))
        : (string) $tags;

    $eventTypeOptions = \App\Models\Event::eventTypeOptions();

    // Cascading picker prefill — only the EDIT view passes in
    // $districts / $subdistricts (rows already keyed to the saved
    // province/district). On CREATE we fall back to an empty
    // collection so the Alpine component just shows the province
    // dropdown until the user picks one.
    $provinces       = $provinces       ?? collect();
    $prefDistricts   = $districts       ?? collect();
    $prefSubdistricts= $subdistricts    ?? collect();

    $oldProvinceId    = $oldFn('province_id',    $event?->province_id);
    $oldDistrictId    = $oldFn('district_id',    $event?->district_id);
    $oldSubdistrictId = $oldFn('subdistrict_id', $event?->subdistrict_id);
@endphp

<div class="md:col-span-2 mt-2">
    <details class="group rounded-xl border border-indigo-100 bg-gradient-to-br from-indigo-50/40 to-white open:shadow-sm transition" {{ ($event && ($event->venue_name || $event->organizer || $event->event_type || $event->province_id || $event->district_id || $event->subdistrict_id)) ? 'open' : '' }}>
        <summary class="cursor-pointer list-none px-4 py-3 flex items-center justify-between gap-3 select-none">
            <div class="flex items-center gap-2">
                <i class="bi bi-stars text-indigo-600"></i>
                <span class="font-semibold text-slate-800 text-sm">เพิ่มข้อมูลอีเวนต์ให้ครบ</span>
                <span class="hidden sm:inline-flex text-[11px] font-medium text-indigo-700 bg-indigo-100 px-2 py-0.5 rounded-full">
                    SEO ↑ · ดึงดูดลูกค้ามากขึ้น
                </span>
            </div>
            <i class="bi bi-chevron-down text-slate-400 transition group-open:rotate-180"></i>
        </summary>

        <div class="px-4 pb-4 pt-1 space-y-5">

            {{-- ── Group 0: Cascading location picker ───────────────────
                 Province → District (อำเภอ/เขต) → Subdistrict (ตำบล/แขวง)
                 backed by the Thai government reference tables. The two
                 child dropdowns are hydrated via fetch() against the
                 photographer.api.locations.* endpoints whenever the
                 parent select changes.

                 Why this lives in the extra-info card (collapsed by
                 default) instead of the basic form: the existing
                 free-text "สถานที่" field is what photographers reach
                 for first. The structured picker is for SEO + filtering
                 — nice to have, not required.
                 ────────────────────────────────────────────────────── --}}
            <div x-data="{
                    provinceId:    @js((string) ($oldProvinceId ?? '')),
                    districtId:    @js((string) ($oldDistrictId ?? '')),
                    subdistrictId: @js((string) ($oldSubdistrictId ?? '')),
                    districts:     {{ $prefDistricts->toJson() }},
                    subdistricts:  {{ $prefSubdistricts->toJson() }},
                    loadingD:      false,
                    loadingS:      false,
                    async fetchDistricts() {
                        // Reset child selections whenever the province
                        // flips — otherwise stale district/subdistrict
                        // IDs from the old province would post.
                        this.districts = []; this.subdistricts = [];
                        this.districtId = ''; this.subdistrictId = '';
                        if (!this.provinceId) return;
                        this.loadingD = true;
                        try {
                            const url = '{{ route('photographer.api.locations.districts') }}?province_id=' + encodeURIComponent(this.provinceId);
                            const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                            this.districts = res.ok ? await res.json() : [];
                        } catch (_) { this.districts = []; }
                        finally { this.loadingD = false; }
                    },
                    async fetchSubdistricts() {
                        this.subdistricts = []; this.subdistrictId = '';
                        if (!this.districtId) return;
                        this.loadingS = true;
                        try {
                            const url = '{{ route('photographer.api.locations.subdistricts') }}?district_id=' + encodeURIComponent(this.districtId);
                            const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                            this.subdistricts = res.ok ? await res.json() : [];
                        } catch (_) { this.subdistricts = []; }
                        finally { this.loadingS = false; }
                    }
                 }">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                    <i class="bi bi-geo-alt mr-1"></i>พื้นที่ (จังหวัด · อำเภอ · ตำบล)
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    {{-- จังหวัด --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">จังหวัด</label>
                        <select x-model="provinceId" @change="fetchDistricts()"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">— เลือก —</option>
                            @foreach($provinces as $p)
                                <option value="{{ $p->id }}">{{ $p->name_th }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- อำเภอ/เขต --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            อำเภอ/เขต
                            <span x-show="loadingD" class="text-indigo-500"><i class="bi bi-arrow-repeat animate-spin"></i></span>
                        </label>
                        <select x-model="districtId" @change="fetchSubdistricts()"
                                :disabled="!provinceId || loadingD"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-50 disabled:cursor-not-allowed">
                            <option value="">— เลือก —</option>
                            <template x-for="d in districts" :key="d.id">
                                <option :value="d.id" x-text="d.name_th" :selected="String(d.id) === String(districtId)"></option>
                            </template>
                        </select>
                    </div>

                    {{-- ตำบล/แขวง --}}
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            ตำบล/แขวง
                            <span x-show="loadingS" class="text-indigo-500"><i class="bi bi-arrow-repeat animate-spin"></i></span>
                        </label>
                        <select x-model="subdistrictId"
                                :disabled="!districtId || loadingS"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-50 disabled:cursor-not-allowed">
                            <option value="">— เลือก —</option>
                            <template x-for="s in subdistricts" :key="s.id">
                                <option :value="s.id"
                                        x-text="s.name_th + (s.zip_code ? ' (' + s.zip_code + ')' : '')"
                                        :selected="String(s.id) === String(subdistrictId)"></option>
                            </template>
                        </select>
                    </div>
                </div>

                {{-- Hidden inputs feed the form submit. The visible
                     <select x-model> values flow through Alpine; we
                     mirror them onto these <input>s so the controller
                     gets clean string IDs. --}}
                <input type="hidden" name="province_id"    :value="provinceId">
                <input type="hidden" name="district_id"    :value="districtId">
                <input type="hidden" name="subdistrict_id" :value="subdistrictId">

                {{-- Optional address detail (street, building, room) --}}
                <div class="mt-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        รายละเอียดที่อยู่เพิ่มเติม
                        <span class="text-slate-400 font-normal">— เช่น "ห้อง A2, ชั้น 3"</span>
                    </label>
                    <input type="text" name="location_detail" maxlength="500" value="{{ $val('location_detail') }}"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           placeholder="ที่อยู่ละเอียด / จุดสังเกต">
                </div>
            </div>

            {{-- ── Group 1: Time + Venue + Organizer ─────────────────── --}}
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                    <i class="bi bi-clock mr-1"></i>เวลา · สถานที่ · ผู้จัด
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">เวลาเริ่ม</label>
                        <input type="time" name="start_time" value="{{ $timeVal('start_time') }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">เวลาสิ้นสุด</label>
                        <input type="time" name="end_time" value="{{ $timeVal('end_time') }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            ชื่อสถานที่ (Venue)
                            <span class="text-slate-400 font-normal">— เช่น "Impact Arena", "หาดป่าตอง"</span>
                        </label>
                        <input type="text" name="venue_name" maxlength="200" value="{{ $val('venue_name') }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="ชื่อสถานที่หรืออาคาร">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            ผู้จัดงาน (Organizer)
                        </label>
                        <input type="text" name="organizer" maxlength="200" value="{{ $val('organizer') }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="บริษัท/ทีม/ผู้จัดงาน">
                    </div>
                </div>
            </div>

            {{-- ── Group 2: Type + Attendees ─────────────────────────── --}}
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                    <i class="bi bi-tags mr-1"></i>ประเภท · ขนาดงาน
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">ประเภทอีเวนต์</label>
                        <select name="event_type"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">— เลือก —</option>
                            @foreach($eventTypeOptions as $k => $label)
                                <option value="{{ $k }}" {{ $val('event_type') === $k ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            จำนวนผู้ร่วมงาน (โดยประมาณ)
                        </label>
                        <input type="number" name="expected_attendees" min="0" max="1000000"
                               value="{{ $val('expected_attendees') }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="เช่น 200">
                    </div>
                </div>
            </div>

            {{-- ── Group 3: Highlights + Tags ────────────────────────── --}}
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                    <i class="bi bi-lightbulb mr-1"></i>จุดเด่น · แท็ก
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            จุดเด่นของงาน
                            <span class="text-slate-400 font-normal">— บรรทัดละ 1 ข้อ</span>
                        </label>
                        <textarea name="highlights_text" rows="3"
                                  class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                  placeholder="ฟรีน้ำดื่ม&#10;รับเหรียญที่จุดเส้นชัย&#10;มีรูปกลุ่ม">{{ $highlightsText }}</textarea>
                        <p class="text-[11px] text-slate-400 mt-1">โชว์ให้ลูกค้าดูบนหน้าอีเวนต์ — ชวนกดเข้ามากขึ้น</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">
                            แท็กค้นหา
                            <span class="text-slate-400 font-normal">— คั่นด้วย ,</span>
                        </label>
                        <input type="text" name="tags_text" value="{{ $tagsText }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="มาราธอน, สวนลุมพินี, 10K, charity">
                        <p class="text-[11px] text-slate-400 mt-1">ใช้ค้นหาในระบบ — ไม่จำกัดจำนวน</p>
                    </div>
                </div>
            </div>

            {{-- ── Group 4: Contact + Links + Logistics ──────────────── --}}
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">
                    <i class="bi bi-telephone mr-1"></i>ติดต่อ · ลิงก์ · ที่จอดรถ/แต่งกาย
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">เบอร์โทรติดต่อ</label>
                        <input type="tel" name="contact_phone" maxlength="30" value="{{ $val('contact_phone') }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="08x-xxx-xxxx">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">อีเมลติดต่อ</label>
                        <input type="email" name="contact_email" maxlength="150" value="{{ $val('contact_email') }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="info@...">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">เว็บไซต์งาน (URL)</label>
                        <input type="url" name="website_url" maxlength="500" value="{{ $val('website_url') }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="https://...">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Facebook URL</label>
                        <input type="url" name="facebook_url" maxlength="500" value="{{ $val('facebook_url') }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="https://facebook.com/...">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">การแต่งกาย</label>
                        <input type="text" name="dress_code" maxlength="200" value="{{ $val('dress_code') }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="เช่น 'ชุดสุภาพ', 'ชุดวิ่ง'">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">ที่จอดรถ</label>
                        <input type="text" name="parking_info" maxlength="500" value="{{ $val('parking_info') }}"
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                               placeholder="เช่น 'มีที่จอดฟรี 200 คัน'">
                    </div>
                </div>
            </div>

            <p class="text-[11px] text-slate-500 border-t border-slate-100 pt-3">
                <i class="bi bi-info-circle"></i>
                ข้อมูลทั้งหมดเป็น <strong>ตัวเลือก</strong> — ยิ่งกรอกครบ Google ยิ่งจัดอันดับดีและลูกค้าค้นเจอง่ายขึ้น
            </p>
        </div>
    </details>
</div>
