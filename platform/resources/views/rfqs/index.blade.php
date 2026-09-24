@extends('layouts.app')
@section('title', 'بازار استعلام قیمت')

@section('content')
<div class="container mt">
    <div class="row between mb">
        <div>
            <h1 style="margin:0">بازار استعلام قیمت</h1>
            <p class="muted small" style="margin:0">خریداران نیاز خود را ثبت کرده‌اند؛ تأمین‌کنندگان می‌توانند پیشنهاد قیمت بدهند.</p>
        </div>
        <div class="row">
            <a href="{{ route('rfqs.index', ['status' => request('status') === 'all' ? null : 'all']) }}" class="btn btn-ghost btn-sm">{{ request('status') === 'all' ? 'فقط باز' : 'نمایش همه' }}</a>
            <a href="{{ route('rfqs.create') }}" class="btn btn-amber">＋ ثبت استعلام</a>
        </div>
    </div>
    <div class="card-flat table-wrap">
        <table>
            <thead><tr><th>عنوان</th><th>دسته</th><th>مقدار</th><th>محل تحویل</th><th>پیشنهادها</th><th>تاریخ</th><th></th></tr></thead>
            <tbody>
            @forelse($rfqs as $rfq)
                <tr>
                    <td><a href="{{ route('rfqs.show', $rfq) }}"><b>{{ $rfq->title }}</b></a></td>
                    <td class="small">{{ $rfq->category->name ?? '—' }}</td>
                    <td>{{ fa_number($rfq->quantity, 2) }} {{ $rfq->unit }}</td>
                    <td>{{ $rfq->city ?? '—' }}</td>
                    <td>{{ fa_number($rfq->quotes_count) }}</td>
                    <td class="small muted">{{ jdate($rfq->created_at) }}</td>
                    <td>@if($rfq->isOpen())<span class="badge badge-ok">باز</span>@else<span class="badge">بسته</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="empty">استعلامی وجود ندارد.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination">{{ $rfqs->links() }}</div>
</div>
@endsection
