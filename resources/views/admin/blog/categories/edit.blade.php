@extends('layouts.admin')

@section('title', 'แก้ไขหมวดหมู่')

@section('content')
<div class="mb-6">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('admin.blog.categories.index') }}"
           class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white">
            <i class="bi bi-pencil-square text-amber-500 mr-2"></i>แก้ไขหมวดหมู่
        </h2>
    </div>
    <p class="text-sm text-gray-500 dark:text-gray-400 ml-11">
        กำลังแก้ไข: <span class="font-medium text-slate-600 dark:text-gray-300">{{ $category->name }}</span>
    </p>
</div>

@include('admin.blog.categories._form', ['category' => $category])
@endsection
