@extends('layouts.shop')
@section('title', $category->name ?? (request('q') ? 'جستجو: '.request('q') : 'قطعات'))

@section('content')
<div class="container mt">
    <div class="row between mb">
        <h1 style="margin:0">{{ $category->name ?? 'قطعات' }} @if(request('q'))<span class="muted small">— «{{ request('q') }}»</span>@endif</h1>
        <span class="muted small">{{ fa_number($products->total()) }} قلم</span>
    </div>
    <div class="grid layout-side" style="grid-template-columns:250px 1fr;align-items:start">
        <form class="card" method="GET">
            @if(request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
            <div class="field"><label>نوع قطعه</label>
                <select name="category"><option value="">همه</option>
                    @foreach($categories as $c)<option value="{{ $c->slug }}" @selected($category?->id === $c->id)>{{ $c->name }}</option>@endforeach
                </select>
            </div>
            <div class="field"><label>برند سازنده</label>
                <select name="brand"><option value="">همه</option>
                    @foreach($brands as $b)<option value="{{ $b->id }}" @selected(request('brand') == $b->id)>{{ $b->name }}</option>@endforeach
                </select>
            </div>
            <div class="field"><label>وضعیت موجودی</label>
                <select name="stock"><option value="">همه</option>
                    @foreach(\App\Models\Product::STOCK as $k => $l)<option value="{{ $k }}" @selected(request('stock') === $k)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <button class="btn btn-block">اعمال فیلتر</button>
            @unless(session('vehicle_id'))<p class="small muted" style="margin-bottom:0">برای دیدن فقط قطعات سازگار، خودروی خود را از نوار بالا انتخاب کنید.</p>@endunless
        </form>
        <div>
            <div class="grid g3">
                @forelse($products as $product)
                    @include('partials.product-card')
                @empty
                    <div class="card empty" style="grid-column:1/-1">
                        قطعه‌ای با این مشخصات در فهرست ما نیست — ولی شاید بتوانیم تأمینش کنیم.<br>
                        <a href="{{ route('fitment.create', ['q' => request('q')]) }}" class="btn btn-accent mt">مشخصات را بفرستید تا بررسی کنیم</a>
                    </div>
                @endforelse
            </div>
            <div class="pagination">{{ $products->links() }}</div>
        </div>
    </div>
</div>
<style>@media(max-width:760px){.layout-side{grid-template-columns:1fr!important}}</style>
@endsection
