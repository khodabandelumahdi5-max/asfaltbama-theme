@extends('layouts.panel')
@section('title', 'مدیریت')

@section('content')
<div class="main-head"><h1>مدیریت بازار</h1></div>
<div class="grid g3">
    <a href="{{ route('panel.admin.companies.index') }}" class="stat"><span>در انتظار تأیید</span><b style="color:var(--bad)">{{ fa_number($pending) }}</b></a>
    <div class="stat"><span>کل تأمین‌کنندگان</span><b>{{ fa_number($companies) }}</b></div>
    <div class="stat"><span>کل استعلام‌ها</span><b>{{ fa_number($rfqs) }}</b></div>
</div>
@endsection
