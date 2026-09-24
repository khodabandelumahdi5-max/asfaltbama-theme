@extends('layouts.panel')
@section('title', 'مخاطب جدید')

@section('content')
<div class="main-head"><h1>مخاطب جدید</h1></div>
<form method="POST" action="{{ route('panel.crm.store') }}" class="card" style="max-width:760px">
    @csrf
    @include('panel.crm._fields')
    <div class="row"><button class="btn btn-amber">ذخیره</button><a href="{{ route('panel.crm.index') }}" class="btn btn-ghost">انصراف</a></div>
</form>
@endsection
