@extends('layouts.admin')
@section('title', 'تطبیق — '.$fitment->name)

@section('content')
<div class="main-head">
    <h1>درخواست تطبیق — {{ $fitment->name }}</h1>
    <a href="tel:{{ $fitment->phone }}" class="btn btn-accent">📞 <span class="ltr">{{ $fitment->phone }}</span></a>
</div>
<div class="grid layout-side" style="grid-template-columns:1fr 1fr;align-items:start">
    <div class="card">
        <h3>اطلاعات ارسالی مشتری</h3>
        <table class="spec">
            <tr><th>خودرو</th><td>{{ $fitment->vehicle?->label() ?? '—' }} {{ $fitment->vehicle_text }}</td></tr>
            <tr><th>سال / موتور</th><td>{{ $fitment->year ? fa_digits($fitment->year) : '—' }} / {{ $fitment->engine ?? '—' }}</td></tr>
            <tr><th>شماره شاسی</th><td>@if($fitment->vin)<span class="code">{{ $fitment->vin }}</span>@else — @endif</td></tr>
            <tr><th>شماره فنی</th><td>@if($fitment->part_number)<span class="code">{{ $fitment->part_number }}</span>@else — @endif</td></tr>
            <tr><th>قطعه از سایت</th><td>@if($fitment->product)<a href="{{ route('admin.products.edit', $fitment->product) }}" style="color:var(--info)">{{ $fitment->product->name }}</a>@else — @endif</td></tr>
            <tr><th>شرح</th><td style="white-space:pre-line">{{ $fitment->description }}</td></tr>
        </table>
        @if($fitment->photo_path)<a href="{{ asset('storage/'.$fitment->photo_path) }}" target="_blank"><img src="{{ asset('storage/'.$fitment->photo_path) }}" alt="عکس ارسالی" style="max-height:260px;margin-top:12px;border-radius:10px"></a>@endif
        @if($fitment->product && $fitment->vehicle)
            <p class="mt {{ $fitment->product->vehicles->contains('id', $fitment->vehicle_id) ? 'fit fit-yes' : 'fit fit-unknown' }}">
                {{ $fitment->product->vehicles->contains('id', $fitment->vehicle_id) ? 'این ترکیب قبلاً در فهرست سازگاری ثبت شده است.' : 'این ترکیب در فهرست سازگاری ثبت نشده — بررسی کنید و در صورت تأیید به محصول اضافه کنید.' }}
            </p>
        @endif
        @if($fitment->customer)<p class="small"><a href="{{ route('admin.customers.show', $fitment->customer) }}" style="color:var(--info)">پرونده مشتری ({{ fa_digits($fitment->customer->orders->count()) }} سفارش)</a></p>@endif
    </div>
    <form method="POST" action="{{ route('admin.fitment.update', $fitment) }}" class="card">
        @csrf @method('PUT')
        <h3>نتیجه بررسی فنی</h3>
        <div class="field"><label>وضعیت</label>
            <select name="status">@foreach(\App\Models\FitmentRequest::STATUSES as $k => $l)<option value="{{ $k }}" @selected(old('status', $fitment->status) === $k)>{{ $l }}</option>@endforeach</select>
        </div>
        <div class="field"><label>قطعه پیشنهادی</label>
            <select name="product_id"><option value="">—</option>@foreach($products as $p)<option value="{{ $p->id }}" @selected(old('product_id', $fitment->product_id) == $p->id)>{{ $p->name }} {{ $p->part_number }}</option>@endforeach</select>
        </div>
        <div class="field"><label>نتیجه و دلیل (چه چیزی بررسی شد)</label><textarea name="result_note" rows="3">{{ old('result_note', $fitment->result_note) }}</textarea></div>
        <div class="grid g2">
            <div class="field"><label>قیمت اعلامی (تومان)</label><input type="number" name="quoted_price" min="0" value="{{ old('quoted_price', $fitment->quoted_price) }}"></div>
            <div class="field"><label>زمان تأمین (روز)</label><input type="number" name="quoted_lead_days" min="0" value="{{ old('quoted_lead_days', $fitment->quoted_lead_days) }}"></div>
        </div>
        <div class="field"><label class="row" style="font-weight:400"><input type="checkbox" name="followed_up" value="1" @checked($fitment->followed_up_at)> نتیجه به مشتری اعلام و پیگیری شد (قیمت و موجودی دوباره تأیید شد)</label></div>
        @if($fitment->checker)<p class="small muted">بررسی‌شده توسط {{ $fitment->checker->name }} در {{ jdate($fitment->checked_at, 'd MMMM، HH:mm') }}</p>@endif
        <button class="btn btn-accent">ثبت نتیجه</button>
    </form>
</div>
<style>@media(max-width:1000px){.layout-side{grid-template-columns:1fr!important}}</style>
@endsection
