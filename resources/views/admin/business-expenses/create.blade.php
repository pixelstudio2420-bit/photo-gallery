@extends('layouts.admin')

@section('title', 'เพิ่มค่าใช้จ่าย')

@section('content')
<div class="flex items-center justify-between mb-4">
  <h4 class="font-bold mb-0 tracking-tight">
    <i class="bi bi-plus-square mr-2 text-indigo-500"></i>เพิ่มค่าใช้จ่าย
  </h4>
  <a href="{{ route('admin.business-expenses.index') }}"
     class="px-4 py-2 border border-gray-200 dark:border-white/5 dark:text-gray-200 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-slate-700">
    <i class="bi bi-arrow-left mr-1"></i>กลับ
  </a>
</div>

@if ($errors->any())
  <div class="bg-rose-50 dark:bg-rose-900/20 border border-rose-200 dark:border-rose-500/30 text-rose-800 dark:text-rose-200 rounded-xl p-3 mb-4 text-sm">
    <ul class="list-disc list-inside">
      @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
  </div>
@endif

<form action="{{ route('admin.business-expenses.store') }}" method="POST"
      class="bg-white dark:bg-slate-800 border border-gray-100 dark:border-white/5 rounded-xl p-5">
  @csrf
  @include('admin.business-expenses._form')
  <div class="flex justify-end gap-2 mt-5 pt-4 border-t border-gray-100 dark:border-white/5">
    <a href="{{ route('admin.business-expenses.index') }}"
       class="px-5 py-2 border border-gray-200 dark:border-white/5 text-gray-700 dark:text-gray-200 rounded-lg text-sm">ยกเลิก</a>
    <button type="submit"
            class="px-5 py-2 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded-lg text-sm font-medium hover:from-indigo-600 hover:to-indigo-700">
      <i class="bi bi-check-lg mr-1"></i>บันทึก
    </button>
  </div>
</form>
@endsection
