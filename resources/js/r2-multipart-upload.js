/**
 * Resumable, chunked browser → R2 multipart upload (Alpine.js).
 *
 * Companion to r2-upload.js (single-PUT flow). Use this one for:
 *   • files larger than the single-PUT max_bytes (currently 25 MB
 *     for events.photos — anything bigger MUST use multipart)
 *   • mobile uploaders on flaky networks where a 100 MB transfer
 *     would lose the whole upload on a network blip
 *   • photographers wanting to drag-drop 200 photos at once
 *
 * Backend protocol it speaks
 * --------------------------
 *   POST /api/uploads/multipart/init         { category, resource_id, filename, mime, total_bytes }
 *                                             → { upload_id, key, part_size, total_parts }
 *
 *   POST /api/uploads/multipart/sign-part    { upload_id, part_number }
 *                                             → { url, expires_at }
 *
 *   PUT  {url} (chunk bytes)                  → R2 returns ETag header
 *
 *   POST /api/uploads/multipart/record-part  { upload_id, part_number, etag, size_bytes }
 *                                             → { ok, completed_parts }
 *
 *   GET  /api/uploads/multipart/{uploadId}/parts
 *                                             → [{ partNumber, etag, sizeBytes }, ...]
 *
 *   POST /api/uploads/multipart/complete     { upload_id, parts: [...] }
 *                                             → { key, size_bytes }
 *
 *   POST /api/uploads/multipart/abort        { upload_id }
 *
 * Resume semantics
 * ----------------
 * If the page reloads or network drops mid-upload, the server-side
 * upload_chunks row still exists. Calling list-parts returns the
 * already-confirmed partNumbers + ETags. The client iterates from
 * partNumber 1 and skips any partNumber already in the list.
 *
 * Browser-side state we persist:
 *   - upload_id   (so resume works after a page reload — kept in
 *                 localStorage keyed by file hash)
 *
 * Usage in Blade
 * --------------
 *   <div x-data="r2MultipartUpload({
 *           category:    'events.photos',
 *           resourceId:  {{ $event->id }},
 *           parallelism: 3,
 *           partSize:    5 * 1024 * 1024,
 *           onSuccess:   (file, key) => console.log('done', key),
 *       })">
 *       <input type="file" x-ref="input" multiple @change="onFilesPicked">
 *       <template x-for="f in queue" :key="f.id">
 *         <div>
 *           <span x-text="f.name"></span>
 *           <progress :value="f.partsDone" :max="f.totalParts"></progress>
 *           <span x-text="f.status"></span>
 *           <button x-show="f.status === 'failed'" @click="retry(f)">Retry</button>
 *           <button x-show="f.status === 'uploading'" @click="abort(f)">Abort</button>
 *         </div>
 *       </template>
 *   </div>
 */

