@extends('layouts.panel')
@section('title', 'تأمین‌کنندگان')

@section('content')
<div class="main-head"><h1>تأمین‌کنندگان</h1></div>
<div class="card-flat table-wrap">
    <table>
        <thead><tr><th>شرکت</th><th>مالک</th><th>شهر</th><th>محصولات</th><th>عضویت</th><th>وضعیت</th><th></th></tr></thead>
        <tbody>
        @foreach($companies as $company)
            <tr>
                <td><a href="{{ route('companies.show', $company) }}" target="_blank"><b>{{ $company->name }}</b></a></td>
                <td class="small">{{ $company->owner->name }}<br><span class="muted ltr">{{ $company->owner->phone }}</span></td>
                <td>{{ $company->city ?? '—' }}</td>
                <td>{{ fa_number($company->products_count) }}</td>
                <td class="small muted">{{ jdate($company->created_at) }}</td>
                <td>@if($company->is_verified)<span class="badge badge-ok">تأییدشده</span>@else<span class="badge badge-amber">در انتظار</span>@endif</td>
                <td>
                    <form method="POST" action="{{ route('panel.admin.companies.verify', $company) }}">@csrf @method('PATCH')
                        <button class="btn btn-sm {{ $company->is_verified ? 'btn-danger' : 'btn-amber' }}">{{ $company->is_verified ? 'لغو تأیید' : 'تأیید' }}</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div class="pagination">{{ $companies->links() }}</div>
@endsection
