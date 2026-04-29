@extends('layouts.admin')
@section('title', 'Edit Landing Page: ' . $page->title)
@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">

    <div class="mb-4">
        <a href="{{ route('admin.marketing.landing.index') }}" class="text-sm text-slate-500 hover:text-indigo-500">
            <i class="bi bi-arrow-left"></i> กลับ
        </a>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
            <i class="bi bi-pencil text-indigo-500"></i> แก้ไข: {{ $page->title }}
        </h1>
        <p class="text-xs text-slate-500 font-mono">/lp/{{ $page->slug }}</p>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-500 text-sm">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-500/10 border border-rose-500/30">
            <ul class="text-sm text-rose-500 list-disc list-inside">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.marketing.landing.update', $page) }}">
        @csrf @method('PUT')
        @include('admin.marketing.landing._form', ['page' => $page])
    </form>
</div>
@endsection
