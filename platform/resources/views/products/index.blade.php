@extends('layouts.app')
@section('title', $category->name ?? (request('q') ? 'جستجو: '.request('q') : 'محصولات'))

@section('content')
<div class="container mt">
    <div class="row between mb">
        <h1 style="margin:0">{{ $category->name ?? 'همه محصولات' }} @if(request('q'))<span class="muted small">— «{{ request('q') }}»</span>@endif</h1>
        <span class="muted small">{{ fa_number($products->total()) }} محصول</span>
    </div>
    <div class="grid" style="grid-template-columns:240px 1fr;align-items:start">
        <form class="card" method="GET">
            @if(request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
            <div class="field">
                <label>دسته‌بندی</label>
                <select name="category">
                    <option value="">همه</option>
                    @foreach($categories as $c)
                        <option value="{{ $c->slug }}" @selected($category?->id === $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field">
                <label>شهر تأمین‌کننده</label>
                <select name="city">
                    <option value="">همه شهرها</option>
                    @foreach($cities as $city)<option @selected(request('city') === $city)>{{ $city }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label>مرتب‌سازی</label>
                <select name="sort">
                    <option value="">جدیدترین</option>
                    <option value="price" @selected(request('sort') === 'price')>ارزان‌ترین</option>
                </select>
            </div>
            <div class="field"><label class="row" style="font-weight:400"><input type="checkbox" name="verified" value="1" @checked(request('verified'))> فقط تأمین‌کنندگان تأییدشده</label></div>
            <button class="btn btn-block">اعمال فیلتر</button>
        </form>
        <div>
            <div class="grid g3">
                @forelse($products as $product)
                    @include('partials.product-card')
                @empty
                    <div class="card empty" style="grid-column:1/-1">
                        محصولی پیدا نشد.<br>
                        <a href="{{ route('rfqs.create', ['title' => request('q')]) }}" class="btn btn-amber mt">استعلام قیمت ثبت کنید تا تأمین‌کنندگان پیدایتان کنند</a>
                    </div>
                @endforelse
            </div>
            <div class="pagination">{{ $products->links() }}</div>
        </div>
    </div>
</div>
<style>@media(max-width:760px){.container>.grid[style]{grid-template-columns:1fr!important}}</style>
@endsection
