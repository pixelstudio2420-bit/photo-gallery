@extends('layouts.app')

@section('title', 'ศูนย์ช่วยเหลือ · คำถามที่พบบ่อย')

@section('content')
<div class="flex justify-center">
  <div class="w-full max-w-3xl">
    <div class="text-center mb-8">
      <div class="flex items-center justify-center mx-auto mb-3" style="width:56px;height:56px;background:linear-gradient(135deg,#6366f1,#4f46e5);border-radius:14px;">
        <i class="bi bi-question-circle-fill text-white" style="font-size:1.5rem;"></i>
      </div>
      {{-- Page H1 — required for SEO; was missing pre-2026-04-28 audit. --}}
      <h1 class="text-3xl sm:text-4xl font-extrabold text-slate-900 dark:text-white mb-1" style="letter-spacing:-0.02em;">ศูนย์ช่วยเหลือ</h1>
      <p class="text-gray-500 dark:text-gray-400">คำถามที่พบบ่อย · คู่มือการใช้งาน · ติดต่อทีม</p>
    </div>

    <div id="faqAccordion">
      @php
        $faqs = [
          ['q' => 'ฉันจะซื้อภาพถ่ายได้อย่างไร?', 'a' => 'เลือกดูอีเวนต์ที่ต้องการ เลือกภาพที่ชอบ เพิ่มลงตะกร้า แล้วดำเนินการชำระเงิน สามารถชำระผ่าน PromptPay โอนเงิน หรือบัตรเครดิตได้'],
          ['q' => 'ฉันจะดาวน์โหลดภาพถ่ายได้อย่างไร?', 'a' => 'หลังจากชำระเงินเรียบร้อยแล้ว คุณจะได้รับลิงก์ดาวน์โหลดในหน้าคำสั่งซื้อ ลิงก์มีอายุ 7 วัน'],
          ['q' => 'รองรับการชำระเงินช่องทางไหนบ้าง?', 'a' => 'เรารองรับ PromptPay QR Code, โอนเงินผ่านธนาคาร และบัตรเครดิต/เดบิต (Visa, MasterCard)'],
          ['q' => 'ภาพถ่ายมีลายน้ำไหม?', 'a' => 'ภาพตัวอย่างจะมีลายน้ำ แต่ภาพที่ดาวน์โหลดหลังชำระเงินจะเป็นภาพคุณภาพสูงไม่มีลายน้ำ'],
          ['q' => 'สามารถขอคืนเงินได้ไหม?', 'a' => 'กรุณาติดต่อทีมงานภายใน 24 ชั่วโมงหลังชำระเงิน เราจะพิจารณาเป็นกรณีไป'],
        ];
      @endphp

      @foreach($faqs as $i => $faq)
      <div class="mb-3 bg-white rounded-2xl shadow-sm overflow-hidden" style="border-radius:16px;">
        <details {{ $i === 0 ? 'open' : '' }} class="group">
          <summary class="flex items-center gap-2 px-5 py-4 cursor-pointer font-semibold text-gray-800 hover:bg-gray-50 transition" style="font-size:0.95rem;list-style:none;">
            <i class="bi bi-chat-dots mr-2" style="color:#6366f1;"></i>
            {{ $faq['q'] }}
            <i class="bi bi-chevron-down ml-auto transition-transform group-open:rotate-180" style="color:#94a3b8;"></i>
          </summary>
          <div class="px-5 pb-4 text-gray-500" style="line-height:1.8;font-size:0.9rem;">
            {{ $faq['a'] }}
          </div>
        </details>
      </div>
      @endforeach
    </div>

    {{-- CTA --}}
    <div class="text-center mt-8 p-6 rounded-2xl" style="background:linear-gradient(135deg,rgba(99,102,241,0.05),rgba(244,63,94,0.05));border-radius:20px;">
      <i class="bi bi-headset mb-2" style="font-size:2rem;color:#6366f1;"></i>
      <p class="text-gray-500 mb-3">ยังมีคำถามอื่นอีก?</p>
      <a href="{{ route('contact') }}" class="inline-block px-6 py-2 text-white font-medium rounded-full" style="background:linear-gradient(135deg,#6366f1,#4f46e5);">
        <i class="bi bi-envelope mr-1"></i> ติดต่อเรา
      </a>
    </div>
  </div>
</div>
@endsection
