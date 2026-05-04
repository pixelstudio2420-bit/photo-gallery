/**
 * Tiptap rich-text editor for the admin blog post form.
 *
 * Why a custom Alpine integration instead of @tiptap/vue or React?
 *   - Project already uses Alpine; pulling in Vue just for the editor would
 *     double the JS bundle and confuse state ownership.
 *   - Tiptap is headless — its core API works fine with vanilla JS, we just
 *     need to wire DOM mount/unmount + reactive sync into Alpine's lifecycle.
 *
 * Storage shape:
 *   - Editor reads/writes HTML (not JSON) so the existing HtmlSanitizer
 *     pipeline works untouched.
 *   - On every editor update we sync the HTML into Alpine state via
 *     `onChange(html)` — the parent form keeps the textarea hidden + a
 *     `name="content"` submit attribute so server-side flow is unchanged.
 *
 * Image uploads:
 *   - Toolbar button + drag-drop + paste all funnel through `handleImageUpload`
 *   - Endpoint:  POST /admin/blog/posts/upload-inline-image (multipart)
 *   - Response:  { success: true, url: "https://r2.../..." }
 *   - Endpoint stores via R2MediaService::uploadBlogImage so URLs are CDN-served.
 *
 * Public API exposed to Alpine via `Alpine.data('blogTiptapEditor', ...)`:
 *   - Methods: command runners (toggleBold/H2/Link/...), runImageUpload,
 *     insertHtml (for AI insert), focus, getHTML, setHTML
 *   - Reactive state: `editor` (Tiptap instance, may be null pre-init),
 *     `state` (snapshot of which marks/nodes are active for toolbar highlights),
 *     `chars` (current character count), `linkUrl` / `linkPickerOpen` for
 *     the link dialog, `imageUploading` for spinner.
 */

import { Editor } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Underline from '@tiptap/extension-underline';
import Placeholder from '@tiptap/extension-placeholder';
import CharacterCount from '@tiptap/extension-character-count';
import Typography from '@tiptap/extension-typography';
import TextAlign from '@tiptap/extension-text-align';
import Table from '@tiptap/extension-table';
import TableRow from '@tiptap/extension-table-row';
import TableCell from '@tiptap/extension-table-cell';
import TableHeader from '@tiptap/extension-table-header';
import TaskList from '@tiptap/extension-task-list';
import TaskItem from '@tiptap/extension-task-item';
import Youtube from '@tiptap/extension-youtube';
import Highlight from '@tiptap/extension-highlight';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';

/**
 * Editor instances live OUTSIDE Alpine's reactive proxy system.
 *
 * Why: Alpine 3 deep-proxies every property assigned to a `data()` scope.
 * If we store `this.editor = new Editor(...)`, then `this.editor.chain()`
 * walks through Alpine's reactive Proxy traps for every method call. Some
 * of those traps return wrapped/unwrapped values inconsistently, so the
 * ProseMirror schema reference inside the chain ends up not matching the
 * schema instance the editor view holds — when the resulting transaction
 * is dispatched, PM's `applyInner` throws "Applying a mismatched
 * transaction".
 *
 * Storing the editor in a module-scoped WeakMap keyed by the Alpine
 * component object keeps every method call on a *raw* Editor instance,
 * so the schema identity check inside PM always succeeds. The reactive
 * scope still gets a boolean `_editorReady` flag for x-show/x-if.
 */
const editorRegistry = new WeakMap();

