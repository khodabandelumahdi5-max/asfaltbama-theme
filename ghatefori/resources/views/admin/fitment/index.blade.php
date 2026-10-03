@extends('layouts.admin')
@section('title', 'تطبیق و استعلام')

@section('content')
<div class="main-head">
    <h1>درخواست‌های تطبیق و استعلام</h1>
    <form class="row"><select name="status" style="width:auto" onchange="this.form.submit()"><option value="">همه</option>@foreach(\App\Models\FitmentRequest::STATUSES as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></form>
</div>
<div class="card-flat table-wrap">
    <table>
        <thead><tr><th>مشتری</th><th>خودرو</th><th>قطعه</th><th>وضعیت</th><th>بررسی‌کننده</th><th>ثبت</th></tr></thead>
        <tbody>
        @forelse($requests as $f)
            <tr>
                <td><a href="{{ route('admin.fitment.show', $f) }}"><b>{{ $f->name }}</b></a><div class="small muted ltr">{{ $f->phone }}</div></td>
                <td class="small">{{ $f->vehicle?->label() ?? $f->vehicle_text }} @if($f->year)({{ fa_digits($f->year) }})@endif</td>
                <td class="small">{{ $f->product->name ?? \Illuminate\Support\Str::limit($f->description, 50) }}</td>
                <td><span class="badge {{ ['pending' => 'badge-accent', 'compatible' => 'badge-ok', 'incompatible' => 'badge-bad'][$f->status] ?? '' }}">{{ \App\Models\FitmentRequest::STATUSES[$f->status] }}</span></td>
                <td class="small">{{ $f->checker->name ?? '—' }}</td>
                <td class="small {{ $f->status === 'pending' && $f->created_at->lt(now()->subHours(2)) ? 'overdue' : 'muted' }}">{{ $f->created_at->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">درخواستی نیست.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pagination">{{ $requests->links() }}</div>
@endsection
