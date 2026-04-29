@extends('layouts.admin')

@section('title', 'เพิ่มลิงก์ Affiliate')

@section('content')
<div class="mb-6">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('admin.blog.affiliate.index') }}"
           class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white">
            <i class="bi bi-plus-circle text-purple-500 mr-2"></i>เพิ่มลิงก์ Affiliate
        </h2>
    </div>
</div>

@include('admin.blog.affiliate._form', ['link' => (object) [
    'exists' => false, 'name' => '', 'slug' => '', 'destination_url' => '', 'provider' => '',
    'campaign' => '', 'commission_rate' => '', 'description' => '', 'image' => null,
    'nofollow' => true, 'is_active' => true, 'expires_at' => null,
]])
@endsection
