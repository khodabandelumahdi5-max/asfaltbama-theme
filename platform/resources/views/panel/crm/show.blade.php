@extends('layouts.panel')
@section('title', $contact->name)

@section('content')
<div class="main-head">
    <div>
        <h1>{{ $contact->name }}</h1>
        <div class="row small muted">
            @include('partials.stage-badge', ['stage' => $contact->stage])
            <span>منبع: {{ \App\Models\CrmContact::SOURCES[$contact->source] ?? $contact->source }}</span>
            <span>ایجاد: {{ jdate($contact->created_at) }}</span>
        </div>
    </div>
    <div class="row">
        @if($contact->phone)<a href="tel:{{ $contact->phone }}" class="btn btn-amber">📞 <span class="ltr">{{ $contact->phone }}</span></a>@endif
        <a href="{{ route('panel.crm.index') }}" class="btn btn-ghost">بازگشت</a>
    </div>
</div>

<div class="grid" style="grid-template-columns:1fr 1fr;align-items:start">
    <div>
        <form method="POST" action="{{ route('panel.crm.activities.store', $contact) }}" class="card mb">
            @csrf
            <h3>ثبت فعالیت</h3>
            <div class="grid g2">
                <div class="field"><label>نوع</label>
                    <select name="type">@foreach(\App\Models\CrmActivity::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
                </div>
                <div class="field"><label>موعد پیگیری (اختیاری)</label><input type="datetime-local" name="due_at" class="ltr"></div>
            </div>
            <div class="field"><textarea name="body" rows="2" placeholder="مثلاً: تماس گرفتم، نمونه قیر می‌خواهد…" required></textarea></div>
            <button class="btn">ثبت</button>
        </form>

        <div class="card">
            <h3>تاریخچه</h3>
            <ul class="timeline">
                @forelse($contact->activities as $a)
                    <li @class(['task' => $a->due_at && ! $a->done_at, 'done' => $a->done_at])>
                        <div class="row between small">
                            <b>{{ \App\Models\CrmActivity::TYPES[$a->type] ?? $a->type }}</b>
                            <span class="muted">{{ jdate($a->created_at, 'd MMM، HH:mm') }} @if($a->user)· {{ $a->user->name }}@endif</span>
                        </div>
                        <div class="txt">{{ $a->body }}</div>
                        @if($a->due_at)
                            <div class="row small">
                                <span @class(['overdue' => ! $a->done_at && $a->due_at->isPast(), 'muted' => $a->done_at || $a->due_at->isFuture()])>⏰ {{ jdate($a->due_at, 'd MMMM، HH:mm') }}</span>
                                <form method="POST" action="{{ route('panel.crm.activities.done', $a) }}">@csrf @method('PATCH')
                                    <button class="btn btn-ghost btn-sm">{{ $a->done_at ? 'بازگشایی' : '✔ انجام شد' }}</button>
                                </form>
                            </div>
                        @endif
                    </li>
                @empty
                    <li class="muted">هنوز فعالیتی ثبت نشده است.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div>
        <form method="POST" action="{{ route('panel.crm.update', $contact) }}" class="card mb">
            @csrf @method('PUT')
            <h3>اطلاعات مخاطب</h3>
            @include('panel.crm._fields')
            <button class="btn btn-amber">ذخیره</button>
        </form>

        @if($contact->inquiries->isNotEmpty())
            <div class="card mb">
                <h3>پیام‌های این مخاطب</h3>
                @foreach($contact->inquiries as $inq)
                    <a href="{{ route('panel.inquiries.show', $inq) }}" style="display:block;padding:6px 0;border-bottom:1px solid var(--line)">
                        <b class="small">{{ $inq->product->name ?? '—' }}</b> — <span class="small">{{ \Illuminate\Support\Str::limit($inq->message, 60) }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('panel.crm.destroy', $contact) }}" onsubmit="return confirm('مخاطب و تاریخچه‌اش حذف شود؟')">@csrf @method('DELETE')
            <button class="btn btn-danger btn-sm">حذف مخاطب</button>
        </form>
    </div>
</div>
<style>@media(max-width:1000px){.main>.grid[style]{grid-template-columns:1fr!important}}</style>
@endsection
