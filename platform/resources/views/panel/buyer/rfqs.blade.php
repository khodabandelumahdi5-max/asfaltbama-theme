@extends('layouts.panel')
@section('title', 'استعلام‌های من')

@section('content')
<div class="main-head"><h1>استعلام‌های من</h1><a href="{{ route('rfqs.create') }}" class="btn btn-amber">＋ استعلام جدید</a></div>
@include('panel.buyer._table')
<div class="pagination">{{ $rfqs->links() }}</div>
@endsection
