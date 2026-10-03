@extends('layouts.admin')
@section('title', 'کارهای امروز')

@section('content')
<div class="main-head"><h1>کارهای امروز</h1><span class="muted small">{{ jdate(now(), 'EEEE d MMMM y') }}</span></div>

<div class="grid g4">
    <a href="{{ route('admin.orders.index', ['status' => 'awaiting_confirmation']) }}" class="kpi"><span>سفارش منتظر تأیید</span><b>{{ fa_number($awaiting->count()) }}</b><small>مسئول: فروش + فنی</small></a>
    <a href="{{ route('admin.fitment.index', ['status' => 'pending']) }}" class="kpi"><span>تطبیق در انتظار</span><b>{{ fa_number($fitments->count()) }}</b><small>مسئول: بررسی فنی</small></a>
    <a href="{{ route('admin.orders.index', ['status' => 'paid']) }}" class="kpi"><span>آماده ارسال</span><b>{{ fa_number($toShip->count()) }}</b><small>مسئول: ارسال</small></a>
    <div class="kpi"><span>سفارش عقب‌افتاده از قول</span><b class="{{ $late ? 'money-neg' : '' }}">{{ fa_number($late) }}</b><small>مسئول: مدیریت</small></div>
</div>

@if($alertsReady || $expiredOffers || $stalePrices || $followUps)
    <div class="card mt">
        <h3>هشدارها</h3>
        <ul style="margin:0">
            @if($alertsReady)<li><a href="{{ route('admin.alerts.index') }}">{{ fa_digits($alertsReady) }} نفر منتظر کالایی هستند که الان موجود است — تماس بگیرید.</a></li>@endif
            @if($followUps)<li><a href="{{ route('admin.fitment.index', ['status' => 'compatible']) }}">{{ fa_digits($followUps) }} استعلام سازگار هنوز پیگیری نشده (بیش از ۲۴ ساعت).</a></li>@endif
            @if($expiredOffers)<li>{{ fa_digits($expiredOffers) }} قیمت تأمین‌کننده منقضی شده — قبل از تأیید سفارش، قیمت و موجودی را دوباره بگیرید.</li>@endif
            @if($stalePrices)<li><a href="{{ route('admin.products.index') }}">{{ fa_digits($stalePrices) }} قطعه فعال بیش از ۷ روز است قیمتش بررسی نشده.</a></li>@endif
        </ul>
    </div>
@endif

<div class="grid g3 mt">
    <div class="card">
        <h3>سفارش‌های منتظر تأیید</h3>
        @forelse($awaiting as $o)
            <a href="{{ route('admin.orders.show', $o) }}" class="row between" style="padding:8px 0;border-bottom:1px solid var(--line)">
                <span><span class="code">{{ $o->code }}</span> {{ $o->customer->name }}</span>
                <span class="small {{ $o->created_at->lt(now()->subHours(2)) ? 'overdue' : 'muted' }}">{{ $o->created_at->diffForHumans() }}</span>
            </a>
        @empty<p class="muted small">موردی نیست.</p>@endforelse
    </div>
    <div class="card">
        <h3>درخواست‌های تطبیق</h3>
        @forelse($fitments as $f)
            <a href="{{ route('admin.fitment.show', $f) }}" class="row between" style="padding:8px 0;border-bottom:1px solid var(--line)">
                <span>{{ $f->name }}<br><span class="small muted">{{ \Illuminate\Support\Str::limit($f->description, 40) }}</span></span>
                <span class="small {{ $f->created_at->lt(now()->subHours(2)) ? 'overdue' : 'muted' }}">{{ $f->created_at->diffForHumans() }}</span>
            </a>
        @empty<p class="muted small">موردی نیست.</p>@endforelse
    </div>
    <div class="card">
        <h3>یادآوری‌های ۷ روز آینده</h3>
        <p class="small muted" style="margin-top:-6px">فقط مشتریانی که اجازه تماس داده‌اند.</p>
        @forelse($reminders as $r)
            <a href="{{ route('admin.customers.show', $r->customer) }}" style="display:block;padding:8px 0;border-bottom:1px solid var(--line)">
                <b>{{ $r->customer->name }}</b> — {{ $r->title }}
                <div class="small muted">{{ jdate($r->due_on) }} @if($r->is_estimate)<span class="badge">تخمینی</span>@endif</div>
            </a>
        @empty<p class="muted small">موردی نیست.</p>@endforelse
    </div>
</div>
@endsection
