@extends('layouts.admin')

@section('title', 'เพิ่มสินค้าดิจิทัล')

@section('content')
@include('admin.products._form', ['product' => null, 'mode' => 'create'])
@endsection
