@extends('layouts.panel')
@section('title', 'داشبورد')

@section('content')
<div class="main-head">
    <h1>سلام، {{ auth()->user()->name }} 👋</h1>
    @unless($company->is_verified)<span class="badge badge-amber">شرکت شما در انتظار تأیید مدیر است</span>@endunless
</div>
<div class="grid g4">
    <a href="{{ route('panel.products.index') }}" class="stat"><span>محصولات</span><b>{{ fa_number($stats['products']) }}</b></a>
    <a href="{{ route('panel.inquiries.index') }}" class="stat"><span>پیام‌های خوانده‌نشده</span><b>{{ fa_number($stats['unread']) }}</b></a>
    <a href="{{ route('panel.quotes.index') }}" class="stat"><span>پیشنهادهای ارسالی</span><b>{{ fa_number($stats['quotes']) }}</b></a>
    <div class="stat"><span>فروش موفق (CRM)</span><b style="color:var(--ok)">{{ toman($stats['won']) }}</b></div>
</div>

<div class="card mt">
    <div class="row between"><h2 style="margin:0">قیف فروش</h2><a href="{{ route('panel.crm.index') }}" class="btn btn-ghost btn-sm">مشاهده CRM</a></div>
    <div class="grid g5 mt">
        @foreach($stages as $key => $label)
            <div class="stat" style="text-align:center">
                <span>{{ $label }}</span>
                <b>{{ fa_number($pipeline[$key]->total ?? 0) }}</b>
                <span class="small">{{ toman((int) ($pipeline[$key]->value ?? 0)) }}</span>
            </div>
        @endforeach
    </div>
</div>

<div class="grid g3 mt">
    <div class="card">
        <h3>آخرین پیام‌ها</h3>
        @forelse($inquiries as $inq)
            <a href="{{ route('panel.inquiries.show', $inq) }}" class="row between" style="padding:8px 0;border-bottom:1px solid var(--line)">
                <span @style(['font-weight:700' => ! $inq->read_at])>{{ $inq->name }}<br><span class="small muted">{{ $inq->product->name ?? '—' }}</span></span>
                <span class="small muted">{{ jdate($inq->created_at, 'd MMM') }}</span>
            </a>
        @empty
            <p class="muted small">پیامی نیست.</p>
        @endforelse
    </div>
    <div class="card">
        <h3>پیگیری‌های پیش رو</h3>
        @forelse($tasks as $task)
            <a href="{{ route('panel.crm.show', $task->contact) }}" style="display:block;padding:8px 0;border-bottom:1px solid var(--line)">
                <b>{{ $task->contact->name }}</b> — {{ \Illuminate\Support\Str::limit($task->body, 40) }}
                <div class="small {{ $task->due_at->isPast() ? 'overdue' : 'muted' }}">{{ jdate($task->due_at, 'd MMMM، HH:mm') }}</div>
            </a>
        @empty
            <p class="muted small">پیگیری بازی ندارید.</p>
        @endforelse
    </div>
    <div class="card">
        <h3>استعلام‌های جدید برای شما</h3>
        @forelse($openRfqs as $rfq)
            <a href="{{ route('rfqs.show', $rfq) }}" style="display:block;padding:8px 0;border-bottom:1px solid var(--line)">
                <b>{{ $rfq->title }}</b>
                <div class="small muted">{{ fa_number($rfq->quantity, 2) }} {{ $rfq->unit }} · {{ $rfq->city ?? '—' }}</div>
            </a>
        @empty
            <p class="muted small">استعلام جدیدی نیست.</p>
        @endforelse
    </div>
</div>
@endsection
