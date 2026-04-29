/**
 * Direct browser → Cloudflare R2 upload (Alpine.js).
 *
 * Flow:
 *   1. POST /api/uploads/sign      → { url, key, expected_mime, max_bytes }
 *   2. PUT  url with the file      ← uploaded direct to R2, no app server
 *   3. POST /api/uploads/confirm   → server verifies object exists, app
 *                                    persists `key` against the resource
 *
 * Usage in Blade:
 *
 *   <div x-data="r2Upload({
 *       category:    'events.photos',
 *       resourceId:  {{ $event->id }},
 *       multiple:    true,
 *       onSuccess:   (file) => console.log('uploaded', file),
 *   })">
 *       <input type="file" multiple x-ref="input" @change="onFilesPicked"
 *              accept="image/jpeg,image/png,image/webp,image/heic">
 *
 *       <template x-for="f in queue" :key="f.id">
 *           <div>
 *               <span x-text="f.name"></span>
 *               <progress :value="f.progress" max="100"></progress>
 *               <span x-text="f.status"></span>
 *               <button x-show="f.status === 'failed'" @click="retry(f)">Retry</button>
 *           </div>
 *       </template>
 *   </div>
 *
 * Why is this in plain JS not a library?
 *   - We already ship Alpine.js — adding S3 client SDK would be ~120KB more.
 *   - The protocol is just a single PUT; XHR.upload.onprogress gives us
 *     better progress events than fetch() does, with retry on transient
 *     5xx without external dependencies.
 */

