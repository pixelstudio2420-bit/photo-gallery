@extends('layouts.admin')

@section('title', 'สร้างบทความใหม่')

@section('content')
<div class="mb-6">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('admin.blog.posts.index') }}"
           class="w-8 h-8 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h2 class="text-2xl font-bold text-slate-800 dark:text-white">
            <i class="bi bi-plus-circle text-indigo-500 mr-2"></i>สร้างบทความใหม่
        </h2>
    </div>
    <p class="text-sm text-gray-500 dark:text-gray-400 ml-11">กรอกข้อมูลบทความที่ต้องการสร้าง</p>
</div>

@include('admin.blog.posts._form', ['post' => (object) [
    'exists' => false, 'title' => '', 'slug' => '', 'content' => '', 'excerpt' => '',
    'status' => 'draft', 'published_at' => null, 'scheduled_at' => null,
    'visibility' => 'public', 'post_password' => '',
    'category_id' => '', 'featured_image' => '', 'meta_title' => '', 'meta_description' => '',
    'focus_keyword' => '', 'canonical_url' => '', 'is_featured' => false, 'is_affiliate_post' => false,
    'allow_comments' => true, 'schema_type' => 'Article', 'seo_score' => null, 'tags' => null,
    'og_image' => null,
]])
@endsection
