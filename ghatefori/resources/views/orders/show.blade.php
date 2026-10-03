@extends('layouts.shop')
@section('title', 'سفارش '.$order->code)

@section('content')
@php($steps = ['awaiting_confirmation', 'confirmed', 'paid', 'shipped', 'delivered'])
@php($current = array_search($order->status, $steps))
<div class="container mt" style="max-width:900px">
    <div class="row between">
        <h1 style="margin:0">سفارش <span class="code">{{ $order->code }}</span></h1>
        <span class="badge {{ in_array($order->status, ['cancelled', 'returned']) ? 'badge-bad' : 'badge-info' }}">{{ \App\Models\Order::STATUSES[$order->status] }}</span>
    </div>
    <p class="small muted">این صفحه را نگه دارید یا با کد سفارش و موبایل از بخش «پیگیری سفارش» دوباره باز کنید.</p>

    @if($current !== false)
        <div class="grid g5 mt">
            @foreach($steps as $i => $s)
                <div class="kpi" @style(['border-color:var(--accent);background:var(--accent-l)' => $i === $current, 'opacity:.5' => $i > $current])>
                    <span>{{ fa_digits($i + 1) }}</span><div class="small" style="font-weight:600">{{ \App\Models\Order::STATUSES[$s] }}</div>
                </div>
            @endforeach
        </div>
    @endif

    @if($order->status === 'awaiting_confirmation')
        <div class="warn mt">در حال بررسی تطبیق قطعه و تأیید موجودی و قیمت با تأمین‌کننده هستیم. پس از تأیید با شما تماس می‌گیریم؛ تا آن زمان پرداختی لازم نیست.</div>
    @endif
    @if($order->promised_at && in_array($order->status, ['confirmed', 'paid']))
        <div class="alert alert-ok mt">ارسال تا {{ jdate($order->promised_at, 'EEEE d MMMM') }}</div>
    @endif
    @if($order->tracking_code)
        <div class="alert alert-ok mt">کد رهگیری مرسوله: <span class="code">{{ $order->tracking_code }}</span></div>
    @endif

    <div class="card-flat table-wrap mt">
        <table>
            <thead><tr><th>قطعه</th><th>تعداد</th><th>قیمت واحد</th><th>جمع</th></tr></thead>
            <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product->name }}<div class="small muted">{{ $item->product->brand->name ?? '' }}</div></td>
                    <td>{{ fa_digits($item->qty) }}</td>
                    <td>{{ toman($item->unit_price) }}</td>
                    <td>{{ toman($item->qty * $item->unit_price) }}</td>
                </tr>
            @endforeach
            @if($order->discount)<tr><td colspan="3">تخفیف</td><td>− {{ toman($order->discount) }}</td></tr>@endif
            <tr><td colspan="3">ارسال به {{ $order->city }}</td><td>{{ toman($order->shipping_charge) }}</td></tr>
            <tr><td colspan="3"><b>مبلغ کل</b></td><td><b>{{ toman($order->total()) }}</b></td></tr>
            </tbody>
        </table>
    </div>
    @if($order->customerVehicle)<p class="small muted">خودرو: {{ $order->customerVehicle->label() }}</p>@endif

    @if($order->status === 'delivered' && ! $order->review)
        <form method="POST" action="{{ $reviewUrl }}" class="card mt">
            @csrf
            <h3>خریدتان چطور بود؟</h3>
            <div class="field"><label>امتیاز</label>
                <select name="rating">@for($r = 5; $r >= 1; $r--)<option value="{{ $r }}">{{ str_repeat('★', $r) }}</option>@endfor</select>
            </div>
            <div class="field"><textarea name="body" rows="3" placeholder="تطبیق، بسته‌بندی، زمان تحویل…"></textarea></div>
            <div class="field"><label class="row" style="font-weight:400"><input type="checkbox" name="publish_consent" value="1"> اجازه می‌دهم نظرم با نام کوچکم در سایت منتشر شود</label></div>
            <button class="btn btn-accent">ثبت نظر</button>
        </form>
    @elseif($order->review)
        <div class="alert alert-ok mt">نظر شما ثبت شده است. ممنونیم.</div>
    @endif
</div>
@endsection
