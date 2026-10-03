@extends('layouts.shop')
@section('title', 'سبد خرید')

@section('content')
<div class="container mt" style="max-width:960px">
    <h1>سبد خرید</h1>
    @if($lines->isEmpty())
        <div class="card empty">سبد شما خالی است. <a href="{{ route('parts.index') }}" style="color:var(--info)">جستجوی قطعه</a></div>
    @else
        <div class="card-flat table-wrap">
            <table>
                <thead><tr><th>قطعه</th><th>وضعیت</th><th>قیمت واحد</th><th>تعداد</th><th>جمع</th></tr></thead>
                <tbody>
                @foreach($lines as $line)
                    @php($p = $line['product'])
                    <tr>
                        <td><a href="{{ route('parts.show', $p) }}"><b>{{ $p->name }}</b></a><div class="small muted">{{ $p->brand->name ?? '' }} @if($p->part_number)· <span class="code">{{ $p->part_number }}</span>@endif</div></td>
                        <td>@include('partials.stock', ['product' => $p])</td>
                        <td>{{ toman($p->price) }}</td>
                        <td>
                            <form method="POST" action="{{ route('cart.update', $p) }}" class="row" style="flex-wrap:nowrap">@csrf @method('PATCH')
                                <input type="number" name="qty" value="{{ $line['qty'] }}" min="0" max="99" style="width:70px" onchange="this.form.submit()">
                                <button class="btn btn-ghost btn-sm" name="qty" value="0" title="حذف">✕</button>
                            </form>
                        </td>
                        <td><b>{{ toman($line['subtotal']) }}</b></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @if($lines->contains(fn ($l) => $l['product']->stock_status === 'on_request'))
            <div class="warn mt">بعضی اقلام «قابل تأمین» هستند. پس از ثبت سفارش، موجودی، قیمت نهایی و زمان را با تأمین‌کننده تأیید و پیش از پرداخت اعلام می‌کنیم.</div>
        @endif
        <div class="row between mt">
            <a href="{{ route('parts.index') }}" class="btn btn-ghost">ادامه خرید</a>
            <div class="row"><span>جمع کالاها: <b>{{ toman($lines->sum('subtotal')) }}</b></span><a href="{{ route('checkout') }}" class="btn btn-accent">ادامه و ثبت سفارش</a></div>
        </div>
    @endif
</div>
@endsection
