@extends('layouts.admin')
@section('title', 'مشتریان')

@section('content')
<div class="main-head">
    <h1>مشتریان</h1>
    <form class="row">
        <select name="type" style="width:auto"><option value="">همه</option>@foreach(\App\Models\Customer::TYPES as $k => $l)<option value="{{ $k }}" @selected(request('type') === $k)>{{ $l }}</option>@endforeach</select>
        <input name="q" value="{{ request('q') }}" placeholder="نام یا موبایل" style="width:180px"><button class="btn btn-ghost btn-sm">جستجو</button>
    </form>
</div>
<div class="card-flat table-wrap">
    <table>
        <thead><tr><th>نام</th><th>موبایل</th><th>نوع</th><th>شهر</th><th>منبع آشنایی</th><th>سفارش‌ها</th><th>اجازه پیام</th></tr></thead>
        <tbody>
        @forelse($customers as $c)
            <tr>
                <td><a href="{{ route('admin.customers.show', $c) }}"><b>{{ $c->name }}</b></a></td>
                <td class="ltr">{{ $c->phone }}</td>
                <td class="small">{{ \App\Models\Customer::TYPES[$c->type] }}</td>
                <td>{{ $c->city ?? '—' }}</td>
                <td class="small">{{ \App\Models\Customer::SOURCES[$c->source] ?? '—' }}</td>
                <td>{{ fa_digits($c->orders_count) }}</td>
                <td>{!! $c->marketing_consent ? '<span class="badge badge-ok">دارد</span>' : '<span class="badge">ندارد</span>' !!}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">مشتری‌ای نیست.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pagination">{{ $customers->links() }}</div>
@endsection
