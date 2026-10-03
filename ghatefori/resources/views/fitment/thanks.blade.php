@extends('layouts.shop')
@section('title', 'درخواست ثبت شد')

@section('content')
<div class="container mt" style="max-width:640px">
    <div class="card" style="text-align:center">
        <div style="font-size:3rem">✅</div>
        <h1>درخواست شما ثبت شد</h1>
        <p>کارشناس فنی سازگاری قطعه را بررسی می‌کند و نتیجه، قیمت و زمان تأمین را با شما در میان می‌گذارد.</p>
        <p class="muted small">{{ config('shop.response_target') }} · {{ config('shop.support_hours') }}</p>
        <a href="{{ route('parts.index') }}" class="btn btn-ghost">بازگشت به قطعات</a>
    </div>
</div>
@endsection
