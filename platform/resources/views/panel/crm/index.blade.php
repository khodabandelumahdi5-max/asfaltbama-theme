@extends('layouts.panel')
@section('title', 'CRM — قیف فروش')

@section('content')
<div class="main-head">
    <h1>قیف فروش</h1>
    <div class="row">
        <form class="row"><input name="q" value="{{ request('q') }}" placeholder="نام، موبایل یا سازمان…" style="width:220px"><button class="btn btn-ghost btn-sm">جستجو</button></form>
        <a href="{{ route('panel.crm.create') }}" class="btn btn-amber">＋ مخاطب جدید</a>
    </div>
</div>
<p class="small muted">پیام‌های محصول و استعلام‌هایی که به آن‌ها قیمت داده‌اید به‌صورت خودکار در ستون «سرنخ جدید» قرار می‌گیرند.</p>
<div class="kanban">
    @foreach($stages as $key => $label)
        @php($items = $columns[$key] ?? collect())
        <div class="kcol" data-stage="{{ $key }}">
            <div class="kcol-head">
                <span>{{ $label }} <span class="badge">{{ fa_number($items->count()) }}</span></span>
                <span class="sum">{{ fa_number($items->sum('deal_value')) }}</span>
            </div>
            @foreach($items as $contact)
                <div class="kcard">
                    <a href="{{ route('panel.crm.show', $contact) }}" class="title">{{ $contact->name }}</a>
                    @if($contact->organization)<div class="small muted">{{ $contact->organization }}</div>@endif
                    <div class="row between small" style="margin-top:4px">
                        <span>{{ $contact->deal_value ? toman($contact->deal_value) : '' }}</span>
                        <span class="badge">{{ \App\Models\CrmContact::SOURCES[$contact->source] ?? $contact->source }}</span>
                    </div>
                    @if($contact->open_tasks_count)<div class="small" style="color:#8a5a00">⏰ {{ fa_digits($contact->open_tasks_count) }} پیگیری باز</div>@endif
                    <form method="POST" action="{{ route('panel.crm.stage', $contact) }}" class="stage-form">@csrf @method('PATCH')
                        <select name="stage" onchange="this.form.submit()" aria-label="تغییر مرحله">
                            @foreach($stages as $k => $l)<option value="{{ $k }}" @selected($k === $key)>{{ $l }}</option>@endforeach
                        </select>
                    </form>
                </div>
            @endforeach
        </div>
    @endforeach
</div>
@endsection
