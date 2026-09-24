@extends('layouts.panel')
@section('title', 'پیام‌های خریداران')

@section('content')
<div class="main-head"><h1>پیام‌های خریداران</h1></div>
<div class="card-flat table-wrap">
    <table>
        <thead><tr><th>فرستنده</th><th>محصول</th><th>مقدار</th><th>پیام</th><th>تاریخ</th><th>CRM</th></tr></thead>
        <tbody>
        @forelse($inquiries as $inq)
            <tr @class(['unread' => ! $inq->read_at])>
                <td><a href="{{ route('panel.inquiries.show', $inq) }}">{{ $inq->name }}</a><br><span class="small muted ltr">{{ $inq->phone }}</span></td>
                <td class="small">{{ $inq->product->name ?? '—' }}</td>
                <td>{{ $inq->quantity ? fa_number($inq->quantity, 2) : '—' }}</td>
                <td class="small"><a href="{{ route('panel.inquiries.show', $inq) }}">{{ \Illuminate\Support\Str::limit($inq->message, 60) }}</a></td>
                <td class="small muted">{{ jdate($inq->created_at, 'd MMM، HH:mm') }}</td>
                <td>@if($inq->contact)<a href="{{ route('panel.crm.show', $inq->contact) }}">@include('partials.stage-badge', ['stage' => $inq->contact->stage])</a>@endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">هنوز پیامی دریافت نکرده‌اید.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pagination">{{ $inquiries->links() }}</div>
@endsection
