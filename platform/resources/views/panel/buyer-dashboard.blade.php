@extends('layouts.panel')
@section('title', 'داشبورد')

@section('content')
<div class="main-head">
    <h1>سلام، {{ auth()->user()->name }} 👋</h1>
    <a href="{{ route('rfqs.create') }}" class="btn btn-amber">＋ استعلام قیمت جدید</a>
</div>
@include('panel.buyer._table', ['rfqs' => $rfqs])
@endsection
