@extends('layouts.admin')

@section('title', 'เพิ่ม Alert Rule')

@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2">
        <i class="bi bi-bell-fill text-rose-500"></i>
        เพิ่ม Alert Rule
    </h4>
    <a href="{{ route('admin.alerts.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-indigo-500">
        <i class="bi bi-arrow-left mr-1"></i>กลับ
    </a>
</div>

<form action="{{ route('admin.alerts.store') }}" method="POST"
      class="bg-white dark:bg-slate-800 rounded-xl p-6 border border-gray-100 dark:border-white/5">
    @csrf
    @include('admin.alerts._form')
</form>
@endsection
