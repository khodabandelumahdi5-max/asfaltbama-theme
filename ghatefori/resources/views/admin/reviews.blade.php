@extends('layouts.admin')
@section('title', 'نظرات خریداران')

@section('content')
<div class="main-head"><h1>نظرات خریداران</h1></div>
<p class="small muted">فقط خریدارانی که سفارششان تحویل شده می‌توانند نظر بدهند. انتشار فقط با اجازه خود مشتری ممکن است.</p>
<div class="card-flat table-wrap">
    <table>
        <thead><tr><th>سفارش</th><th>مشتری</th><th>امتیاز</th><th>نظر</th><th>اجازه انتشار</th><th></th></tr></thead>
        <tbody>
        @forelse($reviews as $r)
            <tr>
                <td><a href="{{ route('admin.orders.show', $r->order) }}" class="code">{{ $r->order->code }}</a></td>
                <td>{{ $r->order->customer->name }}</td>
                <td class="stars">{{ str_repeat('★', $r->rating) }}</td>
                <td class="small">{{ $r->body }}</td>
                <td>{{ $r->publish_consent ? 'دارد' : 'ندارد' }}</td>
                <td>@if($r->publish_consent)
                    <form method="POST" action="{{ route('admin.reviews.toggle', $r) }}">@csrf @method('PATCH')<button class="btn btn-sm {{ $r->is_published ? 'btn-ghost' : 'btn-accent' }}">{{ $r->is_published ? 'عدم نمایش' : 'انتشار' }}</button></form>
                @endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">هنوز نظری ثبت نشده.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pagination">{{ $reviews->links() }}</div>
@endsection
