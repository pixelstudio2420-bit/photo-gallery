@extends('layouts.app')

@section('title', 'ศูนย์ช่วยเหลือ · คำถามที่พบบ่อย')

@section('content')
<div class="flex justify-center px-3">
  <div class="w-full max-w-3xl">

    {{-- ── Header ───────────────────────────────────────────────── --}}
    <div class="text-center mb-8">
      <div class="flex items-center justify-center mx-auto mb-3"
           style="width:56px;height:56px;background:linear-gradient(135deg,#6366f1,#4f46e5);border-radius:14px;">
        <i class="bi bi-question-circle-fill text-white" style="font-size:1.5rem;"></i>
      </div>
      {{-- Page H1 — required for SEO; was missing pre-2026-04-28 audit. --}}
      <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white mb-1"
          style="letter-spacing:-0.02em;">ศูนย์ช่วยเหลือ</h1>
      <p class="text-gray-500 dark:text-gray-400">คำถามที่พบบ่อย · คู่มือการใช้งาน · ติดต่อทีมงาน</p>
    </div>

    {{--
      FAQs grouped into customer / payment / photographer sections.
      Content reflects the current platform: LINE login, AI Face Search,
      bundle pricing, LINE delivery after checkout, photographer
      registration flow, retention policy, etc.

      Each section's first item opens by default; the rest collapse so
      the page doesn't dump everything at once. Dark-mode classes on
      every text/bg/border surface so the page stays readable on both
      light and dark themes.
    --}}
    @php
      $sections = [
        'ลูกค้า · ซื้อภาพ' => [
          [
            'q' => 'ฉันจะซื้อภาพถ่ายจากอีเวนต์ของฉันได้อย่างไร?',
            'a' => 'เลือกอีเวนต์ที่ต้องการในหน้าค้นหา → กดปุ่ม "ค้นหาด้วยใบหน้า" (AI Face Search) เพื่อให้ระบบหารูปของคุณ หรือเลือกภาพที่ชอบเอง → เพิ่มลงตะกร้า → ชำระเงิน → ระบบจะส่งลิงก์ดาวน์โหลดเข้า LINE และอีเมลของคุณอัตโนมัติ',
          ],
          [
            'q' => 'AI Face Search คืออะไร และใช้งานอย่างไร?',
            'a' => 'AI Face Search เป็นระบบค้นหารูปของคุณจากอีเวนต์โดยใช้รูปใบหน้า อัปโหลดรูปเซลฟี่ 1 รูป ระบบจะคัดรูปที่มีคุณอยู่จากภาพหลายพันภาพภายใน 3 วินาที — ฟรีไม่มีค่าใช้จ่าย ไม่ต้องเลื่อนหารูปเอง',
          ],
          [
            'q' => 'มี Bundle ราคาพิเศษไหม ถ้าซื้อหลายภาพ?',
            'a' => 'มีครับ ทุกอีเวนต์มี Bundle ให้เลือก เช่น แพ็กเกจ 3 ภาพ / 6 ภาพ / 10 ภาพ ในราคาคุ้มกว่าซื้อเดี่ยว 30-50% รวมทั้ง Face Match Bundle (ทุกรูปที่มีหน้าคุณ) และ Event All (ทุกรูปในอีเวนต์)',
          ],
          [
            'q' => 'ฉันจะดาวน์โหลดภาพหลังจ่ายเงินได้อย่างไร?',
            'a' => 'หลังชำระเงินสำเร็จ ระบบจะส่งลิงก์ดาวน์โหลดเข้า LINE ทันที (ถ้าล็อกอินด้วย LINE) และเข้าอีเมลของคุณด้วย ลิงก์มีอายุ 7 วัน ดาวน์โหลดซ้ำได้สูงสุด 5 ครั้ง หรือเข้าหน้า "คำสั่งซื้อของฉัน" ก็ได้',
          ],
          [
            'q' => 'ภาพที่ดาวน์โหลดมีลายน้ำไหม?',
            'a' => 'ภาพตัวอย่างในเว็บมีลายน้ำเพื่อป้องกันการคัดลอก แต่ภาพที่ดาวน์โหลดหลังชำระเงินจะเป็นภาพต้นฉบับความละเอียดสูง ไม่มีลายน้ำใดๆ พร้อมใช้พิมพ์/อัปโหลดโซเชียลได้ทันที',
          ],
        ],

        'การชำระเงิน · คืนเงิน' => [
          [
            'q' => 'รองรับการชำระเงินช่องทางไหนบ้าง?',
            'a' => 'PromptPay QR Code (แนะนำ — เร็วที่สุด), บัตรเครดิต/เดบิต (Visa, MasterCard, JCB) ผ่าน payment gateway มาตรฐานสากล, โอนเงินผ่านธนาคาร (อนุมัติด้วยการอัปโหลดสลิป — AI ตรวจสอบอัตโนมัติ), True Wallet สำหรับยอดต่ำกว่า 1,000 บาท',
          ],
          [
            'q' => 'ใบเสร็จมีให้ไหม?',
            'a' => 'ทุกออเดอร์มีใบเสร็จออนไลน์ดูได้ใน Dashboard ของคุณ พร้อมเลขใบเสร็จและประวัติการชำระย้อนหลัง — หากต้องการใบกำกับภาษีนิติบุคคลกรุณาติดต่อทีมงานเพื่อขอเป็นรายกรณี',
          ],
          [
            'q' => 'ขอคืนเงินได้ไหม นโยบายเป็นอย่างไร?',
            'a' => 'หากภาพมีปัญหาทางเทคนิค (ไฟล์เสีย, ไม่ตรงตามตัวอย่าง) คืนเงินเต็มจำนวนภายใน 7 วัน กรุณาติดต่อทีมงานพร้อมแนบรูปประกอบ — สำหรับเหตุผลอื่น เช่น "ไม่ชอบภาพ" จะพิจารณาเป็นกรณีไป',
          ],
        ],

        'ช่างภาพ · ขายภาพ' => [
          [
            'q' => 'อยากเป็นช่างภาพในระบบ ต้องเริ่มยังไง?',
            'a' => 'สมัครสมาชิกฟรี → ไปที่หน้า "สมัครเป็นช่างภาพ" → กรอกข้อมูล + แนบเอกสารยืนยันตัวตน → รอแอดมินอนุมัติ (ภายใน 24-48 ชม.) → เริ่มสร้างอีเวนต์และอัปโหลดภาพได้ทันที',
          ],
          [
            'q' => 'ค่าคอมมิชชั่นเท่าไหร่ ถูกหักเงินยังไง?',
            'a' => 'ค่าคอมมิชชั่นขึ้นกับแพ็กเกจที่เลือก ตั้งแต่ 0% (Studio plan) ถึง 20% (Free plan) — แสดงในหน้า "รายได้" แบบโปร่งใสทุก order ระบบหักจาก gross ก่อนโอน ไม่มีค่าธรรมเนียมแอบซ่อน',
          ],
          [
            'q' => 'เงินเข้าบัญชีเมื่อไหร่ จ่ายผ่านอะไร?',
            'a' => 'เมื่อยอดสะสมถึงขั้นต่ำที่แอดมินกำหนด คุณกด "แจ้งถอน" จาก dashboard — แอดมินจะตรวจและโอนเข้าบัญชีไทยตามรอบ (PromptPay หรือเลขบัญชี) · ทุกรายการมี audit trail ตรวจย้อนหลังได้ในหน้า "รายได้ของฉัน"',
          ],
          [
            'q' => 'ภาพต้นฉบับเก็บไว้บนระบบนานแค่ไหน?',
            'a' => 'ตามแพ็กเกจที่เลือก: Free 30 วัน, Starter 90 วัน, Pro 1 ปี, Business+ ไม่จำกัด เมื่อครบเวลาระบบจะลบไฟล์ต้นฉบับเพื่อประหยัด storage แต่ภาพ thumbnail+watermarked ยังเก็บอยู่ในพอร์ตโฟลิโอ',
          ],
          [
            'q' => 'AI Face Search ใช้ฟรีไหม สำหรับช่างภาพ?',
            'a' => 'ใช้ฟรี ไม่จำกัดจำนวนหน้าที่ระบบสแกน — เปิดใช้ได้ต่ออีเวนต์ (per event opt-in/out เพื่อให้สอดคล้องกับ PDPA) ระบบสร้าง face embedding อัตโนมัติเมื่ออัปโหลดภาพเสร็จ',
          ],
        ],

        'บัญชี · ความปลอดภัย' => [
          [
            'q' => 'จำเป็นต้องล็อกอินด้วย LINE ไหม?',
            'a' => 'ไม่จำเป็น — เลือกได้ระหว่าง LINE, Google, อีเมล/รหัสผ่าน — แต่แนะนำ LINE เพราะลูกค้าจะได้รับลิงก์ดาวน์โหลดเข้า LINE chat ทันทีหลังจ่ายเงิน ไม่ต้องเปิดอีเมลตรวจ',
          ],
          [
            'q' => 'ลืมรหัสผ่าน ทำอย่างไร?',
            'a' => 'ที่หน้า login กดลิงก์ "ลืมรหัสผ่าน" → กรอกอีเมล → ระบบส่งลิงก์ตั้งรหัสใหม่ที่อายุ 60 นาที — หรือใช้ LINE Login เข้าเลยก็ได้ ไม่ต้องตั้งรหัสใหม่',
          ],
          [
            'q' => 'ข้อมูลส่วนตัวของฉันปลอดภัยไหม?',
            'a' => 'รักษาตาม PDPA — ไฟล์ภาพเก็บใน enterprise cloud storage (เข้ารหัส at-rest ระดับ AES-256), อัปโหลดผ่าน HTTPS, รหัสผ่าน hash ด้วย bcrypt, ใบหน้าใน face index ลบได้ตามคำขอ ไม่ขายข้อมูลให้ third party ใดๆ',
          ],
        ],
      ];
    @endphp

    @foreach($sections as $sectionTitle => $items)
      <h2 class="text-sm font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400 mt-6 mb-3 px-1">
        <i class="bi bi-bookmark-fill mr-1"></i>{{ $sectionTitle }}
      </h2>

      @foreach($items as $i => $faq)
        <div class="mb-2.5 bg-white dark:bg-slate-800 rounded-2xl shadow-sm dark:shadow-black/20 border border-gray-100 dark:border-white/10 overflow-hidden">
          <details {{ $loop->parent->first && $i === 0 ? 'open' : '' }} class="group">
            <summary class="flex items-center gap-2 px-5 py-4 cursor-pointer font-semibold text-gray-800 dark:text-gray-100 hover:bg-gray-50 dark:hover:bg-white/5 transition"
                     style="font-size:0.95rem;list-style:none;">
              <i class="bi bi-chat-dots mr-2 text-indigo-500 dark:text-indigo-400"></i>
              <span class="flex-1">{{ $faq['q'] }}</span>
              <i class="bi bi-chevron-down ml-2 text-gray-400 dark:text-gray-500 transition-transform group-open:rotate-180"></i>
            </summary>
            <div class="px-5 pb-4 text-gray-600 dark:text-gray-300 border-t border-gray-100 dark:border-white/10 pt-3"
                 style="line-height:1.8;font-size:0.9rem;">
              {{ $faq['a'] }}
            </div>
          </details>
        </div>
      @endforeach
    @endforeach

    {{-- ── CTA — still have questions? ───────────────────────────── --}}
    <div class="text-center mt-8 p-6 rounded-2xl border border-indigo-100/60 dark:border-indigo-400/15"
         style="background:linear-gradient(135deg,rgba(99,102,241,0.05),rgba(244,63,94,0.05));border-radius:20px;">
      <i class="bi bi-headset mb-2 text-indigo-500 dark:text-indigo-400" style="font-size:2rem;"></i>
      <p class="text-gray-600 dark:text-gray-300 mb-3">ยังหาคำตอบไม่เจอ?</p>
      <a href="{{ route('contact') }}"
         class="inline-flex items-center gap-2 px-6 py-2.5 text-white font-semibold rounded-full shadow-md hover:shadow-lg transition"
         style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
        <i class="bi bi-envelope"></i> ติดต่อทีมงาน
      </a>
    </div>

  </div>
</div>
@endsection
