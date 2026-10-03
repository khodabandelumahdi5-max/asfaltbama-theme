@extends('layouts.admin')
@section('title', 'گزارش هفتگی')

@section('content')
@php($m = $metrics)
@php($pct = fn ($v) => $v === null ? '—' : fa_digits($v).'٪')
<div class="main-head">
    <h1>گزارش هفتگی <span class="small muted">{{ jdate($start, 'd MMMM') }} تا {{ jdate($end, 'd MMMM y') }}</span></h1>
    <div class="row">
        <a href="{{ route('admin.report', ['week' => $offset + 1]) }}" class="btn btn-ghost btn-sm">هفته قبل</a>
        @if($offset)<a href="{{ route('admin.report', ['week' => $offset - 1]) }}" class="btn btn-ghost btn-sm">هفته بعد</a>@endif
    </div>
</div>

<h3>قیف فروش</h3>
<div class="grid g4">
    <div class="kpi"><span>استعلام و درخواست تطبیق</span><b>{{ fa_number($m['inquiries']) }}</b></div>
    <div class="kpi"><span>میانگین زمان پاسخ</span><b>{{ $m['response_minutes'] === null ? '—' : fa_number($m['response_minutes']).' دقیقه' }}</b></div>
    <div class="kpi"><span>استعلام قابل تأمین</span><b>{{ $pct($m['sourceable_pct']) }}</b><small>از استعلام‌های بررسی‌شده</small></div>
    <div class="kpi"><span>تبدیل استعلام به سفارش</span><b>{{ $pct($m['converted_pct']) }}</b></div>
</div>

<h3 class="mt">پول</h3>
<div class="grid g4">
    <div class="kpi"><span>سفارش ثبت‌شده</span><b>{{ fa_number($m['orders']) }}</b></div>
    <div class="kpi"><span>فروش (پرداخت‌شده به بعد)</span><b>{{ toman($m['revenue']) }}</b></div>
    <div class="kpi"><span>باقی‌مانده پس از هزینه‌های متغیر</span><b class="{{ ($m['contribution'] ?? 0) < 0 ? 'money-neg' : 'money-pos' }}">{{ $m['contribution'] === null ? '—' : toman($m['contribution']) }}</b>
        @if($m['unknown_cost_orders'])<small class="overdue">{{ fa_digits($m['unknown_cost_orders']) }} سفارش بدون قیمت خرید — عدد کامل نیست</small>@endif</div>
    <div class="kpi"><span>هزینه جذب</span><b>{{ toman($m['acquisition']) }}</b></div>
</div>

<h3 class="mt">کیفیت اجرا</h3>
<div class="grid g4">
    <div class="kpi"><span>لغو به علت موجودی یا قیمت</span><b class="{{ $m['cancel_stock_price'] ? 'money-neg' : '' }}">{{ fa_number($m['cancel_stock_price']) }}</b></div>
    <div class="kpi"><span>مرجوعی به علت عدم تطبیق</span><b class="{{ $m['returns_fitment'] ? 'money-neg' : '' }}">{{ fa_number($m['returns_fitment']) }}</b></div>
    <div class="kpi"><span>تأخیر نسبت به قول ارسال</span><b class="{{ $m['late'] ? 'money-neg' : '' }}">{{ fa_number($m['late']) }}</b></div>
    <div class="kpi"><span>مشتری با خرید تکراری</span><b>{{ fa_number($m['repeat_customers']) }}</b></div>
</div>

<div class="card mt small">
    <b>راهنمای تصمیم:</b>
    <ul style="margin-bottom:0">
        <li>استعلام زیاد ولی «قابل تأمین» کم ← اول تأمین را اصلاح کنید، نه تبلیغ.</li>
        <li>تأمین خوب ولی تبدیل به سفارش کم ← علت نخریدن را از پرونده مشتری‌ها بررسی کنید.</li>
        <li>فروش بالا ولی باقی‌مانده کم یا منفی ← قیمت‌گذاری، تخفیف‌ها و هزینه‌های متغیر را بررسی کنید.</li>
        <li>مرجوعی عدم تطبیق ← فرایند بررسی فنی و اطلاعات صفحه محصول را اصلاح کنید.</li>
    </ul>
</div>
@endsection
