@extends('layouts.admin')
@section('title', 'سفارش '.$order->code)

@section('content')
@php($manager = auth()->user()->isManager())
@php($needsFit = $order->items->contains(fn ($i) => $i->product->requires_fitment_check))
<div class="main-head">
    <div>
        <h1>سفارش <span class="code">{{ $order->code }}</span></h1>
        <span class="badge badge-info">{{ \App\Models\Order::STATUSES[$order->status] }}</span>
        <span class="small muted">ثبت {{ jdate($order->created_at, 'd MMMM، HH:mm') }}</span>
    </div>
    <a href="{{ route('admin.customers.show', $order->customer) }}" class="btn btn-ghost">پرونده مشتری: {{ $order->customer->name }}</a>
</div>

<div class="grid layout-side" style="grid-template-columns:1fr 340px;align-items:start">
<div>
    <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="card mb" id="order-form">
        @csrf @method('PUT')
        <h3>اقلام</h3>
        <div class="table-wrap">
        <table>
            <thead><tr><th>قطعه</th><th>تعداد</th><th>قیمت فروش</th>@if($manager)<th>قیمت خرید</th>@endif<th>تأمین از</th></tr></thead>
            <tbody>
            @foreach($order->items as $item)
                <tr>
                    <td><b>{{ $item->product->name }}</b>
                        <div class="small muted">@if($item->product->part_number)<span class="code">{{ $item->product->part_number }}</span> @endif @include('partials.stock', ['product' => $item->product])</div>
                        @if($item->product->requires_fitment_check)<div class="small" style="color:var(--accent-d)">⚠ نیاز به بررسی تطبیق</div>@endif
                    </td>
                    <td><input type="number" name="items[{{ $item->id }}][qty]" value="{{ old("items.$item->id.qty", $item->qty) }}" min="1" style="width:70px"></td>
                    <td><input type="number" name="items[{{ $item->id }}][unit_price]" value="{{ old("items.$item->id.unit_price", $item->unit_price) }}" min="0" style="width:140px">
                        @if($manager && $item->product->floorPrice())<div class="small muted">حداقل: {{ fa_number($item->product->floorPrice()) }}</div>@endif</td>
                    @if($manager)<td><input type="number" name="items[{{ $item->id }}][unit_cost]" value="{{ old("items.$item->id.unit_cost", $item->unit_cost) }}" min="0" style="width:140px"></td>@endif
                    <td>
                        <select name="items[{{ $item->id }}][supplier_id]" style="min-width:150px"><option value="">—</option>
                            @foreach($item->product->offers as $offer)
                                <option value="{{ $offer->supplier_id }}" @selected($item->supplier_id === $offer->supplier_id)>{{ $offer->supplier->name }}@if($manager) — {{ fa_number($offer->cost_price) }}@endif · {{ $offer->stock_qty !== null ? 'موجودی '.fa_digits($offer->stock_qty) : 'موجودی؟' }} · {{ $offer->lead_time_days !== null ? fa_digits($offer->lead_time_days).' روز' : '' }}{{ $offer->isExpired() ? ' (قیمت منقضی)' : '' }}</option>
                            @endforeach
                        </select>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        </div>

        <div class="grid g3 mt">
            <div class="field"><label>تخفیف (تومان)</label><input type="number" name="discount" min="0" value="{{ old('discount', $order->discount) }}"></div>
            <div class="field"><label>هزینه ارسال از مشتری</label><input type="number" name="shipping_charge" min="0" value="{{ old('shipping_charge', $order->shipping_charge) }}"></div>
            <div class="field"><label>تاریخ ارسال قول‌داده‌شده</label><input type="datetime-local" name="promised_at" class="ltr" value="{{ old('promised_at', $order->promised_at?->format('Y-m-d\TH:i')) }}"></div>
        </div>
        @if($manager)
            <h3 class="mt">هزینه‌های متغیر این سفارش</h3>
            <div class="grid g3">
                <div class="field"><label>هزینه واقعی ارسال</label><input type="number" name="shipping_cost" min="0" value="{{ $order->shipping_cost }}"></div>
                <div class="field"><label>حمل از بنکدار</label><input type="number" name="inbound_cost" min="0" value="{{ $order->inbound_cost }}"></div>
                <div class="field"><label>بسته‌بندی</label><input type="number" name="packaging_cost" min="0" value="{{ $order->packaging_cost }}"></div>
                <div class="field"><label>کارمزد پرداخت</label><input type="number" name="payment_fee" min="0" value="{{ $order->payment_fee }}"></div>
                <div class="field"><label>هزینه جذب (تبلیغ)</label><input type="number" name="acquisition_cost" min="0" value="{{ $order->acquisition_cost }}"></div>
                <div class="field"><label>سهم برآوردی مرجوعی/خرابی</label><input type="number" name="return_reserve" min="0" value="{{ $order->return_reserve }}"></div>
            </div>
        @endif

        <h3 class="mt">تطبیق قطعه با خودرو</h3>
        <p class="small">خودرو: <b>{{ $order->customerVehicle?->label() ?? 'ثبت نشده' }}</b>
            @if($order->customerVehicle?->vin) · شاسی: <span class="code">{{ $order->customerVehicle->vin }}</span>@endif</p>
        <div class="field"><label>یادداشت بررسی فنی (شماره فنی، عکس سوکت، …)</label><textarea name="fitment_note" rows="2">{{ old('fitment_note', $order->fitment_note) }}</textarea></div>
        <div class="field">
            <label class="row" style="font-weight:400"><input type="checkbox" name="fitment_confirmed" value="1" @checked($order->fitment_checked_by)> تطبیق اقلام با این خودرو را بررسی و تأیید می‌کنم</label>
            @if($order->fitmentChecker)<div class="small muted">تأییدکننده: {{ $order->fitmentChecker->name }}</div>@elseif($needsFit)<div class="small overdue">بدون تأیید تطبیق، سفارش قابل تأیید نیست.</div>@endif
        </div>
        <div class="row between">
            <div class="field" style="margin:0"><label>مسئول سفارش</label>
                <select name="assigned_to" style="width:auto"><option value="">—</option>@foreach($staff as $s)<option value="{{ $s->id }}" @selected($order->assigned_to === $s->id)>{{ $s->name }} ({{ \App\Models\User::ROLES[$s->role] ?? '' }})</option>@endforeach</select>
            </div>
            <button class="btn btn-accent">ذخیره</button>
        </div>
    </form>

    <div class="card">
        <h3>تاریخچه</h3>
        <ul class="timeline">
            @foreach($order->events as $e)
                <li><div class="row between small"><span>{{ $e->body }}</span><span class="muted">{{ jdate($e->created_at, 'd MMM، HH:mm') }} @if($e->user)· {{ $e->user->name }}@endif</span></div></li>
            @endforeach
        </ul>
    </div>
