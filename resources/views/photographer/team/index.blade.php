@extends('layouts.photographer')

@section('title', 'จัดการทีม')

@php
  use App\Models\PhotographerTeamMember;
  $isAtCap = $seatsAvail <= 0;
  $allowsTeam = $maxAdditional > 0;
@endphp

@section('content')
@include('photographer.partials.page-hero', [
  'icon'     => 'bi-people-fill',
  'eyebrow'  => 'บริการเสริม',
  'title'    => 'จัดการทีม',
  'subtitle' => 'เชิญสมาชิกร่วมจัดการอีเวนต์ · อัปโหลด · ดูยอดขาย (Business+)',
  'actions'  => '<a href="'.route('photographer.subscription.plans').'" class="pg-btn-ghost"><i class="bi bi-box"></i> ดูแผนทั้งหมด</a>',
])

@if(session('success'))
  <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-900 text-sm px-4 py-3">
    <i class="bi bi-check-circle-fill mr-1.5"></i>{{ session('success') }}
  </div>
@endif
@if(session('error'))
  <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 text-rose-900 text-sm px-4 py-3">
    <i class="bi bi-exclamation-triangle-fill mr-1.5"></i>{{ session('error') }}
  </div>
@endif

{{-- Seats summary --}}
<div class="pg-card p-5 mb-6">
  <div class="flex items-center justify-between">
    <div>
      <p class="text-xs uppercase tracking-wider text-gray-500 font-medium">ที่นั่งทีมที่ใช้</p>
      <p class="font-bold text-2xl text-gray-900 mt-1">
        {{ count($members) }}
        <span class="text-sm font-medium text-gray-500">/ {{ $maxAdditional }} ที่นั่ง</span>
      </p>
    </div>
    @if(!$allowsTeam)
      <a href="{{ route('photographer.subscription.plans') }}"
         class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
        <i class="bi bi-arrow-up-circle"></i> อัปเกรดเพื่อเพิ่มทีม
      </a>
    @endif
  </div>
  @if(!$allowsTeam)
    <p class="text-xs text-amber-600 mt-3">
      <i class="bi bi-info-circle mr-1"></i>
      แผนปัจจุบันยังไม่รองรับการเชิญทีม — Business มี 3 ที่นั่ง, Studio 10 ที่นั่ง
    </p>
  @endif
</div>

{{-- Invite form --}}
@if($allowsTeam && !$isAtCap)
<div class="pg-card p-5 mb-6">
  <h5 class="font-semibold text-gray-900 mb-4">
    <i class="bi bi-envelope-plus mr-1.5 text-indigo-500"></i>เชิญสมาชิกใหม่
  </h5>
  <form method="POST" action="{{ route('photographer.team.invite') }}" class="grid grid-cols-1 md:grid-cols-3 gap-3">
    @csrf
    <div class="md:col-span-2">
      <label class="text-xs text-gray-600 mb-1 block">อีเมลสมาชิก</label>
      <input type="email" name="email" required
             class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
             placeholder="member@example.com">
    </div>
    <div>
      <label class="text-xs text-gray-600 mb-1 block">บทบาท</label>
      <select name="role" class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        <option value="editor">Editor — แก้ไขอีเวนต์ + อัปโหลด</option>
        <option value="admin">Admin — เต็มสิทธิ์</option>
        <option value="viewer">Viewer — อ่านอย่างเดียว</option>
      </select>
    </div>
    <div class="md:col-span-3 flex justify-end">
      <button class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
        <i class="bi bi-send"></i> ส่งคำเชิญ
      </button>
    </div>
  </form>
</div>
@elseif($allowsTeam && $isAtCap)
<div class="pg-alert pg-alert--warning mb-6">
  <i class="bi bi-exclamation-triangle-fill"></i>
  <div>ที่นั่งทีมเต็มแล้ว — กรุณายกเลิกสมาชิกเดิมหรืออัปเกรดแผน</div>
</div>
@endif

{{-- Members list --}}
<div class="pg-card overflow-hidden pg-anim d2">
  <div class="pg-card-header">
    <h5 class="pg-section-title m-0"><i class="bi bi-people"></i> สมาชิกในทีม</h5>
  </div>
  @if($members->isEmpty())
    <div class="pg-empty">
      <div class="pg-empty-icon"><i class="bi bi-people"></i></div>
      <p class="font-medium">ยังไม่มีสมาชิกในทีม</p>
      <p class="text-xs mt-1">เชิญสมาชิกใหม่ผ่านฟอร์มด้านบน</p>
    </div>
  @else
    <div class="pg-table-wrap" style="border:none;border-radius:0;box-shadow:none;">
      <table class="pg-table">
        <thead>
          <tr>
            <th>อีเมล / ชื่อ</th>
            <th>บทบาท</th>
            <th>สถานะ</th>
            <th class="text-end">จัดการ</th>
          </tr>
        </thead>
        <tbody>
          @foreach($members as $m)
            <tr>
              <td>
                <p class="font-semibold text-gray-900 m-0">
                  {{ $m->user?->name ?? $m->invite_email }}
                </p>
                @if($m->user)
                  <p class="text-xs text-gray-500 m-0">{{ $m->user->email }}</p>
                @endif
              </td>
              <td>
                <form method="POST" action="{{ route('photographer.team.role', $m->id) }}" class="flex items-center gap-2">
                  @csrf
                  <select name="role" onchange="this.form.submit()"
                          class="text-xs border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500/30 focus:border-indigo-500">
                    @foreach(['admin'=>'Admin','editor'=>'Editor','viewer'=>'Viewer'] as $code => $label)
                      <option value="{{ $code }}" @selected($m->role===$code)>{{ $label }}</option>
                    @endforeach
                  </select>
                </form>
              </td>
              <td>
                @if($m->status === PhotographerTeamMember::STATUS_PENDING)
                  <span class="pg-pill pg-pill--amber">รอยอมรับ</span>
                  @if($m->invite_token)
                    <button type="button"
                            class="text-[11px] text-indigo-600 hover:underline ml-1 font-bold"
                            onclick="navigator.clipboard.writeText('{{ route('team.accept', ['token' => $m->invite_token]) }}'); this.textContent='คัดลอกแล้ว';">
                      คัดลอกลิงก์
                    </button>
                  @endif
                @elseif($m->status === PhotographerTeamMember::STATUS_ACTIVE)
                  <span class="pg-pill pg-pill--green">ใช้งาน</span>
                @endif
              </td>
              <td class="text-end">
                <form method="POST" action="{{ route('photographer.team.revoke', $m->id) }}"
                      onsubmit="return confirm('ยืนยันยกเลิกสิทธิ์?');" class="inline">
                  @csrf
                  @method('DELETE')
                  <button class="text-rose-600 hover:text-rose-700 text-xs font-bold">
                    <i class="bi bi-x-circle"></i> ยกเลิก
                  </button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
