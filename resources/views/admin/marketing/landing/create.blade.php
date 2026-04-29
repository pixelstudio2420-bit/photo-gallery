@extends('layouts.admin')
@section('title', 'New Landing Page')
@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">

    <div class="mb-4">
        <a href="{{ route('admin.marketing.landing.index') }}" class="text-sm text-slate-500 hover:text-indigo-500">
            <i class="bi bi-arrow-left"></i> กลับ
        </a>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
            <i class="bi bi-plus-circle text-indigo-500"></i> สร้าง Landing Page ใหม่
        </h1>
    </div>

    @if($errors->any())
        <div class="mb-4 p-3 rounded-lg bg-rose-500/10 border border-rose-500/30">
            <ul class="text-sm text-rose-500 list-disc list-inside">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.marketing.landing.store') }}">
        @csrf
        @include('admin.marketing.landing._form')
    </form>
</div>
@endsection
