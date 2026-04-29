@extends('layouts.app')

@section('title', '500 - Server Error')

@section('content')
<div class="text-center py-20">
  <div class="mb-4">
    <i class="bi bi-bug text-6xl text-red-500"></i>
  </div>
  <h1 class="text-4xl font-bold">500</h1>
  <p class="text-lg text-gray-500 mb-4">Something went wrong. Please try again later.</p>
  <a href="{{ url('/') }}" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-6 py-3 rounded-lg transition">
    <i class="bi bi-house mr-1"></i> Back to Home
  </a>
</div>
@endsection
