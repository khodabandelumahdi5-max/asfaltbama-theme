@extends('layouts.shop')
@section('title', 'تماس با ما')

@section('content')
<div class="container mt" style="max-width:700px">
    <h1>تماس با {{ config('shop.name') }}</h1>
    <div class="card">
        <table class="spec">
            <tr><th>تلفن</th><td class="ltr">{{ config('shop.phone') }}</td></tr>
            @if(config('shop.whatsapp'))<tr><th>واتساپ</th><td class="ltr">{{ config('shop.whatsapp') }}</td></tr>@endif
            <tr><th>ساعات پاسخ‌گویی</th><td>{{ config('shop.support_hours') }}</td></tr>
            <tr><th>زمان پاسخ</th><td>{{ config('shop.response_target') }}</td></tr>
        </table>
        <div class="row mt">
            <a href="{{ route('fitment.create') }}" class="btn btn-accent">درخواست تطبیق و استعلام</a>
            <a href="{{ route('orders.track') }}" class="btn btn-ghost">پیگیری سفارش</a>
        </div>
    </div>
</div>
@endsection