document.addEventListener('alpine:init', () => {
    Alpine.data('r2Upload', (config = {}) => ({
        // ─── Configuration (passed at component init) ───────────────────
        category:    config.category    ?? '',
        resourceId:  config.resourceId  ?? null,
        multiple:    config.multiple    ?? false,
        onSuccess:   config.onSuccess   ?? (() => {}),
        onError:     config.onError     ?? ((err) => console.error('[r2Upload]', err)),
        maxRetries:  config.maxRetries  ?? 3,

        // ─── Reactive state ─────────────────────────────────────────────
        queue:       [],   // { id, file, name, progress, status, key, error }
        isUploading: false,

        /** Triggered by the <input type="file" @change="onFilesPicked"> */
        onFilesPicked(e) {
            const files = Array.from(e.target.files || []);
            if (files.length === 0) return;
            const toAdd = this.multiple ? files : files.slice(0, 1);
            for (const f of toAdd) {
                this.queue.push({
                    id:       crypto.randomUUID(),
                    file:     f,
                    name:     f.name,
                    progress: 0,
                    status:   'queued',
                    key:      null,
                    error:    null,
                });
            }
            // Reset the native input so picking the same file twice still fires.
            e.target.value = '';
            this.processQueue();
        },

        /** Drain the queue 3-at-a-time so we don't saturate the user's uplink. */
        async processQueue() {
            if (this.isUploading) return;
            this.isUploading = true;
            try {
                const concurrency = 3;
                while (true) {
                    const next = this.queue.filter((f) => f.status === 'queued').slice(0, concurrency);
                    if (next.length === 0) break;
                    await Promise.all(next.map((f) => this.uploadOne(f)));
                }
            } finally {
                this.isUploading = false;
            }
        },

        async retry(file) {
            file.status   = 'queued';
            file.progress = 0;
            file.error    = null;
            this.processQueue();
        },

        /** Single-file pipeline: sign → PUT → confirm. */
        async uploadOne(item) {
            item.status = 'signing';

            // Step 1 — get presigned URL
            let signed;
            try {
                signed = await this.postJson('/api/uploads/sign', {
                    category:    this.category,
                    resource_id: this.resourceId,
                    filename:    item.file.name,
                    mime:        item.file.type || 'application/octet-stream',
                    size:        item.file.size,
                });
            } catch (e) {
                this.fail(item, 'sign-failed', e.message);
                return;
            }

            if (item.file.size > signed.max_bytes) {
                this.fail(item, 'too-large', `Max ${signed.max_bytes} bytes for this category`);
                return;
            }

            // Step 2 — PUT directly to R2 with retry on transient failure
            item.status = 'uploading';
            const ok = await this.putWithRetry(signed.url, item, signed.expected_mime);
            if (!ok) return;

            // Step 3 — confirm
            item.status = 'confirming';
            try {
                await this.postJson('/api/uploads/confirm', {
                    key:           signed.key,
                    category:      this.category,
                    resource_id:   this.resourceId,
                    original_name: item.file.name,
                    byte_size:     item.file.size,
                });
            } catch (e) {
                this.fail(item, 'confirm-failed', e.message);
                return;
            }

            item.key      = signed.key;
            item.status   = 'done';
            item.progress = 100;
            this.onSuccess({
                key:        signed.key,
                name:       item.file.name,
                size:       item.file.size,
                mime:       item.file.type,
                resourceId: this.resourceId,
            });
        },

        /**
         * PUT to R2 with progress + 1 retry on 5xx.
         *
         * R2 occasionally returns transient 503 / 502 under load — retry once
         * with a small backoff before surfacing a hard error to the user.
         */
        async putWithRetry(url, item, expectedMime) {
            for (let attempt = 0; attempt <= this.maxRetries; attempt++) {
                try {
                    await this.putXhr(url, item, expectedMime);
                    return true;
                } catch (err) {
                    const transient = err.status >= 500 && err.status <= 599;
                    if (!transient || attempt === this.maxRetries) {
                        this.fail(item, 'upload-failed', err.message || `HTTP ${err.status}`);
                        return false;
                    }
                    await new Promise((r) => setTimeout(r, 500 * (attempt + 1)));
                }
            }
            return false;
        },

        /**
         * XHR.PUT with progress callback. We use XHR (not fetch) because
         * fetch() does not yet expose request-body progress events
         * cross-browser (only response progress).
         */
        putXhr(url, item, expectedMime) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('PUT', url, true);
                xhr.setRequestHeader('Content-Type', expectedMime);

                xhr.upload.onprogress = (e) => {
                    if (e.lengthComputable) {
                        item.progress = Math.round((e.loaded / e.total) * 100);
                    }
                };
                xhr.onload  = () => xhr.status >= 200 && xhr.status < 300
                                ? resolve()
                                : reject({ status: xhr.status, message: xhr.statusText });
                xhr.onerror = () => reject({ status: 0, message: 'network error' });
                xhr.ontimeout = () => reject({ status: 0, message: 'timeout' });
                xhr.timeout   = 10 * 60 * 1000; // 10 min for very large files
                xhr.send(item.file);
            });
        },

        /**
         * App-server JSON helper. Pulls CSRF token from the meta tag — the
         * app's standard pattern. Throws on non-2xx with a useful message.
         */
        async postJson(url, payload) {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const res  = await fetch(url, {
                method:  'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept':       'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body:        JSON.stringify(payload),
            });
            const body = await res.json().catch(() => ({}));
            if (!res.ok) {
                const msg = body.error || body.message || `HTTP ${res.status}`;
                throw new Error(msg);
            }
            return body;
        },

        fail(item, status, msg) {
            item.status = 'failed';
            item.error  = msg;
            this.onError({ name: item.name, status, message: msg });
        },

        // ─── Convenience getters for templates ──────────────────────────
        get pending()  { return this.queue.filter((f) => ['queued', 'signing', 'uploading', 'confirming'].includes(f.status)); },
        get done()     { return this.queue.filter((f) => f.status === 'done'); },
        get failed()   { return this.queue.filter((f) => f.status === 'failed'); },
        get totalProgress() {
            if (this.queue.length === 0) return 0;
            const sum = this.queue.reduce((s, f) => s + f.progress, 0);
            return Math.round(sum / this.queue.length);
        },
    }));
});
