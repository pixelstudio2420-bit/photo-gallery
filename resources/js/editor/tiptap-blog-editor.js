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

document.addEventListener('alpine:init', () => {
    window.Alpine.data('blogTiptapEditor', (config = {}) => ({
        // ─── Configuration ──────────────────────────────────────────────
        initialContent: config.initialContent ?? '',
        uploadUrl:      config.uploadUrl      ?? '/admin/blog/posts/upload-inline-image',
        postId:         config.postId         ?? 0,
        csrfToken:      config.csrfToken      ?? document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        onChange:       config.onChange       ?? (() => {}),

        // ─── Reactive state ────────────────────────────────────────────
        editor:           null,
        state:            {},      // { bold:bool, italic:bool, h2:bool, ... }
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

        /** Mount the editor into the element with x-ref="tiptap" */
        initEditor() {
            const mount = this.$refs.tiptap;
            if (!mount) {
                console.warn('[TiptapEditor] mount target (x-ref="tiptap") not found');
                return;
            }

            this.editor = new Editor({
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
                    this.refreshState();
                    this.onChange(editor.getHTML());
                },
                onSelectionUpdate: () => this.refreshState(),
                onTransaction: () => this.refreshState(),
            });

            this.refreshState();
        },

        /** Snapshot which marks/nodes are active so toolbar buttons can highlight */
        refreshState() {
            if (!this.editor) return;
            const e = this.editor;
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
        },

        /* ─── Command runners ──────────────────────────────────────── */
        toggleBold()        { this.editor?.chain().focus().toggleBold().run(); },
        toggleItalic()      { this.editor?.chain().focus().toggleItalic().run(); },
        toggleUnderline()   { this.editor?.chain().focus().toggleUnderline().run(); },
        toggleStrike()      { this.editor?.chain().focus().toggleStrike().run(); },
        toggleCode()        { this.editor?.chain().focus().toggleCode().run(); },
        toggleHighlight()   { this.editor?.chain().focus().toggleHighlight().run(); },
        toggleSubscript()   { this.editor?.chain().focus().toggleSubscript().run(); },
        toggleSuperscript() { this.editor?.chain().focus().toggleSuperscript().run(); },

        setH(level)         { this.editor?.chain().focus().toggleHeading({ level }).run(); },
        setParagraph()      { this.editor?.chain().focus().setParagraph().run(); },

        toggleBulletList()  { this.editor?.chain().focus().toggleBulletList().run(); },
        toggleOrderedList() { this.editor?.chain().focus().toggleOrderedList().run(); },
        toggleTaskList()    { this.editor?.chain().focus().toggleTaskList().run(); },

        toggleBlockquote()  { this.editor?.chain().focus().toggleBlockquote().run(); },
        toggleCodeBlock()   { this.editor?.chain().focus().toggleCodeBlock().run(); },
        insertHr()          { this.editor?.chain().focus().setHorizontalRule().run(); },
        clearMarks()        { this.editor?.chain().focus().unsetAllMarks().clearNodes().run(); },

        setAlign(dir)       { this.editor?.chain().focus().setTextAlign(dir).run(); },

        undo()              { this.editor?.chain().focus().undo().run(); },
        redo()              { this.editor?.chain().focus().redo().run(); },

        /* ─── Link handling ─────────────────────────────────────────── */
        openLinkPicker() {
            if (!this.editor) return;
            const prev = this.editor.getAttributes('link').href ?? '';
            const { from, to } = this.editor.state.selection;
            this.linkText = this.editor.state.doc.textBetween(from, to, ' ');
            this.linkUrl = prev;
            this.linkPickerOpen = true;
        },
        applyLink() {
            const url = (this.linkUrl || '').trim();
            if (!url) {
                this.editor?.chain().focus().extendMarkRange('link').unsetLink().run();
            } else {
                // Normalize: prepend https:// if user just types example.com
                const safe = /^(https?:\/\/|\/|mailto:|tel:)/.test(url) ? url : `https://${url}`;
                if (this.linkText && this.editor.state.selection.empty) {
                    this.editor?.chain().focus().insertContent({
                        type: 'text',
                        text: this.linkText,
                        marks: [{ type: 'link', attrs: { href: safe } }],
                    }).run();
                } else {
                    this.editor?.chain().focus().extendMarkRange('link').setLink({ href: safe, target: '_blank' }).run();
                }
            }
            this.linkPickerOpen = false;
            this.linkUrl = '';
            this.linkText = '';
        },
        removeLink() {
            this.editor?.chain().focus().extendMarkRange('link').unsetLink().run();
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
            this.editor?.chain().focus().setImage({ src: url, alt: this.imageAltInput || '' }).run();
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
                this.editor?.chain().focus().setImage({ src: data.url, alt: file.name.replace(/\.[^.]+$/, '') }).run();
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
        insertTable() {
            this.editor?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
        },
        addColumnAfter()  { this.editor?.chain().focus().addColumnAfter().run(); },
        addRowAfter()     { this.editor?.chain().focus().addRowAfter().run(); },
        deleteColumn()    { this.editor?.chain().focus().deleteColumn().run(); },
        deleteRow()       { this.editor?.chain().focus().deleteRow().run(); },
        deleteTable()     { this.editor?.chain().focus().deleteTable().run(); },

        /* ─── YouTube embed ─────────────────────────────────────────── */
        openYoutube() {
            this.ytUrl = '';
            this.ytPickerOpen = true;
        },
        applyYoutube() {
            const url = (this.ytUrl || '').trim();
            if (!url) { this.ytPickerOpen = false; return; }
            this.editor?.chain().focus().setYoutubeVideo({ src: url }).run();
            this.ytPickerOpen = false;
        },

        /* ─── Public API for parent (AI insert, programmatic set) ──── */
        getHTML()      { return this.editor?.getHTML() ?? ''; },
        setHTML(html)  { this.editor?.commands.setContent(html || '<p></p>', true); this.refreshState(); },
        appendHTML(html) {
            if (!this.editor) return;
            this.editor.chain().focus('end').insertContent(html).run();
            this.refreshState();
        },
        focus()        { this.editor?.commands.focus(); },

        /* ─── Cleanup ──────────────────────────────────────────────── */
        destroyEditor() {
            this.editor?.destroy();
            this.editor = null;
        },
    }));
});
