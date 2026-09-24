@extends('layouts.app')

@section('content')
<section class="hero">
    <div class="container">
        <div>
            <h1>بازار آنلاین <span>قیر، آسفالت</span> و خدمات راهسازی</h1>
            <p>از تأمین‌کنندگان تأییدشده در سراسر کشور قیمت بگیرید، مقایسه کنید و مستقیم معامله کنید. تأمین‌کنندگان با CRM داخلی، مشتریان و مذاکرات خود را مدیریت می‌کنند.</p>
            <div class="row mt">
                <a href="{{ route('products.index') }}" class="btn btn-amber">مشاهده محصولات</a>
                <a href="{{ route('register', ['role' => 'supplier']) }}" class="btn btn-ghost">ثبت‌نام تأمین‌کننده</a>
            </div>
            <div class="stats">
                <div><b>{{ fa_number($stats['suppliers']) }}+</b>تأمین‌کننده</div>
                <div><b>{{ fa_number($stats['products']) }}+</b>محصول فعال</div>
                <div><b>{{ fa_number($stats['rfqs']) }}+</b>استعلام قیمت</div>
            </div>
        </div>
        <div class="rfq-box">
            <h3>📋 یک درخواست، چندین قیمت</h3>
            <p class="small muted">بگویید چه می‌خواهید؛ تأمین‌کنندگان برای شما پیشنهاد قیمت می‌فرستند.</p>
            <form action="{{ route('rfqs.create') }}">
                <div class="field"><input name="title" placeholder="مثلاً: ۲۰۰ تن قیر ۶۰/۷۰ فله"></div>
                <button class="btn btn-amber btn-block">ثبت استعلام رایگان</button>
            </form>
        </div>
    </div>
</section>

<div class="container">
    <div class="section-title"><h2>دسته‌بندی‌ها</h2><a href="{{ route('products.index') }}">همه محصولات ←</a></div>
    <div class="grid g3" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
        @foreach($categories as $cat)
            <a href="{{ route('products.index', ['category' => $cat->slug]) }}" class="cat">
                <span class="ic">{{ $cat->icon }}</span>
                <b>{{ $cat->name }}</b>
                <span class="small muted">{{ fa_number($cat->products_count) }} محصول</span>
            </a>
        @endforeach
    </div>

    <div class="section-title"><h2>تازه‌ترین محصولات</h2><a href="{{ route('products.index') }}">مشاهده همه ←</a></div>
    <div class="grid g4">
        @forelse($featured as $product)
            @include('partials.product-card')
        @empty
            <div class="card empty">هنوز محصولی ثبت نشده است.</div>
        @endforelse
    </div>

    <div class="grid g2 mt">
        <div>
            <div class="section-title" style="margin-top:12px"><h2>استعلام‌های باز</h2><a href="{{ route('rfqs.index') }}">همه ←</a></div>
            <div class="card-flat">
                @forelse($rfqs as $rfq)
                    <a href="{{ route('rfqs.show', $rfq) }}" class="row between" style="padding:12px 16px;border-bottom:1px solid var(--line)">
                        <span><b>{{ $rfq->title }}</b><br><span class="small muted">{{ fa_number($rfq->quantity, 2) }} {{ $rfq->unit }} · {{ $rfq->city ?? 'همه شهرها' }}</span></span>
                        <span class="badge badge-amber">{{ fa_number($rfq->quotes_count) }} پیشنهاد</span>
                    </a>
                @empty
                    <div class="empty">استعلام بازی وجود ندارد.</div>
                @endforelse
            </div>
        </div>
        <div>
            <div class="section-title" style="margin-top:12px"><h2>تأمین‌کنندگان تأییدشده</h2><a href="{{ route('companies.index') }}">همه ←</a></div>
            <div class="grid g2">
                @foreach($suppliers as $company)
                    <a href="{{ route('companies.show', $company) }}" class="card" style="padding:14px">
                        <b>{{ $company->name }}</b> <span class="verified">✔</span>
                        <div class="small muted">{{ $company->city }} · {{ fa_number($company->products_count) }} محصول</div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <div class="section-title"><h2>چطور کار می‌کند؟</h2></div>
    <div class="steps">
        <div class="card"><h3>استعلام ثبت کنید</h3><p class="small muted">نوع، مقدار و محل تحویل را بنویسید.</p></div>
        <div class="card"><h3>قیمت دریافت کنید</h3><p class="small muted">تأمین‌کنندگان پیشنهاد قیمت و زمان تحویل می‌دهند.</p></div>
        <div class="card"><h3>مقایسه و انتخاب</h3><p class="small muted">بهترین پیشنهاد را در پنل خود بپذیرید.</p></div>
        <div class="card"><h3>معامله مستقیم</h3><p class="small muted">فروشنده از طریق CRM پیگیری و تحویل را انجام می‌دهد.</p></div>
    </div>
</div>
@endsection
