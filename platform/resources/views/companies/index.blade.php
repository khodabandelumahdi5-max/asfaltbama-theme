@extends('layouts.app')
@section('title', 'تأمین‌کنندگان')

@section('content')
<div class="container mt">
    <div class="row between mb">
        <h1 style="margin:0">تأمین‌کنندگان</h1>
        <form class="row"><input name="q" value="{{ request('q') }}" placeholder="نام شرکت…" style="width:220px"><button class="btn btn-sm">جستجو</button></form>
    </div>
    <div class="grid g3">
        @forelse($companies as $company)
            <a href="{{ route('companies.show', $company) }}" class="card">
                <div class="row between">
                    <h3 style="margin:0">{{ $company->name }}</h3>
                    @if($company->is_verified)<span class="badge badge-ok">✔ تأییدشده</span>@endif
                </div>
                <div class="small muted">{{ $company->city ?? '—' }} · {{ fa_number($company->products_count) }} محصول</div>
                <p class="small" style="margin-bottom:0">{{ \Illuminate\Support\Str::limit($company->description, 110) }}</p>
            </a>
        @empty
            <div class="card empty" style="grid-column:1/-1">تأمین‌کننده‌ای پیدا نشد.</div>
        @endforelse
    </div>
    <div class="pagination">{{ $companies->links() }}</div>
</div>
@endsection