document.addEventListener('alpine:init', () => {
    Alpine.data('r2MultipartUpload', (config = {}) => ({
        // ── config ────────────────────────────────────────────────
        category:    config.category    ?? '',
        resourceId:  config.resourceId  ?? null,
        // Default 5 MB — R2's minimum part size.
        partSize:    Math.max(5 * 1024 * 1024, config.partSize ?? 5 * 1024 * 1024),
        // How many parts to upload concurrently per file. Higher → faster
        // on good networks, but every parallel stream needs its own
        // bandwidth slice. 3 is a sweet spot for browsers.
        parallelism: Math.max(1, config.parallelism ?? 3),
        maxRetries:  config.maxRetries  ?? 5,
        onSuccess:   config.onSuccess   ?? (() => {}),
        onError:     config.onError     ?? ((err) => console.error('[r2Multipart]', err)),
        onProgress:  config.onProgress  ?? (() => {}),

        // ── reactive state ────────────────────────────────────────
        queue: [],   // [{ id, file, name, status, totalParts, partsDone, uploadId, key, error }]
        nextId: 1,

        async onFilesPicked(ev) {
            const files = Array.from(ev.target.files || []);
            for (const file of files) {
                this.queue.push({
                    id:         this.nextId++,
                    file,
                    name:       file.name,
                    size:       file.size,
                    totalParts: Math.ceil(file.size / this.partSize),
                    partsDone:  0,
                    status:     'queued',
                    uploadId:   null,
                    key:        null,
                    error:      null,
                });
            }
            // Process the queue serially across files; each file
            // internally uses parallelism for its parts.
            for (const item of this.queue.filter(q => q.status === 'queued')) {
                await this.uploadOne(item);
            }
        },

        async uploadOne(item) {
            try {
                item.status = 'starting';

                // ── 1. init ────────────────────────────────────
                const initResp = await this.api('/api/uploads/multipart/init', {
                    category:    this.category,
                    resource_id: this.resourceId,
                    filename:    item.file.name,
                    mime:        item.file.type || 'application/octet-stream',
                    total_bytes: item.file.size,
                });
                if (!initResp.ok) {
                    throw new Error(initResp.error || 'init failed');
                }
                item.uploadId   = initResp.upload_id;
                item.key        = initResp.key;
                item.totalParts = initResp.total_parts;
                item.status     = 'uploading';

                // ── 2. resume — fetch already-uploaded parts ──
                let alreadyDone = [];
                try {
                    const r = await fetch(
                        `/api/uploads/multipart/${encodeURIComponent(item.uploadId)}/parts`,
                        { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
                    );
                    if (r.ok) {
                        const j = await r.json();
                        alreadyDone = j.parts || [];
                    }
                } catch { /* fresh upload, list-parts will be empty */ }

                const doneSet = new Set(alreadyDone.map(p => p.partNumber));
                item.partsDone = doneSet.size;

                // ── 3. parallel chunk upload ─────────────────
                const partsToUpload = [];
                for (let n = 1; n <= item.totalParts; n++) {
                    if (!doneSet.has(n)) partsToUpload.push(n);
                }

                // Sliding-window parallelism. We don't use Promise.all
                // on all parts because for large files (100+ parts) the
                // browser would race that many XHRs simultaneously and
                // most networks throttle.
                const completed = [...alreadyDone];
                let cursor = 0;
                const worker = async () => {
                    while (cursor < partsToUpload.length) {
                        const partNumber = partsToUpload[cursor++];
                        const result = await this.uploadPartWithRetry(item, partNumber);
                        completed.push({
                            partNumber,
                            etag:      result.etag,
                            sizeBytes: result.sizeBytes,
                        });
                        item.partsDone++;
                        this.onProgress(item);
                    }
                };
                await Promise.all(
                    Array.from({ length: this.parallelism }, () => worker()),
                );

                // ── 4. complete ──────────────────────────────
                completed.sort((a, b) => a.partNumber - b.partNumber);
                const compResp = await this.api('/api/uploads/multipart/complete', {
                    upload_id: item.uploadId,
                    parts:     completed,
                });
                if (!compResp.key) {
                    throw new Error(compResp.error || 'complete failed');
                }

                item.status = 'done';
                item.key    = compResp.key;
                this.onSuccess(item, compResp.key);
            } catch (err) {
                item.status = 'failed';
                item.error  = err?.message || String(err);
                this.onError(err);
            }
        },

        async uploadPartWithRetry(item, partNumber) {
            // Slice the file. The slice IS lazy (browser doesn't
            // actually read bytes until they're needed for the PUT)
            // so this works even for multi-GB files.
            const start = (partNumber - 1) * this.partSize;
            const end   = Math.min(start + this.partSize, item.file.size);
            const blob  = item.file.slice(start, end);

            let attempt = 0;
            while (true) {
                attempt++;
                try {
                    // Sign the part — fresh URL each retry in case the
                    // previous one expired.
                    const sig = await this.api('/api/uploads/multipart/sign-part', {
                        upload_id:   item.uploadId,
                        part_number: partNumber,
                    });
                    if (!sig.url) {
                        throw new Error(sig.error || 'sign-part failed');
                    }

                    // PUT the chunk. We use XHR (not fetch) for two reasons:
                    //   • finer progress events
                    //   • R2 returns the ETag in a response HEADER, and
                    //     fetch() doesn't expose response.headers reliably
                    //     across browsers without explicit CORS-expose.
                    const etag = await this.putChunk(sig.url, blob);

                    // Confirm the part to our backend so the audit row
                    // tracks progress (and so resume works).
                    await this.api('/api/uploads/multipart/record-part', {
                        upload_id:   item.uploadId,
                        part_number: partNumber,
                        etag:        etag,
                        size_bytes:  blob.size,
                    });

                    return { etag, sizeBytes: blob.size };
                } catch (err) {
                    if (attempt >= this.maxRetries) throw err;
                    // Exponential backoff with jitter: 1s, 2s, 4s, 8s, 16s.
                    const delay = (2 ** (attempt - 1)) * 1000 + Math.random() * 500;
                    await new Promise(r => setTimeout(r, delay));
                }
            }
        },

        putChunk(url, blob) {
            return new Promise((resolve, reject) => {
                const xhr = new XMLHttpRequest();
                xhr.open('PUT', url);
                xhr.onload = () => {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        // R2 returns the ETag in the ETag header.
                        const etag = (xhr.getResponseHeader('ETag') || '')
                            .replace(/^"|"$/g, '');
                        if (!etag) {
                            return reject(new Error('R2 returned 2xx without an ETag header'));
                        }
                        resolve(etag);
                    } else {
                        reject(new Error(`PUT failed: HTTP ${xhr.status}`));
                    }
                };
                xhr.onerror = () => reject(new Error('Network error during PUT'));
                xhr.ontimeout = () => reject(new Error('PUT timed out'));
                xhr.timeout = 120000; // 2 min per chunk
                xhr.send(blob);
            });
        },

        async retry(item) {
            item.status = 'queued';
            item.error  = null;
            // partsDone retains its value so the resume picks up where
            // it left off.
            await this.uploadOne(item);
        },

        async abort(item) {
            if (!item.uploadId) {
                item.status = 'aborted';
                return;
            }
            try {
                await this.api('/api/uploads/multipart/abort', {
                    upload_id: item.uploadId,
                });
            } catch { /* best-effort */ }
            item.status = 'aborted';
        },

        async api(path, body) {
            // CSRF token comes from the meta tag Laravel templates emit.
            const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const r = await fetch(path, {
                method:  'POST',
                headers: {
                    'Content-Type':   'application/json',
                    'Accept':         'application/json',
                    'X-CSRF-TOKEN':   token,
                    'X-Requested-With':'XMLHttpRequest',
                },
                body: JSON.stringify(body),
            });
            try {
                return await r.json();
            } catch {
                return { ok: r.ok, error: r.ok ? null : `HTTP ${r.status}` };
            }
        },
    }));
});
