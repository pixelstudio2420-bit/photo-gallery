@extends('layouts.admin')

@section('title', 'แก้ไขสินค้าดิจิทัล')

@section('content')
@include('admin.products._form', ['product' => $product, 'mode' => 'edit'])
@endsection
