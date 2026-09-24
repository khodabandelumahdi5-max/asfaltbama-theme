@extends('layouts.panel')
@section('title', 'پیگیری‌ها')

@section('content')
<div class="main-head"><h1>پیگیری‌ها</h1></div>
<div class="card-flat table-wrap">
    <table>
        <thead><tr><th>موعد</th><th>مخاطب</th><th>نوع</th><th>شرح</th><th></th></tr></thead>
        <tbody>
        @forelse($tasks as $task)
            <tr>
                <td class="small {{ ! $task->done_at && $task->due_at->isPast() ? 'overdue' : '' }}">{{ jdate($task->due_at, 'd MMMM، HH:mm') }}</td>
                <td><a href="{{ route('panel.crm.show', $task->contact) }}"><b>{{ $task->contact->name }}</b></a></td>
                <td class="small">{{ \App\Models\CrmActivity::TYPES[$task->type] ?? $task->type }}</td>
                <td @style(['text-decoration:line-through;color:var(--muted)' => $task->done_at])>{{ $task->body }}</td>
                <td>
                    <form method="POST" action="{{ route('panel.crm.activities.done', $task) }}">@csrf @method('PATCH')
                        <button class="btn btn-sm {{ $task->done_at ? 'btn-ghost' : 'btn-amber' }}">{{ $task->done_at ? 'بازگشایی' : '✔ انجام شد' }}</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">پیگیری زمان‌داری ندارید. در پرونده هر مخاطب می‌توانید با «موعد پیگیری» یادآور بسازید.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="pagination">{{ $tasks->links() }}</div>
@endsection
