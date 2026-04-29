@extends('layouts.admin')
@section('title', 'แก้ไข Changelog')
@section('content')
<div class="flex items-center justify-between mb-4">
    <h4 class="font-bold tracking-tight flex items-center gap-2"><i class="bi bi-pencil text-purple-500"></i>แก้ไข: {{ $entry->title }}</h4>
    <a href="{{ route('admin.changelog.index') }}" class="text-sm text-gray-500 dark:text-gray-400 hover:text-indigo-500"><i class="bi bi-arrow-left mr-1"></i>กลับ</a>
</div>
<form action="{{ route('admin.changelog.update', $entry) }}" method="POST" class="bg-white dark:bg-slate-800 rounded-xl p-6 border border-gray-100 dark:border-white/5">
    @csrf @method('PUT')
    @include('admin.changelog._form')
</form>
@endsection
