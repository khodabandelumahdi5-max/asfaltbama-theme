@extends('layouts.shop')
@section('title', 'پیگیری سفارش')

@section('content')
<div class="container mt" style="max-width:460px">
    <form method="POST" action="{{ route('orders.find') }}" class="card">
        @csrf
        <h1>پیگیری سفارش</h1>
        <div class="field"><label>کد سفارش</label><input name="code" class="ltr" value="{{ old('code') }}" placeholder="GF-XXXXXX" required></div>
        <div class="field"><label>موبایل ثبت‌شده در سفارش</label><input name="phone" class="ltr" value="{{ old('phone') }}" required></div>
        <button class="btn btn-accent btn-block">نمایش وضعیت</button>
    </form>
</div>
@endsection