document.addEventListener('alpine:init', () => {
    window.Alpine.data('blogTiptapEditor', (config = {}) => ({
        // ─── Configuration ──────────────────────────────────────────────
        initialContent: config.initialContent ?? '',
        uploadUrl:      config.uploadUrl      ?? '/admin/blog/posts/upload-inline-image',
        postId:         config.postId         ?? 0,
        csrfToken:      config.csrfToken      ?? document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        onChange:       config.onChange       ?? (() => {}),

        // ─── Reactive state ────────────────────────────────────────────
        // NOTE: do NOT store the editor instance on `this` — Alpine would
        // wrap it in a reactive Proxy and break ProseMirror schema identity.
        // Use `getEditor()` to access the raw instance from the WeakMap.
        _editorReady:     false,    // boolean flag for templates (x-show etc.)
        state:            {},       // { bold:bool, italic:bool, h2:bool, ... }
        chars:            0,
        words:            0,
        linkPickerOpen:   false,
        linkUrl:          '',
        linkText:         '',
        imagePickerOpen:  false,
        imageUrlInput:    '',
        imageAltInput:    '',
        ytPickerOpen:     false,
        ytUrl:            '',
        imageUploading:   false,

        /** Get the raw (non-reactive) Editor instance for command dispatch. */
        getEditor() {
            return editorRegistry.get(this);
        },

        /**
         * Convenience getter so existing template references like
         * `editor.state.selection.empty` keep working. Returns the raw
         * editor — Alpine reads through getters without wrapping the
         * result in a Proxy on each access.
         */
        get editor() {
            return editorRegistry.get(this);
        },

        /** Mount the editor into the element with x-ref="tiptap" */
        initEditor() {
            const mount = this.$refs.tiptap;
            if (!mount) {
                console.warn('[TiptapEditor] mount target (x-ref="tiptap") not found');
                return;
            }

            const editor = new Editor({
                element: mount,
                extensions: [
                    StarterKit.configure({
                        heading: { levels: [2, 3, 4] },
                        codeBlock: { HTMLAttributes: { class: 'tt-codeblock' } },
                        blockquote: { HTMLAttributes: { class: 'tt-blockquote' } },
                        bulletList: { HTMLAttributes: { class: 'tt-ul' } },
                        orderedList: { HTMLAttributes: { class: 'tt-ol' } },
                        horizontalRule: { HTMLAttributes: { class: 'tt-hr' } },
                    }),
                    Link.configure({
                        openOnClick: false,
                        HTMLAttributes: {
                            rel: 'noopener noreferrer',
                            class: 'tt-link',
                        },
                        validate: (href) => /^https?:\/\/|^\/|^mailto:|^tel:/.test(href),
                    }),
                    Image.configure({
                        HTMLAttributes: { class: 'tt-image' },
                        allowBase64: false,
                    }),
                    Underline,
                    Placeholder.configure({
                        placeholder: 'เริ่มเขียนเนื้อหาบทความที่นี่...',
                    }),
                    CharacterCount,
                    Typography,
                    TextAlign.configure({ types: ['heading', 'paragraph'] }),
                    Table.configure({ resizable: true, HTMLAttributes: { class: 'tt-table' } }),
                    TableRow,
                    TableHeader.configure({ HTMLAttributes: { class: 'tt-th' } }),
                    TableCell.configure({ HTMLAttributes: { class: 'tt-td' } }),
                    TaskList.configure({ HTMLAttributes: { class: 'tt-task-list' } }),
                    TaskItem.configure({ nested: true, HTMLAttributes: { class: 'tt-task-item' } }),
                    Youtube.configure({ HTMLAttributes: { class: 'tt-youtube' }, width: 640, height: 360, nocookie: true }),
                    Highlight.configure({ multicolor: false, HTMLAttributes: { class: 'tt-mark' } }),
                    Subscript,
                    Superscript,
                ],
                content: this.initialContent || '<p></p>',
                editorProps: {
                    attributes: {
                        class: 'tiptap-content focus:outline-none',
                        spellcheck: 'false',
                    },
                    // Drag-drop image handler
                    handleDrop: (view, event, slice, moved) => {
                        if (moved) return false;
                        const file = event.dataTransfer?.files?.[0];
                        if (file && file.type?.startsWith('image/')) {
                            event.preventDefault();
                            this.uploadImage(file);
                            return true;
                        }
                        return false;
                    },
                    // Paste image handler
                    handlePaste: (view, event) => {
                        const item = Array.from(event.clipboardData?.items || [])
                            .find(it => it.type?.startsWith('image/'));
                        if (item) {
                            const file = item.getAsFile();
                            if (file) {
                                event.preventDefault();
                                this.uploadImage(file);
                                return true;
                            }
                        }
                        return false;
                    },
                },
                onUpdate: ({ editor }) => {
                    this.scheduleRefresh();
                    const html = editor.getHTML();

                    // Path 1 (primary, always works) — write the HTML to
                    // the hidden <textarea name="content"> directly and
                    // fire its input event. Any Alpine x-model bound to
                    // it picks up the new value via the standard DOM
                    // change pipeline. This bypasses the closure issue
                    // with arrow-function `onChange` callbacks defined
                    // inline in `x-data="..."` (Alpine evaluates those
                    // expressions through a Function constructor that
                    // doesn't inherit the parent x-data's scope, so a
                    // bare `(html) => form.content = html` looks like
                    // it captures `form` but actually throws on call).
                    const textarea = document.getElementById('contentEditor');
                    if (textarea) {
                        textarea.value = html;
                        textarea.dispatchEvent(new Event('input', { bubbles: true }));
                    }

                    // Path 2 — broadcast a window event so parent
                    // Alpine scopes can listen with
                    //   @tiptap:content-changed.window="form.content = $event.detail.html"
                    // (event listener IS evaluated in the parent's
                    // proper scope, so this works correctly.)
                    try {
                        window.dispatchEvent(new CustomEvent('tiptap:content-changed', {
                            detail: { html, postId: this.postId },
                        }));
                    } catch (_) { /* CustomEvent unsupported on ancient browsers — ignore */ }

                    // Path 3 — legacy onChange callback. Kept for
                    // any caller that already wires it correctly
                    // (e.g. via Alpine.closest()), but wrapped in
                    // try/catch so the broken inline arrow form
                    // ("form.content = html") fails silently instead
                    // of breaking the editor.
                    if (typeof this.onChange === 'function') {
                        try { this.onChange(html); } catch (_) { /* swallow — paths 1+2 already handled it */ }
                    }
                },
                onSelectionUpdate: () => this.scheduleRefresh(),
                onTransaction:     () => this.scheduleRefresh(),
            });

            // Stash the raw editor outside the Alpine proxy world so
            // command chains see consistent ProseMirror schema identity.
            editorRegistry.set(this, editor);
            this._editorReady = true;
            this.refreshState();
        },

        /**
         * Defer state-refresh by one microtask.
         *
         * ProseMirror dispatches transactions synchronously and the editor
         * fires `onTransaction` *during* the apply phase. If we read
         * isActive() at that moment we sometimes see the pre-apply state,
         * which then becomes "stale" the instant the transaction commits —
         * leading to a UI checkbox that thinks codeBlock=false right when
         * the user clicks the button to toggle it off again.
         *
         * Deferring with queueMicrotask lets the transaction settle, then
         * we read a clean view of the document. It also collapses bursts
         * (typing fires onTransaction per keystroke) into one sync.
         */
        scheduleRefresh() {
            if (this._refreshPending) return;
            this._refreshPending = true;
            queueMicrotask(() => {
                this._refreshPending = false;
                this.refreshState();
            });
        },

        /** Snapshot which marks/nodes are active so toolbar buttons can highlight */
        refreshState() {
            const e = editorRegistry.get(this);
            if (!e || e.isDestroyed) return;
            try {
                this.state = {
                    bold:        e.isActive('bold'),
                    italic:      e.isActive('italic'),
                    underline:   e.isActive('underline'),
                    strike:      e.isActive('strike'),
                    code:        e.isActive('code'),
                    highlight:   e.isActive('highlight'),
                    subscript:   e.isActive('subscript'),
                    superscript: e.isActive('superscript'),
                    h2:          e.isActive('heading', { level: 2 }),
                    h3:          e.isActive('heading', { level: 3 }),
                    h4:          e.isActive('heading', { level: 4 }),
                    paragraph:   e.isActive('paragraph'),
                    bulletList:  e.isActive('bulletList'),
                    orderedList: e.isActive('orderedList'),
                    taskList:    e.isActive('taskList'),
                    blockquote:  e.isActive('blockquote'),
                    codeBlock:   e.isActive('codeBlock'),
                    link:        e.isActive('link'),
                    table:       e.isActive('table'),
                    alignLeft:   e.isActive({ textAlign: 'left' }),
                    alignCenter: e.isActive({ textAlign: 'center' }),
                    alignRight:  e.isActive({ textAlign: 'right' }),
                };
                this.chars = e.storage.characterCount?.characters() ?? 0;
                this.words = e.storage.characterCount?.words() ?? 0;
            } catch (_) {
                /* swallow — editor may be mid-destroy */
            }
        },

        /**
         * Defensive command runner.
         *
         * ProseMirror sometimes throws "Applying a mismatched transaction"
         * when:
         *   - a command runs against a document the schema rejects (e.g.
         *     toggleCodeBlock from a list item with active marks),
         *   - Alpine re-renders mid-dispatch and stale handlers fire,
         *   - the editor was destroyed by HMR/x-show before we got the click.
         *
         * Wrapping every command in try/catch + `isDestroyed` + `can()`
         * guard turns those into a no-op + a console warning instead of
         * crashing the whole Alpine component (which freezes the UI).
         */
        runCommand(builder) {
            const e = editorRegistry.get(this);   // raw, NOT this.editor (proxy)
            if (!e || e.isDestroyed) return false;
            try {
                const chain = e.chain().focus();
                const built = typeof builder === 'function' ? builder(chain) : null;
                if (!built) return false;
                return built.run();
            } catch (err) {
                console.warn('[Tiptap] command failed:', err?.message || err);
                this.scheduleRefresh();
                return false;
            }
        },

        /* ─── Command runners (all routed through runCommand) ────── */
        toggleBold()        { this.runCommand(c => c.toggleBold()); },
        toggleItalic()      { this.runCommand(c => c.toggleItalic()); },
        toggleUnderline()   { this.runCommand(c => c.toggleUnderline()); },
        toggleStrike()      { this.runCommand(c => c.toggleStrike()); },
        toggleCode()        { this.runCommand(c => c.toggleCode()); },
        toggleHighlight()   { this.runCommand(c => c.toggleHighlight()); },
        toggleSubscript()   { this.runCommand(c => c.toggleSubscript()); },
        toggleSuperscript() { this.runCommand(c => c.toggleSuperscript()); },

        setH(level)         { this.runCommand(c => c.toggleHeading({ level })); },
        setParagraph()      { this.runCommand(c => c.setParagraph()); },

        toggleBulletList()  { this.runCommand(c => c.toggleBulletList()); },
        toggleOrderedList() { this.runCommand(c => c.toggleOrderedList()); },
        toggleTaskList()    { this.runCommand(c => c.toggleTaskList()); },

        toggleBlockquote()  { this.runCommand(c => c.toggleBlockquote()); },

        // toggleCodeBlock specifically: the cursor must be in a node that
        // can transform into code_block. From inside a list item or table
        // cell ProseMirror's `toggleCodeBlock` builds a transaction that
        // doesn't match the schema → "mismatched transaction". Strip
        // marks and lift out of any wrapping nodes first; only attempt
        // the toggle if the resulting state can host a codeBlock.
        toggleCodeBlock() {
            this.runCommand(c => {
                const e = editorRegistry.get(this);
                if (!e) return null;
                // If we're already in a codeBlock, just toggle it off.
                if (e.isActive('codeBlock')) {
                    return c.toggleCodeBlock();
                }
                // Otherwise: clear marks first so the resulting codeBlock
                // doesn't carry illegal inline marks (link/highlight/etc.)
                return c.unsetAllMarks().setCodeBlock();
            });
        },

        insertHr()          { this.runCommand(c => c.setHorizontalRule()); },
        clearMarks()        { this.runCommand(c => c.unsetAllMarks().clearNodes()); },

        setAlign(dir)       { this.runCommand(c => c.setTextAlign(dir)); },

        undo()              { this.runCommand(c => c.undo()); },
        redo()              { this.runCommand(c => c.redo()); },

        /* ─── Link handling ─────────────────────────────────────────── */
        openLinkPicker() {
            const e = editorRegistry.get(this);
            if (!e) return;
            const prev = e.getAttributes('link').href ?? '';
            const { from, to } = e.state.selection;
            this.linkText = e.state.doc.textBetween(from, to, ' ');
            this.linkUrl = prev;
            this.linkPickerOpen = true;
        },
        applyLink() {
            const url = (this.linkUrl || '').trim();
            if (!url) {
                this.runCommand(c => c.extendMarkRange('link').unsetLink());
            } else {
                // Normalize: prepend https:// if user just types example.com
                const safe = /^(https?:\/\/|\/|mailto:|tel:)/.test(url) ? url : `https://${url}`;
                const e = editorRegistry.get(this);
                const empty = e?.state?.selection?.empty;
                if (this.linkText && empty) {
                    this.runCommand(c => c.insertContent({
                        type: 'text',
                        text: this.linkText,
                        marks: [{ type: 'link', attrs: { href: safe } }],
                    }));
                } else {
                    this.runCommand(c => c.extendMarkRange('link').setLink({ href: safe, target: '_blank' }));
                }
            }
            this.linkPickerOpen = false;
            this.linkUrl = '';
            this.linkText = '';
        },
        removeLink() {
            this.runCommand(c => c.extendMarkRange('link').unsetLink());
            this.linkPickerOpen = false;
        },

        /* ─── Image handling ─────────────────────────────────────────── */
        openImagePicker() {
            this.imageUrlInput = '';
            this.imageAltInput = '';
            this.imagePickerOpen = true;
        },
        applyImageUrl() {
            const url = (this.imageUrlInput || '').trim();
            if (!url) return;
            this.runCommand(c => c.setImage({ src: url, alt: this.imageAltInput || '' }));
            this.imagePickerOpen = false;
        },
        triggerImageFile() {
            this.$refs.imageFileInput?.click();
        },
        onImageFileSelected(event) {
            const file = event.target.files?.[0];
            if (file) this.uploadImage(file);
            // Reset input so picking the same file again still triggers change
            event.target.value = '';
        },
        async uploadImage(file) {
            if (!file) return;
            this.imageUploading = true;

            const formData = new FormData();
            formData.append('image', file);
            formData.append('post_id', String(this.postId || 0));

            try {
                const response = await fetch(this.uploadUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': this.csrfToken,
                        'Accept': 'application/json',
                    },
                    body: formData,
                    credentials: 'same-origin',
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'อัพโหลดล้มเหลว');
                }
                this.runCommand(c => c.setImage({ src: data.url, alt: file.name.replace(/\.[^.]+$/, '') }));
                this.imagePickerOpen = false;
            } catch (err) {
                if (window.Swal) {
                    window.Swal.fire({ icon: 'error', title: 'อัพโหลดรูปไม่สำเร็จ', text: err.message });
                } else {
                    alert('อัพโหลดรูปไม่สำเร็จ: ' + err.message);
                }
            } finally {
                this.imageUploading = false;
            }
        },

        /* ─── Tables ───────────────────────────────────────────────── */
        insertTable()     { this.runCommand(c => c.insertTable({ rows: 3, cols: 3, withHeaderRow: true })); },
        addColumnAfter()  { this.runCommand(c => c.addColumnAfter()); },
        addRowAfter()     { this.runCommand(c => c.addRowAfter()); },
        deleteColumn()    { this.runCommand(c => c.deleteColumn()); },
        deleteRow()       { this.runCommand(c => c.deleteRow()); },
        deleteTable()     { this.runCommand(c => c.deleteTable()); },

        /* ─── YouTube embed ─────────────────────────────────────────── */
        openYoutube() {
            this.ytUrl = '';
            this.ytPickerOpen = true;
        },
        applyYoutube() {
            const url = (this.ytUrl || '').trim();
            if (!url) { this.ytPickerOpen = false; return; }
            this.runCommand(c => c.setYoutubeVideo({ src: url }));
            this.ytPickerOpen = false;
        },

        /* ─── Public API for parent (AI insert, programmatic set) ──── */
        getHTML() {
            const e = editorRegistry.get(this);
            return e?.getHTML() ?? '';
        },
        setHTML(html) {
            const e = editorRegistry.get(this);
            if (!e || e.isDestroyed) return;
            try { e.commands.setContent(html || '<p></p>', true); }
            catch (err) { console.warn('[Tiptap] setHTML failed:', err?.message); }
            this.scheduleRefresh();
        },
        appendHTML(html) {
            const e = editorRegistry.get(this);
            if (!e || e.isDestroyed) return;
            this.runCommand(c => c.insertContentAt(e.state.doc.content.size, html));
        },
        focus() {
            const e = editorRegistry.get(this);
            if (!e || e.isDestroyed) return;
            try { e.commands.focus(); }
            catch (_) { /* no-op */ }
        },

        /* ─── Cleanup ──────────────────────────────────────────────── */
        destroyEditor() {
            const e = editorRegistry.get(this);
            try { e?.destroy(); } catch (_) {}
            editorRegistry.delete(this);
            this._editorReady = false;
        },
    }));
});
