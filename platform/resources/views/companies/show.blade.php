@extends('layouts.app')
@section('title', $company->name)

@section('content')
<div style="background:var(--asphalt);color:#fff">
    <div class="container" style="padding-block:32px">
        <div class="row between">
            <div>
                <h1 style="margin:0">{{ $company->name }}</h1>
                <div class="row small" style="color:#cfd3da">
                    @if($company->is_verified)<span class="badge badge-ok">✔ تأمین‌کننده تأییدشده</span>@endif
                    @if($company->city)<span>📍 {{ $company->city }}</span>@endif
                    @if($company->founded_year)<span>تأسیس {{ fa_digits($company->founded_year) }}</span>@endif
                    <span>عضویت از {{ jdate($company->created_at, 'MMMM y') }}</span>
                </div>
            </div>
            <a href="{{ route('rfqs.create') }}" class="btn btn-amber">درخواست قیمت</a>
        </div>
    </div>
</div>
<div class="container mt">
    <div class="grid" style="grid-template-columns:1fr 300px;align-items:start">
        <div>
            <h2>محصولات ({{ fa_number($products->total()) }})</h2>
            <div class="grid g3">
                @forelse($products as $product)
                    @include('partials.product-card')
                @empty
                    <div class="card empty" style="grid-column:1/-1">هنوز محصولی ثبت نشده است.</div>
                @endforelse
            </div>
            <div class="pagination">{{ $products->links() }}</div>
        </div>
        <aside class="card">
            <h3>درباره شرکت</h3>
            <p class="small" style="white-space:pre-line">{{ $company->description ?: 'توضیحی ثبت نشده است.' }}</p>
            @if($company->website)<p class="small"><a class="ltr" href="{{ $company->website }}" rel="nofollow noopener" target="_blank" style="color:var(--info)">{{ $company->website }}</a></p>@endif
        </aside>
    </div>
</div>
<style>@media(max-width:860px){.container>.grid[style]{grid-template-columns:1fr!important}}</style>
@endsection
