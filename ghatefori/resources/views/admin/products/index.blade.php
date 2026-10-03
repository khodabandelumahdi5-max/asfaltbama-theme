@extends('layouts.admin')
@section('title', 'قطعات')

@section('content')
<div class="main-head">
    <h1>قطعات</h1>
    <div class="row">
        <form class="row">
            <select name="stock" style="width:auto"><option value="">همه</option>@foreach(\App\Models\Product::STOCK as $k => $l)<option value="{{ $k }}" @selected(request('stock') === $k)>{{ $l }}</option>@endforeach</select>
            <input name="q" value="{{ request('q') }}" placeholder="نام یا شماره فنی" style="width:180px"><button class="btn btn-ghost btn-sm">جستجو</button>
        </form>
        <a href="{{ route('admin.products.create') }}" class="btn btn-accent">＋ قطعه جدید</a>
    </div>
</div>
<div class="card-flat table-wrap">
    <table>
        <thead><tr><th>قطعه</th><th>برند / اصالت</th><th>موجودی</th><th>قیمت فروش</th>@if(auth()->user()->isManager())<th>بهترین قیمت خرید</th>@endif<th>تأمین‌کننده</th><th>قیمت بررسی‌شده</th></tr></thead>
        <tbody>
        @forelse($products as $p)
            <tr>
                <td><a href="{{ route('admin.products.edit', $p) }}"><b>{{ $p->name }}</b></a>
                    <div class="small muted">@if($p->part_number)<span class="code">{{ $p->part_number }}</span>@endif {{ $p->category->name }} @unless($p->is_active)<span class="badge">غیرفعال</span>@endunless</div></td>
                <td class="small">{{ $p->brand->name ?? '—' }}<br><span class="muted">{{ \App\Models\Product::AUTHENTICITY[$p->authenticity] }}</span></td>
                <td>@include('partials.stock', ['product' => $p]) @if($p->stock_status === 'in_stock')<span class="small">{{ fa_digits($p->stock_qty) }}</span>@endif
                    @if($p->waiting_alerts)<div class="small" style="color:var(--accent-d)">🔔 {{ fa_digits($p->waiting_alerts) }} منتظر</div>@endif</td>
                <td>{{ $p->price ? fa_number($p->price) : '—' }}</td>
                @if(auth()->user()->isManager())
                    @php($best = $p->offers->min('cost_price'))
                    <td class="small">{{ $best ? fa_number($best) : '—' }}
                        @if($best && $p->price)<div class="{{ $p->price > $best ? 'money-pos' : 'money-neg' }}">{{ fa_number(round(100 * ($p->price - $best) / $p->price)) }}٪ حاشیه</div>@endif</td>
                @endif
                <td class="small {{ $p->offers->count() < 2 ? 'overdue' : '' }}">{{ fa_digits($p->offers->count()) }} منبع</td>
                <td class="small {{ ! $p->price_checked_at || $p->price_checked_at->lt(now()->subDays(7)) ? 'overdue' : 'muted' }}">{{ $p->price_checked_at ? jdate($p->price_checked_at, 'd MMM') : 'هرگز' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">قطعه‌ای ثبت نشده.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<p class="small muted">قرمز: کمتر از دو منبع تأمین، یا قیمتی که بیش از ۷ روز بررسی نشده.</p>
<div class="pagination">{{ $products->links() }}</div>
@endsection
