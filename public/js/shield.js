/**
 * SourceShield — Client-side Source Code Protection
 * ป้องกันการดูโค้ดฝั่ง Client (เป็น deterrent ไม่ใช่ 100% protection)
 */
const SourceShield = {
    config: {
        level: 'standard',
        disableRightClick: true,
        disableDevTools: true,
        disableViewSource: true,
        disableCopy: false,
        disableDrag: true,
        obfuscateHtml: false,
        consoleWarning: true,
    },

    init(options = {}) {
        Object.assign(this.config, options);

        if (this.config.disableRightClick) this.blockRightClick();
        if (this.config.disableDevTools) this.detectDevTools();
        if (this.config.disableViewSource) this.blockViewSource();
        if (this.config.disableCopy) this.blockCopy();
        if (this.config.disableDrag) this.blockDrag();
        if (this.config.consoleWarning) this.showConsoleWarning();
        if (this.config.obfuscateHtml) this.obfuscateSource();

        this.blockPrintScreen();
    },

    /* ===================================
       1. ป้องกัน Right Click
       =================================== */
    blockRightClick() {
        document.addEventListener('contextmenu', function(e) {
            // อนุญาตใน input/textarea
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            e.preventDefault();
            return false;
        });
    },

    /* ===================================
       2. ตรวจจับ DevTools
       =================================== */
    detectDevTools() {
        const threshold = 160;
        let devToolsOpen = false;

        // Method 1: ตรวจจากขนาดหน้าต่าง
        const checkSize = () => {
            const widthDiff = window.outerWidth - window.innerWidth > threshold;
            const heightDiff = window.outerHeight - window.innerHeight > threshold;
            if (widthDiff || heightDiff) {
                if (!devToolsOpen) {
                    devToolsOpen = true;
                    this.onDevToolsDetected();
                }
            } else {
                devToolsOpen = false;
            }
        };

        // Method 2: debugger statement (ใช้ในโหมด strict)
        if (this.config.level === 'strict') {
            const devToolsCheck = new Image();
            Object.defineProperty(devToolsCheck, 'id', {
                get: () => {
                    this.onDevToolsDetected();
                }
            });

            setInterval(() => {
                devToolsOpen = false;
                console.dir(devToolsCheck);
                console.clear();
            }, 2000);
        }

        setInterval(checkSize, 1500);
        window.addEventListener('resize', checkSize);
    },

    onDevToolsDetected() {
        // โหมด strict: redirect ออก
        if (this.config.level === 'strict') {
            document.body.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;background:#1a1a2e;color:#e94560"><div style="text-align:center"><h2>⚠️ Developer Tools Detected</h2><p style="color:#aaa">กรุณาปิด Developer Tools เพื่อใช้งานเว็บไซต์</p></div></div>';
        }
        // โหมด standard: แค่เตือน
    },

    /* ===================================
       3. ป้องกัน View Source (Ctrl+U, F12, Ctrl+Shift+I/J/C)
       =================================== */
    blockViewSource() {
        document.addEventListener('keydown', function(e) {
            // F12
            if (e.key === 'F12') { e.preventDefault(); return false; }

            // Ctrl+Shift+I (Inspect), Ctrl+Shift+J (Console), Ctrl+Shift+C (Pick)
            if (e.ctrlKey && e.shiftKey && ['I','J','C'].includes(e.key.toUpperCase())) {
                e.preventDefault(); return false;
            }

            // Ctrl+U (View Source)
            if (e.ctrlKey && e.key.toUpperCase() === 'U') {
                e.preventDefault(); return false;
            }

            // Ctrl+S (Save Page)
            if (e.ctrlKey && e.key.toUpperCase() === 'S') {
                e.preventDefault(); return false;
            }

            // Ctrl+P (Print - optional)
            // if (e.ctrlKey && e.key.toUpperCase() === 'P') {
            //     e.preventDefault(); return false;
            // }
        });
    },

    /* ===================================
       4. ป้องกัน Copy (optional)
       =================================== */
    blockCopy() {
        document.addEventListener('copy', function(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            e.preventDefault();
            return false;
        });

        document.addEventListener('cut', function(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            e.preventDefault();
            return false;
        });
    },

    /* ===================================
       5. ป้องกัน Drag รูปภาพ
       =================================== */
    blockDrag() {
        document.addEventListener('dragstart', function(e) {
            if (e.target.tagName === 'IMG') {
                e.preventDefault();
                return false;
            }
        });
    },

    /* ===================================
       6. ป้องกัน Print Screen
       =================================== */
    blockPrintScreen() {
        document.addEventListener('keyup', function(e) {
            if (e.key === 'PrintScreen') {
                navigator.clipboard.writeText('').catch(() => {});
            }
        });
    },

    /* ===================================
       7. Console Warning
       =================================== */
    showConsoleWarning() {
        const style1 = 'color:#e94560;font-size:2rem;font-weight:bold;text-shadow:1px 1px 2px rgba(0,0,0,0.3)';
        const style2 = 'color:#333;font-size:1rem';
        const style3 = 'color:#666;font-size:0.85rem';

        console.log('%c⚠️ หยุด!', style1);
        console.log('%cนี่คือฟีเจอร์ของเบราว์เซอร์สำหรับนักพัฒนา', style2);
        console.log('%cหากมีผู้แนะนำให้คุณ copy-paste สิ่งใดที่นี่ นั่นคือการหลอกลวง\nอาจทำให้บัญชีของคุณถูกแฮกได้', style3);
        console.log('%cSelf-XSS Warning: Do not paste anything here that you do not understand.', style3);
    },

    /* ===================================
       8. HTML Obfuscation (optional)
       =================================== */
    obfuscateSource() {
        // ลบ HTML comments
        const walker = document.createTreeWalker(document, NodeFilter.SHOW_COMMENT, null, false);
        const comments = [];
        while (walker.nextNode()) comments.push(walker.currentNode);
        comments.forEach(c => c.parentNode.removeChild(c));

        // ลบ data attributes ที่ไม่จำเป็น (ยกเว้น Bootstrap data-bs-*)
        document.querySelectorAll('*').forEach(el => {
            [...el.attributes].forEach(attr => {
                if (attr.name.startsWith('data-') && !attr.name.startsWith('data-bs-')) {
                    // เก็บ data attributes ที่จำเป็น
                    const keep = ['data-lang', 'data-id', 'data-page', 'data-url'];
                    if (!keep.includes(attr.name)) {
                        // el.removeAttribute(attr.name); // uncomment ถ้าต้องการ strict mode
                    }
                }
            });
        });
    }
};
