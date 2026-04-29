<?php $__env->startSection('title', 'คู่มือการใช้งาน'); ?>

<?php $__env->startPush('styles'); ?>
<style>
  /* Manual-specific styles */
  .manual-prose p { line-height: 1.8; }
  .manual-prose ul { line-height: 1.9; }
  .manual-prose ol { line-height: 1.9; }
  .manual-prose strong { color: #4338ca; }
  .dark .manual-prose strong { color: #a5b4fc; }

  /* Info / Warning / Tip boxes */
  .info-box { border-left: 4px solid #6366f1; background: rgba(99,102,241,0.05); }
  .dark .info-box { background: rgba(99,102,241,0.10); border-left-color: #818cf8; }
  .warning-box { border-left: 4px solid #f59e0b; background: rgba(245,158,11,0.08); }
  .dark .warning-box { background: rgba(245,158,11,0.10); border-left-color: #fbbf24; }
  .tip-box { border-left: 4px solid #10b981; background: rgba(16,185,129,0.06); }
  .dark .tip-box { background: rgba(16,185,129,0.10); border-left-color: #34d399; }
  .danger-box { border-left: 4px solid #ef4444; background: rgba(239,68,68,0.06); }
  .dark .danger-box { background: rgba(239,68,68,0.10); border-left-color: #f87171; }

  /* Code blocks */
  pre.manual-code {
    background: #0f172a;
    color: #e2e8f0;
    padding: 1rem;
    border-radius: 0.75rem;
    font-size: 0.85rem;
    overflow-x: auto;
    line-height: 1.6;
    border: 1px solid rgba(255,255,255,0.06);
  }
  .dark pre.manual-code { background: #020617; border-color: rgba(255,255,255,0.08); }
  code.manual-inline {
    background: #f1f5f9;
    color: #4f46e5;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.85em;
    font-family: ui-monospace, monospace;
  }
  .dark code.manual-inline { background: rgba(99,102,241,0.15); color: #a5b4fc; }

  /* Section anchors offset for sticky header */
  .manual-section { scroll-margin-top: 80px; }

  /* Step number circles */
  .step-number {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.85rem;
    background: linear-gradient(135deg,#6366f1,#4f46e5);
    color: white;
    flex-shrink: 0;
  }

  /* Screenshot placeholder */
  .screenshot-placeholder {
    background: repeating-linear-gradient(45deg, rgba(99,102,241,0.06), rgba(99,102,241,0.06) 8px, transparent 8px, transparent 16px);
    border: 2px dashed rgba(99,102,241,0.25);
  }
  .dark .screenshot-placeholder {
    background: repeating-linear-gradient(45deg, rgba(99,102,241,0.12), rgba(99,102,241,0.12) 8px, transparent 8px, transparent 16px);
    border-color: rgba(129,140,248,0.35);
  }

  /* Sidebar active highlight */
  .manual-toc-link.active {
    background: linear-gradient(135deg,rgba(99,102,241,0.14),rgba(99,102,241,0.08));
    color: #4f46e5;
    font-weight: 600;
    border-left-color: #6366f1;
  }
  .dark .manual-toc-link.active {
    background: rgba(99,102,241,0.18);
    color: #c7d2fe;
    border-left-color: #818cf8;
  }

  /* FAQ accordion */
  .faq-item[open] summary .faq-chev { transform: rotate(180deg); }
  .faq-chev { transition: transform 0.25s ease; }

  /* Print styles */
  @media print {
    .manual-sidebar, .manual-search-wrap, .manual-mobile-toggle, .admin-sidebar, header, .no-print { display: none !important; }
    .manual-main { margin-left: 0 !important; max-width: 100% !important; }
    .manual-section { break-inside: avoid; page-break-inside: avoid; }
    body { background: white !important; color: black !important; }
    .info-box, .warning-box, .tip-box, .danger-box { border: 1px solid #999; background: white !important; }
    pre.manual-code { background: #f5f5f5 !important; color: black !important; border: 1px solid #ddd; }
  }

  /* Smooth scroll */
  html { scroll-behavior: smooth; }

  /* Filter highlight */
  .manual-hit mark {
    background: #fde68a;
    color: #92400e;
    padding: 0 2px;
    border-radius: 3px;
  }
  .dark .manual-hit mark { background: rgba(251,191,36,0.35); color: #fde68a; }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div x-data="manualApp()" x-init="init()" class="relative">

  
  <div class="flex flex-wrap justify-between items-center gap-3 mb-6 no-print">
    <div>
      <h4 class="font-bold mb-1 tracking-tight text-slate-800 dark:text-gray-100 text-xl">
        <i class="bi bi-book mr-2 text-indigo-500 dark:text-indigo-400"></i>คู่มือการใช้งาน
      </h4>
      <p class="text-sm text-gray-500 dark:text-gray-400 mb-0">
        คำแนะนำการใช้งานระบบสำหรับลูกค้า ช่างภาพ และผู้ดูแลระบบ
      </p>
    </div>
    <div class="flex items-center gap-2">
      <button type="button" @click="window.print()"
              class="inline-flex items-center gap-1.5 px-3 py-2 text-sm rounded-lg border border-gray-200 dark:border-white/10 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition">
        <i class="bi bi-printer"></i> <span class="hidden sm:inline">พิมพ์</span>
      </button>
      <a href="#faq" class="inline-flex items-center gap-1.5 px-3 py-2 text-sm rounded-lg bg-indigo-50 dark:bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-100 dark:hover:bg-indigo-500/20 transition">
        <i class="bi bi-question-circle"></i> <span class="hidden sm:inline">FAQ</span>
      </a>
    </div>
  </div>

  
  <button type="button"
          @click="mobileTocOpen = !mobileTocOpen"
          class="manual-mobile-toggle lg:hidden w-full mb-4 flex items-center justify-between gap-2 px-4 py-3 rounded-xl bg-white dark:bg-slate-900 border border-gray-200 dark:border-white/10 text-slate-800 dark:text-gray-100 shadow-sm">
    <span class="flex items-center gap-2 text-sm font-semibold">
      <i class="bi bi-list-ul text-indigo-500 dark:text-indigo-400"></i>
      <span x-text="activeTitle || 'สารบัญ'"></span>
    </span>
    <i class="bi bi-chevron-down text-gray-400" :class="{'rotate-180': mobileTocOpen}" style="transition:transform .2s"></i>
  </button>

  <div class="flex gap-6 items-start">

    
    <aside class="manual-sidebar lg:block shrink-0 w-full lg:w-[280px]"
           :class="{ 'hidden': !mobileTocOpen && !isDesktop() }">
      <div class="lg:sticky lg:top-[80px] bg-white dark:bg-slate-900 rounded-2xl border border-gray-100 dark:border-white/10 shadow-sm overflow-hidden">

        
        <div class="manual-search-wrap p-3 border-b border-gray-100 dark:border-white/10 no-print">
          <div class="relative">
            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
            <input type="text"
                   x-model="searchQuery"
                   @input="filterSections()"
                   placeholder="ค้นหาในคู่มือ..."
                   class="w-full pl-9 pr-8 py-2 text-sm rounded-lg bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10 text-gray-700 dark:text-gray-200 placeholder-gray-400 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
            <button type="button" x-show="searchQuery" @click="searchQuery=''; filterSections()"
                    class="absolute right-2 top-1/2 -translate-y-1/2 w-6 h-6 flex items-center justify-center rounded-md text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5">
              <i class="bi bi-x text-sm"></i>
            </button>
          </div>
        </div>

        
        <nav class="p-2 max-h-[calc(100vh-180px)] overflow-y-auto" aria-label="สารบัญ">
          <template x-for="s in filteredSections" :key="s.id">
            <a :href="'#' + s.id"
               @click="mobileTocOpen = false; active = s.id"
               :class="{ 'active': active === s.id }"
               class="manual-toc-link flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-white/5 transition border-l-2 border-transparent mb-0.5">
              <i :class="'bi bi-' + s.icon" class="text-indigo-500 dark:text-indigo-400 shrink-0"></i>
              <span x-text="s.title"></span>
            </a>
          </template>

          <template x-if="filteredSections.length === 0">
            <div class="px-3 py-6 text-center text-sm text-gray-400 dark:text-gray-500">
              <i class="bi bi-search text-2xl block mb-1 opacity-50"></i>
              ไม่พบหัวข้อที่ตรงกัน
            </div>
          </template>
        </nav>

        
        <div class="border-t border-gray-100 dark:border-white/10 p-3 text-xs text-gray-500 dark:text-gray-400 no-print">
          <p class="mb-1"><i class="bi bi-info-circle mr-1"></i>ปรับปรุง: เมษายน 2026</p>
          <p class="mb-0"><i class="bi bi-shield-check mr-1"></i>เวอร์ชัน 1.0</p>
        </div>
      </div>
    </aside>

    
    <main class="manual-main flex-1 min-w-0">
      <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-gray-100 dark:border-white/10 p-5 sm:p-8 manual-prose">

        
        <div class="relative overflow-hidden rounded-2xl p-6 mb-8 no-print"
             style="background:linear-gradient(135deg,rgba(99,102,241,0.12),rgba(168,85,247,0.08));">
          <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shrink-0 shadow-lg shadow-indigo-500/30">
              <i class="bi bi-book-half text-2xl text-white"></i>
            </div>
            <div class="flex-1 min-w-0">
              <h3 class="font-bold text-lg mb-1 text-slate-800 dark:text-gray-100">ยินดีต้อนรับสู่คู่มือการใช้งาน</h3>
              <p class="text-sm text-gray-600 dark:text-gray-400 mb-0">
                คู่มือนี้ครอบคลุมทุกฟีเจอร์ของแพลตฟอร์ม ตั้งแต่การเริ่มต้นใช้งาน ระบบการค้นหาภาพด้วยใบหน้า ระบบชำระเงิน
                ไปจนถึงการตั้งค่าระดับผู้ดูแลระบบ เลือกหัวข้อจากเมนูด้านซ้ายเพื่อดูข้อมูลที่ต้องการ
              </p>
            </div>
          </div>
        </div>

        
        <section id="getting-started" class="manual-section" data-title="เริ่มต้นใช้งาน" data-keywords="เริ่มต้น getting started โปรไฟล์ line google สมัคร login">
          <h2 class="text-2xl font-bold text-slate-800 dark:text-gray-100 mb-3 flex items-center gap-2">
            <i class="bi bi-rocket-takeoff text-indigo-500 dark:text-indigo-400"></i>
            1. เริ่มต้นใช้งาน
          </h2>
          <p class="text-gray-600 dark:text-gray-400 mb-6">
            ส่วนนี้จะช่วยให้คุณเข้าใจภาพรวมของระบบและเริ่มต้นใช้งานได้อย่างรวดเร็ว ไม่ว่าคุณจะเป็นลูกค้า
            ช่างภาพ หรือผู้ดูแลระบบ
          </p>

          
          <div class="mb-8">
            <h3 id="system-overview" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-diagram-3 text-indigo-500 dark:text-indigo-400"></i>
              1.1 ภาพรวมระบบ
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              แพลตฟอร์มนี้เป็นระบบค้นหาและซื้อภาพถ่ายจากงานอีเวนต์ต่าง ๆ โดยมีผู้ใช้ 3 ประเภทหลัก:
            </p>
            <ul class="list-disc ml-6 text-gray-600 dark:text-gray-400 space-y-1">
              <li><strong>ลูกค้า (Customer)</strong> — เข้าค้นหาอีเวนต์ ใช้ Face Search และซื้อภาพ</li>
              <li><strong>ช่างภาพ (Photographer)</strong> — สร้างอีเวนต์ อัพโหลดภาพ และรับรายได้</li>
              <li><strong>ผู้ดูแลระบบ (Admin)</strong> — จัดการผู้ใช้ คำสั่งซื้อ การเงิน และ Blog</li>
            </ul>

            
            <div class="screenshot-placeholder rounded-xl p-10 text-center my-4">
              <i class="bi bi-image text-4xl text-indigo-400 dark:text-indigo-300 mb-2 block"></i>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-0">[ภาพประกอบ: แผนผังระบบโดยรวม]</p>
            </div>

            <div class="info-box rounded-r-xl p-4 my-4">
              <p class="text-sm text-slate-800 dark:text-gray-200 mb-0 flex items-start gap-2">
                <i class="bi bi-info-circle-fill text-indigo-500 dark:text-indigo-400 mt-0.5"></i>
                <span><strong>Tip:</strong> บัญชีลูกค้าสามารถยกระดับเป็นช่างภาพได้ในภายหลังจากเมนู
                "สมัครเป็นช่างภาพ" ที่โปรไฟล์ของคุณ</span>
              </p>
            </div>
          </div>

          
          <div class="mb-8">
            <h3 id="register-login" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-person-plus text-indigo-500 dark:text-indigo-400"></i>
              1.2 การสมัครสมาชิก / เข้าสู่ระบบ
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              ระบบรองรับการสมัครสมาชิก 2 รูปแบบสำหรับลูกค้า และมีช่องทางพิเศษสำหรับช่างภาพและผู้ดูแลระบบ:
            </p>

            <div class="space-y-3 my-4">
              <div class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-white/5 rounded-xl">
                <span class="step-number">1</span>
                <div class="flex-1">
                  <h6 class="font-semibold text-slate-800 dark:text-gray-100 mb-1">สมัครลูกค้า</h6>
                  <p class="text-sm text-gray-600 dark:text-gray-400 mb-0">
                    ไปที่ <code class="manual-inline">/register</code> กรอก อีเมล รหัสผ่าน และข้อมูลส่วนตัว
                    หลังจากนั้นระบบจะส่งอีเมลยืนยันให้คุณ
                  </p>
                </div>
              </div>
              <div class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-white/5 rounded-xl">
                <span class="step-number">2</span>
                <div class="flex-1">
                  <h6 class="font-semibold text-slate-800 dark:text-gray-100 mb-1">สมัครช่างภาพ</h6>
                  <p class="text-sm text-gray-600 dark:text-gray-400 mb-0">
                    ลูกค้าที่ลงทะเบียนแล้วสามารถกดเมนู "สมัครเป็นช่างภาพ" ที่หน้าโปรไฟล์
                    อัพโหลดเอกสาร และรอทีมงานตรวจสอบ (ปกติ 1-3 วันทำการ)
                  </p>
                </div>
              </div>
              <div class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-white/5 rounded-xl">
                <span class="step-number">3</span>
                <div class="flex-1">
                  <h6 class="font-semibold text-slate-800 dark:text-gray-100 mb-1">เข้าสู่ระบบผู้ดูแล</h6>
                  <p class="text-sm text-gray-600 dark:text-gray-400 mb-0">
                    ผู้ดูแลระบบเข้าที่ <code class="manual-inline">/admin/login</code> และใช้บัญชีแยกจากลูกค้า
                  </p>
                </div>
              </div>
            </div>

            <div class="warning-box rounded-r-xl p-4 my-4">
              <p class="text-sm text-slate-800 dark:text-gray-200 mb-0 flex items-start gap-2">
                <i class="bi bi-exclamation-triangle-fill text-amber-500 dark:text-amber-400 mt-0.5"></i>
                <span><strong>Warning:</strong> อีเมลที่ยังไม่ยืนยันจะไม่สามารถซื้อภาพหรือรับการแจ้งเตือนทาง email ได้
                กรุณาตรวจสอบกล่องจดหมายและคลิกลิงก์ยืนยันภายใน 24 ชั่วโมง</span>
              </p>
            </div>
          </div>

          
          <div class="mb-8">
            <h3 id="profile-setup" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-person-gear text-indigo-500 dark:text-indigo-400"></i>
              1.3 การตั้งค่าโปรไฟล์
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              เพื่อประสบการณ์ที่ดี ขอแนะนำให้ตั้งค่าข้อมูลดังนี้:
            </p>
            <ol class="list-decimal ml-6 text-gray-600 dark:text-gray-400 space-y-1.5">
              <li>อัพโหลดรูปโปรไฟล์ (Avatar) — ใช้เวลาเพียงไม่กี่วินาที</li>
              <li>กรอกชื่อ-นามสกุลและหมายเลขโทรศัพท์ สำหรับรับการแจ้งเตือนและใบเสร็จ</li>
              <li>เลือกภาษาและตั้งค่าเขตเวลา (Timezone)</li>
              <li>ตั้งค่าการแจ้งเตือน (Email, LINE, SMS) ให้ตรงตามความต้องการ</li>
              <li>(ช่างภาพ) กรอกข้อมูลบัญชีธนาคารสำหรับการรับเงิน</li>
            </ol>
          </div>

          
          <div class="mb-4">
            <h3 id="social-connect" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-link-45deg text-indigo-500 dark:text-indigo-400"></i>
              1.4 การเชื่อมต่อ LINE และ Google
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              เพิ่มความสะดวกในการใช้งานด้วยการเชื่อมต่อบัญชี social:
            </p>

            <div class="grid md:grid-cols-2 gap-3 my-4">
              <div class="rounded-xl border border-gray-200 dark:border-white/10 p-4">
                <h6 class="font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
                  <i class="bi bi-line text-green-500"></i> LINE Notify
                </h6>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-0">
                  ไปที่ <code class="manual-inline">โปรไฟล์ &gt; การแจ้งเตือน</code> กด "เชื่อมต่อ LINE"
                  เมื่อมีการอัพเดทคำสั่งซื้อหรือเหตุการณ์สำคัญ ระบบจะส่งข้อความให้อัตโนมัติ
                </p>
              </div>
              <div class="rounded-xl border border-gray-200 dark:border-white/10 p-4">
                <h6 class="font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
                  <i class="bi bi-google text-red-500"></i> Google Sign-In
                </h6>
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-0">
                  สามารถ login ด้วย Google ได้ที่หน้า login โดยกดปุ่ม "Sign in with Google"
                  สำหรับช่างภาพ สามารถเชื่อมต่อ Google Drive เพื่ออัพโหลดภาพจำนวนมากได้
                </p>
              </div>
            </div>
          </div>
        </section>

        <hr class="my-10 border-gray-100 dark:border-white/10">

        
        <section id="for-customers" class="manual-section" data-title="สำหรับลูกค้า" data-keywords="ลูกค้า customer face search ตะกร้า ชำระเงิน ดาวน์โหลด รีวิว คืนเงิน">
          <h2 class="text-2xl font-bold text-slate-800 dark:text-gray-100 mb-3 flex items-center gap-2">
            <i class="bi bi-person-heart text-pink-500 dark:text-pink-400"></i>
            2. สำหรับลูกค้า
          </h2>
          <p class="text-gray-600 dark:text-gray-400 mb-6">
            เนื้อหาส่วนนี้จะครอบคลุมขั้นตอนหลักของการใช้งานในฐานะลูกค้า ตั้งแต่การค้นหาอีเวนต์
            ไปจนถึงการดาวน์โหลดและขอคืนเงิน
          </p>

          
          <div class="mb-8">
            <h3 id="find-events" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-search text-pink-500 dark:text-pink-400"></i>
              2.1 การค้นหาอีเวนต์
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              วิธีการค้นหาอีเวนต์ที่คุณต้องการ:
            </p>
            <ol class="list-decimal ml-6 text-gray-600 dark:text-gray-400 space-y-1.5 mb-4">
              <li>ไปที่หน้า <code class="manual-inline">อีเวนต์</code> จากเมนูหลัก</li>
              <li>ใช้ช่องค้นหาใส่ชื่องาน, สถานที่, หรือคำค้นหาที่เกี่ยวข้อง</li>
              <li>กรองผลลัพธ์ด้วย "หมวดหมู่", "ราคา", และ "จังหวัด"</li>
              <li>เลือกเรียงลำดับ (ล่าสุด / ยอดนิยม / ชื่อ / ราคา)</li>
              <li>คลิกเลือกอีเวนต์ที่สนใจ</li>
            </ol>

            <div class="tip-box rounded-r-xl p-4 my-4">
              <p class="text-sm text-slate-800 dark:text-gray-200 mb-0 flex items-start gap-2">
                <i class="bi bi-lightbulb-fill text-emerald-500 dark:text-emerald-400 mt-0.5"></i>
                <span><strong>Tip:</strong> ลองใช้คำค้นหาเป็นภาษาไทยหรืออังกฤษก็ได้
                ระบบใช้ Full-Text Search รองรับทั้งสองภาษา</span>
              </p>
            </div>
          </div>

          
          <div class="mb-8">
            <h3 id="face-search" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-person-bounding-box text-pink-500 dark:text-pink-400"></i>
              2.2 การใช้ Face Search
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              Face Search คือฟีเจอร์ค้นหาภาพที่มีคุณอยู่ในงานโดยใช้ AI ตรวจจับใบหน้า:
            </p>

            <div class="space-y-3 my-4">
              <div class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-white/5 rounded-xl">
                <span class="step-number">1</span>
                <div class="flex-1">
                  <p class="text-sm text-gray-700 dark:text-gray-200 mb-0">เข้าไปในหน้าอีเวนต์ที่คุณเข้าร่วม คลิกปุ่ม <strong>"ค้นหาด้วยใบหน้า"</strong></p>
                </div>
              </div>
              <div class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-white/5 rounded-xl">
                <span class="step-number">2</span>
                <div class="flex-1">
                  <p class="text-sm text-gray-700 dark:text-gray-200 mb-0">อัพโหลดภาพ selfie ของคุณ (แนะนำภาพหน้าตรง แสงสว่างชัดเจน)</p>
                </div>
              </div>
              <div class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-white/5 rounded-xl">
                <span class="step-number">3</span>
                <div class="flex-1">
                  <p class="text-sm text-gray-700 dark:text-gray-200 mb-0">ระบบจะตรวจจับใบหน้าและเทียบกับภาพในอีเวนต์ (ใช้เวลา 5-30 วินาที)</p>
                </div>
              </div>
              <div class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-white/5 rounded-xl">
                <span class="step-number">4</span>
                <div class="flex-1">
                  <p class="text-sm text-gray-700 dark:text-gray-200 mb-0">ผลลัพธ์จะแสดงภาพที่ตรงกับใบหน้าของคุณพร้อมคะแนนความแม่นยำ (Match Score)</p>
                </div>
              </div>
            </div>

            <div class="screenshot-placeholder rounded-xl p-10 text-center my-4">
              <i class="bi bi-camera text-4xl text-indigo-400 dark:text-indigo-300 mb-2 block"></i>
              <p class="text-xs text-gray-500 dark:text-gray-400 mb-0">[ภาพประกอบ: หน้าจอ Face Search พร้อมผลลัพธ์]</p>
            </div>

            <div class="warning-box rounded-r-xl p-4 my-4">
              <p class="text-sm text-slate-800 dark:text-gray-200 mb-0 flex items-start gap-2">
                <i class="bi bi-exclamation-triangle-fill text-amber-500 dark:text-amber-400 mt-0.5"></i>
                <span><strong>Warning:</strong> ภาพ selfie ของคุณจะถูกประมวลผลและลบทันทีหลังจากเสร็จสิ้น
                เราไม่เก็บภาพใบหน้าของคุณไว้ที่เซิร์ฟเวอร์</span>
              </p>
            </div>
          </div>

          
          <div class="mb-8">
            <h3 id="select-cart" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-bag-plus text-pink-500 dark:text-pink-400"></i>
              2.3 การเลือกภาพและเพิ่มในตะกร้า
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              หลังจากพบภาพที่ต้องการ:
            </p>
            <ul class="list-disc ml-6 text-gray-600 dark:text-gray-400 space-y-1">
              <li>คลิกไอคอน <i class="bi bi-heart text-pink-500"></i> เพื่อบันทึกใน Wishlist</li>
              <li>คลิก <i class="bi bi-bag-plus text-indigo-500"></i> "เพิ่มลงตะกร้า" เพื่อใส่ภาพเพื่อเตรียมซื้อ</li>
              <li>เลือกได้หลายภาพพร้อมกัน ระบบจะคำนวณราคารวมและส่วนลดอัตโนมัติ</li>
              <li>ตรวจสอบรายการในตะกร้าจากไอคอน <i class="bi bi-bag"></i> มุมขวาบน</li>
            </ul>
          </div>

          
          <div class="mb-8">
            <h3 id="payment-channels" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-credit-card text-pink-500 dark:text-pink-400"></i>
              2.4 วิธีชำระเงิน (7 ช่องทาง)
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              เรารองรับช่องทางการชำระเงินหลากหลายเพื่อความสะดวกของคุณ:
            </p>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 my-4">
              <?php
              $_payMethods = [
                ['icon' => 'qr-code', 'color' => 'blue', 'name' => 'PromptPay', 'desc' => 'สแกน QR ผ่านแอปธนาคาร อนุมัติอัตโนมัติ'],
                ['icon' => 'bank', 'color' => 'emerald', 'name' => 'โอนธนาคาร', 'desc' => 'อัพโหลดสลิป ตรวจสอบภายใน 5-15 นาที'],
                ['icon' => 'credit-card-2-front', 'color' => 'indigo', 'name' => 'บัตรเครดิต', 'desc' => 'Visa, Mastercard, JCB, UnionPay รองรับ 3D Secure'],
                ['icon' => 'chat-dots-fill', 'color' => 'green', 'name' => 'LINE Pay', 'desc' => 'ชำระผ่านแอป LINE โดยตรง'],
                ['icon' => 'wallet-fill', 'color' => 'orange', 'name' => 'TrueMoney Wallet', 'desc' => 'ใช้ e-Wallet ของ True'],
                ['icon' => 'globe', 'color' => 'purple', 'name' => '2C2P', 'desc' => 'Gateway สำหรับนักท่องเที่ยวต่างชาติ'],
                ['icon' => 'paypal', 'color' => 'sky', 'name' => 'PayPal', 'desc' => 'รองรับสกุลเงินต่างประเทศ'],
              ];
              ?>
              <?php $__currentLoopData = $_payMethods; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pm): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
              <div class="rounded-xl border border-gray-200 dark:border-white/10 p-3 flex items-start gap-3">
                <div class="w-10 h-10 rounded-lg bg-<?php echo e($pm['color']); ?>-100 dark:bg-<?php echo e($pm['color']); ?>-500/15 text-<?php echo e($pm['color']); ?>-600 dark:text-<?php echo e($pm['color']); ?>-300 flex items-center justify-center shrink-0">
                  <i class="bi bi-<?php echo e($pm['icon']); ?>"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <h6 class="font-semibold text-sm text-slate-800 dark:text-gray-100 mb-0.5"><?php echo e($pm['name']); ?></h6>
                  <p class="text-xs text-gray-600 dark:text-gray-400 mb-0"><?php echo e($pm['desc']); ?></p>
                </div>
              </div>
              <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>

            <div class="tip-box rounded-r-xl p-4 my-4">
              <p class="text-sm text-slate-800 dark:text-gray-200 mb-0 flex items-start gap-2">
                <i class="bi bi-lightbulb-fill text-emerald-500 dark:text-emerald-400 mt-0.5"></i>
                <span><strong>Tip:</strong> PromptPay และบัตรเครดิตจะประมวลผลทันที
                ส่วนการโอนธนาคารต้องรอเจ้าหน้าที่ตรวจสอบสลิปก่อน</span>
              </p>
            </div>
          </div>

          
          <div class="mb-8">
            <h3 id="download-images" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-download text-pink-500 dark:text-pink-400"></i>
              2.5 การดาวน์โหลดภาพ
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              หลังจากคำสั่งซื้อได้รับการยืนยันการชำระเงิน:
            </p>
            <ol class="list-decimal ml-6 text-gray-600 dark:text-gray-400 space-y-1.5 mb-3">
              <li>ไปที่ <code class="manual-inline">โปรไฟล์ &gt; คำสั่งซื้อของฉัน</code></li>
              <li>เลือกคำสั่งซื้อที่สถานะ "ชำระเงินแล้ว"</li>
              <li>กดปุ่ม <strong>ดาวน์โหลด</strong> เพื่อโหลดทีละภาพ หรือ <strong>ดาวน์โหลดทั้งหมด (ZIP)</strong></li>
              <li>ลิงก์ดาวน์โหลดหมดอายุใน 7 วัน แต่สามารถขอลิงก์ใหม่ได้ตลอด</li>
            </ol>
            <div class="info-box rounded-r-xl p-4 my-4">
              <p class="text-sm text-slate-800 dark:text-gray-200 mb-0 flex items-start gap-2">
                <i class="bi bi-info-circle-fill text-indigo-500 dark:text-indigo-400 mt-0.5"></i>
                <span><strong>Tip:</strong> ภาพที่ซื้อจะถูกฝัง watermark ที่ Metadata เพื่อการตรวจสอบลิขสิทธิ์
                การดาวน์โหลดทุกครั้งจะถูกบันทึกใน Log</span>
              </p>
            </div>
          </div>

          
          <div class="mb-8">
            <h3 id="reviews" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-star text-pink-500 dark:text-pink-400"></i>
              2.6 รีวิว
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              หลังจากดาวน์โหลดภาพสำเร็จ คุณสามารถให้คะแนนและเขียนรีวิวช่างภาพได้:
            </p>
            <ul class="list-disc ml-6 text-gray-600 dark:text-gray-400 space-y-1">
              <li>ให้คะแนน 1-5 ดาว</li>
              <li>เขียนความคิดเห็นเกี่ยวกับคุณภาพภาพ ความรวดเร็ว และความเป็นมืออาชีพ</li>
              <li>รีวิวจะแสดงในโปรไฟล์ช่างภาพเพื่อช่วยลูกค้ารายอื่นตัดสินใจ</li>
              <li>ช่างภาพสามารถตอบกลับรีวิวของคุณได้</li>
            </ul>
          </div>

          
          <div class="mb-4">
            <h3 id="refund" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-arrow-counterclockwise text-pink-500 dark:text-pink-400"></i>
              2.7 ขอคืนเงิน
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              หากมีปัญหาเกี่ยวกับคำสั่งซื้อ คุณสามารถขอคืนเงินได้ภายใน 7 วัน:
            </p>
            <ol class="list-decimal ml-6 text-gray-600 dark:text-gray-400 space-y-1.5 mb-3">
              <li>ไปที่คำสั่งซื้อที่ต้องการ กดเมนู "ขอคืนเงิน"</li>
              <li>เลือกเหตุผล เช่น ภาพเสียหาย, ซื้อผิด, ไม่ตรงตามที่ระบุ</li>
              <li>แนบหลักฐาน (ภาพ / ข้อความ) ถ้ามี</li>
              <li>รอทีมงานตรวจสอบภายใน 1-3 วันทำการ</li>
              <li>เมื่ออนุมัติแล้วเงินจะกลับภายใน 3-10 วันทำการ ขึ้นอยู่กับช่องทางชำระเงิน</li>
            </ol>
            <div class="danger-box rounded-r-xl p-4 my-4">
              <p class="text-sm text-slate-800 dark:text-gray-200 mb-0 flex items-start gap-2">
                <i class="bi bi-shield-exclamation text-red-500 dark:text-red-400 mt-0.5"></i>
                <span><strong>Warning:</strong> การขอคืนเงินบ่อยเกินไปโดยไม่มีเหตุผลชัดเจน
                อาจส่งผลให้บัญชีถูกระงับ กรุณาตรวจสอบภาพก่อนซื้อทุกครั้ง</span>
              </p>
            </div>
          </div>
        </section>

        <hr class="my-10 border-gray-100 dark:border-white/10">

        
        <section id="for-photographers" class="manual-section" data-title="สำหรับช่างภาพ" data-keywords="ช่างภาพ photographer อีเวนต์ อัพโหลด google drive รายได้ ถอนเงิน แพ็กเกจ">
          <h2 class="text-2xl font-bold text-slate-800 dark:text-gray-100 mb-3 flex items-center gap-2">
            <i class="bi bi-camera-reels text-blue-500 dark:text-blue-400"></i>
            3. สำหรับช่างภาพ
          </h2>
          <p class="text-gray-600 dark:text-gray-400 mb-6">
            คู่มือสำหรับช่างภาพที่ต้องการสร้างรายได้จากการขายภาพงานอีเวนต์
          </p>

          
          <div class="mb-8">
            <h3 id="photographer-register" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-person-vcard text-blue-500 dark:text-blue-400"></i>
              3.1 การสมัครและอนุมัติ
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              ขั้นตอนการสมัครเป็นช่างภาพ:
            </p>
            <ol class="list-decimal ml-6 text-gray-600 dark:text-gray-400 space-y-1.5 mb-3">
              <li>สมัครสมาชิกลูกค้าปกติและยืนยันอีเมล</li>
              <li>ที่โปรไฟล์ คลิกปุ่ม <strong>"สมัครเป็นช่างภาพ"</strong></li>
              <li>กรอกข้อมูล: ชื่อแสดง, ประเภทงาน, portfolio link, ข้อมูลบัญชีธนาคาร</li>
              <li>อัพโหลดเอกสาร: บัตรประชาชน, ผลงานตัวอย่าง (3-5 ภาพ)</li>
              <li>ส่งคำขอ รอการอนุมัติจากทีมงาน (1-3 วันทำการ)</li>
            </ol>
            <div class="info-box rounded-r-xl p-4 my-4">
              <p class="text-sm text-slate-800 dark:text-gray-200 mb-0 flex items-start gap-2">
                <i class="bi bi-info-circle-fill text-indigo-500 dark:text-indigo-400 mt-0.5"></i>
                <span><strong>Tip:</strong> portfolio link เช่น Instagram, Facebook Page,
                หรือเว็บไซต์ส่วนตัว ช่วยให้ทีมตรวจสอบได้เร็วขึ้น</span>
              </p>
            </div>
          </div>

          
          <div class="mb-8">
            <h3 id="create-event" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-calendar-plus text-blue-500 dark:text-blue-400"></i>
              3.2 สร้างอีเวนต์
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              เมื่อได้รับการอนุมัติแล้ว คุณสามารถสร้างอีเวนต์ได้จากแดชบอร์ดช่างภาพ:
            </p>
            <ol class="list-decimal ml-6 text-gray-600 dark:text-gray-400 space-y-1.5">
              <li>ไปที่ <code class="manual-inline">แดชบอร์ดช่างภาพ &gt; อีเวนต์ของฉัน &gt; สร้างใหม่</code></li>
              <li>กรอก: ชื่องาน, หมวดหมู่, วันที่ถ่าย, สถานที่ / จังหวัด, รายละเอียด</li>
              <li>อัพโหลดภาพปก (Cover) — ขนาดแนะนำ 1920×1080</li>
              <li>เลือกสถานะ (ร่าง / เผยแพร่ / ส่วนตัว — ต้องใช้รหัสผ่าน)</li>
              <li>ตั้งราคาต่อภาพ หรือแพ็กเกจ</li>
              <li>บันทึก แล้วเริ่มอัพโหลดภาพ</li>
            </ol>
          </div>

          
          <div class="mb-8">
            <h3 id="upload-methods" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-cloud-upload text-blue-500 dark:text-blue-400"></i>
              3.3 อัพโหลดภาพ (3 วิธี)
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              ระบบรองรับการอัพโหลดภาพ 3 วิธีให้เลือกใช้ตามปริมาณงาน:
            </p>

            <div class="grid md:grid-cols-3 gap-3 my-4">
              <div class="rounded-xl border border-gray-200 dark:border-white/10 p-4">
                <div class="w-10 h-10 rounded-lg bg-indigo-100 dark:bg-indigo-500/15 text-indigo-600 dark:text-indigo-300 flex items-center justify-center mb-3">
                  <i class="bi bi-arrow-down-square"></i>
                </div>
                <h6 class="font-semibold text-slate-800 dark:text-gray-100 mb-1">1. Drag &amp; Drop</h6>
                <p class="text-xs text-gray-600 dark:text-gray-400 mb-0">
                  ลากไฟล์ภาพจากเครื่องมาใส่ในหน้าอัพโหลด เหมาะสำหรับ &lt; 200 ภาพ
                </p>
              </div>
              <div class="rounded-xl border border-gray-200 dark:border-white/10 p-4">
                <div class="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-500/15 text-green-600 dark:text-green-300 flex items-center justify-center mb-3">
                  <i class="bi bi-google"></i>
                </div>
                <h6 class="font-semibold text-slate-800 dark:text-gray-100 mb-1">2. Google Drive</h6>
                <p class="text-xs text-gray-600 dark:text-gray-400 mb-0">
                  เชื่อมต่อ Google Drive แล้วเลือก folder ระบบจะ sync ภาพอัตโนมัติ
                  เหมาะสำหรับงานขนาดใหญ่
                </p>
              </div>
              <div class="rounded-xl border border-gray-200 dark:border-white/10 p-4">
                <div class="w-10 h-10 rounded-lg bg-orange-100 dark:bg-orange-500/15 text-orange-600 dark:text-orange-300 flex items-center justify-center mb-3">
                  <i class="bi bi-stack"></i>
                </div>
                <h6 class="font-semibold text-slate-800 dark:text-gray-100 mb-1">3. Bulk Upload</h6>
                <p class="text-xs text-gray-600 dark:text-gray-400 mb-0">
                  อัพโหลดไฟล์ ZIP ที่มีภาพจำนวนมาก ระบบจะแตกและประมวลผลให้อัตโนมัติ
                </p>
              </div>
            </div>

            <div class="tip-box rounded-r-xl p-4 my-4">
              <p class="text-sm text-slate-800 dark:text-gray-200 mb-0 flex items-start gap-2">
                <i class="bi bi-lightbulb-fill text-emerald-500 dark:text-emerald-400 mt-0.5"></i>
                <span><strong>Tip:</strong> ระบบจะสร้าง watermark และ thumbnail อัตโนมัติ
                พร้อมทำ Face indexing เพื่อให้ลูกค้าค้นหาด้วย Face Search ได้</span>
              </p>
            </div>
          </div>

          
          <div class="mb-8">
            <h3 id="pricing-packages" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-tag text-blue-500 dark:text-blue-400"></i>
              3.4 ตั้งราคา / แพ็กเกจ
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              กำหนดราคาภาพแบบยืดหยุ่นตามความต้องการ:
            </p>
            <ul class="list-disc ml-6 text-gray-600 dark:text-gray-400 space-y-1">
              <li><strong>ราคาต่อภาพ</strong> — กำหนดราคาเดียวสำหรับทุกภาพในอีเวนต์</li>
              <li><strong>แพ็กเกจ</strong> — เช่น ซื้อ 10 ภาพ ลด 20%, ซื้อหมดในอีเวนต์ ลด 50%</li>
              <li><strong>ฟรี</strong> — ตั้งราคา 0 เพื่อแจกฟรี (ลูกค้าต้อง login)</li>
              <li><strong>เปลี่ยนราคาภายหลัง</strong> — ราคาสามารถปรับเปลี่ยนได้ตลอด</li>
            </ul>
          </div>

          
          <div class="mb-8">
            <h3 id="earnings" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-graph-up-arrow text-blue-500 dark:text-blue-400"></i>
              3.5 ติดตามรายได้
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              แดชบอร์ดรายได้แสดงข้อมูลครบถ้วน:
            </p>
            <ul class="list-disc ml-6 text-gray-600 dark:text-gray-400 space-y-1">
              <li>ยอดขายทั้งหมด / เดือนนี้ / สัปดาห์นี้</li>
              <li>ยอดคงเหลือพร้อมถอน</li>
              <li>กราฟรายได้ย้อนหลัง 12 เดือน</li>
              <li>รายการ Top อีเวนต์ที่ขายดี</li>
              <li>ค่าคอมมิชชันที่แพลตฟอร์มหัก (ปกติ 15-20%)</li>
            </ul>
          </div>

          
          <div class="mb-8">
            <h3 id="photographer-payout" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-wallet2 text-blue-500 dark:text-blue-400"></i>
              3.6 การถอนเงิน
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              ขั้นตอนการถอนเงินเข้าบัญชีธนาคาร:
            </p>
            <ol class="list-decimal ml-6 text-gray-600 dark:text-gray-400 space-y-1.5">
              <li>ยอดเงินขั้นต่ำที่ถอนได้: 500 บาท</li>
              <li>ไปที่ <code class="manual-inline">แดชบอร์ดช่างภาพ &gt; รายได้ &gt; ขอถอนเงิน</code></li>
              <li>ระบุจำนวนเงินและตรวจสอบบัญชีธนาคารที่ลงทะเบียน</li>
              <li>ส่งคำขอ แล้วรอทีมการเงินประมวลผล (ปกติ 3-5 วันทำการ)</li>
              <li>เมื่อโอนแล้วระบบจะแจ้งเตือนและส่งใบรับรองภาษีให้ (ถ้ามี)</li>
            </ol>
          </div>

          
          <div class="mb-4">
            <h3 id="reply-reviews" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-chat-dots text-blue-500 dark:text-blue-400"></i>
              3.7 ตอบรีวิว
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              การตอบรีวิวลูกค้าช่วยสร้างความน่าเชื่อถือและปรับปรุงบริการ:
            </p>
            <ul class="list-disc ml-6 text-gray-600 dark:text-gray-400 space-y-1">
              <li>ไปที่ <code class="manual-inline">แดชบอร์ดช่างภาพ &gt; รีวิว</code></li>
              <li>ตอบกลับรีวิวแต่ละข้อความ — คำตอบจะแสดงในโปรไฟล์ของคุณ</li>
              <li>รายงานรีวิวที่ไม่เหมาะสม (ทีมงานจะตรวจสอบและลบถ้าละเมิดกฎ)</li>
            </ul>
          </div>
        </section>

        <hr class="my-10 border-gray-100 dark:border-white/10">

        
        <section id="blog-ai" class="manual-section" data-title="Blog & AI" data-keywords="blog ai บทความ news aggregator affiliate cta editor admin">
          <h2 class="text-2xl font-bold text-slate-800 dark:text-gray-100 mb-3 flex items-center gap-2">
            <i class="bi bi-robot text-purple-500 dark:text-purple-400"></i>
            4. Blog &amp; AI (สำหรับ Admin / Editor)
          </h2>
          <p class="text-gray-600 dark:text-gray-400 mb-6">
            ส่วนนี้ครอบคลุมเครื่องมือเขียนบทความแบบ manual การใช้ AI ช่วยสร้างเนื้อหา
            และระบบ News Aggregator
          </p>

          
          <div class="mb-8">
            <h3 id="manual-article" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-pencil-square text-purple-500 dark:text-purple-400"></i>
              4.1 สร้างบทความ manual
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              การเขียนบทความแบบ manual ด้วย WYSIWYG editor:
            </p>
            <ol class="list-decimal ml-6 text-gray-600 dark:text-gray-400 space-y-1.5">
              <li>ไปที่ <code class="manual-inline">Admin &gt; Blog &gt; เขียนใหม่</code></li>
              <li>กรอก: หัวข้อ, หมวดหมู่, tags, Meta Description (สำหรับ SEO)</li>
              <li>เขียนเนื้อหา พร้อม H1-H6, ภาพ, ตาราง, code blocks</li>
              <li>เลือก Featured Image และ slug URL</li>
              <li>เลือกสถานะ: ร่าง / กำหนดเผยแพร่ / เผยแพร่ทันที</li>
              <li>กด "บันทึก" และ preview ก่อนเผยแพร่</li>
            </ol>
          </div>

          
          <div class="mb-8">
            <h3 id="ai-article" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-stars text-purple-500 dark:text-purple-400"></i>
              4.2 ใช้ AI สร้างบทความ
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              AI Writing Assistant ใช้ LLM สร้างบทความคุณภาพสูงได้รวดเร็ว:
            </p>
            <ol class="list-decimal ml-6 text-gray-600 dark:text-gray-400 space-y-1.5 mb-3">
              <li>ไปที่ <code class="manual-inline">Admin &gt; Blog &gt; AI Generator</code></li>
              <li>ใส่ Topic หรือ Keywords ที่ต้องการ</li>
              <li>เลือก Tone (formal, friendly, casual) และ Length</li>
              <li>เลือก language (TH / EN)</li>
              <li>กด "Generate" — ระบบจะสร้างร่างให้ใน 10-30 วินาที</li>
              <li>ตรวจสอบ แก้ไข เพิ่มภาพ แล้วเผยแพร่</li>
            </ol>
            <pre class="manual-code"><code>// Configuration: .env
AI_PROVIDER=anthropic
AI_MODEL=claude-3-sonnet
AI_MAX_TOKENS=4096
AI_ARTICLE_ENABLED=true</code></pre>
          </div>

          
          <div class="mb-8">
            <h3 id="ai-toggle" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-toggles text-purple-500 dark:text-purple-400"></i>
              4.3 ระบบเปิด-ปิด AI
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              สามารถเปิด-ปิดฟีเจอร์ AI ได้ทั้งระบบ เพื่อควบคุมค่าใช้จ่าย API:
            </p>
            <ul class="list-disc ml-6 text-gray-600 dark:text-gray-400 space-y-1">
              <li>ไปที่ <code class="manual-inline">Admin &gt; ตั้งค่า &gt; AI Settings</code></li>
              <li>Toggle เปิด/ปิด: Article Generation, Face Recognition, Chat Bot</li>
              <li>ดู usage ประจำเดือน และ budget alert</li>
              <li>ตั้ง rate limit เพื่อป้องกันการใช้เกิน</li>
            </ul>
          </div>

          
          <div class="mb-8">
            <h3 id="news-aggregator" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-rss text-purple-500 dark:text-purple-400"></i>
              4.4 News Aggregator
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              News Aggregator ดึงบทความจากแหล่งข่าวอื่นมาแสดงใน Blog:
            </p>
            <ol class="list-decimal ml-6 text-gray-600 dark:text-gray-400 space-y-1.5">
              <li>ไปที่ <code class="manual-inline">Admin &gt; Blog &gt; News Sources</code></li>
              <li>เพิ่ม RSS Feed URL หรือ API endpoint</li>
              <li>เลือก keyword filter และ category mapping</li>
              <li>ตั้ง fetch schedule (รายวัน / รายชั่วโมง)</li>
              <li>ตรวจสอบข่าวที่ fetch มาใน Drafts แล้วเผยแพร่</li>
            </ol>
          </div>

          
          <div class="mb-4">
            <h3 id="affiliate-cta" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-megaphone text-purple-500 dark:text-purple-400"></i>
              4.5 Affiliate Links &amp; CTA
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              เพิ่มรายได้จาก Blog ด้วย Affiliate links และ Call-to-Action block:
            </p>
            <ul class="list-disc ml-6 text-gray-600 dark:text-gray-400 space-y-1">
              <li>แทรก Affiliate link ในบทความ ระบบจะเพิ่ม <code class="manual-inline">rel="sponsored nofollow"</code> อัตโนมัติ</li>
              <li>สร้าง CTA block — ปุ่มลิงก์สีสันสะดุดตาพร้อม tracking</li>
              <li>ดูสถิติคลิกและ conversion ที่ <code class="manual-inline">Admin &gt; Analytics &gt; Affiliates</code></li>
              <li>รายงานรายเดือนพร้อมส่งออกเป็น CSV</li>
            </ul>
          </div>
        </section>

        <hr class="my-10 border-gray-100 dark:border-white/10">

        
        <section id="admin-panel" class="manual-section" data-title="Admin Panel" data-keywords="admin dashboard user management order refund coupon storage seo login history security">
          <h2 class="text-2xl font-bold text-slate-800 dark:text-gray-100 mb-3 flex items-center gap-2">
            <i class="bi bi-speedometer2 text-red-500 dark:text-red-400"></i>
            5. Admin Panel
          </h2>
          <p class="text-gray-600 dark:text-gray-400 mb-6">
            คู่มือการใช้งาน Admin Panel สำหรับผู้ดูแลระบบ
          </p>

          
          <div class="mb-8">
            <h3 id="admin-dashboard" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-grid-1x2 text-red-500 dark:text-red-400"></i>
              5.1 Dashboard Overview
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              หน้าแรกของ Admin Panel แสดงข้อมูลภาพรวม:
            </p>
            <ul class="list-disc ml-6 text-gray-600 dark:text-gray-400 space-y-1">
              <li>ยอดขาย / รายได้ / Orders ประจำวัน, สัปดาห์, เดือน</li>
              <li>จำนวนผู้ใช้ที่ออนไลน์ ณ ขณะนั้น</li>
              <li>คำขอ (pending) ที่รอตรวจสอบ: refund, photographer approval, slip verification</li>
              <li>กราฟสถิติ 30 วันย้อนหลัง</li>
              <li>Top อีเวนต์, Top ช่างภาพ</li>
            </ul>
          </div>

          
          <div class="mb-8">
            <h3 id="user-management" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-people text-red-500 dark:text-red-400"></i>
              5.2 User Management
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              จัดการผู้ใช้ในระบบ:
            </p>
            <ul class="list-disc ml-6 text-gray-600 dark:text-gray-400 space-y-1">
              <li>ดูรายชื่อผู้ใช้ทั้งหมด พร้อม filter (active / banned / unverified)</li>
              <li>ค้นหาด้วยอีเมล, ชื่อ, เบอร์โทร</li>
              <li>ระงับ / ปลดระงับบัญชี พร้อมบันทึกเหตุผล</li>
              <li>ดูประวัติการสั่งซื้อและเข้าสู่ระบบ</li>
              <li>Merge บัญชีซ้ำ</li>
            </ul>
          </div>

          
          <div class="mb-8">
            <h3 id="order-management" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-bag-check text-red-500 dark:text-red-400"></i>
              5.3 Order Management
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              จัดการคำสั่งซื้อ:
            </p>
            <ul class="list-disc ml-6 text-gray-600 dark:text-gray-400 space-y-1">
              <li>ดูคำสั่งซื้อทั้งหมด filter by status (pending / paid / failed / refunded)</li>
              <li>ตรวจสอบสลิปการโอนธนาคาร — อนุมัติ / ปฏิเสธ</li>
              <li>ส่ง Invoice ซ้ำ</li>
              <li>ยกเลิกคำสั่งซื้อและคืนเงิน</li>
              <li>Export CSV สำหรับบัญชีรายเดือน</li>
            </ul>
          </div>

          
          <div class="mb-8">
            <h3 id="refund-workflow" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-arrow-counterclockwise text-red-500 dark:text-red-400"></i>
              5.4 Refund Workflow
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              ขั้นตอนการอนุมัติคืนเงิน:
            </p>
            <ol class="list-decimal ml-6 text-gray-600 dark:text-gray-400 space-y-1.5">
              <li>ดูรายการคำขอที่ <code class="manual-inline">Admin &gt; Refunds</code></li>
              <li>ตรวจสอบเหตุผลและหลักฐาน</li>
              <li>อนุมัติ: ระบบจะเรียก API ของ gateway เพื่อคืนเงินอัตโนมัติ</li>
              <li>ปฏิเสธ: ต้องระบุเหตุผล ลูกค้าจะได้รับอีเมลแจ้ง</li>
              <li>ติดตามสถานะ refund ใน timeline</li>
            </ol>
          </div>

          
          <div class="mb-8">
            <h3 id="coupon-analytics" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-ticket-perforated text-red-500 dark:text-red-400"></i>
              5.5 Coupon Analytics
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              สร้างและวิเคราะห์คูปองส่วนลด:
            </p>
            <ul class="list-disc ml-6 text-gray-600 dark:text-gray-400 space-y-1">
              <li>สร้างคูปองแบบต่าง ๆ: เปอร์เซ็นต์ / จำนวนคงที่ / ฟรีขนส่ง</li>
              <li>ตั้งเงื่อนไข: minimum purchase, user group, expiry date, usage limit</li>
              <li>ดูสถิติ: จำนวนใช้ / รายได้ที่เกิด / conversion rate</li>
              <li>Auto-expire คูปองที่หมดอายุ</li>
            </ul>
          </div>

          
          <div class="mb-8">
            <h3 id="storage-seo" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-hdd text-red-500 dark:text-red-400"></i>
              5.6 Storage &amp; SEO Tools
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              เครื่องมือจัดการพื้นที่เก็บข้อมูลและ SEO:
            </p>
            <ul class="list-disc ml-6 text-gray-600 dark:text-gray-400 space-y-1">
              <li><strong>Storage:</strong> ดูการใช้พื้นที่ (S3, Google Drive, Local), ลบไฟล์ orphan</li>
              <li><strong>SEO:</strong> meta tags, sitemap.xml, robots.txt, schema.org</li>
              <li><strong>Google Analytics:</strong> เชื่อมต่อ GA4 และดูรายงาน</li>
              <li><strong>OG Tags:</strong> ปรับแต่ง Open Graph ของแต่ละหน้า</li>
            </ul>
          </div>

          
          <div class="mb-4">
            <h3 id="login-history" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-shield-lock text-red-500 dark:text-red-400"></i>
              5.7 Login History + Security
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              ระบบติดตามการเข้าสู่ระบบเพื่อความปลอดภัย:
            </p>
            <ul class="list-disc ml-6 text-gray-600 dark:text-gray-400 space-y-1">
              <li>ดู login history: IP, User Agent, Location, Success/Failed</li>
              <li>Alert เมื่อมี login จากตำแหน่งใหม่</li>
              <li>Force logout all sessions ของผู้ใช้คนใดคนหนึ่ง</li>
              <li>Blacklist IP ที่ brute force</li>
            </ul>
          </div>
        </section>

        <hr class="my-10 border-gray-100 dark:border-white/10">

        
        <section id="payment-finance" class="manual-section" data-title="การชำระเงิน & การเงิน" data-keywords="payment gateway สลิป ภาษี ใบเสร็จ payout tax invoice">
          <h2 class="text-2xl font-bold text-slate-800 dark:text-gray-100 mb-3 flex items-center gap-2">
            <i class="bi bi-cash-coin text-emerald-500 dark:text-emerald-400"></i>
            6. การชำระเงิน &amp; การเงิน
          </h2>
          <p class="text-gray-600 dark:text-gray-400 mb-6">
            การตั้งค่าและบริหารระบบการเงินของแพลตฟอร์ม
          </p>

          
          <div class="mb-8">
            <h3 id="gateway-setup" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-gear-wide-connected text-emerald-500 dark:text-emerald-400"></i>
              6.1 วิธีตั้งค่า Payment Gateways
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              ตั้งค่า Payment Gateway ที่ <code class="manual-inline">Admin &gt; Payments &gt; Gateways</code>:
            </p>
            <ol class="list-decimal ml-6 text-gray-600 dark:text-gray-400 space-y-1.5 mb-3">
              <li>เลือก Gateway ที่ต้องการเปิด</li>
              <li>กรอก API Key / Secret Key / Merchant ID</li>
              <li>กำหนด webhook URL ใน dashboard ของ provider</li>
              <li>ทดสอบ (test mode) ก่อน go live</li>
              <li>เปิดใช้งาน</li>
            </ol>
            <pre class="manual-code"><code># Example: PromptPay setup (.env)
PROMPTPAY_ENABLED=true
PROMPTPAY_ID=0812345678
PROMPTPAY_QR_PROVIDER=scb
PROMPTPAY_AUTO_VERIFY=true

# Example: Stripe
STRIPE_KEY=pk_live_xxx
STRIPE_SECRET=sk_live_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx</code></pre>
          </div>

          
          <div class="mb-8">
            <h3 id="slip-verification" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-receipt-cutoff text-emerald-500 dark:text-emerald-400"></i>
              6.2 การตรวจสอบสลิป
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              ระบบตรวจสอบสลิปการโอนธนาคารอัตโนมัติด้วย OCR + Slip Verify API:
            </p>
            <ul class="list-disc ml-6 text-gray-600 dark:text-gray-400 space-y-1">
              <li>ลูกค้าอัพโหลดสลิป ระบบอ่าน QR และตรวจสอบทันที</li>
              <li>ถ้า auto-verify ผ่าน order จะ confirm อัตโนมัติ</li>
              <li>ถ้าไม่ผ่าน จะเข้า manual queue ให้ admin ตรวจสอบ</li>
              <li>รองรับสลิป K-Bank, SCB, BBL, KTB, TMB, BAY</li>
            </ul>
          </div>

          
          <div class="mb-8">
            <h3 id="payout-management" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-bank text-emerald-500 dark:text-emerald-400"></i>
              6.3 การจ่ายเงินช่างภาพ (Payout)
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              ระบบจ่ายเงินช่างภาพอัตโนมัติและแบบ manual:
            </p>
            <ol class="list-decimal ml-6 text-gray-600 dark:text-gray-400 space-y-1.5">
              <li>ช่างภาพส่งคำขอถอน</li>
              <li>Admin ตรวจสอบยอดคงเหลือและบัญชีธนาคาร</li>
              <li>Export batch file (SCB / KBank format) สำหรับโอนจำนวนมาก</li>
              <li>อัพโหลดสลิปการโอนกลับเข้าระบบ</li>
              <li>ระบบบันทึกประวัติและส่งอีเมลยืนยันให้ช่างภาพ</li>
            </ol>
          </div>

          
          <div class="mb-8">
            <h3 id="tax" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-calculator text-emerald-500 dark:text-emerald-400"></i>
              6.4 ภาษี
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              การจัดการภาษีสำหรับธุรกิจ:
            </p>
            <ul class="list-disc ml-6 text-gray-600 dark:text-gray-400 space-y-1">
              <li>ตั้งค่า VAT 7% หรือ 0% (สำหรับต่างชาติ)</li>
              <li>หักภาษี ณ ที่จ่าย 3% สำหรับค่าช่างภาพ (ตามเกณฑ์กรมสรรพากร)</li>
              <li>ออก ใบกำกับภาษี / ใบเสร็จรับเงิน แยกประเภท</li>
              <li>Export รายงานภาษีประจำเดือน (ภ.ง.ด.53, ภ.พ.30)</li>
            </ul>
          </div>

          
          <div class="mb-4">
            <h3 id="invoice" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-file-earmark-text text-emerald-500 dark:text-emerald-400"></i>
              6.5 ใบเสร็จ
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              ระบบใบเสร็จแบบอัตโนมัติ:
            </p>
            <ul class="list-disc ml-6 text-gray-600 dark:text-gray-400 space-y-1">
              <li>สร้าง Invoice PDF อัตโนมัติหลังจ่ายเงิน</li>
              <li>ส่งทางอีเมลให้ลูกค้า</li>
              <li>ดาวน์โหลดได้จาก "คำสั่งซื้อของฉัน"</li>
              <li>สำหรับนิติบุคคล: กรอกเลขผู้เสียภาษี + ที่อยู่ออกใบกำกับภาษี</li>
            </ul>
          </div>
        </section>

        <hr class="my-10 border-gray-100 dark:border-white/10">

        
        <section id="security" class="manual-section" data-title="Security" data-keywords="security 2fa api key activity log suspicious login">
          <h2 class="text-2xl font-bold text-slate-800 dark:text-gray-100 mb-3 flex items-center gap-2">
            <i class="bi bi-shield-check text-amber-500 dark:text-amber-400"></i>
            7. Security
          </h2>
          <p class="text-gray-600 dark:text-gray-400 mb-6">
            ฟีเจอร์ความปลอดภัยสำหรับผู้ใช้ทุกระดับ
          </p>

          
          <div class="mb-8">
            <h3 id="two-fa" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-shield-lock text-amber-500 dark:text-amber-400"></i>
              7.1 2FA Setup
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              เปิดใช้งาน Two-Factor Authentication เพื่อความปลอดภัย:
            </p>
            <ol class="list-decimal ml-6 text-gray-600 dark:text-gray-400 space-y-1.5">
              <li>ไปที่ <code class="manual-inline">โปรไฟล์ &gt; ความปลอดภัย &gt; 2FA</code></li>
              <li>เลือกวิธี: TOTP (Google Authenticator / Authy), SMS, Email</li>
              <li>Scan QR Code ด้วย app TOTP และกรอก 6 หลัก</li>
              <li>บันทึก Backup Codes (สำคัญ!) เก็บไว้ในที่ปลอดภัย</li>
              <li>ครั้งต่อไปตอน login ระบบจะขอ 2FA code</li>
            </ol>
            <div class="tip-box rounded-r-xl p-4 my-4">
              <p class="text-sm text-slate-800 dark:text-gray-200 mb-0 flex items-start gap-2">
                <i class="bi bi-lightbulb-fill text-emerald-500 dark:text-emerald-400 mt-0.5"></i>
                <span><strong>Tip:</strong> Admin account ควรเปิด 2FA บังคับ — ตั้งได้ที่
                <code class="manual-inline">Admin &gt; Settings &gt; Require 2FA for Admins</code></span>
              </p>
            </div>
          </div>

          
          <div class="mb-8">
            <h3 id="suspicious-login" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-exclamation-octagon text-amber-500 dark:text-amber-400"></i>
              7.2 Suspicious Login Detection
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              ระบบตรวจจับการเข้าสู่ระบบที่น่าสงสัยอัตโนมัติ:
            </p>
            <ul class="list-disc ml-6 text-gray-600 dark:text-gray-400 space-y-1">
              <li>ตรวจเมื่อ login จาก IP / Country / Device ใหม่</li>
              <li>ส่งอีเมลแจ้งเตือนให้ user ทันที</li>
              <li>บล็อกอัตโนมัติถ้ามี failed login &gt; 5 ครั้งใน 5 นาที</li>
              <li>ตรวจสอบ Tor Exit Node และ VPN ที่รู้จัก</li>
            </ul>
          </div>

          
          <div class="mb-8">
            <h3 id="api-keys" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-key text-amber-500 dark:text-amber-400"></i>
              7.3 API Keys
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              สร้าง API Keys สำหรับ integration:
            </p>
            <ol class="list-decimal ml-6 text-gray-600 dark:text-gray-400 space-y-1.5 mb-3">
              <li>ไปที่ <code class="manual-inline">Admin &gt; API Keys</code></li>
              <li>กด "สร้างใหม่" ใส่ชื่อและเลือก scopes</li>
              <li>คัดลอก API Key (แสดงครั้งเดียว)</li>
              <li>ตั้ง IP Whitelist และ expiry date (optional)</li>
              <li>ดู usage log และ revoke เมื่อไม่ใช้</li>
            </ol>
            <pre class="manual-code"><code># ใช้งาน API Key
curl -H "Authorization: Bearer YOUR_API_KEY" \
     -H "Accept: application/json" \
     https://example.com/api/v1/events</code></pre>
          </div>

          
          <div class="mb-4">
            <h3 id="activity-log" class="text-lg font-semibold text-slate-800 dark:text-gray-100 mb-2 flex items-center gap-2">
              <i class="bi bi-journal-text text-amber-500 dark:text-amber-400"></i>
              7.4 Activity Log
            </h3>
            <p class="text-gray-600 dark:text-gray-400 mb-3">
              ทุกการกระทำสำคัญจะถูกบันทึกใน Activity Log:
            </p>
            <ul class="list-disc ml-6 text-gray-600 dark:text-gray-400 space-y-1">
              <li>Login / Logout, password change, 2FA toggle</li>
              <li>Order, refund, payment</li>
              <li>Admin actions: approve/reject, ban user, delete content</li>
              <li>Settings change — ระบุค่าเก่าและค่าใหม่</li>
              <li>ค้นหาและ export CSV ได้</li>
            </ul>
          </div>
        </section>

        <hr class="my-10 border-gray-100 dark:border-white/10">

        
        <section id="faq" class="manual-section" data-title="FAQ" data-keywords="faq คำถาม frequently asked questions ช่วยเหลือ">
          <h2 class="text-2xl font-bold text-slate-800 dark:text-gray-100 mb-3 flex items-center gap-2">
            <i class="bi bi-patch-question text-cyan-500 dark:text-cyan-400"></i>
            8. คำถามที่พบบ่อย (FAQ)
          </h2>
          <p class="text-gray-600 dark:text-gray-400 mb-6">
            รวมคำถามยอดฮิตพร้อมคำตอบ
          </p>

          <?php
          $_faqs = [
            ['q' => 'ลืมรหัสผ่านต้องทำอย่างไร?', 'a' => 'กด "ลืมรหัสผ่าน" ที่หน้า login ระบบจะส่งลิงก์รีเซ็ตไปยังอีเมลของคุณ ลิงก์มีอายุ 1 ชั่วโมง หากไม่ได้รับอีเมล ตรวจสอบโฟลเดอร์ Spam'],
            ['q' => 'Face Search แม่นยำแค่ไหน?', 'a' => 'ระบบใช้ AI face recognition มีความแม่นยำประมาณ 95%+ ในสภาพแสงปกติ แนะนำใช้ภาพ selfie หน้าตรงและแสงสว่างเพื่อผลลัพธ์ดีที่สุด'],
            ['q' => 'ภาพที่ซื้อแล้วสามารถแก้ไข/ตีพิมพ์ได้หรือไม่?', 'a' => 'ลิขสิทธิ์ยังเป็นของช่างภาพ คุณมีสิทธิ์ใช้เพื่อการส่วนตัว หากต้องการใช้เชิงพาณิชย์ กรุณาติดต่อช่างภาพโดยตรง'],
            ['q' => 'ทำไมการชำระเงินไม่ผ่าน?', 'a' => 'อาจเกิดจาก: ยอดเงินไม่พอ, บัตรหมดอายุ, เกิน limit รายวัน, 3D Secure ไม่ผ่าน กรุณาตรวจสอบกับธนาคาร หรือลองใช้วิธีชำระเงินอื่น'],
            ['q' => 'สามารถขอ refund ได้เมื่อไหร่?', 'a' => 'ภายใน 7 วันนับจากวันซื้อ โดยไม่ได้ดาวน์โหลดภาพ หรือหากมีปัญหาเกี่ยวกับคุณภาพภาพ'],
            ['q' => 'ค่าคอมมิชชันของช่างภาพเท่าไหร่?', 'a' => 'แพลตฟอร์มหัก 15-20% ขึ้นอยู่กับระดับของช่างภาพ (Starter / Pro / Elite) ที่กำหนดจาก performance'],
            ['q' => 'อัพโหลดภาพได้ขนาดเท่าไหร่?', 'a' => 'ขนาดไฟล์สูงสุด 50MB ต่อภาพ รองรับ JPG, PNG, WebP ขั้นต่ำ 1920×1080 เพื่อคุณภาพที่ดี'],
            ['q' => 'ข้อมูลของฉันปลอดภัยหรือไม่?', 'a' => 'เราใช้ HTTPS/TLS, เข้ารหัสข้อมูลสำคัญใน database, ปฏิบัติตาม PDPA พ.ร.บ.คุ้มครองข้อมูลส่วนบุคคล ไม่ขายข้อมูลให้บุคคลที่สาม'],
            ['q' => 'รองรับการใช้งานบนมือถือหรือไม่?', 'a' => 'Responsive design ทุกหน้า รองรับ iOS, Android, และ browser หลัก (Chrome, Safari, Firefox, Edge)'],
            ['q' => 'ช่างภาพกี่วันถึงได้รับการอนุมัติ?', 'a' => 'ปกติ 1-3 วันทำการ ขึ้นอยู่กับจำนวนคำขอ portfolio ที่ครบถ้วนจะได้รับการอนุมัติเร็วขึ้น'],
            ['q' => 'ภาพของฉันจะถูกใช้เป็น AI training หรือไม่?', 'a' => 'ไม่ใช้ ระบบใช้ AI เฉพาะการประมวลผลเพื่อให้บริการ (เช่น face search) เท่านั้น ภาพจะไม่ถูกส่งออกไปเทรน AI model'],
            ['q' => 'สามารถลบบัญชีได้หรือไม่?', 'a' => 'สามารถขอลบบัญชีได้ที่ "โปรไฟล์ > ตั้งค่า > ลบบัญชี" ระบบจะลบข้อมูลภายใน 30 วัน ยกเว้นข้อมูลที่ต้องเก็บตามกฎหมาย'],
            ['q' => 'Admin ติดต่อที่ไหน?', 'a' => 'ส่ง Support Ticket ที่ "โปรไฟล์ > Support" หรืออีเมลไปที่ support@example.com ทีมงานจะติดต่อกลับภายใน 24 ชั่วโมง'],
          ];
          ?>

          <div class="space-y-2 manual-hit">
            <?php $__currentLoopData = $_faqs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $faq): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <details class="faq-item bg-gray-50 dark:bg-white/5 rounded-xl border border-gray-100 dark:border-white/10 overflow-hidden">
              <summary class="flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-gray-100 dark:hover:bg-white/10 transition list-none">
                <span class="w-7 h-7 rounded-full bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-300 flex items-center justify-center text-xs font-bold shrink-0">
                  <?php echo e($i + 1); ?>

                </span>
                <h6 class="font-semibold text-slate-800 dark:text-gray-100 flex-1 mb-0 text-sm"><?php echo e($faq['q']); ?></h6>
                <i class="bi bi-chevron-down faq-chev text-gray-400 shrink-0"></i>
              </summary>
              <div class="px-4 pb-4 pl-14">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-0"><?php echo e($faq['a']); ?></p>
              </div>
            </details>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
          </div>

          
          <div class="mt-8 p-6 rounded-2xl border border-dashed border-indigo-200 dark:border-indigo-500/30 bg-indigo-50/50 dark:bg-indigo-500/5 text-center">
            <i class="bi bi-chat-heart text-3xl text-indigo-500 dark:text-indigo-400 mb-2 block"></i>
            <h5 class="font-bold text-slate-800 dark:text-gray-100 mb-1">ยังไม่พบคำตอบที่ต้องการ?</h5>
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">
              ติดต่อทีมซัพพอร์ต เราพร้อมช่วยเหลือคุณ
            </p>
            <a href="<?php echo e(route('support.index', [], false)); ?>"
               class="inline-flex items-center gap-2 px-5 py-2 rounded-full bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white font-semibold text-sm transition no-print">
              <i class="bi bi-life-preserver"></i> เปิด Support Ticket
            </a>
          </div>
        </section>

      </div>

      
      <div class="mt-6 text-center no-print">
        <a href="#getting-started" class="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5 transition">
          <i class="bi bi-arrow-up-circle"></i> กลับด้านบน
        </a>
      </div>
    </main>
  </div>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
  function manualApp() {
    return {
      sections: [
        { id: 'getting-started', title: '1. เริ่มต้นใช้งาน', icon: 'rocket-takeoff' },
        { id: 'for-customers', title: '2. สำหรับลูกค้า', icon: 'person-heart' },
        { id: 'for-photographers', title: '3. สำหรับช่างภาพ', icon: 'camera-reels' },
        { id: 'blog-ai', title: '4. Blog & AI', icon: 'robot' },
        { id: 'admin-panel', title: '5. Admin Panel', icon: 'speedometer2' },
        { id: 'payment-finance', title: '6. การชำระเงิน & การเงิน', icon: 'cash-coin' },
        { id: 'security', title: '7. Security', icon: 'shield-check' },
        { id: 'faq', title: '8. FAQ', icon: 'patch-question' },
      ],
      filteredSections: [],
      active: 'getting-started',
      activeTitle: '',
      mobileTocOpen: false,
      searchQuery: '',
      observer: null,

      init() {
        this.filteredSections = this.sections.slice();
        this.activeTitle = this.sections[0].title;

        // Intersection Observer for active section highlighting
        this.$nextTick(() => {
          this.setupObserver();
        });

        // Listen to hash change for direct link jump
        window.addEventListener('hashchange', () => {
          const hash = location.hash.replace('#', '');
          if (hash) {
            this.active = hash;
            const s = this.sections.find(x => x.id === hash);
            if (s) this.activeTitle = s.title;
          }
        });
      },

      setupObserver() {
        const opts = { root: null, rootMargin: '-80px 0px -60% 0px', threshold: 0 };
        this.observer = new IntersectionObserver((entries) => {
          entries.forEach(e => {
            if (e.isIntersecting) {
              this.active = e.target.id;
              const s = this.sections.find(x => x.id === e.target.id);
              if (s) this.activeTitle = s.title;
            }
          });
        }, opts);

        document.querySelectorAll('.manual-section').forEach(el => this.observer.observe(el));
      },

      filterSections() {
        const q = (this.searchQuery || '').trim().toLowerCase();
        if (!q) {
          this.filteredSections = this.sections.slice();
          this.clearHighlights();
          return;
        }

        // Filter by title, keywords, or content
        this.filteredSections = this.sections.filter(s => {
          const el = document.getElementById(s.id);
          if (!el) return false;
          const title = (el.dataset.title || s.title || '').toLowerCase();
          const keywords = (el.dataset.keywords || '').toLowerCase();
          const text = el.innerText.toLowerCase();
          return title.includes(q) || keywords.includes(q) || text.includes(q);
        });

        this.highlightMatches(q);
      },

      highlightMatches(q) {
        this.clearHighlights();
        if (!q) return;
        document.querySelectorAll('.manual-section').forEach(section => {
          // Walk text nodes safely
          const walker = document.createTreeWalker(section, NodeFilter.SHOW_TEXT, {
            acceptNode(node) {
              if (!node.nodeValue || !node.nodeValue.trim()) return NodeFilter.FILTER_REJECT;
              const parent = node.parentElement;
              if (!parent) return NodeFilter.FILTER_REJECT;
              if (['SCRIPT','STYLE','MARK','CODE','PRE'].includes(parent.tagName)) return NodeFilter.FILTER_REJECT;
              return NodeFilter.FILTER_ACCEPT;
            }
          });
          const toReplace = [];
          let node;
          while (node = walker.nextNode()) {
            const idx = node.nodeValue.toLowerCase().indexOf(q);
            if (idx !== -1) toReplace.push({ node, idx, len: q.length });
          }
          toReplace.forEach(({ node, idx, len }) => {
            const before = node.nodeValue.slice(0, idx);
            const hit = node.nodeValue.slice(idx, idx + len);
            const after = node.nodeValue.slice(idx + len);
            const frag = document.createDocumentFragment();
            if (before) frag.appendChild(document.createTextNode(before));
            const mark = document.createElement('mark');
            mark.textContent = hit;
            frag.appendChild(mark);
            if (after) frag.appendChild(document.createTextNode(after));
            node.parentNode.replaceChild(frag, node);
          });
        });
      },

      clearHighlights() {
        document.querySelectorAll('.manual-section mark').forEach(m => {
          const parent = m.parentNode;
          parent.replaceChild(document.createTextNode(m.textContent), m);
          parent.normalize();
        });
      },

      isDesktop() {
        return window.innerWidth >= 1024;
      },
    };
  }
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.admin', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\xampp\htdocs\photo-gallery-pgsql\resources\views/admin/manual.blade.php ENDPATH**/ ?>