</div>

<aside>
    <div class="card mb">
        <h3>مبالغ</h3>
        <div class="row between"><span>کالاها</span><b>{{ toman($order->itemsTotal()) }}</b></div>
        <div class="row between"><span>تخفیف</span><b>− {{ fa_number($order->discount) }}</b></div>
        <div class="row between"><span>ارسال</span><b>{{ fa_number($order->shipping_charge) }}</b></div>
        <div class="row between" style="font-size:1.1rem"><span>دریافتی از مشتری</span><b>{{ toman($order->total()) }}</b></div>
        @if($manager)
            <hr style="border:0;border-top:1px solid var(--line)">
            <div class="row between small"><span>قیمت خرید کالا</span><span>{{ $order->goodsCost() === null ? 'نامشخص' : '− '.fa_number($order->goodsCost()) }}</span></div>
            <div class="row between small"><span>هزینه‌های متغیر</span><span>− {{ fa_number($order->variableCosts()) }}</span></div>
            @php($cm = $order->contribution())
            <div class="row between"><span>باقی‌مانده برای هزینه ثابت و سود</span><b class="{{ $cm === null ? '' : ($cm < 0 ? 'money-neg' : 'money-pos') }}">{{ $cm === null ? '—' : toman($cm) }}</b></div>
            @if($cm !== null && $cm < 0)<div class="small money-neg">این سفارش زیان‌ده است.</div>@endif
        @endif
    </div>

    <div class="card mb">
        <h3>تغییر وضعیت</h3>
        @php($next = \App\Models\Order::TRANSITIONS[$order->status])
        @if(! $next)<p class="muted small">این سفارش بسته شده است.</p>@endif
        @foreach($next as $to)
            <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="mb">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="{{ $to }}">
                @if($to === 'cancelled')
                    <select name="cancel_reason" required class="mb"><option value="">علت لغو…</option>@foreach(\App\Models\Order::CANCEL_REASONS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                @elseif($to === 'returned')
                    <select name="return_reason" required class="mb"><option value="">علت مرجوعی…</option>@foreach(\App\Models\Order::RETURN_REASONS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                @elseif($to === 'shipped')
                    <input name="tracking_code" class="ltr mb" placeholder="کد رهگیری" required>
                @endif
                <button class="btn btn-block {{ in_array($to, ['cancelled', 'returned']) ? 'btn-danger' : 'btn-accent' }}">{{ \App\Models\Order::STATUSES[$to] }}</button>
            </form>
        @endforeach
    </div>

    <div class="card small">
        <h3>ارسال</h3>
        <p style="margin:0">{{ $order->city }} — {{ $order->address }}</p>
        @if($order->customer_note)<p><b>توضیح مشتری:</b> {{ $order->customer_note }}</p>@endif
        @if($order->tracking_code)<p>رهگیری: <span class="code">{{ $order->tracking_code }}</span></p>@endif
        <a href="{{ \Illuminate\Support\Facades\URL::signedRoute('orders.show', $order) }}" target="_blank" style="color:var(--info)">لینک پیگیری مشتری ↗</a>
    </div>
</aside>
</div>
<style>@media(max-width:1000px){.layout-side{grid-template-columns:1fr!important}}</style>
@endsection
